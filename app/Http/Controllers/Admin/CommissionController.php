<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Commission;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommissionController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status', 'all');
        $sellerId = $request->query('seller_id');
        $search = $request->query('q');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $query = Commission::query()->with(['order', 'seller.sellerProfile']);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($sellerId) {
            $query->where('seller_id', $sellerId);
        }

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('order', fn ($oq) => $oq->where('order_number', 'like', "%{$search}%"))
                    ->orWhereHas('seller', function ($sq) use ($search) {
                        $sq->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhereHas('sellerProfile', fn ($pq) => $pq->where('business_name', 'like', "%{$search}%"));
                    });
            });
        }

        $commissions = $query->latest()->paginate(15)->withQueryString();

        $stats = [
            'totalCommission' => (float) Commission::sum('commission_amount'),
            'settledCommission' => (float) Commission::where('status', 'settled')->sum('commission_amount'),
            'pendingCommission' => (float) Commission::where('status', 'pending')->sum('commission_amount'),
            'totalSellerPayout' => (float) Commission::sum('seller_payout'),
            'totalVolume' => (float) Commission::sum('order_total'),
            'pendingCount' => Commission::where('status', 'pending')->count(),
            'settledCount' => Commission::where('status', 'settled')->count(),
        ];

        $sellers = User::where('role', 'seller')->with('sellerProfile')->orderBy('first_name')->get();

        return view('admin.commissions.index', compact('commissions', 'status', 'sellerId', 'search', 'startDate', 'endDate', 'stats', 'sellers'));
    }

    public function settle(Commission $commission): RedirectResponse
    {
        if ($commission->status === 'settled') {
            return back()->with('status', 'Commission record is already marked as settled.');
        }

        $oldStatus = $commission->status;
        $commission->update([
            'status' => 'settled',
            'settled_at' => now(),
        ]);

        AuditLog::log(
            action: 'commission_settled',
            auditable: $commission,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => 'settled', 'settled_at' => now()->toDateTimeString()],
            reason: "Commission #{$commission->id} settled by administrator"
        );

        return back()->with('status', "Commission of ₱" . number_format($commission->commission_amount, 2) . " for Order #{$commission->order->order_number} marked as settled.");
    }

    public function batchSettle(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'seller_id' => ['nullable', 'exists:users,id'],
        ]);

        $query = Commission::where('status', 'pending');

        if (! empty($validated['seller_id'])) {
            $query->where('seller_id', $validated['seller_id']);
        }

        $pending = $query->get();
        $count = $pending->count();

        if ($count === 0) {
            return back()->with('status', 'No pending commissions to settle.');
        }

        $now = now();
        foreach ($pending as $commission) {
            $commission->update([
                'status' => 'settled',
                'settled_at' => $now,
            ]);

            AuditLog::log(
                action: 'commission_settled_batch',
                auditable: $commission,
                oldValues: ['status' => 'pending'],
                newValues: ['status' => 'settled', 'settled_at' => $now->toDateTimeString()],
                reason: 'Batch settled by administrator'
            );
        }

        return back()->with('status', "Successfully settled {$count} commission record(s).");
    }
}
