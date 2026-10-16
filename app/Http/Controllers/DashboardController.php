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

    public function logistics(\Illuminate\Http\Request $request): View
    {
        $sortingCenterId = $request->user()->id;

        $stats = [
            'pendingPickups' => Order::where('status', 'ready_for_pickup')->whereNull('pickup_courier_id')->count(),
            'scheduledPickups' => Order::where('status', 'ready_for_pickup')->whereNotNull('pickup_courier_id')->count(),
            'awaitingReceipt' => Order::where('status', 'picked_up')->count(),
            'receivedUnsorted' => Order::where('sorting_center_id', $sortingCenterId)->where('status', 'at_sorting_center')->count(),
            'sortedUnassigned' => Order::where('sorting_center_id', $sortingCenterId)->where('status', 'sorted')->count(),
            'assignedToRider' => Order::where('sorting_center_id', $sortingCenterId)->where('status', 'assigned_to_rider')->count(),
            'outForDelivery' => Order::where('sorting_center_id', $sortingCenterId)->where('status', 'out_for_delivery')->count(),
            'exceptionsCount' => Order::where('sorting_center_id', $sortingCenterId)->whereIn('status', ['delivery_failed', 'returned'])->count(),
            'pendingRiders' => User::where('role', 'courier')->where('approval_status', 'pending')->count(),
        ];

        $pendingPickups = Order::where('status', 'ready_for_pickup')
            ->with(['items.product.seller', 'address', 'pickupCourier'])
            ->latest('placed_at')
            ->limit(5)
            ->get();

        $incomingParcels = Order::where('status', 'picked_up')
            ->with(['buyer', 'address', 'pickupCourier', 'items.product.seller'])
            ->latest('updated_at')
            ->limit(5)
            ->get();

        $unsortedParcels = Order::where('sorting_center_id', $sortingCenterId)
            ->where('status', 'at_sorting_center')
            ->with(['buyer', 'address', 'deliveryArea'])
            ->latest('updated_at')
            ->limit(5)
            ->get();

        $unassignedDeliveries = Order::where('sorting_center_id', $sortingCenterId)
            ->where('status', 'sorted')
            ->with(['buyer', 'address', 'deliveryArea'])
            ->latest('updated_at')
            ->limit(5)
            ->get();

        return view('logistics.dashboard', compact(
            'stats',
            'pendingPickups',
            'incomingParcels',
            'unsortedParcels',
            'unassignedDeliveries'
        ));
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
