<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SellerSeeder extends Seeder
{
    /**
     * These are the sellers referenced throughout the catalog/home page
     * demo content (Nexus Home, Tech Haven, etc.). Seeded as real,
     * pre-approved accounts so ProductSeeder has valid seller_id values
     * to attach products to, and so the storefront has something to show
     * before any real seller registers.
     */
    public function run(): void
    {
        $sellers = [
            ['business_name' => 'Nexus Home', 'category' => 'home-living', 'email' => 'nexushome@nexora.test'],
            ['business_name' => 'Tech Haven', 'category' => 'electronics', 'email' => 'techhaven@nexora.test'],
            ['business_name' => 'Luxe Goods Co.', 'category' => 'fashion', 'email' => 'luxegoods@nexora.test'],
            ['business_name' => 'Premium Sports', 'category' => 'sports', 'email' => 'premiumsports@nexora.test'],
            ['business_name' => 'Echo Home', 'category' => 'home-living', 'email' => 'echohome@nexora.test'],
        ];

        foreach ($sellers as $seller) {
            $user = User::firstOrCreate(
                ['email' => $seller['email']],
                [
                    'first_name' => $seller['business_name'],
                    'last_name' => 'Seller',
                    'sex' => 'other',
                    'birthday' => '1995-01-01',
                    'age' => \Carbon\Carbon::parse('1995-01-01')->age,
                    'contact_number' => '09171234567',
                    'password' => Hash::make('password'),
                    'role' => 'seller',
                    'approval_status' => 'approved',
                    'email_verified_at' => now(),
                ]
            );

            SellerProfile::firstOrCreate(
                ['user_id' => $user->id],
                ['business_name' => $seller['business_name'], 'category' => $seller['category']]
            );

            Address::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'province_code' => '0434', 'province_name' => 'Laguna',
                    'municipality_code' => '043412', 'municipality_name' => 'Santa Cruz',
                    'barangay_code' => '043412001', 'barangay_name' => 'Barangay 1',
                    'street' => 'Sample Street', 'house_number' => '1',
                ]
            );
        }
    }
}
