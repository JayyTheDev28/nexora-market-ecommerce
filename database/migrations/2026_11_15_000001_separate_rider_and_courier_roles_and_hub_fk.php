<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Expand users.role enum on MySQL so 'rider' (last-mile) and 'courier' (hub transfer) are distinct roles.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('buyer', 'seller', 'admin', 'logistics', 'rider', 'courier') DEFAULT 'buyer'");
        }

        // 2. Preserve historical delivery riders: if any legacy account has role='courier'
        //    and has last-mile delivery/pickup/earnings/remittance records, keep them as 'rider'.
        $legacyCourierIds = DB::table('users')->where('role', 'courier')->pluck('id');
        if ($legacyCourierIds->isNotEmpty()) {
            foreach ($legacyCourierIds as $userId) {
                $hasLastMileActivity = DB::table('orders')
                    ->where('pickup_courier_id', $userId)
                    ->orWhere('delivery_courier_id', $userId)
                    ->exists()
                    || DB::table('job_offers')->where('courier_id', $userId)->exists()
                    || DB::table('rider_earnings')->where('courier_id', $userId)->exists()
                    || DB::table('cod_ledger_entries')->where('courier_id', $userId)->exists()
                    || DB::table('cod_remittances')->where('courier_id', $userId)->exists();

                if ($hasLastMileActivity) {
                    DB::table('users')->where('id', $userId)->update(['role' => 'rider']);
                }
            }
        }

        // 3. Update courier_profiles:
        //    - Make vehicle_type and plate_number nullable (transfer couriers don't own/register trucks)
        //    - Add dedicated assigned_hub_id FK to hubs
        Schema::table('courier_profiles', function (Blueprint $table) {
            $table->string('vehicle_type')->nullable()->change();
            $table->string('plate_number')->nullable()->change();

            if (! Schema::hasColumn('courier_profiles', 'assigned_hub_id')) {
                $table->foreignId('assigned_hub_id')
                    ->nullable()
                    ->after('logistics_company_id')
                    ->constrained('hubs')
                    ->nullOnDelete();
            }
        });

        // 4. Clean up any ambiguity where sorting_center_id stored a hub ID instead of logistics_company_id
        $profiles = DB::table('courier_profiles')->get();
        foreach ($profiles as $profile) {
            if ($profile->sorting_center_id && $profile->logistics_company_id && (int) $profile->sorting_center_id !== (int) $profile->logistics_company_id) {
                $hubExists = DB::table('hubs')
                    ->where('id', $profile->sorting_center_id)
                    ->where('logistics_company_id', $profile->logistics_company_id)
                    ->exists();

                if ($hubExists) {
                    DB::table('courier_profiles')
                        ->where('id', $profile->id)
                        ->update([
                            'assigned_hub_id' => $profile->sorting_center_id,
                            'sorting_center_id' => $profile->logistics_company_id,
                        ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('courier_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('courier_profiles', 'assigned_hub_id')) {
                $table->dropForeign(['assigned_hub_id']);
                $table->dropColumn('assigned_hub_id');
            }
        });
    }
};
