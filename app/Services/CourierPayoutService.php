<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CompensationPolicy;
use App\Models\CourierPayout;
use App\Models\CourierPayoutItem;
use App\Models\CourierProfile;
use App\Models\RiderEarning;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CourierPayoutService
{
    public const PAYOUT_METHODS = ['gcash', 'maya', 'bank_transfer', 'physical_cash'];
    public const RESERVED_STATUSES = [
        CourierPayout::STATUS_REQUESTED,
        CourierPayout::STATUS_UNDER_REVIEW,
        CourierPayout::STATUS_APPROVED,
        CourierPayout::STATUS_PAID,
    ];

    public function __construct(protected CompensationPolicyService $policyService) {}

    protected function toCents(float|string|int|null $value): int
    {
        return (int) round((float) ($value ?? 0) * 100);
    }

    protected function fromCents(int $cents): float
    {
        return round($cents / 100, 2);
    }

    /**
     * Unified real database-backed earnings & payout overview for Riders and Transfer Couriers.
     */
    public function getCourierEarningsOverview(User $courier): array
    {
        $profile = $courier->courierProfile;
        $companyId = $this->policyService->resolveCompanyIdForCourier($courier);
        $role = $courier->role === 'courier' ? CompensationPolicy::ROLE_COURIER : CompensationPolicy::ROLE_RIDER;
        $activePolicy = $this->policyService->getActivePolicy($role, $companyId);

        $lifetimeEarnedCents = $this->toCents(
            RiderEarning::where('courier_id', $courier->id)
                ->whereIn('status', [RiderEarning::STATUS_EARNED, RiderEarning::STATUS_APPROVED_FOR_PAYOUT, RiderEarning::STATUS_PAID])
                ->sum('amount')
        );

        $onHoldCents = $this->toCents(
            RiderEarning::where('courier_id', $courier->id)
                ->whereIn('status', [RiderEarning::STATUS_PENDING, RiderEarning::STATUS_ON_HOLD, RiderEarning::STATUS_DISPUTED])
                ->sum('amount')
        );

        $paidOutCents = $this->toCents(
            CourierPayout::where('courier_id', $courier->id)
                ->where('status', CourierPayout::STATUS_PAID)
                ->sum('amount')
        );

        $pendingPayoutCents = $this->toCents(
            CourierPayout::where('courier_id', $courier->id)
                ->whereIn('status', [
                    CourierPayout::STATUS_REQUESTED,
                    CourierPayout::STATUS_UNDER_REVIEW,
                    CourierPayout::STATUS_APPROVED,
                ])
                ->sum('amount')
        );

        $reservedTotalCents = $paidOutCents + $pendingPayoutCents;
        $availableForPayoutCents = max(0, $lifetimeEarnedCents - $reservedTotalCents);

        // Sync check against profile.earnings_balance (paid deductions happen on markPaid)
        $currentBalanceCents = max(0, $lifetimeEarnedCents - $paidOutCents);
        if ($profile && $this->toCents($profile->earnings_balance) === 0 && $currentBalanceCents > 0 && RiderEarning::where('courier_id', $courier->id)->exists()) {
            // Keep source-of-truth ledger accessible cleanly without silently mutating unexpected state
        }

        $todayPickups = RiderEarning::where('courier_id', $courier->id)->where('type', 'pickup')->where('earned_at', '>=', now()->startOfDay())->count();
        $todayDeliveries = RiderEarning::where('courier_id', $courier->id)->where('type', 'delivery')->where('earned_at', '>=', now()->startOfDay())->count();
        $todayTransferTrips = RiderEarning::where('courier_id', $courier->id)->where('type', 'transfer_trip')->where('earned_at', '>=', now()->startOfDay())->count();
        $todayPay = (float) RiderEarning::where('courier_id', $courier->id)->where('earned_at', '>=', now()->startOfDay())->sum('amount');
        $weeklyPay = (float) RiderEarning::where('courier_id', $courier->id)->where('earned_at', '>=', now()->startOfWeek())->sum('amount');

        $past7Days = [];
        for ($i = 6; $i >= 0; $i--) {
            $dayDate = now()->subDays($i);
            $dayStart = $dayDate->copy()->startOfDay();
            $dayEnd = $dayDate->copy()->endOfDay();
            $dayEarnings = RiderEarning::where('courier_id', $courier->id)
                ->whereBetween('earned_at', [$dayStart, $dayEnd]);
            $past7Days[] = [
                'date' => $dayDate->format('Y-m-d'),
                'day' => $dayDate->format('D'),
                'amount' => (float) $dayEarnings->sum('amount'),
                'trips' => (int) $dayEarnings->count(),
            ];
        }

        $recentEarnings = RiderEarning::where('courier_id', $courier->id)
            ->with(['order:id,order_number,tracking_number', 'manifest:id,manifest_number'])
            ->orderByDesc('earned_at')
            ->limit(50)
            ->get()
            ->map(function (RiderEarning $e) {
                return [
                    'id' => $e->id,
                    'type' => $e->type,
                    'amount' => (float) $e->amount,
                    'status' => $e->status ?? RiderEarning::STATUS_EARNED,
                    'description' => $e->description,
                    'order_id' => $e->order_id,
                    'order_number' => $e->order?->order_number,
                    'manifest_id' => $e->manifest_id,
                    'manifest_number' => $e->manifest?->manifest_number,
                    'rate_snapshot' => $e->rate_snapshot,
                    'earned_at' => $e->earned_at?->toIso8601String(),
                ];
            });

        $recentPayouts = CourierPayout::where('courier_id', $courier->id)
            ->orderByDesc('requested_at')
            ->limit(25)
            ->get()
            ->map(fn (CourierPayout $p) => [
                'id' => $p->id,
                'payout_number' => $p->payout_number,
                'amount' => (float) $p->amount,
                'payout_method' => $p->payout_method,
                'account_name' => $p->account_name,
                'account_number' => $p->account_number,
                'status' => $p->status,
                'external_reference' => $p->external_reference,
                'rejection_reason' => $p->rejection_reason,
                'requested_at' => $p->requested_at?->toIso8601String(),
                'approved_at' => $p->approved_at?->toIso8601String(),
                'paid_at' => $p->paid_at?->toIso8601String(),
            ]);

        return [
            'role' => $role,
            'earnings_balance' => (float) ($profile?->earnings_balance ?? $this->fromCents($currentBalanceCents)),
            'available_for_payout' => $this->fromCents($availableForPayoutCents),
            'pending_payout_amount' => $this->fromCents($pendingPayoutCents),
            'total_paid_out' => $this->fromCents($paidOutCents),
            'on_hold_amount' => $this->fromCents($onHoldCents),
            'total_earned_all_time' => $this->fromCents($lifetimeEarnedCents),
            'today_summary' => [
                'pickups' => $todayPickups,
                'deliveries' => $todayDeliveries,
                'transfer_trips' => $todayTransferTrips,
                'total_pay' => $todayPay,
            ],
            'weekly_total_pay' => $weeklyPay,
            'past_7_days' => $past7Days,
            'recent_earnings' => $recentEarnings,
            'recent_payouts' => $recentPayouts,
            'active_policy' => [
                'id' => $activePolicy->id,
                'name' => $activePolicy->name,
                'version' => $activePolicy->version,
                'pickup_rate' => (float) $activePolicy->pickup_rate,
                'delivery_rate' => (float) $activePolicy->delivery_rate,
                'transfer_trip_base_rate' => (float) $activePolicy->transfer_trip_base_rate,
                'transfer_per_km_rate' => (float) $activePolicy->transfer_per_km_rate,
                'transfer_per_parcel_rate' => (float) $activePolicy->transfer_per_parcel_rate,
                'transfer_fuel_allowance' => (float) $activePolicy->transfer_fuel_allowance,
            ],
        ];
    }

    /**
     * Request a verified courier earnings payout without deducting earnings_balance until manually paid.
     */
    public function requestPayout(User $courier, array $data): CourierPayout
    {
        $amountCents = $this->toCents($data['amount'] ?? 0);
        if ($amountCents <= 0) {
            throw ValidationException::withMessages(['amount' => 'Payout amount must be greater than ₱0.00.']);
        }

        $method = $data['payout_method'] ?? '';
        if (! in_array($method, self::PAYOUT_METHODS, true)) {
            throw ValidationException::withMessages(['payout_method' => 'Invalid payout method selected.']);
        }

        $idempotencyKey = $data['idempotency_key'] ?? null;
        if ($idempotencyKey) {
            $existing = CourierPayout::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($courier, $data, $amountCents, $method, $idempotencyKey) {
            CourierProfile::where('user_id', $courier->id)->lockForUpdate()->first();

            $lifetimeEarnedCents = $this->toCents(
                RiderEarning::where('courier_id', $courier->id)
                    ->whereIn('status', [RiderEarning::STATUS_EARNED, RiderEarning::STATUS_APPROVED_FOR_PAYOUT, RiderEarning::STATUS_PAID])
                    ->lockForUpdate()
                    ->sum('amount')
            );

            $reservedCents = $this->toCents(
                CourierPayout::where('courier_id', $courier->id)
                    ->whereIn('status', self::RESERVED_STATUSES)
                    ->lockForUpdate()
                    ->sum('amount')
            );

            $availableCents = max(0, $lifetimeEarnedCents - $reservedCents);
            if ($amountCents > $availableCents) {
                throw ValidationException::withMessages([
                    'amount' => 'Requested payout (₱' . number_format($this->fromCents($amountCents), 2) . ') exceeds withdrawable verified earnings (₱' . number_format($this->fromCents($availableCents), 2) . ').',
                ]);
            }

            $companyId = $this->policyService->resolveCompanyIdForCourier($courier);
            $role = $courier->role === 'courier' ? CompensationPolicy::ROLE_COURIER : CompensationPolicy::ROLE_RIDER;

            $payout = CourierPayout::create([
                'payout_number' => 'PAY-' . strtoupper(Str::random(10)),
                'courier_id' => $courier->id,
                'logistics_company_id' => $companyId,
                'role' => $role,
                'amount' => $this->fromCents($amountCents),
                'payout_method' => $method,
                'account_name' => trim((string) $data['account_name']),
                'account_number' => trim((string) $data['account_number']),
                'status' => CourierPayout::STATUS_REQUESTED,
                'courier_notes' => $data['courier_notes'] ?? null,
                'requested_at' => now(),
                'idempotency_key' => $idempotencyKey,
            ]);

            AuditLog::log(
                'courier_payout_requested',
                $payout,
                null,
                $payout->toArray(),
                $data['courier_notes'] ?? 'Courier requested earnings payout',
                $courier->id
            );

            return $payout;
        });
    }

    public function approvePayout(CourierPayout $payout, User $operator, ?string $notes = null): CourierPayout
    {
        return DB::transaction(function () use ($payout, $operator, $notes) {
            $locked = CourierPayout::whereKey($payout->id)->lockForUpdate()->firstOrFail();
            $this->assertOperatorAuthorized($locked, $operator);

            if (! in_array($locked->status, [CourierPayout::STATUS_REQUESTED, CourierPayout::STATUS_UNDER_REVIEW], true)) {
                throw ValidationException::withMessages(['status' => 'Only requested or under-review payouts can be approved.']);
            }

            $oldStatus = $locked->status;
            $locked->update([
                'status' => CourierPayout::STATUS_APPROVED,
                'operator_notes' => $notes ?? $locked->operator_notes,
                'reviewed_by' => $operator->id,
                'approved_at' => now(),
            ]);

            AuditLog::log('courier_payout_approved', $locked, ['status' => $oldStatus], ['status' => CourierPayout::STATUS_APPROVED], $notes, $operator->id);

            return $locked->fresh();
        });
    }

    public function markPaid(CourierPayout $payout, User $operator, string $externalReference, ?UploadedFile $proofFile = null, ?string $notes = null): CourierPayout
    {
        return DB::transaction(function () use ($payout, $operator, $externalReference, $proofFile, $notes) {
            $locked = CourierPayout::whereKey($payout->id)->lockForUpdate()->firstOrFail();
            $this->assertOperatorAuthorized($locked, $operator);

            if ($locked->status === CourierPayout::STATUS_PAID) {
                throw ValidationException::withMessages(['status' => 'This payout has already been disbursed.']);
            }

            if (! in_array($locked->status, [CourierPayout::STATUS_REQUESTED, CourierPayout::STATUS_UNDER_REVIEW, CourierPayout::STATUS_APPROVED], true)) {
                throw ValidationException::withMessages(['status' => 'Cannot disburse a rejected or cancelled payout.']);
            }

            $proofPath = $proofFile ? $proofFile->store('payout-proofs/couriers', 'public') : $locked->proof_path;

            $profile = CourierProfile::where('user_id', $locked->courier_id)->lockForUpdate()->first();
            $payoutCents = $this->toCents($locked->amount);
            if ($profile) {
                $currentCents = $this->toCents($profile->earnings_balance);
                $profile->update(['earnings_balance' => $this->fromCents(max(0, $currentCents - $payoutCents))]);
            }

            // Mark oldest unlinked earned RiderEarning rows up to amount cleanly
            $unlinkedEarnings = RiderEarning::where('courier_id', $locked->courier_id)
                ->whereIn('status', [RiderEarning::STATUS_EARNED, RiderEarning::STATUS_APPROVED_FOR_PAYOUT])
                ->whereNull('courier_payout_id')
                ->orderBy('earned_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $remainingToCover = $payoutCents;
            foreach ($unlinkedEarnings as $earning) {
                if ($remainingToCover <= 0) {
                    break;
                }
                $earningCents = $this->toCents($earning->amount);
                if ($earningCents <= $remainingToCover) {
                    $earning->update([
                        'status' => RiderEarning::STATUS_PAID,
                        'courier_payout_id' => $locked->id,
                    ]);
                    CourierPayoutItem::firstOrCreate(
                        ['rider_earning_id' => $earning->id],
                        ['courier_payout_id' => $locked->id, 'amount' => $earning->amount]
                    );
                    $remainingToCover -= $earningCents;
                }
            }

            $oldStatus = $locked->status;
            $locked->update([
                'status' => CourierPayout::STATUS_PAID,
                'external_reference' => trim($externalReference),
                'proof_path' => $proofPath,
                'operator_notes' => $notes ?? $locked->operator_notes,
                'reviewed_by' => $locked->reviewed_by ?? $operator->id,
                'approved_at' => $locked->approved_at ?? now(),
                'paid_by' => $operator->id,
                'paid_at' => now(),
            ]);

            AuditLog::log(
                'courier_payout_paid',
                $locked,
                ['status' => $oldStatus],
                ['status' => CourierPayout::STATUS_PAID, 'external_reference' => $externalReference, 'amount' => (float) $locked->amount],
                $notes,
                $operator->id
            );

            return $locked->fresh();
        });
    }

    public function rejectPayout(CourierPayout $payout, User $operator, string $reason): CourierPayout
    {
        return DB::transaction(function () use ($payout, $operator, $reason) {
            $locked = CourierPayout::whereKey($payout->id)->lockForUpdate()->firstOrFail();
            $this->assertOperatorAuthorized($locked, $operator);

            if ($locked->status === CourierPayout::STATUS_PAID) {
                throw ValidationException::withMessages(['status' => 'Cannot reject an already disbursed payout.']);
            }

            $oldStatus = $locked->status;
            $locked->update([
                'status' => CourierPayout::STATUS_REJECTED,
                'rejection_reason' => $reason,
                'reviewed_by' => $operator->id,
            ]);

            AuditLog::log('courier_payout_rejected', $locked, ['status' => $oldStatus], ['status' => CourierPayout::STATUS_REJECTED, 'reason' => $reason], $reason, $operator->id);

            return $locked->fresh();
        });
    }

    protected function assertOperatorAuthorized(CourierPayout $payout, User $operator): void
    {
        if ($operator->isAdmin()) {
            return;
        }

        $companyId = $operator->logisticsCompanyId();
        $courierCompany = $payout->logistics_company_id ?: $payout->courier?->courierProfile?->logistics_company_id ?: $payout->courier?->courierProfile?->sorting_center_id;
        if ($courierCompany && (int) $courierCompany !== $companyId) {
            throw ValidationException::withMessages(['payout' => 'Unauthorized: this payout belongs to another logistics company.']);
        }
    }
}
