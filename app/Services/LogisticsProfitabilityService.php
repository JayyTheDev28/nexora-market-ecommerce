<?php

namespace App\Services;

use App\Models\InterCompanyShippingAllocation;
use App\Models\LogisticsExpense;
use App\Models\Order;
use App\Models\RiderEarning;
use App\Models\TransferManifest;
use App\Models\User;
use Carbon\CarbonInterface;

class LogisticsProfitabilityService
{
    public function getProfitabilityReport(
        int $companyId,
        CarbonInterface $from,
        CarbonInterface $to,
        ?int $hubId = null
    ): array {
        // 1. Delivered orders touching this logistics company within business event timestamps
        $ordersQuery = Order::whereIn('status', ['delivered', 'completed'])
            ->where(function ($q) use ($companyId) {
                $q->where('delivery_logistics_id', $companyId)
                    ->orWhere('pickup_logistics_id', $companyId)
                    ->orWhere('sorting_center_id', $companyId);
            })
            ->whereBetween(\DB::raw('COALESCE(delivered_at, completed_at, placed_at)'), [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->with(['originHub', 'destinationHub', 'address', 'interCompanyShippingAllocation']);

        if ($hubId) {
            $ordersQuery->where(function ($q) use ($hubId) {
                $q->where('origin_hub_id', $hubId)
                    ->orWhere('destination_hub_id', $hubId)
                    ->orWhere('current_hub_id', $hubId);
            });
        }

        $orders = $ordersQuery->get();

        $recognizedShippingCents = 0;
        $buyerPaidShippingCollectedCents = 0;
        $shippingSubsidyCents = 0;
        $totalCodPrincipalCents = 0;
        $intercompanyNetAllocationCents = 0;
        $missingCostDataCount = 0;

        $routeBuckets = [];
        $orderRows = [];

        foreach ($orders as $order) {
            $alloc = $order->interCompanyShippingAllocation;
            $collectingId = (int) ($order->delivery_logistics_id ?: $order->sorting_center_id);
            $subsidyThisOrderCents = 0;

            if ($alloc) {
                $companyShare = 0;
                if ((int) $alloc->pickup_logistics_id === $companyId) {
                    $companyShare += (int) round((float) $alloc->pickup_share_amount * 100);
                }
                if ((int) $alloc->transfer_logistics_id === $companyId) {
                    $companyShare += (int) round((float) $alloc->transfer_share_amount * 100);
                }
                if ((int) $alloc->delivery_logistics_id === $companyId) {
                    $companyShare += (int) round((float) $alloc->delivery_share_amount * 100);
                }
                if ($collectingId === $companyId) {
                    $intercompanyNetAllocationCents += ($companyShare - (int) round((float) $alloc->total_shipping_fee * 100));
                } else {
                    $intercompanyNetAllocationCents += $companyShare;
                }
                $subsidyThisOrderCents = (int) round((float) $alloc->platform_shipping_subsidy * 100);
            } else {
                // Single-carrier attribution to collecting logistics company
                $companyShare = $collectingId === $companyId
                    ? (int) round((float) ($order->base_shipping_fee ?? $order->shipping_fee) * 100)
                    : 0;
                $subsidyThisOrderCents = (int) round((float) ($order->platform_shipping_subsidy ?? $order->shipping_voucher_discount ?? 0) * 100);
            }

            $recognizedShippingCents += $companyShare;
            if ($collectingId === $companyId) {
                $buyerPaidShippingCollectedCents += (int) round((float) $order->shipping_fee * 100);
                $shippingSubsidyCents += $subsidyThisOrderCents;
                $totalCodPrincipalCents += (int) round((float) $order->total * 100);
            }

            // Route bucket for route profitability
            $originLabel = $order->originHub?->name ?? 'Origin Hub';
            $destLabel = $order->destinationHub?->name ?? ($order->address?->municipality_name ?: 'Local Delivery');
            $routeKey = "{$originLabel} → {$destLabel}";

            if (! isset($routeBuckets[$routeKey])) {
                $routeBuckets[$routeKey] = [
                    'route' => $routeKey,
                    'orders_count' => 0,
                    'shipping_revenue_cents' => 0,
                    'order_ids' => [],
                ];
            }
            $routeBuckets[$routeKey]['orders_count']++;
            $routeBuckets[$routeKey]['shipping_revenue_cents'] += $companyShare;
            $routeBuckets[$routeKey]['order_ids'][] = $order->id;

            $orderRows[$order->id] = [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'is_realized' => true,
                'route_type' => $order->shipping_route_type ?? 'standard',
                'route' => $routeKey,
                'allocated_shipping_revenue_cents' => $companyShare,
                'subsidies_cents' => $subsidyThisOrderCents,
                'has_quote_breakdown' => ! empty($order->shipping_quote_breakdown),
                'estimated_hub_cost_cents' => (int) round((float) ($order->shipping_quote_breakdown['estimated_hub_cost'] ?? 0) * 100),
            ];
        }

        // 2. Courier Compensation incurred strictly by this logistics company during the period
        $earnings = RiderEarning::whereBetween('earned_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->where(function ($q) use ($companyId) {
                $q->where('logistics_company_id', $companyId)
                    ->orWhere(function ($legacy) use ($companyId) {
                        $legacy->whereNull('logistics_company_id')
                            ->whereHas('courier.courierProfile', function ($pq) use ($companyId) {
                                $pq->where('logistics_company_id', $companyId)->orWhere('sorting_center_id', $companyId);
                            });
                    });
            })
            ->where('status', '!=', 'reversed')
            ->get();

        $riderPickupCents = (int) round((float) $earnings->where('type', 'pickup')->sum('amount') * 100);
        $riderDeliveryCents = (int) round((float) $earnings->where('type', 'delivery')->sum('amount') * 100);
        $riderFailedCents = (int) round((float) $earnings->where('type', 'failed_delivery')->sum('amount') * 100);
        $transferCompCents = (int) round((float) $earnings->whereIn('type', ['transfer_trip', 'transfer_exception'])->sum('amount') * 100);
        $totalCompCents = $riderPickupCents + $riderDeliveryCents + $riderFailedCents + $transferCompCents;

        // 3. Real Operating Expenses
        $expensesQuery = LogisticsExpense::where('logistics_company_id', $companyId)
            ->where('status', 'recorded')
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()]);
        if ($hubId) {
            $expensesQuery->where('hub_id', $hubId);
        }
        $expenses = $expensesQuery->get();

