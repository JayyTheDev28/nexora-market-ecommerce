<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('addresses')) {
            Schema::table('addresses', function (Blueprint $table) {
                $table->string('barangay_code')->nullable()->change();
                $table->string('barangay_name')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('addresses')) {
            Schema::table('addresses', function (Blueprint $table) {
                $table->string('barangay_code')->nullable(false)->change();
                $table->string('barangay_name')->nullable(false)->change();
            });
        }
    }
};
