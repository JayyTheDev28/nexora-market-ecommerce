<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CompensationPolicy;
use App\Models\CourierProfile;
use App\Models\LogisticsProfile;
use App\Models\Order;
use App\Models\RiderEarning;
use App\Models\TransferManifest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompensationPolicyService
{
    public const DEFAULT_RIDER_PICKUP = 30.00;
    public const DEFAULT_RIDER_DELIVERY = 50.00;
    public const DEFAULT_TRANSFER_BASE = 150.00;
    public const DEFAULT_TRANSFER_PER_KM = 2.50;
    public const DEFAULT_TRANSFER_PER_PARCEL = 5.00;
    public const DEFAULT_TRANSFER_FUEL = 50.00;

    /**
     * Ensure baseline active policies (₱30/₱50 Rider, ₱150 Transfer Trip) and the proposed ₱10/₱30 draft exist.
     */
    public function ensureDefaultPolicies(): void
    {
        if (! CompensationPolicy::whereNull('logistics_company_id')->where('role', CompensationPolicy::ROLE_RIDER)->where('status', CompensationPolicy::STATUS_ACTIVE)->exists()) {
            CompensationPolicy::create([
                'logistics_company_id' => null,
                'role' => CompensationPolicy::ROLE_RIDER,
                'name' => 'Standard Rider Compensation Policy (Legacy Active Baseline)',
                'version' => 1,
                'status' => CompensationPolicy::STATUS_ACTIVE,
                'pickup_rate' => self::DEFAULT_RIDER_PICKUP,
                'delivery_rate' => self::DEFAULT_RIDER_DELIVERY,
                'failed_attempt_rate' => 0.00,
                'effective_from' => now(),
                'notes' => 'Active baseline preserving ₱30 pickup / ₱50 delivery rates.',
            ]);
        }

        if (! CompensationPolicy::whereNull('logistics_company_id')->where('role', CompensationPolicy::ROLE_RIDER)->whereIn('status', [CompensationPolicy::STATUS_DRAFT, CompensationPolicy::STATUS_PROPOSED])->where('pickup_rate', 10.00)->where('delivery_rate', 30.00)->exists()) {
            CompensationPolicy::create([
                'logistics_company_id' => null,
                'role' => CompensationPolicy::ROLE_RIDER,
                'name' => 'Proposed Revised Rider Rate Schedule (₱10 Pickup / ₱30 Delivery)',
                'version' => 2,
                'status' => CompensationPolicy::STATUS_DRAFT,
                'pickup_rate' => 10.00,
                'delivery_rate' => 30.00,
                'failed_attempt_rate' => 0.00,
                'notes' => 'Draft proposal requiring explicit Logistics/Admin approval before activation.',
            ]);
        }

        if (! CompensationPolicy::whereNull('logistics_company_id')->where('role', CompensationPolicy::ROLE_COURIER)->where('status', CompensationPolicy::STATUS_ACTIVE)->exists()) {
            CompensationPolicy::create([
                'logistics_company_id' => null,
                'role' => CompensationPolicy::ROLE_COURIER,
                'name' => 'Standard Transfer Courier Trip Policy',
                'version' => 1,
                'status' => CompensationPolicy::STATUS_ACTIVE,
                'pickup_rate' => 0.00,
                'delivery_rate' => 0.00,
                'failed_attempt_rate' => 0.00,
                'transfer_trip_base_rate' => self::DEFAULT_TRANSFER_BASE,
                'transfer_per_km_rate' => self::DEFAULT_TRANSFER_PER_KM,
                'transfer_per_parcel_rate' => self::DEFAULT_TRANSFER_PER_PARCEL,
                'transfer_per_kg_rate' => 0.00,
                'transfer_fuel_allowance' => self::DEFAULT_TRANSFER_FUEL,
                'transfer_exception_rate' => 75.00,
                'pay_on_exception' => false,
                'effective_from' => now(),
                'notes' => 'Default Transfer Courier trip compensation applied upon completed hub transfer.',
            ]);
        }
    }

    /**
     * Resolve currently active policy for a logistics company + role, falling back to global active policy.
     */
    public function getActivePolicy(string $role, ?int $logisticsCompanyId = null, ?\DateTimeInterface $at = null): CompensationPolicy
    {
        $this->ensureDefaultPolicies();
        $at ??= now();

        if ($logisticsCompanyId) {
            $companyPolicy = CompensationPolicy::where('logistics_company_id', $logisticsCompanyId)
                ->where('role', $role)
                ->where('status', CompensationPolicy::STATUS_ACTIVE)
                ->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', $at))
                ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $at))
                ->orderByDesc('version')
                ->orderByDesc('id')
                ->first();

            if ($companyPolicy) {
                return $companyPolicy;
            }
        }

        return CompensationPolicy::whereNull('logistics_company_id')
            ->where('role', $role)
            ->where('status', CompensationPolicy::STATUS_ACTIVE)
            ->orderByDesc('version')
            ->orderByDesc('id')
            ->firstOrFail();
    }

    /**
     * Resolve locked pickup or delivery rate for a courier + order (honors JobOffer rate snapshot if accepted).
     */
    public function resolveRiderJobRate(
        Order $order,
        User $courier,
        string $jobType,
        ?float $overrideAmount = null,
        float $distanceKm = 0.0,
        bool $includeBatchBonus = false
    ): array {
        $companyId = $this->resolveCompanyIdForCourier($courier, $order, $jobType);
        $policy = $this->getActivePolicy(CompensationPolicy::ROLE_RIDER, $companyId);

        $acceptedOffer = $order->jobOffers()
            ->where('courier_id', $courier->id)
            ->where('type', $jobType)
            ->where('status', 'accepted')
            ->latest('id')
            ->first();

        $baseRate = match ($jobType) {
            'pickup' => (float) ($acceptedOffer?->expected_pay ?? $policy->pickup_rate),
            'delivery' => (float) ($acceptedOffer?->expected_pay ?? $policy->delivery_rate),
            'failed_delivery' => (float) $policy->failed_attempt_rate,
            default => (float) $policy->delivery_rate,
        };

        $distanceAllowance = (! $acceptedOffer && $distanceKm > 0 && in_array($jobType, ['pickup', 'delivery'], true))
            ? round((float) ($policy->distance_allowance_rate ?? 0) * $distanceKm, 2)
            : 0.00;
        $batchBonus = (! $acceptedOffer && $includeBatchBonus && in_array($jobType, ['pickup', 'delivery'], true))
            ? round((float) ($policy->batch_completion_bonus ?? 0), 2)
            : 0.00;

        $rate = $baseRate + $distanceAllowance + $batchBonus;

        if ($overrideAmount !== null) {
            $rate = $overrideAmount;
        }

        return [
            'policy' => $policy,
            'company_id' => $companyId,
            'amount' => round(max(0.00, $rate), 2),
            'snapshot' => [
                'policy_id' => $policy->id,
                'policy_name' => $policy->name,
                'policy_version' => $policy->version,
                'job_type' => $jobType,
                'locked_from_offer_id' => $acceptedOffer?->id,
                'pickup_rate' => (float) $policy->pickup_rate,
                'delivery_rate' => (float) $policy->delivery_rate,
                'failed_attempt_rate' => (float) $policy->failed_attempt_rate,
                'distance_allowance_rate' => (float) ($policy->distance_allowance_rate ?? 0),
                'distance_km' => round($distanceKm, 2),
                'distance_allowance_amount' => $distanceAllowance,
                'batch_completion_bonus' => $batchBonus,
            ],
        ];
    }

    /**
     * Estimate Transfer Cost Per Parcel = Estimated Trip Cost / Expected Number of Parcels.
     */
    public function estimateTransferCostPerParcel(
        CompensationPolicy $policy,
        float $distanceKm = 0.0,
        ?int $expectedParcels = null,
        float $expectedWeightKg = 0.0
    ): array {
        $capacity = max(1, (int) ($expectedParcels ?: $policy->expected_manifest_parcels ?: 20));
        $baseCents = (int) round((float) $policy->transfer_trip_base_rate * 100);
        $kmCents = (int) round((float) $policy->transfer_per_km_rate * $distanceKm * 100);
        $parcelCents = (int) round((float) $policy->transfer_per_parcel_rate * $capacity * 100);
        $kgCents = (int) round((float) $policy->transfer_per_kg_rate * $expectedWeightKg * 100);
        $fuelCents = (int) round((float) $policy->transfer_fuel_allowance * 100);

        $estimatedTripCost = round(($baseCents + $kmCents + $parcelCents + $kgCents + $fuelCents) / 100, 2);
        $costPerParcel = round($estimatedTripCost / $capacity, 2);

        return [
            'policy_id' => $policy->id,
            'expected_parcel_count' => $capacity,
            'estimated_trip_cost' => $estimatedTripCost,
            'expected_cost_per_parcel' => $costPerParcel,
        ];
    }

    /**
     * Calculate and credit Transfer Courier compensation upon manifest completion (or exception if policy permits).
     * Guaranteed idempotent via unique earning_key.
     */
    public function creditTransferCourierForManifest(TransferManifest $manifest, bool $isException = false): ?RiderEarning
    {
        if (! $manifest->assigned_courier_id) {
            return null;
        }

        return DB::transaction(function () use ($manifest, $isException) {
            $courierId = (int) $manifest->assigned_courier_id;
            $companyId = (int) ($manifest->sender_logistics_id ?: $manifest->receiver_logistics_id);
            $policy = $this->getActivePolicy(CompensationPolicy::ROLE_COURIER, $companyId);

            if ($isException && ! $policy->pay_on_exception) {
                return null;
            }

            $earningKey = $isException
                ? "manifest_{$manifest->id}_exception_courier_{$courierId}"
                : "manifest_{$manifest->id}_completion_courier_{$courierId}";

            // Prevent duplicate pay across both completion and exception
            $existing = RiderEarning::where('manifest_id', $manifest->id)
                ->where('courier_id', $courierId)
                ->whereIn('type', ['transfer_trip', 'transfer_exception'])
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            $receivedCount = (int) $manifest->items()
                ->where('receiving_status', \App\Models\TransferManifestItem::RECEIVING_RECEIVED)
                ->count();
            $parcelCount = $receivedCount > 0 ? $receivedCount : max(1, (int) $manifest->items()->count());
            $totalWeightKg = (float) $manifest->items()->with('order')->get()->sum(fn ($item) => (float) ($item->order?->shipping_weight ?? 1.0));
            $distanceKm = (float) ($manifest->distance_km ?? 0.0);

            if ($isException) {
                $amount = round((float) $policy->transfer_exception_rate, 2);
                $type = 'transfer_exception';
                $breakdown = [
                    'policy_id' => $policy->id,
                    'policy_name' => $policy->name,
                    'policy_version' => $policy->version,
                    'mode' => 'exception',
                    'delivered_parcels_count' => $receivedCount,
                    'transfer_exception_rate' => $amount,
                ];
            } else {
                $baseCents = (int) round((float) $policy->transfer_trip_base_rate * 100);
                $kmCents = (int) round((float) $policy->transfer_per_km_rate * $distanceKm * 100);
                $parcelCents = (int) round((float) $policy->transfer_per_parcel_rate * $parcelCount * 100);
                $kgCents = (int) round((float) $policy->transfer_per_kg_rate * $totalWeightKg * 100);
                $fuelCents = (int) round((float) $policy->transfer_fuel_allowance * 100);
                $totalCents = $baseCents + $kmCents + $parcelCents + $kgCents + $fuelCents;
                $amount = round($totalCents / 100, 2);
                $type = 'transfer_trip';
                $breakdown = [
                    'policy_id' => $policy->id,
                    'policy_name' => $policy->name,
                    'policy_version' => $policy->version,
                    'mode' => 'completed_trip',
                    'parcel_count' => $parcelCount,
                    'delivered_parcels_count' => $receivedCount,
                    'total_weight_kg' => round($totalWeightKg, 2),
                    'distance_km' => round($distanceKm, 2),
                    'base_rate' => (float) $policy->transfer_trip_base_rate,
                    'per_km_amount' => round($kmCents / 100, 2),
                    'per_parcel_amount' => round($parcelCents / 100, 2),
                    'per_kg_amount' => round($kgCents / 100, 2),
                    'fuel_allowance' => (float) $policy->transfer_fuel_allowance,
                ];
            }

            if ($amount <= 0) {
                return null;
            }

            $expectedEstimate = $this->estimateTransferCostPerParcel(
                $policy,
                $distanceKm,
                $manifest->expected_parcel_count ?: ($policy->expected_manifest_parcels ?: 20),
                $totalWeightKg
            );
            $expectedParcels = $manifest->expected_parcel_count ?: $expectedEstimate['expected_parcel_count'];
            $estimatedTripCost = $manifest->estimated_trip_cost !== null
                ? (float) $manifest->estimated_trip_cost
                : $expectedEstimate['estimated_trip_cost'];
            $expectedCostPerParcel = $manifest->expected_cost_per_parcel !== null
                ? (float) $manifest->expected_cost_per_parcel
                : $expectedEstimate['expected_cost_per_parcel'];
            $actualCostPerParcel = round($amount / max(1, $parcelCount), 2);
            $lowVolumeWarning = $parcelCount < max(1, (int) ceil($expectedParcels * 0.5));

            $manifest->update([
                'expected_parcel_count' => $expectedParcels,
                'estimated_trip_cost' => $estimatedTripCost,
                'expected_cost_per_parcel' => $expectedCostPerParcel,
                'actual_cost_per_parcel' => $actualCostPerParcel,
            ]);

            $breakdown['expected_parcel_count'] = $expectedParcels;
            $breakdown['estimated_trip_cost'] = $estimatedTripCost;
            $breakdown['expected_cost_per_parcel'] = $expectedCostPerParcel;
            $breakdown['actual_trip_cost'] = $amount;
            $breakdown['actual_cost_per_parcel'] = $actualCostPerParcel;
            $breakdown['low_volume_warning'] = $lowVolumeWarning;

            $earning = RiderEarning::create([
                'courier_id' => $courierId,
                'logistics_company_id' => $companyId,
                'order_id' => null,
                'manifest_id' => $manifest->id,
                'policy_id' => $policy->id,
                'earning_key' => $earningKey,
                'type' => $type,
                'amount' => $amount,
                'status' => RiderEarning::STATUS_EARNED,
                'description' => "Transfer Manifest {$manifest->manifest_number} ({$parcelCount} parcel(s))",
                'rate_snapshot' => $breakdown,
                'earned_at' => now(),
            ]);

            $profile = CourierProfile::where('user_id', $courierId)->lockForUpdate()->first();
            if ($profile) {
                $newBalanceCents = (int) round((float) $profile->earnings_balance * 100) + (int) round($amount * 100);
                $profile->update(['earnings_balance' => round($newBalanceCents / 100, 2)]);
            }

            AuditLog::log(
                'transfer_courier_earning_credited',
                $manifest,
                null,
                [
                    'courier_id' => $courierId,
                    'earning_id' => $earning->id,
                    'amount' => $amount,
                    'type' => $type,
                ],
                "Transfer compensation credited upon manifest {$manifest->manifest_number} reconciliation",
                $manifest->receiver_logistics_id ?: $companyId
            );

            return $earning;
        });
    }

    public function createPolicy(array $data, User $actor): CompensationPolicy
    {
        $companyId = $data['logistics_company_id'] ?? null;
        if (! $actor->isAdmin()) {
            $companyId = $actor->logisticsCompanyId();
        }

        $latestVersion = (int) CompensationPolicy::where('logistics_company_id', $companyId)
            ->where('role', $data['role'])
            ->max('version');

        return CompensationPolicy::create([
            'logistics_company_id' => $companyId,
            'role' => $data['role'],
            'name' => $data['name'],
            'version' => $latestVersion + 1,
            'status' => $data['status'] ?? CompensationPolicy::STATUS_PROPOSED,
            'pickup_rate' => round((float) ($data['pickup_rate'] ?? 0), 2),
            'delivery_rate' => round((float) ($data['delivery_rate'] ?? 0), 2),
            'failed_attempt_rate' => round((float) ($data['failed_attempt_rate'] ?? 0), 2),
            'distance_allowance_rate' => round((float) ($data['distance_allowance_rate'] ?? 0), 2),
            'batch_completion_bonus' => round((float) ($data['batch_completion_bonus'] ?? 0), 2),
            'transfer_trip_base_rate' => round((float) ($data['transfer_trip_base_rate'] ?? 0), 2),
            'transfer_per_km_rate' => round((float) ($data['transfer_per_km_rate'] ?? 0), 2),
            'transfer_per_parcel_rate' => round((float) ($data['transfer_per_parcel_rate'] ?? 0), 2),
            'transfer_per_kg_rate' => round((float) ($data['transfer_per_kg_rate'] ?? 0), 2),
            'transfer_fuel_allowance' => round((float) ($data['transfer_fuel_allowance'] ?? 0), 2),
            'transfer_exception_rate' => round((float) ($data['transfer_exception_rate'] ?? 0), 2),
            'pay_on_exception' => (bool) ($data['pay_on_exception'] ?? false),
            'expected_manifest_parcels' => max(1, (int) ($data['expected_manifest_parcels'] ?? 20)),
            'effective_from' => $data['effective_from'] ?? null,
            'effective_to' => $data['effective_to'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $actor->id,
        ]);
    }

    public function approveAndActivatePolicy(CompensationPolicy $policy, User $actor): CompensationPolicy
    {
        return DB::transaction(function () use ($policy, $actor) {
            $locked = CompensationPolicy::whereKey($policy->id)->lockForUpdate()->firstOrFail();

            if (! $actor->isAdmin()) {
                $companyId = $actor->logisticsCompanyId();
                if ($locked->logistics_company_id === null) {
                    // Clone/activate scoped to this company so one company never alters another company's active rates
                    CompensationPolicy::where('logistics_company_id', $companyId)
                        ->where('role', $locked->role)
                        ->where('status', CompensationPolicy::STATUS_ACTIVE)
                        ->update(['status' => CompensationPolicy::STATUS_ARCHIVED, 'effective_to' => now()]);

                    $nextVersion = (int) CompensationPolicy::where('logistics_company_id', $companyId)
                        ->where('role', $locked->role)
                        ->max('version') + 1;

                    $activated = CompensationPolicy::create([
                        'logistics_company_id' => $companyId,
                        'role' => $locked->role,
                        'name' => $locked->name,
                        'version' => max($nextVersion, $locked->version),
                        'status' => CompensationPolicy::STATUS_ACTIVE,
                        'pickup_rate' => $locked->pickup_rate,
                        'delivery_rate' => $locked->delivery_rate,
                        'failed_attempt_rate' => $locked->failed_attempt_rate,
                        'distance_allowance_rate' => $locked->distance_allowance_rate ?? 0.00,
                        'batch_completion_bonus' => $locked->batch_completion_bonus ?? 0.00,
                        'transfer_trip_base_rate' => $locked->transfer_trip_base_rate,
                        'transfer_per_km_rate' => $locked->transfer_per_km_rate,
                        'transfer_per_parcel_rate' => $locked->transfer_per_parcel_rate,
                        'transfer_per_kg_rate' => $locked->transfer_per_kg_rate,
                        'transfer_fuel_allowance' => $locked->transfer_fuel_allowance,
                        'transfer_exception_rate' => $locked->transfer_exception_rate,
                        'pay_on_exception' => $locked->pay_on_exception,
                        'expected_manifest_parcels' => $locked->expected_manifest_parcels ?? 20,
                        'effective_from' => now(),
                        'notes' => $locked->notes,
                        'created_by' => $actor->id,
                        'approved_by' => $actor->id,
                        'approved_at' => now(),
                    ]);

                    AuditLog::log('compensation_policy_activated', $activated, null, $activated->toArray(), 'Company activated compensation policy', $actor->id);

                    return $activated;
                }

                if ((int) $locked->logistics_company_id !== $companyId) {
                    throw ValidationException::withMessages([
                        'policy' => 'You are not authorized to activate another company\'s compensation policy.',
                    ]);
                }
            }

            CompensationPolicy::where('logistics_company_id', $locked->logistics_company_id)
                ->where('role', $locked->role)
                ->where('id', '!=', $locked->id)
                ->where('status', CompensationPolicy::STATUS_ACTIVE)
                ->update(['status' => CompensationPolicy::STATUS_ARCHIVED, 'effective_to' => now()]);

            $old = $locked->only(['status', 'effective_from', 'approved_by']);
            $locked->update([
                'status' => CompensationPolicy::STATUS_ACTIVE,
                'effective_from' => $locked->effective_from ?? now(),
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);

            AuditLog::log('compensation_policy_activated', $locked, $old, $locked->only(['status', 'effective_from', 'approved_by']), 'Activated compensation policy', $actor->id);

            return $locked->fresh();
        });
    }

    public function resolveCompanyIdForCourier(User $courier, ?Order $order = null, ?string $jobType = null): ?int
    {
        $profile = $courier->courierProfile;
        if ($profile) {
            if ($profile->logistics_company_id) {
                return (int) $profile->logistics_company_id;
            }
            if ($profile->sorting_center_id) {
                return (int) $profile->sorting_center_id;
            }
        }

        if ($order) {
            if ($jobType === 'pickup') {
                return (int) ($order->pickup_logistics_id ?? $order->sorting_center_id ?: 0) ?: null;
            }

            return (int) ($order->delivery_logistics_id ?? $order->sorting_center_id ?: 0) ?: null;
        }

        return null;
    }
}
