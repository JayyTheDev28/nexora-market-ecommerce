<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Expand and migrate users.role enum
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('buyer', 'seller', 'rider', 'logistics', 'admin', 'courier', 'sorting_center') DEFAULT 'buyer'");
            DB::table('users')->where('role', 'sorting_center')->update(['role' => 'logistics']);
            DB::table('users')->where('role', 'courier')->update(['role' => 'rider']);
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('buyer', 'seller', 'admin', 'logistics', 'rider') DEFAULT 'buyer'");
        } else {
            DB::table('users')->where('role', 'sorting_center')->update(['role' => 'logistics']);
            DB::table('users')->where('role', 'courier')->update(['role' => 'rider']);
        }

        // 2. Allow nullable fields in logistics_profiles to accommodate merged profiles
        Schema::table('logistics_profiles', function (Blueprint $table) {
            $table->string('sec_registration_no')->nullable()->change();
            $table->string('tin')->nullable()->change();
            $table->string('region')->nullable()->change();
            $table->string('province')->nullable()->change();
            $table->string('city_municipality')->nullable()->change();
            $table->string('barangay')->nullable()->change();
            $table->string('street_address')->nullable()->change();
            $table->string('postal_code', 20)->nullable()->change();
            $table->string('contact_person_first_name', 50)->nullable()->change();
            $table->string('contact_person_last_name', 50)->nullable()->change();
            $table->string('position')->nullable()->change();
            $table->string('email')->nullable()->change();
            $table->string('mobile_number')->nullable()->change();
            $table->json('service_areas')->nullable()->change();
        });

        // 3. Merge data from sorting_center_profiles into logistics_profiles
        if (Schema::hasTable('sorting_center_profiles')) {
            $sortingCenters = DB::table('sorting_center_profiles')->get();
            foreach ($sortingCenters as $sc) {
                $user = DB::table('users')->where('id', $sc->user_id)->first();
                $existingLogistics = DB::table('logistics_profiles')->where('user_id', $sc->user_id)->first();

                if (! $existingLogistics && $user) {
                    $companyName = ! empty($sc->business_name) ? $sc->business_name : "Logistics Company {$user->id}";
                    // Check if company_name already taken
                    if (DB::table('logistics_profiles')->where('company_name', $companyName)->exists()) {
                        $companyName = "{$companyName} ({$user->id})";
                    }

                    DB::table('logistics_profiles')->insert([
                        'user_id' => $user->id,
                        'company_name' => $companyName,
                        'contact_person_first_name' => $user->first_name ?? 'Logistics',
                        'contact_person_last_name' => $user->last_name ?? 'Manager',
                        'email' => $user->email,
                        'mobile_number' => $user->contact_number,
                        'mayors_permit_path' => $sc->business_permit_path,
                        'status' => 'approved',
                        'created_at' => $sc->created_at ?? now(),
                        'updated_at' => $sc->updated_at ?? now(),
                    ]);
                }
            }
        }

        // 4. Update courier_profiles to add logistics_company_id
        if (Schema::hasTable('courier_profiles')) {
            Schema::table('courier_profiles', function (Blueprint $table) {
                if (! Schema::hasColumn('courier_profiles', 'logistics_company_id')) {
                    $table->foreignId('logistics_company_id')->nullable()->after('sorting_center_id')->constrained('users')->nullOnDelete();
                }
            });

            // Backfill logistics_company_id from sorting_center_id
            DB::statement('UPDATE courier_profiles SET logistics_company_id = sorting_center_id WHERE sorting_center_id IS NOT NULL');
        }

        // 5. Update orders table with multi-region logistics columns
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'pickup_logistics_id')) {
                $table->foreignId('pickup_logistics_id')->nullable()->after('sorting_center_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('orders', 'delivery_logistics_id')) {
                $table->foreignId('delivery_logistics_id')->nullable()->after('pickup_logistics_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('orders', 'current_custody_logistics_id')) {
                $table->foreignId('current_custody_logistics_id')->nullable()->after('delivery_logistics_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('orders', 'pickup_rider_id')) {
                $table->foreignId('pickup_rider_id')->nullable()->after('pickup_courier_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('orders', 'delivery_rider_id')) {
                $table->foreignId('delivery_rider_id')->nullable()->after('delivery_courier_id')->constrained('users')->nullOnDelete();
            }
        });

        // Backfill orders
        DB::statement('UPDATE orders SET pickup_logistics_id = sorting_center_id, delivery_logistics_id = sorting_center_id, current_custody_logistics_id = sorting_center_id WHERE sorting_center_id IS NOT NULL');
        DB::statement('UPDATE orders SET pickup_rider_id = pickup_courier_id WHERE pickup_courier_id IS NOT NULL');
        DB::statement('UPDATE orders SET delivery_rider_id = delivery_courier_id WHERE delivery_courier_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['pickup_logistics_id']);
            $table->dropForeign(['delivery_logistics_id']);
            $table->dropForeign(['current_custody_logistics_id']);
            $table->dropForeign(['pickup_rider_id']);
            $table->dropForeign(['delivery_rider_id']);
            $table->dropColumn([
                'pickup_logistics_id',
                'delivery_logistics_id',
                'current_custody_logistics_id',
                'pickup_rider_id',
                'delivery_rider_id',
            ]);
        });

        if (Schema::hasTable('courier_profiles')) {
            Schema::table('courier_profiles', function (Blueprint $table) {
                $table->dropForeign(['logistics_company_id']);
                $table->dropColumn('logistics_company_id');
            });
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('buyer', 'seller', 'rider', 'logistics', 'admin', 'courier', 'sorting_center') DEFAULT 'buyer'");
            DB::table('users')->where('role', 'logistics')->update(['role' => 'sorting_center']);
            DB::table('users')->where('role', 'rider')->update(['role' => 'courier']);
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('buyer', 'seller', 'courier', 'sorting_center', 'admin') DEFAULT 'buyer'");
        } else {
            DB::table('users')->where('role', 'logistics')->update(['role' => 'sorting_center']);
            DB::table('users')->where('role', 'rider')->update(['role' => 'courier']);
        }
    }
};

