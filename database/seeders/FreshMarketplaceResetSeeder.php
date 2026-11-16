<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\CourierProfile;
use App\Models\Hub;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class FreshMarketplaceResetSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Remove all parcels, orders, financial records, stores, and products
        $this->clearStoresProductsAndParcels();

        // 2. Clean old generated product images on disk
        Storage::disk('public')->deleteDirectory('products');
        Storage::disk('public')->makeDirectory('products/variants');

        // 3. Re-seed foundational & necessary system users
        if (DB::table('psgc_provinces')->count() === 0) {
            $this->call(PsgcSeeder::class);
        }

        $this->call([
            CategorySeeder::class,
            AdminUserSeeder::class,
            LogisticsSeeder::class,
            InterRegionLogisticsSeeder::class,
        ]);

        $this->seedTransferCourier();
        $this->seedNecessaryBuyers();

        // 4. Seed 20 sellers, 100 products (with images & variations), and vouchers
        $this->call([
            SellerSeeder::class,
            ProductSeeder::class,
            VoucherSeeder::class,
        ]);
    }

    private function clearStoresProductsAndParcels(): void
    {
        Schema::disableForeignKeyConstraints();

        $tablesToClear = [
            // Parcel / Incident / Manifest / Hub Transfer tables
            'parcel_incident_events',
            'parcel_incidents',
            'transfer_manifest_items',
            'transfer_manifests',
            'hub_transfers',
            'delivery_attempts',
            'proof_of_deliveries',
            // COD / Financials / Payouts / Commissions / Disputes
            'cod_remittance_allocations',
            'cod_remittances',
            'cod_ledger_entries',
            'cod_collections',
            'inter_company_shipping_allocations',
            'commissions',
            'disputes',
            'financial_adjustments',
            'rider_earnings',
            'courier_payout_items',
            'courier_payouts',
            'logistics_remittance_items',
            'logistics_admin_remittances',
            'logistics_expenses',
            'seller_settlement_allocations',
            'seller_payout_requests',
            'seller_warnings',
            // Orders / Cart / Reviews / Wishlists / Chat
            'order_status_histories',
            'order_items',
            'orders',
            'reviews',
            'cart_items',
            'wishlists',
            'messages',
            'conversation_participants',
            'conversations',
            'notifications',
            'audit_logs',
            'idempotency_keys',
            // Store / Product / Voucher tables
            'store_follows',
            'store_categories',
            'category_seller',
            'products',
            'seller_profiles',
            'user_vouchers',
            'vouchers',
        ];

        foreach ($tablesToClear as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
            }
        }

        // Remove old seller user accounts, their addresses, and tokens
        $oldSellerIds = User::where('role', 'seller')->pluck('id');
        if ($oldSellerIds->isNotEmpty()) {
            DB::table('addresses')->whereIn('user_id', $oldSellerIds)->delete();
            DB::table('personal_access_tokens')
                ->where('tokenable_type', User::class)
                ->whereIn('tokenable_id', $oldSellerIds)
                ->delete();
            User::whereIn('id', $oldSellerIds)->delete();
        }

        Schema::enableForeignKeyConstraints();
    }

    private function seedTransferCourier(): void
    {
        $logistics = User::where('email', 'logistics@nexora.test')->first()
            ?? User::where('role', 'logistics')->first();
        $calambaHub = Hub::where('code', 'HUB-LAG-CAL')->first();

        if (! $logistics || ! $calambaHub) {
            return;
        }

        $courier = User::updateOrCreate(
            ['email' => 'courier@nexora.test'],
            [
                'first_name' => 'Marco',
                'last_name' => 'Villanueva',
                'sex' => 'male',
                'birthday' => '1993-04-12',
                'age' => Carbon::parse('1993-04-12')->age,
                'contact_number' => '09178889900',
                'password' => Hash::make('Password123!@#'),
                'role' => 'courier',
                'approval_status' => 'approved',
                'account_status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        CourierProfile::updateOrCreate(
            ['user_id' => $courier->id],
            [
                'sorting_center_id' => $logistics->id,
                'logistics_company_id' => $logistics->id,
                'assigned_hub_id' => $calambaHub->id,
                'vehicle_type' => 'Van',
                'plate_number' => 'TR-2045',
                'license_number' => 'N02-19-884210',
                'license_expiry_date' => now()->addYears(2)->toDateString(),
            ]
        );
    }

    private function seedNecessaryBuyers(): void
    {
        $buyer = User::updateOrCreate(
            ['email' => 'buyer@nexora.test'],
            [
                'first_name' => 'Juan',
                'last_name' => 'Dela Cruz',
                'middle_initial' => 'M',
                'sex' => 'male',
                'birthday' => '1998-06-15',
                'age' => Carbon::parse('1998-06-15')->age,
                'contact_number' => '09171234567',
                'password' => Hash::make('Password123!@#'),
                'role' => 'buyer',
                'approval_status' => 'approved',
                'account_status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        Address::updateOrCreate(
            ['user_id' => $buyer->id, 'street' => '123 Rizal Street'],
            [
                'recipient_name' => 'Juan Dela Cruz',
                'recipient_phone' => '09171234567',
                'region_code' => '040000000',
                'region_name' => 'Region IV-A (CALABARZON)',
                'province_code' => '043400000',
                'province_name' => 'Laguna',
                'municipality_code' => '043426000',
                'municipality_name' => 'Santa Cruz',
                'barangay_code' => '043426001',
                'barangay_name' => 'Poblacion I',
                'house_number' => 'Unit 4B',
                'postal_code' => '4009',
                'is_default' => true,
                'needs_manual_review' => false,
            ]
        );

        $buyer2 = User::updateOrCreate(
            ['email' => 'maria@nexora.test'],
            [
                'first_name' => 'Maria',
                'last_name' => 'Santos',
                'middle_initial' => 'C',
                'sex' => 'female',
                'birthday' => '1999-09-20',
                'age' => Carbon::parse('1999-09-20')->age,
                'contact_number' => '09189876543',
                'password' => Hash::make('Password123!@#'),
                'role' => 'buyer',
                'approval_status' => 'approved',
                'account_status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        Address::updateOrCreate(
            ['user_id' => $buyer2->id, 'street' => '45 Mabini Street'],
            [
                'recipient_name' => 'Maria Santos',
                'recipient_phone' => '09189876543',
                'region_code' => '040000000',
                'region_name' => 'Region IV-A (CALABARZON)',
                'province_code' => '043400000',
                'province_name' => 'Laguna',
                'municipality_code' => '043426000',
                'municipality_name' => 'Santa Cruz',
                'barangay_code' => '043426002',
                'barangay_name' => 'Poblacion II',
                'house_number' => 'House 12',
                'postal_code' => '4009',
                'is_default' => true,
                'needs_manual_review' => false,
            ]
        );
    }
}
