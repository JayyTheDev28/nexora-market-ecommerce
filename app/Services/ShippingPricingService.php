<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\InterCompanyShippingAllocation;
use App\Models\Order;
use App\Models\ShippingRatePolicy;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ShippingPricingService
{
    public const DEFAULT_BASE_FEE = 50.00;
    public const DEFAULT_BASE_WEIGHT_KG = 1.00;
    public const DEFAULT_PER_ADDITIONAL_KG_FEE = 20.00;

    public function ensureDefaultPolicy(): ShippingRatePolicy
    {
        $existing = ShippingRatePolicy::whereNull('logistics_company_id')
            ->where('status', ShippingRatePolicy::STATUS_ACTIVE)
            ->orderByDesc('version')
            ->first();

        if ($existing) {
            return $existing;
        }

        return ShippingRatePolicy::create([
            'name' => 'Standard Weight-Tiered Shipping Rate Policy',
            'logistics_company_id' => null,
            'version' => 1,
            'status' => ShippingRatePolicy::STATUS_ACTIVE,
            'review_status' => ShippingRatePolicy::REVIEW_PENDING,
            'contract_reference' => 'DRAFT-ASSUMPTION-35-20-45',
            'base_fee' => self::DEFAULT_BASE_FEE,
            'base_weight_kg' => self::DEFAULT_BASE_WEIGHT_KG,
            'per_additional_kg_fee' => self::DEFAULT_PER_ADDITIONAL_KG_FEE,
            'minimum_fee' => self::DEFAULT_BASE_FEE,
            'origin_logistics_share_pct' => 35.00,
            'transfer_carrier_share_pct' => 20.00,
            'delivery_logistics_share_pct' => 45.00,
            'platform_shipping_commission_pct' => 0.00,
            'effective_from' => now(),
        ]);
    }

    public function getActivePolicy(?int $logisticsCompanyId = null): ShippingRatePolicy
    {
        $this->ensureDefaultPolicy();

        if ($logisticsCompanyId) {
            $companyPolicy = ShippingRatePolicy::where('logistics_company_id', $logisticsCompanyId)
                ->where('status', ShippingRatePolicy::STATUS_ACTIVE)
                ->orderByDesc('version')
                ->orderByDesc('id')
                ->first();

            if ($companyPolicy) {
                return $companyPolicy;
            }
        }

        return ShippingRatePolicy::whereNull('logistics_company_id')
            ->where('status', ShippingRatePolicy::STATUS_ACTIVE)
            ->orderByDesc('version')
            ->orderByDesc('id')
            ->firstOrFail();
    }

    /**
     * Calculate shipping fee using active ShippingRatePolicy while preserving ₱50 first kg + ₱20/kg default.
     */
    public function calculateForWeight(float $rawWeightKg, ?int $logisticsCompanyId = null): array
    {
        $policy = $this->getActivePolicy($logisticsCompanyId);
        $billedWeightKg = max(1, (int) ceil($rawWeightKg));
        $baseWeightInt = max(1, (int) ceil((float) $policy->base_weight_kg));
        $additionalKg = max(0, $billedWeightKg - $baseWeightInt);

        $fee = max(
            (float) $policy->minimum_fee,
            round((float) $policy->base_fee + ((float) $policy->per_additional_kg_fee * $additionalKg), 2)
        );

        return [
            'policy' => $policy,
            'billed_weight_kg' => $billedWeightKg,
            'shipping_fee' => $fee,
        ];
    }

    public function approvePolicy(
        ShippingRatePolicy $policy,
        User $admin,
        string $contractReference,
        ?string $notes = null,
        bool $activateNow = false
    ): ShippingRatePolicy {
        abort_unless($admin->isAdmin(), 403, 'Only Platform Admins may approve shipping revenue-sharing policies.');

        return DB::transaction(function () use ($policy, $admin, $contractReference, $notes, $activateNow) {
            $old = $policy->only(['review_status', 'contract_reference', 'status']);
            $updates = [
                'review_status' => ShippingRatePolicy::REVIEW_APPROVED,
                'contract_reference' => $contractReference,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'review_notes' => $notes,
            ];

            if ($activateNow) {
                ShippingRatePolicy::where('logistics_company_id', $policy->logistics_company_id)
                    ->where('id', '!=', $policy->id)
                    ->where('status', ShippingRatePolicy::STATUS_ACTIVE)
                    ->update([
                        'status' => ShippingRatePolicy::STATUS_ARCHIVED,
                        'effective_until' => now(),
                    ]);
                $updates['status'] = ShippingRatePolicy::STATUS_ACTIVE;
                $updates['effective_from'] = $policy->effective_from ?? now();
            }

            $policy->update($updates);

            AuditLog::log('shipping_policy_admin_approved', $policy, $old, $policy->fresh()->toArray(), "Admin approved shipping policy under contract {$contractReference}", $admin->id);

            return $policy->fresh();
        });
    }

    public function rejectPolicy(ShippingRatePolicy $policy, User $admin, string $notes): ShippingRatePolicy
    {
        abort_unless($admin->isAdmin(), 403, 'Only Platform Admins may reject shipping revenue-sharing policies.');

        $old = $policy->only(['review_status', 'status']);
        $policy->update([
            'review_status' => ShippingRatePolicy::REVIEW_REJECTED,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ]);

        AuditLog::log('shipping_policy_admin_rejected', $policy, $old, $policy->only(['review_status', 'review_notes']), $notes, $admin->id);

        return $policy->fresh();
    }

    /**
     * Allocate recognized shipping revenue across pickup, transfer, and delivery logistics companies.
     * Supports Scenario A (single company: 100% self_retained), Scenario B (2 companies: no phantom 20% transfer share),
     * and Scenario C (3 distinct companies: uses Admin-approved sharing contract percentages).
     */
    public function allocateInterCompanyShipping(Order $order): InterCompanyShippingAllocation
    {
        return DB::transaction(function () use ($order) {
            $existing = InterCompanyShippingAllocation::where('order_id', $order->id)->lockForUpdate()->first();
            if ($existing && in_array($existing->settlement_status, ['settled', 'self_retained'], true)) {
                return $existing;
            }

            $policy = $order->shipping_rate_policy_id
                ? (ShippingRatePolicy::find($order->shipping_rate_policy_id) ?? $this->getActivePolicy($order->delivery_logistics_id ?: $order->sorting_center_id))
                : $this->getActivePolicy($order->delivery_logistics_id ?: $order->sorting_center_id);

            $buyerPaid = round((float) $order->shipping_fee, 2);
            $subsidy = round((float) ($order->platform_shipping_subsidy ?? $order->shipping_voucher_discount ?? 0), 2);
            $baseShipping = round((float) ($order->base_shipping_fee ?? ($buyerPaid + $subsidy)), 2);

            $totalCents = (int) round($baseShipping * 100);

            $pickupCoId = $order->pickup_logistics_id ?: $order->sorting_center_id;
            $deliveryCoId = $order->delivery_logistics_id ?: $order->sorting_center_id;
            $collectingCoId = $deliveryCoId;

            $manifestItem = $order->manifestItems()
                ->with('manifest')
                ->whereHas('manifest', fn ($q) => $q->whereIn('status', ['completed', 'receiving', 'arrived', 'in_transit']))
                ->latest('id')
                ->first();

            $candidateTransferCoId = $manifestItem?->manifest?->sender_logistics_id
                ?? $order->transfers()->whereIn('status', ['dispatched', 'in_transit', 'received', 'completed'])->latest('id')->value('sender_logistics_id');

            // Never double-count Pickup Co or Delivery Co as a separate 3rd-party Transfer Carrier,
            // and never pay a hub just because a parcel passed through without a third contracted carrier.
            $transferCoId = ($candidateTransferCoId
                && (int) $candidateTransferCoId !== (int) $pickupCoId
                && (int) $candidateTransferCoId !== (int) $deliveryCoId)
                ? (int) $candidateTransferCoId
                : null;

            $isSingleCarrier = (! $pickupCoId || (int) $pickupCoId === (int) $deliveryCoId) && ! $transferCoId;
            $policyApproved = $policy->isApprovedForSharing($order->delivered_at ?? $order->placed_at ?? now());

            if ($isSingleCarrier || ! $pickupCoId || ! $deliveryCoId) {
                // Scenario A: Same Logistics Company retains 100% with zero self-payables
                $pickupCoId = $deliveryCoId;
                $transferCoId = null;
                $pickupShareCents = 0;
                $transferShareCents = 0;
                $deliveryShareCents = $totalCents;
                $allocationMode = 'single_carrier';
                $settlementStatus = 'self_retained';
            } elseif (! $transferCoId) {
                // Scenario B: Two companies (Pickup Co A -> Delivery Co B), zero unallocated transfer share
                $originPct = (float) $policy->origin_logistics_share_pct;
                $deliveryPct = (float) $policy->delivery_logistics_share_pct;
                $denom = max(1.0, $originPct + $deliveryPct);
                $pickupShareCents = (int) round($totalCents * ($originPct / $denom));
                $transferShareCents = 0;
                $deliveryShareCents = $totalCents - $pickupShareCents;
                $allocationMode = 'two_company';
                $settlementStatus = $policyApproved ? 'allocated' : 'pending_policy_approval';
            } elseif (! $policyApproved) {
                // Scenario C with unapproved/draft percentages: hold sharing payout until Admin approves contract
                $pickupShareCents = 0;
                $transferShareCents = 0;
                $deliveryShareCents = $totalCents;
                $allocationMode = 'pending_contract_approval';
                $settlementStatus = 'pending_policy_approval';
            } else {
                // Scenario C with Admin-approved multi-carrier contract
                $pickupShareCents = (int) round($totalCents * ((float) $policy->origin_logistics_share_pct / 100));
                $transferShareCents = (int) round($totalCents * ((float) $policy->transfer_carrier_share_pct / 100));
                $deliveryShareCents = $totalCents - $pickupShareCents - $transferShareCents;
                $allocationMode = 'three_company_contract';
                $settlementStatus = 'allocated';
            }

            $allocation = InterCompanyShippingAllocation::updateOrCreate(
                ['order_id' => $order->id],
                [
                    'shipping_rate_policy_id' => $policy->id,
                    'total_shipping_fee' => round($totalCents / 100, 2),
                    'buyer_paid_shipping' => $buyerPaid,
                    'platform_shipping_subsidy' => $subsidy,
                    'pickup_logistics_id' => $pickupCoId,
                    'pickup_share_amount' => round($pickupShareCents / 100, 2),
                    'transfer_logistics_id' => $transferCoId,
                    'transfer_share_amount' => round($transferShareCents / 100, 2),
                    'delivery_logistics_id' => $deliveryCoId,
                    'delivery_share_amount' => round($deliveryShareCents / 100, 2),
                    'collecting_logistics_id' => $collectingCoId,
                    'allocation_mode' => $allocationMode,
                    'contract_reference' => $policy->contract_reference,
                    'settlement_status' => $settlementStatus,
                    'breakdown' => [
                        'policy_id' => $policy->id,
                        'policy_name' => $policy->name,
                        'policy_version' => $policy->version,
                        'policy_review_status' => $policy->review_status,
                        'contract_reference' => $policy->contract_reference,
                        'effective_from' => $policy->effective_from?->toIso8601String(),
                        'effective_until' => $policy->effective_until?->toIso8601String(),
                        'allocation_mode' => $allocationMode,
                        'single_carrier' => $isSingleCarrier,
                        'origin_pct' => (float) $policy->origin_logistics_share_pct,
                        'transfer_pct' => (float) $policy->transfer_carrier_share_pct,
                        'delivery_pct' => (float) $policy->delivery_logistics_share_pct,
                    ],
                ]
            );

            AuditLog::log('shipping_allocation_computed', $allocation, null, $allocation->toArray(), "Allocated shipping revenue for order {$order->order_number}", $collectingCoId);

            return $allocation;
        });
    }

    public function settleAllocation(InterCompanyShippingAllocation $allocation, User $actor): InterCompanyShippingAllocation
    {
        $companyId = $actor->logisticsCompanyId();
        abort_unless(
            $actor->isAdmin()
            || (int) $allocation->collecting_logistics_id === $companyId
            || (int) $allocation->pickup_logistics_id === $companyId
            || (int) $allocation->delivery_logistics_id === $companyId,
            403
        );
        abort_unless(
            $allocation->settlement_status === 'allocated',
            422,
            'Only approved multi-company allocations can be marked as settled.'
        );

        $allocation->update([
            'settlement_status' => 'settled',
            'settled_at' => now(),
        ]);

        AuditLog::log('inter_company_shipping_settled', $allocation, ['settlement_status' => 'allocated'], ['settlement_status' => 'settled'], 'Inter-company shipping settlement confirmed', $actor->id);

        return $allocation->fresh();
    }
}
