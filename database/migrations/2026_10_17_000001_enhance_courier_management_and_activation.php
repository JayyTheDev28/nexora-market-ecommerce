<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Allow 'pending_activation' in users.account_status and add activation fields
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN account_status VARCHAR(30) NOT NULL DEFAULT 'active'");
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('activation_token', 64)->nullable()->index()->after('remember_token');
            $table->timestamp('activation_token_expires_at')->nullable()->after('activation_token');
        });

        // 2. Add logistics company tenant isolation, license number & expiry date to courier_profiles
        Schema::table('courier_profiles', function (Blueprint $table) {
            $table->foreignId('sorting_center_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->string('license_number', 50)->nullable()->unique()->after('plate_number');
            $table->date('license_expiry_date')->nullable()->after('license_number');
        });
    }

    public function down(): void
    {
        Schema::table('courier_profiles', function (Blueprint $table) {
            $table->dropForeign(['sorting_center_id']);
            $table->dropColumn(['sorting_center_id', 'license_number', 'license_expiry_date']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['activation_token', 'activation_token_expires_at']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN account_status ENUM('active', 'suspended', 'deactivated') NOT NULL DEFAULT 'active'");
        }
    }
};
