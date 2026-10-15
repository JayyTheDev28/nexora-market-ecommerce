<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function buyer(): View
    {
        $buyerId = auth()->id();

        $stats = [
            'cartItems' => auth()->user()->cartItems()->sum('quantity'),
            'activeOrders' => Order::where('buyer_id', $buyerId)->whereNotIn('status', ['completed', 'returned'])->count(),
            'completedOrders' => Order::where('buyer_id', $buyerId)->where('status', 'completed')->count(),
        ];

        $recentOrders = Order::where('buyer_id', $buyerId)
            ->with('items')
            ->latest('placed_at')
            ->limit(5)
            ->get();

        return view('buyer.dashboard.index', compact('stats', 'recentOrders'));
    }

    public function seller(): View
    {
        $sellerId = auth()->id();

        $stats = [
            'activeProducts' => Product::where('seller_id', $sellerId)->where('status', 'active')->count(),
            'lowStock' => Product::where('seller_id', $sellerId)->where('status', 'active')->where('stock', '<=', 10)->count(),
            'newOrders' => Order::whereHas('items.product', fn ($q) => $q->where('seller_id', $sellerId))->where('status', 'placed')->count(),
            'revenueThisMonth' => Order::whereHas('items.product', fn ($q) => $q->where('seller_id', $sellerId))
                ->whereMonth('placed_at', now()->month)
                ->whereYear('placed_at', now()->year)
                ->with(['items' => fn ($q) => $q->whereHas('product', fn ($q2) => $q2->where('seller_id', $sellerId))])
                ->get()
                ->flatMap->items
                ->sum(fn ($item) => $item->price * $item->quantity),
        ];

        $recentOrders = Order::whereHas('items.product', fn ($q) => $q->where('seller_id', $sellerId))
            ->with('buyer')
            ->latest('placed_at')
            ->limit(5)
            ->get();

        return view('seller.dashboard', compact('stats', 'recentOrders'));
    }

    public function logistics(): View
    {
        return view('logistics.dashboard');
    }

    public function admin(): View
    {
        // Per the ERP spec, the admin approves buyer / seller / logistics
        // applications. Couriers wait on the Logistics/Sorting Center's
        // approval instead, so they're deliberately excluded here and
        // surface on the logistics dashboard's rider management instead.
        $pending = User::where('approval_status', 'pending')
            ->whereIn('role', ['buyer', 'seller', 'sorting_center'])
            ->with(['sellerProfile', 'sortingCenterProfile'])
            ->latest()
            ->get();

        return view('admin.dashboard', compact('pending'));
    }
}
