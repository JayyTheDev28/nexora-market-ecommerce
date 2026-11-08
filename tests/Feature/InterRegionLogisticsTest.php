<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Category;
use App\Models\CourierProfile;
use App\Models\Hub;
use App\Models\HubTransfer;
use App\Models\LogisticsProfile;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PsgcBarangay;
use App\Models\PsgcCity;
use App\Models\PsgcProvince;
use App\Models\Product;
use App\Models\User;
use App\Models\Zone;
use App\Models\ZoneBarangay;
use App\Services\InterRegionLogisticsService;
use Database\Seeders\InterRegionLogisticsSeeder;
use Database\Seeders\PsgcSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InterRegionLogisticsTest extends TestCase
{
    use DatabaseTransactions;

    protected InterRegionLogisticsService $service;

    protected User $lagunaCompany;
    protected User $caviteCompany;
    protected User $buyerCavite;
    protected User $sellerLaguna;
    protected User $lagunaRider1;
    protected User $lagunaRider2;
    protected User $caviteRider1;
    protected Hub $lagunaHub;
    protected Hub $caviteHub;
    protected Zone $lagunaZone;
    protected Zone $caviteZone;
    protected Product $testProduct;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(InterRegionLogisticsService::class);

        // Seed PSGC and inter-region baseline data if needed
        $this->seed(PsgcSeeder::class);
        $this->seed(InterRegionLogisticsSeeder::class);

        $this->lagunaCompany = User::where('email', 'logistics@nexora.test')->firstOrFail();
        $this->caviteCompany = User::where('email', 'cavite.logistics@nexora.test')->firstOrFail();

        $this->lagunaRider1 = User::where('email', 'laguna.rider1@nexora.test')->firstOrFail();
        $this->lagunaRider2 = User::where('email', 'laguna.rider2@nexora.test')->firstOrFail();
        $this->caviteRider1 = User::where('email', 'cavite.rider1@nexora.test')->firstOrFail();

        $this->lagunaHub = Hub::where('code', 'HUB-LAG-SC')->firstOrFail();
        $this->caviteHub = Hub::where('code', 'HUB-CAV-BAC')->firstOrFail();

        $this->lagunaZone = Zone::where('logistics_company_id', $this->lagunaCompany->id)
            ->where('city_name', 'Santa Cruz')
            ->firstOrFail();

        $this->caviteZone = Zone::where('logistics_company_id', $this->caviteCompany->id)
            ->where('city_name', 'City of Bacoor')
            ->firstOrFail();

        // Create Buyer with Bacoor, Cavite address
        $this->buyerCavite = User::create([
            'first_name' => 'Cavite',
            'last_name' => 'Buyer',
            'sex' => 'other',
            'birthday' => '1996-05-10',
            'age' => 30,
            'contact_number' => '09179998877',
            'email' => 'cavite_buyer_' . uniqid() . '@nexora.test',
            'password' => Hash::make('Password123!@#'),
            'role' => 'buyer',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        Address::create([
            'user_id' => $this->buyerCavite->id,
            'recipient_name' => 'Cavite Buyer',
            'recipient_phone' => '09179998877',
            'province_code' => '042100000',
            'province_name' => 'Cavite',
            'municipality_code' => '042103000',
            'municipality_name' => 'City of Bacoor',
            'barangay_code' => '042103001',
            'barangay_name' => 'Alima',
            'street' => '123 Coastal Road',
            'house_number' => 'Block 1',
            'is_default' => true,
            'needs_manual_review' => false,
        ]);

        // Create Seller with Santa Cruz, Laguna address
        $this->sellerLaguna = User::create([
            'first_name' => 'Laguna',
            'last_name' => 'Seller',
            'sex' => 'other',
            'birthday' => '1992-03-15',
            'age' => 34,
            'contact_number' => '09187776655',
            'email' => 'laguna_seller_' . uniqid() . '@nexora.test',
            'password' => Hash::make('Password123!@#'),
            'role' => 'seller',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        Address::create([
            'user_id' => $this->sellerLaguna->id,
            'recipient_name' => 'Laguna Seller Store',
            'recipient_phone' => '09187776655',
            'province_code' => '043400000',
            'province_name' => 'Laguna',
            'municipality_code' => '043426000',
            'municipality_name' => 'Santa Cruz',
            'barangay_code' => '043426001',
            'barangay_name' => 'Poblacion I',
            'street' => 'Main Commercial Strip',
            'house_number' => 'Store 101',
            'is_default' => true,
            'needs_manual_review' => false,
        ]);

        $category = Category::firstOrCreate(
            ['slug' => 'test-gadgets'],
            ['name' => 'Test Gadgets', 'is_active' => true]
        );

        $this->testProduct = Product::create([
            'seller_id' => $this->sellerLaguna->id,
            'category_id' => $category->id,
            'name' => 'Logistics Test Item',
            'slug' => 'logistics-test-item-' . uniqid(),
            'description' => 'A test parcel item',
            'price' => 1500.00,
            'stock' => 50,
            'weight' => 1.5,
            'status' => 'active',
            'approval_status' => 'approved',
        ]);
    }

    /**
     * Helper to create an order between seller and buyer.
     */
    protected function createOrder(User $seller, User $buyer): Order
    {
        $shippingAddress = $buyer->address;

        $order = Order::create([
            'order_number' => 'ORD-' . strtoupper(uniqid()),
            'tracking_number' => 'NX-' . strtoupper(uniqid()),
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'address_id' => $shippingAddress->id,
            'subtotal' => 1500.00,
            'total' => 1620.00,
            'status' => 'pending',
            'payment_status' => 'paid',
            'payment_method' => 'cod',
            'shipping_fee' => 120.00,
            'shipping_province' => $shippingAddress->province_name,
            'shipping_city' => $shippingAddress->municipality_name,
            'shipping_barangay' => $shippingAddress->barangay_name,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->testProduct->id,
            'product_name' => $this->testProduct->name,
            'price' => 1500.00,
            'quantity' => 1,
        ]);

        return $order;
    }

    /**
     * TEST: Exact barangay matching for rider dispatch.
     */
    public function test_exact_barangay_matching_for_rider_dispatch(): void
    {
        // Poblacion I (043426001) in Santa Cruz is covered by Santa Cruz Zone
        // which has $this->lagunaRider1 assigned.
        $rider = $this->service->findBestEligibleRider(
            logisticsCompanyId: $this->lagunaCompany->id,
            barangayCode: '043426001',
            cityCode: '043426000',
            provinceCode: '043400000'
        );

        $this->assertNotNull($rider);
        $this->assertEquals($this->lagunaRider1->id, $rider->id);
    }

    /**
     * TEST: City and province fallback order.
     */
    public function test_city_and_province_fallback_order(): void
    {
        // 1. Uncovered barangay in Santa Cruz falls back to city rider
        $cityRider = $this->service->findBestEligibleRider(
            logisticsCompanyId: $this->lagunaCompany->id,
            barangayCode: '043426999', // Uncovered barangay
            cityCode: '043426000',     // Santa Cruz city code
            provinceCode: '043400000'
        );

        $this->assertNotNull($cityRider);
        $this->assertEquals($this->lagunaCompany->id, $cityRider->courierProfile->logistics_company_id);

        // 2. City without zone coverage in Laguna falls back to province
        $provRider = $this->service->findBestEligibleRider(
            logisticsCompanyId: $this->lagunaCompany->id,
            barangayCode: '043404999',
            cityCode: '043404000',     // Cabuyao (no zone)
            provinceCode: '043400000'  // Laguna
        );

        $this->assertNotNull($provRider);
        $this->assertEquals($this->lagunaCompany->id, $provRider->courierProfile->logistics_company_id);

        // 3. Workload preference: Rider with fewer active jobs is preferred
        // Assign an active job to Rider 1:
        $order = $this->createOrder($this->sellerLaguna, $this->buyerCavite);
        $order->update([
            'delivery_courier_id' => $this->lagunaRider1->id,
            'delivery_rider_id' => $this->lagunaRider1->id,
            'status' => 'assigned_to_rider',
        ]);

        // Also assign Laguna Rider 2 to the Santa Cruz Zone
        $this->lagunaZone->riders()->syncWithoutDetaching([$this->lagunaRider2->id]);

        $tieBreakRider = $this->service->findBestEligibleRider(
            logisticsCompanyId: $this->lagunaCompany->id,
            barangayCode: '043426001',
            cityCode: '043426000',
            provinceCode: '043400000'
        );

        // Rider 2 has 0 jobs, Rider 1 has 1 job -> Rider 2 is selected!
        $this->assertNotNull($tieBreakRider);
        $this->assertEquals($this->lagunaRider2->id, $tieBreakRider->id);
    }

    /**
     * TEST: Parcel crossing from one region to another (Laguna to Cavite).
     */
    public function test_cross_region_parcel_flow_with_hub_transfer(): void
    {
        $order = $this->createOrder($this->sellerLaguna, $this->buyerCavite);

        // 1. Initial Routing
        $routedOrder = $this->service->matchLogisticsAndRouteOrder($order);
        $this->assertEquals($this->lagunaCompany->id, $routedOrder->pickup_logistics_id);
        $this->assertEquals($this->caviteCompany->id, $routedOrder->delivery_logistics_id);
        $this->assertEquals($this->lagunaCompany->id, $routedOrder->current_custody_logistics_id);
        $this->assertNotEquals($routedOrder->pickup_logistics_id, $routedOrder->delivery_logistics_id);

        // 2. Laguna Rider takes parcel to Laguna Hub
        $routedOrder->update([
            'pickup_rider_id' => $this->lagunaRider1->id,
            'pickup_courier_id' => $this->lagunaRider1->id,
            'status' => 'picked_up',
        ]);

        // 3. Laguna confirms receipt at Laguna Hub
        $atHubOrder = $this->service->confirmReceiptAtHub($routedOrder, $this->lagunaHub, $this->lagunaCompany);
        $this->assertEquals('received_at_hub', $atHubOrder->status);
        $this->assertEquals($this->lagunaCompany->id, $atHubOrder->current_custody_logistics_id);

        // 4. Laguna dispatches parcel to Cavite Hub
        $transfer = $this->service->dispatchHubTransfer(
            order: $atHubOrder,
            senderHub: $this->lagunaHub,
            receiverHub: $this->caviteHub,
            actor: $this->lagunaCompany,
            notes: 'Dispatching via South Luzon inter-hub feeder van'
        );

        $atHubOrder->refresh();
        $this->assertEquals('in_transit_hub', $atHubOrder->status);
        // Custody remains with Laguna while in transit
        $this->assertEquals($this->lagunaCompany->id, $atHubOrder->current_custody_logistics_id);
        $this->assertEquals('in_transit', $transfer->status);

        // 5. Cavite Company confirms receipt at Cavite Hub
        $receivedOrder = $this->service->confirmHubTransferReceipt(
            transfer: $transfer,
            actor: $this->caviteCompany,
            notes: 'Received and verified at Bacoor Hub'
        );

        $transfer->refresh();
        $this->assertEquals('received', $transfer->status);
        $this->assertEquals('received_at_hub', $receivedOrder->status);
        // Custody is officially transferred to Cavite!
        $this->assertEquals($this->caviteCompany->id, $receivedOrder->current_custody_logistics_id);

        // 6. Cavite assigns delivery rider from buyer's zone
        $deliveryRider = $this->service->findBestEligibleRider(
            logisticsCompanyId: $this->caviteCompany->id,
            barangayCode: '042103001',
            cityCode: '042103000',
            provinceCode: '042100000'
        );

        $this->assertNotNull($deliveryRider);
        $this->assertEquals($this->caviteCompany->id, $deliveryRider->courierProfile->logistics_company_id);
        $this->assertEquals($this->caviteRider1->id, $deliveryRider->id);
    }

    /**
     * TEST: Same-company delivery with no transfer.
     */
    public function test_same_company_delivery_with_no_transfer(): void
    {
        // Buyer in Santa Cruz (same hub as seller - no transfer needed)
        $buyerLaguna = User::create([
            'first_name' => 'Santa Cruz',
            'last_name' => 'Buyer',
            'sex' => 'other',
            'birthday' => '1994-08-20',
            'age' => 32,
            'contact_number' => '09171112233',
            'email' => 'sc_buyer_' . uniqid() . '@nexora.test',
            'password' => Hash::make('Password123!@#'),
            'role' => 'buyer',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        Address::create([
            'user_id' => $buyerLaguna->id,
            'recipient_name' => 'Santa Cruz Buyer',
            'recipient_phone' => '09171112233',
            'province_code' => '043400000',
            'province_name' => 'Laguna',
            'municipality_code' => '043426000',
            'municipality_name' => 'Santa Cruz',
            'barangay_code' => '043426001',
            'barangay_name' => 'Poblacion I',
            'street' => 'Crossing Santa Cruz',
            'house_number' => '45',
            'is_default' => true,
            'needs_manual_review' => false,
        ]);

        $order = $this->createOrder($this->sellerLaguna, $buyerLaguna);
        $routedOrder = $this->service->matchLogisticsAndRouteOrder($order);

        // Both pickup and delivery belong to Laguna Express and share the same hub
        $this->assertEquals($this->lagunaCompany->id, $routedOrder->pickup_logistics_id);
        $this->assertEquals($this->lagunaCompany->id, $routedOrder->delivery_logistics_id);
        $this->assertEquals($routedOrder->origin_hub_id, $routedOrder->destination_hub_id);

        $routedOrder->update(['status' => 'picked_up']);

        // When confirmed at same hub, skips unnecessary transportation and goes straight to sorted
        $confirmedOrder = $this->service->confirmReceiptAtHub($routedOrder, $this->lagunaHub, $this->lagunaCompany);
        $this->assertEquals('sorted', $confirmedOrder->status);
        $this->assertEquals(0, HubTransfer::where('order_id', $order->id)->count());
    }

    /**
     * TEST: Internal hub transfer within the same company between different hubs.
     */
    public function test_internal_hub_transfer_within_same_company(): void
    {
        // Buyer in Calamba (covered by Laguna West Hub - Calamba, different hub than Santa Cruz)
        $buyerCalamba = User::create([
            'first_name' => 'Calamba',
            'last_name' => 'Buyer',
            'sex' => 'other',
            'birthday' => '1993-01-15',
            'age' => 33,
            'contact_number' => '09172223344',
            'email' => 'calamba_int_' . uniqid() . '@nexora.test',
            'password' => Hash::make('Password123!@#'),
            'role' => 'buyer',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        Address::create([
            'user_id' => $buyerCalamba->id,
            'recipient_name' => 'Calamba Internal Buyer',
            'recipient_phone' => '09172223344',
            'province_code' => '043400000',
            'province_name' => 'Laguna',
            'municipality_code' => '043404000',
            'municipality_name' => 'City of Calamba',
            'barangay_code' => '043404001',
            'barangay_name' => 'Bagong Kalsada',
            'street' => 'Turbina Highway',
            'house_number' => '88',
            'is_default' => true,
            'needs_manual_review' => false,
        ]);

        $order = $this->createOrder($this->sellerLaguna, $buyerCalamba);
        $routedOrder = $this->service->matchLogisticsAndRouteOrder($order);

        // Same company, but different hubs: Santa Cruz (origin) -> Calamba (destination)
        $this->assertEquals($this->lagunaCompany->id, $routedOrder->pickup_logistics_id);
        $this->assertEquals($this->lagunaCompany->id, $routedOrder->delivery_logistics_id);
        $this->assertNotEquals($routedOrder->origin_hub_id, $routedOrder->destination_hub_id);

        $calambaHub = Hub::where('code', 'HUB-LAG-CAL')->firstOrFail();
        $this->assertEquals($calambaHub->id, $routedOrder->destination_hub_id);

        // 1. Received at Origin Hub (Santa Cruz)
        $atOriginHub = $this->service->confirmReceiptAtHub($routedOrder, $this->lagunaHub, $this->lagunaCompany);
        $this->assertEquals('received_at_hub', $atOriginHub->status);
        $this->assertEquals($this->lagunaHub->id, $atOriginHub->current_hub_id);

        // 2. Dispatched via internal hub transfer
        $transfer = $this->service->dispatchHubTransfer(
            order: $atOriginHub,
            senderHub: $this->lagunaHub,
            receiverHub: $calambaHub,
            actor: $this->lagunaCompany,
            notes: 'Internal transfer from Santa Cruz to Calamba'
        );

        $this->assertTrue($transfer->is_internal);
        $this->assertEquals('internal', $transfer->transfer_type);
        $this->assertEquals('in_transit', $transfer->status);

        $atOriginHub->refresh();
        $this->assertEquals('in_transit_hub', $atOriginHub->status);
        $this->assertNull($atOriginHub->current_hub_id);
        // Custody remains with Laguna company
        $this->assertEquals($this->lagunaCompany->id, $atOriginHub->current_custody_logistics_id);

        // 3. Calamba Hub confirms receipt of internal transfer
        $atDestHub = $this->service->confirmHubTransferReceipt($transfer, $this->lagunaCompany);
        $this->assertEquals('received_at_hub', $atDestHub->status);
        $this->assertEquals($calambaHub->id, $atDestHub->current_hub_id);
        $this->assertEquals($this->lagunaCompany->id, $atDestHub->current_custody_logistics_id);
        $this->assertEquals('received', $transfer->fresh()->status);
    }

    /**
     * TEST: Custody changes only on receipt at the destination hub.
     */
    public function test_custody_changes_only_on_receipt(): void
    {
        $order = $this->createOrder($this->sellerLaguna, $this->buyerCavite);
        $this->service->matchLogisticsAndRouteOrder($order);

        $order->update([
            'status' => 'received_at_hub',
            'current_custody_logistics_id' => $this->lagunaCompany->id,
        ]);

        $transfer = $this->service->dispatchHubTransfer(
            order: $order,
            senderHub: $this->lagunaHub,
            receiverHub: $this->caviteHub,
            actor: $this->lagunaCompany
        );

        $order->refresh();
        // In transit: custody is Laguna
        $this->assertEquals('in_transit_hub', $order->status);
        $this->assertEquals($this->lagunaCompany->id, $order->current_custody_logistics_id);

        // Only after confirmHubTransferReceipt, custody changes to Cavite
        $this->service->confirmHubTransferReceipt($transfer, $this->caviteCompany);

        $order->refresh();
        $this->assertEquals($this->caviteCompany->id, $order->current_custody_logistics_id);
        $this->assertNotNull($transfer->fresh()->received_at);
    }

    /**
     * TEST: A rider is never offered a job outside their company or zone hierarchy.
     */
    public function test_rider_never_offered_job_outside_company_or_zone(): void
    {
        // 1. Cavite rider is NEVER matched for a Laguna company job
        $lagunaRider = $this->service->findBestEligibleRider(
            logisticsCompanyId: $this->lagunaCompany->id,
            barangayCode: '043426001',
            cityCode: '043426000',
            provinceCode: '043400000'
        );

        $this->assertNotNull($lagunaRider);
        $this->assertNotEquals($this->caviteRider1->id, $lagunaRider->id);
        $this->assertEquals($this->lagunaCompany->id, $lagunaRider->courierProfile->logistics_company_id);

        // 2. Laguna rider is NEVER matched for a Cavite company job
        $caviteRider = $this->service->findBestEligibleRider(
            logisticsCompanyId: $this->caviteCompany->id,
            barangayCode: '042103001',
            cityCode: '042103000',
            provinceCode: '042100000'
        );

        $this->assertNotNull($caviteRider);
        $this->assertNotEquals($this->lagunaRider1->id, $caviteRider->id);
        $this->assertNotEquals($this->lagunaRider2->id, $caviteRider->id);
        $this->assertEquals($this->caviteCompany->id, $caviteRider->courierProfile->logistics_company_id);
    }

    /**
     * TEST: Coverage must live in one place only per logistics company.
     */
    public function test_single_coverage_constraint_per_company(): void
    {
        // Try creating a second zone in Laguna Company that overlaps with Poblacion I (already in Santa Cruz Zone)
        $this->expectException(ValidationException::class);

        $controller = app(\App\Http\Controllers\Logistics\ZoneController::class);
        $request = \Illuminate\Http\Request::create('/logistics/zones', 'POST', [
            'name' => 'Overlapping Zone',
            'province_code' => '043400000',
            'province_name' => 'Laguna',
            'city_code' => '043426000',
            'city_name' => 'Santa Cruz',
            'barangay_codes' => ['043426001'], // Already covered by Santa Cruz Zone!
        ]);
        $request->setUserResolver(fn () => $this->lagunaCompany);

        $controller->store($request);
    }
}
