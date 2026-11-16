<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Category;
use App\Models\SellerProfile;
use App\Models\StoreCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class SellerSeeder extends Seeder
{
    /**
     * 20 verified seller stores spanning all 13 platform categories.
     * Each seller receives approved account status, a SellerProfile,
     * a valid Laguna/CALABARZON pickup Address, allowed category pivot
     * records, and StoreCategory buckets so product uploads obey all rules.
     */
    public function run(): void
    {
        $sellers = [
            ['business_name' => 'Nexora Flagship Store', 'category' => 'electronics', 'email' => 'seller@nexora.test', 'city_code' => '043426000', 'city_name' => 'Santa Cruz', 'brgy_code' => '043426001', 'brgy_name' => 'Poblacion I'],
            ['business_name' => 'Tech Haven', 'category' => 'electronics', 'email' => 'techhaven@nexora.test', 'city_code' => '043405000', 'city_name' => 'Calamba', 'brgy_code' => '043405001', 'brgy_name' => 'Bagong Kalsada'],
            ['business_name' => 'Nexus Home', 'category' => 'home-living', 'email' => 'nexushome@nexora.test', 'city_code' => '043426000', 'city_name' => 'Santa Cruz', 'brgy_code' => '043426002', 'brgy_name' => 'Poblacion II'],
            ['business_name' => 'Echo Home', 'category' => 'home-living', 'email' => 'echohome@nexora.test', 'city_code' => '043414000', 'city_name' => 'Los Baños', 'brgy_code' => '043414001', 'brgy_name' => 'Anos'],
            ['business_name' => 'Luxe Goods Co.', 'category' => 'fashion', 'email' => 'luxegoods@nexora.test', 'city_code' => '043424000', 'city_name' => 'San Pablo', 'brgy_code' => '043424001', 'brgy_name' => 'Bagong Bayan II-A'],
            ['business_name' => 'Manila Threadworks', 'category' => 'fashion', 'email' => 'threadworks@nexora.test', 'city_code' => '043405000', 'city_name' => 'Calamba', 'brgy_code' => '043405002', 'brgy_name' => 'Banadero'],
            ['business_name' => 'Premium Sports', 'category' => 'sports', 'email' => 'premiumsports@nexora.test', 'city_code' => '043426000', 'city_name' => 'Santa Cruz', 'brgy_code' => '043426003', 'brgy_name' => 'Poblacion III'],
            ['business_name' => 'Peak Athletics PH', 'category' => 'sports', 'email' => 'peakathletics@nexora.test', 'city_code' => '043403000', 'city_name' => 'Biñan', 'brgy_code' => '043403001', 'brgy_name' => 'Biñan'],
            ['business_name' => 'Glow & Botanica', 'category' => 'beauty', 'email' => 'glowbotanica@nexora.test', 'city_code' => '043428000', 'city_name' => 'Santa Rosa', 'brgy_code' => '043428001', 'brgy_name' => 'Aplaya'],
            ['business_name' => 'Velvet Skin Studio', 'category' => 'beauty', 'email' => 'velvetskin@nexora.test', 'city_code' => '043414000', 'city_name' => 'Los Baños', 'brgy_code' => '043414002', 'brgy_name' => 'Bagong Silang'],
            ['business_name' => 'PixelForge Gaming', 'category' => 'gaming', 'email' => 'pixelforge@nexora.test', 'city_code' => '043405000', 'city_name' => 'Calamba', 'brgy_code' => '043405003', 'brgy_name' => 'Banlic'],
            ['business_name' => 'Respawn Gear PH', 'category' => 'gaming', 'email' => 'respawngear@nexora.test', 'city_code' => '043428000', 'city_name' => 'Santa Rosa', 'brgy_code' => '043428002', 'brgy_name' => 'Balibago'],
            ['business_name' => 'Horology & Carry', 'category' => 'accessories', 'email' => 'horologycarry@nexora.test', 'city_code' => '043426000', 'city_name' => 'Santa Cruz', 'brgy_code' => '043426004', 'brgy_name' => 'Poblacion IV'],
            ['business_name' => 'Little Sprouts Kids', 'category' => 'kids', 'email' => 'littlesprouts@nexora.test', 'city_code' => '043419000', 'city_name' => 'Pagsanjan', 'brgy_code' => '043419001', 'brgy_name' => 'Anibong'],
            ['business_name' => 'Laguna Pantry Market', 'category' => 'groceries', 'email' => 'lagunapantry@nexora.test', 'city_code' => '043426000', 'city_name' => 'Santa Cruz', 'brgy_code' => '043426005', 'brgy_name' => 'Poblacion V'],
            ['business_name' => 'VitalCore Wellness', 'category' => 'health', 'email' => 'vitalcore@nexora.test', 'city_code' => '043414000', 'city_name' => 'Los Baños', 'brgy_code' => '043414003', 'brgy_name' => 'Bambang'],
            ['business_name' => 'Apex Auto & Moto', 'category' => 'automotive', 'email' => 'apexautomoto@nexora.test', 'city_code' => '043405000', 'city_name' => 'Calamba', 'brgy_code' => '043405004', 'brgy_name' => 'Barandal'],
            ['business_name' => 'Pawfect Companions', 'category' => 'pet-supplies', 'email' => 'pawfect@nexora.test', 'city_code' => '043428000', 'city_name' => 'Santa Rosa', 'brgy_code' => '043428003', 'brgy_name' => 'Caingin'],
            ['business_name' => 'Paper & Ink Collective', 'category' => 'books-stationery', 'email' => 'paperink@nexora.test', 'city_code' => '043424000', 'city_name' => 'San Pablo', 'brgy_code' => '043424002', 'brgy_name' => 'Bagong Pook VI-C'],
            ['business_name' => 'VoltEdge Gadgets', 'category' => 'electronics', 'email' => 'voltedge@nexora.test', 'city_code' => '043403000', 'city_name' => 'Biñan', 'brgy_code' => '043403002', 'brgy_name' => 'Bungahan'],
        ];

        $categoriesBySlug = Category::all()->keyBy('slug');

        foreach ($sellers as $index => $seller) {
            $phone = '0917' . str_pad((string) (2100001 + $index), 7, '0', STR_PAD_LEFT);

            $user = User::updateOrCreate(
                ['email' => $seller['email']],
                [
                    'first_name' => $seller['business_name'],
                    'last_name' => 'Store',
                    'sex' => 'other',
                    'birthday' => '1995-01-01',
                    'age' => Carbon::parse('1995-01-01')->age,
                    'contact_number' => $phone,
                    'password' => Hash::make('Password123!@#'),
                    'role' => 'seller',
                    'approval_status' => 'approved',
                    'account_status' => 'active',
                    'email_verified_at' => now(),
                ]
            );

            SellerProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'business_name' => $seller['business_name'],
                    'category' => $seller['category'],
                    'description' => "Official storefront of {$seller['business_name']} on Nexora Market — curated quality products with nationwide COD shipping.",
                    'contact_phone' => $phone,
                    'contact_email' => $seller['email'],
                    'is_vacation_mode' => false,
                ]
            );

            Address::updateOrCreate(
                ['user_id' => $user->id, 'is_default' => true],
                [
                    'region_code' => '040000000',
                    'region_name' => 'Region IV-A (CALABARZON)',
                    'province_code' => '043400000',
                    'province_name' => 'Laguna',
                    'municipality_code' => $seller['city_code'],
                    'municipality_name' => $seller['city_name'],
                    'barangay_code' => $seller['brgy_code'],
                    'barangay_name' => $seller['brgy_name'],
                    'street' => 'Commerce Avenue',
                    'house_number' => 'Unit ' . ($index + 101),
                    'postal_code' => '4009',
                    'recipient_name' => $seller['business_name'],
                    'recipient_phone' => $phone,
                    'is_default' => true,
                    'needs_manual_review' => false,
                ]
            );

            // Attach primary allowed category in category_seller pivot
            if ($cat = $categoriesBySlug->get($seller['category'])) {
                $user->allowedCategories()->syncWithoutDetaching([$cat->id]);
            }

            // Create 2 store categories per seller
            StoreCategory::firstOrCreate(
                ['seller_id' => $user->id, 'slug' => 'featured-picks'],
                ['name' => 'Featured Picks', 'order_index' => 1, 'is_visible' => true]
            );
            StoreCategory::firstOrCreate(
                ['seller_id' => $user->id, 'slug' => 'new-arrivals'],
                ['name' => 'New Arrivals', 'order_index' => 2, 'is_visible' => true]
            );
        }
    }
}
