<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Category;
use App\Models\CompensationPolicy;
use App\Models\CourierProfile;
use App\Models\Hub;
use App\Models\LogisticsProfile;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RiderEarning;
use App\Models\SellerProfile;
use App\Models\User;
use App\Models\Zone;
use App\Models\ZoneBarangay;
use App\Services\InterRegionLogisticsService;
use App\Services\JobDispatchService;
use App\Services\TransferManifestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EndToEndLogisticsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_san_pablo_to_cavite_end_to_end_journey_and_negative_guards(): void
    {
        Storage::fake('public');

        // 1. Setup Laguna (Origin) & Cavite (Destination) Logistics Companies
        $lagunaCompany = $this->createLogisticsUser('laguna.e2e@nexora.test', 'Laguna Express');
        $caviteCompany = $this->createLogisticsUser('cavite.e2e@nexora.test', 'Cavite Express');

        // Laguna Hubs: Santa Cruz (created first) and San Pablo (created second)
        $santaCruzHub = Hub::create([
            'logistics_company_id' => $lagunaCompany->id,
            'code' => 'HUB-LAG-SC',
            'name' => 'Laguna Central Hub - Santa Cruz',
            'province_code' => '043400000',
            'province_name' => 'Laguna',
            'city_code' => '043426000',
            'city_name' => 'Santa Cruz',
            'barangay_code' => '043426001',
            'barangay_name' => 'Poblacion I',
            'street_address' => 'National Hwy, Santa Cruz',
            'contact_number' => '09170001001',
            'is_active' => true,
        ]);

        $sanPabloHub = Hub::create([
            'logistics_company_id' => $lagunaCompany->id,
            'code' => 'HUB-LAG-SP',
            'name' => 'San Pablo Hub',
            'province_code' => '043400000',
            'province_name' => 'Laguna',
            'city_code' => '043424000',
            'city_name' => 'City of San Pablo',
            'barangay_code' => '043424001',
            'barangay_name' => 'Bagong Bayan II-A',
            'street_address' => 'Maharlika Hwy, San Pablo',
            'contact_number' => '09170001002',
            'is_active' => true,
        ]);

        $caviteHub = Hub::create([
            'logistics_company_id' => $caviteCompany->id,
            'code' => 'HUB-CAV-IMUS',
            'name' => 'Cavite Central Hub - Imus',
            'province_code' => '042100000',
            'province_name' => 'Cavite',
            'city_code' => '042109000',
            'city_name' => 'City of Imus',
            'barangay_code' => '042109001',
            'barangay_name' => 'Alapan I-A',
            'street_address' => 'Aguinaldo Hwy, Imus',
            'contact_number' => '09170002001',
            'is_active' => true,
        ]);

        $santaCruzZone = Zone::create([
            'logistics_company_id' => $lagunaCompany->id,
            'hub_id' => $santaCruzHub->id,
            'name' => 'Santa Cruz Zone',
            'code' => 'Z-SC',
            'province_code' => '043400000',
            'province_name' => 'Laguna',
            'city_code' => '043426000',
            'city_name' => 'Santa Cruz',
            'is_active' => true,
        ]);
        ZoneBarangay::create([
            'zone_id' => $santaCruzZone->id,
            'logistics_company_id' => $lagunaCompany->id,
            'province_code' => '043400000',
            'province_name' => 'Laguna',
            'city_code' => '043426000',
            'city_name' => 'Santa Cruz',
            'barangay_code' => '043426001',
            'barangay_name' => 'Poblacion I',
        ]);

        // San Pablo Hub has a Zone with city_code '043424000' (no ZoneBarangay rows, testing Issue 3 fix!)
        $sanPabloZone = Zone::create([
            'logistics_company_id' => $lagunaCompany->id,
            'hub_id' => $sanPabloHub->id,
            'name' => 'San Pablo Zone',
            'code' => 'Z-SP',
            'province_code' => '043400000',
            'province_name' => 'Laguna',
            'city_code' => '043424000',
            'city_name' => 'San Pablo',
            'is_active' => true,
        ]);

        $caviteZone = Zone::create([
            'logistics_company_id' => $caviteCompany->id,
            'hub_id' => $caviteHub->id,
            'name' => 'Imus Zone',
            'code' => 'Z-IMUS',
            'province_code' => '042100000',
            'province_name' => 'Cavite',
            'city_code' => '042109000',
            'city_name' => 'City of Imus',
            'is_active' => true,
        ]);
        ZoneBarangay::create([
            'zone_id' => $caviteZone->id,
            'logistics_company_id' => $caviteCompany->id,
            'province_code' => '042100000',
            'province_name' => 'Cavite',
            'city_code' => '042109000',
            'city_name' => 'City of Imus',
            'barangay_code' => '042109001',
            'barangay_name' => 'Alapan I-A',
        ]);

        // Compensation Policies: Laguna pickup = 30, Cavite delivery = 45
        CompensationPolicy::create([
            'logistics_company_id' => $lagunaCompany->id,
            'role' => CompensationPolicy::ROLE_RIDER,
            'name' => 'Laguna Rider Policy',
            'version' => 1,
            'status' => CompensationPolicy::STATUS_ACTIVE,
            'pickup_rate' => 30.00,
            'delivery_rate' => 45.00,
            'effective_from' => now()->subDay(),
        ]);
        CompensationPolicy::create([
            'logistics_company_id' => $caviteCompany->id,
            'role' => CompensationPolicy::ROLE_RIDER,
            'name' => 'Cavite Rider Policy',
            'version' => 1,
            'status' => CompensationPolicy::STATUS_ACTIVE,
            'pickup_rate' => 35.00,
            'delivery_rate' => 45.00,
            'effective_from' => now()->subDay(),
        ]);

        // Riders & Transfer Courier
        $lagunaPickupRider = $this->createRider('laguna.pickup@nexora.test', $lagunaCompany->id, $sanPabloHub->id, [$sanPabloZone->id]);
        $caviteDeliveryRider = $this->createRider('cavite.delivery@nexora.test', $caviteCompany->id, $caviteHub->id, [$caviteZone->id]);
        $transferCourier = $this->createTransferCourier('courier.e2e@nexora.test', $lagunaCompany->id, $sanPabloHub->id);

        // Seller in San Pablo, Laguna & Buyer in Imus, Cavite
        $seller = $this->createSeller('sanpablo.seller@nexora.test', 'San Pablo Crafts', [
            'province_code' => '043400000',
            'province_name' => 'Laguna',
            'municipality_code' => '043424000',
            'municipality_name' => 'City of San Pablo',
            'barangay_code' => '043424002',
            'barangay_name' => 'Bagong Pook VI-C',
        ]);

        $buyer = $this->createBuyer('cavite.buyer@nexora.test');
        $buyerAddress = Address::create([
            'user_id' => $buyer->id,
            'recipient_name' => 'Cavite Buyer',
            'recipient_phone' => '09171112233',
            'region_code' => '040000000',
            'region_name' => 'Region IV-A (CALABARZON)',
            'province_code' => '042100000',
            'province_name' => 'Cavite',
            'municipality_code' => '042109000',
            'municipality_name' => 'City of Imus',
            'barangay_code' => '042109001',
            'barangay_name' => 'Alapan I-A',
            'street' => 'Aguinaldo St',
            'house_number' => '12',
            'is_default' => true,
        ]);

        $category = Category::firstOrCreate(['slug' => 'electronics'], ['name' => 'Electronics']);
        $product = Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'name' => 'San Pablo Speaker',
            'slug' => 'san-pablo-speaker',
            'price' => 1200.00,
            'stock' => 10,
            'weight' => 1.0,
            'status' => 'active',
        ]);

        // 2. Buyer places order & Seller prepares parcel
        $order = Order::create([
            'order_number' => 'ORD-E2E-0001',
            'tracking_number' => 'NX-TRK-E2E0001',
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'address_id' => $buyerAddress->id,
            'status' => 'ready_for_pickup',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'subtotal' => 1200.00,
            'shipping_fee' => 85.00,
            'shipping_weight' => 1.0,
            'total' => 1285.00,
            'delivery_otp' => '123456',
            'placed_at' => now(),
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'price' => 1200.00,
            'subtotal' => 1200.00,
        ]);

        // ISSUE 3 CHECK: Origin Hub must be San Pablo Hub (NOT Santa Cruz Hub!)
        $order = app(InterRegionLogisticsService::class)->matchLogisticsAndRouteOrder($order);
        $this->assertSame($sanPabloHub->id, $order->origin_hub_id, 'San Pablo seller must route to San Pablo Hub');
        $this->assertSame($caviteHub->id, $order->destination_hub_id);
        $this->assertSame($lagunaCompany->id, $order->pickup_logistics_id);
        $this->assertSame($caviteCompany->id, $order->delivery_logistics_id);

        // 3. Pickup Offer & Acceptance -> Expected pay is 30.00 (NOT 50 or 85)
        $offer = app(JobDispatchService::class)->dispatchOrder($order, 'pickup');
        $this->assertNotNull($offer);
        $this->assertSame($lagunaPickupRider->id, $offer->courier_id);
        $this->assertEquals(30.00, (float) $offer->expected_pay);

        Sanctum::actingAs($lagunaPickupRider);
        $this->postJson("/api/mobile/v1/rider/offers/{$offer->id}/accept")->assertOk();

        // Negative: Wrong barcode cannot confirm pickup
        $this->postJson('/api/mobile/v1/rider/pickup', [
            'tracking_number' => 'NX-WRONG-999',
            'expected_order_id' => $order->id,
        ])->assertStatus(404);

        // 4. Rider scans parcel -> picked_up -> +30.00 credited once
        $pickupResp = $this->postJson('/api/mobile/v1/rider/pickup', [
            'tracking_number' => $order->tracking_number,
            'expected_order_id' => $order->id,
        ])->assertOk();
        $this->assertEquals(30.00, (float) $pickupResp->json('data.earned_pay'));

        // Negative: Duplicate scan is rejected and does not duplicate compensation
        $this->postJson('/api/mobile/v1/rider/pickup', [
            'tracking_number' => $order->tracking_number,
            'expected_order_id' => $order->id,
        ])->assertStatus(422);
        $this->assertSame(1, RiderEarning::where('courier_id', $lagunaPickupRider->id)->where('order_id', $order->id)->where('type', 'pickup')->count());

        // ISSUE 1 CHECK: Rider Home (/profile) & Earnings (/earnings) both report 30.00 today
        $profileResp = $this->getJson('/api/mobile/v1/rider/profile')->assertOk();
        $this->assertEquals(30.00, (float) $profileResp->json('data.today_earnings'));
        $earningsResp = $this->getJson('/api/mobile/v1/rider/earnings')->assertOk();
        $this->assertEquals(30.00, (float) $earningsResp->json('data.today_summary.total_pay'));
        $this->assertEquals(30.00, (float) $earningsResp->json('data.today_summary.pickup_pay'));

        // ISSUE 2 CHECK: Completed pickup appears immediately in Rider history
        $historyResp = $this->getJson('/api/mobile/v1/rider/deliveries/history')->assertOk();
        $historyItems = $historyResp->json('data');
        $this->assertCount(1, $historyItems);
        $this->assertSame('pickup', $historyItems[0]['task_type']);
        $this->assertSame('picked_up', $historyItems[0]['task_outcome']);
        $this->assertEquals(30.00, (float) $historyItems[0]['compensation_amount']);

        // 5. Laguna physically receives parcel at San Pablo Hub
        $this->actingAs($lagunaCompany)
            ->postJson('/logistics/incoming/scan', [
                'identifier' => $order->tracking_number,
                'hub_id' => $sanPabloHub->id,
            ])
            ->assertOk()
            ->assertJsonPath('outcome', 'received');

        $order->refresh();
        $this->assertSame('received_at_hub', $order->status);
        $this->assertSame($sanPabloHub->id, $order->current_hub_id);
        $this->assertSame($lagunaCompany->id, $order->current_custody_logistics_id);

        // ISSUE 4 CHECK: Cavite MUST NOT see the parcel as received while it is in Laguna!
        $this->actingAs($caviteCompany)
            ->get('/logistics/incoming?tab=received')
            ->assertOk()
            ->assertDontSee($order->tracking_number);

        $this->actingAs($caviteCompany)
            ->postJson('/logistics/incoming/lookup', [
                'identifier' => $order->tracking_number,
                'hub_id' => $caviteHub->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.already_received', false)
            ->assertJsonPath('outcome', 'not_eligible');

        // Negative: Cavite cannot mark parcel received while still in Laguna
        $this->actingAs($caviteCompany)
            ->postJson('/logistics/incoming/scan', [
                'identifier' => $order->tracking_number,
                'hub_id' => $caviteHub->id,
            ])
            ->assertStatus(422);

        // ISSUE 5 CHECK: Laguna cannot sort or assign final delivery rider for out-of-area Cavite parcel
        $this->actingAs($lagunaCompany)
            ->get('/logistics/assignments')
            ->assertOk()
            ->assertDontSee($order->tracking_number);

        $this->actingAs($lagunaCompany)
            ->post("/logistics/assignments/{$order->id}/assign", [
                'courier_id' => $lagunaPickupRider->id,
            ])
            ->assertSessionHasErrors('courier_id');

        // Negative: Cavite cannot assign delivery rider before physical receipt
        $this->actingAs($caviteCompany)
            ->post("/logistics/assignments/{$order->id}/assign", [
                'courier_id' => $caviteDeliveryRider->id,
            ])
            ->assertSessionHasErrors('courier_id');

        // 6. Laguna creates Transfer Manifest -> Transfer Courier scans & departs -> Cavite receives manifest
        $manifestService = app(TransferManifestService::class);
        $manifest = $manifestService->createManifest($lagunaCompany, [
            'origin_hub_id' => $sanPabloHub->id,
            'destination_hub_id' => $caviteHub->id,
            'assigned_courier_id' => $transferCourier->id,
            'order_ids' => [$order->id],
        ]);

        Sanctum::actingAs($transferCourier);
        $this->postJson("/api/mobile/v1/transfer-courier/manifests/{$manifest->id}/scan", [
            'tracking_number' => $order->tracking_number,
        ])->assertOk();
        $this->postJson("/api/mobile/v1/transfer-courier/manifests/{$manifest->id}/depart")->assertOk();

        // Negative: Start Delivery cannot bypass manifest receiving
        Sanctum::actingAs($caviteDeliveryRider);
        $this->postJson("/api/mobile/v1/rider/deliveries/{$order->id}/start")->assertStatus(403);

        // Transfer courier arrives at Cavite & Cavite physically receives and verifies parcel
        Sanctum::actingAs($transferCourier);
        $this->postJson("/api/mobile/v1/transfer-courier/manifests/{$manifest->id}/arrive")->assertOk();

        $this->actingAs($caviteCompany)
            ->postJson("/logistics/transfers/manifests/{$manifest->id}/verify-parcel", [
                'tracking_number' => $order->tracking_number,
                'classification' => 'received',
            ])
            ->assertOk();

        $order->refresh();
        $this->assertSame($caviteHub->id, $order->current_hub_id);
        $this->assertSame($caviteCompany->id, $order->current_custody_logistics_id);

        // 7. Cavite sorts (if needed) & assigns Cavite Delivery Rider
        if ($order->status !== 'sorted') {
            $this->actingAs($caviteCompany)
                ->post("/logistics/sorting/{$order->id}/confirm", ['zone_id' => $caviteZone->id])
                ->assertRedirect();
        }

        $this->actingAs($caviteCompany)
            ->post("/logistics/assignments/{$order->id}/assign", [
                'courier_id' => $caviteDeliveryRider->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame('assigned_to_rider', $order->status);
        $this->assertSame($caviteDeliveryRider->id, $order->delivery_courier_id);

        // 8. ISSUE 6 CHECK: Cavite Delivery Rider clicks START DELIVERY TO CUSTOMER
        Sanctum::actingAs($caviteDeliveryRider);
        $this->postJson("/api/mobile/v1/rider/deliveries/{$order->id}/start")
            ->assertOk()
            ->assertJsonPath('data.status', 'out_for_delivery');

        $order->refresh();
        $this->assertSame('out_for_delivery', $order->status);

        // Buyer API reflects out_for_delivery
        Sanctum::actingAs($buyer);
        $this->getJson("/api/mobile/v1/buyer/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'out_for_delivery');

        // 9. Delivery Rider completes delivery with OTP + POD -> +45.00 credited
        Sanctum::actingAs($caviteDeliveryRider);
        $podFile = UploadedFile::fake()->create('pod.jpg', 100, 'image/jpeg');
        $completeResp = $this->postJson("/api/mobile/v1/rider/deliveries/{$order->id}/complete", [
            'delivery_otp' => '123456',
            'pod_photo' => $podFile,
        ])->assertOk();

        $this->assertEquals(45.00, (float) $completeResp->json('data.earned_pay'));

        $caviteEarnings = $this->getJson('/api/mobile/v1/rider/earnings')->assertOk();
        $this->assertEquals(45.00, (float) $caviteEarnings->json('data.today_summary.total_pay'));
        $this->assertEquals(45.00, (float) $caviteEarnings->json('data.today_summary.delivery_pay'));

        // ISSUE 2 CHECK: Pickup Rider's completed pickup history STILL remains intact!
        Sanctum::actingAs($lagunaPickupRider);
        $pickupHistoryAfter = $this->getJson('/api/mobile/v1/rider/deliveries/history')->assertOk()->json('data');
        $this->assertCount(1, $pickupHistoryAfter);
        $this->assertSame('pickup', $pickupHistoryAfter[0]['task_type']);
        $this->assertSame('picked_up', $pickupHistoryAfter[0]['task_outcome']);
        $this->assertEquals(30.00, (float) $pickupHistoryAfter[0]['compensation_amount']);
    }

    private function createLogisticsUser(string $email, string $companyName): User
    {
        $user = User::create([
            'first_name' => $companyName,
            'last_name' => 'Logistics',
            'email' => $email,
            'contact_number' => '09170009999',
            'birthday' => '1990-01-01',
            'age' => 36,
            'sex' => 'other',
            'password' => Hash::make('Password123!@#'),
            'role' => 'logistics',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        LogisticsProfile::create([
            'user_id' => $user->id,
            'company_name' => $companyName,
            'sec_registration_no' => 'SEC-' . $user->id,
            'tin' => '123-456-789-' . $user->id,
            'region' => 'Region IV-A (CALABARZON)',
            'province' => 'Laguna',
            'city_municipality' => 'Santa Cruz',
            'barangay' => 'Poblacion I',
            'street_address' => 'Logistics Hwy',
            'postal_code' => '4009',
            'contact_person_first_name' => $companyName,
            'contact_person_last_name' => 'Ops',
            'position' => 'Manager',
            'email' => $email,
            'mobile_number' => '0917' . str_pad((string) $user->id, 7, '0', STR_PAD_LEFT),
            'status' => 'approved',
        ]);

        return $user;
    }

    private function createRider(string $email, int $companyId, int $hubId, array $zoneIds): User
    {
        $rider = User::create([
            'first_name' => 'Rider',
            'last_name' => explode('@', $email)[0],
            'email' => $email,
            'contact_number' => '09180001111',
            'birthday' => '1995-01-01',
            'age' => 31,
            'sex' => 'male',
            'password' => Hash::make('Password123!@#'),
            'role' => 'rider',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        CourierProfile::create([
            'user_id' => $rider->id,
            'sorting_center_id' => $companyId,
            'logistics_company_id' => $companyId,
            'assigned_hub_id' => $hubId,
            'vehicle_type' => 'Motorcycle',
            'plate_number' => 'MC-' . $rider->id,
            'license_number' => 'LIC-' . $rider->id,
            'license_expiry_date' => now()->addYear()->toDateString(),
            'is_online' => true,
        ]);

        $rider->assignedZones()->sync($zoneIds);

        return $rider;
    }

    private function createTransferCourier(string $email, int $companyId, int $hubId): User
    {
        $courier = User::create([
            'first_name' => 'Transfer',
            'last_name' => 'Courier',
            'email' => $email,
            'contact_number' => '09180002222',
            'birthday' => '1993-01-01',
            'age' => 33,
            'sex' => 'male',
            'password' => Hash::make('Password123!@#'),
            'role' => 'courier',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        CourierProfile::create([
            'user_id' => $courier->id,
            'sorting_center_id' => $companyId,
            'logistics_company_id' => $companyId,
            'assigned_hub_id' => $hubId,
            'vehicle_type' => 'Van',
            'plate_number' => 'TR-' . $courier->id,
            'license_number' => 'TRC-' . $courier->id,
            'license_expiry_date' => now()->addYear()->toDateString(),
            'is_online' => true,
        ]);

        return $courier;
    }

    private function createSeller(string $email, string $businessName, array $addressAttrs): User
    {
        $seller = User::create([
            'first_name' => $businessName,
            'last_name' => 'Seller',
            'email' => $email,
            'contact_number' => '09172223333',
            'birthday' => '1992-01-01',
            'age' => 34,
            'sex' => 'other',
            'password' => Hash::make('Password123!@#'),
            'role' => 'seller',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        SellerProfile::create([
            'user_id' => $seller->id,
            'business_name' => $businessName,
            'category' => 'electronics',
        ]);

        Address::create(array_merge([
            'user_id' => $seller->id,
            'recipient_name' => $businessName,
            'recipient_phone' => '09172223333',
            'region_code' => '040000000',
            'region_name' => 'Region IV-A (CALABARZON)',
            'street' => 'Commerce St',
            'house_number' => '10',
            'is_default' => true,
        ], $addressAttrs));

        return $seller;
    }

    private function createBuyer(string $email): User
    {
        return User::create([
            'first_name' => 'Cavite',
            'last_name' => 'Buyer',
            'email' => $email,
            'contact_number' => '09171112233',
            'birthday' => '1998-01-01',
            'age' => 28,
            'sex' => 'female',
            'password' => Hash::make('Password123!@#'),
            'role' => 'buyer',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
    }
}
