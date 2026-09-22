<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();

            // Snapshot fields — product name/price at time of order, so
            // later edits to the product don't rewrite order history.
            $table->string('product_name');
            $table->decimal('price', 10, 2);
            $table->unsignedInteger('quantity');
            $table->json('variant')->nullable(); // { "color": "...", "size": "..." }

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
