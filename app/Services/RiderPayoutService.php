<?php

namespace App\Services;

use App\Models\CodCollection;
use App\Models\CodRemittance;
use App\Models\CourierProfile;
use App\Models\Order;
use App\Models\ProofOfDelivery;
use App\Models\RiderEarning;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class RiderPayoutService
{
    public function __construct(
        protected CompensationPolicyService $policyService,
        protected CodCollectionService $codCollectionService,
    ) {}

    /**
     * Legacy parameter order preserved: creditPickupPay(User $courier, Order $order).
     */
    public function creditPickupPay(User $courier, Order $order, ?float $overrideAmount = null): RiderEarning
    {
        return DB::transaction(function () use ($order, $courier, $overrideAmount) {
            $earningKey = "order_{$order->id}_pickup_{$courier->id}";

            $existing = RiderEarning::where('order_id', $order->id)
                ->where('type', 'pickup')
                ->where('courier_id', $courier->id)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            $rateInfo = $this->policyService->resolveRiderJobRate($order, $courier, 'pickup', $overrideAmount);

            $earning = RiderEarning::create([
                'courier_id' => $courier->id,
                'logistics_company_id' => $rateInfo['company_id'],
                'order_id' => $order->id,
                'policy_id' => $rateInfo['policy']->id,
                'earning_key' => $earningKey,
                'type' => 'pickup',
                'amount' => $rateInfo['amount'],
                'status' => RiderEarning::STATUS_EARNED,
                'description' => "Pickup fee for order #{$order->order_number}",
                'rate_snapshot' => $rateInfo['snapshot'],
                'earned_at' => now(),
            ]);

            $this->incrementEarningsBalance($courier->id, $rateInfo['amount']);

            return $earning;
        });
    }

    public function creditPickupEarning(Order $order, User $courier, ?float $overrideAmount = null): RiderEarning
    {
        return $this->creditPickupPay($courier, $order, $overrideAmount);
    }

    /**
     * Legacy parameter order preserved: creditDeliveryPay(User $courier, Order $order).
     */
    public function creditDeliveryPay(User $courier, Order $order, ?float $overrideAmount = null): RiderEarning
    {
        return DB::transaction(function () use ($order, $courier, $overrideAmount) {
            $earningKey = "order_{$order->id}_delivery_{$courier->id}";

            $existing = RiderEarning::where('order_id', $order->id)
                ->where('type', 'delivery')
                ->where('courier_id', $courier->id)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            $rateInfo = $this->policyService->resolveRiderJobRate($order, $courier, 'delivery', $overrideAmount);

            $earning = RiderEarning::create([
                'courier_id' => $courier->id,
                'logistics_company_id' => $rateInfo['company_id'],
                'order_id' => $order->id,
                'policy_id' => $rateInfo['policy']->id,
                'earning_key' => $earningKey,
                'type' => 'delivery',
                'amount' => $rateInfo['amount'],
                'status' => RiderEarning::STATUS_EARNED,
                'description' => "Delivery fee for order #{$order->order_number}",
                'rate_snapshot' => $rateInfo['snapshot'],
                'earned_at' => now(),
            ]);

            $this->incrementEarningsBalance($courier->id, $rateInfo['amount']);

            return $earning;
        });
    }

    public function creditDeliveryEarning(Order $order, User $courier, ?float $overrideAmount = null): RiderEarning
    {
        return $this->creditDeliveryPay($courier, $order, $overrideAmount);
    }

    /**
     * Credit optional failed delivery attempt compensation when active policy configures failed_attempt_rate > 0.
     */
    public function creditFailedAttemptEarning(User $courier, Order $order, int $attemptNumber): ?RiderEarning
    {
        return DB::transaction(function () use ($order, $courier, $attemptNumber) {
            $rateInfo = $this->policyService->resolveRiderJobRate($order, $courier, 'failed_delivery');
            if ($rateInfo['amount'] <= 0) {
                return null;
            }

            $earningKey = "order_{$order->id}_failed_attempt_{$attemptNumber}_{$courier->id}";
            $existing = RiderEarning::where('earning_key', $earningKey)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }

            $earning = RiderEarning::create([
                'courier_id' => $courier->id,
                'logistics_company_id' => $rateInfo['company_id'],
                'order_id' => $order->id,
                'policy_id' => $rateInfo['policy']->id,
                'earning_key' => $earningKey,
                'type' => 'failed_delivery',
                'amount' => $rateInfo['amount'],
                'status' => RiderEarning::STATUS_EARNED,
                'description' => "Failed delivery attempt #{$attemptNumber} allowance for order #{$order->order_number}",
                'rate_snapshot' => $rateInfo['snapshot'],
                'earned_at' => now(),
            ]);

            $this->incrementEarningsBalance($courier->id, $rateInfo['amount']);

            return $earning;
        });
    }

    /**
     * Legacy parameter order preserved: recordCodCollection(User $courier, Order $order).
     */
    public function recordCodCollection(
        User $courier,
        Order $order,
        ?ProofOfDelivery $pod = null,
        ?float $collectedAmount = null,
        ?string $idempotencyKey = null
    ): ?CodCollection {
        return $this->codCollectionService->recordCollectionOnDelivery($order, $courier, $pod, $collectedAmount, $idempotencyKey);
    }

    /**
     * Legacy parameter order preserved: submitRemittance(User $courier, float $amount, ?string $notes = null).
     */
    public function submitRemittance(
        User $courier,
        float $amount,
        ?string $notes = null,
        array $options = [],
        ?UploadedFile $proofFile = null
    ): CodRemittance {
        return $this->codCollectionService->submitRemittance(
            $courier,
            array_merge($options, [
                'amount' => $amount,
                'notes' => $notes,
            ]),
            $proofFile
        );
    }

    /**
     * Legacy parameter order preserved: reviewRemittance(CodRemittance $remittance, User $operator, string $decision, ?string $operatorNotes = null).
     */
    public function reviewRemittance(
        CodRemittance $remittance,
        User $operator,
        string $decision,
        ?string $operatorNotes = null,
        array $options = []
    ): CodRemittance {
        return $this->codCollectionService->reviewRemittance(
            $remittance,
            $decision,
            $operator,
            array_merge($options, ['operator_notes' => $operatorNotes])
        );
    }

    protected function incrementEarningsBalance(int $courierId, float $amount): void
    {
        $profile = CourierProfile::where('user_id', $courierId)->lockForUpdate()->first();
        if (! $profile) {
            return;
        }

        $currentCents = (int) round((float) $profile->earnings_balance * 100);
        $deltaCents = (int) round($amount * 100);
        $profile->update(['earnings_balance' => round(($currentCents + $deltaCents) / 100, 2)]);
    }
}
