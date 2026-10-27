<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CodLedgerEntry;
use App\Models\CodRemittance;
use App\Models\CourierProfile;
use App\Models\DeliveryAttempt;
use App\Models\IdempotencyKey;
use App\Models\JobOffer;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\ProofOfDelivery;
use App\Models\RiderEarning;
use App\Models\SortingCenterProfile;
use App\Models\User;
use App\Services\JobDispatchService;
use App\Services\RiderPayoutService;
use App\Services\ShipmentStateMachineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RiderOperationsAndDispatchTest extends TestCase
{
    use RefreshDatabase;

    protected User $sortingCenter;
    protected User $courier;
    protected CourierProfile $courierProfile;
    protected User $buyer;
    protected User $seller;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Logistics Center
        $this->sortingCenter = User::factory()->create([
            'role' => 'sorting_center',
            'approval_status' => 'approved',
            'account_status' => 'active',
        ]);
        SortingCenterProfile::create([
            'user_id' => $this->sortingCenter->id,
            'business_name' => 'Nexora Test Sorting Center',
        ]);

        // 2. Courier
        $this->courier = User::factory()->create([
            'role' => 'courier',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'password' => Hash::make('Password123!@#'),
        ]);
        $this->courierProfile = CourierProfile::create([
            'user_id' => $this->courier->id,
            'sorting_center_id' => $this->sortingCenter->id,
            'is_online' => true,
            'earnings_balance' => 0.00,
            'unremitted_cod_balance' => 0.00,
            'vehicle_type' => 'Motorcycle',
            'plate_number' => 'MC-9999',
            'license_number' => 'N01-23-456789',
            'license_expiry_date' => now()->addYears(2),
            'vehicle_verification_status' => 'verified',
        ]);

        // 3. Buyer & Seller
        $this->buyer = User::factory()->create(['role' => 'buyer']);
        $this->seller = User::factory()->create(['role' => 'seller']);

        $this->address = \App\Models\Address::create([
            'user_id' => $this->buyer->id,
            'province_code' => '043400000',
            'province_name' => 'Laguna',
            'municipality_code' => '043426000',
            'municipality_name' => 'Santa Cruz',
            'barangay_code' => '043426001',
            'barangay_name' => 'Poblacion I',
            'street' => '123 High St',
            'house_number' => 'Unit 4',
            'recipient_name' => $this->buyer->full_name,
            'recipient_phone' => '09170001234',
            'is_default' => true,
        ]);
    }

    protected \App\Models\Address $address;

    protected function createOrder(array $attributes = []): Order
    {
        return Order::create(array_merge([
            'order_number' => 'ORD-' . uniqid(),
            'tracking_number' => 'TRK-' . uniqid(),
            'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'address_id' => $this->address->id,
            'sorting_center_id' => $this->sortingCenter->id,
            'pickup_courier_id' => $this->courier->id,
            'delivery_courier_id' => $this->courier->id,
            'status' => 'ready_for_pickup',
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'subtotal' => 500.00,
            'shipping_fee' => 50.00,
            'total' => 550.00,
            'placed_at' => now(),
        ], $attributes));
    }

    public function test_courier_access_control_and_session_lifetime(): void
    {
        // Guests redirected
        $this->get(route('courier.dashboard'))->assertRedirect(route('login'));

        // Buyers and sellers blocked (403 on API / redirected on UI)
        $this->actingAs($this->buyer)->getJson(route('courier.dashboard'))->assertStatus(403);
        $this->actingAs($this->buyer)->get(route('courier.dashboard'))->assertRedirect($this->buyer->homeRoute());
        $this->actingAs($this->seller)->getJson(route('courier.dashboard'))->assertStatus(403);
        $this->actingAs($this->seller)->get(route('courier.dashboard'))->assertRedirect($this->seller->homeRoute());

        // Courier can access
        $this->actingAs($this->courier)->get(route('courier.dashboard'))->assertOk();

        // Config session lifetime is 30 minutes
        $this->assertEquals(30, config('session.lifetime'));
    }

    public function test_audit_logs_are_immutable(): void
    {
        $log = AuditLog::log(
            action: 'test_action',
            auditable: $this->courier,
            oldValues: ['status' => 'inactive'],
            newValues: ['status' => 'active'],
            reason: 'Test immutability',
            operatorId: $this->sortingCenter->id
        );

        $this->assertDatabaseHas('audit_logs', [
            'id' => $log->id,
            'action' => 'test_action',
        ]);

        // Trying to update must throw exception
        $this->expectException(\RuntimeException::class);
        $log->update(['reason' => 'Tampered reason']);
    }

    public function test_vehicle_update_workflow_with_unique_plate_and_verification_status(): void
    {
        // 1. Rejects duplicate plate numbers (case-insensitive and trimmed)
        $otherCourier = User::factory()->create(['role' => 'courier']);
        CourierProfile::create([
            'user_id' => $otherCourier->id,
            'sorting_center_id' => $this->sortingCenter->id,
            'plate_number' => 'ABC-1234',
            'vehicle_type' => 'Van',
        ]);

        $response = $this->actingAs($this->courier)->post(route('courier.vehicle.update'), [
            'vehicle_type' => 'Motorcycle',
            'plate_number' => '  abc-1234  ', // duplicate of other courier
        ]);
        $response->assertSessionHasErrors('plate_number');

        // 2. Valid unique plate flags for Logistics review
        $validResponse = $this->actingAs($this->courier)->post(route('courier.vehicle.update'), [
            'vehicle_type' => 'Van',
            'plate_number' => 'XYZ-7890',
        ]);
        $validResponse->assertRedirect();

        $this->courierProfile->refresh();
        $this->assertEquals('pending', $this->courierProfile->vehicle_verification_status);
        $this->assertEquals('XYZ-7890', $this->courierProfile->pending_plate_number);
        $this->assertEquals('Van', $this->courierProfile->pending_vehicle_type);

        // 3. Logistics Operator approves update
        $this->actingAs($this->sortingCenter)
            ->post(route('logistics.riders.verify-vehicle', $this->courier), [
                'decision' => 'approve',
                'reason' => 'Valid LTO documents inspected',
            ])
            ->assertRedirect();

        $this->courierProfile->refresh();
        $this->assertEquals('verified', $this->courierProfile->vehicle_verification_status);
        $this->assertEquals('XYZ-7890', $this->courierProfile->plate_number);
        $this->assertNull($this->courierProfile->pending_plate_number);

        // Verification recorded in AuditLog
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'vehicle_re_verification_approved',
            'operator_id' => $this->sortingCenter->id,
        ]);
    }

    public function test_online_toggle_and_capacity_limits(): void
    {
        // Toggle offline
        $this->actingAs($this->courier)->post(route('courier.toggle-online'), ['is_online' => 0])
            ->assertRedirect();
        $this->courierProfile->refresh();
        $this->assertFalse($this->courierProfile->isOnline());

        // Toggle online
        $this->actingAs($this->courier)->post(route('courier.toggle-online'), ['is_online' => 1])
            ->assertRedirect();
        $this->courierProfile->refresh();
        $this->assertTrue($this->courierProfile->isOnline());

        // Capacity test: maximum 3 active jobs
        $this->assertEquals(0, $this->courierProfile->activeJobsCount());

        $order1 = $this->createOrder(['status' => 'assigned']);
        $order2 = $this->createOrder(['status' => 'assigned_to_rider']);
        $order3 = $this->createOrder(['status' => 'out_for_delivery']);

        $this->assertEquals(3, $this->courierProfile->activeJobsCount());
        $this->assertFalse($this->courierProfile->canAcceptJobs());

        // Attempting to accept a 4th job offer must fail capacity check
        $order4 = $this->createOrder(['status' => 'ready_for_pickup']);
        $offer = JobOffer::create([
            'order_id' => $order4->id,
            'courier_id' => $this->courier->id,
            'type' => 'pickup',
            'status' => 'pending',
            'pickup_location' => 'Store',
            'dropoff_location' => 'Buyer',
            'expected_pay' => 30.00,
            'expires_at' => now()->addSeconds(60),
        ]);

        $response = $this->actingAs($this->courier)->post(route('courier.offers.accept', $offer));
        $response->assertSessionHasErrors('offer');
        $this->assertEquals('pending', $offer->fresh()->status);
    }

    public function test_suspension_unassigns_pending_jobs_and_preserves_picked_up_parcels(): void
    {
        $orderPendingPickup = $this->createOrder([
            'pickup_courier_id' => $this->courier->id,
            'status' => 'assigned',
        ]);
        $orderPendingDelivery = $this->createOrder([
            'delivery_courier_id' => $this->courier->id,
            'status' => 'assigned_to_rider',
        ]);
        $orderAlreadyPickedUp = $this->createOrder([
            'delivery_courier_id' => $this->courier->id,
            'status' => 'out_for_delivery',
        ]);

        // Logistics deactivates courier
        $this->actingAs($this->sortingCenter)->post(route('logistics.riders.toggle-status', $this->courier), [
            'action' => 'deactivate',
            'reason' => 'License verification under review',
        ])->assertRedirect();

        $this->courier->refresh();
        $this->assertEquals('deactivated', $this->courier->account_status);

        // Pending pickup returned to unassigned queue
        $orderPendingPickup->refresh();
        $this->assertNull($orderPendingPickup->pickup_courier_id);
        $this->assertEquals('ready_for_pickup', $orderPendingPickup->status);

        // Pending delivery returned to hub
        $orderPendingDelivery->refresh();
        $this->assertNull($orderPendingDelivery->delivery_courier_id);
        $this->assertEquals('at_hub', $orderPendingDelivery->status);

        // Already picked up order remains assigned to rider!
        $orderAlreadyPickedUp->refresh();
        $this->assertEquals($this->courier->id, $orderAlreadyPickedUp->delivery_courier_id);
        $this->assertEquals('out_for_delivery', $orderAlreadyPickedUp->status);

        // Audit log created
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'rider_suspension',
            'auditable_id' => $this->courier->id,
        ]);
    }

    public function test_job_dispatching_engine_60s_sla_and_consecutive_declines(): void
    {
        $dispatchService = app(JobDispatchService::class);
        $order = $this->createOrder(['status' => 'ready_for_pickup']);

        // 1. Dispatches to eligible online rider with 60s timer
        $offer = $dispatchService->dispatchOrder($order, 'pickup');
        $this->assertNotNull($offer);
        $this->assertEquals($this->courier->id, $offer->courier_id);
        $this->assertEquals('pending', $offer->status);
        $this->assertTrue(now()->diffInSeconds($offer->expires_at) >= 58);

        // 2. Reject acceptance after 60s expiration
        $offer->update(['expires_at' => now()->subSecond()]);
        $acceptResponse = $this->actingAs($this->courier)->post(route('courier.offers.accept', $offer));
        $acceptResponse->assertSessionHasErrors('offer');

        // 3. Consecutive declines limit (3 declines -> moves to unassigned queue)
        $offer->update(['status' => 'pending', 'expires_at' => now()->addSeconds(60)]);
        $dispatchService->declineOffer($offer, $this->courier, true); // Decline 1

        $offer2 = $dispatchService->dispatchOrder($order, 'pickup', 1);
        if ($offer2) {
            $dispatchService->declineOffer($offer2, $this->courier, true); // Decline 2
        }

        // 3rd decline moves to unassigned queue
        $result = $dispatchService->dispatchOrder($order, 'pickup', 3);
        $this->assertNull($result);
        $this->assertNull($order->fresh()->pickup_courier_id);
    }

    public function test_pickup_confirmation_credits_30_php_and_freezes_cancellation(): void
    {
        $order = $this->createOrder([
            'pickup_courier_id' => $this->courier->id,
            'status' => 'ready_for_pickup',
        ]);

        $this->actingAs($this->courier)->post(route('courier.pickup.confirm'), [
            'tracking_number' => $order->tracking_number,
        ])->assertRedirect();

        $order->refresh();
        $this->assertEquals('picked_up', $order->status);
        $this->assertFalse($order->canBeCancelledByBuyer());

        // Earnings credited
        $this->assertDatabaseHas('rider_earnings', [
            'courier_id' => $this->courier->id,
            'order_id' => $order->id,
            'type' => 'pickup',
            'amount' => 30.00,
        ]);

        $this->courierProfile->refresh();
        $this->assertEquals(30.00, (float) $this->courierProfile->earnings_balance);
    }

    public function test_delivery_execution_with_pod_and_otp_brute_force_lock(): void
    {
        Storage::fake('public');

        $order = $this->createOrder([
            'delivery_courier_id' => $this->courier->id,
            'status' => 'out_for_delivery',
            'payment_method' => 'cod',
            'total' => 600.00,
        ]);
        $correctOtp = $order->generateDeliveryOtp();

        $podFile = UploadedFile::fake()->create('proof.jpg', 1024, 'image/jpeg');

        // 1. Wrong OTP failed attempt
        $wrongResponse = $this->actingAs($this->courier)->post(route('courier.deliveries.complete', $order), [
            'delivery_otp' => '000000',
            'pod_image' => $podFile,
        ]);
        $wrongResponse->assertSessionHasErrors('delivery_otp');
        $this->assertEquals(1, $order->fresh()->delivery_otp_failed_attempts);

        // 2. Lockout triggered after 5 failed attempts
        for ($i = 2; $i <= 5; $i++) {
            $this->actingAs($this->courier)->post(route('courier.deliveries.complete', $order), [
                'delivery_otp' => '00000' . $i,
                'pod_image' => $podFile,
            ]);
        }

        $order->refresh();
        $this->assertTrue($order->isDeliveryOtpLocked());

        // 3. Locked state blocks even correct OTP
        $lockedResponse = $this->actingAs($this->courier)->post(route('courier.deliveries.complete', $order), [
            'delivery_otp' => $correctOtp,
            'pod_image' => $podFile,
        ]);
        $lockedResponse->assertSessionHasErrors('delivery_otp');

        // 4. Unlock and successful completion
        $order->update(['delivery_otp_locked_until' => null]);

        $successResponse = $this->actingAs($this->courier)->post(route('courier.deliveries.complete', $order), [
            'delivery_otp' => $correctOtp,
            'pod_image' => $podFile,
            'notes' => 'Handed to recipient',
        ]);
        $successResponse->assertRedirect();

        $order->refresh();
        $this->assertEquals('delivered', $order->status);
        $this->assertNotNull($order->delivered_at);

        // POD stored
        $this->assertDatabaseHas('proof_of_deliveries', [
            'order_id' => $order->id,
            'verified_otp' => $correctOtp,
        ]);

        // ₱50.00 delivery pay credited
        $this->assertDatabaseHas('rider_earnings', [
            'courier_id' => $this->courier->id,
            'order_id' => $order->id,
            'type' => 'delivery',
            'amount' => 50.00,
        ]);

        // COD added to unremitted balance and ledger
        $this->courierProfile->refresh();
        $this->assertEquals(600.00, (float) $this->courierProfile->unremitted_cod_balance);
        $this->assertDatabaseHas('cod_ledger_entries', [
            'courier_id' => $this->courier->id,
            'order_id' => $order->id,
            'type' => 'collected',
            'amount' => 600.00,
        ]);
    }

    public function test_failed_delivery_3_attempts_and_refused_auto_returns_to_sender(): void
    {
        $order = $this->createOrder([
            'delivery_courier_id' => $this->courier->id,
            'status' => 'out_for_delivery',
        ]);

        // Attempt 1: buyer_not_home -> failed_delivery
        $this->actingAs($this->courier)->post(route('courier.deliveries.fail', $order), [
            'reason' => 'buyer_not_home',
            'notes' => 'Called twice, no answer.',
        ])->assertRedirect();

        $order->refresh();
        $this->assertEquals('failed_delivery', $order->status);
        $this->assertEquals(1, $order->deliveryAttempts()->count());

        // Attempt 2: wrong_address -> failed_delivery
        $order->update(['status' => 'out_for_delivery']);
        $this->actingAs($this->courier)->post(route('courier.deliveries.fail', $order), [
            'reason' => 'wrong_address',
        ])->assertRedirect();

        $order->refresh();
        $this->assertEquals('failed_delivery', $order->status);

        // Attempt 3: 3rd failure automatically switches to return_to_sender
        $order->update(['status' => 'out_for_delivery']);
        $this->actingAs($this->courier)->post(route('courier.deliveries.fail', $order), [
            'reason' => 'buyer_not_home',
        ])->assertRedirect();

        $order->refresh();
        $this->assertEquals('return_to_sender', $order->status);

        // Immediate refused test: Refused immediately routes to return_to_sender
        $orderRefused = $this->createOrder([
            'delivery_courier_id' => $this->courier->id,
            'status' => 'out_for_delivery',
        ]);

        $this->actingAs($this->courier)->post(route('courier.deliveries.fail', $orderRefused), [
            'reason' => 'refused',
            'notes' => 'Customer refused to accept parcel',
        ])->assertRedirect();

        $orderRefused->refresh();
        $this->assertEquals('return_to_sender', $orderRefused->status);
    }

    public function test_idempotency_prevents_duplicate_submissions(): void
    {
        $order = $this->createOrder([
            'pickup_courier_id' => $this->courier->id,
            'status' => 'ready_for_pickup',
        ]);
        $idempotencyKey = 'unique-key-12345';

        // 1st request
        $this->actingAs($this->courier)->post(route('courier.pickup.confirm'), [
            'tracking_number' => $order->tracking_number,
            'idempotency_key' => $idempotencyKey,
        ])->assertRedirect();

        // 2nd request with same key
        $this->actingAs($this->courier)->post(route('courier.pickup.confirm'), [
            'tracking_number' => $order->tracking_number,
            'idempotency_key' => $idempotencyKey,
        ])->assertRedirect();

        // Exactly one earning record created! No duplicates!
        $this->assertEquals(1, RiderEarning::where('order_id', $order->id)->where('type', 'pickup')->count());
    }

    public function test_eod_cod_remittance_submission_and_logistics_reconciliation(): void
    {
        $this->courierProfile->update(['unremitted_cod_balance' => 1200.00]);

        // Courier submits 1200.00 remittance
        $this->actingAs($this->courier)->post(route('courier.remittance.submit'), [
            'amount' => 1200.00,
            'notes' => 'EOD cash turnover to Hub',
        ])->assertRedirect();

        $remittance = CodRemittance::where('courier_id', $this->courier->id)->latest()->first();
        $this->assertNotNull($remittance);
        $this->assertEquals(1200.00, (float) $remittance->amount);
        $this->assertEquals('pending', $remittance->status);

        // Logistics operator approves remittance
        $this->actingAs($this->sortingCenter)->post(route('logistics.remittances.review', $remittance), [
            'decision' => 'approve',
            'operator_notes' => 'Cash received and counted.',
        ])->assertRedirect();

        $remittance->refresh();
        $this->assertEquals('approved', $remittance->status);

        $this->courierProfile->refresh();
        $this->assertEquals(0.00, (float) $this->courierProfile->unremitted_cod_balance);

        // Audit log created
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'cod_remittance_approved',
            'operator_id' => $this->sortingCenter->id,
        ]);
    }

    public function test_logistics_manual_status_override_requires_reason_and_logs_audit(): void
    {
        $order = $this->createOrder(['status' => 'at_hub']);

        // Without reason -> validation error
        $response = $this->actingAs($this->sortingCenter)->post(route('logistics.orders.override-status', $order), [
            'status' => 'out_for_delivery',
            'reason' => '',
        ]);
        $response->assertSessionHasErrors('reason');

        // With valid reason -> successfully overrides status and creates audit log
        $this->actingAs($this->sortingCenter)->post(route('logistics.orders.override-status', $order), [
            'status' => 'out_for_delivery',
            'reason' => 'Emergency reroute approved by hub supervisor',
        ])->assertRedirect();

        $order->refresh();
        $this->assertEquals('out_for_delivery', $order->status);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'order_status_manual_override',
            'auditable_id' => $order->id,
            'operator_id' => $this->sortingCenter->id,
            'reason' => 'Emergency reroute approved by hub supervisor',
        ]);
    }
}
