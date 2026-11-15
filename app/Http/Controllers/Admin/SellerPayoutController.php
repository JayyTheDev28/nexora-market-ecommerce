<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SellerPayoutRequest;
use App\Models\User;
use App\Services\CodFinancialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SellerPayoutController extends Controller
{
    public function __construct(protected CodFinancialService $financialService) {}

    /**
     * Display all Seller Payout Requests.
     */
    public function index(Request $request): View
    {
        $status = $request->query('status');
        $sellerId = $request->query('seller_id');
        $search = $request->query('search');

        $query = SellerPayoutRequest::query()
            ->with(['seller.sellerProfile', 'reviewer'])
            ->latest();

        if ($status && in_array($status, ['pending', 'approved', 'paid', 'processed', 'rejected'], true)) {
            if ($status === 'paid') {
                $query->whereIn('status', ['paid', 'processed']);
            } else {
                $query->where('status', $status);
            }
        }

        if ($sellerId) {
            $query->where('seller_id', $sellerId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                  ->orWhere('account_number', 'like', "%{$search}%")
                  ->orWhere('transaction_reference', 'like', "%{$search}%")
                  ->orWhereHas('seller', function ($sq) use ($search) {
                      $sq->where('first_name', 'like', "%{$search}%")
                         ->orWhere('last_name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%")
                         ->orWhereHas('sellerProfile', fn ($pq) => $pq->where('business_name', 'like', "%{$search}%"));
                  });
            });
        }

        $payouts = $query->paginate(15)->withQueryString();

        $stats = [
            'pendingCount' => SellerPayoutRequest::where('status', 'pending')->count(),
            'pendingAmount' => (float) SellerPayoutRequest::where('status', 'pending')->sum('amount'),
            'approvedCount' => SellerPayoutRequest::where('status', 'approved')->count(),
            'approvedAmount' => (float) SellerPayoutRequest::where('status', 'approved')->sum('amount'),
            'paidCount' => SellerPayoutRequest::whereIn('status', ['paid', 'processed'])->count(),
            'paidAmount' => (float) SellerPayoutRequest::whereIn('status', ['paid', 'processed'])->sum('amount'),
            'rejectedCount' => SellerPayoutRequest::where('status', 'rejected')->count(),
        ];

        $sellers = User::where('role', 'seller')
            ->with('sellerProfile')
            ->get();

        return view('admin.seller_payouts.index', compact(
            'payouts',
            'stats',
            'status',
            'sellerId',
            'search',
            'sellers'
        ));
    }

    /**
     * Show payout request details.
     */
    public function show(SellerPayoutRequest $payout): View
    {
        $payout->load(['seller.sellerProfile', 'reviewer']);
        $sellerOverview = $this->financialService->getSellerFinancialOverview($payout->seller);

        return view('admin.seller_payouts.show', compact('payout', 'sellerOverview'));
    }

    /**
     * Approve payout request (step 1: ready for disbursement).
     */
    public function approve(Request $request, SellerPayoutRequest $payout): RedirectResponse
    {
        $admin = $request->user();

        $validated = $request->validate([
            'admin_notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->financialService->approveSellerPayout(
                $payout,
                $admin,
                $validated['admin_notes'] ?? null
            );

            return back()->with('status', "Payout request #{$payout->id} (₱" . number_format($payout->amount, 2) . ") approved. It can now be disbursed.");
        } catch (\DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Disburse payout: record transaction reference, proof, and mark paid (step 2).
     */
    public function disburse(Request $request, SellerPayoutRequest $payout): RedirectResponse
    {
        $admin = $request->user();

        $validated = $request->validate([
            'transaction_reference' => ['required', 'string', 'min:3', 'max:100'],
            'proof_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'admin_notes' => ['nullable', 'string', 'max:500'],
        ], [
            'transaction_reference.required' => 'Please provide the GCash, Maya, or Bank transfer transaction reference number.',
        ]);

        try {
            $this->financialService->disburseSellerPayout(
                $payout,
                $admin,
                $validated['transaction_reference'],
                $request->file('proof_file'),
                $validated['admin_notes'] ?? null
            );

            return back()->with('status', "Payout #{$payout->id} marked as Paid! Reference: {$validated['transaction_reference']}.");
        } catch (\DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Reject payout request.
     */
    public function reject(Request $request, SellerPayoutRequest $payout): RedirectResponse
    {
        $admin = $request->user();

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'rejection_reason.required' => 'Please provide a reason for rejecting the payout request.',
        ]);

        try {
            $this->financialService->rejectSellerPayout(
                $payout,
                $admin,
                $validated['rejection_reason']
            );

            return back()->with('status', "Payout request #{$payout->id} rejected. Balance restored to seller.");
        } catch (\DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}

