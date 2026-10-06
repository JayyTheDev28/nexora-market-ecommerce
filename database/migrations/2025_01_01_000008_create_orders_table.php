<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('buyer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('address_id')->constrained()->restrictOnDelete();

            // Full lifecycle per the ERP flow doc: buyer places, seller
            // prepares, rider picks up, sorting center sorts & reassigns,
            // rider delivers, buyer confirms.
            $table->enum('status', [
                'placed',
                'confirmed',
                'preparing',
                'ready_for_pickup',
                'picked_up',
                'at_sorting_center',
                'sorted',
                'assigned_to_rider',
                'out_for_delivery',
                'delivered',
                'completed',
                'delivery_failed',
                'returned',
            ])->default('placed');

            $table->enum('payment_method', ['cod', 'ewallet', 'card'])->default('cod');
            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('shipping_fee', 10, 2)->default(0);
            $table->decimal('total', 10, 2);

            $table->foreignId('courier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sorting_center_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('placed_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
