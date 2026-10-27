<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CodLedgerEntry;
use App\Models\CodRemittance;
use App\Models\Order;
use App\Models\RiderEarning;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RiderPayoutService
{
    /**
     * Credit ₱30.00 for completed pickup from seller.
     */
    public function creditPickupPay(User $courier, Order $order): ?RiderEarning
    {
        return DB::transaction(function () use ($courier, $order) {
            // Guard against duplicate pay
            $existing = RiderEarning::where('courier_id', $courier->id)
                ->where('order_id', $order->id)
                ->where('type', 'pickup')
                ->first();

            if ($existing) {
                return $existing;
            }

            $earning = RiderEarning::create([
                'courier_id' => $courier->id,
                'order_id' => $order->id,
                'type' => 'pickup',
                'amount' => 30.00,
                'description' => "Pickup completed for order {$order->order_number}",
                'earned_at' => now(),
            ]);

            if ($courier->courierProfile) {
                $courier->courierProfile->increment('earnings_balance', 30.00);
            }

            return $earning;
        });
    }

    /**
     * Credit ₱50.00 for successful delivery to buyer.
     */
    public function creditDeliveryPay(User $courier, Order $order): ?RiderEarning
    {
        return DB::transaction(function () use ($courier, $order) {
            // Guard against duplicate pay
            $existing = RiderEarning::where('courier_id', $courier->id)
                ->where('order_id', $order->id)
                ->where('type', 'delivery')
                ->first();

            if ($existing) {
                return $existing;
            }

            $earning = RiderEarning::create([
                'courier_id' => $courier->id,
                'order_id' => $order->id,
                'type' => 'delivery',
                'amount' => 50.00,
                'description' => "Delivery completed for order {$order->order_number}",
                'earned_at' => now(),
            ]);

            if ($courier->courierProfile) {
                $courier->courierProfile->increment('earnings_balance', 50.00);
            }

            return $earning;
        });
    }

    /**
     * Record COD collection on successful delivery: adds order total to unremitted COD balance.
     */
    public function recordCodCollection(User $courier, Order $order): ?CodLedgerEntry
    {
        if (strtolower($order->payment_method) !== 'cod') {
            return null;
        }

        return DB::transaction(function () use ($courier, $order) {
            // Guard against duplicate COD collection
            $existing = CodLedgerEntry::where('courier_id', $courier->id)
                ->where('order_id', $order->id)
                ->where('type', 'collected')
                ->first();

            if ($existing) {
                return $existing;
            }

            $profile = $courier->courierProfile;
            $newBalance = ($profile ? (float) $profile->unremitted_cod_balance : 0.0) + (float) $order->total;

            if ($profile) {
                $profile->update(['unremitted_cod_balance' => $newBalance]);
            }

            return CodLedgerEntry::create([
                'courier_id' => $courier->id,
                'order_id' => $order->id,
                'type' => 'collected',
                'amount' => (float) $order->total,
                'balance_after' => $newBalance,
                'description' => "COD collected for order {$order->order_number}",
            ]);
        });
    }

    /**
     * Submit EOD COD Remittance request to Logistics.
     */
    public function submitRemittance(User $courier, float $amount, ?string $notes = null): CodRemittance
    {
        $profile = $courier->courierProfile;
        $expectedAmount = $profile ? (float) $profile->unremitted_cod_balance : 0.00;
        $sortingCenterId = $profile?->sorting_center_id ?? $courier->id;

        $remittanceNumber = 'REM-' . date('Ymd') . '-' . Str::upper(Str::random(5));

        return CodRemittance::create([
            'remittance_number' => $remittanceNumber,
            'courier_id' => $courier->id,
            'sorting_center_id' => $sortingCenterId,
            'amount' => $amount,
            'expected_amount' => $expectedAmount,
            'status' => 'pending',
            'rider_notes' => $notes,
            'remitted_at' => now(),
        ]);
    }

    /**
     * Review / Approve / Flag COD Remittance by Logistics Operator.
     */
    public function reviewRemittance(
        CodRemittance $remittance,
        User $operator,
        string $decision,
        ?string $operatorNotes = null,
        ?string $reason = null
    ): CodRemittance {
        return DB::transaction(function () use ($remittance, $operator, $decision, $operatorNotes, $reason) {
            $oldStatus = $remittance->status;
            $courier = $remittance->courier;
            $profile = $courier->courierProfile;

            $status = match ($decision) {
                'approve' => 'approved',
                'flag', 'flag_discrepancy' => 'discrepancy_flagged',
                'reject' => 'rejected',
                default => 'pending',
            };

            if ($status === 'approved') {
                $currentBalance = $profile ? (float) $profile->unremitted_cod_balance : 0.00;
                $newBalance = max(0.00, $currentBalance - (float) $remittance->amount);

                if ($profile) {
                    $profile->update(['unremitted_cod_balance' => $newBalance]);
                }

                CodLedgerEntry::create([
                    'courier_id' => $courier->id,
                    'order_id' => null,
                    'type' => 'remitted',
                    'amount' => (float) $remittance->amount,
                    'balance_after' => $newBalance,
                    'description' => "COD Remittance approved: {$remittance->remittance_number}",
                ]);
            }

            $remittance->update([
                'status' => $status,
                'operator_notes' => $operatorNotes,
                'reviewed_by' => $operator->id,
                'reviewed_at' => now(),
            ]);

            // Audit Trail
            AuditLog::log(
                action: 'cod_remittance_' . $status,
                auditable: $remittance,
                oldValues: ['status' => $oldStatus],
                newValues: ['status' => $status, 'amount' => $remittance->amount],
                reason: $reason ?? $operatorNotes ?? 'Logistics remittance reconciliation review',
                operatorId: $operator->id
            );

            return $remittance;
        });
    }
}
