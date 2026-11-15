<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CodCollection;
use App\Models\CodLedgerEntry;
use App\Models\CodRemittance;
use App\Models\CodRemittanceAllocation;
use App\Models\CourierProfile;
use App\Models\FinancialAdjustment;
use App\Models\Order;
use App\Models\ProofOfDelivery;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CodCollectionService
{
    public const PAYMENT_METHODS = ['physical_cash', 'gcash', 'maya', 'bank_transfer'];

    public function toCents(float|string|int|null $amount): int
    {
        return (int) round((float) ($amount ?? 0) * 100);
    }

    public function fromCents(int $cents): float
    {
        return round($cents / 100, 2);
    }

    /**
     * Resolve authoritative Rider COD summary + open order-level collections for mobile & web dashboards.
     */
    public function getRiderCodWalletSummary(User $courier): array
    {
        $profile = $courier->courierProfile()->first();
        $collections = CodCollection::with(['order:id,order_number,tracking_number,total,delivered_at', 'allocations' => function ($q) {
            $q->whereIn('status', [CodRemittanceAllocation::STATUS_SUBMITTED]);
        }])
            ->where('courier_id', $courier->id)
            ->orderByDesc('collected_at')
            ->get();

        $pendingRemittances = CodRemittance::where('courier_id', $courier->id)
            ->whereIn('status', CodRemittance::OPEN_STATUSES)
            ->orderByDesc('remitted_at')
            ->get();

        $pendingReservedCents = (int) $pendingRemittances->sum(fn ($r) => $this->toCents($r->claimed_amount ?? $r->amount));
        $totalCollectedCents = (int) $collections->sum(fn ($c) => $this->toCents($c->actual_collected_amount));
        $totalVerifiedRemittedCents = (int) $collections->sum(fn ($c) => $this->toCents($c->verified_remitted_amount));
        $totalShortageCents = (int) $collections->sum(fn ($c) => $this->toCents($c->shortage_amount));
        $profileUnremittedCents = $this->toCents($profile?->unremitted_cod_balance ?? 0);

        $openCollections = $collections->filter(fn (CodCollection $c) => $this->toCents($c->outstanding_amount) > 0)->map(function (CodCollection $c) {
            $reservedCents = (int) $c->allocations->sum(fn ($a) => $this->toCents($a->allocated_amount));
            $outstandingCents = $this->toCents($c->outstanding_amount);
            $availableToSubmitCents = max(0, $outstandingCents - $reservedCents);

            return [
                'id' => $c->id,
                'order_id' => $c->order_id,
                'order_number' => $c->order?->order_number,
                'tracking_number' => $c->order?->tracking_number,
                'cod_amount_due' => (float) $c->cod_amount_due,
                'actual_collected_amount' => (float) $c->actual_collected_amount,
                'verified_remitted_amount' => (float) $c->verified_remitted_amount,
                'outstanding_amount' => (float) $c->outstanding_amount,
                'pending_reserved_amount' => $this->fromCents($reservedCents),
                'available_to_submit_amount' => $this->fromCents($availableToSubmitCents),
                'shortage_amount' => (float) $c->shortage_amount,
                'status' => $c->status,
                'collected_at' => $c->collected_at?->toIso8601String(),
            ];
        })->values();

        return [
            'cod_on_hand_balance' => $this->fromCents($profileUnremittedCents),
            'pending_verification_amount' => $this->fromCents($pendingReservedCents),
            'available_for_new_remittance' => $this->fromCents(max(0, $profileUnremittedCents - $pendingReservedCents)),
            'total_collected_lifetime' => $this->fromCents($totalCollectedCents),
            'total_verified_remitted' => $this->fromCents($totalVerifiedRemittedCents),
            'total_shortage_flagged' => $this->fromCents($totalShortageCents),
            'open_collections' => $openCollections,
            'pending_remittances_count' => $pendingRemittances->count(),
        ];
    }

    /**
     * Record an immutable per-order COD collection entry when a rider delivers a COD order.
     */
    public function recordCollectionOnDelivery(
        Order $order,
        User $courier,
        ?ProofOfDelivery $pod = null,
        ?float $collectedAmount = null,
        ?string $idempotencyKey = null
    ): ?CodCollection {
        if ($order->payment_method !== 'cod') {
            return null;
        }

        return DB::transaction(function () use ($order, $courier, $pod, $collectedAmount, $idempotencyKey) {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            $existing = CodCollection::where('order_id', $lockedOrder->id)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }

            if ($idempotencyKey) {
                $byKey = CodCollection::where('idempotency_key', $idempotencyKey)->first();
                if ($byKey) {
                    return $byKey;
                }
            }

            $dueCents = $this->toCents($lockedOrder->total);
            $actualCents = $collectedAmount !== null ? $this->toCents($collectedAmount) : $dueCents;

            $collectingCompanyId = (int) (
                $lockedOrder->delivery_logistics_id
                ?? $lockedOrder->sorting_center_id
                ?? $courier->courierProfile?->logistics_company_id
                ?? $courier->courierProfile?->sorting_center_id
                ?? 0
            ) ?: null;

            $collection = CodCollection::create([
                'order_id' => $lockedOrder->id,
                'courier_id' => $courier->id,
                'collecting_logistics_id' => $collectingCompanyId,
                'delivery_hub_id' => $lockedOrder->destination_hub_id ?? $lockedOrder->current_hub_id ?? $courier->courierProfile?->assigned_hub_id,
                'proof_of_delivery_id' => $pod?->id,
                'cod_amount_due' => $this->fromCents($dueCents),
                'actual_collected_amount' => $this->fromCents($actualCents),
                'verified_remitted_amount' => 0.00,
                'outstanding_amount' => $this->fromCents($actualCents),
                'shortage_amount' => $dueCents > $actualCents ? $this->fromCents($dueCents - $actualCents) : 0.00,
                'overpayment_amount' => $actualCents > $dueCents ? $this->fromCents($actualCents - $dueCents) : 0.00,
                'status' => CodCollection::STATUS_COLLECTED,
                'idempotency_key' => $idempotencyKey,
                'collected_at' => $lockedOrder->delivered_at ?? now(),
            ]);

            $profile = CourierProfile::where('user_id', $courier->id)->lockForUpdate()->firstOrFail();
            $newBalanceCents = $this->toCents($profile->unremitted_cod_balance) + $actualCents;
            $newBalance = $this->fromCents($newBalanceCents);
            $profile->update(['unremitted_cod_balance' => $newBalance]);

            CodLedgerEntry::create([
                'courier_id' => $courier->id,
                'order_id' => $lockedOrder->id,
                'cod_collection_id' => $collection->id,
                'type' => 'collected',
                'amount' => $this->fromCents($actualCents),
                'balance_after' => $newBalance,
                'description' => "COD collected upon delivery of order #{$lockedOrder->order_number} (Tracking: {$lockedOrder->tracking_number})",
            ]);

            AuditLog::log(
                'cod_collection_recorded',
                $collection,
                null,
                $collection->toArray(),
                "COD cash recorded for delivered order {$lockedOrder->order_number}",
                $courier->id
            );

            app(ShippingPricingService::class)->allocateInterCompanyShipping($lockedOrder);

            return $collection;
        });
    }

    /**
     * Submit an itemized, non-overlapping Rider COD remittance to their authorized Logistics Company.
     */
    public function submitRemittance(User $courier, array $data, ?UploadedFile $proofFile = null): CodRemittance
    {
        $amountCents = $this->toCents($data['amount'] ?? 0);
        if ($amountCents <= 0) {
            throw ValidationException::withMessages(['amount' => 'Remittance amount must be greater than ₱0.00.']);
        }

        $paymentMethod = $data['payment_method'] ?? 'physical_cash';
        if (! in_array($paymentMethod, self::PAYMENT_METHODS, true)) {
            throw ValidationException::withMessages(['payment_method' => 'Unsupported remittance payment channel selected.']);
        }

        $idempotencyKey = $data['idempotency_key'] ?? null;
        if ($idempotencyKey) {
            $existing = CodRemittance::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing->load('allocations');
            }
        }

        // Digital external payments ALWAYS require reference, transfer date, and proof (no fake instant clearing)
        if ($paymentMethod !== 'physical_cash') {
            if (empty($data['external_reference'])) {
                throw ValidationException::withMessages(['external_reference' => 'External transaction reference number is required for digital remittance channels.']);
            }
            if (! $proofFile && empty($data['proof_path'])) {
                throw ValidationException::withMessages(['proof_file' => 'Payment receipt proof image/PDF is required for GCash, Maya, or Bank Transfer remittances.']);
            }
        }

        return DB::transaction(function () use ($courier, $data, $proofFile, $amountCents, $paymentMethod, $idempotencyKey) {
            $profile = CourierProfile::where('user_id', $courier->id)->lockForUpdate()->firstOrFail();

            $targetLogisticsId = (int) ($profile->logistics_company_id ?: $profile->sorting_center_id ?: 0);
            if ($targetLogisticsId <= 0) {
                throw ValidationException::withMessages(['sorting_center_id' => 'Your rider profile is not assigned to an active logistics company.']);
            }

            if (! empty($data['sorting_center_id']) && (int) $data['sorting_center_id'] !== $targetLogisticsId) {
                throw ValidationException::withMessages(['sorting_center_id' => 'You can only remit COD cash to your assigned logistics company.']);
            }

            $collections = CodCollection::where('courier_id', $courier->id)
                ->where('outstanding_amount', '>', 0)
                ->orderBy('collected_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $pendingReservedByCollection = CodRemittanceAllocation::whereIn('cod_collection_id', $collections->pluck('id'))
                ->where('status', CodRemittanceAllocation::STATUS_SUBMITTED)
                ->selectRaw('cod_collection_id, SUM(allocated_amount) as total_reserved')
                ->groupBy('cod_collection_id')
                ->pluck('total_reserved', 'cod_collection_id');

            $pendingTotalCents = (int) CodRemittance::where('courier_id', $courier->id)
                ->whereIn('status', CodRemittance::OPEN_STATUSES)
                ->lockForUpdate()
                ->sum(DB::raw('COALESCE(claimed_amount, amount) * 100'));

            $profileUnremittedCents = $this->toCents($profile->unremitted_cod_balance);
            $availableUnremittedCents = max(0, $profileUnremittedCents - $pendingTotalCents);

            if ($profileUnremittedCents <= 0) {
                throw ValidationException::withMessages(['amount' => 'You have no unremitted COD cash balance to submit.']);
            }

            $allocationsPlan = [];
            $expectedTotalCents = 0;

            if (! empty($data['order_ids']) && is_array($data['order_ids'])) {
                $requestedOrderIds = array_values(array_unique(array_map('intval', $data['order_ids'])));
                $matched = $collections->whereIn('order_id', $requestedOrderIds)->values();

                if ($matched->count() !== count($requestedOrderIds)) {
                    throw ValidationException::withMessages(['order_ids' => 'One or more selected COD orders are invalid, already verified, or belong to another rider.']);
                }

                $remainingToAllocate = $amountCents;
                foreach ($matched as $collection) {
                    $outstandingCents = $this->toCents($collection->outstanding_amount);
                    $alreadyReservedCents = $this->toCents($pendingReservedByCollection[$collection->id] ?? 0);
                    $freeCents = max(0, $outstandingCents - $alreadyReservedCents);

                    if ($freeCents <= 0) {
                        throw ValidationException::withMessages([
                            'order_ids' => "Order #{$collection->order_id} is already included in a pending remittance awaiting verification.",
                        ]);
                    }

                    $expectedTotalCents += $freeCents;
                    $allocCents = min($freeCents, max(0, $remainingToAllocate));
                    if ($allocCents <= 0 && count($requestedOrderIds) === 1) {
                        $allocCents = $amountCents;
                    }
                    $remainingToAllocate = max(0, $remainingToAllocate - $allocCents);
                    if ($allocCents > 0) {
                        $allocationsPlan[] = ['collection' => $collection, 'allocated_cents' => $allocCents];
                    }
                }
            } elseif ($collections->isNotEmpty()) {
                // FIFO auto-allocation across available unreserved open COD collections
                $remainingToAllocate = $amountCents;
                foreach ($collections as $collection) {
                    $outstandingCents = $this->toCents($collection->outstanding_amount);
                    $alreadyReservedCents = $this->toCents($pendingReservedByCollection[$collection->id] ?? 0);
                    $freeCents = max(0, $outstandingCents - $alreadyReservedCents);
                    if ($freeCents <= 0) {
                        continue;
                    }
                    $expectedTotalCents += $freeCents;
                    if ($remainingToAllocate > 0) {
                        $allocCents = min($freeCents, $remainingToAllocate);
                        $remainingToAllocate -= $allocCents;
                        $allocationsPlan[] = ['collection' => $collection, 'allocated_cents' => $allocCents];
                    }
                }

                if (empty($allocationsPlan) && $availableUnremittedCents <= 0) {
                    throw ValidationException::withMessages([
                        'amount' => 'All open COD collections already have pending remittances awaiting verification.',
                    ]);
                }
            } else {
                // Legacy balance without itemized CodCollection rows
                if ($amountCents > $availableUnremittedCents) {
                    throw ValidationException::withMessages([
                        'amount' => 'Remittance exceeds remaining unremitted COD balance after pending submissions.',
                    ]);
                }
                $expectedTotalCents = $availableUnremittedCents;
            }

            if ($collections->isNotEmpty() && $expectedTotalCents === 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Selected COD collections have already been submitted in another pending remittance.',
                ]);
            }

            $proofPath = $data['proof_path'] ?? null;
            if ($proofFile) {
                $proofPath = $proofFile->store('remittance-proofs/riders', 'public');
            }

            $expectedFinalCents = $expectedTotalCents > 0 ? $expectedTotalCents : $availableUnremittedCents;

            $remittance = CodRemittance::create([
                'remittance_number' => 'REMIT-' . strtoupper(Str::random(10)),
                'courier_id' => $courier->id,
                'sorting_center_id' => $targetLogisticsId,
                'collecting_hub_id' => $data['collecting_hub_id'] ?? $profile->assigned_hub_id,
                'amount' => $this->fromCents($amountCents),
                'claimed_amount' => $this->fromCents($amountCents),
                'expected_amount' => $this->fromCents($expectedFinalCents),
                'payment_method' => $paymentMethod,
                'external_reference' => $data['external_reference'] ?? null,
                'transfer_date' => $data['transfer_date'] ?? ($paymentMethod !== 'physical_cash' ? now() : null),
                'proof_path' => $proofPath,
                'status' => CodRemittance::STATUS_SUBMITTED,
                'rider_notes' => $data['notes'] ?? null,
                'remitted_at' => now(),
                'idempotency_key' => $idempotencyKey,
            ]);

            foreach ($allocationsPlan as $item) {
                /** @var CodCollection $col */
                $col = $item['collection'];
                CodRemittanceAllocation::create([
                    'cod_remittance_id' => $remittance->id,
                    'cod_collection_id' => $col->id,
                    'order_id' => $col->order_id,
                    'allocated_amount' => $this->fromCents($item['allocated_cents']),
                    'status' => CodRemittanceAllocation::STATUS_SUBMITTED,
                ]);
                if ($col->status === CodCollection::STATUS_COLLECTED) {
                    $col->update(['status' => CodCollection::STATUS_SUBMITTED]);
                }
            }

            AuditLog::log(
                'cod_remittance_submitted',
                $remittance,
                null,
                [
                    'courier_id' => $courier->id,
                    'amount' => $this->fromCents($amountCents),
                    'expected_amount' => $this->fromCents($expectedFinalCents),
                    'payment_method' => $paymentMethod,
                    'order_count' => count($allocationsPlan),
                ],
                $data['notes'] ?? null,
                $courier->id
            );

            return $remittance->load('allocations.order');
        });
    }

    /**
     * Logistics Company Cash Verification & Reconciliation (Atomic, Locked, Idempotent, Partial/Shortage/Overpayment aware).
     */
    public function reviewRemittance(
        CodRemittance $remittance,
        string $action,
        User $operator,
        array $options = []
    ): CodRemittance {
        $normalizedAction = match ($action) {
            'approve', 'verify_full' => 'verify_full',
            'verify_partial' => 'verify_partial',
            'under_review', 'mark_under_review' => 'under_review',
            'flag', 'flag_discrepancy' => 'flag',
            'reject' => 'reject',
            default => $action,
        };

        return DB::transaction(function () use ($remittance, $normalizedAction, $operator, $options) {
            $locked = CodRemittance::whereKey($remittance->id)->lockForUpdate()->firstOrFail();

            if (! $operator->isAdmin()) {
                $companyId = $operator->logisticsCompanyId();
                if ((int) $locked->sorting_center_id !== $companyId) {
                    throw ValidationException::withMessages([
                        'remittance' => 'Unauthorized: this COD remittance belongs to another logistics company.',
                    ]);
                }
            }

            if ($locked->isTerminal()) {
                throw ValidationException::withMessages([
                    'status' => 'This remittance has already been finalized and cannot be re-processed.',
                ]);
            }

            if ($normalizedAction === 'under_review') {
                $locked->update([
                    'status' => CodRemittance::STATUS_UNDER_REVIEW,
                    'operator_notes' => $options['operator_notes'] ?? $locked->operator_notes,
                    'reviewed_by' => $operator->id,
                ]);
                AuditLog::log('cod_remittance_under_review', $locked, null, ['status' => CodRemittance::STATUS_UNDER_REVIEW], $options['operator_notes'] ?? null, $operator->id);

                return $locked->fresh('allocations.order');
            }

            if ($normalizedAction === 'reject') {
                $reason = trim((string) ($options['operator_notes'] ?? $options['discrepancy_reason'] ?? ''));
                if ($reason === '') {
                    throw ValidationException::withMessages(['operator_notes' => 'Rejection reason is required when rejecting a remittance.']);
                }

                $oldStatus = $locked->status;
                $locked->update([
                    'status' => CodRemittance::STATUS_REJECTED,
                    'operator_notes' => $reason,
                    'discrepancy_reason' => $reason,
                    'reviewed_by' => $operator->id,
                    'reviewed_at' => now(),
                ]);

                // Release allocations and revert unverified collections back so Rider can resubmit
                $allocations = CodRemittanceAllocation::where('cod_remittance_id', $locked->id)->lockForUpdate()->get();
                foreach ($allocations as $alloc) {
                    $alloc->update(['status' => CodRemittanceAllocation::STATUS_REJECTED]);
                    $col = CodCollection::whereKey($alloc->cod_collection_id)->lockForUpdate()->first();
                    if ($col) {
                        $verifiedCents = $this->toCents($col->verified_remitted_amount);
                        $col->update([
                            'status' => $verifiedCents > 0 ? CodCollection::STATUS_PARTIAL : CodCollection::STATUS_COLLECTED,
                        ]);
                    }
                }

                AuditLog::log('cod_remittance_rejected', $locked, ['status' => $oldStatus], ['status' => CodRemittance::STATUS_REJECTED, 'reason' => $reason], $reason, $operator->id);

                return $locked->fresh('allocations.order');
            }

            if ($normalizedAction === 'flag') {
                $reason = trim((string) ($options['discrepancy_reason'] ?? $options['operator_notes'] ?? ''));
                if ($reason === '') {
                    throw ValidationException::withMessages(['discrepancy_reason' => 'Please enter a reason before flagging a discrepancy.']);
                }
                $oldStatus = $locked->status;
                $locked->update([
                    'status' => CodRemittance::STATUS_DISCREPANCY_FLAGGED,
                    'operator_notes' => $options['operator_notes'] ?? $reason,
                    'discrepancy_reason' => $reason,
                    'reviewed_by' => $operator->id,
                    'reviewed_at' => now(),
                ]);

                CodRemittanceAllocation::where('cod_remittance_id', $locked->id)
                    ->update(['status' => CodRemittanceAllocation::STATUS_DISPUTED]);

                $colIds = CodRemittanceAllocation::where('cod_remittance_id', $locked->id)->pluck('cod_collection_id');
                if ($colIds->isNotEmpty()) {
                    CodCollection::whereIn('id', $colIds)->update(['status' => CodCollection::STATUS_DISPUTED]);
                }

                AuditLog::log('cod_remittance_flagged', $locked, ['status' => $oldStatus], ['status' => CodRemittance::STATUS_DISCREPANCY_FLAGGED, 'reason' => $reason], $reason, $operator->id);

                return $locked->fresh('allocations.order');
            }

            // verify_full or verify_partial
            $claimedCents = $this->toCents($locked->claimed_amount ?? $locked->amount);
            $actualCents = isset($options['actual_received_amount']) && $options['actual_received_amount'] !== '' && $options['actual_received_amount'] !== null
                ? $this->toCents($options['actual_received_amount'])
                : $claimedCents;

            if ($actualCents < 0) {
                throw ValidationException::withMessages(['actual_received_amount' => 'Actual received amount cannot be negative.']);
            }
            if ($normalizedAction === 'verify_partial' && $actualCents === 0) {
                throw ValidationException::withMessages(['actual_received_amount' => 'Partial verification requires an actual cash received amount greater than ₱0.00.']);
            }

            $allocations = CodRemittanceAllocation::where('cod_remittance_id', $locked->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $targetExpectedCents = $allocations->isNotEmpty()
                ? (int) $allocations->sum(fn ($a) => $this->toCents($a->allocated_amount))
                : $this->toCents($locked->expected_amount);

            $shortageCents = max(0, $targetExpectedCents - $actualCents);
            $overpayCents = max(0, $actualCents - $targetExpectedCents);

            if ($shortageCents > 0 && empty(trim((string) ($options['discrepancy_reason'] ?? $options['operator_notes'] ?? '')))) {
                if ($normalizedAction === 'verify_partial') {
                    throw ValidationException::withMessages(['discrepancy_reason' => 'Please enter a reason/explanation when recording a partial receipt or shortage.']);
                }
            }

            // Distribute verified cash across itemized collections (FIFO)
            $remainingCashCents = $actualCents;
            foreach ($allocations as $alloc) {
                /** @var CodCollection $col */
                $col = CodCollection::whereKey($alloc->cod_collection_id)->lockForUpdate()->firstOrFail();
                $dueCents = $this->toCents($col->cod_amount_due);
                $prevVerifiedCents = $this->toCents($col->verified_remitted_amount);
                $needCents = max(0, $dueCents - $prevVerifiedCents);

                $creditToColCents = min($needCents, $remainingCashCents);
                $remainingCashCents -= $creditToColCents;

                $newVerifiedCents = $prevVerifiedCents + $creditToColCents;
                $newOutstandingCents = max(0, $dueCents - $newVerifiedCents);
                $allocExpectedCents = $this->toCents($alloc->allocated_amount);
                $allocShortageCents = max(0, $allocExpectedCents - $creditToColCents);

                $colStatus = $newOutstandingCents === 0
                    ? CodCollection::STATUS_VERIFIED
                    : ($newVerifiedCents > 0 ? CodCollection::STATUS_PARTIAL : CodCollection::STATUS_SHORTAGE_FLAGGED);

                $col->update([
                    'verified_remitted_amount' => $this->fromCents($newVerifiedCents),
                    'outstanding_amount' => $this->fromCents($newOutstandingCents),
                    'shortage_amount' => $this->fromCents($newOutstandingCents > 0 && $allocShortageCents > 0 ? $allocShortageCents : 0),
                    'status' => $colStatus,
                    'verified_at' => $newOutstandingCents === 0 ? now() : $col->verified_at,
                ]);

                $alloc->update([
                    'verified_amount' => $this->fromCents($creditToColCents),
                    'shortage_amount' => $this->fromCents($allocShortageCents),
                    'status' => $newOutstandingCents === 0 ? CodRemittanceAllocation::STATUS_VERIFIED : CodRemittanceAllocation::STATUS_PARTIALLY_VERIFIED,
                ]);
            }

            // Decrement CourierProfile.unremitted_cod_balance ONLY by cash actually received (never credit fake shortage cash)
            $profile = CourierProfile::where('user_id', $locked->courier_id)->lockForUpdate()->firstOrFail();
            $currentUnremittedCents = $this->toCents($profile->unremitted_cod_balance);
            $deductCents = min($currentUnremittedCents, $actualCents);
            $newBalanceCents = max(0, $currentUnremittedCents - $deductCents);
            $newBalance = $this->fromCents($newBalanceCents);
            $profile->update(['unremitted_cod_balance' => $newBalance]);

            CodLedgerEntry::create([
                'courier_id' => $locked->courier_id,
                'cod_remittance_id' => $locked->id,
                'type' => 'remitted',
                'amount' => $this->fromCents($actualCents),
                'balance_after' => $newBalance,
                'description' => "COD Remittance {$locked->remittance_number} verified (₱" . number_format($this->fromCents($actualCents), 2) . ($shortageCents > 0 ? ', shortage ₱' . number_format($this->fromCents($shortageCents), 2) : '') . ')',
            ]);

            if ($overpayCents > 0) {
                FinancialAdjustment::create([
                    'reference_number' => 'ADJ-' . strtoupper(Str::random(10)),
                    'logistics_company_id' => $locked->sorting_center_id,
                    'courier_id' => $locked->courier_id,
                    'adjustment_type' => 'cod_overpayment_refund',
                    'pool' => 'cod_cash',
                    'amount' => $this->fromCents($overpayCents),
                    'status' => 'pending',
                    'reason' => "Overpayment of ₱" . number_format($this->fromCents($overpayCents), 2) . " received on remittance {$locked->remittance_number}",
                    'metadata' => ['cod_remittance_id' => $locked->id],
                    'created_by' => $operator->id,
                ]);
            }

            $finalStatus = ($shortageCents > 0 || $normalizedAction === 'verify_partial')
                ? CodRemittance::STATUS_PARTIALLY_VERIFIED
                : CodRemittance::STATUS_VERIFIED;

            $oldSnapshot = $locked->only(['status', 'actual_received_amount', 'shortage_amount']);
            $locked->update([
                'amount' => $this->fromCents($actualCents),
                'actual_received_amount' => $this->fromCents($actualCents),
                'shortage_amount' => $this->fromCents($shortageCents),
                'overpayment_amount' => $this->fromCents($overpayCents),
                'verification_reference' => $options['verification_reference'] ?? $locked->verification_reference,
                'status' => $finalStatus,
                'operator_notes' => $options['operator_notes'] ?? $locked->operator_notes,
                'discrepancy_reason' => $options['discrepancy_reason'] ?? $locked->discrepancy_reason,
                'reviewed_by' => $operator->id,
                'reviewed_at' => now(),
            ]);

            AuditLog::log(
                'cod_remittance_verified',
                $locked,
                $oldSnapshot,
                [
                    'status' => $finalStatus,
                    'actual_received_amount' => $this->fromCents($actualCents),
                    'shortage_amount' => $this->fromCents($shortageCents),
                    'overpayment_amount' => $this->fromCents($overpayCents),
                ],
                $options['operator_notes'] ?? null,
                $operator->id
            );

            return $locked->fresh('allocations.order');
        });
    }
}
