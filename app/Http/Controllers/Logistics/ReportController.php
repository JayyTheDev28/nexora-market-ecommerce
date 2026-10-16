<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\DeliveryArea;
use App\Models\DeliveryAttempt;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReportController extends Controller
{
    /**
     * Display operational reports and analytics for the sorting center.
     */
    public function index(Request $request): View
    {
        $sortingCenterId = $request->user()->id;

        $fromInput = $request->query('from', now()->subDays(30)->toDateString());
        $toInput = $request->query('to', now()->toDateString());

        $startDate = Carbon::parse($fromInput)->startOfDay();
        $endDate = Carbon::parse($toInput)->endOfDay();

        // 1. Overall volume & status breakdown
        $ordersQuery = Order::where('sorting_center_id', $sortingCenterId)
            ->whereBetween('updated_at', [$startDate, $endDate]);

        $statusCounts = (clone $ordersQuery)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();

        $metrics = [
            'total_processed' => (clone $ordersQuery)->count(),
            'received' => OrderStatusHistory::where('changed_by', $sortingCenterId)
                ->where('status', 'at_sorting_center')
                ->whereBetween('changed_at', [$startDate, $endDate])
                ->count(),
            'sorted' => OrderStatusHistory::where('changed_by', $sortingCenterId)
                ->where('status', 'sorted')
                ->whereBetween('changed_at', [$startDate, $endDate])
                ->count(),
            'assigned' => (clone $ordersQuery)->whereIn('status', ['assigned_to_rider', 'out_for_delivery', 'delivered', 'completed'])->count(),
            'delivered' => (clone $ordersQuery)->whereIn('status', ['delivered', 'completed'])->count(),
            'failed' => (clone $ordersQuery)->where('status', 'delivery_failed')->count(),
            'returned' => (clone $ordersQuery)->where('status', 'returned')->count(),
            'pending_pickups' => Order::where('status', 'ready_for_pickup')->count(),
        ];

        // 2. Deliveries by Area breakdown
        $areaBreakdown = DeliveryArea::where('sorting_center_id', $sortingCenterId)
            ->withCount([
                'orders as total_orders' => fn ($q) => $q->whereBetween('updated_at', [$startDate, $endDate]),
                'orders as delivered_orders' => fn ($q) => $q->whereIn('status', ['delivered', 'completed'])->whereBetween('updated_at', [$startDate, $endDate]),
                'orders as failed_orders' => fn ($q) => $q->where('status', 'delivery_failed')->whereBetween('updated_at', [$startDate, $endDate]),
            ])
            ->get();

        // 3. Rider Performance table
        $riderPerformance = User::where('role', 'courier')
            ->where('approval_status', 'approved')
            ->with(['courierProfile'])
            ->withCount([
                'activeDeliveries as active_count',
                'completedDeliveries as delivered_count' => fn ($q) => $q->whereBetween('updated_at', [$startDate, $endDate]),
            ])
            ->get();

        // 4. Delivery Attempt Reasons
        $failureReasons = DeliveryAttempt::whereHas('order', fn ($q) => $q->where('sorting_center_id', $sortingCenterId))
            ->where('result', 'failed')
            ->whereBetween('attempted_at', [$startDate, $endDate])
            ->select('failure_reason', DB::raw('count(*) as count'))
            ->groupBy('failure_reason')
            ->orderByDesc('count')
            ->get();

        return view('logistics.reports.index', compact(
            'metrics',
            'statusCounts',
            'areaBreakdown',
            'riderPerformance',
            'failureReasons',
            'fromInput',
            'toInput'
        ));
    }
}
