<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\CourierProfile;
use App\Models\Hub;
use App\Models\Order;
use App\Models\RiderEarning;
use App\Models\SortingCenterProfile;
use App\Models\TransferManifest;
use App\Models\TransferManifestItem;
use App\Models\User;
use App\Services\OrderLogisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BarcodeWaybillAndScanningTest extends TestCase
{
    use RefreshDatabase;

    private function createLogisticsCompany(string $name, string $email): User
    {
        $user = User::create([
            'first_name' => $name,
            'last_name' => 'Logistics',
            'email' => $email,
            'contact_number' => '0917' . random_int(1000000, 9999999),
            'birthday' => '1990-01-01',
            'age' => 35,
            'sex' => 'male',
            'password' => Hash::make('Password123!'),
            'role' => 'logistics',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        SortingCenterProfile::create([
            'user_id' => $user->id,
            'business_name' => $name . ' Logistics',
        ]);

        return $user;
    }

    private function createHub(User $company, string $name, string $code): Hub
    {
        return Hub::create([
            'logistics_company_id' => $company->id,
            'name' => $name,
            'code' => $code,
            'province_code' => '043400000',
            'province_name' => 'Laguna',
            'city_code' => '043426000',
            'city_name' => 'Santa Cruz',
            'barangay_code' => '043426001',
            'barangay_name' => 'Poblacion I',
            'is_active' => true,
        ]);
    }

    private function createBuyer(): array
    {
        $buyer = User::factory()->create([
            'role' => 'buyer',
            'approval_status' => 'approved',
            'account_status' => 'active',
        ]);

        $address = Address::create([
            'user_id' => $buyer->id,
            'province_code' => '043400000',
            'province_name' => 'Laguna',
            'municipality_code' => '043426000',
            'municipality_name' => 'Santa Cruz',
            'barangay_code' => '043426001',
            'barangay_name' => 'Poblacion I',
            'street' => '45 Rizal Ave',
            'house_number' => 'Unit 2',
            'recipient_name' => $buyer->full_name,
            'recipient_phone' => '09171234567',
            'is_default' => true,
        ]);

        return [$buyer, $address];
    }

    private function createSeller(): User
    {
        return User::factory()->create([
            'role' => 'seller',
            'approval_status' => 'approved',
            'account_status' => 'active',
        ]);
    }

    private function createRider(User $company, string $role = 'rider'): User
    {
        $rider = User::create([
            'first_name' => ucfirst($role),
            'last_name' => 'Operator',
            'email' => $role . '_' . uniqid() . '@nexora.test',
            'contact_number' => '0918' . random_int(1000000, 9999999),
            'birthday' => '1993-04-12',
            'age' => 32,
            'sex' => 'male',
            'password' => Hash::make('Password123!'),
            'role' => $role,
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        CourierProfile::create([
            'user_id' => $rider->id,
            'sorting_center_id' => $company->id,
            'logistics_company_id' => $company->id,
            'is_online' => true,
            'vehicle_type' => $role === 'rider' ? 'Motorcycle' : 'Box Truck',
            'plate_number' => 'NX-' . random_int(1000, 9999),
            'license_number' => 'N01-23-' . random_int(100000, 999999),
            'license_expiry_date' => now()->addYears(2),
            'vehicle_verification_status' => 'verified',
        ]);

        return $rider;
    }

    public function test_order_creation_assigns_unique_tracking_number_and_waybill_renders_svg_code128_stably(): void
    {
        [$buyer, $address] = $this->createBuyer();
        $seller = $this->createSeller();

        $order = Order::create([
            'order_number' => 'NX-ORD-' . uniqid(),
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'address_id' => $address->id,
            'status' => 'confirmed',
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'subtotal' => 350.00,
            'shipping_fee' => 50.00,
            'total' => 400.00,
            'placed_at' => now(),
        ]);

        $this->assertNotEmpty($order->tracking_number);
        $this->assertNotEquals($order->order_number, $order->tracking_number);

        $firstTracking = $order->tracking_number;

        // Calling ensureTrackingNumber or reprinting waybill must preserve existing canonical tracking_number
        $this->actingAs($seller);

        $firstResponse = $this->get(route('seller.orders.waybill', $order));
        $firstResponse->assertStatus(200);
        $firstResponse->assertSee('<svg', false);
        $firstResponse->assertSee('data-tracking-number="' . $firstTracking . '"', false);
        $firstResponse->assertSee($order->order_number);

        $secondResponse = $this->get(route('seller.orders.waybill', $order));
        $secondResponse->assertStatus(200);
        $secondResponse->assertSee('data-tracking-number="' . $firstTracking . '"', false);
        $this->assertEquals($firstTracking, $order->fresh()->tracking_number);

        // Unassigned tracking number generation remains unique across orders
        $secondOrder = Order::create([
            'order_number' => 'NX-ORD-' . uniqid(),
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'address_id' => $address->id,
            'status' => 'placed',
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'subtotal' => 200.00,
            'shipping_fee' => 50.00,
            'total' => 250.00,
            'placed_at' => now(),
        ]);

        $this->assertNotEmpty($secondOrder->tracking_number);
        $this->assertNotEquals($firstTracking, $secondOrder->tracking_number);
        $this->assertSame($firstTracking, OrderLogisticsService::ensureTrackingNumber($order));
    }

    public function test_rider_pickup_requires_matching_order_barcode_and_is_idempotent_for_payouts(): void
    {
        $company = $this->createLogisticsCompany('Laguna Express', 'laguna_' . uniqid() . '@nexora.test');
        $rider = $this->createRider($company, 'rider');
        $transferCourier = $this->createRider($company, 'courier');
        [$buyer, $address] = $this->createBuyer();
        $seller = $this->createSeller();

        $orderA = Order::create([
            'order_number' => 'NX-ORD-A-' . uniqid(),
            'tracking_number' => 'NX-TRK-PICKUPA1',
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'address_id' => $address->id,
            'pickup_logistics_id' => $company->id,
            'sorting_center_id' => $company->id,
            'pickup_courier_id' => $rider->id,
            'status' => 'ready_for_pickup',
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'subtotal' => 450.00,
            'shipping_fee' => 50.00,
            'total' => 500.00,
            'placed_at' => now(),
        ]);

        $orderB = Order::create([
            'order_number' => 'NX-ORD-B-' . uniqid(),
            'tracking_number' => 'NX-TRK-PICKUPB2',
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'address_id' => $address->id,
            'pickup_logistics_id' => $company->id,
            'sorting_center_id' => $company->id,
            'pickup_courier_id' => $rider->id,
            'status' => 'ready_for_pickup',
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'subtotal' => 450.00,
            'shipping_fee' => 50.00,
            'total' => 500.00,
            'placed_at' => now(),
        ]);

        // Transfer courier is forbidden from last-mile rider pickup endpoint
        Sanctum::actingAs($transferCourier);
        $this->postJson(route('api.mobile.rider.pickup'), [
            'tracking_number' => $orderA->tracking_number,
            'expected_order_id' => $orderA->id,
        ])->assertStatus(403);

        // Wrong barcode for expected order must fail with 422
        Sanctum::actingAs($rider);
        $this->postJson(route('api.mobile.rider.pickup'), [
            'tracking_number' => $orderB->tracking_number,
            'expected_order_id' => $orderA->id,
        ])->assertStatus(422);

        $this->assertEquals('ready_for_pickup', $orderA->fresh()->status);

        // Valid scan confirms pickup once
        $this->postJson(route('api.mobile.rider.pickup'), [
            'tracking_number' => $orderA->tracking_number,
            'expected_order_id' => $orderA->id,
        ])->assertStatus(200)
          ->assertJsonPath('data.order_status', 'picked_up');

        $this->assertEquals('picked_up', $orderA->fresh()->status);

        $earningsCountAfterFirst = RiderEarning::where('courier_id', $rider->id)
            ->where('order_id', $orderA->id)
            ->count();
        $this->assertEquals(1, $earningsCountAfterFirst);

        // Duplicate scan cannot duplicate earnings or break state
        $this->postJson(route('api.mobile.rider.pickup'), [
            'tracking_number' => $orderA->tracking_number,
            'expected_order_id' => $orderA->id,
        ])->assertStatus(200);

        $this->assertEquals(
            1,
            RiderEarning::where('courier_id', $rider->id)->where('order_id', $orderA->id)->count()
        );
    }

    public function test_logistics_incoming_scanner_blocks_active_manifest_bypass_and_validates_custody(): void
    {
        $companyA = $this->createLogisticsCompany('Company A', 'compa_' . uniqid() . '@nexora.test');
        $companyB = $this->createLogisticsCompany('Company B', 'compb_' . uniqid() . '@nexora.test');
        $hubA = $this->createHub($companyA, 'Calamba Hub', 'CAL-01');
        $hubB = $this->createHub($companyB, 'Santa Cruz Hub', 'STC-01');

        [$buyer, $address] = $this->createBuyer();
        $seller = $this->createSeller();

        $inboundOrder = Order::create([
            'order_number' => 'NX-INB-' . uniqid(),
            'tracking_number' => 'NX-TRK-INBOUND1',
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'address_id' => $address->id,
            'pickup_logistics_id' => $companyA->id,
            'current_custody_logistics_id' => $companyA->id,
            'origin_hub_id' => $hubA->id,
            'current_hub_id' => $hubA->id,
            'status' => 'picked_up',
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'subtotal' => 600.00,
            'shipping_fee' => 60.00,
            'total' => 660.00,
            'placed_at' => now(),
        ]);

        // Unauthorized company cannot lookup or receive companyA's parcel
        $this->actingAs($companyB);
        $this->postJson(route('logistics.incoming.lookup'), [
            'identifier' => $inboundOrder->tracking_number,
            'hub_id' => $hubB->id,
        ])->assertStatus(403);

        // Authorized company lookup succeeds
        $this->actingAs($companyA);
        $this->postJson(route('logistics.incoming.lookup'), [
            'identifier' => $inboundOrder->tracking_number,
            'hub_id' => $hubA->id,
        ])->assertStatus(200)
          ->assertJsonPath('data.tracking_number', $inboundOrder->tracking_number)
          ->assertJsonPath('data.eligible_for_receipt', true);

        // Scan marks received at selected hub (and auto-sorts when routed to destination hub)
        $this->post(route('logistics.incoming.scan'), [
            'identifier' => $inboundOrder->tracking_number,
            'hub_id' => $hubA->id,
        ])->assertSessionHas('status');

        $this->assertContains($inboundOrder->fresh()->status, ['received_at_hub', 'sorted']);

        // Active transfer manifest parcel cannot be bypassed via incoming scanner
        $manifestOrder = Order::create([
            'order_number' => 'NX-MAN-' . uniqid(),
            'tracking_number' => 'NX-TRK-MANIFEST1',
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'address_id' => $address->id,
            'pickup_logistics_id' => $companyA->id,
            'delivery_logistics_id' => $companyB->id,
            'current_custody_logistics_id' => $companyA->id,
            'origin_hub_id' => $hubA->id,
            'destination_hub_id' => $hubB->id,
            'current_hub_id' => $hubA->id,
            'status' => 'in_transit_to_hub',
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'subtotal' => 700.00,
            'shipping_fee' => 80.00,
            'total' => 780.00,
            'placed_at' => now(),
        ]);

        $manifest = TransferManifest::create([
            'manifest_number' => 'MNF-' . uniqid(),
            'sender_logistics_id' => $companyA->id,
            'receiver_logistics_id' => $companyB->id,
            'origin_hub_id' => $hubA->id,
            'destination_hub_id' => $hubB->id,
            'status' => TransferManifest::STATUS_IN_TRANSIT,
            'total_parcels' => 1,
            'loaded_parcels' => 1,
            'received_parcels' => 0,
            'missing_parcels' => 0,
            'damaged_parcels' => 0,
        ]);

        TransferManifestItem::create([
            'manifest_id' => $manifest->id,
            'order_id' => $manifestOrder->id,
            'tracking_number' => $manifestOrder->tracking_number,
            'loading_status' => TransferManifestItem::LOADING_LOADED,
            'receiving_status' => TransferManifestItem::RECEIVING_PENDING,
        ]);

        $this->actingAs($companyB);

        // Incoming lookup flags active manifest requirement
        $this->postJson(route('logistics.incoming.lookup'), [
            'identifier' => $manifestOrder->tracking_number,
            'hub_id' => $hubB->id,
        ])->assertStatus(200)
          ->assertJsonPath('data.requires_manifest_receiving', true)
          ->assertJsonPath('data.eligible_for_receipt', false);

        // Incoming scan refuses bypass with validation error and preserves original custody
        $this->post(route('logistics.incoming.scan'), [
            'identifier' => $manifestOrder->tracking_number,
            'hub_id' => $hubB->id,
        ])->assertSessionHasErrors('identifier');

        $this->assertEquals($companyA->id, $manifestOrder->fresh()->current_custody_logistics_id);

        // Manifest parcel lookup succeeds only for destination company
        $this->actingAs($companyA);
        $this->postJson(route('logistics.transfers.manifests.lookup-parcel', $manifest), [
            'tracking_number' => $manifestOrder->tracking_number,
        ])->assertStatus(403);

        $this->actingAs($companyB);
        $this->postJson(route('logistics.transfers.manifests.lookup-parcel', $manifest), [
            'tracking_number' => $manifestOrder->tracking_number,
        ])->assertStatus(200)
          ->assertJsonPath('data.tracking_number', $manifestOrder->tracking_number)
          ->assertJsonPath('data.receiving_status', TransferManifestItem::RECEIVING_PENDING);
    }
}
