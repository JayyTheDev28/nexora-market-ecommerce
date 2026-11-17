<?php

namespace App\Services;

use App\Models\Address;
use App\Models\AuditLog;
use App\Models\DeliveryArea;
use App\Models\Hub;
use App\Models\InterCompanyShippingAllocation;
use App\Models\Order;
use App\Models\ShippingRatePolicy;
use App\Models\User;
use App\Models\Zone;
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

    /**
     * Authoritative smart shipping quote used by Web Checkout, Mobile Checkout Summary, and Order Placement.
     * Supports Cost + Margin formula, local/same-province/interprovincial route rules,
     * volumetric/oversized weights, and route serviceability checks.
     */
    public function calculateSmartQuote(
        iterable $items,
        ?int $sellerId = null,
        ?Address $buyerAddress = null,
        ?array $buyerLocationOverride = null,
        ?ShippingRatePolicy $policyOverride = null
    ): array {
        $itemsCollection = collect($items);

        $rawWeightKg = 0.0;
        $volumetricWeightKg = 0.0;
        $oversizedFlag = false;

        foreach ($itemsCollection as $item) {
            $product = $item->product ?? $item;
            $qty = max(1, (int) ($item->quantity ?? 1));
            $itemWeight = max(0.01, (float) ($product->weight ?? 1.0));
            $rawWeightKg += $itemWeight * $qty;
        }

        $policyCandidate = $policyOverride;
        $sellerAddress = $sellerId ? Address::where('user_id', $sellerId)->orderByDesc('is_default')->first() : null;
        $originRoute = $this->resolveAddressRoute($sellerAddress);
        $destinationRoute = $this->resolveAddressRoute($buyerAddress, $buyerLocationOverride);

        $logisticsCompanyId = $destinationRoute['logistics_company_id']
            ?? $originRoute['logistics_company_id']
            ?? null;

        $policy = $policyCandidate ?? $this->getActivePolicy($logisticsCompanyId);
        $divisor = max(1000, (int) ($policy->volumetric_divisor ?: 5000));

        foreach ($itemsCollection as $item) {
            $product = $item->product ?? $item;
            $qty = max(1, (int) ($item->quantity ?? 1));
            $volPerUnit = $this->computeVolumetricWeightKg($product->dimensions ?? null, $divisor);
            if ($volPerUnit > (float) ($product->weight ?? 1.0)) {
                $oversizedFlag = true;
            }
            $volumetricWeightKg += $volPerUnit * $qty;
        }

        $effectiveWeightKg = max($rawWeightKg, $volumetricWeightKg);
        $billedWeightKg = max(1, (int) ceil($effectiveWeightKg));
        $baseWeightInt = max(1, (int) ceil((float) $policy->base_weight_kg));
        $additionalKg = max(0, $billedWeightKg - $baseWeightInt);
        if ($billedWeightKg >= 10) {
            $oversizedFlag = true;
        }

        // Check serviceability when address or location override is supplied
        $serviceability = $this->checkDestinationServiceability($buyerAddress, $buyerLocationOverride, $destinationRoute);

        // Classify route type (same_city, same_province, interprovincial)
        $routeType = $this->classifyRouteType($sellerAddress, $buyerAddress, $buyerLocationOverride, $originRoute, $destinationRoute);

        $routeRule = is_array($policy->route_rules) ? ($policy->route_rules[$routeType] ?? []) : [];
        $routeSurcharge = match ($routeType) {
            ShippingRatePolicy::ROUTE_SAME_PROVINCE => (float) ($policy->same_province_surcharge ?? 0),
            ShippingRatePolicy::ROUTE_INTERPROVINCIAL => (float) ($policy->interprovincial_surcharge ?? 0),
            default => 0.00,
        };
        if (isset($routeRule['surcharge'])) {
            $routeSurcharge = (float) $routeRule['surcharge'];
        }

        $oversizedSurcharge = $oversizedFlag ? (float) ($policy->oversized_surcharge ?? 0) : 0.00;
        $additionalWeightFee = round((float) $policy->per_additional_kg_fee * $additionalKg, 2);

        if ($policy->pricing_mode === ShippingRatePolicy::MODE_SMART_COST_MARGIN) {
            $pickupCost = (float) ($routeRule['pickup_cost'] ?? $policy->estimated_pickup_cost ?? 10.00);
            $deliveryCost = (float) ($routeRule['delivery_cost'] ?? $policy->estimated_delivery_cost ?? 30.00);
            $defaultTransferCost = $routeType === ShippingRatePolicy::ROUTE_SAME_CITY
                ? 0.00
                : (float) ($policy->estimated_transfer_cost_per_parcel ?? 20.00);
            $transferCost = (float) ($routeRule['transfer_cost'] ?? $defaultTransferCost);
            $hubCost = (float) ($routeRule['hub_cost'] ?? $policy->estimated_hub_operating_cost ?? 10.00);

            $totalEstimatedCost = round(
                $pickupCost + $deliveryCost + $transferCost + $hubCost + $additionalWeightFee + $routeSurcharge + $oversizedSurcharge,
                2
            );

            $targetMarginPct = min(90.0, max(0.0, (float) ($policy->target_profit_margin_pct ?? 20.0)));
            $marginDivisor = max(0.05, 1.0 - ($targetMarginPct / 100.0));
            $rawFormulaFee = round($totalEstimatedCost / $marginDivisor, 2);

            $roundingIncrement = (float) ($policy->rounding_increment ?? 0.0);
            $roundedFee = $roundingIncrement > 0
                ? round(ceil($rawFormulaFee / $roundingIncrement) * $roundingIncrement, 2)
                : $rawFormulaFee;

            $finalFee = max((float) $policy->minimum_fee, $roundedFee);
        } else {
            // Preserve legacy weight-tiered calculation while allowing optional route/oversized surcharges
            $pickupCost = (float) ($policy->estimated_pickup_cost ?? 10.00);
            $deliveryCost = (float) ($policy->estimated_delivery_cost ?? 30.00);
            $transferCost = $routeType === ShippingRatePolicy::ROUTE_SAME_CITY ? 0.00 : (float) ($policy->estimated_transfer_cost_per_parcel ?? 20.00);
            $hubCost = (float) ($policy->estimated_hub_operating_cost ?? 10.00);
            $totalEstimatedCost = round($pickupCost + $deliveryCost + $transferCost + $hubCost + $additionalWeightFee, 2);
            $targetMarginPct = (float) ($policy->target_profit_margin_pct ?? 20.0);

            $rawFormulaFee = round((float) $policy->base_fee + $additionalWeightFee + $routeSurcharge + $oversizedSurcharge, 2);
            $roundingIncrement = (float) ($policy->rounding_increment ?? 0.0);
            $roundedFee = $roundingIncrement > 0
                ? round(ceil($rawFormulaFee / $roundingIncrement) * $roundingIncrement, 2)
                : $rawFormulaFee;
            $finalFee = max((float) $policy->minimum_fee, $roundedFee);
        }

        return [
            'policy' => $policy,
            'pricing_mode' => $policy->pricing_mode ?? ShippingRatePolicy::MODE_WEIGHT_TIERED,
            'raw_weight_kg' => round($rawWeightKg, 2),
            'volumetric_weight_kg' => round($volumetricWeightKg, 2),
            'billed_weight_kg' => $billedWeightKg,
            'is_oversized' => $oversizedFlag,
            'route_type' => $routeType,
            'origin_logistics_id' => $originRoute['logistics_company_id'] ?? null,
            'delivery_logistics_id' => $destinationRoute['logistics_company_id'] ?? null,
            'origin_hub_id' => $originRoute['hub_id'] ?? null,
            'destination_hub_id' => $destinationRoute['hub_id'] ?? null,
            'serviceable' => $serviceability['serviceable'],
            'serviceability_error' => $serviceability['error'],
            'shipping_fee' => round($finalFee, 2),
            'breakdown' => [
                'pricing_mode' => $policy->pricing_mode ?? ShippingRatePolicy::MODE_WEIGHT_TIERED,
                'route_type' => $routeType,
                'estimated_pickup_cost' => round($pickupCost, 2),
                'estimated_delivery_cost' => round($deliveryCost, 2),
                'estimated_transfer_cost' => round($transferCost, 2),
                'estimated_hub_cost' => round($hubCost, 2),
                'additional_weight_fee' => $additionalWeightFee,
                'route_surcharge' => round($routeSurcharge, 2),
                'oversized_surcharge' => round($oversizedSurcharge, 2),
                'total_estimated_cost' => $totalEstimatedCost,
                'target_profit_margin_pct' => $targetMarginPct,
                'raw_formula_fee' => $rawFormulaFee,
                'rounding_increment' => (float) ($policy->rounding_increment ?? 0),
                'final_shipping_fee' => round($finalFee, 2),
                'expected_profit_amount' => round($finalFee - $totalEstimatedCost, 2),
            ],
        ];
    }

    protected function computeVolumetricWeightKg(mixed $dimensions, int $divisor): float
    {
        if (empty($dimensions)) {
            return 0.0;
        }

        if (is_array($dimensions)) {
            $l = (float) ($dimensions['length'] ?? $dimensions['l'] ?? 0);
            $w = (float) ($dimensions['width'] ?? $dimensions['w'] ?? 0);
            $h = (float) ($dimensions['height'] ?? $dimensions['h'] ?? 0);

            return ($l > 0 && $w > 0 && $h > 0) ? round(($l * $w * $h) / $divisor, 2) : 0.0;
        }

        if (is_string($dimensions) && preg_match('/(\d+(?:\.\d+)?)\s*[x×\*]\s*(\d+(?:\.\d+)?)\s*[x×\*]\s*(\d+(?:\.\d+)?)/i', $dimensions, $m)) {
            $l = (float) $m[1];
            $w = (float) $m[2];
            $h = (float) $m[3];

            return ($l > 0 && $w > 0 && $h > 0) ? round(($l * $w * $h) / $divisor, 2) : 0.0;
        }

        return 0.0;
    }

    protected function resolveAddressRoute(?Address $address = null, ?array $override = null): ?array
    {
        $barangayCode = $override['barangay_code'] ?? $address?->barangay_code;
        $cityCode = $override['municipality_code'] ?? $override['city_code'] ?? $address?->municipality_code;
        $provinceCode = $override['province_code'] ?? $address?->province_code;
        $barangayName = $override['barangay_name'] ?? $address?->barangay_name;
        $cityName = $override['municipality_name'] ?? $override['city_name'] ?? $address?->municipality_name;
        $provinceName = $override['province_name'] ?? $address?->province_name;

        if (! $barangayCode && ! $cityCode && ! $provinceCode && ! $cityName && ! $provinceName) {
            return null;
        }

        try {
            return app(InterRegionLogisticsService::class)->resolveCoverage(
                $barangayCode,
                $cityCode,
                $provinceCode,
                $barangayName,
                $cityName,
                $provinceName
            );
        } catch (\Throwable) {
            return null;
        }
    }

    protected function checkDestinationServiceability(?Address $address, ?array $override, ?array $resolvedRoute): array
    {
        $hasInfra = DeliveryArea::exists() || Hub::where('is_active', true)->exists() || Zone::where('is_active', true)->exists();
        if (! $hasInfra) {
            return ['serviceable' => true, 'error' => null];
        }

        $provinceCode = $override['province_code'] ?? $address?->province_code;
        $cityCode = $override['municipality_code'] ?? $override['city_code'] ?? $address?->municipality_code;
        $provinceName = $override['province_name'] ?? $address?->province_name;
        $cityName = $override['municipality_name'] ?? $override['city_name'] ?? $address?->municipality_name;

        if (! $provinceCode && ! $cityCode && ! $provinceName && ! $cityName) {
            return ['serviceable' => true, 'error' => null];
        }

        if ($resolvedRoute !== null) {
            return ['serviceable' => true, 'error' => null];
        }

        // Check strictly at barangay/city level (never infer serviceability from province alone)
        $covered = false;

        if ($cityCode || $cityName) {
            $covered = Hub::where('is_active', true)
                ->where(function ($q) use ($cityCode, $cityName) {
                    if ($cityCode) {
                        $q->where('city_code', $cityCode);
                    }
                    if ($cityName) {
                        $method = $cityCode ? 'orWhere' : 'where';
                        $q->{$method}('city_name', 'LIKE', "%{$cityName}%")
                            ->orWhere('name', 'LIKE', "%{$cityName}%");
                    }
                })->exists();

            if (! $covered) {
                $covered = Zone::where('is_active', true)
                    ->where(function ($q) use ($cityCode, $cityName) {
                        if ($cityCode) {
                            $q->where('city_code', $cityCode);
                        }
                        if ($cityName) {
                            $method = $cityCode ? 'orWhere' : 'where';
                            $q->{$method}('city_name', 'LIKE', "%{$cityName}%")
                                ->orWhere('name', 'LIKE', "%{$cityName}%");
                        }
                    })->exists();
            }

            if (! $covered && DeliveryArea::exists() && $cityName) {
                $covered = DeliveryArea::where('name', 'LIKE', "%{$cityName}%")->exists();
            }
        }

        if (! $covered) {
            $label = $provinceName ?: ($cityName ?: 'selected area');

            return [
                'serviceable' => false,
                'error' => "Notice: The delivery address in '{$label}' is currently outside our courier servicing areas.",
            ];
        }

        return ['serviceable' => true, 'error' => null];
    }

    protected function classifyRouteType(
        ?Address $sellerAddress,
        ?Address $buyerAddress,
        ?array $buyerOverride,
        ?array $originRoute,
        ?array $destinationRoute
    ): string {
        $sProv = strtolower(trim((string) ($sellerAddress?->province_code ?: $sellerAddress?->province_name ?: '')));
        $bProv = strtolower(trim((string) ($buyerOverride['province_code'] ?? $buyerOverride['province_name'] ?? $buyerAddress?->province_code ?? $buyerAddress?->province_name ?? '')));
        $sCity = strtolower(trim((string) ($sellerAddress?->municipality_code ?: $sellerAddress?->municipality_name ?: '')));
        $bCity = strtolower(trim((string) ($buyerOverride['municipality_code'] ?? $buyerOverride['municipality_name'] ?? $buyerAddress?->municipality_code ?? $buyerAddress?->municipality_name ?? '')));

        $sCity = preg_replace('/\b(city|municipality)\b/i', '', $sCity ?? '');
        $bCity = preg_replace('/\b(city|municipality)\b/i', '', $bCity ?? '');

        if ($originRoute && $destinationRoute
            && ! empty($originRoute['hub_id'])
            && ! empty($destinationRoute['hub_id'])
            && (int) $originRoute['hub_id'] === (int) $destinationRoute['hub_id']
            && ($sCity === '' || $bCity === '' || trim($sCity) === trim($bCity))
        ) {
            return ShippingRatePolicy::ROUTE_SAME_CITY;
        }

        if ($sCity !== '' && $bCity !== '' && trim($sCity) === trim($bCity) && ($sProv === '' || $bProv === '' || $sProv === $bProv)) {
            return ShippingRatePolicy::ROUTE_SAME_CITY;
        }

        if ($sProv !== '' && $bProv !== '' && $sProv !== $bProv) {
            return ShippingRatePolicy::ROUTE_INTERPROVINCIAL;
        }

        if ($sProv !== '' && $bProv !== '' && $sProv === $bProv) {
            return ShippingRatePolicy::ROUTE_SAME_PROVINCE;
        }

        if ($originRoute && $destinationRoute && (int) ($originRoute['logistics_company_id'] ?? 0) !== (int) ($destinationRoute['logistics_company_id'] ?? 0)) {
            return ShippingRatePolicy::ROUTE_INTERPROVINCIAL;
        }

        return ShippingRatePolicy::ROUTE_SAME_CITY;
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
            $voucherDiscount = round((float) ($order->shipping_voucher_discount ?? 0), 2);
            $funder = $order->shipping_voucher_funder ?: ($order->shippingVoucher?->funding_source ?? 'platform');
            $subsidy = $funder === 'logistics_approved'
                ? 0.00
                : round((float) ($order->platform_shipping_subsidy ?? $voucherDiscount), 2);
            $baseShipping = round((float) ($order->base_shipping_fee ?? ($buyerPaid + $voucherDiscount)), 2);
            $recognizedShipping = $funder === 'logistics_approved' ? $buyerPaid : $baseShipping;

            $totalCents = (int) round($recognizedShipping * 100);

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
