<?php

namespace App\Http\Controllers\Api\Mobile\Rider;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Mobile\RiderProfileResource;
use App\Http\Resources\Api\Mobile\UserResource;
use App\Models\AuditLog;
use App\Models\CodLedgerEntry;
use App\Models\CodRemittance;
use App\Models\DeliveryAttempt;
use App\Models\JobOffer;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\ProofOfDelivery;
use App\Models\RiderEarning;
use App\Models\User;
use App\Services\IdempotencyService;
use App\Services\JobDispatchService;
use App\Services\RiderPayoutService;
use App\Services\ShipmentStateMachineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RiderController extends Controller
{
    public function __construct(
        protected JobDispatchService $dispatchService,
        protected RiderPayoutService $payoutService,
        protected ShipmentStateMachineService $stateMachine
    ) {}

    /**
     * GET /api/mobile/v1/rider/profile
     */
    public function profile(Request $request): JsonResponse
    {
        /** @var User $rider */
        $rider = $request->user();
        $profile = $rider->courierProfile()->with(['sortingCenter', 'logisticsCompany'])->first();

        $reviewsQuery = \App\Models\Review::whereHas('order', fn ($q) => $q->where('delivery_courier_id', $rider->id));
        $reviewsCount = $reviewsQuery->count();
        $avgRating = $reviewsCount > 0 ? (float) round($reviewsQuery->avg('rating'), 2) : 5.0;

        $recentReviews = \App\Models\Review::whereHas('order', fn ($q) => $q->where('delivery_courier_id', $rider->id))
            ->with(['order', 'buyer'])
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'rating' => (float) $r->rating,
                'comment' => $r->comment ?? '',
                'buyer_name' => $r->buyer?->full_name ?? 'Customer',
                'order_number' => $r->order?->order_number ?? '',
                'created_at' => $r->created_at?->toIso8601String(),
            ]);

        $totalDeliveries = Order::where('delivery_courier_id', $rider->id)
            ->whereIn('status', ['delivered', 'completed'])
            ->count();

        return response()->json([
            'data' => [
                'user' => new UserResource($rider),
                'courier_profile' => $profile ? new RiderProfileResource($profile) : null,
                'is_online' => (bool) ($profile?->is_online ?? false),
                'active_jobs_count' => $profile?->activeJobsCount() ?? 0,
                'earnings_balance' => (float) ($profile?->earnings_balance ?? 0),
                'unremitted_cod_balance' => (float) ($profile?->unremitted_cod_balance ?? 0),
                'rating' => $avgRating,
                'reviews_count' => $reviewsCount,
                'total_deliveries' => $totalDeliveries,
                'recent_reviews' => $recentReviews,
            ],
            'message' => 'Rider profile retrieved successfully.',
        ]);
    }

    /**
     * POST /api/mobile/v1/rider/availability
     */
    public function toggleAvailability(Request $request): JsonResponse
    {
        /** @var User $rider */
        $rider = $request->user();
        $profile = $rider->courierProfile;

        if (! $profile) {
            return response()->json([
                'message' => 'Courier profile not found for this account.',
                'errors' => ['profile' => ['Courier profile not found.']],
            ], 400);
        }

        $isOnline = $request->has('is_online')
            ? (bool) $request->input('is_online')
            : ! $profile->is_online;

        $profile->update(['is_online' => $isOnline]);

        return response()->json([
            'data' => [
                'is_online' => $isOnline,
            ],
            'message' => $isOnline ? 'Online on auto-dispatch' : 'Offline',
        ]);
    }

    /**
     * GET /api/mobile/v1/rider/hubs
     */
    public function hubs(Request $request): JsonResponse
    {
        /** @var User $rider */
        $rider = $request->user();
        $profile = $rider->courierProfile;

        $query = \App\Models\Hub::where('is_active', true);
        if ($profile && $profile->logistics_company_id) {
            $query->where('logistics_company_id', $profile->logistics_company_id);
        }

        $hubs = $query->get()->map(fn ($h) => [
            'id' => $h->id,
            'name' => $h->name,
            'code' => $h->code,
            'city_name' => $h->city_name,
            'province_name' => $h->province_name,
            'street_address' => $h->street_address,
            'full_location' => $h->full_location,
            'is_selected' => $profile && ($profile->sorting_center_id == $h->id),
        ]);

        return response()->json([
            'data' => $hubs,
            'message' => 'Hubs retrieved successfully.',
        ]);
    }

    /**
     * POST /api/mobile/v1/rider/hub/select
     */
    public function selectHub(Request $request): JsonResponse
    {
        /** @var User $rider */
        $rider = $request->user();
        $profile = $rider->courierProfile;

        if (! $profile) {
            return response()->json(['message' => 'Courier profile not found.'], 404);
        }

        $validated = $request->validate([
            'hub_id' => ['required', 'integer', 'exists:hubs,id'],
        ]);

        $hub = \App\Models\Hub::findOrFail($validated['hub_id']);
        $profile->update([
            'sorting_center_id' => $hub->id,
        ]);

        return response()->json([
            'data' => [
                'hub_id' => $hub->id,
                'hub_name' => $hub->name,
                'hub_code' => $hub->code,
            ],
            'message' => "Assigned to {$hub->name}.",
        ]);
    }

    /**
     * GET /api/mobile/v1/rider/offers
     */
    public function offers(Request $request): JsonResponse
    {
        /** @var User $rider */
        $rider = $request->user();

        $offers = JobOffer::where('courier_id', $rider->id)
            ->where('status', 'pending')
            ->where('expires_at', '>=', now())
            ->with(['order.items.product'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($o) {
                return [
                    'id' => $o->id,
                    'order_id' => $o->order_id,
                    'order_number' => $o->order?->order_number,
                    'type' => $o->type,
                    'pickup_location' => $o->pickup_location,
                    'dropoff_location' => $o->dropoff_location,
                    'cod_amount' => (float) $o->cod_amount,
                    'expected_pay' => (float) $o->expected_pay,
                    'expires_in_seconds' => max(0, (int) now()->diffInSeconds($o->expires_at, false)),
                    'items_count' => $o->order?->items?->count() ?? 0,
                    'order' => $o->order ? [
                        'id' => $o->order->id,
                        'order_number' => $o->order->order_number,
                        'tracking_number' => $o->order->tracking_number,
                        'total' => (float) $o->order->total,
                        'payment_method' => $o->order->payment_method,
                        'recipient_name' => $o->order->recipient_name,
                        'recipient_phone' => $o->order->recipient_phone,
                    ] : null,
                ];
            });

        return response()->json([
            'data' => $offers,
            'message' => 'Active job offers retrieved.',
        ]);
    }

    /**
     * POST /api/mobile/v1/rider/offers/{offer}/accept
     */
    public function acceptOffer(Request $request, JobOffer $offer): JsonResponse
    {
        /** @var User $rider */
        $rider = $request->user();

        $key = $request->header('Idempotency-Key') ?? $request->input('idempotency_key');

        return IdempotencyService::run($key, $rider->id, "mobile_accept_offer_{$offer->id}", function () use ($offer, $rider) {
            $result = $this->dispatchService->acceptOffer($offer, $rider);

            if (! ($result['success'] ?? false)) {
                $status = ($offer->isExpired() || $offer->status !== 'pending') ? 409 : 422;
                return response()->json([
                    'message' => $result['message'] ?? 'Unable to accept job offer.',
                    'errors' => [
                        'offer' => [$result['message'] ?? 'Unable to accept job offer.'],
                    ],
                ], $status);
            }

            return response()->json([
                'data' => [
                    'offer_id' => $offer->id,
                    'order_id' => $offer->order_id,
                    'status' => 'accepted',
                ],
                'message' => $result['message'] ?? 'Job offer accepted successfully!',
            ]);
        });
    }

    /**
     * POST /api/mobile/v1/rider/offers/{offer}/decline
     */
    public function declineOffer(Request $request, JobOffer $offer): JsonResponse
    {
        /** @var User $rider */
        $rider = $request->user();

        $result = $this->dispatchService->declineOffer($offer, $rider, true);

        return response()->json([
            'data' => [
                'offer_id' => $offer->id,
                'status' => 'declined',
            ],
            'message' => $result['message'] ?? 'Job offer declined.',
        ]);
    }

    /**
     * POST /api/mobile/v1/rider/pickup
     */
    public function confirmPickup(Request $request): JsonResponse
    {
        /** @var User $rider */
        $rider = $request->user();

        $validated = $request->validate([
            'tracking_number' => ['required', 'string'],
        ]);

        $key = $request->header('Idempotency-Key') ?? $request->input('idempotency_key');
        $trackingNumber = trim($validated['tracking_number']);

        return IdempotencyService::run($key, $rider->id, "mobile_pickup_{$trackingNumber}", function () use ($rider, $trackingNumber) {
            $order = Order::where('tracking_number', $trackingNumber)
                ->orWhere('order_number', $trackingNumber)
                ->first();

            if (! $order) {
                return response()->json([
                    'message' => 'Order not found for tracking or order number.',
                    'errors' => ['tracking_number' => ['Order not found.']],
                ], 404);
            }

            if ((int) $order->pickup_courier_id !== (int) $rider->id) {
                return response()->json([
                    'message' => 'You are not assigned as the pickup courier for this parcel.',
                    'errors' => ['courier' => ['You are not assigned as the pickup courier for this parcel.']],
                ], 403);
            }

            if ($order->status === 'picked_up') {
                return response()->json([
                    'data' => [
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                        'tracking_number' => $order->tracking_number,
                        'order_status' => 'picked_up',
                    ],
                    'message' => 'Already marked as Picked Up.',
                ]);
            }

            if (! in_array($order->status, ['ready_for_pickup', 'assigned'], true)) {
                return response()->json([
                    'message' => "Cannot pickup order in status {$order->status}.",
                    'errors' => ['status' => ["Cannot pickup order in status {$order->status}."]],
                ], 422);
            }

            $this->stateMachine->transition($order, 'picked_up', $rider, 'Picked up from merchant seller.');
            $this->payoutService->creditPickupPay($rider, $order);

            return response()->json([
                'data' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'tracking_number' => $order->tracking_number,
                    'order_status' => 'picked_up',
                    'earned_pay' => 30.00,
                ],
                'message' => "Order {$order->order_number} marked as Picked Up. ₱30.00 pay credited.",
            ]);
        });
    }

    /**
     * POST /api/mobile/v1/rider/deliveries/{order}/pickup
     */
    public function confirmPickupOrder(Request $request, Order $order): JsonResponse
    {
        $request->merge(['tracking_number' => $order->tracking_number]);
        return $this->confirmPickup($request);
    }

    /**
     * GET /api/mobile/v1/rider/deliveries/active
     */
    public function activeDeliveries(Request $request): JsonResponse
    {
        /** @var User $rider */
        $rider = $request->user();

        $orders = Order::where(function ($query) use ($rider) {
            $query->where(function ($q) use ($rider) {
                $q->where('pickup_courier_id', $rider->id)
                    ->whereIn('status', ['ready_for_pickup', 'assigned']);
            })->orWhere(function ($q) use ($rider) {
                $q->where('delivery_courier_id', $rider->id)
                    ->whereIn('status', ['assigned_to_rider', 'out_for_delivery']);
            });
        })
            ->with(['items.product', 'buyer', 'seller.address', 'address'])
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(fn ($order) => $this->formatRiderOrder($order, $rider));

        return response()->json([
            'data' => $orders,
            'message' => 'Active deliveries retrieved.',
        ]);
    }

    /**
     * GET /api/mobile/v1/rider/deliveries/history
     */
    public function deliveryHistory(Request $request): JsonResponse
    {
        /** @var User $rider */
        $rider = $request->user();

        $orders = Order::where(function ($query) use ($rider) {
            $query->where('pickup_courier_id', $rider->id)
                ->orWhere('delivery_courier_id', $rider->id);
        })
            ->whereIn('status', ['delivered', 'failed_delivery', 'return_to_sender', 'completed'])
            ->with(['items.product'])
            ->orderBy('updated_at', 'desc')
            ->paginate(15);

        return response()->json([
            'data' => $orders->getCollection()->map(fn ($o) => $this->formatRiderOrder($o, $rider)),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
            'message' => 'Delivery history retrieved.',
        ]);
    }

    /**
     * GET /api/mobile/v1/rider/deliveries/{order}
     */
    public function showDelivery(Request $request, Order $order): JsonResponse
    {
        /** @var User $rider */
        $rider = $request->user();

        return response()->json([
            'data' => $this->formatRiderOrder($order, $rider),
            'message' => 'Delivery details retrieved.',
        ]);
    }

    /**
     * POST /api/mobile/v1/rider/deliveries/{order}/complete
     */
    public function completeDelivery(Request $request, Order $order): JsonResponse
    {
        /** @var User $rider */
        $rider = $request->user();

        if ((int) $order->delivery_courier_id !== (int) $rider->id) {
            return response()->json([
                'message' => 'You are not assigned to this delivery.',
                'errors' => ['courier' => ['You are not assigned to this delivery.']],
            ], 403);
        }

        if ($order->status !== 'out_for_delivery') {
            return response()->json([
                'message' => "Order must be Out for Delivery. Current status: {$order->status}.",
                'errors' => ['status' => ["Order must be Out for Delivery. Current status: {$order->status}."]],
            ], 422);
        }

        $validated = $request->validate([
            'delivery_otp' => ['required', 'string', 'digits:6'],
            'pod_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
            'pod_photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $photoFile = $request->file('pod_image') ?? $request->file('pod_photo');
        if (! $photoFile) {
            return response()->json([
                'message' => 'The pod_image or pod_photo field is required.',
                'errors' => ['pod_photo' => ['Proof of delivery photo is required.']],
            ], 422);
        }

        $key = $request->header('Idempotency-Key') ?? $request->input('idempotency_key');

        return IdempotencyService::run($key, $rider->id, "mobile_complete_delivery_{$order->id}", function () use ($request, $rider, $order, $validated, $photoFile) {
            if ($order->isDeliveryOtpLocked() || $order->delivery_otp_failed_attempts >= 3) {
                if (! $order->isDeliveryOtpLocked()) {
                    $order->update(['delivery_otp_locked_until' => now()->addMinutes(15)]);
                }
                $minutes = now()->diffInMinutes($order->delivery_otp_locked_until) + 1;
                return response()->json([
                    'message' => "OTP verification locked for {$minutes} minutes due to 3 consecutive failed attempts. Please report delivery failure.",
                    'errors' => ['delivery_otp' => ["OTP verification locked for {$minutes} minutes."]],
                ], 422);
            }

            if (! $order->verifyDeliveryOtp($validated['delivery_otp'])) {
                $failed = $order->delivery_otp_failed_attempts;
                if ($failed >= 3) {
                    $order->update(['delivery_otp_locked_until' => now()->addMinutes(15)]);
                }
                return response()->json([
                    'message' => "Invalid Delivery OTP ({$failed}/3 failed attempts).",
                    'errors' => ['delivery_otp' => ["Invalid Delivery OTP ({$failed}/3 failed attempts)."]],
                    'data' => ['failed_attempts' => $failed],
                ], 422);
            }

            $podPath = $photoFile->store("couriers/{$rider->id}/pod", 'public');

            ProofOfDelivery::create([
                'order_id' => $order->id,
                'courier_id' => $rider->id,
                'photo_path' => $podPath,
                'verified_otp' => $validated['delivery_otp'],
                'notes' => $validated['notes'] ?? null,
                'delivered_at' => now(),
            ]);

            $this->stateMachine->transition($order, 'delivered', $rider, 'Delivered with verified OTP & POD.');
            $this->payoutService->creditDeliveryPay($rider, $order);
            $this->payoutService->recordCodCollection($rider, $order);

            $codCollected = strtolower($order->payment_method) === 'cod' ? (float) $order->total : 0.00;

            return response()->json([
                'data' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'order_status' => 'delivered',
                    'earned_pay' => 50.00,
                    'cod_collected' => $codCollected,
                ],
                'message' => "Order {$order->order_number} marked as Delivered! ₱50.00 pay credited.",
            ]);
        });
    }

    /**
     * POST /api/mobile/v1/rider/deliveries/{order}/fail
     */
    public function failDelivery(Request $request, Order $order): JsonResponse
    {
        /** @var User $rider */
        $rider = $request->user();

        if ((int) $order->delivery_courier_id !== (int) $rider->id) {
            return response()->json([
                'message' => 'You are not assigned to this delivery.',
                'errors' => ['courier' => ['You are not assigned to this delivery.']],
            ], 403);
        }

        if ($order->status !== 'out_for_delivery') {
            return response()->json([
                'message' => 'Order must be Out for Delivery.',
                'errors' => ['status' => ['Order must be Out for Delivery.']],
            ], 422);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'in:buyer_not_home,wrong_address,refused'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $key = $request->header('Idempotency-Key') ?? $request->input('idempotency_key');

        return IdempotencyService::run($key, $rider->id, "mobile_fail_delivery_{$order->id}", function () use ($rider, $order, $validated) {
            $reason = $validated['reason'];
            $notes = $validated['notes'] ?? null;

            DeliveryAttempt::create([
                'order_id' => $order->id,
                'courier_id' => $rider->id,
                'result' => 'failed',
                'failure_reason' => $reason,
                'notes' => $notes,
                'attempted_at' => now(),
            ]);

            $attemptCount = $order->deliveryAttempts()->where('result', 'failed')->count();

            if ($reason === 'refused' || $attemptCount >= 3) {
                $newStatus = 'return_to_sender';
                $statusNote = $reason === 'refused'
                    ? "Customer refused delivery. Automatically routed to Return to Sender."
                    : "Reached 3 failed delivery attempts. Automatically routed to Return to Sender.";
            } else {
                $newStatus = 'failed_delivery';
                $statusNote = "Delivery attempt {$attemptCount}/3 failed: {$reason}.";
            }

            $order->update(['status' => $newStatus]);

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status' => $newStatus,
                'changed_by' => $rider->id,
                'note' => $statusNote,
                'changed_at' => now(),
            ]);

            return response()->json([
                'data' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'order_status' => $newStatus,
                    'attempt_count' => $attemptCount,
                ],
                'message' => "Delivery failure recorded (Attempt {$attemptCount}/3).",
            ]);
        });
    }

    /**
     * GET /api/mobile/v1/rider/earnings
     */
    public function earnings(Request $request): JsonResponse
    {
        /** @var User $rider */
        $rider = $request->user();
        $profile = $rider->courierProfile;

        $todayPickups = RiderEarning::where('courier_id', $rider->id)->where('type', 'pickup')->where('earned_at', '>=', now()->startOfDay())->count();
        $todayDeliveries = RiderEarning::where('courier_id', $rider->id)->where('type', 'delivery')->where('earned_at', '>=', now()->startOfDay())->count();
        $todayPay = (float) RiderEarning::where('courier_id', $rider->id)->where('earned_at', '>=', now()->startOfDay())->sum('amount');

        $weeklyPay = (float) RiderEarning::where('courier_id', $rider->id)->where('earned_at', '>=', now()->startOfWeek())->sum('amount');
        $totalEarned = (float) RiderEarning::where('courier_id', $rider->id)->sum('amount');

        // Past 7 days daily breakdown for weekly performance chart
        $past7Days = [];
        for ($i = 6; $i >= 0; $i--) {
            $dayDate = now()->subDays($i);
            $dayStart = $dayDate->copy()->startOfDay();
            $dayEnd = $dayDate->copy()->endOfDay();
            $dayEarnings = RiderEarning::where('courier_id', $rider->id)
                ->whereBetween('earned_at', [$dayStart, $dayEnd]);
            $past7Days[] = [
                'date' => $dayDate->format('Y-m-d'),
                'day' => $dayDate->format('D'),
                'amount' => (float) $dayEarnings->sum('amount'),
                'trips' => (int) $dayEarnings->count(),
            ];
        }

        $recentEarnings = RiderEarning::where('courier_id', $rider->id)
            ->with('order')
            ->orderBy('earned_at', 'desc')
            ->limit(50)
            ->get()
            ->map(function ($e) {
                $orderNum = $e->order?->order_number ?? '';
                $fallbackDesc = $e->type === 'pickup'
                    ? ($orderNum ? "Pickup Pay ({$orderNum})" : 'Pickup Pay')
                    : ($orderNum ? "Delivery Pay ({$orderNum})" : 'Delivery Pay');

                return [
                    'id' => $e->id,
                    'type' => $e->type,
                    'amount' => (float) $e->amount,
                    'description' => $e->description ?: $fallbackDesc,
                    'order_id' => $e->order_id,
                    'order_number' => $orderNum,
                    'earned_at' => $e->earned_at?->toIso8601String(),
                ];
            });

        return response()->json([
            'data' => [
                'earnings_balance' => (float) ($profile?->earnings_balance ?? 0),
                'unremitted_cod_balance' => (float) ($profile?->unremitted_cod_balance ?? 0),
                'total_earned_all_time' => $totalEarned,
                'today_summary' => [
                    'pickups' => $todayPickups,
                    'deliveries' => $todayDeliveries,
                    'total_pay' => $todayPay,
                ],
                'weekly_total_pay' => (float) $weeklyPay,
                'past_7_days' => $past7Days,
                'recent_earnings' => $recentEarnings,
            ],
            'message' => 'Earnings retrieved successfully.',
        ]);
    }

    /**
     * POST /api/mobile/v1/rider/remittance
     */
    public function submitRemittance(Request $request): JsonResponse
    {
        /** @var User $rider */
        $rider = $request->user();
        $profile = $rider->courierProfile;
        $unremitted = (float) ($profile?->unremitted_cod_balance ?? 0);

        if ($unremitted <= 0) {
            return response()->json([
                'message' => 'No unremitted COD balance to submit.',
                'errors' => ['amount' => ['No unremitted COD balance to submit.']],
            ], 422);
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', "max:{$unremitted}"],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $key = $request->header('Idempotency-Key') ?? $request->input('idempotency_key');

        return IdempotencyService::run($key, $rider->id, "mobile_remittance_{$rider->id}_" . date('Ymd'), function () use ($rider, $validated) {
            $remittance = $this->payoutService->submitRemittance(
                $rider,
                (float) $validated['amount'],
                $validated['notes'] ?? null
            );

            return response()->json([
                'data' => [
                    'remittance_number' => $remittance->remittance_number,
                    'amount' => (float) $remittance->amount,
                    'status' => $remittance->status,
                    'submitted_at' => $remittance->submitted_at?->toIso8601String(),
                ],
                'message' => "COD Remittance #{$remittance->remittance_number} submitted.",
            ]);
        });
    }

    /**
     * Helper to format an order consistently for rider screens.
     */
    protected function formatRiderOrder(Order $order, User $rider): array
    {
        $isPickup = (int) $order->pickup_courier_id === (int) $rider->id;
        $isDelivery = (int) $order->delivery_courier_id === (int) $rider->id;

        $deliveryStatuses = ['assigned_to_rider', 'out_for_delivery', 'delivered', 'failed_delivery', 'return_to_sender', 'completed'];
        if ($isDelivery && in_array($order->status, $deliveryStatuses, true)) {
            $role = 'delivery';
        } elseif ($isPickup) {
            $role = 'pickup';
        } elseif ($isDelivery) {
            $role = 'delivery';
        } else {
            $role = 'courier';
        }

        $pickupAddress = $order->seller_address
            ? "{$order->seller_address->street}, {$order->seller_address->barangay_name}, {$order->seller_address->municipality_name}"
            : 'Seller Store';

        $dropoffAddress = $order->address
            ? "{$order->address->street}, {$order->address->barangay_name}, {$order->address->municipality_name}"
            : 'Buyer Address';

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'tracking_number' => $order->tracking_number,
            'status' => $order->status,
            'role' => $role,
            'total' => (float) $order->total,
            'payment_method' => $order->payment_method,
            'cod_amount' => strtolower($order->payment_method) === 'cod' ? (float) $order->total : 0.00,
            'recipient_name' => $order->recipient_name ?? $order->buyer?->full_name,
            'recipient_phone' => $order->recipient_phone ?? $order->buyer?->contact_number,
            'pickup_address' => $pickupAddress,
            'dropoff_address' => $dropoffAddress,
            'items_count' => $order->items?->count() ?? 0,
            'items' => $order->items?->map(fn ($item) => [
                'id' => $item->id,
                'product_name' => $item->product?->name ?? 'Product',
                'quantity' => $item->quantity,
                'price' => (float) $item->price,
            ]),
            'created_at' => $order->created_at?->toIso8601String(),
            'updated_at' => $order->updated_at?->toIso8601String(),
        ];
    }
}
