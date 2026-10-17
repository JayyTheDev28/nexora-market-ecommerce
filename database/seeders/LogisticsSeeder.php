<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\CourierProfile;
use App\Models\DeliveryArea;
use App\Models\SortingCenterProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class LogisticsSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Sorting Center User
        $sortingCenter = User::updateOrCreate(
            ['email' => 'logistics@nexora.test'],
            [
                'first_name' => 'Nexora',
                'last_name' => 'Logistics Hub',
                'sex' => 'other',
                'birthday' => '1995-01-01',
                'age' => 31,
                'contact_number' => '09170001122',
                'password' => Hash::make('Password123!@#'),
                'role' => 'sorting_center',
                'approval_status' => 'approved',
                'account_status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        SortingCenterProfile::firstOrCreate(
            ['user_id' => $sortingCenter->id],
            [
                'business_name' => 'Nexora Central Sorting Hub (Laguna)',
                'business_permit_path' => null,
            ]
        );

        Address::firstOrCreate(
            ['user_id' => $sortingCenter->id],
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
                'recipient_name' => 'Nexora Logistics Hub',
                'recipient_phone' => '09170001122',
                'is_default' => true,
            ]
        );

        // 2. Rider 01 (Approved & Active, assigned to Area A)
        $rider1 = User::updateOrCreate(
            ['email' => 'rider@nexora.test'],
            [
                'first_name' => 'Juan',
                'last_name' => 'Rider 01',
                'sex' => 'male',
                'birthday' => '1998-05-12',
                'age' => 28,
                'contact_number' => '09181112233',
                'password' => Hash::make('Password123!@#'),
                'role' => 'courier',
                'approval_status' => 'approved',
                'account_status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        CourierProfile::updateOrCreate(
            ['user_id' => $rider1->id],
            [
                'vehicle_type' => 'Motorcycle',
                'plate_number' => 'MC-1001',
                'or_cr_path' => null,
                'license_path' => null,
            ]
        );

        // Also seed rider1@nexora.test for compatibility
        $rider1Alt = User::updateOrCreate(
            ['email' => 'rider1@nexora.test'],
            [
                'first_name' => 'Juan',
                'last_name' => 'Rider 01',
                'sex' => 'male',
                'birthday' => '1998-05-12',
                'age' => 28,
                'contact_number' => '09181112233',
                'password' => Hash::make('Password123!@#'),
                'role' => 'courier',
                'approval_status' => 'approved',
                'account_status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        CourierProfile::updateOrCreate(
            ['user_id' => $rider1Alt->id],
            [
                'vehicle_type' => 'Motorcycle',
                'plate_number' => 'MC-1001',
                'or_cr_path' => null,
                'license_path' => null,
            ]
        );

        // 3. Rider 02 (Approved & Active, assigned to Area B)
        $rider2 = User::updateOrCreate(
            ['email' => 'rider2@nexora.test'],
            [
                'first_name' => 'Carlos',
                'last_name' => 'Rider 02',
                'sex' => 'male',
                'birthday' => '1996-08-20',
                'age' => 30,
                'contact_number' => '09182223344',
                'password' => Hash::make('Password123!@#'),
                'role' => 'courier',
                'approval_status' => 'approved',
                'account_status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        CourierProfile::updateOrCreate(
            ['user_id' => $rider2->id],
            [
                'vehicle_type' => 'Motorcycle',
                'plate_number' => 'MC-1002',
                'or_cr_path' => null,
                'license_path' => null,
            ]
        );

        // 4. Rider 03 (Pending Approval)
        $rider3 = User::updateOrCreate(
            ['email' => 'rider3@nexora.test'],
            [
                'first_name' => 'Mateo',
                'last_name' => 'Rider 03',
                'sex' => 'male',
                'birthday' => '2000-02-14',
                'age' => 26,
                'contact_number' => '09183334455',
                'password' => Hash::make('Password123!@#'),
                'role' => 'courier',
                'approval_status' => 'pending',
                'account_status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        CourierProfile::firstOrCreate(
            ['user_id' => $rider3->id],
            [
                'vehicle_type' => 'Van',
                'plate_number' => 'VN-2003',
                'or_cr_path' => null,
                'license_path' => null,
            ]
        );

        // 5. Delivery Areas matching the ERP PDF example
        DeliveryArea::firstOrCreate(
            ['sorting_center_id' => $sortingCenter->id, 'name' => 'Area A - Santa Cruz'],
            ['courier_id' => $rider1->id]
        );

        DeliveryArea::firstOrCreate(
            ['sorting_center_id' => $sortingCenter->id, 'name' => 'Area B - Pagsanjan'],
            ['courier_id' => $rider2->id]
        );

        DeliveryArea::firstOrCreate(
            ['sorting_center_id' => $sortingCenter->id, 'name' => 'Area C - Los Baños'],
            ['courier_id' => null]
        );
    }
}
