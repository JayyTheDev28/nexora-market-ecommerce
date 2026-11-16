<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CodCollection;
use App\Models\CodRemittance;
use App\Models\Commission;
use App\Models\CourierPayout;
use App\Models\Dispute;
use App\Models\LogisticsAdminRemittance;
use App\Models\Order;
use App\Models\ParcelIncident;
use App\Models\RiderEarning;
use App\Models\RiskAlert;
use App\Models\SafetyReport;
use App\Models\SellerPayoutRequest;
use App\Models\SellerWarning;
use App\Models\TransferManifest;
use App\Models\User;
use App\Services\FinancialReconciliationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request, FinancialReconciliationService $reconciliationService): View
    {
        $tab = $request->query('tab', 'marketplace');
        $period = $request->query('period', '30_days');
        $customStart = $request->query('start_date');
        $customEnd = $request->query('end_date');
        $sellerId = $request->query('seller_id');
        $logisticsId = $request->query('logistics_id');

        [$startDate, $endDate] = $this->resolveDateRange($period, $customStart, $customEnd);

        // A. Marketplace Reports
        $ordersQuery = Order::whereBetween('placed_at', [$startDate, $endDate])
            ->when($sellerId, fn ($q) => $q->where('seller_id', $sellerId))
            ->when($logisticsId, fn ($q) => $q->where(fn ($lq) => $lq->where('pickup_logistics_id', $logisticsId)->orWhere('delivery_logistics_id', $logisticsId)));

        $totalOrders = (clone $ordersQuery)->count();
        $completedOrders = (clone $ordersQuery)->whereIn('status', ['delivered', 'completed'])->count();
        $cancelledOrReturned = (clone $ordersQuery)->whereIn('status', ['cancelled', 'returned', 'failed_delivery'])->count();
        $grossSales = (float) (clone $ordersQuery)->whereNotIn('status', ['cancelled', 'returned'])->sum('total');
        $avgOrderValue = $totalOrders > 0 ? $grossSales / $totalOrders : 0;

        $commissionsQuery = Commission::whereBetween('created_at', [$startDate, $endDate])
            ->when($sellerId, fn ($q) => $q->where('seller_id', $sellerId));
        $totalCommission = (float) (clone $commissionsQuery)->sum('commission_amount');
        $settledCommission = (float) (clone $commissionsQuery)->where('status', 'settled')->sum('commission_amount');
        $pendingCommission = (float) (clone $commissionsQuery)->where('status', 'pending')->sum('commission_amount');
        $sellerPayouts = (float) (clone $commissionsQuery)->sum('seller_payout');

        $sellerStats = Commission::whereBetween('created_at', [$startDate, $endDate])
            ->when($sellerId, fn ($q) => $q->where('seller_id', $sellerId))
            ->selectRaw('seller_id, COUNT(*) as orders_count, SUM(order_total) as total_volume, SUM(commission_amount) as total_commission, SUM(seller_payout) as total_payout')
            ->groupBy('seller_id')
            ->with('seller.sellerProfile')
            ->orderByDesc('total_volume')
            ->limit(10)
            ->get();

        $ordersByStatus = (clone $ordersQuery)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();

        $categories = Category::withCount('products')->get();

        // B. Logistics Reports
        $logisticsStats = [
            'parcelsProcessed' => (clone $ordersQuery)->whereNotIn('status', ['placed', 'cancelled'])->count(),
            'activeTransfers' => TransferManifest::whereBetween('created_at', [$startDate, $endDate])
                ->when($logisticsId, fn ($q) => $q->where(fn ($lq) => $lq->where('sender_logistics_id', $logisticsId)->orWhere('receiver_logistics_id', $logisticsId)))
                ->whereIn('status', TransferManifest::ACTIVE_STATUSES)->count(),
            'deliveredParcels' => $completedOrders,
            'failedDeliveries' => (clone $ordersQuery)->where('status', 'failed_delivery')->count(),
            'missingOrDamaged' => ParcelIncident::whereBetween('created_at', [$startDate, $endDate])
                ->whereIn('incident_type', ['missing', 'damaged'])->count(),
            'recoveredParcels' => ParcelIncident::whereBetween('created_at', [$startDate, $endDate])
                ->whereNotNull('recovered_at')->count(),
        ];

        // C. Financial Reports
        $reconcileSummary = $reconciliationService->runReconciliationReport();
        $financialStats = [
            'verifiedCodCollections' => (float) CodCollection::whereBetween('collected_at', [$startDate, $endDate])
                ->when($logisticsId, fn ($q) => $q->where('collecting_logistics_id', $logisticsId))
                ->where('status', 'verified')->sum('actual_collected_amount'),
            'pendingRiderCodRemittances' => (float) CodRemittance::whereBetween('remitted_at', [$startDate, $endDate])
                ->where('status', 'pending_verification')->sum('claimed_amount'),
            'verifiedAdminRemittances' => (float) LogisticsAdminRemittance::whereBetween('created_at', [$startDate, $endDate])
                ->when($logisticsId, fn ($q) => $q->where('logistics_id', $logisticsId))
                ->where('status', 'verified')->sum('merchandise_amount'),
            'completedSellerPayouts' => (float) SellerPayoutRequest::whereBetween('created_at', [$startDate, $endDate])
                ->when($sellerId, fn ($q) => $q->where('seller_id', $sellerId))
                ->whereIn('status', ['paid', 'processed'])->sum('amount'),
            'refundsRecorded' => (float) Dispute::whereBetween('created_at', [$startDate, $endDate])
                ->where('return_stage', 'refund_recorded')->sum('refund_amount'),
            'discrepancyCount' => count($reconcileSummary['critical'] ?? []) + count($reconcileSummary['warnings'] ?? []),
        ];

        // D. Courier Reports
        $courierStats = [
            'completedPickups' => RiderEarning::whereBetween('earned_at', [$startDate, $endDate])->where('type', 'pickup')->count(),
            'completedDeliveries' => RiderEarning::whereBetween('earned_at', [$startDate, $endDate])->where('type', 'delivery')->count(),
            'completedHubTransfers' => RiderEarning::whereBetween('earned_at', [$startDate, $endDate])->where('type', 'transfer_trip')->count(),
            'riderCompensation' => (float) RiderEarning::whereBetween('earned_at', [$startDate, $endDate])->whereIn('type', ['pickup', 'delivery', 'failed_delivery'])->sum('amount'),
            'transferCourierCompensation' => (float) RiderEarning::whereBetween('earned_at', [$startDate, $endDate])->where('type', 'transfer_trip')->sum('amount'),
            'courierPayoutsPaid' => (float) CourierPayout::whereBetween('requested_at', [$startDate, $endDate])->where('status', 'paid')->sum('amount'),
        ];

        // E. Safety Reports
        $safetyStats = [
            'reportsSubmitted' => SafetyReport::whereBetween('created_at', [$startDate, $endDate])->count(),
            'openInvestigations' => SafetyReport::whereBetween('created_at', [$startDate, $endDate])->whereIn('status', ['new', 'under_investigation', 'awaiting_evidence', 'escalated'])->count(),
            'resolvedCases' => SafetyReport::whereBetween('created_at', [$startDate, $endDate])->whereIn('status', ['resolved', 'closed'])->count(),
            'suspendedAccounts' => User::where('account_status', 'suspended')->count(),
            'sellerViolations' => SellerWarning::whereBetween('created_at', [$startDate, $endDate])->count(),
            'fraudAlerts' => RiskAlert::whereBetween('detected_at', [$startDate, $endDate])->count(),
        ];

        $sellers = User::where('role', 'seller')->with('sellerProfile')->orderBy('first_name')->get();
        $logisticsCompanies = User::whereIn('role', ['logistics', 'sorting_center'])->with('logisticsProfile')->orderBy('first_name')->get();

        return view('admin.reports.index', compact(
            'tab',
            'period',
            'startDate',
            'endDate',
            'sellerId',
            'logisticsId',
            'sellers',
            'logisticsCompanies',
            'totalOrders',
            'completedOrders',
            'cancelledOrReturned',
            'grossSales',
            'avgOrderValue',
            'totalCommission',
            'settledCommission',
            'pendingCommission',
            'sellerPayouts',
            'sellerStats',
            'ordersByStatus',
            'categories',
            'logisticsStats',
            'financialStats',
            'courierStats',
            'safetyStats'
        ));
    }

    public function export(Request $request): StreamedResponse
    {
        $type = $request->query('type', 'sales');
        $period = $request->query('period', '30_days');
        $sellerId = $request->query('seller_id');
        $logisticsId = $request->query('logistics_id');
        [$startDate, $endDate] = $this->resolveDateRange($period, $request->query('start_date'), $request->query('end_date'));

        $filename = "nexora-{$type}-report-" . now()->format('Y-m-d-His') . ".csv";

        return response()->streamDownload(function () use ($type, $startDate, $endDate, $sellerId, $logisticsId) {
            $handle = fopen('php://output', 'w');

            if ($type === 'commission') {
                fputcsv($handle, ['Commission ID', 'Order Number', 'Seller Name', 'Order Total', 'Commission Rate', 'Commission Amount', 'Seller Payout', 'Status', 'Date']);
                Commission::with(['order', 'seller'])
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->when($sellerId, fn ($q) => $q->where('seller_id', $sellerId))
                    ->chunk(100, function ($rows) use ($handle) {
                        foreach ($rows as $row) {
                            fputcsv($handle, [
                                $row->id,
                                $row->order->order_number ?? 'N/A',
                                $row->seller->full_name ?? 'N/A',
                                number_format($row->order_total, 2, '.', ''),
                                ($row->rate * 100) . '%',
                                number_format($row->commission_amount, 2, '.', ''),
                                number_format($row->seller_payout, 2, '.', ''),
                                ucfirst($row->status),
                                $row->created_at->toDateTimeString(),
                            ]);
                        }
                    });
            } elseif ($type === 'logistics') {
                fputcsv($handle, ['Manifest Number', 'Origin Hub', 'Destination Hub', 'Courier', 'Total Parcels', 'Received Parcels', 'Missing', 'Damaged', 'Status', 'Created At']);
                TransferManifest::with(['originHub', 'destinationHub', 'assignedCourier'])
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->when($logisticsId, fn ($q) => $q->where(fn ($lq) => $lq->where('sender_logistics_id', $logisticsId)->orWhere('receiver_logistics_id', $logisticsId)))
                    ->chunk(100, function ($rows) use ($handle) {
                        foreach ($rows as $row) {
                            fputcsv($handle, [
                                $row->manifest_number,
                                $row->originHub?->name ?? 'N/A',
                                $row->destinationHub?->name ?? 'N/A',
                                $row->assignedCourier?->full_name ?? 'Unassigned',
                                $row->total_parcels,
                                $row->received_parcels,
                                $row->missing_parcels,
                                $row->damaged_parcels,
                                $row->status,
                                $row->created_at->toDateTimeString(),
                            ]);
                        }
                    });
            } elseif ($type === 'financial') {
                fputcsv($handle, ['Reference Number', 'Logistics Company', 'Merchandise Amount', 'Shipping Retained', 'Status', 'Submitted At']);
                LogisticsAdminRemittance::with('logistics.logisticsProfile')
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->when($logisticsId, fn ($q) => $q->where('logistics_id', $logisticsId))
                    ->chunk(100, function ($rows) use ($handle) {
                        foreach ($rows as $row) {
                            fputcsv($handle, [
                                $row->reference_number,
                                $row->logistics?->logisticsProfile?->company_name ?? $row->logistics?->full_name ?? 'N/A',
                                number_format($row->merchandise_amount, 2, '.', ''),
                                number_format($row->shipping_retained, 2, '.', ''),
                                $row->status,
                                $row->created_at->toDateTimeString(),
                            ]);
                        }
                    });
            } elseif ($type === 'courier') {
                fputcsv($handle, ['Earning ID', 'Courier', 'Type', 'Order Number', 'Amount', 'Status', 'Earned At']);
                RiderEarning::with(['courier', 'order'])
                    ->whereBetween('earned_at', [$startDate, $endDate])
                    ->chunk(100, function ($rows) use ($handle) {
                        foreach ($rows as $row) {
                            fputcsv($handle, [
                                $row->id,
                                $row->courier?->full_name ?? 'N/A',
                                $row->type,
                                $row->order?->order_number ?? 'N/A',
                                number_format($row->amount, 2, '.', ''),
                                $row->status,
                                $row->earned_at?->toDateTimeString() ?? '',
                            ]);
                        }
                    });
            } elseif ($type === 'safety') {
                fputcsv($handle, ['Reference', 'Reporter', 'Role', 'Category', 'Priority', 'Status', 'Created At']);
                SafetyReport::with('reporter')
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->chunk(100, function ($rows) use ($handle) {
                        foreach ($rows as $row) {
                            fputcsv($handle, [
                                $row->reference_number,
                                $row->reporter?->full_name ?? 'N/A',
                                $row->reporter_role,
                                $row->category_label,
                                $row->priority,
                                $row->status,
                                $row->created_at->toDateTimeString(),
                            ]);
                        }
                    });
            } else {
                fputcsv($handle, ['Order Number', 'Buyer Name', 'Seller Name', 'Status', 'Payment Method', 'Subtotal', 'Delivery Fee', 'Total Amount', 'Date Placed']);
                Order::with(['buyer', 'seller.sellerProfile'])
                    ->whereBetween('placed_at', [$startDate, $endDate])
                    ->when($sellerId, fn ($q) => $q->where('seller_id', $sellerId))
                    ->when($logisticsId, fn ($q) => $q->where(fn ($lq) => $lq->where('pickup_logistics_id', $logisticsId)->orWhere('delivery_logistics_id', $logisticsId)))
                    ->chunk(100, function ($rows) use ($handle) {
                        foreach ($rows as $row) {
                            fputcsv($handle, [
                                $row->order_number,
                                $row->buyer->full_name ?? 'N/A',
                                $row->seller->sellerProfile->business_name ?? $row->seller->full_name ?? 'N/A',
                                ucfirst(str_replace('_', ' ', $row->status)),
                                strtoupper($row->payment_method ?? 'COD'),
                                number_format($row->subtotal, 2, '.', ''),
                                number_format($row->shipping_fee, 2, '.', ''),
                                number_format($row->total, 2, '.', ''),
                                $row->placed_at?->toDateTimeString() ?? $row->created_at->toDateTimeString(),
                            ]);
                        }
                    });
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function resolveDateRange(string $period, ?string $start, ?string $end): array
    {
        if ($period === 'today') {
            return [Carbon::today()->startOfDay(), Carbon::today()->endOfDay()];
        } elseif ($period === '7_days') {
            return [Carbon::now()->subDays(7)->startOfDay(), Carbon::now()->endOfDay()];
        } elseif ($period === 'this_month') {
            return [Carbon::now()->startOfMonth(), Carbon::now()->endOfDay()];
        } elseif ($period === 'this_year') {
            return [Carbon::now()->startOfYear(), Carbon::now()->endOfDay()];
        } elseif ($period === 'custom' && $start && $end) {
            return [Carbon::parse($start)->startOfDay(), Carbon::parse($end)->endOfDay()];
        }

        return [Carbon::now()->subDays(30)->startOfDay(), Carbon::now()->endOfDay()];
    }
}
