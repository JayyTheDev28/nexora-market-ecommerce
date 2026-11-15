<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\CodCollection;
use App\Models\CodRemittance;
use App\Models\CourierPayout;
use App\Models\DeliveryArea;
use App\Models\DeliveryAttempt;
use App\Models\Hub;
use App\Models\LogisticsAdminRemittance;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\RiderEarning;
use App\Models\TransferManifest;
use App\Models\User;
use App\Services\FinancialReconciliationService;
use App\Services\LogisticsProfitabilityService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        protected LogisticsProfitabilityService $profitabilityService,
        protected FinancialReconciliationService $reconciliationService,
    ) {}

    public function index(Request $request): View
    {
        $data = $this->buildReportData($request);

        return view('logistics.reports.index', $data);
    }

    public function export(Request $request): StreamedResponse
    {
        $data = $this->buildReportData($request);
        $reportType = $request->query('type', 'profitability');
        $filename = "nexora-logistics-{$reportType}-{$data['fromInput']}-to-{$data['toInput']}.csv";

        return response()->streamDownload(function () use ($data, $reportType) {
            $out = fopen('php://output', 'w');

            if ($reportType === 'cod_reconciliation') {
                fputcsv($out, ['Remittance #', 'Rider', 'Channel', 'External Ref', 'Claimed (PHP)', 'Expected (PHP)', 'Actual Verified (PHP)', 'Shortage (PHP)', 'Overpayment (PHP)', 'Status', 'Remitted At']);
                foreach ($data['codReport']['remittances'] as $r) {
                    fputcsv($out, [
                        $r->remittance_number,
                        $r->courier?->full_name,
                        $r->payment_method ?? 'physical_cash',
                        $r->external_reference ?? '',
                        number_format((float) ($r->claimed_amount ?? $r->amount), 2, '.', ''),
                        number_format((float) $r->expected_amount, 2, '.', ''),
                        $r->actual_received_amount !== null ? number_format((float) $r->actual_received_amount, 2, '.', '') : '',
                        number_format((float) $r->shortage_amount, 2, '.', ''),
                        number_format((float) $r->overpayment_amount, 2, '.', ''),
                        $r->status,
                        $r->remitted_at?->toDateTimeString(),
                    ]);
                }
            } elseif ($reportType === 'admin_settlements') {
                fputcsv($out, ['Batch Ref #', 'Method', 'Transfer Ref', 'Total COD (PHP)', 'Shipping Retained (PHP)', 'Merchandise (PHP)', 'Actual Received (PHP)', 'Status', 'Reviewed At']);
                foreach ($data['adminReport']['batches'] as $b) {
                    fputcsv($out, [
                        $b->reference_number,
                        $b->transfer_method,
                        $b->transfer_reference,
                        number_format((float) $b->total_cod_amount, 2, '.', ''),
                        number_format((float) $b->shipping_retained, 2, '.', ''),
                        number_format((float) $b->merchandise_amount, 2, '.', ''),
                        $b->actual_received_amount !== null ? number_format((float) $b->actual_received_amount, 2, '.', '') : '',
                        $b->status,
                        $b->reviewed_at?->toDateTimeString() ?? '',
                    ]);
                }
            } elseif ($reportType === 'rider_performance') {
                fputcsv($out, ['Rider', 'Deliveries', 'COD Collected (PHP)', 'Verified Remitted (PHP)', 'Outstanding COD (PHP)', 'Earnings (PHP)']);
                foreach ($data['riderPerformance'] as $r) {
                    fputcsv($out, [
                        $r->full_name,
                        $r->delivered_count,
                        number_format((float) ($r->cod_collected_total ?? 0), 2, '.', ''),
                        number_format((float) ($r->cod_verified_total ?? 0), 2, '.', ''),
                        number_format((float) ($r->courierProfile?->unremitted_cod_balance ?? 0), 2, '.', ''),
                        number_format((float) ($r->period_earnings_total ?? 0), 2, '.', ''),
                    ]);
                }
            } elseif ($reportType === 'courier_performance') {
                fputcsv($out, ['Transfer Courier', 'Completed Manifests', 'Exception Manifests', 'Parcels Moved', 'Compensation Earned (PHP)', 'Paid Out (PHP)']);
                foreach ($data['transferCourierPerformance'] as $tc) {
                    fputcsv($out, [
                        $tc['name'],
                        $tc['completed_manifests'],
                        $tc['exception_manifests'],
                        $tc['parcels_moved'],
                        number_format($tc['period_earnings'], 2, '.', ''),
                        number_format($tc['paid_out'], 2, '.', ''),
                    ]);
                }
            } else {
                // Profitability summary + route breakdown CSV matching dashboard figures identically
                $prof = $data['profitability'];
                fputcsv($out, ['Metric', 'Amount (PHP)']);
                fputcsv($out, ['Recognized Shipping Revenue', number_format($prof['recognized_shipping_revenue'], 2, '.', '')]);
                fputcsv($out, ['Buyer-Paid Shipping Collected', number_format($prof['buyer_paid_shipping_collected'], 2, '.', '')]);
                fputcsv($out, ['Platform Shipping Subsidies', number_format($prof['platform_shipping_subsidies'], 2, '.', '')]);
                fputcsv($out, ['COD Principal Passthrough (Excluded from Revenue)', number_format($prof['cod_principal_passthrough'], 2, '.', '')]);
                fputcsv($out, ['Rider Pickup Compensation', number_format($prof['rider_pickup_comp'], 2, '.', '')]);
                fputcsv($out, ['Rider Delivery Compensation', number_format($prof['rider_delivery_comp'], 2, '.', '')]);
                fputcsv($out, ['Transfer Courier Compensation', number_format($prof['transfer_courier_comp'], 2, '.', '')]);
                fputcsv($out, ['Total Courier Compensation', number_format($prof['total_courier_comp'], 2, '.', '')]);
                fputcsv($out, ['Operating Expenses', number_format($prof['operating_expenses_total'], 2, '.', '')]);
                fputcsv($out, ['Net Operating Contribution', number_format($prof['net_operating_contribution'], 2, '.', '')]);
                fputcsv($out, []);
                fputcsv($out, ['Route', 'Orders', 'Shipping Revenue (PHP)', 'Direct Courier Cost (PHP)', 'Net Contribution (PHP)', 'Margin %', 'Unprofitable Flag']);
                foreach ($prof['route_profitability'] as $route) {
                    fputcsv($out, [
                        $route['route'],
                        $route['orders_count'],
                        number_format($route['shipping_revenue'], 2, '.', ''),
                        number_format($route['direct_courier_cost'], 2, '.', ''),
                        number_format($route['net_contribution'], 2, '.', ''),
                        $route['margin_pct'],
                        $route['is_unprofitable'] ? 'UNPROFITABLE' : 'OK',
                    ]);
                }
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    protected function buildReportData(Request $request): array
    {
        $companyId = $request->user()->logisticsCompanyId();

        $fromInput = $request->query('from', now()->subDays(30)->toDateString());
        $toInput = $request->query('to', now()->toDateString());
        $hubId = $request->filled('hub_id') ? (int) $request->query('hub_id') : null;
        $courierFilterId = $request->filled('courier_id') ? (int) $request->query('courier_id') : null;
        $statusFilter = $request->query('status');
        $activeTab = $request->query('tab', 'operations');

        $startDate = Carbon::parse($fromInput)->startOfDay();
        $endDate = Carbon::parse($toInput)->endOfDay();

        // 1. Operational Throughput using business-event timestamps (COALESCE(delivered_at, completed_at, placed_at))
        $ordersQuery = Order::where(function ($q) use ($companyId) {
            $q->where('sorting_center_id', $companyId)
                ->orWhere('pickup_logistics_id', $companyId)
                ->orWhere('delivery_logistics_id', $companyId);
        })->whereBetween(DB::raw('COALESCE(delivered_at, completed_at, placed_at)'), [$startDate, $endDate]);

        if ($hubId) {
            $ordersQuery->where(fn ($q) => $q->where('origin_hub_id', $hubId)->orWhere('destination_hub_id', $hubId)->orWhere('current_hub_id', $hubId));
        }

        $statusCounts = (clone $ordersQuery)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();

        $metrics = [
            'total_processed' => (clone $ordersQuery)->count(),
            'received' => OrderStatusHistory::where('status', 'at_sorting_center')
                ->whereBetween('changed_at', [$startDate, $endDate])
                ->whereHas('order', fn ($q) => $q->where(fn ($oq) => $oq->where('sorting_center_id', $companyId)->orWhere('pickup_logistics_id', $companyId)->orWhere('delivery_logistics_id', $companyId)))
                ->count(),
            'sorted' => OrderStatusHistory::where('status', 'sorted')
                ->whereBetween('changed_at', [$startDate, $endDate])
                ->whereHas('order', fn ($q) => $q->where(fn ($oq) => $oq->where('sorting_center_id', $companyId)->orWhere('pickup_logistics_id', $companyId)->orWhere('delivery_logistics_id', $companyId)))
                ->count(),
            'assigned' => (clone $ordersQuery)->whereIn('status', ['assigned_to_rider', 'out_for_delivery', 'delivered', 'completed'])->count(),
            'delivered' => (clone $ordersQuery)->whereIn('status', ['delivered', 'completed'])->count(),
            'failed' => (clone $ordersQuery)->whereIn('status', ['failed_delivery', 'delivery_failed'])->count(),
            'returned' => (clone $ordersQuery)->whereIn('status', ['return_to_sender', 'returned'])->count(),
            'pending_pickups' => Order::where(fn ($q) => $q->where('pickup_logistics_id', $companyId)->orWhere('sorting_center_id', $companyId))
                ->where('status', 'ready_for_pickup')
                ->count(),
        ];

        $areaBreakdown = DeliveryArea::where('sorting_center_id', $companyId)
            ->withCount([
                'orders as total_orders' => fn ($q) => $q->whereBetween(DB::raw('COALESCE(delivered_at, placed_at)'), [$startDate, $endDate]),
                'orders as delivered_orders' => fn ($q) => $q->whereIn('status', ['delivered', 'completed'])->whereBetween(DB::raw('COALESCE(delivered_at, completed_at, placed_at)'), [$startDate, $endDate]),
                'orders as failed_orders' => fn ($q) => $q->whereIn('status', ['failed_delivery', 'delivery_failed'])->whereBetween('placed_at', [$startDate, $endDate]),
            ])
            ->get();

        $failureReasons = DeliveryAttempt::whereHas('order', fn ($q) => $q->where('sorting_center_id', $companyId)->orWhere('delivery_logistics_id', $companyId))
            ->where('result', 'failed')
            ->whereBetween('attempted_at', [$startDate, $endDate])
            ->select('failure_reason', DB::raw('count(*) as count'))
            ->groupBy('failure_reason')
            ->orderByDesc('count')
            ->get();

        // 2. Rider Performance Report (role='rider' only, company-scoped)
        $riderPerformance = User::where('role', 'rider')
            ->where('approval_status', 'approved')
            ->whereHas('courierProfile', fn ($q) => $q->where('logistics_company_id', $companyId)->orWhere('sorting_center_id', $companyId))
            ->when($courierFilterId, fn ($q) => $q->where('id', $courierFilterId))
            ->with(['courierProfile'])
            ->withCount([
                'activeDeliveries as active_count',
                'completedDeliveries as delivered_count' => fn ($q) => $q->whereBetween(DB::raw('COALESCE(delivered_at, completed_at)'), [$startDate, $endDate]),
            ])
            ->get()
            ->map(function (User $rider) use ($startDate, $endDate) {
                $rider->cod_collected_total = (float) CodCollection::where('courier_id', $rider->id)
                    ->whereBetween('collected_at', [$startDate, $endDate])
                    ->sum('actual_collected_amount');
                $rider->cod_verified_total = (float) CodCollection::where('courier_id', $rider->id)
                    ->whereBetween('collected_at', [$startDate, $endDate])
                    ->sum('verified_remitted_amount');
                $rider->period_earnings_total = (float) RiderEarning::where('courier_id', $rider->id)
                    ->whereBetween('earned_at', [$startDate, $endDate])
                    ->where('status', '!=', 'reversed')
                    ->sum('amount');

                return $rider;
            });

        // 3. Transfer Courier Performance Report (role='courier' ONLY)
        $transferCouriers = User::where('role', 'courier')
            ->where('approval_status', 'approved')
            ->whereHas('courierProfile', fn ($q) => $q->where('logistics_company_id', $companyId)->orWhere('sorting_center_id', $companyId))
            ->when($courierFilterId, fn ($q) => $q->where('id', $courierFilterId))
            ->with('courierProfile')
            ->get();

        $transferCourierPerformance = $transferCouriers->map(function (User $tc) use ($startDate, $endDate, $companyId) {
            $manifests = TransferManifest::where('assigned_courier_id', $tc->id)
                ->where(function ($q) use ($companyId) {
                    $q->where('sender_logistics_id', $companyId)
                        ->orWhere('receiver_logistics_id', $companyId);
                })
                ->whereBetween(DB::raw('COALESCE(completed_at, arrived_at, dispatched_at, created_at)'), [$startDate, $endDate])
                ->withCount('items')
                ->get();

            return [
                'id' => $tc->id,
                'name' => $tc->full_name,
                'completed_manifests' => $manifests->where('status', TransferManifest::STATUS_COMPLETED)->count(),
                'exception_manifests' => $manifests->where('status', TransferManifest::STATUS_EXCEPTION)->count(),
                'parcels_moved' => (int) $manifests->sum('items_count'),
                'period_earnings' => (float) RiderEarning::where('courier_id', $tc->id)
                    ->where('logistics_company_id', $companyId)
                    ->whereBetween('earned_at', [$startDate, $endDate])
                    ->where('status', '!=', 'reversed')
                    ->sum('amount'),
                'paid_out' => (float) CourierPayout::where('courier_id', $tc->id)
                    ->where('logistics_company_id', $companyId)
                    ->where('status', CourierPayout::STATUS_PAID)
                    ->whereBetween('paid_at', [$startDate, $endDate])
                    ->sum('amount'),
            ];
        })->values();

        // 4. COD Reconciliation Report
        $remitQuery = CodRemittance::where('sorting_center_id', $companyId)
            ->whereBetween('remitted_at', [$startDate, $endDate])
            ->with(['courier']);
        if ($statusFilter) {
            $remitQuery->where('status', $statusFilter);
        }
        $codRemittances = $remitQuery->latest('remitted_at')->get();

        $codReport = [
            'remittances' => $codRemittances,
            'collected_in_period' => (float) CodCollection::where('collecting_logistics_id', $companyId)
                ->whereBetween('collected_at', [$startDate, $endDate])
                ->sum('actual_collected_amount'),
            'verified_remitted_total' => (float) $codRemittances->whereIn('status', CodRemittance::VERIFIED_STATUSES)->sum(fn ($r) => (float) ($r->actual_received_amount ?? $r->amount)),
            'pending_verification_total' => (float) $codRemittances->whereIn('status', CodRemittance::OPEN_STATUSES)->sum('amount'),
            'shortages_total' => (float) $codRemittances->sum('shortage_amount'),
            'overpayments_total' => (float) $codRemittances->sum('overpayment_amount'),
        ];

        // 5. Logistics-to-Admin Financial Settlement Report
        $adminBatches = LogisticsAdminRemittance::where('logistics_id', $companyId)
            ->whereBetween(DB::raw('COALESCE(reviewed_at, created_at)'), [$startDate, $endDate])
            ->latest()
            ->get();

        $adminReport = [
            'batches' => $adminBatches,
            'total_merchandise_verified' => (float) $adminBatches->where('status', 'verified')->sum('merchandise_amount'),
            'total_shipping_retained' => (float) $adminBatches->where('status', 'verified')->sum('shipping_retained'),
            'pending_admin_verification' => (float) $adminBatches->whereIn('status', ['submitted', 'under_review', 'disputed'])->sum('merchandise_amount'),
        ];

        // 6. Profitability, Expenses & Balance Reconciliation Integrity
        $profitability = $this->profitabilityService->getProfitabilityReport($companyId, $startDate, $endDate, $hubId);
        $reconciliation = $this->reconciliationService->runReconciliationReport($companyId);
        $hubs = Hub::where('logistics_company_id', $companyId)->orderBy('name')->get();

        return compact(
            'metrics',
            'statusCounts',
            'areaBreakdown',
            'riderPerformance',
            'transferCourierPerformance',
            'failureReasons',
            'codReport',
            'adminReport',
            'profitability',
            'reconciliation',
            'hubs',
            'fromInput',
            'toInput',
            'hubId',
            'courierFilterId',
            'statusFilter',
            'activeTab'
        );
    }
}
