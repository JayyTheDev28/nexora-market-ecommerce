<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            // Platform-wide vouchers have a null seller_id; seller-specific
            // vouchers are scoped to that seller's products only.
            $table->foreignId('seller_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('code')->unique();
            $table->enum('type', ['percent', 'flat']);
            $table->decimal('value', 10, 2);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