        $expenseCents = (int) round((float) $expenses->sum('amount') * 100);
        $expensesByCategory = $expenses->groupBy('category')->map(fn ($group, $cat) => [
            'category' => $cat,
            'label' => LogisticsExpense::CATEGORIES[$cat] ?? ucfirst(str_replace('_', ' ', $cat)),
            'total' => round((float) $group->sum('amount'), 2),
            'count' => $group->count(),
        ])->values();

        // 4. Net Operating Contribution = Recognized Shipping Revenue - Courier Comp - Operating Expenses
        $netContributionCents = $recognizedShippingCents - $totalCompCents - $expenseCents;
        $marginPct = $recognizedShippingCents > 0
            ? round(($netContributionCents / $recognizedShippingCents) * 100, 2)
            : 0.00;

        // 5. Compute Route-Level Profitability & Flag Unprofitable Routes
        $earningsByOrder = $earnings->whereNotNull('order_id')->groupBy('order_id');
        $routeRows = [];
        foreach ($routeBuckets as $bucket) {
            $routeCompCents = 0;
            foreach ($bucket['order_ids'] as $oid) {
                $routeCompCents += (int) round((float) ($earningsByOrder->get($oid)?->sum('amount') ?? 0) * 100);
            }
            $routeNetCents = $bucket['shipping_revenue_cents'] - $routeCompCents;
            $rev = round($bucket['shipping_revenue_cents'] / 100, 2);
            $cost = round($routeCompCents / 100, 2);
            $net = round($routeNetCents / 100, 2);

            $routeRows[] = [
                'route' => $bucket['route'],
                'orders_count' => $bucket['orders_count'],
                'shipping_revenue' => $rev,
                'direct_courier_cost' => $cost,
                'net_contribution' => $net,
                'margin_pct' => $rev > 0 ? round(($net / $rev) * 100, 1) : 0.0,
                'is_unprofitable' => $net < 0,
            ];
        }
        usort($routeRows, fn ($a, $b) => $a['net_contribution'] <=> $b['net_contribution']);

