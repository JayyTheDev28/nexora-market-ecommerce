<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Manage Commission (10%)" and "Commission Report" from the admin
     * section of the ERP spec. Rate is stored per-order rather than
     * hardcoded, so changing the platform rate later doesn't rewrite
     * historical earnings.
     */
    public function up(): void
    {
        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();

            $table->decimal('order_total', 10, 2);
            $table->decimal('rate', 5, 4)->default(0.1000); // 10%
            $table->decimal('commission_amount', 10, 2);
            $table->decimal('seller_payout', 10, 2);

            $table->enum('status', ['pending', 'settled'])->default('pending');
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commissions');
    }
};
