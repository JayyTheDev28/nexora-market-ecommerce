<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $sellerId = $request->user()->id;

        $from = $request->query('from') ? Carbon::parse($request->query('from'))->startOfDay() : now()->startOfMonth();
        $to = $request->query('to') ? Carbon::parse($request->query('to'))->endOfDay() : now()->endOfDay();

        $orders = Order::whereHas('items.product', fn ($q) => $q->where('seller_id', $sellerId))
            ->whereBetween('placed_at', [$from, $to])
            ->with([
                'items' => fn ($q) => $q->whereHas('product', fn ($q2) => $q2->where('seller_id', $sellerId)),
                'commissions' => fn ($q) => $q->where('seller_id', $sellerId),
            ])
            ->get();

        $grossSales = $orders->flatMap->items->sum(fn ($item) => $item->price * $item->quantity);
        $commission = $orders->flatMap->commissions->sum('commission_amount');
        $netPayout = $grossSales - $commission;

        $byStatus = $orders->groupBy('status')->map->count();

        return view('seller.reports.index', [
            'from' => $from,
            'to' => $to,
            'orderCount' => $orders->count(),
            'grossSales' => $grossSales,
            'commission' => $commission,
            'netPayout' => $netPayout,
            'byStatus' => $byStatus,
        ]);
    }
}
