<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. PSGC Reference Tables
        Schema::create('psgc_provinces', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->string('region_code', 20)->nullable();
            $table->string('region_name', 100)->nullable();
            $table->timestamps();
        });

        Schema::create('psgc_cities', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->string('province_code', 20)->index();
            $table->string('province_name', 100);
            $table->timestamps();
        });

        Schema::create('psgc_barangays', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->string('city_code', 20)->index();
            $table->string('city_name', 100);
            $table->string('province_code', 20)->index();
            $table->timestamps();
        });

        // 2. Add manual review flag to addresses
        Schema::table('addresses', function (Blueprint $table) {
            if (! Schema::hasColumn('addresses', 'needs_manual_review')) {
                $table->boolean('needs_manual_review')->default(false)->after('is_default')->index();
            }
            if (! Schema::hasColumn('addresses', 'manual_review_reason')) {
                $table->string('manual_review_reason')->nullable()->after('needs_manual_review');
            }
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn(['needs_manual_review', 'manual_review_reason']);
        });

        Schema::dropIfExists('psgc_barangays');
        Schema::dropIfExists('psgc_cities');
        Schema::dropIfExists('psgc_provinces');
    }
};

