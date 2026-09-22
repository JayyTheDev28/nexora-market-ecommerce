<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The ERP flow has two separate rider stages, which can be handled by
     * different people:
     *   1. Pickup  — seller -> sorting center
     *   2. Delivery — sorting center -> buyer
     * A single courier_id can't represent both, so it's split here.
     *
     * Also adds delivery_attempts for the "DELIVERY_FAILED -> Reason
     * Recorded -> Reschedule Delivery / Return Parcel" branch.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('courier_id');

            $table->foreignId('pickup_courier_id')->nullable()->after('total')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('delivery_courier_id')->nullable()->after('pickup_courier_id')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('delivery_area_id')->nullable()->after('sorting_center_id')
                ->constrained('delivery_areas')->nullOnDelete();

            $table->string('tracking_number')->nullable()->unique()->after('order_number');
        });

        Schema::create('delivery_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('courier_id')->constrained('users')->cascadeOnDelete();
            $table->enum('result', ['delivered', 'failed']);
            $table->string('failure_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('attempted_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_attempts');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pickup_courier_id');
            $table->dropConstrainedForeignId('delivery_courier_id');
            $table->dropConstrainedForeignId('delivery_area_id');
            $table->dropColumn('tracking_number');

            $table->foreignId('courier_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }
};
