<?php

namespace App\Http\Controllers\Api;

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
use App\Models\User;
use App\Services\IdempotencyService;
use App\Services\JobDispatchService;
use App\Services\RiderPayoutService;
use App\Services\ShipmentStateMachineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class RiderApiController extends Controller
{
    public function __construct(
        protected JobDispatchService $dispatchService,
        protected RiderPayoutService $payoutService,
        protected ShipmentStateMachineService $stateMachine
    ) {}

    /**
     * POST /api/v1/rider/login
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json(['error' => 'Invalid email or password.'], 401);
        }

        if (! $user->isCourier()) {
            return response()->json(['error' => 'Access denied. Account is not a registered courier.'], 403);
        }

        if ($user->account_status === 'pending_activation') {
            return response()->json(['error' => 'Account Pending Activation. Please complete your password setup via your activation email.'], 403);
        }

        if ($user->account_status !== 'active') {
            return response()->json(['error' => 'Account is suspended or deactivated. Contact your logistics operator.'], 403);
        }

        Auth::login($user);

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'user' => [
                'id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
                'role' => $user->role,
                'account_status' => $user->account_status,
            ],
        ]);
    }

    /**
     * GET /api/v1/rider/profile
     */
    public function profile(Request $request): JsonResponse
    {
        $rider = $request->user();
        abort_unless($rider && $rider->isCourier(), 403);

        $profile = $rider->courierProfile()->with('sortingCenter')->first();

        return response()->json([
            'id' => $rider->id,
            'first_name' => $rider->first_name,
            'last_name' => $rider->last_name,
            'email' => $rider->email,
            'contact_number' => $rider->contact_number,
            'account_status' => $rider->account_status,
            'is_online' => $profile?->is_online ?? false,
            'vehicle_type' => $profile?->vehicle_type,
            'plate_number' => $profile?->plate_number,
            'license_number' => $profile?->license_number,
            'license_expiry_date' => $profile?->license_expiry_date?->format('Y-m-d'),
            'vehicle_verification_status' => $profile?->vehicle_verification_status,
            'sorting_center' => $profile?->sortingCenter?->full_name,
            'earnings_balance' => (float) ($profile?->earnings_balance ?? 0),
            'unremitted_cod_balance' => (float) ($profile?->unremitted_cod_balance ?? 0),
            'active_jobs_count' => $profile?->activeJobsCount() ?? 0,
        ]);
    }

    /**
     * POST /api/v1/rider/availability
     */
    public function toggleAvailability(Request $request): JsonResponse
    {
        $rider = $request->user();
        abort_unless($rider && $rider->isCourier(), 403);

        $profile = $rider->courierProfile;
        abort_unless($profile, 400);

        $isOnline = $request->has('is_online')
            ? (bool) $request->input('is_online')
            : ! $profile->is_online;

        $profile->update(['is_online' => $isOnline]);

        return response()->json([
            'success' => true,
            'is_online' => $isOnline,
            'message' => $isOnline ? 'Online on auto-dispatch' : 'Offline',
        ]);
    }

    /**
     * GET /api/v1/rider/offers
     */
    public function offers(Request $request): JsonResponse
    {
        $rider = $request->user();
        abort_unless($rider && $rider->isCourier(), 403);

        $offers = JobOffer::where('courier_id', $rider->id)
            ->where('status', 'pending')
            ->where('expires_at', '>=', now())
            ->with(['order.items.product'])
            ->get()
            ->map(fn ($o) => [
                'id' => $o->id,
                'order_id' => $o->order_id,
                'type' => $o->type,
                'pickup_location' => $o->pickup_location,
                'dropoff_location' => $o->dropoff_location,
                'cod_amount' => (float) $o->cod_amount,
                'expected_pay' => (float) $o->expected_pay,
                'expires_in_seconds' => max(0, now()->diffInSeconds($o->expires_at, false)),
            ]);

        return response()->json(['offers' => $offers]);
    }

    /**
     * POST /api/v1/rider/offers/{offer}/accept
     */
    public function acceptOffer(Request $request, JobOffer $offer): JsonResponse
    {
        $rider = $request->user();
        abort_unless($rider && $rider->isCourier(), 403);

        $key = $request->header('Idempotency-Key') ?? $request->input('idempotency_key');

        return IdempotencyService::run($key, $rider->id, "api_accept_offer_{$offer->id}", function () use ($offer, $rider) {
            $result = $this->dispatchService->acceptOffer($offer, $rider);
            return response()->json($result, $result['success'] ? 200 : 422);
        });
    }

    /**
     * POST /api/v1/rider/offers/{offer}/decline
     */
    public function declineOffer(Request $request, JobOffer $offer): JsonResponse
    {
        $rider = $request->user();
        abort_unless($rider && $rider->isCourier(), 403);

        $result = $this->dispatchService->declineOffer($offer, $rider, true);
        return response()->json($result);
    }

    /**
     * POST /api/v1/rider/pickup
     */
    public function confirmPickup(Request $request): JsonResponse
    {
        $rider = $request->user();
        abort_unless($rider && $rider->isCourier(), 403);

        $validated = $request->validate([
            'tracking_number' => ['required', 'string'],
        ]);

        $key = $request->header('Idempotency-Key') ?? $request->input('idempotency_key');
        $trackingNumber = trim($validated['tracking_number']);

        return IdempotencyService::run($key, $rider->id, "api_pickup_{$trackingNumber}", function () use ($rider, $trackingNumber) {
            $order = Order::where('tracking_number', $trackingNumber)
                ->orWhere('order_number', $trackingNumber)
                ->first();

            if (! $order) {
                return response()->json(['error' => 'Order not found.'], 404);
            }

            if ((int) $order->pickup_courier_id !== (int) $rider->id) {
                return response()->json(['error' => 'You are not assigned as the pickup courier for this parcel.'], 403);
            }

            if ($order->status === 'picked_up') {
                return response()->json(['success' => true, 'message' => 'Already marked as Picked Up.']);
            }

            if (! in_array($order->status, ['ready_for_pickup', 'assigned'], true)) {
                return response()->json(['error' => "Cannot pickup order in status {$order->status}."], 422);
            }

            $this->stateMachine->transition($order, 'picked_up', $rider, 'Picked up from merchant seller.');
            $this->payoutService->creditPickupPay($rider, $order);

            return response()->json([
                'success' => true,
                'message' => "Order {$order->order_number} marked as Picked Up. ₱30.00 pay credited.",
                'order_status' => 'picked_up',
            ]);
        });
    }

    /**
     * POST /api/v1/rider/deliveries/{order}/complete
     */
    public function completeDelivery(Request $request, Order $order): JsonResponse
    {
        $rider = $request->user();
        abort_unless($rider && $rider->isCourier(), 403);

        if ((int) $order->delivery_courier_id !== (int) $rider->id) {
            return response()->json(['error' => 'You are not assigned to this delivery.'], 403);
        }

        if ($order->status !== 'out_for_delivery') {
            return response()->json(['error' => "Order must be Out for Delivery. Current status: {$order->status}."], 422);
        }

        $validated = $request->validate([
            'delivery_otp' => ['required', 'string', 'digits:6'],
            'pod_image' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $key = $request->header('Idempotency-Key') ?? $request->input('idempotency_key');

        return IdempotencyService::run($key, $rider->id, "api_complete_delivery_{$order->id}", function () use ($request, $rider, $order, $validated) {
            if ($order->isDeliveryOtpLocked()) {
                $minutes = now()->diffInMinutes($order->delivery_otp_locked_until) + 1;
                return response()->json([
                    'error' => "OTP verification locked for {$minutes} minutes due to 5 consecutive failed attempts.",
                ], 422);
            }

            if (! $order->verifyDeliveryOtp($validated['delivery_otp'])) {
                $failed = $order->delivery_otp_failed_attempts;
                return response()->json([
                    'error' => "Invalid Delivery OTP ({$failed}/5 failed attempts).",
                    'failed_attempts' => $failed,
                ], 422);
            }

            $podPath = $request->file('pod_image')->store("couriers/{$rider->id}/pod", 'public');

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

            return response()->json([
                'success' => true,
                'message' => "Order {$order->order_number} marked as Delivered! ₱50.00 pay credited.",
                'order_status' => 'delivered',
                'cod_collected' => strtolower($order->payment_method) === 'cod' ? (float) $order->total : 0.00,
            ]);
        });
    }

    /**
     * POST /api/v1/rider/deliveries/{order}/fail
     */
    public function failDelivery(Request $request, Order $order): JsonResponse
    {
        $rider = $request->user();
        abort_unless($rider && $rider->isCourier(), 403);

        if ((int) $order->delivery_courier_id !== (int) $rider->id) {
            return response()->json(['error' => 'You are not assigned to this delivery.'], 403);
        }

        if ($order->status !== 'out_for_delivery') {
            return response()->json(['error' => 'Order must be Out for Delivery.'], 422);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'in:buyer_not_home,wrong_address,refused'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $key = $request->header('Idempotency-Key') ?? $request->input('idempotency_key');

        return IdempotencyService::run($key, $rider->id, "api_fail_delivery_{$order->id}", function () use ($rider, $order, $validated) {
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
                'success' => true,
                'message' => "Delivery failure recorded (Attempt {$attemptCount}/3).",
                'order_status' => $newStatus,
                'attempt_count' => $attemptCount,
            ]);
        });
    }

    /**
     * GET /api/v1/rider/earnings
     */
    public function earnings(Request $request): JsonResponse
    {
        $rider = $request->user();
        abort_unless($rider && $rider->isCourier(), 403);

        $profile = $rider->courierProfile;

        $todayPickups = RiderEarning::where('courier_id', $rider->id)->where('type', 'pickup')->where('earned_at', '>=', now()->startOfDay())->count();
        $todayDeliveries = RiderEarning::where('courier_id', $rider->id)->where('type', 'delivery')->where('earned_at', '>=', now()->startOfDay())->count();
        $todayPay = ($todayPickups * 30.00) + ($todayDeliveries * 50.00);

        $weeklyPay = RiderEarning::where('courier_id', $rider->id)->where('earned_at', '>=', now()->startOfWeek())->sum('amount');

        return response()->json([
            'earnings_balance' => (float) ($profile?->earnings_balance ?? 0),
            'unremitted_cod_balance' => (float) ($profile?->unremitted_cod_balance ?? 0),
            'today_summary' => [
                'pickups' => $todayPickups,
                'deliveries' => $todayDeliveries,
                'total_pay' => $todayPay,
            ],
            'weekly_total_pay' => (float) $weeklyPay,
        ]);
    }

    /**
     * POST /api/v1/rider/remittance
     */
    public function submitRemittance(Request $request): JsonResponse
    {
        $rider = $request->user();
        abort_unless($rider && $rider->isCourier(), 403);

        $profile = $rider->courierProfile;
        $unremitted = (float) ($profile?->unremitted_cod_balance ?? 0);

        if ($unremitted <= 0) {
            return response()->json(['error' => 'No unremitted COD balance to submit.'], 422);
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', "max:{$unremitted}"],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $key = $request->header('Idempotency-Key') ?? $request->input('idempotency_key');

        return IdempotencyService::run($key, $rider->id, "api_remittance_{$rider->id}_" . date('Ymd'), function () use ($rider, $validated) {
            $remittance = $this->payoutService->submitRemittance(
                $rider,
                (float) $validated['amount'],
                $validated['notes'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => "COD Remittance #{$remittance->remittance_number} submitted.",
                'remittance' => $remittance,
            ]);
        });
    }
}
