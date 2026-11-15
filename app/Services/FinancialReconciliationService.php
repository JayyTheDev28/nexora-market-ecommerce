<?php

namespace App\Services;

use App\Models\CodCollection;
use App\Models\CodLedgerEntry;
use App\Models\CodRemittance;
use App\Models\CodRemittanceAllocation;
use App\Models\CourierPayout;
use App\Models\CourierProfile;
use App\Models\LogisticsAdminRemittance;
use App\Models\LogisticsRemittanceItem;
use App\Models\Order;
use App\Models\RiderEarning;
use App\Models\SellerSettlementAllocation;
use Illuminate\Support\Facades\DB;

class FinancialReconciliationService
{
    protected function toCents(float|string|int|null $v): int
    {
        return (int) round((float) ($v ?? 0) * 100);
    }

    /**
     * Run comprehensive cross-ledger integrity and balance checks.
     * Reports discrepancies clearly without silent auto-mutation.
     */
    public function runReconciliationReport(?int $logisticsCompanyId = null): array
    {
        $issues = [];

        // Check 1: Delivered COD orders missing CodCollection accountability record (when no legacy remitted path)
        $deliveredQuery = Order::where('payment_method', 'cod')
            ->whereIn('status', ['delivered', 'completed'])
            ->whereDoesntHave('codCollection');

        if ($logisticsCompanyId) {
            $deliveredQuery->where(function ($q) use ($logisticsCompanyId) {
                $q->where('delivery_logistics_id', $logisticsCompanyId)
                    ->orWhere(fn ($sub) => $sub->whereNull('delivery_logistics_id')->where('sorting_center_id', $logisticsCompanyId));
            });
        }
        foreach ($deliveredQuery->with(['remittanceItem.remittance', 'settlementAllocation'])->get() as $order) {
            $hasCourier = ! empty($order->delivery_courier_id);
            $hasLogistics = ! empty($order->delivery_logistics_id ?? $order->sorting_center_id);
            $hasLedger = CodLedgerEntry::where('order_id', $order->id)->exists();
            $hasAdminRemit = $order->remittanceItem !== null;
            $hasSellerAlloc = $order->settlementAllocation !== null;
            $hasCourierRemit = $hasCourier && CodRemittance::where('courier_id', $order->delivery_courier_id)->exists();

            $evidenceComplete = $hasCourier && $hasLogistics && ($hasLedger || $hasAdminRemit || $hasSellerAlloc);

            $issues[] = [
                'check' => 'missing_cod_collection_record',
                'severity' => 'warning',
                'classification' => $evidenceComplete ? 'backfill_candidate' : 'manual_review_required',
                'entity_type' => 'order',
                'entity_id' => $order->id,
                'reference' => $order->order_number,
                'cod_amount' => (float) $order->total,
                'payment_status' => $order->payment_status,
                'evidence' => [
                    'has_delivery_courier' => $hasCourier,
                    'has_logistics_company' => $hasLogistics,
                    'has_cod_ledger_entry' => $hasLedger,
                    'has_rider_remittance_history' => $hasCourierRemit,
                    'has_admin_remittance_item' => $hasAdminRemit,
                    'has_seller_allocation' => $hasSellerAlloc,
                ],
                'message' => $evidenceComplete
                    ? "Delivered COD order #{$order->order_number} (₱" . number_format((float) $order->total, 2) . ") lacks a CodCollection row but has verifiable custody history."
                    : "Pre-migration/historical delivered COD order #{$order->order_number} (₱" . number_format((float) $order->total, 2) . ", payment_status={$order->payment_status}) lacks a CodCollection row and has incomplete custody/cash remittance evidence — classified as manual_review_required.",
            ];
        }

        // Check 2: Rider unremitted_cod_balance vs sum(outstanding_amount on CodCollection)
        $profilesQuery = CourierProfile::with('user');
        if ($logisticsCompanyId) {
            $profilesQuery->where(fn ($q) => $q->where('logistics_company_id', $logisticsCompanyId)->orWhere('sorting_center_id', $logisticsCompanyId));
        }

        foreach ($profilesQuery->get() as $profile) {
            $hasCollections = CodCollection::where('courier_id', $profile->user_id)->exists();
            if ($hasCollections) {
                $collectionOutstandingCents = $this->toCents(CodCollection::where('courier_id', $profile->user_id)->sum('outstanding_amount'));
                $profileBalanceCents = $this->toCents($profile->unremitted_cod_balance);
                if (abs($collectionOutstandingCents - $profileBalanceCents) > 1) {
                    $issues[] = [
                        'check' => 'cod_balance_mismatch',
                        'severity' => 'critical',
                        'entity_type' => 'courier',
                        'entity_id' => $profile->user_id,
                        'reference' => $profile->user?->full_name ?? "Courier #{$profile->user_id}",
                        'message' => 'Courier profile unremitted_cod_balance (₱' . number_format($profileBalanceCents / 100, 2) . ') differs from open CodCollection outstanding sum (₱' . number_format($collectionOutstandingCents / 100, 2) . ').',
                    ];
                }
            }

            // Check 7: Courier earnings_balance vs (earned/approved/paid earnings - paid payouts)
            $earnedCents = $this->toCents(
                RiderEarning::where('courier_id', $profile->user_id)
                    ->whereIn('status', [RiderEarning::STATUS_EARNED, RiderEarning::STATUS_APPROVED_FOR_PAYOUT, RiderEarning::STATUS_PAID])
                    ->sum('amount')
            );
            $paidCents = $this->toCents(
                CourierPayout::where('courier_id', $profile->user_id)
                    ->where('status', CourierPayout::STATUS_PAID)
                    ->sum('amount')
            );
            $expectedEarningsBalance = max(0, $earnedCents - $paidCents);
            $actualEarningsBalance = $this->toCents($profile->earnings_balance);
            if ($earnedCents > 0 && abs($expectedEarningsBalance - $actualEarningsBalance) > 1) {
                $issues[] = [
                    'check' => 'courier_earnings_balance_mismatch',
                    'severity' => 'critical',
                    'entity_type' => 'courier',
                    'entity_id' => $profile->user_id,
                    'reference' => $profile->user?->full_name ?? "Courier #{$profile->user_id}",
                    'message' => 'Courier earnings_balance (₱' . number_format($actualEarningsBalance / 100, 2) . ') does not match ledger net (₱' . number_format($expectedEarningsBalance / 100, 2) . ').',
                ];
            }
        }

        // Check 3: Overlapping pending/verified allocations exceeding cod_amount_due
        $overAllocated = CodRemittanceAllocation::select('cod_collection_id', DB::raw('SUM(allocated_amount) as sum_alloc'))
            ->whereIn('status', [
                CodRemittanceAllocation::STATUS_SUBMITTED,
                CodRemittanceAllocation::STATUS_VERIFIED,
                CodRemittanceAllocation::STATUS_PARTIALLY_VERIFIED,
            ])
            ->groupBy('cod_collection_id')
            ->get();

        foreach ($overAllocated as $row) {
            $col = CodCollection::find($row->cod_collection_id);
            if ($col && $this->toCents($row->sum_alloc) > $this->toCents($col->cod_amount_due)) {
                $issues[] = [
                    'check' => 'cod_over_allocation',
                    'severity' => 'critical',
                    'entity_type' => 'cod_collection',
                    'entity_id' => $col->id,
                    'reference' => "Order #{$col->order_id}",
                    'message' => "Allocated remittances (₱{$row->sum_alloc}) exceed COD amount due (₱{$col->cod_amount_due}).",
                ];
            }
        }

        // Check 4, 5 & 6: Logistics-to-Admin Remittance & Seller Settlement integrity
        $adminRemitsQuery = LogisticsAdminRemittance::with(['items.order.codCollection', 'allocations']);
        if ($logisticsCompanyId) {
            $adminRemitsQuery->where('logistics_id', $logisticsCompanyId);
        }

        foreach ($adminRemitsQuery->get() as $batch) {
            $itemsSumCents = $this->toCents($batch->items->sum('merchandise_component'));
            $batchMerchCents = $this->toCents($batch->merchandise_amount);
            if ($batch->items->isNotEmpty() && abs($itemsSumCents - $batchMerchCents) > 1) {
                $issues[] = [
                    'check' => 'admin_remittance_batch_sum_mismatch',
                    'severity' => 'critical',
                    'entity_type' => 'logistics_admin_remittance',
                    'entity_id' => $batch->id,
                    'reference' => $batch->reference_number,
                    'message' => "Batch header merchandise_amount (₱{$batch->merchandise_amount}) does not equal sum of itemized items.",
                ];
            }

            foreach ($batch->items as $item) {
                $col = $item->order?->codCollection;
                if ($batch->status !== 'rejected' && $col && ! $col->isFullyVerifiedForAdminRemittance()) {
                    $issues[] = [
                        'check' => 'unverified_rider_cash_in_admin_remit',
                        'severity' => 'critical',
                        'entity_type' => 'order',
                        'entity_id' => $item->order_id,
                        'reference' => $batch->reference_number,
                        'message' => "Batch {$batch->reference_number} includes order #{$item->order_id} whose Rider COD collection status is '{$col->status}'.",
                    ];
                }

                if ($batch->status === 'verified') {
                    $ssa = SellerSettlementAllocation::where('order_id', $item->order_id)->first();
                    if (! $ssa) {
                        $issues[] = [
                            'check' => 'missing_seller_allocation',
                            'severity' => 'critical',
                            'entity_type' => 'order',
                            'entity_id' => $item->order_id,
                            'reference' => $batch->reference_number,
                            'message' => "Verified batch {$batch->reference_number} order #{$item->order_id} is missing a SellerSettlementAllocation.",
                        ];
                    } else {
                        $sumSplitCents = $this->toCents($ssa->platform_commission) + $this->toCents($ssa->seller_payable);
                        $eligibleCents = $this->toCents($ssa->eligible_merchandise_amount);
                        if (abs($sumSplitCents - $eligibleCents) > 1) {
                            $issues[] = [
                                'check' => 'seller_commission_split_mismatch',
                                'severity' => 'critical',
                                'entity_type' => 'seller_settlement_allocation',
                                'entity_id' => $ssa->id,
                                'reference' => "Order #{$ssa->order_id}",
                                'message' => 'Commission + seller payable does not equal eligible merchandise amount.',
                            ];
                        }
                    }
                }
            }
        }

        // Check 8: Duplicate courier earnings on same order+type or manifest+courier
        $dupOrderEarnings = RiderEarning::select('order_id', 'type', 'courier_id', DB::raw('COUNT(*) as cnt'))
            ->whereNotNull('order_id')
            ->where('status', '!=', 'reversed')
            ->groupBy('order_id', 'type', 'courier_id')
            ->having('cnt', '>', 1)
            ->get();

        foreach ($dupOrderEarnings as $dup) {
            $issues[] = [
                'check' => 'duplicate_order_earning',
                'severity' => 'critical',
                'entity_type' => 'order',
                'entity_id' => $dup->order_id,
                'reference' => "Order #{$dup->order_id} ({$dup->type})",
                'message' => "Detected {$dup->cnt} active earnings of type '{$dup->type}' for courier #{$dup->courier_id}.",
            ];
        }

        $dupManifestEarnings = RiderEarning::select('manifest_id', 'courier_id', DB::raw('COUNT(*) as cnt'))
            ->whereNotNull('manifest_id')
            ->where('status', '!=', 'reversed')
            ->groupBy('manifest_id', 'courier_id')
            ->having('cnt', '>', 1)
            ->get();

        foreach ($dupManifestEarnings as $dup) {
            $issues[] = [
                'check' => 'duplicate_manifest_earning',
                'severity' => 'critical',
                'entity_type' => 'transfer_manifest',
                'entity_id' => $dup->manifest_id,
                'reference' => "Manifest #{$dup->manifest_id}",
                'message' => "Detected {$dup->cnt} active transfer trip earnings for courier #{$dup->courier_id}.",
            ];
        }

        $criticalCount = collect($issues)->where('severity', 'critical')->count();
        $warningCount = collect($issues)->where('severity', 'warning')->count();
        $manualReviewCount = collect($issues)->where('classification', 'manual_review_required')->count();

        return [
            'checked_at' => now()->toIso8601String(),
            'logistics_company_id' => $logisticsCompanyId,
            'status' => count($issues) === 0 ? 'balanced' : ($criticalCount > 0 ? 'critical_discrepancies' : 'warnings_detected'),
            'is_balanced' => count($issues) === 0,
            'critical_count' => $criticalCount,
            'warning_count' => $warningCount,
            'manual_review_count' => $manualReviewCount,
            'issues' => $issues,
        ];
    }
}
