<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\Address;
use App\Models\CourierProfile;
use App\Models\JobOffer;
use App\Models\Order;
use App\Models\SortingCenterProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileRiderApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $sortingCenter;
    protected User $courier;
    protected CourierProfile $courierProfile;
    protected User $buyer;
    protected User $seller;
    protected Address $address;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sortingCenter = User::factory()->create([
            'role' => 'sorting_center',
            'approval_status' => 'approved',
            'account_status' => 'active',
        ]);
        SortingCenterProfile::create([
            'user_id' => $this->sortingCenter->id,
            'business_name' => 'Central Hub',
        ]);

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
            'plate_number' => 'MC-7777',
            'license_number' => 'N01-23-456789',
            'license_expiry_date' => now()->addYears(2),
            'vehicle_verification_status' => 'verified',
        ]);

        $this->buyer = User::factory()->create(['role' => 'buyer']);
        $this->seller = User::factory()->create(['role' => 'seller']);

        $this->address = Address::create([
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

    public function test_mobile_rider_profile_and_availability(): void
    {
        Sanctum::actingAs($this->courier);

        // Profile
        $response = $this->getJson('/api/mobile/v1/rider/profile');
        $response->assertOk()
            ->assertJsonPath('data.courier_profile.plate_number', 'MC-7777')
            ->assertJsonPath('data.is_online', true);

        // Toggle availability to offline
        $toggleRes = $this->postJson('/api/mobile/v1/rider/availability', ['is_online' => false]);
        $toggleRes->assertOk()->assertJsonPath('data.is_online', false);
        $this->assertFalse($this->courierProfile->fresh()->is_online);

        // Toggle back to online
        $toggleRes2 = $this->postJson('/api/mobile/v1/rider/availability');
        $toggleRes2->assertOk()->assertJsonPath('data.is_online', true);
        $this->assertTrue($this->courierProfile->fresh()->is_online);
    }

    public function test_mobile_rider_job_offers_and_accept_decline(): void
    {
        Sanctum::actingAs($this->courier);

        $order = $this->createOrder(['status' => 'ready_for_pickup']);

        $offer = JobOffer::create([
            'order_id' => $order->id,
            'courier_id' => $this->courier->id,
            'type' => 'pickup',
            'pickup_location' => 'Seller Store, Laguna',
            'dropoff_location' => 'Central Hub',
            'cod_amount' => 550.00,
            'expected_pay' => 30.00,
            'status' => 'pending',
            'expires_at' => now()->addSeconds(60),
        ]);

        // List offers
        $res = $this->getJson('/api/mobile/v1/rider/offers');
        $res->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $offer->id);

        // Accept offer with Idempotency-Key
        $acceptRes = $this->withHeader('Idempotency-Key', 'mobile-accept-1')
            ->postJson("/api/mobile/v1/rider/offers/{$offer->id}/accept");
        $acceptRes->assertOk()
            ->assertJsonPath('data.status', 'accepted');

        $this->assertEquals('accepted', $offer->fresh()->status);
        $this->assertEquals('assigned', $order->fresh()->status);

        // Test decline with a new offer
        $order2 = $this->createOrder(['status' => 'ready_for_pickup']);
        $offer2 = JobOffer::create([
            'order_id' => $order2->id,
            'courier_id' => $this->courier->id,
            'type' => 'pickup',
            'pickup_location' => 'Seller Store 2',
            'dropoff_location' => 'Central Hub',
            'cod_amount' => 200.00,
            'expected_pay' => 30.00,
            'status' => 'pending',
            'expires_at' => now()->addSeconds(60),
        ]);

        $declineRes = $this->postJson("/api/mobile/v1/rider/offers/{$offer2->id}/decline");
        $declineRes->assertOk()
            ->assertJsonPath('data.status', 'declined');
        $this->assertEquals('declined', $offer2->fresh()->status);
    }

    public function test_mobile_rider_pickup_workflow(): void
    {
        Sanctum::actingAs($this->courier);

        $order = $this->createOrder(['status' => 'ready_for_pickup']);

        $pickupRes = $this->withHeader('Idempotency-Key', 'mobile-pickup-1')
            ->postJson('/api/mobile/v1/rider/pickup', [
                'tracking_number' => $order->tracking_number,
            ]);

        $pickupRes->assertOk()
            ->assertJsonPath('data.order_status', 'picked_up');
        $this->assertEquals(30.00, (float) $pickupRes->json('data.earned_pay'));

        $this->assertEquals('picked_up', $order->fresh()->status);
        $this->assertEquals(30.00, (float) $this->courierProfile->fresh()->earnings_balance);

        // Test Idempotency / Already picked up
        $pickupRes2 = $this->withHeader('Idempotency-Key', 'mobile-pickup-2')
            ->postJson('/api/mobile/v1/rider/pickup', [
                'tracking_number' => $order->tracking_number,
            ]);
        $pickupRes2->assertOk()
            ->assertJsonPath('data.order_status', 'picked_up');
    }

    public function test_mobile_rider_active_deliveries_and_history(): void
    {
        Sanctum::actingAs($this->courier);

        $order1 = $this->createOrder(['status' => 'out_for_delivery']);
        $order2 = $this->createOrder(['status' => 'delivered']);

        // Active
        $activeRes = $this->getJson('/api/mobile/v1/rider/deliveries/active');
        $activeRes->assertOk();
        $this->assertTrue(collect($activeRes->json('data'))->contains('id', $order1->id));
        $this->assertFalse(collect($activeRes->json('data'))->contains('id', $order2->id));

        // History
        $historyRes = $this->getJson('/api/mobile/v1/rider/deliveries/history');
        $historyRes->assertOk();
        $this->assertTrue(collect($historyRes->json('data'))->contains('id', $order2->id));
    }

    public function test_mobile_rider_complete_delivery_workflow(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->courier);

        $order = $this->createOrder(['status' => 'out_for_delivery']);
        $otp = $order->generateDeliveryOtp();
        $podFile = UploadedFile::fake()->create('pod.jpg', 500, 'image/jpeg');

        // Wrong OTP should fail
        $failOtpRes = $this->postJson("/api/mobile/v1/rider/deliveries/{$order->id}/complete", [
            'delivery_otp' => '000000',
            'pod_image' => $podFile,
            'notes' => 'Wrong OTP test',
        ]);
        $failOtpRes->assertStatus(422);

        // Valid OTP should succeed
        $completeRes = $this->withHeader('Idempotency-Key', 'mobile-complete-1')
            ->postJson("/api/mobile/v1/rider/deliveries/{$order->id}/complete", [
                'delivery_otp' => $otp,
                'pod_image' => $podFile,
                'notes' => 'Delivered to buyer at gate',
            ]);

        $completeRes->assertOk()
            ->assertJsonPath('data.order_status', 'delivered');
        $this->assertEquals(50.00, (float) $completeRes->json('data.earned_pay'));
        $this->assertEquals(550.00, (float) $completeRes->json('data.cod_collected'));

        $this->assertEquals('delivered', $order->fresh()->status);
        $this->assertEquals(50.00, (float) $this->courierProfile->fresh()->earnings_balance);
        $this->assertEquals(550.00, (float) $this->courierProfile->fresh()->unremitted_cod_balance);
    }

    public function test_mobile_rider_fail_delivery_attempts_and_return_to_sender(): void
    {
        Sanctum::actingAs($this->courier);

        $order = $this->createOrder(['status' => 'out_for_delivery']);

        // Attempt 1: buyer not home -> failed_delivery
        $res1 = $this->postJson("/api/mobile/v1/rider/deliveries/{$order->id}/fail", [
            'reason' => 'buyer_not_home',
            'notes' => 'No one answered gate call',
        ]);
        $res1->assertOk()
            ->assertJsonPath('data.order_status', 'failed_delivery')
            ->assertJsonPath('data.attempt_count', 1);

        $this->assertEquals('failed_delivery', $order->fresh()->status);

        // Attempt 2: wrong address -> failed_delivery
        $order->refresh();
        $order->update(['status' => 'out_for_delivery']);
        $res2 = $this->postJson("/api/mobile/v1/rider/deliveries/{$order->id}/fail", [
            'reason' => 'wrong_address',
        ]);
        $res2->assertOk()
            ->assertJsonPath('data.order_status', 'failed_delivery')
            ->assertJsonPath('data.attempt_count', 2);

        // Attempt 3: 3rd attempt -> automatically return_to_sender
        $order->refresh();
        $order->update(['status' => 'out_for_delivery']);
        $res3 = $this->postJson("/api/mobile/v1/rider/deliveries/{$order->id}/fail", [
            'reason' => 'buyer_not_home',
        ]);
        $res3->assertOk()
            ->assertJsonPath('data.order_status', 'return_to_sender')
            ->assertJsonPath('data.attempt_count', 3);

        $this->assertEquals('return_to_sender', $order->fresh()->status);

        // Immediate refused -> return_to_sender on attempt 1
        $orderRefused = $this->createOrder(['status' => 'out_for_delivery']);
        $resRefused = $this->postJson("/api/mobile/v1/rider/deliveries/{$orderRefused->id}/fail", [
            'reason' => 'refused',
            'notes' => 'Buyer changed mind',
        ]);
        $resRefused->assertOk()
            ->assertJsonPath('data.order_status', 'return_to_sender');

        $this->assertEquals('return_to_sender', $orderRefused->fresh()->status);
    }

    public function test_mobile_rider_earnings_and_remittance(): void
    {
        Sanctum::actingAs($this->courier);

        // Set balances
        $this->courierProfile->update([
            'earnings_balance' => 80.00,
            'unremitted_cod_balance' => 550.00,
        ]);

        $earningsRes = $this->getJson('/api/mobile/v1/rider/earnings');
        $earningsRes->assertOk();
        $this->assertEquals(80.00, (float) $earningsRes->json('data.earnings_balance'));
        $this->assertEquals(550.00, (float) $earningsRes->json('data.unremitted_cod_balance'));

        // Submit remittance over balance should fail
        $failRemit = $this->postJson('/api/mobile/v1/rider/remittance', [
            'amount' => 600.00,
        ]);
        $failRemit->assertStatus(422);

        // Submit valid remittance
        $remitRes = $this->withHeader('Idempotency-Key', 'mobile-remit-1')
            ->postJson('/api/mobile/v1/rider/remittance', [
                'amount' => 550.00,
                'notes' => 'EOD remittance from mobile app',
            ]);
        $remitRes->assertOk()
            ->assertJsonPath('data.status', 'pending');
        $this->assertEquals(550.00, (float) $remitRes->json('data.amount'));
        $this->assertNotEmpty($remitRes->json('data.remittance_number'));
    }
}
