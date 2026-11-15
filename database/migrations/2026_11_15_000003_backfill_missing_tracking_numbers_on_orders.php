<?php

use App\Models\Order;
use App\Services\OrderLogisticsService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Backfill any legacy orders missing a canonical tracking number without altering existing values.
     */
    public function up(): void
    {
        Order::query()
            ->where(function ($q) {
                $q->whereNull('tracking_number')
                  ->orWhere('tracking_number', '');
            })
            ->orderBy('id')
            ->chunkById(200, function ($orders) {
                foreach ($orders as $order) {
                    OrderLogisticsService::ensureTrackingNumber($order);
                }
            });
    }

    /**
     * Backfill is non-destructive and should not delete valid tracking numbers on rollback.
     */
    public function down(): void
    {
        // Intentionally left non-destructive so canonical tracking numbers are preserved.
    }
};
