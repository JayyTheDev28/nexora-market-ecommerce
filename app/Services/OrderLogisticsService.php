<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Notifications\OrderStatusUpdated;
use Illuminate\Support\Facades\DB;

class OrderLogisticsService
{
    /**
     * Ensure the order has a unique tracking number.
     */
    public static function ensureTrackingNumber(Order $order): string
    {
        if (! empty($order->tracking_number)) {
            return $order->tracking_number;
        }

        $tracking = 'NX-TRK-' . strtoupper(substr(md5($order->id . $order->order_number . microtime()), 0, 10));
        $order->update(['tracking_number' => $tracking]);

        return $tracking;
    }

    /**
     * Transition order status safely with audit logging and notifications.
     */
    public static function transition(
        Order $order,
        string $newStatus,
        ?int $changedBy = null,
        ?string $note = null,
        array $extraAttributes = []
    ): Order {
        return DB::transaction(function () use ($order, $newStatus, $changedBy, $note, $extraAttributes) {
            // Lock order row for update
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->firstOrFail();

            $attributes = array_merge([
                'status' => $newStatus,
            ], $extraAttributes);

            if (empty($lockedOrder->tracking_number)) {
                $attributes['tracking_number'] = 'NX-TRK-' . strtoupper(substr(md5($lockedOrder->id . $lockedOrder->order_number . microtime()), 0, 10));
            }

            $lockedOrder->update($attributes);

            OrderStatusHistory::create([
                'order_id' => $lockedOrder->id,
                'status' => $newStatus,
                'changed_by' => $changedBy,
                'note' => $note,
                'changed_at' => now(),
            ]);

            // Notify buyer via email
            if ($lockedOrder->buyer) {
                try {
                    $lockedOrder->buyer->notify(new OrderStatusUpdated($lockedOrder, $newStatus));
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            return $lockedOrder->fresh();
        });
    }
}