        // Also compute transfer manifest lane costs vs order revenue
        $manifestCosts = $expenses->whereNotNull('manifest_id')->groupBy('manifest_id');
        $manifests = TransferManifest::where('sender_logistics_id', $companyId)
            ->whereBetween(\DB::raw('COALESCE(completed_at, arrived_at, dispatched_at, created_at)'), [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->with(['originHub', 'destinationHub', 'items'])
            ->get();

        $lowVolumeManifestsCount = 0;
        $manifestProfitability = $manifests->map(function (TransferManifest $m) use ($manifestCosts, &$lowVolumeManifestsCount) {
            $tripComp = (float) RiderEarning::where('manifest_id', $m->id)->where('status', '!=', 'reversed')->sum('amount');
            $tripExpenses = (float) ($manifestCosts->get($m->id)?->sum('amount') ?? 0);
            $actualTripCost = round($tripComp + $tripExpenses, 2);
            $actualParcels = (int) ($m->items->count() ?: $m->total_parcels);
            $expectedParcels = (int) ($m->expected_parcel_count ?: 20);
            $estimatedTripCost = $m->estimated_trip_cost !== null ? round((float) $m->estimated_trip_cost, 2) : null;
            $expectedCostPerParcel = $m->expected_cost_per_parcel !== null
                ? round((float) $m->expected_cost_per_parcel, 2)
                : ($estimatedTripCost !== null ? round($estimatedTripCost / max(1, $expectedParcels), 2) : null);
            $actualCostPerParcel = $actualParcels > 0 ? round($actualTripCost / $actualParcels, 2) : 0.00;
            $lowVolumeWarning = $actualParcels > 0 && $actualParcels < max(1, (int) ceil($expectedParcels * 0.5));
            if ($lowVolumeWarning) {
                $lowVolumeManifestsCount++;
            }

            return [
                'manifest_number' => $m->manifest_number,
                'route' => ($m->originHub?->name ?? 'Origin') . ' → ' . ($m->destinationHub?->name ?? 'Destination'),
                'status' => $m->status,
                'parcels' => $actualParcels,
                'expected_parcels' => $expectedParcels,
                'distance_km' => $m->distance_km !== null ? round((float) $m->distance_km, 2) : null,
                'courier_pay' => round($tripComp, 2),
                'direct_expenses' => round($tripExpenses, 2),
                'total_manifest_cost' => $actualTripCost,
                'estimated_trip_cost' => $estimatedTripCost,
                'expected_cost_per_parcel' => $expectedCostPerParcel,
                'actual_cost_per_parcel' => $actualCostPerParcel,
                'low_volume_warning' => $lowVolumeWarning,
            ];
        })->values();

        // Build per-order profitability breakdown (Realized delivered orders + Projected in-transit/active orders)
        $orderProfitability = [];
        foreach ($orderRows as $oid => $row) {
            $orderEarnings = $earningsByOrder->get($oid) ?? collect();
            $pickupCents = (int) round((float) $orderEarnings->where('type', 'pickup')->sum('amount') * 100);
            $deliveryCents = (int) round((float) $orderEarnings->where('type', 'delivery')->sum('amount') * 100);
            $failedCents = (int) round((float) $orderEarnings->where('type', 'failed_delivery')->sum('amount') * 100);
            $transferAllocCents = (int) round((float) $orderEarnings->whereIn('type', ['transfer_trip', 'transfer_exception'])->sum('amount') * 100);
            $totalDirectCostCents = $pickupCents + $deliveryCents + $failedCents + $transferAllocCents;
            $revCents = $row['allocated_shipping_revenue_cents'];
            $netCents = $revCents - $totalDirectCostCents;
            $missingData = ($totalDirectCostCents === 0 && ! $row['has_quote_breakdown']);
            if ($missingData) {
                $missingCostDataCount++;
            }
            $rev = round($revCents / 100, 2);
            $net = round($netCents / 100, 2);

            $orderProfitability[] = [
                'order_number' => $row['order_number'],
                'status' => $row['status'],
                'is_realized' => true,
                'route_type' => $row['route_type'],
                'route' => $row['route'],
                'allocated_shipping_revenue' => $rev,
                'subsidies' => round($row['subsidies_cents'] / 100, 2),
                'pickup_cost' => round($pickupCents / 100, 2),
                'delivery_cost' => round(($deliveryCents + $failedCents) / 100, 2),
                'transfer_cost' => round($transferAllocCents / 100, 2),
                'hub_cost' => round($row['estimated_hub_cost_cents'] / 100, 2),
                'total_operating_cost' => round($totalDirectCostCents / 100, 2),
                'net_profit' => $net,
                'margin_pct' => $rev > 0 ? round(($net / $rev) * 100, 1) : 0.0,
                'is_unprofitable' => $net < 0,
                'missing_cost_data' => $missingData,
            ];
        }

        // Projected profit from active in-flight orders touching this company
        $activeOrders = Order::whereNotIn('status', ['delivered', 'completed', 'cancelled', 'returned', 'return_to_sender'])
            ->where(function ($q) use ($companyId) {
                $q->where('delivery_logistics_id', $companyId)
                    ->orWhere('pickup_logistics_id', $companyId)
                    ->orWhere('sorting_center_id', $companyId);
            })
            ->whereBetween('placed_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->with(['originHub', 'destinationHub', 'address'])
            ->get();

        $projectedRevenueCents = 0;
        $projectedCostCents = 0;
        foreach ($activeOrders as $ao) {
            $qb = is_array($ao->shipping_quote_breakdown) ? $ao->shipping_quote_breakdown : null;
            $rev = round((float) ($ao->base_shipping_fee ?? $ao->shipping_fee), 2);
            $revCents = (int) round($rev * 100);
            $estCost = $qb ? (float) ($qb['total_estimated_cost'] ?? 0) : null;
            $estCostCents = $estCost !== null ? (int) round($estCost * 100) : 0;

            $projectedRevenueCents += $revCents;
            $projectedCostCents += $estCostCents;

            $originLabel = $ao->originHub?->name ?? 'Origin Hub';
            $destLabel = $ao->destinationHub?->name ?? ($ao->address?->municipality_name ?: 'Local Delivery');
            $netProj = round(($revCents - $estCostCents) / 100, 2);

            $orderProfitability[] = [
                'order_number' => $ao->order_number,
                'status' => $ao->status,
                'is_realized' => false,
                'route_type' => $ao->shipping_route_type ?? ($qb['route_type'] ?? 'standard'),
                'route' => "{$originLabel} → {$destLabel}",
                'allocated_shipping_revenue' => $rev,
                'subsidies' => round((float) ($ao->platform_shipping_subsidy ?? $ao->shipping_voucher_discount ?? 0), 2),
                'pickup_cost' => round((float) ($qb['estimated_pickup_cost'] ?? 0), 2),
                'delivery_cost' => round((float) ($qb['estimated_delivery_cost'] ?? 0), 2),
                'transfer_cost' => round((float) ($qb['estimated_transfer_cost'] ?? 0), 2),
                'hub_cost' => round((float) ($qb['estimated_hub_cost'] ?? 0), 2),
                'total_operating_cost' => round($estCostCents / 100, 2),
                'net_profit' => $netProj,
                'margin_pct' => $rev > 0 ? round(($netProj / $rev) * 100, 1) : 0.0,
                'is_unprofitable' => $estCost !== null && $netProj < 0,
                'missing_cost_data' => $qb === null,
            ];
            if ($qb === null) {
                $missingCostDataCount++;
            }
        }

        $missingDataWarnings = [];
        if ($missingCostDataCount > 0) {
            $missingDataWarnings[] = "{$missingCostDataCount} order(s) are missing itemized cost snapshots or courier ledger entries; profit cannot be estimated reliably for those orders.";
        }
        if ($lowVolumeManifestsCount > 0) {
            $missingDataWarnings[] = "{$lowVolumeManifestsCount} transfer manifest(s) operated below 50% expected parcel capacity (high fixed trip cost per parcel).";
        }
        if ($manifests->contains(fn (TransferManifest $m) => $m->distance_km === null)) {
            $missingDataWarnings[] = 'One or more transfer manifests are missing route distance (km) records.';
        }

        $projectedProfit = round(($projectedRevenueCents - $projectedCostCents) / 100, 2);

        return [
            'recognized_shipping_revenue' => round($recognizedShippingCents / 100, 2),
            'buyer_paid_shipping_collected' => round($buyerPaidShippingCollectedCents / 100, 2),
            'platform_shipping_subsidies' => round($shippingSubsidyCents / 100, 2),
            'intercompany_net_allocation' => round($intercompanyNetAllocationCents / 100, 2),
            'cod_principal_passthrough' => round($totalCodPrincipalCents / 100, 2),
            'rider_pickup_comp' => round($riderPickupCents / 100, 2),
            'rider_delivery_comp' => round($riderDeliveryCents / 100, 2),
            'rider_failed_comp' => round($riderFailedCents / 100, 2),
            'transfer_courier_comp' => round($transferCompCents / 100, 2),
            'total_courier_comp' => round($totalCompCents / 100, 2),
            'operating_expenses_total' => round($expenseCents / 100, 2),
            'expenses_by_category' => $expensesByCategory,
            'net_operating_contribution' => round($netContributionCents / 100, 2),
            'realized_profit' => round($netContributionCents / 100, 2),
            'projected_profit' => $projectedProfit,
            'projected_revenue' => round($projectedRevenueCents / 100, 2),
            'projected_orders_count' => $activeOrders->count(),
            'contribution_note' => 'Operating Contribution (Excludes Unrecorded Overhead)',
            'operating_margin_pct' => $marginPct,
            'delivered_orders_count' => $orders->count(),
            'route_profitability' => $routeRows,
            'unprofitable_routes_count' => collect($routeRows)->where('is_unprofitable', true)->count(),
            'order_profitability' => $orderProfitability,
            'manifest_costs' => $manifestProfitability,
            'missing_data_warnings' => $missingDataWarnings,
        ];
    }
}
