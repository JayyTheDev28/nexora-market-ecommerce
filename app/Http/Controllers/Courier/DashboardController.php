<?php

namespace App\Http\Controllers\Courier;

use App\Http\Controllers\Controller;
use App\Models\CodLedgerEntry;
use App\Models\CodRemittance;
use App\Models\CourierProfile;
use App\Models\DeliveryAttempt;
use App\Models\JobOffer;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\ProofOfDelivery;
use App\Models\RiderEarning;
use App\Services\IdempotencyService;
use App\Services\JobDispatchService;
use App\Services\RiderPayoutService;
use App\Services\ShipmentStateMachineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        protected JobDispatchService $dispatchService,
        protected RiderPayoutService $payoutService,
        protected ShipmentStateMachineService $stateMachine
    ) {}

    /**
     * Module R1 & R2 & R3: Courier Main Operational Dashboard.
     */
    public function index(Request $request): View
    {
        $rider = $request->user()->load(['courierProfile.sortingCenter', 'deliveryAreas']);
        $profile = $rider->courierProfile;

        // Auto-expire lingering pending job offers past their 60s SLA
        JobOffer::where('courier_id', $rider->id)
            ->where('status', 'pending')
            ->where('expires_at', '<', now())
            ->each(function (JobOffer $offer) {
                $this->dispatchService->declineOffer($offer, $offer->courier, false);
            });

        // 1. Pending 60s Job Offers
        $pendingOffers = JobOffer::where('courier_id', $rider->id)
            ->where('status', 'pending')
            ->where('expires_at', '>=', now())
            ->with(['order.items.product', 'order.address', 'order.primary_seller'])
            ->latest()
            ->get();

        // 2. Assigned Pickups (ready_for_pickup or assigned)
        $pickupTasks = Order::where('pickup_courier_id', $rider->id)
            ->whereIn('status', ['ready_for_pickup', 'assigned'])
            ->with(['seller.sellerProfile', 'seller.address', 'items.product'])
            ->latest('updated_at')
            ->get();

        // 3. Active Deliveries (out_for_delivery or assigned_to_rider)
        $activeDeliveries = Order::where('delivery_courier_id', $rider->id)
            ->whereIn('status', ['assigned_to_rider', 'out_for_delivery'])
            ->with(['buyer', 'address', 'items.product', 'deliveryAttempts'])
            ->latest('updated_at')
            ->get();

        // 4. Completed Deliveries
        $completedDeliveries = Order::where('delivery_courier_id', $rider->id)
            ->whereIn('status', ['delivered', 'completed'])
            ->with(['buyer', 'address', 'proofOfDelivery'])
            ->latest('delivered_at')
            ->limit(10)
            ->get();

        // 5. Earnings summaries
        $todayStart = now()->startOfDay();
        $weekStart = now()->startOfWeek();

        $todayPickupsCount = RiderEarning::where('courier_id', $rider->id)
            ->where('type', 'pickup')
            ->where('earned_at', '>=', $todayStart)
            ->count();

        $todayDeliveriesCount = RiderEarning::where('courier_id', $rider->id)
            ->where('type', 'delivery')
            ->where('earned_at', '>=', $todayStart)
            ->count();

        $todayEarnings = ($todayPickupsCount * 30.00) + ($todayDeliveriesCount * 50.00);

        $weeklyEarnings = RiderEarning::where('courier_id', $rider->id)
            ->where('earned_at', '>=', $weekStart)
            ->sum('amount');

        $activeJobsCount = $profile ? $profile->activeJobsCount() : 0;

        $stats = [
            'is_online' => $profile ? $profile->is_online : false,
            'active_jobs' => $activeJobsCount,
            'max_jobs' => 3,
            'unremitted_cod' => $profile ? (float) $profile->unremitted_cod_balance : 0.00,
            'earnings_balance' => $profile ? (float) $profile->earnings_balance : 0.00,
            'today_earnings' => $todayEarnings,
            'today_pickups' => $todayPickupsCount,
            'today_deliveries' => $todayDeliveriesCount,
            'weekly_earnings' => (float) $weeklyEarnings,
            'assigned_areas' => $rider->deliveryAreas->pluck('name')->all(),
        ];

        return view('courier.dashboard', compact(
            'rider',
            'profile',
            'pendingOffers',
            'pickupTasks',
            'activeDeliveries',
            'completedDeliveries',
            'stats'
        ));
    }

    /**
     * Module R2: Toggle Rider Online / Offline Availability.
     */
    public function toggleOnline(Request $request): RedirectResponse|JsonResponse
    {
        $rider = $request->user();
        $profile = $rider->courierProfile;
        abort_unless($profile, 400, 'Courier profile not found.');

        $isOnline = (bool) $request->input('is_online', ! $profile->is_online);

        $profile->update(['is_online' => $isOnline]);

        $message = $isOnline
            ? 'You are now ONLINE. You will receive real-time job dispatch offers.'
            : 'You are now OFFLINE. Existing assigned parcels remain active and deliverable.';

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'is_online' => $isOnline,
                'message' => $message,
            ]);
        }

        return back()->with('status', $message);
    }

    /**
     * Module R1: Update Vehicle Details & Flag for Re-verification.
     */
    public function updateVehicle(Request $request): RedirectResponse
    {
        $rider = $request->user();
        $profile = $rider->courierProfile;
        abort_unless($profile, 400, 'Courier profile not found.');

        $validated = $request->validate([
            'vehicle_type' => ['required', 'string', 'in:Motorcycle,Van,Sedan,Truck,Bicycle'],
            'plate_number' => ['required', 'string', 'max:50'],
            'or_cr_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        // Clean & trim plate number
        $trimmedPlate = trim(strtoupper($validated['plate_number']));

        // Case-insensitive, space-trimmed duplicate plate validation
        $duplicate = CourierProfile::where('user_id', '!=', $rider->id)
            ->where(function ($q) use ($trimmedPlate) {
                $q->whereRaw('UPPER(TRIM(plate_number)) = ?', [$trimmedPlate])
                  ->orWhereRaw('UPPER(TRIM(pending_plate_number)) = ?', [$trimmedPlate]);
            })
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'plate_number' => "The plate number '{$trimmedPlate}' is already registered to another vehicle in the fleet.",
            ]);
        }

        $updates = [
            'pending_vehicle_type' => $validated['vehicle_type'],
            'pending_plate_number' => $trimmedPlate,
            'vehicle_verification_status' => 'pending',
        ];

        if ($request->hasFile('or_cr_file')) {
            $path = $request->file('or_cr_file')->store("couriers/{$rider->id}/vehicle_docs", 'public');
            $updates['pending_or_cr_path'] = $path;
        }

        $profile->update($updates);

        return back()->with('status', "Vehicle details submitted for Logistics re-verification. Status set to Pending.");
    }

    /**
     * Module R3: Accept Job Offer within 60-second SLA.
     */
    public function acceptOffer(Request $request, JobOffer $offer): RedirectResponse|JsonResponse
    {
        $idempotencyKey = $request->input('idempotency_key') ?? $request->header('Idempotency-Key');

        return IdempotencyService::run($idempotencyKey, $request->user()->id, "accept_offer_{$offer->id}", function () use ($request, $offer) {
            $result = $this->dispatchService->acceptOffer($offer, $request->user());

            if (! $result['success']) {
                if ($request->wantsJson()) {
                    return response()->json($result, 422);
                }
                return back()->withErrors(['offer' => $result['message']]);
            }

            if ($request->wantsJson()) {
                return response()->json($result);
            }

            return back()->with('status', $result['message']);
        });
    }

    /**
     * Module R3: Decline Job Offer.
     */
    public function declineOffer(Request $request, JobOffer $offer): RedirectResponse|JsonResponse
    {
        $idempotencyKey = $request->input('idempotency_key') ?? $request->header('Idempotency-Key');

        return IdempotencyService::run($idempotencyKey, $request->user()->id, "decline_offer_{$offer->id}", function () use ($request, $offer) {
            $result = $this->dispatchService->declineOffer($offer, $request->user(), true);

            if ($request->wantsJson()) {
                return response()->json($result);
            }

            return back()->with('status', $result['message']);
        });
    }

    /**
     * Module R4: Confirm Pickup via Barcode Scan or Manual Tracking Number Entry.
     */
    public function confirmPickup(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'tracking_number' => ['required', 'string'],
            'idempotency_key' => ['nullable', 'string'],
        ]);

        $rider = $request->user();
        $trackingNumber = trim($validated['tracking_number']);
        $idempotencyKey = $validated['idempotency_key'] ?? $request->header('Idempotency-Key');

        return IdempotencyService::run($idempotencyKey, $rider->id, "pickup_{$trackingNumber}", function () use ($request, $rider, $trackingNumber) {
            $order = Order::where(function ($q) use ($trackingNumber) {
                $q->where('tracking_number', $trackingNumber)
                  ->orWhere('order_number', $trackingNumber);
            })->first();

            if (! $order) {
                $msg = "Order with tracking number '{$trackingNumber}' not found.";
                return $request->wantsJson()
                    ? response()->json(['success' => false, 'message' => $msg], 404)
                    : back()->withErrors(['tracking_number' => $msg]);
            }

            abort_unless((int) $order->pickup_courier_id === (int) $rider->id, 403, 'You are not assigned as the pickup courier for this parcel.');

            if ($order->status === 'picked_up') {
                $msg = "Parcel {$order->order_number} is already marked as Picked Up.";
                return $request->wantsJson() ? response()->json(['success' => true, 'message' => $msg]) : back()->with('info', $msg);
            }

            abort_unless(in_array($order->status, ['ready_for_pickup', 'assigned'], true), 422, "Cannot confirm pickup for parcel in status '{$order->status}'.");

            // Transition state to picked_up
            $this->stateMachine->transition($order, 'picked_up', $rider, 'Picked up from merchant seller.');

            // Credit ₱30.00 pickup pay
            $this->payoutService->creditPickupPay($rider, $order);

            $msg = "Parcel {$order->order_number} confirmed as Picked Up! ₱30.00 pickup pay credited to your balance.";

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $msg,
                    'order' => $order,
                ]);
            }

            return back()->with('status', $msg);
        });
    }

    /**
     * Module R5: Complete Delivery with POD Image & 6-Digit Delivery OTP.
     */
    public function completeDelivery(Request $request, Order $order): RedirectResponse|JsonResponse
    {
        $rider = $request->user();
        abort_unless((int) $order->delivery_courier_id === (int) $rider->id, 403, 'You are not assigned to deliver this parcel.');

        // Enforce sequential workflow: Must be Out for Delivery
        abort_unless($order->status === 'out_for_delivery', 422, "Order must be Out for Delivery to complete delivery. Current status: {$order->status}.");

        $validated = $request->validate([
            'delivery_otp' => ['required', 'string', 'digits:6'],
            'pod_image' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
            'notes' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['nullable', 'string'],
        ]);

        $idempotencyKey = $validated['idempotency_key'] ?? $request->header('Idempotency-Key');

        return IdempotencyService::run($idempotencyKey, $rider->id, "delivery_complete_{$order->id}", function () use ($request, $rider, $order, $validated) {
            // Check brute-force OTP lockout (5 failed attempts -> 15 min lock)
            if ($order->isDeliveryOtpLocked()) {
                $minutesLeft = now()->diffInMinutes($order->delivery_otp_locked_until) + 1;
                $msg = "Delivery OTP verification is locked for {$minutesLeft} more minute(s) due to 5 consecutive failed attempts.";
                return $request->wantsJson()
                    ? response()->json(['success' => false, 'message' => $msg], 422)
                    : back()->withErrors(['delivery_otp' => $msg]);
            }

            // Verify 6-digit OTP
            if (! $order->verifyDeliveryOtp($validated['delivery_otp'])) {
                $failedCount = $order->delivery_otp_failed_attempts;
                $msg = $failedCount >= 5
                    ? "Invalid OTP. You have reached 5 failed attempts! Verification locked for 15 minutes."
                    : "Invalid Delivery OTP. ({$failedCount}/5 failed attempts). Please check the code with the buyer.";

                return $request->wantsJson()
                    ? response()->json(['success' => false, 'message' => $msg], 422)
                    : back()->withErrors(['delivery_otp' => $msg]);
            }

            // Binary & mime validation for POD image
            $file = $request->file('pod_image');
            $podPath = $file->store("couriers/{$rider->id}/pod", 'public');

            // Store Proof of Delivery
            ProofOfDelivery::create([
                'order_id' => $order->id,
                'courier_id' => $rider->id,
                'photo_path' => $podPath,
                'verified_otp' => $validated['delivery_otp'],
                'notes' => $validated['notes'] ?? null,
                'delivered_at' => now(),
            ]);

            // Transition status to delivered
            $this->stateMachine->transition($order, 'delivered', $rider, 'Delivered directly to recipient with verified OTP and POD.');

            // Credit ₱50.00 delivery pay
            $this->payoutService->creditDeliveryPay($rider, $order);

            // Record collected COD if applicable
            $this->payoutService->recordCodCollection($rider, $order);

            $msg = "Parcel {$order->order_number} marked as Delivered! ₱50.00 pay credited to your balance.";
            if (strtolower($order->payment_method) === 'cod') {
                $msg .= " ₱" . number_format($order->total, 2) . " COD collected and added to your unremitted balance.";
            }

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $msg,
                    'order' => $order,
                ]);
            }

            return back()->with('status', $msg);
        });
    }

    /**
     * Module R6: Failed Delivery Management.
     */
    public function failDelivery(Request $request, Order $order): RedirectResponse|JsonResponse
    {
        $rider = $request->user();
        abort_unless((int) $order->delivery_courier_id === (int) $rider->id, 403, 'You are not assigned to deliver this parcel.');
        abort_unless($order->status === 'out_for_delivery', 422, 'Order must be Out for Delivery to record a failure.');

        $validated = $request->validate([
            'reason' => ['required', 'string', 'in:buyer_not_home,wrong_address,refused'],
            'notes' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['nullable', 'string'],
        ]);

        $idempotencyKey = $validated['idempotency_key'] ?? $request->header('Idempotency-Key');

        return IdempotencyService::run($idempotencyKey, $rider->id, "delivery_fail_{$order->id}", function () use ($request, $rider, $order, $validated) {
            $reason = $validated['reason'];
            $notes = $validated['notes'] ?? null;

            // Record attempt
            DeliveryAttempt::create([
                'order_id' => $order->id,
                'courier_id' => $rider->id,
                'result' => 'failed',
                'failure_reason' => $reason,
                'notes' => $notes,
                'attempted_at' => now(),
            ]);

            $attemptCount = $order->deliveryAttempts()->where('result', 'failed')->count();

            // Return logic: Instantly route orders with reason 'refused' or attempts >= 3 to return_to_sender
            if ($reason === 'refused' || $attemptCount >= 3) {
                $newStatus = 'return_to_sender';
                $statusNote = $reason === 'refused'
                    ? "Customer refused delivery. Parcel automatically routed to Return to Sender."
                    : "Reached 3 failed delivery attempts. Parcel automatically routed to Return to Sender.";
            } else {
                $newStatus = 'failed_delivery';
                $statusNote = "Delivery attempt {$attemptCount}/3 failed: {$reason}. " . ($notes ?? '');
            }

            $order->update(['status' => $newStatus]);

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status' => $newStatus,
                'changed_by' => $rider->id,
                'note' => $statusNote,
                'changed_at' => now(),
            ]);

            $msg = ($newStatus === 'return_to_sender')
                ? "Parcel marked as Return to Sender (Attempts: {$attemptCount}, Reason: {$reason})."
                : "Delivery failure recorded (Attempt {$attemptCount}/3). ₱0.00 pay credited.";

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $msg,
                    'status' => $newStatus,
                    'attempt_count' => $attemptCount,
                ]);
            }

            return back()->with('status', $msg);
        });
    }

    /**
     * Module R7: History, Earnings Breakdown & COD Reconciliation Dashboard.
     */
    public function earnings(Request $request): View
    {
        $rider = $request->user()->load('courierProfile');
        $profile = $rider->courierProfile;

        // Date Filter
        $startDate = $request->input('start_date') ? now()->parse($request->input('start_date'))->startOfDay() : now()->subDays(30)->startOfDay();
        $endDate = $request->input('end_date') ? now()->parse($request->input('end_date'))->endOfDay() : now()->endOfDay();

        // 1. Handled Orders History
        $handledOrders = Order::where(function ($q) use ($rider) {
            $q->where('pickup_courier_id', $rider->id)
              ->orWhere('delivery_courier_id', $rider->id);
        })
        ->whereBetween('updated_at', [$startDate, $endDate])
        ->with(['buyer', 'seller', 'riderEarnings' => fn ($q) => $q->where('courier_id', $rider->id)])
        ->latest('updated_at')
        ->paginate(15);

        // 2. Earnings Aggregates
        $pickupsCount = RiderEarning::where('courier_id', $rider->id)
            ->where('type', 'pickup')
            ->whereBetween('earned_at', [$startDate, $endDate])
            ->count();

        $deliveriesCount = RiderEarning::where('courier_id', $rider->id)
            ->where('type', 'delivery')
            ->whereBetween('earned_at', [$startDate, $endDate])
            ->count();

        $failedAttemptsCount = DeliveryAttempt::where('courier_id', $rider->id)
            ->where('result', 'failed')
            ->whereBetween('attempted_at', [$startDate, $endDate])
            ->count();

        $calculatedTotalPay = ($pickupsCount * 30.00) + ($deliveriesCount * 50.00) + ($failedAttemptsCount * 0.00);

        // 3. COD Reconciliation
        $codLedger = CodLedgerEntry::where('courier_id', $rider->id)
            ->with('order')
            ->latest()
            ->limit(20)
            ->get();

        $remittances = CodRemittance::where('courier_id', $rider->id)
            ->latest('remitted_at')
            ->get();

        $unremittedCod = $profile ? (float) $profile->unremitted_cod_balance : 0.00;

        return view('courier.earnings', compact(
            'rider',
            'profile',
            'handledOrders',
            'pickupsCount',
            'deliveriesCount',
            'failedAttemptsCount',
            'calculatedTotalPay',
            'codLedger',
            'remittances',
            'unremittedCod',
            'startDate',
            'endDate'
        ));
    }

    /**
     * Module R7: Submit EOD COD Remittance.
     */
    public function submitRemittance(Request $request): RedirectResponse|JsonResponse
    {
        $rider = $request->user();
        $profile = $rider->courierProfile;
        abort_unless($profile, 400);

        $unremitted = (float) $profile->unremitted_cod_balance;
        abort_unless($unremitted > 0, 422, 'You have no unremitted COD balance to submit.');

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', "max:{$unremitted}"],
            'notes' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['nullable', 'string'],
        ]);

        $idempotencyKey = $validated['idempotency_key'] ?? $request->header('Idempotency-Key');

        return IdempotencyService::run($idempotencyKey, $rider->id, "cod_remittance_{$rider->id}_" . date('Ymd'), function () use ($request, $rider, $validated) {
            $remittance = $this->payoutService->submitRemittance(
                $rider,
                (float) $validated['amount'],
                $validated['notes'] ?? null
            );

            $msg = "COD Remittance request #{$remittance->remittance_number} submitted (₱" . number_format($remittance->amount, 2) . ") for Logistics verification.";

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $msg,
                    'remittance' => $remittance,
                ]);
            }

            return back()->with('status', $msg);
        });
    }

    /**
     * Update delivery status action (backward-compatibility wrapper).
     */
    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $action = $request->input('action');
        if ($action === 'out_for_delivery') {
            abort_unless((int) $order->delivery_courier_id === (int) $request->user()->id, 403);
            $this->stateMachine->transition($order, 'out_for_delivery', $request->user(), 'Rider departed for delivery.');
            return back()->with('status', "Parcel {$order->order_number} is now Out for Delivery. Buyer OTP generated.");
        }

        if ($action === 'picked_up') {
            $request->merge(['tracking_number' => $order->tracking_number ?: $order->order_number]);
            return $this->confirmPickup($request);
        }

        return back();
    }
}
