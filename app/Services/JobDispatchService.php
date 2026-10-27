<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\JobOffer;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use Illuminate\Support\Collection;

class JobDispatchService
{
    /**
     * Dispatch an order to the nearest eligible online rider belonging to the logistics company.
     * Each offer has a strict 60-second timer.
     */
    public function dispatchOrder(Order $order, string $type = 'pickup', int $consecutiveDeclines = 0): ?JobOffer
    {
        // Cancel any lingering pending offers for this order
        JobOffer::where('order_id', $order->id)
            ->where('status', 'pending')
            ->update(['status' => 'expired']);

        // Check 3-decline limit: move to unassigned queue for manual logistics handling
        if ($consecutiveDeclines >= 3) {
            $order->update([
                $type === 'pickup' ? 'pickup_courier_id' : 'delivery_courier_id' => null,
            ]);

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status' => $order->status,
                'changed_by' => auth()->id() ?? $order->sorting_center_id,
                'note' => "Dispatched to 3 riders without acceptance. Moved to Unassigned queue for manual dispatch.",
                'changed_at' => now(),
            ]);

            return null;
        }

        // Get riders already offered in this sequence
        $alreadyOfferedRiderIds = JobOffer::where('order_id', $order->id)
            ->whereIn('status', ['declined', 'expired'])
            ->where('created_at', '>=', now()->subHours(2))
            ->pluck('courier_id')
            ->all();

        // Find eligible riders
        $candidate = User::query()
            ->where('role', 'courier')
            ->where('approval_status', 'approved')
            ->where('account_status', 'active')
            ->whereHas('courierProfile', function ($query) use ($order) {
                $query->where('is_online', true);
                if ($order->sorting_center_id) {
                    $query->where('sorting_center_id', $order->sorting_center_id);
                }
            })
            ->whereNotIn('id', $alreadyOfferedRiderIds)
            ->with('courierProfile')
            ->get()
            ->first(function (User $rider) {
                return $rider->courierProfile && $rider->courierProfile->activeJobsCount() < 3;
            });

        if (! $candidate) {
            // No eligible online rider found right now
            return null;
        }

        $pickupAddress = $order->seller_address
            ? "{$order->seller_address->street}, {$order->seller_address->city}"
            : ($order->primary_seller?->first_name . ' Store');

        $dropoffAddress = $order->address
            ? "{$order->address->street}, {$order->address->city}, {$order->address->province}"
            : 'Buyer Delivery Address';

        $expectedPay = $type === 'pickup' ? 30.00 : 50.00;
        $codAmount = ($order->payment_method === 'cod') ? (float) $order->total : 0.00;

        return JobOffer::create([
            'order_id' => $order->id,
            'courier_id' => $candidate->id,
            'type' => $type,
            'status' => 'pending',
            'pickup_location' => $pickupAddress,
            'dropoff_location' => $dropoffAddress,
            'cod_amount' => $codAmount,
            'expected_pay' => $expectedPay,
            'expires_at' => now()->addSeconds(60),
            'consecutive_declines_count' => $consecutiveDeclines,
        ]);
    }

    /**
     * Accept a job offer within the 60-second SLA window.
     */
    public function acceptOffer(JobOffer $offer, User $courier): array
    {
        if ((int) $offer->courier_id !== (int) $courier->id) {
            return ['success' => false, 'message' => 'Unauthorized job offer.'];
        }

        if ($offer->isExpired()) {
            $offer->update(['status' => 'expired']);
            $this->handleDeclinedOrExpired($offer);
            return ['success' => false, 'message' => 'Offer expired (60-second limit reached). The job has been re-dispatched.'];
        }

        if ($offer->status !== 'pending') {
            return ['success' => false, 'message' => 'This offer has already been resolved.'];
        }

        // Check max 3 active jobs limit
        if ($courier->courierProfile && $courier->courierProfile->activeJobsCount() >= 3) {
            return ['success' => false, 'message' => 'Rider capacity reached (maximum 3 active jobs). Finish a delivery before accepting more.'];
        }

        $order = $offer->order;

        if ($offer->type === 'pickup') {
            $order->update([
                'pickup_courier_id' => $courier->id,
                'status' => 'assigned',
            ]);
            $statusText = 'assigned';
            $note = "Rider {$courier->full_name} accepted pickup job offer.";
        } else {
            $order->update([
                'delivery_courier_id' => $courier->id,
                'status' => 'assigned_to_rider',
            ]);
            $statusText = 'assigned_to_rider';
            $note = "Rider {$courier->full_name} accepted delivery job offer.";
        }

        $offer->update(['status' => 'accepted']);

        // Cancel any other pending offers for this order
        JobOffer::where('order_id', $order->id)
            ->where('id', '!=', $offer->id)
            ->where('status', 'pending')
            ->update(['status' => 'expired']);

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'status' => $statusText,
            'changed_by' => $courier->id,
            'note' => $note,
            'changed_at' => now(),
        ]);

        return ['success' => true, 'message' => 'Job offer accepted successfully!'];
    }

    /**
     * Decline a job offer or trigger re-dispatch on expiration.
     */
    public function declineOffer(JobOffer $offer, User $courier, bool $isManualDecline = true): array
    {
        if ((int) $offer->courier_id !== (int) $courier->id) {
            return ['success' => false, 'message' => 'Unauthorized job offer.'];
        }

        if ($offer->status !== 'pending' && $offer->status !== 'expired') {
            return ['success' => false, 'message' => 'Offer has already been processed.'];
        }

        $offer->update([
            'status' => $isManualDecline ? 'declined' : 'expired',
        ]);

        $nextOffer = $this->handleDeclinedOrExpired($offer);

        return [
            'success' => true,
            'message' => $isManualDecline
                ? 'Offer declined. Routing to next eligible rider.'
                : 'Offer expired after 60s. Routing to next eligible rider.',
            'next_offer' => $nextOffer,
        ];
    }

    /**
     * Handle declined or expired offer: routes to next nearest rider or unassigned queue.
     */
    public function handleDeclinedOrExpired(JobOffer $offer): ?JobOffer
    {
        $newDeclinesCount = $offer->consecutive_declines_count + 1;
        return $this->dispatchOrder($offer->order, $offer->type, $newDeclinesCount);
    }
}
