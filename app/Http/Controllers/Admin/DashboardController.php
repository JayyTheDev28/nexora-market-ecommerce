<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Commission;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $stats = [
            'totalGmv' => (float) Order::whereNotIn('status', ['returned', 'delivery_failed'])->sum('total'),
            'totalCommission' => (float) Commission::sum('commission_amount'),
            'settledCommission' => (float) Commission::where('status', 'settled')->sum('commission_amount'),
            'pendingCommission' => (float) Commission::where('status', 'pending')->sum('commission_amount'),
            'totalOrders' => Order::count(),
            'completedOrders' => Order::where('status', 'completed')->count(),
            'activeDisputes' => Dispute::whereIn('status', ['open', 'under_review'])->count(),
            'pendingApplications' => User::where('approval_status', 'pending')
                ->whereIn('role', ['buyer', 'seller', 'sorting_center'])
                ->count(),
            'totalProducts' => Product::count(),
            'activeProducts' => Product::where('status', 'active')->count(),
            'totalBuyers' => User::where('role', 'buyer')->count(),
            'totalSellers' => User::where('role', 'seller')->count(),
            'totalLogistics' => User::where('role', 'sorting_center')->count(),
            'totalCouriers' => User::where('role', 'courier')->count(),
            'suspendedUsers' => User::where('account_status', 'suspended')->count(),
        ];

        $pendingApplications = User::where('approval_status', 'pending')
            ->whereIn('role', ['buyer', 'seller', 'sorting_center'])
            ->with(['sellerProfile', 'sortingCenterProfile'])
            ->latest()
            ->limit(5)
            ->get();

        $recentDisputes = Dispute::with(['order.buyer', 'order.seller.sellerProfile'])
            ->latest()
            ->limit(5)
            ->get();

        $recentAuditLogs = AuditLog::with('operator')
            ->latest('created_at')
            ->limit(6)
            ->get();

        $recentOrders = Order::with(['buyer', 'seller.sellerProfile'])
            ->latest('placed_at')
            ->limit(5)
            ->get();

        return view('admin.dashboard', compact(
            'stats',
            'pendingApplications',
            'recentDisputes',
            'recentAuditLogs',
            'recentOrders'
        ));
    }
}
