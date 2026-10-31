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
        $seller = auth()->user();
        $sellerId = $seller->id;

        $stats = [
            'activeProducts' => Product::where('seller_id', $sellerId)->where('status', 'active')->count(),
            'lowStock' => Product::where('seller_id', $sellerId)->where('status', 'active')->whereColumn('stock', '<=', 'low_stock_threshold')->where('stock', '>', 0)->count(),
            'newOrders' => Order::where('seller_id', $sellerId)->where('status', 'placed')->count(),
            'ordersToPack' => Order::where('seller_id', $sellerId)->whereIn('status', ['confirmed', 'preparing'])->count(),
            'readyForPickup' => Order::where('seller_id', $sellerId)->where('status', 'ready_for_pickup')->count(),
            'openReturns' => \App\Models\Dispute::whereHas('order', fn ($q) => $q->where('seller_id', $sellerId))->where('seller_status', 'pending')->count(),
            'revenueThisMonth' => (float) Order::where('seller_id', $sellerId)
                ->whereNotIn('status', ['cancelled', 'returned'])
                ->whereMonth('placed_at', now()->month)
                ->whereYear('placed_at', now()->year)
                ->sum('total'),
            'vacationMode' => (bool) ($seller->sellerProfile?->is_vacation_mode ?? false),
            'vacationNotice' => $seller->sellerProfile?->vacation_notice,
        ];

        $recentOrders = Order::where(function ($q) use ($sellerId) {
            $q->where('seller_id', $sellerId)
              ->orWhereHas('items.product', fn ($sq) => $sq->where('seller_id', $sellerId));
        })
        ->with(['buyer', 'items'])
        ->latest('placed_at')
        ->limit(6)
        ->get();

        $actionableOrders = Order::where('seller_id', $sellerId)
            ->whereIn('status', ['placed', 'confirmed', 'preparing'])
            ->with(['buyer', 'items'])
            ->oldest('placed_at')
            ->limit(5)
            ->get();

        $recentDisputes = \App\Models\Dispute::whereHas('order', fn ($q) => $q->where('seller_id', $sellerId))
            ->where('seller_status', 'pending')
            ->with(['order.buyer', 'raisedBy'])
            ->latest()
            ->limit(3)
            ->get();

        return view('seller.dashboard', compact('stats', 'recentOrders', 'actionableOrders', 'recentDisputes'));
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

    public function admin(): View|\Illuminate\Http\RedirectResponse
    {
        return redirect()->route('admin.dashboard');
    }
}
