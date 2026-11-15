<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Notifications\OrderStatusUpdated;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderLogisticsService
{
    /**
     * Generate a globally unique, Code-128-friendly tracking number.
     */
    public static function generateUniqueTrackingNumber(): string
    {
        do {
            $candidate = 'NX-TRK-' . Str::upper(Str::random(10));
        } while (Order::where('tracking_number', $candidate)->exists());

        return $candidate;
    }

    /**
     * Ensure the order has a unique, permanent tracking number without overwriting existing values.
     */
    public static function ensureTrackingNumber(Order $order): string
    {
        $existing = trim((string) ($order->tracking_number ?? ''));
        if ($existing !== '') {
            return $existing;
        }

        return DB::transaction(function () use ($order) {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $lockedExisting = trim((string) ($lockedOrder->tracking_number ?? ''));
            if ($lockedExisting !== '') {
                return $lockedExisting;
            }

            for ($attempt = 0; $attempt < 5; $attempt++) {
                $tracking = self::generateUniqueTrackingNumber();
                try {
                    $lockedOrder->update(['tracking_number' => $tracking]);
                    $order->tracking_number = $tracking;

                    return $tracking;
                } catch (QueryException $e) {
                    if ($attempt === 4) {
                        throw $e;
                    }
                }
            }

            throw new \RuntimeException('Unable to assign unique tracking number.');
        });
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

            if (empty(trim((string) $lockedOrder->tracking_number))) {
                $attributes['tracking_number'] = self::generateUniqueTrackingNumber();
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
