<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\CourierProfile;
use App\Models\Hub;
use App\Models\HubTransfer;
use App\Models\Order;
use App\Models\OrderStatusHistory;
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

        // Duplicate scan is rejected and cannot duplicate earnings or break state
        $this->postJson(route('api.mobile.rider.pickup'), [
            'tracking_number' => $orderA->tracking_number,
            'expected_order_id' => $orderA->id,
        ])->assertStatus(422);

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

    public function test_incoming_lookup_returns_structured_outcomes_and_normalizes_barcode_variants(): void
    {
        $companyA = $this->createLogisticsCompany('Logistics One', 'log1_' . uniqid() . '@nexora.test');
        $companyB = $this->createLogisticsCompany('Logistics Two', 'log2_' . uniqid() . '@nexora.test');
        $hubA1 = $this->createHub($companyA, 'Calamba Main Hub', 'CAL-M1');
        $hubA2 = $this->createHub($companyA, 'San Pablo Hub', 'SPB-01');
        $hubB = $this->createHub($companyB, 'Laguna East Hub', 'LAG-E1');

        [$buyer, $address] = $this->createBuyer();
        $seller = $this->createSeller();

        $eligibleOrder = Order::create([
            'order_number' => 'NX-ELG-' . uniqid(),
            'tracking_number' => 'NX-TRK-' . strtoupper(substr(uniqid(), -8)),
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'address_id' => $address->id,
            'pickup_logistics_id' => $companyA->id,
            'delivery_logistics_id' => $companyA->id,
            'current_custody_logistics_id' => $companyA->id,
            'origin_hub_id' => $hubA1->id,
            'destination_hub_id' => $hubA1->id,
            'status' => 'picked_up',
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'subtotal' => 400.00,
            'shipping_fee' => 50.00,
            'total' => 450.00,
            'placed_at' => now(),
        ]);

        $receivedOrder = Order::create([
            'order_number' => 'NX-RCV-' . uniqid(),
            'tracking_number' => 'NX-TRK-' . strtoupper(substr(uniqid(), -8)),
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'address_id' => $address->id,
            'pickup_logistics_id' => $companyA->id,
            'delivery_logistics_id' => $companyA->id,
            'current_custody_logistics_id' => $companyA->id,
            'origin_hub_id' => $hubA1->id,
            'destination_hub_id' => $hubA1->id,
            'current_hub_id' => $hubA1->id,
            'status' => 'received_at_hub',
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'subtotal' => 300.00,
            'shipping_fee' => 50.00,
            'total' => 350.00,
            'placed_at' => now(),
        ]);

        $this->actingAs($companyA);

        // Lowercase + whitespace + USB scanner CR/LF normalized cleanly
        $dirtyInput = '  ' . strtolower($eligibleOrder->tracking_number) . "\r\n";
        $this->postJson(route('logistics.incoming.lookup'), [
            'identifier' => $dirtyInput,
            'hub_id' => $hubA1->id,
        ])->assertStatus(200)
          ->assertJsonPath('outcome', 'eligible')
          ->assertJsonPath('data.eligible_for_receipt', true)
          ->assertJsonPath('data.tracking_number', $eligibleOrder->tracking_number);

        // Wrong hub returns 422 with wrong_hub outcome and not eligible
        $this->postJson(route('logistics.incoming.lookup'), [
            'identifier' => $eligibleOrder->tracking_number,
            'hub_id' => $hubA2->id,
        ])->assertStatus(422)
          ->assertJsonPath('outcome', 'wrong_hub')
          ->assertJsonPath('data.eligible_for_receipt', false);

        // Selecting another company's hub returns 403 invalid_hub
        $this->postJson(route('logistics.incoming.lookup'), [
            'identifier' => $eligibleOrder->tracking_number,
            'hub_id' => $hubB->id,
        ])->assertStatus(403)
          ->assertJsonPath('outcome', 'invalid_hub');

        // Unknown tracking returns 404 not_found
        $this->postJson(route('logistics.incoming.lookup'), [
            'identifier' => 'NX-TRK-DOESNOTEXIST',
            'hub_id' => $hubA1->id,
        ])->assertStatus(404)
          ->assertJsonPath('outcome', 'not_found');

        // Already received parcel returns already_received and eligible_for_receipt = false
        $this->postJson(route('logistics.incoming.lookup'), [
            'identifier' => $receivedOrder->tracking_number,
            'hub_id' => $hubA1->id,
        ])->assertStatus(200)
          ->assertJsonPath('outcome', 'already_received')
          ->assertJsonPath('data.already_received', true)
          ->assertJsonPath('data.eligible_for_receipt', false);

        // Unauthorized company returns 403 unauthorized_company
        $this->actingAs($companyB);
        $this->postJson(route('logistics.incoming.lookup'), [
            'identifier' => $eligibleOrder->tracking_number,
            'hub_id' => $hubB->id,
        ])->assertStatus(403)
          ->assertJsonPath('outcome', 'unauthorized_company');
    }

    public function test_incoming_page_renders_simplified_one_scan_scanner_without_step_bar(): void
    {
        $company = $this->createLogisticsCompany('Logistics UI Test', 'logui_' . uniqid() . '@nexora.test');
        $this->createHub($company, 'Test Hub UI', 'THU-01');

        $response = $this->actingAs($company)->get(route('logistics.incoming.index'));

        $response->assertStatus(200)
            ->assertSee('vendor/html5-qrcode/html5-qrcode.min.js', false)
            ->assertSee('data-camera-status-panel="incoming-camera"', false)
            ->assertSee('data-fullframe-scanner="true"', false)
            ->assertSee('id="incoming-session-count"', false)
            ->assertSee('Parcels Received This Session', false)
            ->assertSee('PARCEL RECEIVED', false)
            ->assertSee('ALREADY_RECEIVED', false)
            ->assertSee('INVALID_BARCODE', false)
            ->assertSee('WRONG_HUB', false)
            ->assertSee('MANIFEST_REQUIRED', false)
            ->assertSee('Retry', false)
            ->assertSee('Flashlight', false)
            ->assertSee('Switch Camera', false)
            ->assertSee('Manual Entry', false)
            ->assertDontSee('1. Camera Feed', false)
            ->assertDontSee('Step 1/4', false)
            ->assertDontSee('id="incoming-confirm-btn"', false)
            ->assertDontSee('qrbox:', false);
    }

    public function test_manifest_lookup_returns_structured_outcomes_and_already_received_state(): void
    {
        $senderCo = $this->createLogisticsCompany('Sender Co', 'snd_' . uniqid() . '@nexora.test');
        $receiverCo = $this->createLogisticsCompany('Receiver Co', 'rcv_' . uniqid() . '@nexora.test');
        $originHub = $this->createHub($senderCo, 'Calamba Hub', 'CAL-02');
        $destHub = $this->createHub($receiverCo, 'Laguna Hub', 'LAG-02');

        [$buyer, $address] = $this->createBuyer();
        $seller = $this->createSeller();

        $order = Order::create([
            'order_number' => 'NX-MNF-' . uniqid(),
            'tracking_number' => 'NX-TRK-' . strtoupper(substr(uniqid(), -8)),
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'address_id' => $address->id,
            'pickup_logistics_id' => $senderCo->id,
            'delivery_logistics_id' => $receiverCo->id,
            'current_custody_logistics_id' => $senderCo->id,
            'origin_hub_id' => $originHub->id,
            'destination_hub_id' => $destHub->id,
            'status' => 'in_transit_hub',
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'subtotal' => 700.00,
            'shipping_fee' => 80.00,
            'total' => 780.00,
            'placed_at' => now(),
        ]);

        $manifest = TransferManifest::create([
            'manifest_number' => 'MNF-' . uniqid(),
            'sender_logistics_id' => $senderCo->id,
            'receiver_logistics_id' => $receiverCo->id,
            'origin_hub_id' => $originHub->id,
            'destination_hub_id' => $destHub->id,
            'status' => TransferManifest::STATUS_ARRIVED,
            'total_parcels' => 1,
            'loaded_parcels' => 1,
            'received_parcels' => 0,
            'missing_parcels' => 0,
            'damaged_parcels' => 0,
        ]);

        $hubTransfer = HubTransfer::create([
            'manifest_id' => $manifest->id,
            'order_id' => $order->id,
            'tracking_number' => $order->tracking_number,
            'sender_logistics_id' => $senderCo->id,
            'sender_hub_id' => $originHub->id,
            'receiver_logistics_id' => $receiverCo->id,
            'receiver_hub_id' => $destHub->id,
            'status' => 'in_transit',
            'dispatched_at' => now(),
            'dispatched_by' => $senderCo->id,
        ]);

        TransferManifestItem::create([
            'manifest_id' => $manifest->id,
            'order_id' => $order->id,
            'hub_transfer_id' => $hubTransfer->id,
            'tracking_number' => $order->tracking_number,
            'loading_status' => TransferManifestItem::LOADING_LOADED,
            'receiving_status' => TransferManifestItem::RECEIVING_PENDING,
        ]);

        $this->actingAs($receiverCo);

        // Lookup with lowercase + whitespace succeeds with eligible outcome
        $this->postJson(route('logistics.transfers.manifests.lookup-parcel', $manifest), [
            'tracking_number' => ' ' . strtolower($order->tracking_number) . "\n",
        ])->assertStatus(200)
          ->assertJsonPath('outcome', 'eligible')
          ->assertJsonPath('data.already_received', false);

        // Wrong tracking number fails with not_on_manifest outcome
        $this->postJson(route('logistics.transfers.manifests.lookup-parcel', $manifest), [
            'tracking_number' => 'NX-TRK-WRONGITEM',
        ])->assertStatus(422)
          ->assertJsonPath('outcome', 'not_on_manifest');

        // Verify parcel as received
        $this->post(route('logistics.transfers.manifests.verify-parcel', $manifest), [
            'tracking_number' => strtolower($order->tracking_number),
            'classification' => 'received',
        ])->assertSessionHas('status');

        // Subsequent lookup flags already_received
        $this->postJson(route('logistics.transfers.manifests.lookup-parcel', $manifest), [
            'tracking_number' => $order->tracking_number,
        ])->assertStatus(200)
          ->assertJsonPath('outcome', 'already_received')
          ->assertJsonPath('data.already_received', true);
    }

    public function test_one_scan_automatic_incoming_receiving_batch_idempotency_and_security(): void
    {
        $companyA = $this->createLogisticsCompany('Auto Scan Co', 'auto_' . uniqid() . '@nexora.test');
        $companyB = $this->createLogisticsCompany('Other Co', 'other_' . uniqid() . '@nexora.test');
        $hubA = $this->createHub($companyA, 'Calamba Main', 'CAL-AUTO1');
        $hubOther = $this->createHub($companyA, 'San Pablo', 'SPB-AUTO1');

        [$buyer, $address] = $this->createBuyer();
        $seller = $this->createSeller();

        $parcelOne = Order::create([
            'order_number' => 'NX-ONE-' . uniqid(),
            'tracking_number' => 'NX-TRK-' . strtoupper(substr(uniqid(), -8)),
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'address_id' => $address->id,
            'pickup_logistics_id' => $companyA->id,
            'delivery_logistics_id' => $companyA->id,
            'current_custody_logistics_id' => $companyA->id,
            'origin_hub_id' => $hubA->id,
            'destination_hub_id' => $hubA->id,
            'status' => 'picked_up',
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'subtotal' => 450.00,
            'shipping_fee' => 50.00,
            'total' => 500.00,
            'placed_at' => now(),
        ]);

        $parcelTwo = Order::create([
            'order_number' => 'NX-TWO-' . uniqid(),
            'tracking_number' => 'NX-TRK-' . strtoupper(substr(uniqid(), -8)),
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'address_id' => $address->id,
            'pickup_logistics_id' => $companyA->id,
            'delivery_logistics_id' => $companyA->id,
            'current_custody_logistics_id' => $companyA->id,
            'origin_hub_id' => $hubA->id,
            'destination_hub_id' => $hubOther->id,
            'status' => 'picked_up',
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'subtotal' => 300.00,
            'shipping_fee' => 50.00,
            'total' => 350.00,
            'placed_at' => now(),
        ]);

        $flaggedParcel = Order::create([
            'order_number' => 'NX-FLG-' . uniqid(),
            'tracking_number' => 'NX-TRK-' . strtoupper(substr(uniqid(), -8)),
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'address_id' => $address->id,
            'pickup_logistics_id' => $companyA->id,
            'delivery_logistics_id' => $companyA->id,
            'current_custody_logistics_id' => $companyA->id,
            'origin_hub_id' => $hubA->id,
            'destination_hub_id' => $hubOther->id,
            'status' => 'picked_up',
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'subtotal' => 250.00,
            'shipping_fee' => 50.00,
            'total' => 300.00,
            'placed_at' => now(),
        ]);

        OrderStatusHistory::create([
            'order_id' => $flaggedParcel->id,
            'status' => 'picked_up',
            'note' => 'ISSUE FLAGGED: Damaged packaging requires manual inspection.',
            'changed_by' => $companyA->id,
        ]);

        $manifestParcel = Order::create([
            'order_number' => 'NX-MNP-' . uniqid(),
            'tracking_number' => 'NX-TRK-' . strtoupper(substr(uniqid(), -8)),
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'address_id' => $address->id,
            'pickup_logistics_id' => $companyB->id,
            'delivery_logistics_id' => $companyA->id,
            'current_custody_logistics_id' => $companyB->id,
            'origin_hub_id' => $hubOther->id,
            'destination_hub_id' => $hubA->id,
            'status' => 'in_transit_hub',
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'subtotal' => 600.00,
            'shipping_fee' => 70.00,
            'total' => 670.00,
            'placed_at' => now(),
        ]);

        $manifest = TransferManifest::create([
            'manifest_number' => 'MNF-AUTO-' . uniqid(),
            'sender_logistics_id' => $companyB->id,
            'receiver_logistics_id' => $companyA->id,
            'origin_hub_id' => $hubOther->id,
            'destination_hub_id' => $hubA->id,
            'status' => TransferManifest::STATUS_IN_TRANSIT,
            'total_parcels' => 1,
            'loaded_parcels' => 1,
            'received_parcels' => 0,
            'missing_parcels' => 0,
            'damaged_parcels' => 0,
        ]);

        TransferManifestItem::create([
            'manifest_id' => $manifest->id,
            'order_id' => $manifestParcel->id,
            'tracking_number' => $manifestParcel->tracking_number,
            'loading_status' => TransferManifestItem::LOADING_LOADED,
            'receiving_status' => TransferManifestItem::RECEIVING_PENDING,
        ]);

        $this->actingAs($companyA);

        // Batch scan 1 (clean lowercase/whitespace normalized)
        $this->postJson(route('logistics.incoming.scan'), [
            'identifier' => ' ' . strtolower($parcelOne->tracking_number) . "\n",
            'hub_id' => $hubA->id,
            'auto_scan' => true,
        ])->assertStatus(200)
          ->assertJsonPath('outcome', 'received')
          ->assertJsonPath('newly_received', true)
          ->assertJsonPath('already_received', false)
          ->assertJsonPath('data.tracking_number', $parcelOne->tracking_number)
          ->assertJsonPath('data.status', 'sorted')
          ->assertJsonPath('counts.awaiting', 2);

        $historyCountAfterFirst = OrderStatusHistory::where('order_id', $parcelOne->id)->count();

        // Duplicate scan of parcel 1 is idempotent (no duplicate receiving history)
        $this->postJson(route('logistics.incoming.scan'), [
            'identifier' => $parcelOne->tracking_number,
            'hub_id' => $hubA->id,
            'auto_scan' => true,
        ])->assertStatus(200)
          ->assertJsonPath('outcome', 'already_received')
          ->assertJsonPath('newly_received', false)
          ->assertJsonPath('already_received', true);

        $this->assertSame(
            $historyCountAfterFirst,
            OrderStatusHistory::where('order_id', $parcelOne->id)->count()
        );

        // Wrong hub blocked
        $this->postJson(route('logistics.incoming.scan'), [
            'identifier' => $parcelTwo->tracking_number,
            'hub_id' => $hubOther->id,
            'auto_scan' => true,
        ])->assertStatus(422)
          ->assertJsonPath('outcome', 'wrong_hub')
          ->assertJsonPath('newly_received', false);

        // Correct hub receives parcel 2 continuously
        $this->postJson(route('logistics.incoming.scan'), [
            'identifier' => $parcelTwo->tracking_number,
            'hub_id' => $hubA->id,
            'auto_scan' => true,
        ])->assertStatus(200)
          ->assertJsonPath('outcome', 'received')
          ->assertJsonPath('newly_received', true)
          ->assertJsonPath('data.status', 'received_at_hub')
          ->assertJsonPath('counts.received', 1)
          ->assertJsonPath('counts.awaiting', 1);

        // Invalid barcode returns 404 invalid_barcode
        $this->postJson(route('logistics.incoming.scan'), [
            'identifier' => 'NX-TRK-DOESNOTEXIST',
            'hub_id' => $hubA->id,
            'auto_scan' => true,
        ])->assertStatus(404)
          ->assertJsonPath('outcome', 'invalid_barcode')
          ->assertJsonPath('newly_received', false);

        // Flagged parcel blocked from auto-scan acceptance
        $this->postJson(route('logistics.incoming.scan'), [
            'identifier' => $flaggedParcel->tracking_number,
            'hub_id' => $hubA->id,
            'auto_scan' => true,
        ])->assertStatus(422)
          ->assertJsonPath('outcome', 'flagged_for_investigation')
          ->assertJsonPath('newly_received', false);
        $this->assertSame('picked_up', $flaggedParcel->fresh()->status);

        // Active Transfer Manifest parcel cannot bypass manifest workflow
        $this->postJson(route('logistics.incoming.scan'), [
            'identifier' => $manifestParcel->tracking_number,
            'hub_id' => $hubA->id,
            'auto_scan' => true,
        ])->assertStatus(422)
          ->assertJsonPath('outcome', 'manifest_required')
          ->assertJsonPath('newly_received', false)
          ->assertJsonPath('manifest.manifest_number', $manifest->manifest_number);

        // Unauthorized company blocked even when using its own valid hub
        $hubB = $this->createHub($companyB, 'Unauthorized Co Hub', 'UCH-01');
        $this->actingAs($companyB);
        $this->postJson(route('logistics.incoming.scan'), [
            'identifier' => $parcelTwo->tracking_number,
            'hub_id' => $hubB->id,
            'auto_scan' => true,
        ])->assertStatus(403)
          ->assertJsonPath('outcome', 'unauthorized_company')
          ->assertJsonPath('newly_received', false);
    }

    public function test_manifest_auto_scan_receives_clean_parcels_and_blocks_damaged_or_flagged_items(): void
    {
        $senderCo = $this->createLogisticsCompany('Sender Mnf', 'smnf_' . uniqid() . '@nexora.test');
        $receiverCo = $this->createLogisticsCompany('Receiver Mnf', 'rmnf_' . uniqid() . '@nexora.test');
        $originHub = $this->createHub($senderCo, 'Calamba Hub M', 'CAL-M9');
        $destHub = $this->createHub($receiverCo, 'Laguna Hub M', 'LAG-M9');

        [$buyer, $address] = $this->createBuyer();
        $seller = $this->createSeller();

        $cleanOrder = Order::create([
            'order_number' => 'NX-CLN-' . uniqid(),
            'tracking_number' => 'NX-TRK-' . strtoupper(substr(uniqid(), -8)),
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'address_id' => $address->id,
            'pickup_logistics_id' => $senderCo->id,
            'delivery_logistics_id' => $receiverCo->id,
            'current_custody_logistics_id' => $senderCo->id,
            'origin_hub_id' => $originHub->id,
            'destination_hub_id' => $destHub->id,
            'status' => 'in_transit_hub',
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'subtotal' => 500.00,
            'shipping_fee' => 60.00,
            'total' => 560.00,
            'placed_at' => now(),
        ]);

        $damagedOrder = Order::create([
            'order_number' => 'NX-DMG-' . uniqid(),
            'tracking_number' => 'NX-TRK-' . strtoupper(substr(uniqid(), -8)),
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'address_id' => $address->id,
            'pickup_logistics_id' => $senderCo->id,
            'delivery_logistics_id' => $receiverCo->id,
            'current_custody_logistics_id' => $senderCo->id,
            'origin_hub_id' => $originHub->id,
            'destination_hub_id' => $destHub->id,
            'status' => 'in_transit_hub',
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'subtotal' => 400.00,
            'shipping_fee' => 60.00,
            'total' => 460.00,
            'placed_at' => now(),
        ]);

        $flaggedHistoryOrder = Order::create([
            'order_number' => 'NX-HST-' . uniqid(),
            'tracking_number' => 'NX-TRK-' . strtoupper(substr(uniqid(), -8)),
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'address_id' => $address->id,
            'pickup_logistics_id' => $senderCo->id,
            'delivery_logistics_id' => $receiverCo->id,
            'current_custody_logistics_id' => $senderCo->id,
            'origin_hub_id' => $originHub->id,
            'destination_hub_id' => $destHub->id,
            'status' => 'in_transit_hub',
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'subtotal' => 300.00,
            'shipping_fee' => 60.00,
            'total' => 360.00,
            'placed_at' => now(),
        ]);

        OrderStatusHistory::create([
            'order_id' => $flaggedHistoryOrder->id,
            'status' => 'in_transit_hub',
            'note' => 'ISSUE FLAGGED: Tampered seal noted during transit.',
            'changed_by' => $senderCo->id,
        ]);

        $manifest = TransferManifest::create([
            'manifest_number' => 'MNF-BAT-' . uniqid(),
            'sender_logistics_id' => $senderCo->id,
            'receiver_logistics_id' => $receiverCo->id,
            'origin_hub_id' => $originHub->id,
            'destination_hub_id' => $destHub->id,
            'status' => TransferManifest::STATUS_ARRIVED,
            'total_parcels' => 3,
            'loaded_parcels' => 3,
            'received_parcels' => 0,
            'missing_parcels' => 0,
            'damaged_parcels' => 1,
        ]);

        foreach ([
            [$cleanOrder, TransferManifestItem::RECEIVING_PENDING],
            [$damagedOrder, TransferManifestItem::RECEIVING_DAMAGED],
            [$flaggedHistoryOrder, TransferManifestItem::RECEIVING_PENDING],
        ] as [$ord, $recStatus]) {
            $ht = HubTransfer::create([
                'manifest_id' => $manifest->id,
                'order_id' => $ord->id,
                'tracking_number' => $ord->tracking_number,
                'sender_logistics_id' => $senderCo->id,
                'sender_hub_id' => $originHub->id,
                'receiver_logistics_id' => $receiverCo->id,
                'receiver_hub_id' => $destHub->id,
                'status' => 'in_transit',
                'dispatched_at' => now(),
                'dispatched_by' => $senderCo->id,
            ]);

            TransferManifestItem::create([
                'manifest_id' => $manifest->id,
                'order_id' => $ord->id,
                'hub_transfer_id' => $ht->id,
                'tracking_number' => $ord->tracking_number,
                'loading_status' => TransferManifestItem::LOADING_LOADED,
                'receiving_status' => $recStatus,
            ]);
        }

        $this->actingAs($receiverCo);

        // Clean parcel auto-receives on single scan
        $this->postJson(route('logistics.transfers.manifests.verify-parcel', $manifest), [
            'tracking_number' => ' ' . strtolower($cleanOrder->tracking_number),
            'classification' => 'received',
            'auto_scan' => true,
        ])->assertStatus(200)
          ->assertJsonPath('outcome', 'received')
          ->assertJsonPath('newly_received', true)
          ->assertJsonPath('item.receiving_status', 'received');

        // Duplicate auto-scan is idempotent
        $this->postJson(route('logistics.transfers.manifests.verify-parcel', $manifest), [
            'tracking_number' => $cleanOrder->tracking_number,
            'classification' => 'received',
            'auto_scan' => true,
        ])->assertStatus(200)
          ->assertJsonPath('outcome', 'already_received')
          ->assertJsonPath('newly_received', false)
          ->assertJsonPath('already_received', true);

        // Already-damaged item blocked from auto-scan approval
        $this->postJson(route('logistics.transfers.manifests.verify-parcel', $manifest), [
            'tracking_number' => $damagedOrder->tracking_number,
            'classification' => 'received',
            'auto_scan' => true,
        ])->assertStatus(422)
          ->assertJsonPath('outcome', 'flagged_for_investigation')
          ->assertJsonPath('newly_received', false);

        // Item with ISSUE FLAGGED history blocked from auto-scan approval
        $this->postJson(route('logistics.transfers.manifests.verify-parcel', $manifest), [
            'tracking_number' => $flaggedHistoryOrder->tracking_number,
            'classification' => 'received',
            'auto_scan' => true,
        ])->assertStatus(422)
          ->assertJsonPath('outcome', 'flagged_for_investigation')
          ->assertJsonPath('newly_received', false);

        // Parcel belonging to no manifest returns not_on_manifest
        $this->postJson(route('logistics.transfers.manifests.verify-parcel', $manifest), [
            'tracking_number' => 'NX-TRK-WRONGMNF',
            'classification' => 'received',
            'auto_scan' => true,
        ])->assertStatus(422)
          ->assertJsonPath('outcome', 'not_on_manifest')
          ->assertJsonPath('newly_received', false);
    }
}
