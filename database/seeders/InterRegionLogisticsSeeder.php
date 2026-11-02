<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\CourierProfile;
use App\Models\Hub;
use App\Models\LogisticsProfile;
use App\Models\PsgcBarangay;
use App\Models\User;
use App\Models\Zone;
use App\Models\ZoneBarangay;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class InterRegionLogisticsSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure PSGC data is populated first
        $this->call(PsgcSeeder::class);

        // ==========================================
        // 1. LAGUNA LOGISTICS COMPANY
        // ==========================================
        $lagunaCompany = User::updateOrCreate(
            ['email' => 'logistics@nexora.test'],
            [
                'first_name' => 'Laguna',
                'last_name' => 'Express Logistics',
                'sex' => 'other',
                'birthday' => '1995-01-01',
                'age' => 31,
                'contact_number' => '09170001122',
                'password' => Hash::make('Password123!@#'),
                'role' => 'logistics',
                'approval_status' => 'approved',
                'account_status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        LogisticsProfile::updateOrCreate(
            ['user_id' => $lagunaCompany->id],
            [
                'company_name' => 'Laguna Express Logistics Inc.',
                'sec_registration_no' => 'SEC-LAG-2026-001',
                'tin' => '123-456-789-001',
                'region' => 'Region IV-A (CALABARZON)',
                'province' => 'Laguna',
                'city_municipality' => 'Santa Cruz',
                'barangay' => 'Poblacion I',
                'street_address' => 'Logistics Highway, Brgy. Poblacion I',
                'postal_code' => '4009',
                'contact_person_first_name' => 'Laguna',
                'contact_person_last_name' => 'Operations Manager',
                'position' => 'Logistics Director',
                'email' => $lagunaCompany->email,
                'mobile_number' => $lagunaCompany->contact_number,
                'status' => 'approved',
            ]
        );

        Address::updateOrCreate(
            ['user_id' => $lagunaCompany->id],
            [
                'region_code' => '040000000',
                'region_name' => 'Region IV-A (CALABARZON)',
                'province_code' => '043400000',
                'province_name' => 'Laguna',
                'municipality_code' => '043426000',
                'municipality_name' => 'Santa Cruz',
                'barangay_code' => '043426001',
                'barangay_name' => 'Poblacion I',
                'street' => 'Logistics Highway',
                'house_number' => 'Hub 1',
                'recipient_name' => 'Laguna Express Operations',
                'recipient_phone' => '09170001122',
                'is_default' => true,
                'needs_manual_review' => false,
            ]
        );

        // Laguna Hubs
        $lagunaHub1 = Hub::updateOrCreate(
            ['code' => 'HUB-LAG-SC'],
            [
                'logistics_company_id' => $lagunaCompany->id,
                'name' => 'Laguna Central Hub - Santa Cruz',
                'province_code' => '043400000',
                'province_name' => 'Laguna',
                'city_code' => '043426000',
                'city_name' => 'Santa Cruz',
                'barangay_code' => '043426001',
                'barangay_name' => 'Poblacion I',
                'street_address' => 'National Highway, Santa Cruz',
                'contact_number' => '09170001122',
                'is_active' => true,
            ]
        );

        $lagunaHub2 = Hub::updateOrCreate(
            ['code' => 'HUB-LAG-CAL'],
            [
                'logistics_company_id' => $lagunaCompany->id,
                'name' => 'Laguna West Hub - Calamba',
                'province_code' => '043400000',
                'province_name' => 'Laguna',
                'city_code' => '043404000',
                'city_name' => 'City of Calamba',
                'barangay_code' => '043404042',
                'barangay_name' => 'Real',
                'street_address' => 'Real Industrial Park, Calamba',
                'contact_number' => '09170001123',
                'is_active' => true,
            ]
        );

        // Laguna Zones
        $lagunaZone1 = Zone::updateOrCreate(
            ['logistics_company_id' => $lagunaCompany->id, 'name' => 'Santa Cruz Zone'],
            [
                'hub_id' => $lagunaHub1->id,
                'code' => 'ZONE-LAG-SC',
                'province_code' => '043400000',
                'province_name' => 'Laguna',
                'city_code' => '043426000',
                'city_name' => 'Santa Cruz',
                'is_active' => true,
            ]
        );

        $lagunaZone2 = Zone::updateOrCreate(
            ['logistics_company_id' => $lagunaCompany->id, 'name' => 'Los Baños Zone'],
            [
                'hub_id' => $lagunaHub2->id,
                'code' => 'ZONE-LAG-LB',
                'province_code' => '043400000',
                'province_name' => 'Laguna',
                'city_code' => '043411000',
                'city_name' => 'Los Baños',
                'is_active' => true,
            ]
        );

        $lagunaZone3 = Zone::updateOrCreate(
            ['logistics_company_id' => $lagunaCompany->id, 'name' => 'Calamba City Zone'],
            [
                'hub_id' => $lagunaHub2->id,
                'code' => 'ZONE-LAG-CAL',
                'province_code' => '043400000',
                'province_name' => 'Laguna',
                'city_code' => '043404000',
                'city_name' => 'City of Calamba',
                'is_active' => true,
            ]
        );

        // Seed Zone Barangays for Laguna
        $this->attachBarangaysToZone($lagunaZone1, '043426000', ['043426001', '043426002', '043426003', '043426006', '043426011']);
        $this->attachBarangaysToZone($lagunaZone2, '043411000', ['043411001', '043411002', '043411004', '043411005', '043411009']);
        $this->attachBarangaysToZone($lagunaZone3, '043404000', ['043404001', '043404002', '043404011', '043404042', '043404050']);

        // Laguna Riders
        $lagunaRider1 = $this->createOrUpdateRider(
            'laguna.rider1@nexora.test',
            'Eduardo',
            'LagunaRider1',
            '09181112233',
            $lagunaCompany->id,
            'Motorcycle',
            'MC-LAG-101',
            [$lagunaZone1->id, $lagunaZone2->id]
        );

        $lagunaRider2 = $this->createOrUpdateRider(
            'laguna.rider2@nexora.test',
            'Marco',
            'LagunaRider2',
            '09181112244',
            $lagunaCompany->id,
            'Motorcycle',
            'MC-LAG-102',
            [$lagunaZone3->id]
        );

        // Also ensure user 134 (existing Juan Rider 01) is assigned to Laguna Zone 1
        $existingRider = User::find(134);
        if ($existingRider) {
            $existingRider->assignedZones()->syncWithoutDetaching([$lagunaZone1->id]);
        }


        // ==========================================
        // 2. CAVITE LOGISTICS COMPANY
        // ==========================================
        $caviteCompany = User::updateOrCreate(
            ['email' => 'cavite.logistics@nexora.test'],
            [
                'first_name' => 'Cavite',
                'last_name' => 'FastFlow Logistics',
                'sex' => 'other',
                'birthday' => '1994-03-15',
                'age' => 32,
                'contact_number' => '09179998877',
                'password' => Hash::make('Password123!@#'),
                'role' => 'logistics',
                'approval_status' => 'approved',
                'account_status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        LogisticsProfile::updateOrCreate(
            ['user_id' => $caviteCompany->id],
            [
                'company_name' => 'FastFlow Logistics Cavite Corp.',
                'sec_registration_no' => 'SEC-CAV-2026-002',
                'tin' => '987-654-321-002',
                'region' => 'Region IV-A (CALABARZON)',
                'province' => 'Cavite',
                'city_municipality' => 'City of Dasmariñas',
                'barangay' => 'Salitran I',
                'street_address' => 'Aguinaldo Highway, Dasmariñas',
                'postal_code' => '4114',
                'contact_person_first_name' => 'Cavite',
                'contact_person_last_name' => 'Operations Head',
                'position' => 'Regional Logistics Manager',
                'email' => $caviteCompany->email,
                'mobile_number' => $caviteCompany->contact_number,
                'status' => 'approved',
            ]
        );

        Address::updateOrCreate(
            ['user_id' => $caviteCompany->id],
            [
                'region_code' => '040000000',
                'region_name' => 'Region IV-A (CALABARZON)',
                'province_code' => '042100000',
                'province_name' => 'Cavite',
                'municipality_code' => '042106000',
                'municipality_name' => 'City of Dasmariñas',
                'barangay_code' => '042106015',
                'barangay_name' => 'Salitran I',
                'street' => 'Aguinaldo Highway',
                'house_number' => 'Cavite Hub',
                'recipient_name' => 'FastFlow Logistics Cavite Operations',
                'recipient_phone' => '09179998877',
                'is_default' => true,
                'needs_manual_review' => false,
            ]
        );

        // Cavite Hubs
        $caviteHub1 = Hub::updateOrCreate(
            ['code' => 'HUB-CAV-BAC'],
            [
                'logistics_company_id' => $caviteCompany->id,
                'name' => 'Cavite North Hub - Bacoor',
                'province_code' => '042100000',
                'province_name' => 'Cavite',
                'city_code' => '042103000',
                'city_name' => 'City of Bacoor',
                'barangay_code' => '042103008',
                'barangay_name' => 'Habay I',
                'street_address' => 'Tirona Highway, Bacoor',
                'contact_number' => '09179998877',
                'is_active' => true,
            ]
        );

        $caviteHub2 = Hub::updateOrCreate(
            ['code' => 'HUB-CAV-DAS'],
            [
                'logistics_company_id' => $caviteCompany->id,
                'name' => 'Cavite South Hub - Dasmariñas',
                'province_code' => '042100000',
                'province_name' => 'Cavite',
                'city_code' => '042106000',
                'city_name' => 'City of Dasmariñas',
                'barangay_code' => '042106015',
                'barangay_name' => 'Salitran I',
                'street_address' => 'Governor Drive, Dasmariñas',
                'contact_number' => '09179998878',
                'is_active' => true,
            ]
        );

        // Cavite Zones
        $caviteZone1 = Zone::updateOrCreate(
            ['logistics_company_id' => $caviteCompany->id, 'name' => 'Bacoor City Zone'],
            [
                'hub_id' => $caviteHub1->id,
                'code' => 'ZONE-CAV-BAC',
                'province_code' => '042100000',
                'province_name' => 'Cavite',
                'city_code' => '042103000',
                'city_name' => 'City of Bacoor',
                'is_active' => true,
            ]
        );

        $caviteZone2 = Zone::updateOrCreate(
            ['logistics_company_id' => $caviteCompany->id, 'name' => 'Dasmariñas Zone'],
            [
                'hub_id' => $caviteHub2->id,
                'code' => 'ZONE-CAV-DAS',
                'province_code' => '042100000',
                'province_name' => 'Cavite',
                'city_code' => '042106000',
                'city_name' => 'City of Dasmariñas',
                'is_active' => true,
            ]
        );

        $caviteZone3 = Zone::updateOrCreate(
            ['logistics_company_id' => $caviteCompany->id, 'name' => 'Imus City Zone'],
            [
                'hub_id' => $caviteHub1->id,
                'code' => 'ZONE-CAV-IMU',
                'province_code' => '042100000',
                'province_name' => 'Cavite',
                'city_code' => '042109000',
                'city_name' => 'City of Imus',
                'is_active' => true,
            ]
        );

        // Seed Zone Barangays for Cavite
        $this->attachBarangaysToZone($caviteZone1, '042103000', ['042103001', '042103008', '042103009', '042103019', '042103020', '042103021']);
        $this->attachBarangaysToZone($caviteZone2, '042106000', ['042106001', '042106002', '042106008', '042106010', '042106015', '042106016']);
        $this->attachBarangaysToZone($caviteZone3, '042109000', ['042109001', '042109006', '042109013', '042109020', '042109023']);

        // Cavite Riders
        $caviteRider1 = $this->createOrUpdateRider(
            'cavite.rider1@nexora.test',
            'Angelo',
            'CaviteRider1',
            '09182223355',
            $caviteCompany->id,
            'Motorcycle',
            'MC-CAV-201',
            [$caviteZone1->id]
        );

        $caviteRider2 = $this->createOrUpdateRider(
            'cavite.rider2@nexora.test',
            'Noel',
            'CaviteRider2',
            '09182223366',
            $caviteCompany->id,
            'Motorcycle',
            'MC-CAV-202',
            [$caviteZone2->id]
        );

        $caviteRider3 = $this->createOrUpdateRider(
            'cavite.rider3@nexora.test',
            'Paolo',
            'CaviteRider3',
            '09182223377',
            $caviteCompany->id,
            'Van',
            'VN-CAV-203',
            [$caviteZone3->id]
        );
    }

    private function attachBarangaysToZone(Zone $zone, string $cityCode, array $barangayCodes): void
    {
        foreach ($barangayCodes as $code) {
            $psgc = PsgcBarangay::where('code', $code)->first();
            if ($psgc) {
                ZoneBarangay::updateOrCreate(
                    [
                        'logistics_company_id' => $zone->logistics_company_id,
                        'barangay_code' => $code,
                    ],
                    [
                        'zone_id' => $zone->id,
                        'province_code' => $psgc->province_code,
                        'province_name' => $psgc->province->name ?? $zone->province_name,
                        'city_code' => $psgc->city_code,
                        'city_name' => $psgc->city_name,
                        'barangay_name' => $psgc->name,
                    ]
                );
            }
        }
    }

    private function createOrUpdateRider(
        string $email,
        string $firstName,
        string $lastName,
        string $contactNumber,
        int $companyId,
        string $vehicleType,
        string $plateNumber,
        array $zoneIds = []
    ): User {
        $rider = User::updateOrCreate(
            ['email' => $email],
            [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'sex' => 'male',
                'birthday' => '1997-04-10',
                'age' => 29,
                'contact_number' => $contactNumber,
                'password' => Hash::make('Password123!@#'),
                'role' => 'rider',
                'approval_status' => 'approved',
                'account_status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        CourierProfile::updateOrCreate(
            ['user_id' => $rider->id],
            [
                'sorting_center_id' => $companyId,
                'logistics_company_id' => $companyId,
                'is_online' => true,
                'vehicle_type' => $vehicleType,
                'plate_number' => $plateNumber,
                'license_number' => 'N01-23-' . substr(md5($email), 0, 6),
                'license_expiry_date' => now()->addYears(2)->toDateString(),
            ]
        );

        if (! empty($zoneIds)) {
            $rider->assignedZones()->sync($zoneIds);
        }

        return $rider;
    }
}

