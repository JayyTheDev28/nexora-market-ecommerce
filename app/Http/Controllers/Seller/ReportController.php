<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\SellerPayoutRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $sellerId = $request->user()->id;

        $from = $request->query('from') ? Carbon::parse($request->query('from'))->startOfDay() : now()->startOfMonth();
        $to = $request->query('to') ? Carbon::parse($request->query('to'))->endOfDay() : now()->endOfDay();

        // Orders within selected range
        $orders = Order::where(function ($q) use ($sellerId) {
            $q->where('seller_id', $sellerId)
              ->orWhereHas('items.product', fn ($sq) => $sq->where('seller_id', $sellerId));
        })
        ->whereBetween('placed_at', [$from, $to])
        ->with([
            'items' => fn ($q) => $q->whereHas('product', fn ($sq) => $sq->where('seller_id', $sellerId)),
            'commissions' => fn ($q) => $q->where('seller_id', $sellerId),
            'buyer',
        ])
        ->get();

        $grossSales = $orders->whereNotIn('status', ['cancelled', 'returned'])->sum(function ($order) use ($sellerId) {
            return $order->items->sum(fn ($i) => $i->price * $i->quantity);
        });

        $totalCommission = $orders->flatMap->commissions->sum('commission_amount');
        $netEarnings = max(0, $grossSales - $totalCommission);
        $byStatus = $orders->groupBy('status')->map->count();

        // Lifetime financial balances
        $allCompletedOrders = Order::where(function ($q) use ($sellerId) {
            $q->where('seller_id', $sellerId)
              ->orWhereHas('items.product', fn ($sq) => $sq->where('seller_id', $sellerId));
        })
        ->whereIn('status', ['delivered', 'completed'])
        ->with([
            'items' => fn ($q) => $q->whereHas('product', fn ($sq) => $sq->where('seller_id', $sellerId)),
            'commissions' => fn ($q) => $q->where('seller_id', $sellerId),
        ])
        ->get();

        $lifetimeCompletedGross = $allCompletedOrders->sum(function ($o) use ($sellerId) {
            $itemTotal = $o->items->where('product.seller_id', $sellerId)->sum(fn ($i) => $i->price * $i->quantity);
            return $itemTotal > 0 ? $itemTotal : (float) $o->subtotal;
        });
        $lifetimeCommission = $allCompletedOrders->flatMap->commissions->sum('commission_amount');
        if ($lifetimeCommission <= 0 && $lifetimeCompletedGross > 0) {
            $lifetimeCommission = $lifetimeCompletedGross * 0.10;
        }
        $totalLifetimeNet = max(0, $lifetimeCompletedGross - $lifetimeCommission);

        // Pending earnings (in transit / processing orders)
        $pendingOrders = Order::where(function ($q) use ($sellerId) {
            $q->where('seller_id', $sellerId)
              ->orWhereHas('items.product', fn ($sq) => $sq->where('seller_id', $sellerId));
        })
        ->whereNotIn('status', ['delivered', 'completed', 'cancelled', 'returned'])
        ->with(['items' => fn ($q) => $q->whereHas('product', fn ($sq) => $sq->where('seller_id', $sellerId))])
        ->get();

        $pendingEarnings = $pendingOrders->sum(fn ($o) => $o->items->sum(fn ($i) => $i->price * $i->quantity) * 0.90);

        // Total payouts already requested (pending or processed)
        $payoutsRequested = (float) SellerPayoutRequest::where('seller_id', $sellerId)
            ->whereIn('status', ['pending', 'processed'])
            ->sum('amount');

        $availableBalance = max(0, round($totalLifetimeNet - $payoutsRequested, 2));

        // Payout history
        $payoutRequests = SellerPayoutRequest::where('seller_id', $sellerId)
            ->latest()
            ->paginate(10);

        return view('seller.reports.index', [
            'from' => $from,
            'to' => $to,
            'orders' => $orders,
            'orderCount' => $orders->count(),
            'grossSales' => $grossSales,
            'commission' => $totalCommission,
            'netPayout' => $netEarnings,
            'byStatus' => $byStatus,
            'pendingEarnings' => $pendingEarnings,
            'availableBalance' => $availableBalance,
            'payoutRequests' => $payoutRequests,
            'lifetimeCompletedGross' => $lifetimeCompletedGross,
        ]);
    }

    public function requestPayout(Request $request): RedirectResponse
    {
        $sellerId = $request->user()->id;

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:500'],
            'payout_method' => ['required', 'in:bank_transfer,gcash,maya'],
            'account_name' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        // Calculate current available balance
        $allCompletedOrders = Order::where(function ($q) use ($sellerId) {
            $q->where('seller_id', $sellerId)
              ->orWhereHas('items.product', fn ($sq) => $sq->where('seller_id', $sellerId));
        })
        ->whereIn('status', ['delivered', 'completed'])
        ->with([
            'items' => fn ($q) => $q->whereHas('product', fn ($sq) => $sq->where('seller_id', $sellerId)),
            'commissions' => fn ($q) => $q->where('seller_id', $sellerId),
        ])
        ->get();

        $lifetimeCompletedGross = $allCompletedOrders->sum(function ($o) use ($sellerId) {
            $itemTotal = $o->items->where('product.seller_id', $sellerId)->sum(fn ($i) => $i->price * $i->quantity);
            return $itemTotal > 0 ? $itemTotal : (float) $o->subtotal;
        });
        $lifetimeCommission = $allCompletedOrders->flatMap->commissions->sum('commission_amount');
        if ($lifetimeCommission <= 0 && $lifetimeCompletedGross > 0) {
            $lifetimeCommission = $lifetimeCompletedGross * 0.10;
        }
        $totalLifetimeNet = max(0, $lifetimeCompletedGross - $lifetimeCommission);

        $payoutsRequested = (float) SellerPayoutRequest::where('seller_id', $sellerId)
            ->whereIn('status', ['pending', 'processed'])
            ->sum('amount');

        $availableBalance = max(0, round($totalLifetimeNet - $payoutsRequested, 2));

        if ((float) $validated['amount'] > $availableBalance) {
            return back()->withErrors([
                'amount' => "Requested payout (₱" . number_format($validated['amount'], 2) . ") exceeds your available balance (₱" . number_format($availableBalance, 2) . ").",
            ]);
        }

        SellerPayoutRequest::create([
            'seller_id' => $sellerId,
            'amount' => $validated['amount'],
            'payout_method' => $validated['payout_method'],
            'account_name' => $validated['account_name'],
            'account_number' => $validated['account_number'],
            'status' => 'pending',
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('seller.reports.index')
            ->with('status', "Payout request for ₱" . number_format($validated['amount'], 2) . " has been submitted for processing.");
    }
}
