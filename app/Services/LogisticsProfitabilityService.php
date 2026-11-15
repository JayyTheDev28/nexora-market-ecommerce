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

        $routeBuckets = [];

        foreach ($orders as $order) {
            $alloc = $order->interCompanyShippingAllocation;
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
            } else {
                // Single-carrier attribution to collecting logistics company
                $collectingId = (int) ($order->delivery_logistics_id ?: $order->sorting_center_id);
                $companyShare = $collectingId === $companyId
                    ? (int) round((float) ($order->base_shipping_fee ?? $order->shipping_fee) * 100)
                    : 0;
            }

            $recognizedShippingCents += $companyShare;
            $collectingId = (int) ($order->delivery_logistics_id ?: $order->sorting_center_id);
            if ($collectingId === $companyId) {
                $buyerPaidShippingCollectedCents += (int) round((float) $order->shipping_fee * 100);
                $shippingSubsidyCents += (int) round((float) ($order->platform_shipping_subsidy ?? $order->shipping_voucher_discount ?? 0) * 100);
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

        $manifestProfitability = $manifests->map(function (TransferManifest $m) use ($manifestCosts) {
            $tripComp = (float) RiderEarning::where('manifest_id', $m->id)->where('status', '!=', 'reversed')->sum('amount');
            $tripExpenses = (float) ($manifestCosts->get($m->id)?->sum('amount') ?? 0);

            return [
                'manifest_number' => $m->manifest_number,
                'route' => ($m->originHub?->name ?? 'Origin') . ' → ' . ($m->destinationHub?->name ?? 'Destination'),
                'status' => $m->status,
                'parcels' => (int) ($m->items->count() ?: $m->total_parcels),
                'courier_pay' => round($tripComp, 2),
                'direct_expenses' => round($tripExpenses, 2),
                'total_manifest_cost' => round($tripComp + $tripExpenses, 2),
            ];
        })->values();

        return [
            'recognized_shipping_revenue' => round($recognizedShippingCents / 100, 2),
            'buyer_paid_shipping_collected' => round($buyerPaidShippingCollectedCents / 100, 2),
            'platform_shipping_subsidies' => round($shippingSubsidyCents / 100, 2),
            'cod_principal_passthrough' => round($totalCodPrincipalCents / 100, 2),
            'rider_pickup_comp' => round($riderPickupCents / 100, 2),
            'rider_delivery_comp' => round($riderDeliveryCents / 100, 2),
            'rider_failed_comp' => round($riderFailedCents / 100, 2),
            'transfer_courier_comp' => round($transferCompCents / 100, 2),
            'total_courier_comp' => round($totalCompCents / 100, 2),
            'operating_expenses_total' => round($expenseCents / 100, 2),
            'expenses_by_category' => $expensesByCategory,
            'net_operating_contribution' => round($netContributionCents / 100, 2),
            'contribution_note' => 'Operating Contribution (Excludes Unrecorded Overhead)',
            'operating_margin_pct' => $marginPct,
            'delivered_orders_count' => $orders->count(),
            'route_profitability' => $routeRows,
            'unprofitable_routes_count' => collect($routeRows)->where('is_unprofitable', true)->count(),
            'manifest_costs' => $manifestProfitability,
        ];
    }
}
