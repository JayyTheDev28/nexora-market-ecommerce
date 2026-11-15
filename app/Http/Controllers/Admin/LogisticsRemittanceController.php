<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LogisticsAdminRemittance;
use App\Models\User;
use App\Services\CodFinancialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LogisticsRemittanceController extends Controller
{
    public function __construct(protected CodFinancialService $financialService) {}

    /**
     * Display list of all Logistics-to-Admin Remittances.
     */
    public function index(Request $request): View
    {
        $status = $request->query('status');
        $logisticsId = $request->query('logistics_id');
        $search = $request->query('search');

        $query = LogisticsAdminRemittance::query()
            ->with(['logistics.logisticsProfile', 'reviewer'])
            ->latest();

        if ($status && in_array($status, ['submitted', 'under_review', 'verified', 'rejected', 'disputed'], true)) {
            $query->where('status', $status);
        }

        if ($logisticsId) {
            $query->where('logistics_id', $logisticsId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                  ->orWhere('transfer_reference', 'like', "%{$search}%")
                  ->orWhereHas('logistics', function ($lq) use ($search) {
                      $lq->where('first_name', 'like', "%{$search}%")
                         ->orWhere('last_name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%")
                         ->orWhereHas('logisticsProfile', fn ($pq) => $pq->where('company_name', 'like', "%{$search}%"));
                  });
            });
        }

        $remittances = $query->paginate(15)->withQueryString();

        $stats = [
            'totalRemitted' => (float) LogisticsAdminRemittance::where('status', 'verified')->sum('merchandise_amount'),
            'totalShippingRetained' => (float) LogisticsAdminRemittance::where('status', 'verified')->sum('shipping_retained'),
            'pendingAmount' => (float) LogisticsAdminRemittance::whereIn('status', ['submitted', 'under_review'])->sum('merchandise_amount'),
            'pendingCount' => LogisticsAdminRemittance::whereIn('status', ['submitted', 'under_review'])->count(),
            'verifiedCount' => LogisticsAdminRemittance::where('status', 'verified')->count(),
            'rejectedCount' => LogisticsAdminRemittance::where('status', 'rejected')->count(),
        ];

        $logisticsProviders = User::whereIn('role', ['logistics', 'sorting_center'])
            ->with('logisticsProfile')
            ->get();

        return view('admin.logistics_remittances.index', compact(
            'remittances',
            'stats',
            'status',
            'logisticsId',
            'search',
            'logisticsProviders'
        ));
    }

    /**
     * Show detailed view of a logistics remittance batch with items and proof.
     */
    public function show(LogisticsAdminRemittance $remittance): View
    {
        $remittance->load([
            'logistics.logisticsProfile',
            'reviewer',
            'items.order.seller.sellerProfile',
            'items.order.buyer',
        ]);

        // Calculate preview of commissions and seller allocations for this batch
        $sellerAllocations = [];
        $totalCommission = 0.00;
        $totalSellerPayable = 0.00;

        foreach ($remittance->items as $item) {
            $seller = $item->order->seller;
            $sellerId = $seller ? $seller->id : 0;
            $merchandise = (float) $item->merchandise_component;
            $commission = round($merchandise * CodFinancialService::COMMISSION_RATE, 2);
            $payable = round($merchandise - $commission, 2);

            $totalCommission += $commission;
            $totalSellerPayable += $payable;

            if (!isset($sellerAllocations[$sellerId])) {
                $sellerAllocations[$sellerId] = [
                    'seller' => $seller,
                    'orders_count' => 0,
                    'merchandise_total' => 0.00,
                    'commission_total' => 0.00,
                    'payable_total' => 0.00,
                ];
            }

            $sellerAllocations[$sellerId]['orders_count']++;
            $sellerAllocations[$sellerId]['merchandise_total'] += $merchandise;
            $sellerAllocations[$sellerId]['commission_total'] += $commission;
            $sellerAllocations[$sellerId]['payable_total'] += $payable;
        }

        return view('admin.logistics_remittances.show', compact(
            'remittance',
            'sellerAllocations',
            'totalCommission',
            'totalSellerPayable'
        ));
    }

    /**
     * Admin verifies actual receipt of merchandise funds and allocates to sellers.
     */
    public function verify(Request $request, LogisticsAdminRemittance $remittance): RedirectResponse
    {
        $admin = $request->user();

        $validated = $request->validate([
            'actual_received_amount' => ['nullable', 'numeric', 'min:0'],
            'verified_bank_reference' => ['nullable', 'string', 'max:120'],
            'admin_notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->financialService->verifyLogisticsAdminRemittance(
                $remittance,
                $admin,
                $validated['admin_notes'] ?? null,
                isset($validated['actual_received_amount']) ? (float) $validated['actual_received_amount'] : null,
                $validated['verified_bank_reference'] ?? null
            );

            return redirect()->route('admin.logistics-remittances.show', $remittance)
                ->with('status', "Remittance #{$remittance->reference_number} verified successfully! Merchandise funds (₱" . number_format($remittance->merchandise_amount, 2) . ") confirmed and credited to seller earnings.");
        } catch (\DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Mark remittance as under review.
     */
    public function underReview(Request $request, LogisticsAdminRemittance $remittance): RedirectResponse
    {
        if ($remittance->status !== 'submitted') {
            return back()->with('status', 'Remittance is already in progress.');
        }

        $remittance->update([
            'status' => 'under_review',
            'reviewed_by' => $request->user()->id,
        ]);

        return back()->with('status', "Remittance #{$remittance->reference_number} is now under review.");
    }

    /**
     * Reject a logistics remittance with a reason.
     */
    public function reject(Request $request, LogisticsAdminRemittance $remittance): RedirectResponse
    {
        $admin = $request->user();

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'rejection_reason.required' => 'Please provide a clear reason for rejecting this remittance.',
            'rejection_reason.min' => 'The rejection reason must be at least 5 characters.',
        ]);

        try {
            $this->financialService->rejectLogisticsAdminRemittance(
                $remittance,
                $admin,
                $validated['rejection_reason']
            );

            return redirect()->route('admin.logistics-remittances.show', $remittance)
                ->with('status', "Remittance #{$remittance->reference_number} has been rejected. Orders have been unlocked.");
        } catch (\DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Dispute / flag an amount or proof discrepancy on a logistics remittance.
     */
    public function dispute(Request $request, LogisticsAdminRemittance $remittance): RedirectResponse
    {
        $validated = $request->validate([
            'discrepancy_notes' => ['required', 'string', 'min:5', 'max:500'],
            'actual_received_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $this->financialService->disputeLogisticsAdminRemittance(
                $remittance,
                $request->user(),
                $validated['discrepancy_notes'],
                isset($validated['actual_received_amount']) ? (float) $validated['actual_received_amount'] : null
            );

            return redirect()->route('admin.logistics-remittances.show', $remittance)
                ->with('status', "Remittance #{$remittance->reference_number} has been flagged as Disputed for investigation.");
        } catch (\DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}

