<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Hub;
use App\Models\LogisticsProfile;
use App\Models\PsgcBarangay;
use App\Models\PsgcCity;
use App\Models\User;
use App\Models\Zone;
use App\Models\ZoneBarangay;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ZoneFixedProvinceAndCoverageTest extends TestCase
{
    use DatabaseTransactions;

    private User $lagunaLogistics;
    private User $caviteLogistics;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PsgcSeeder::class);

        // Laguna Logistics
        $this->lagunaLogistics = User::create([
            'first_name' => 'Laguna',
            'last_name' => 'Logistics Admin',
            'sex' => 'other',
            'birthday' => '1990-01-01',
            'age' => 35,
            'contact_number' => '09170000001',
            'email' => 'laguna_test_' . uniqid() . '@nexora.test',
            'password' => Hash::make('Password123!@#'),
            'role' => 'logistics',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        Address::create([
            'user_id' => $this->lagunaLogistics->id,
            'recipient_name' => 'Laguna Hub',
            'recipient_phone' => '09171112233',
            'region_code' => '040000000',
            'region_name' => 'Region IV-A (CALABARZON)',
            'province_code' => '043400000',
            'province_name' => 'Laguna',
            'municipality_code' => '043426000',
            'municipality_name' => 'Santa Cruz',
            'barangay_code' => '043426001',
            'barangay_name' => 'Poblacion I',
            'street' => 'Provincial Road',
            'is_default' => true,
        ]);

        LogisticsProfile::create([
            'user_id' => $this->lagunaLogistics->id,
            'company_name' => 'Laguna Express Corp',
            'province' => 'Laguna',
            'city_municipality' => 'Santa Cruz',
            'status' => 'approved',
        ]);

        Hub::create([
            'logistics_company_id' => $this->lagunaLogistics->id,
            'name' => 'Santa Cruz Hub',
            'code' => 'HUB-TEST-SC-' . uniqid(),
            'province_code' => '043400000',
            'province_name' => 'Laguna',
            'city_code' => '043426000',
            'city_name' => 'Santa Cruz',
            'barangay_code' => '043426001',
            'barangay_name' => 'Poblacion I',
            'is_active' => true,
        ]);

        // Cavite Logistics
        $this->caviteLogistics = User::create([
            'first_name' => 'Cavite',
            'last_name' => 'Logistics Admin',
            'sex' => 'other',
            'birthday' => '1990-01-01',
            'age' => 35,
            'contact_number' => '09170000002',
            'email' => 'cavite_test_' . uniqid() . '@nexora.test',
            'password' => Hash::make('Password123!@#'),
            'role' => 'logistics',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        Address::create([
            'user_id' => $this->caviteLogistics->id,
            'recipient_name' => 'Cavite Hub',
            'recipient_phone' => '09179998877',
            'region_code' => '040000000',
            'region_name' => 'Region IV-A (CALABARZON)',
            'province_code' => '042100000',
            'province_name' => 'Cavite',
            'municipality_code' => '042106000',
            'municipality_name' => 'City of Dasmariñas',
            'barangay_code' => '042106015',
            'barangay_name' => 'Salitran I',
            'street' => 'Aguinaldo Highway',
            'is_default' => true,
        ]);

        LogisticsProfile::create([
            'user_id' => $this->caviteLogistics->id,
            'company_name' => 'Cavite Speed Delivery',
            'province' => 'Cavite',
            'city_municipality' => 'City of Dasmariñas',
            'status' => 'approved',
        ]);
    }

    public function test_laguna_account_locks_province_to_laguna_and_preloads_all_laguna_cities(): void
    {
        $response = $this->actingAs($this->lagunaLogistics)
            ->get(route('logistics.zones.create'));

        $response->assertStatus(200);
        $response->assertViewHas('companyProvinceName', 'Laguna');
        $response->assertViewHas('companyProvinceCode', '043400000');

        $cities = $response->viewData('cities');
        // Laguna has 30 cities and municipalities
        $this->assertCount(30, $cities);

        // Verify HTML displays fixed province badge and no interactive province select
        $response->assertSee('Operating Region (Fixed)');
        $response->assertSee('Laguna');
        $response->assertDontSee('Select Province');
        $response->assertSee('Select City / Municipality (30 available)');
    }

    public function test_cavite_account_locks_province_to_cavite_and_preloads_all_cavite_cities(): void
    {
        $response = $this->actingAs($this->caviteLogistics)
            ->get(route('logistics.zones.create'));

        $response->assertStatus(200);
        $response->assertViewHas('companyProvinceName', 'Cavite');
        $response->assertViewHas('companyProvinceCode', '042100000');

        $cities = $response->viewData('cities');
        // Cavite has 23 cities and municipalities
        $this->assertCount(23, $cities);

        $response->assertSee('Operating Region (Fixed)');
        $response->assertSee('Cavite');
        $response->assertDontSee('Select Province');
        $response->assertSee('Select City / Municipality (23 available)');
    }

    public function test_location_api_returns_all_barangays_for_selected_city(): void
    {
        // 1. Liliw Laguna has 33 barangays
        $responseLiliw = $this->actingAs($this->lagunaLogistics)
            ->getJson('/locations/cities/043410000/barangays');

        $responseLiliw->assertStatus(200);
        $barangaysLiliw = $responseLiliw->json();
        $this->assertCount(33, $barangaysLiliw);

        // 2. Calamba City Laguna has 50 barangays
        $responseCalamba = $this->actingAs($this->lagunaLogistics)
            ->getJson('/locations/cities/043404000/barangays');

        $responseCalamba->assertStatus(200);
        $barangaysCalamba = $responseCalamba->json();
        $this->assertGreaterThanOrEqual(45, count($barangaysCalamba));

        // 3. City of Dasmariñas Cavite
        $responseDasma = $this->actingAs($this->caviteLogistics)
            ->getJson('/locations/cities/042106000/barangays');

        $responseDasma->assertStatus(200);
        $barangaysDasma = $responseDasma->json();
        $this->assertGreaterThanOrEqual(15, count($barangaysDasma));
    }

    public function test_zone_creation_succeeds_with_fixed_province_and_selected_barangays(): void
    {
        // Select 3 uncovered barangays in Liliw
        $selectedBarangays = ['043410001', '043410002', '043410003'];

        $payload = [
            'name' => 'Liliw Highland Sector',
            'province_code' => '043400000',
            'province_name' => 'Laguna',
            'city_code' => '043410000',
            'city_name' => 'Liliw',
            'barangay_codes' => $selectedBarangays,
        ];

        $response = $this->actingAs($this->lagunaLogistics)
            ->post(route('logistics.zones.store'), $payload);

        $response->assertRedirect(route('logistics.zones.index'));

        $this->assertDatabaseHas('zones', [
            'logistics_company_id' => $this->lagunaLogistics->id,
            'name' => 'Liliw Highland Sector',
            'province_name' => 'Laguna',
            'city_name' => 'Liliw',
        ]);

        foreach ($selectedBarangays as $bCode) {
            $this->assertDatabaseHas('zone_barangays', [
                'logistics_company_id' => $this->lagunaLogistics->id,
                'barangay_code' => $bCode,
            ]);
        }
    }

    public function test_nationwide_coverage_works_for_any_philippine_province(): void
    {
        // Create a logistics account in Batangas (041000000)
        $batangasLogistics = User::create([
            'first_name' => 'Batangas',
            'last_name' => 'Logistics Admin',
            'sex' => 'other',
            'birthday' => '1990-01-01',
            'age' => 35,
            'contact_number' => '09170000003',
            'email' => 'batangas_' . uniqid() . '@nexora.test',
            'password' => Hash::make('Password123!@#'),
            'role' => 'logistics',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        Address::create([
            'user_id' => $batangasLogistics->id,
            'recipient_name' => 'Batangas Central Hub',
            'recipient_phone' => '09170000003',
            'region_code' => '040000000',
            'region_name' => 'Region IV-A (CALABARZON)',
            'province_code' => '041000000',
            'province_name' => 'Batangas',
            'municipality_code' => '041005000',
            'municipality_name' => 'Batangas City',
            'barangay_code' => '041005001',
            'barangay_name' => 'Poblacion',
            'street' => 'Batangas Port Road',
            'is_default' => true,
        ]);

        LogisticsProfile::create([
            'user_id' => $batangasLogistics->id,
            'company_name' => 'Batangas Express Cargo',
            'province' => 'Batangas',
            'city_municipality' => 'Batangas City',
            'status' => 'approved',
        ]);

        // When visiting zone create page, cities are automatically synced and persisted
        $response = $this->actingAs($batangasLogistics)
            ->get(route('logistics.zones.create'));

        $response->assertStatus(200);
        $response->assertViewHas('companyProvinceName', 'Batangas');
        $response->assertViewHas('companyProvinceCode', '041000000');

        $cities = $response->viewData('cities');
        // Batangas has 34 cities and municipalities
        $this->assertGreaterThanOrEqual(30, count($cities));

        // When requesting barangays for Agoncillo Batangas (041001000)
        $responseBarangays = $this->actingAs($batangasLogistics)
            ->getJson('/locations/cities/041001000/barangays');

        $responseBarangays->assertStatus(200);
        $barangays = $responseBarangays->json();
        $this->assertGreaterThanOrEqual(15, count($barangays));
    }
}
