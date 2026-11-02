<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Hubs
        Schema::create('hubs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('logistics_company_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('code', 50)->unique();
            $table->string('province_code', 20);
            $table->string('province_name', 100);
            $table->string('city_code', 20);
            $table->string('city_name', 100);
            $table->string('barangay_code', 20);
            $table->string('barangay_name', 100);
            $table->string('street_address')->nullable();
            $table->string('contact_number', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Zones
        Schema::create('zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('logistics_company_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('hub_id')->nullable()->constrained('hubs')->nullOnDelete();
            $table->string('name', 150);
            $table->string('code', 50)->nullable();
            $table->string('province_code', 20)->nullable();
            $table->string('province_name', 100)->nullable();
            $table->string('city_code', 20)->nullable();
            $table->string('city_name', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 3. Zone Barangays (Coverage must live in one place only per logistics company)
        Schema::create('zone_barangays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id')->constrained('zones')->cascadeOnDelete();
            $table->foreignId('logistics_company_id')->constrained('users')->cascadeOnDelete();
            $table->string('province_code', 20);
            $table->string('province_name', 100);
            $table->string('city_code', 20);
            $table->string('city_name', 100);
            $table->string('barangay_code', 20);
            $table->string('barangay_name', 100);
            $table->timestamps();

            // Coverage constraint: each barangay can only be assigned to one zone within the same company
            $table->unique(['logistics_company_id', 'barangay_code'], 'unique_company_barangay_coverage');
        });

        // 4. Rider to Zone assignments (A zone can have several riders, a rider can have one or more zones)
        Schema::create('rider_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('zone_id')->constrained('zones')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['rider_id', 'zone_id']);
        });

        // 5. Hub Transfers (Tracking parcels traveling between hubs / logistics companies)
        Schema::create('hub_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('tracking_number', 100)->index();
            $table->foreignId('sender_logistics_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('sender_hub_id')->nullable()->constrained('hubs')->nullOnDelete();
            $table->foreignId('receiver_logistics_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('receiver_hub_id')->nullable()->constrained('hubs')->nullOnDelete();
            $table->enum('status', ['in_transit', 'received', 'cancelled'])->default('in_transit');
            $table->timestamp('dispatched_at')->nullable();
            $table->foreignId('dispatched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 6. Link Orders to Hubs and Zones
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'origin_hub_id')) {
                $table->foreignId('origin_hub_id')->nullable()->after('current_custody_logistics_id')->constrained('hubs')->nullOnDelete();
            }
            if (! Schema::hasColumn('orders', 'destination_hub_id')) {
                $table->foreignId('destination_hub_id')->nullable()->after('origin_hub_id')->constrained('hubs')->nullOnDelete();
            }
            if (! Schema::hasColumn('orders', 'pickup_zone_id')) {
                $table->foreignId('pickup_zone_id')->nullable()->after('destination_hub_id')->constrained('zones')->nullOnDelete();
            }
            if (! Schema::hasColumn('orders', 'delivery_zone_id')) {
                $table->foreignId('delivery_zone_id')->nullable()->after('pickup_zone_id')->constrained('zones')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['origin_hub_id']);
            $table->dropForeign(['destination_hub_id']);
            $table->dropForeign(['pickup_zone_id']);
            $table->dropForeign(['delivery_zone_id']);
            $table->dropColumn(['origin_hub_id', 'destination_hub_id', 'pickup_zone_id', 'delivery_zone_id']);
        });

        Schema::dropIfExists('hub_transfers');
        Schema::dropIfExists('rider_zones');
        Schema::dropIfExists('zone_barangays');
        Schema::dropIfExists('zones');
        Schema::dropIfExists('hubs');
    }
};

