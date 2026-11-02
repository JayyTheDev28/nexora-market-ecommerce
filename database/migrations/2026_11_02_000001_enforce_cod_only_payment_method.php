<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Normalize all existing orders to COD and clear gateway payment expiration
        DB::table('orders')->where('payment_method', '!=', 'cod')->update([
            'payment_method' => 'cod',
            'payment_expires_at' => null,
        ]);

        // 2. Modify column to string with default 'cod'
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_method', 20)->default('cod')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('payment_method', ['cod', 'ewallet', 'card'])->default('cod')->change();
        });
    }
};

