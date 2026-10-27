<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use InvalidArgumentException;

class ShipmentStateMachineService
{
    /**
     * Allowed forward status transitions.
     */
    protected const VALID_TRANSITIONS = [
        'ready_for_pickup' => ['assigned', 'picked_up'],
        'assigned' => ['picked_up', 'ready_for_pickup'], // ready_for_pickup if unassigned/rider suspended
        'picked_up' => ['at_hub', 'sorted'],
        'at_hub' => ['sorted', 'assigned_to_rider', 'out_for_delivery'],
        'sorted' => ['assigned_to_rider', 'out_for_delivery'],
        'assigned_to_rider' => ['out_for_delivery', 'at_hub', 'sorted'],
        'out_for_delivery' => ['delivered', 'failed_delivery', 'delivery_failed'],
        'failed_delivery' => ['out_for_delivery', 'return_to_sender'],
        'delivery_failed' => ['out_for_delivery', 'return_to_sender'],
        'return_to_sender' => ['returned_to_sender'],
    ];

    /**
     * Validate whether a status transition is permitted.
     */
    public function canTransition(string $currentStatus, string $nextStatus): bool
    {
        if ($currentStatus === $nextStatus) {
            return true;
        }

        $allowed = self::VALID_TRANSITIONS[$currentStatus] ?? [];
        return in_array($nextStatus, $allowed, true);
    }

    /**
     * Perform normal state transition.
     */
    public function transition(Order $order, string $newStatus, ?User $actor = null, ?string $note = null): Order
    {
        $currentStatus = $order->status;

        if ($currentStatus === $newStatus) {
            return $order;
        }

        if (! $this->canTransition($currentStatus, $newStatus)) {
            throw new InvalidArgumentException("Invalid state transition from '{$currentStatus}' to '{$newStatus}'.");
        }

        $updates = ['status' => $newStatus];

        if ($newStatus === 'out_for_delivery' && empty($order->delivery_otp)) {
            $updates['delivery_otp'] = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            $updates['delivery_otp_failed_attempts'] = 0;
            $updates['delivery_otp_locked_until'] = null;
        }

        if ($newStatus === 'delivered') {
            $updates['delivered_at'] = now();
        }

        $order->update($updates);

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'status' => $newStatus,
            'changed_by' => $actor?->id,
            'note' => $note,
            'changed_at' => now(),
        ]);

        return $order;
    }

    /**
     * Perform authorized Logistics manual override transition with mandatory AuditLog.
     */
    public function logisticsOverride(Order $order, string $newStatus, User $operator, string $reason): Order
    {
        if (empty(trim($reason))) {
            throw new InvalidArgumentException("A reason is mandatory for manual logistics status override.");
        }

        $oldStatus = $order->status;

        $updates = ['status' => $newStatus];
        if ($newStatus === 'delivered' && empty($order->delivered_at)) {
            $updates['delivered_at'] = now();
        }

        $order->update($updates);

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'status' => $newStatus,
            'changed_by' => $operator->id,
            'note' => "LOGISTICS MANUAL OVERRIDE: {$reason}",
            'changed_at' => now(),
        ]);

        AuditLog::log(
            action: 'order_status_manual_override',
            auditable: $order,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => $newStatus],
            reason: $reason,
            operatorId: $operator->id
        );

        return $order;
    }
}
