<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Commission;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $period = $request->query('period', '30_days');
        $customStart = $request->query('start_date');
        $customEnd = $request->query('end_date');

        [$startDate, $endDate] = $this->resolveDateRange($period, $customStart, $customEnd);

        // Core Sales Metrics
        $ordersQuery = Order::whereBetween('placed_at', [$startDate, $endDate]);
        $totalOrders = (clone $ordersQuery)->count();
        $completedOrders = (clone $ordersQuery)->where('status', 'completed')->count();
        $cancelledOrReturned = (clone $ordersQuery)->whereIn('status', ['cancelled', 'returned', 'delivery_failed'])->count();
        $grossSales = (float) (clone $ordersQuery)->whereNotIn('status', ['cancelled', 'returned'])->sum('total');
        $avgOrderValue = $totalOrders > 0 ? $grossSales / $totalOrders : 0;

        // Core Commission Metrics
        $commissionsQuery = Commission::whereBetween('created_at', [$startDate, $endDate]);
        $totalCommission = (float) (clone $commissionsQuery)->sum('commission_amount');
        $settledCommission = (float) (clone $commissionsQuery)->where('status', 'settled')->sum('commission_amount');
        $pendingCommission = (float) (clone $commissionsQuery)->where('status', 'pending')->sum('commission_amount');
        $sellerPayouts = (float) (clone $commissionsQuery)->sum('seller_payout');

        // Seller breakdown
        $sellerStats = Commission::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('seller_id, COUNT(*) as orders_count, SUM(order_total) as total_volume, SUM(commission_amount) as total_commission, SUM(seller_payout) as total_payout')
            ->groupBy('seller_id')
            ->with('seller.sellerProfile')
            ->orderByDesc('total_volume')
            ->limit(10)
            ->get();

        // Orders by Status
        $ordersByStatus = Order::whereBetween('placed_at', [$startDate, $endDate])
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();

        // Categories with products
        $categories = Category::withCount('products')->get();

        return view('admin.reports.index', compact(
            'period',
            'startDate',
            'endDate',
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
            'categories'
        ));
    }

    public function export(Request $request): StreamedResponse
    {
        $type = $request->query('type', 'sales');
        $period = $request->query('period', '30_days');
        [$startDate, $endDate] = $this->resolveDateRange($period, $request->query('start_date'), $request->query('end_date'));

        $filename = "nexora-{$type}-report-" . now()->format('Y-m-d-His') . ".csv";

        return response()->streamDownload(function () use ($type, $startDate, $endDate) {
            $handle = fopen('php://output', 'w');

            if ($type === 'commission') {
                fputcsv($handle, ['Commission ID', 'Order Number', 'Seller Name', 'Order Total', 'Commission Rate', 'Commission Amount', 'Seller Payout', 'Status', 'Date']);
                Commission::with(['order', 'seller'])
                    ->whereBetween('created_at', [$startDate, $endDate])
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
            } else {
                fputcsv($handle, ['Order Number', 'Buyer Name', 'Seller Name', 'Status', 'Payment Method', 'Subtotal', 'Delivery Fee', 'Total Amount', 'Date Placed']);
                Order::with(['buyer', 'seller.sellerProfile'])
                    ->whereBetween('placed_at', [$startDate, $endDate])
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

        // default 30 days
        return [Carbon::now()->subDays(30)->startOfDay(), Carbon::now()->endOfDay()];
    }
}
