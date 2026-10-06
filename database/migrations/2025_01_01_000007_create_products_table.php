<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();

            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('compare_price', 10, 2)->nullable();
            $table->unsignedInteger('stock')->default(0);
            $table->enum('status', ['draft', 'active', 'archived'])->default('active');

            // Kept as JSON (matching App\Support\SampleCatalog's shape) rather
            // than a separate variants table for now — simple color/size
            // lists without per-variant stock/price. Revisit if the seller
            // dashboard needs per-variant inventory later.
            $table->json('gallery')->nullable();
            $table->json('colors')->nullable();
            $table->json('sizes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
