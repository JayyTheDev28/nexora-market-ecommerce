<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Category;
use App\Models\CourierProfile;
use App\Models\Hub;
use App\Models\JobOffer;
use App\Models\LogisticsProfile;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\SortingCenterProfile;
use App\Models\User;
use App\Models\Zone;
use App\Models\ZoneBarangay;
use App\Services\InterRegionLogisticsService;
use App\Services\ShippingPricingService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductRiderZoneAndHubCoverageFixTest extends TestCase
{
    use DatabaseTransactions;

    private User $company;
    private User $otherCompany;
    private Hub $santaCruzHub;
    private Hub $calambaHub;
    private Zone $santaCruzZone;
    private Zone $calambaZone;
    private User $rider;
    private User $transferCourier;
    private User $buyer;
    private User $seller;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        $this->company = $this->createLogisticsCompany('Laguna', '043400000', '043426000', 'Santa Cruz');
        $this->otherCompany = $this->createLogisticsCompany('Cavite', '042100000', '042106000', 'Dasmariñas');

        $this->santaCruzHub = Hub::create([
            'logistics_company_id' => $this->company->id,
            'name' => 'Laguna Santa Cruz Hub',
            'code' => 'NX-FIX-SC-' . uniqid(),
            'province_code' => '043400000',
            'province_name' => 'Laguna',
            'city_code' => '043426000',
            'city_name' => 'Santa Cruz',
            'barangay_code' => '043426001',
            'barangay_name' => 'Poblacion I',
            'is_active' => true,
        ]);

        $this->calambaHub = Hub::create([
            'logistics_company_id' => $this->company->id,
            'name' => 'Laguna Calamba Hub',
            'code' => 'NX-FIX-CL-' . uniqid(),
            'province_code' => '043400000',
            'province_name' => 'Laguna',
            'city_code' => '043404000',
            'city_name' => 'City of Calamba',
            'barangay_code' => '043404001',
            'barangay_name' => 'Bagong Kalsada',
            'is_active' => true,
        ]);

        $this->santaCruzZone = Zone::create([
            'logistics_company_id' => $this->company->id,
            'hub_id' => $this->santaCruzHub->id,
            'name' => 'Santa Cruz Central Zone',
            'code' => 'NX-Z-SC-' . uniqid(),
            'province_code' => '043400000',
            'province_name' => 'Laguna',
            'city_code' => '043426000',
            'city_name' => 'Santa Cruz',
            'is_active' => true,
        ]);

        ZoneBarangay::create([
            'zone_id' => $this->santaCruzZone->id,
            'logistics_company_id' => $this->company->id,
            'province_code' => '043400000',
            'province_name' => 'Laguna',
            'city_code' => '043426000',
            'city_name' => 'Santa Cruz',
            'barangay_code' => '043426001',
            'barangay_name' => 'Poblacion I',
        ]);

        $this->calambaZone = Zone::create([
            'logistics_company_id' => $this->company->id,
            'hub_id' => $this->calambaHub->id,
            'name' => 'City of Calamba Central Zone',
            'code' => 'NX-Z-CL-' . uniqid(),
            'province_code' => '043400000',
            'province_name' => 'Laguna',
            'city_code' => '043404000',
            'city_name' => 'City of Calamba',
            'is_active' => true,
        ]);

        ZoneBarangay::create([
            'zone_id' => $this->calambaZone->id,
            'logistics_company_id' => $this->company->id,
            'province_code' => '043400000',
            'province_name' => 'Laguna',
            'city_code' => '043404000',
            'city_name' => 'City of Calamba',
            'barangay_code' => '043404001',
            'barangay_name' => 'Bagong Kalsada',
        ]);

        $this->rider = User::create([
            'first_name' => 'SingleZone',
            'last_name' => 'Rider',
            'sex' => 'male',
            'birthday' => '1995-01-01',
            'age' => 31,
            'contact_number' => '0917' . rand(1000000, 9999999),
            'email' => 'singlezone.rider.' . uniqid() . '@nexora.test',
            'password' => Hash::make('Password123!@#'),
            'role' => 'rider',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        CourierProfile::create([
            'user_id' => $this->rider->id,
            'sorting_center_id' => $this->company->id,
            'logistics_company_id' => $this->company->id,
            'assigned_hub_id' => $this->santaCruzHub->id,
            'vehicle_type' => 'Motorcycle',
            'plate_number' => 'NX-' . rand(1000, 9999),
            'license_number' => 'LIC-' . uniqid(),
            'is_online' => true,
        ]);

        $this->rider->assignedZones()->sync([$this->santaCruzZone->id]);

        $this->transferCourier = User::create([
            'first_name' => 'InterHub',
            'last_name' => 'Courier',
            'sex' => 'male',
            'birthday' => '1994-01-01',
            'age' => 32,
            'contact_number' => '0917' . rand(1000000, 9999999),
            'email' => 'transfer.courier.' . uniqid() . '@nexora.test',
            'password' => Hash::make('Password123!@#'),
            'role' => 'courier',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        CourierProfile::create([
            'user_id' => $this->transferCourier->id,
            'sorting_center_id' => $this->company->id,
            'logistics_company_id' => $this->company->id,
            'assigned_hub_id' => $this->santaCruzHub->id,
            'vehicle_type' => 'Van',
            'plate_number' => 'TR-' . rand(1000, 9999),
            'license_number' => 'TRLIC-' . uniqid(),
            'is_online' => true,
        ]);

        $category = Category::first() ?? Category::create([
            'name' => 'Electronics',
            'slug' => 'electronics-' . uniqid(),
        ]);

        $this->seller = User::create([
            'first_name' => 'Seller',
            'last_name' => 'SantaCruz',
            'sex' => 'female',
            'birthday' => '1993-02-02',
            'age' => 33,
            'contact_number' => '0918' . rand(1000000, 9999999),
            'email' => 'seller.sc.' . uniqid() . '@nexora.test',
            'password' => Hash::make('Password123!@#'),
            'role' => 'seller',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
        SellerProfile::create([
            'user_id' => $this->seller->id,
            'business_name' => 'Santa Cruz Store',
            'category' => $category->slug,
        ]);
        Address::create([
            'user_id' => $this->seller->id,
            'region_code' => '040000000',
            'region_name' => 'Region IV-A (CALABARZON)',
            'province_code' => '043400000',
            'province_name' => 'Laguna',
            'municipality_code' => '043426000',
            'municipality_name' => 'Santa Cruz',
            'barangay_code' => '043426001',
            'barangay_name' => 'Poblacion I',
            'street' => 'Seller Rd',
            'is_default' => true,
        ]);
        $this->product = Product::create([
            'seller_id' => $this->seller->id,
            'category_id' => $category->id,
            'sku' => 'FIX-SKU-' . uniqid(),
            'name' => 'Fixture Product',
            'slug' => 'fix-prod-' . uniqid(),
            'description' => 'Fixture item',
            'price' => 300,
            'stock' => 10,
            'weight' => 0.5,
            'dimensions' => '10x10x10 cm',
            'status' => 'active',
        ]);

        $this->buyer = User::create([
            'first_name' => 'Buyer',
            'last_name' => 'SantaCruz',
            'sex' => 'male',
            'birthday' => '1996-03-03',
            'age' => 30,
            'contact_number' => '0919' . rand(1000000, 9999999),
            'email' => 'buyer.sc.' . uniqid() . '@nexora.test',
            'password' => Hash::make('Password123!@#'),
            'role' => 'buyer',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    public function test_rider_single_zone_is_enforced_in_ui_backend_and_database(): void
    {
        // Show page uses single select dropdown & shows company/hub/zone/barangays
        $html = $this->actingAs($this->company)
            ->get(route('logistics.riders.show', $this->rider))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Select Single Delivery Zone', $html);
        $this->assertStringContainsString('name="zone_id"', $html);
        $this->assertStringNotContainsString('name="zone_ids[]"', $html);
        $this->assertStringContainsString('Laguna Santa Cruz Hub', $html);
        $this->assertStringContainsString('Santa Cruz Central Zone', $html);
        $this->assertStringContainsString('Poblacion I', $html);

        // Multi-zone payload rejected cleanly
        $this->actingAs($this->company)
            ->post(route('logistics.riders.assign-zones', $this->rider), [
                'zone_ids' => [$this->santaCruzZone->id, $this->calambaZone->id],
            ])
            ->assertSessionHasErrors('zone_id');
        $this->assertSame(1, $this->rider->assignedZones()->count());

        // Zone belonging to a different hub for the same rider is rejected
        $this->actingAs($this->company)
            ->post(route('logistics.riders.assign-zones', $this->rider), [
                'zone_id' => $this->calambaZone->id,
            ])
            ->assertSessionHasErrors('zone_id');

        // Transfer courier cannot be assigned a delivery zone
        $this->actingAs($this->company)
            ->post(route('logistics.riders.assign-zones', $this->transferCourier), [
                'zone_id' => $this->santaCruzZone->id,
            ])
            ->assertStatus(422);

        // Changing rider's hub and replacing zone atomically replaces previous assignment and preserves active parcels
        $buyerAddr = Address::create([
            'user_id' => $this->buyer->id,
            'province_code' => '043400000',
            'province_name' => 'Laguna',
            'municipality_code' => '043426000',
            'municipality_name' => 'Santa Cruz',
            'barangay_code' => '043426001',
            'barangay_name' => 'Poblacion I',
            'street' => 'Buyer St',
            'is_default' => true,
        ]);
        $activeOrder = Order::create([
            'order_number' => 'NX-ACTIVE-' . uniqid(),
            'tracking_number' => 'NX-TRK-ACT-' . uniqid(),
            'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'address_id' => $buyerAddr->id,
            'status' => 'assigned_to_rider',
            'payment_method' => 'cod',
            'subtotal' => 300,
            'discount' => 0,
            'shipping_fee' => 50,
            'total' => 350,
            'delivery_zone_id' => $this->santaCruzZone->id,
            'delivery_courier_id' => $this->rider->id,
            'delivery_rider_id' => $this->rider->id,
        ]);

        $this->rider->courierProfile->update(['assigned_hub_id' => $this->calambaHub->id]);
        $this->actingAs($this->company)
            ->post(route('logistics.riders.assign-zones', $this->rider), [
                'zone_id' => $this->calambaZone->id,
            ])
            ->assertRedirect();

        $this->assertSame([$this->calambaZone->id], $this->rider->assignedZones()->pluck('zones.id')->all());
        $this->assertSame((int) $this->rider->id, (int) $activeOrder->fresh()->delivery_courier_id);

        // Database-level unique constraint blocks inserting a second zone directly
        $this->expectException(QueryException::class);
        DB::table('rider_zones')->insert([
            'rider_id' => $this->rider->id,
            'zone_id' => $this->santaCruzZone->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_zone_creation_blocks_cross_city_hub_mismatch_and_province_fallback(): void
    {
        // Attaching a Calamba zone to Santa Cruz Hub must be rejected
        $this->actingAs($this->company)
            ->post(route('logistics.zones.store'), [
                'name' => 'Cross-City Bad Zone',
                'hub_id' => $this->santaCruzHub->id,
                'province_code' => '043400000',
                'province_name' => 'Laguna',
                'city_code' => '043404000',
                'city_name' => 'City of Calamba',
                'barangay_codes' => ['043404099'],
            ])
            ->assertSessionHasErrors('hub_id');

        // Uncovered municipality in Laguna (e.g. Pagsanjan 043419000) must NOT resolve to Santa Cruz Hub via province fallback
        $router = app(InterRegionLogisticsService::class);
        $this->assertNull($router->resolveCoverage('043419001', '043419000', '043400000', 'Anibong', 'Pagsanjan', 'Laguna'));

        $uncoveredAddr = Address::create([
            'user_id' => $this->buyer->id,
            'province_code' => '043400000',
            'province_name' => 'Laguna',
            'municipality_code' => '043419000',
            'municipality_name' => 'Pagsanjan',
            'barangay_code' => '043419001',
            'barangay_name' => 'Anibong',
            'street' => 'Pagsanjan St',
            'is_default' => true,
        ]);
        $quoteItems = collect([(object) ['product' => $this->product, 'quantity' => 1]]);
        $quote = app(ShippingPricingService::class)->calculateSmartQuote($quoteItems, $this->seller->id, $uncoveredAddr);
        $this->assertFalse((bool) ($quote['serviceable'] ?? true));
        $this->assertNotEmpty($quote['serviceability_error'] ?? null);
    }

    public function test_mobile_rider_api_exposes_single_zone_and_filters_offers_to_authorized_zone(): void
    {
        $addrSc = Address::create([
            'user_id' => $this->buyer->id,
            'province_code' => '043400000',
            'province_name' => 'Laguna',
            'municipality_code' => '043426000',
            'municipality_name' => 'Santa Cruz',
            'barangay_code' => '043426001',
            'barangay_name' => 'Poblacion I',
            'street' => 'SC St',
            'is_default' => true,
        ]);
        $addrCl = Address::create([
            'user_id' => $this->buyer->id,
            'province_code' => '043400000',
            'province_name' => 'Laguna',
            'municipality_code' => '043404000',
            'municipality_name' => 'City of Calamba',
            'barangay_code' => '043404001',
            'barangay_name' => 'Bagong Kalsada',
            'street' => 'Calamba St',
            'is_default' => false,
        ]);

        $orderSc = Order::create([
            'order_number' => 'NX-OFFER-SC-' . uniqid(),
            'tracking_number' => 'NX-TRK-OSC-' . uniqid(),
            'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'address_id' => $addrSc->id,
            'status' => 'sorted',
            'payment_method' => 'cod',
            'subtotal' => 300,
            'discount' => 0,
            'shipping_fee' => 50,
            'total' => 350,
            'delivery_zone_id' => $this->santaCruzZone->id,
        ]);
        $orderCl = Order::create([
            'order_number' => 'NX-OFFER-CL-' . uniqid(),
            'tracking_number' => 'NX-TRK-OCL-' . uniqid(),
            'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'address_id' => $addrCl->id,
            'status' => 'sorted',
            'payment_method' => 'cod',
            'subtotal' => 300,
            'discount' => 0,
            'shipping_fee' => 50,
            'total' => 350,
            'delivery_zone_id' => $this->calambaZone->id,
        ]);

        JobOffer::create([
            'order_id' => $orderSc->id,
            'courier_id' => $this->rider->id,
            'type' => 'delivery',
            'status' => 'pending',
            'pickup_location' => 'SC Hub',
            'dropoff_location' => 'SC Addr',
            'cod_amount' => 350,
            'expected_pay' => 35,
            'expires_at' => now()->addMinute(),
        ]);
        JobOffer::create([
            'order_id' => $orderCl->id,
            'courier_id' => $this->rider->id,
            'type' => 'delivery',
            'status' => 'pending',
            'pickup_location' => 'Calamba Hub',
            'dropoff_location' => 'Calamba Addr',
            'cod_amount' => 350,
            'expected_pay' => 35,
            'expires_at' => now()->addMinute(),
        ]);

        Sanctum::actingAs($this->rider);
        $this->getJson('/api/mobile/v1/rider/profile')
            ->assertOk()
            ->assertJsonPath('data.courier_profile.assigned_zone.id', $this->santaCruzZone->id)
            ->assertJsonPath('data.courier_profile.assigned_zone.name', 'Santa Cruz Central Zone');

        $offers = $this->getJson('/api/mobile/v1/rider/offers')
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $offers);
        $this->assertSame($orderSc->order_number, $offers[0]['order_number']);
    }

    private function createLogisticsCompany(string $provName, string $provCode, string $cityCode, string $cityName): User
    {
        $u = User::create([
            'first_name' => $provName,
            'last_name' => 'Logistics',
            'sex' => 'other',
            'birthday' => '1990-01-01',
            'age' => 36,
            'contact_number' => '0917' . rand(1000000, 9999999),
            'email' => strtolower($provName) . '.log.' . uniqid() . '@nexora.test',
            'password' => Hash::make('Password123!@#'),
            'role' => 'logistics',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
        LogisticsProfile::create([
            'user_id' => $u->id,
            'company_name' => "Nexora {$provName} Fix Test",
            'sec_registration_no' => 'SEC-' . uniqid(),
            'tin' => rand(100, 999) . '-' . rand(100, 999) . '-' . rand(100, 999) . '-000',
            'region' => 'Region IV-A (CALABARZON)',
            'province' => $provName,
            'city_municipality' => $cityName,
            'barangay' => 'Poblacion',
            'street_address' => 'Test St',
            'postal_code' => '4009',
            'contact_person_first_name' => $provName,
            'contact_person_last_name' => 'Ops',
            'position' => 'Manager',
            'email' => $u->email,
            'mobile_number' => $u->contact_number,
            'service_areas' => [$provCode],
            'status' => 'approved',
        ]);
        SortingCenterProfile::create([
            'user_id' => $u->id,
            'business_name' => "Nexora {$provName} Fix Test",
        ]);
        Address::create([
            'user_id' => $u->id,
            'province_code' => $provCode,
            'province_name' => $provName,
            'municipality_code' => $cityCode,
            'municipality_name' => $cityName,
            'barangay_code' => substr($cityCode, 0, 6) . '001',
            'barangay_name' => 'Poblacion I',
            'street' => 'Main Rd',
            'is_default' => true,
        ]);

        return $u;
    }
}
