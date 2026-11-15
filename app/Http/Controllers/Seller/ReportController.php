<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\SellerPayoutRequest;
use App\Services\CodFinancialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct(protected CodFinancialService $financialService) {}

    public function index(Request $request): View
    {
        $seller = $request->user();
        $sellerId = $seller->id;

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
        $netEarnings = max(0.00, $grossSales - $totalCommission);
        $byStatus = $orders->groupBy('status')->map->count();

        // Comprehensive financial overview from verified COD remittance ledger
        $financialOverview = $this->financialService->getSellerFinancialOverview($seller);

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

            // Financial breakdown cards per Section 7 & 9
            'pendingEarnings' => $financialOverview['pendingInTransitEarnings'],
            'earningsWaitingLogistics' => $financialOverview['earningsWaitingLogistics'],
            'settledEarnings' => $financialOverview['verifiedEarningsPayable'],
            'availableBalance' => $financialOverview['availableBalance'],
            'pendingPayouts' => $financialOverview['pendingPayouts'],
            'completedPayouts' => $financialOverview['completedPayouts'],
            'platformCommissionDeducted' => $financialOverview['platformCommissionDeducted'],
            'lifetimeCompletedGross' => $financialOverview['grossSales'],

            'payoutRequests' => $payoutRequests,
        ]);
    }

    public function requestPayout(Request $request): RedirectResponse
    {
        $seller = $request->user();

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:500'],
            'payout_method' => ['required', 'in:bank_transfer,gcash,maya'],
            'account_name' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $this->financialService->requestSellerPayout(
            $seller,
            (float) $validated['amount'],
            $validated['payout_method'],
            $validated['account_name'],
            $validated['account_number'],
            $validated['notes'] ?? null
        );

        return redirect()->route('seller.reports.index')
            ->with('status', "Payout withdrawal request for ₱" . number_format($validated['amount'], 2) . " has been submitted for Admin approval.");
    }
}
