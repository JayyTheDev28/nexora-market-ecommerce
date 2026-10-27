<?php

namespace Tests\Feature;

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
use Tests\TestCase;

class RiderApiTest extends TestCase
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

    public function test_api_rider_login_authentication(): void
    {
        // Bad credentials
        $this->postJson('/api/v1/rider/login', [
            'email' => $this->courier->email,
            'password' => 'WrongPassword',
        ])->assertStatus(401);

        // Successful login
        $response = $this->postJson('/api/v1/rider/login', [
            'email' => $this->courier->email,
            'password' => 'Password123!@#',
        ]);
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.role', 'courier');
    }

    public function test_api_rider_profile_and_availability_toggle(): void
    {
        $this->actingAs($this->courier);

        // Profile
        $profileRes = $this->getJson('/api/v1/rider/profile');
        $profileRes->assertOk()
            ->assertJsonPath('plate_number', 'MC-7777')
            ->assertJsonPath('is_online', true);

        // Toggle availability
        $toggleRes = $this->postJson('/api/v1/rider/availability', ['is_online' => false]);
        $toggleRes->assertOk()->assertJsonPath('is_online', false);
        $this->assertFalse($this->courierProfile->fresh()->is_online);
    }

    public function test_api_rider_pickup_delivery_and_remittance_workflow(): void
    {
        Storage::fake('public');
        $this->actingAs($this->courier);

        // 1. Pickup
        $order = $this->createOrder(['status' => 'ready_for_pickup']);
        $pickupRes = $this->postJson('/api/v1/rider/pickup', [
            'tracking_number' => $order->tracking_number,
        ]);
        $pickupRes->assertOk()->assertJsonPath('order_status', 'picked_up');
        $this->assertEquals(30.00, (float) $this->courierProfile->fresh()->earnings_balance);

        // 2. Out for delivery & Complete with OTP
        $order->update(['status' => 'out_for_delivery']);
        $otp = $order->generateDeliveryOtp();
        $podFile = UploadedFile::fake()->create('pod.jpg', 500, 'image/jpeg');

        $completeRes = $this->postJson("/api/v1/rider/deliveries/{$order->id}/complete", [
            'delivery_otp' => $otp,
            'pod_image' => $podFile,
            'notes' => 'API Delivery',
        ]);
        $completeRes->assertOk()
            ->assertJsonPath('order_status', 'delivered');
        $this->assertEquals(550.00, (float) $completeRes->json('cod_collected'));

        // Total earnings: 30 pickup + 50 delivery = 80
        $this->assertEquals(80.00, (float) $this->courierProfile->fresh()->earnings_balance);
        $this->assertEquals(550.00, (float) $this->courierProfile->fresh()->unremitted_cod_balance);

        // 3. Earnings API
        $earningsRes = $this->getJson('/api/v1/rider/earnings');
        $earningsRes->assertOk();
        $this->assertEquals(80.00, (float) $earningsRes->json('earnings_balance'));
        $this->assertEquals(550.00, (float) $earningsRes->json('unremitted_cod_balance'));

        // 4. Remittance API
        $remitRes = $this->postJson('/api/v1/rider/remittance', [
            'amount' => 550.00,
            'notes' => 'EOD API remittance',
        ]);
        $remitRes->assertOk()->assertJsonPath('success', true);
    }
}
