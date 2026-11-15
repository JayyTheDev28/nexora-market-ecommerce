<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\CourierPayout;
use App\Models\RiderEarning;
use App\Services\CourierPayoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CourierPayoutController extends Controller
{
    public function __construct(protected CourierPayoutService $courierPayoutService) {}

    public function index(Request $request): View
    {
        $companyId = $request->user()->logisticsCompanyId();

        $query = CourierPayout::where('logistics_company_id', $companyId)
            ->with(['courier.courierProfile', 'reviewer', 'payer'])
            ->orderByDesc('requested_at');

        if ($request->filled('role')) {
            $query->where('role', $request->string('role')->toString());
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        $payouts = $query->paginate(20)->withQueryString();

        $stats = [
            'requestedCount' => CourierPayout::where('logistics_company_id', $companyId)->whereIn('status', ['requested', 'under_review'])->count(),
            'requestedAmount' => (float) CourierPayout::where('logistics_company_id', $companyId)->whereIn('status', ['requested', 'under_review', 'approved'])->sum('amount'),
            'paidAmount' => (float) CourierPayout::where('logistics_company_id', $companyId)->where('status', 'paid')->sum('amount'),
            'riderEarnedTotal' => (float) RiderEarning::where('logistics_company_id', $companyId)->whereIn('type', ['pickup', 'delivery', 'failed_delivery'])->sum('amount'),
            'transferEarnedTotal' => (float) RiderEarning::where('logistics_company_id', $companyId)->whereIn('type', ['transfer_trip', 'transfer_exception'])->sum('amount'),
        ];

        return view('logistics.payouts.index', compact('payouts', 'stats'));
    }

    public function approve(Request $request, CourierPayout $payout): RedirectResponse
    {
        $validated = $request->validate([
            'operator_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $this->courierPayoutService->approvePayout($payout, $request->user(), $validated['operator_notes'] ?? null);

        return back()->with('status', "Payout #{$payout->payout_number} approved and queued for disbursement.");
    }

    public function markPaid(Request $request, CourierPayout $payout): RedirectResponse
    {
        $validated = $request->validate([
            'external_reference' => ['required', 'string', 'min:3', 'max:120'],
            'proof_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'operator_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $this->courierPayoutService->markPaid(
            $payout,
            $request->user(),
            $validated['external_reference'],
            $request->file('proof_file'),
            $validated['operator_notes'] ?? null
        );

        return back()->with('status', "Payout #{$payout->payout_number} (₱" . number_format((float) $payout->amount, 2) . ") marked as Paid.");
    }

    public function reject(Request $request, CourierPayout $payout): RedirectResponse
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $this->courierPayoutService->rejectPayout($payout, $request->user(), $validated['rejection_reason']);

        return back()->with('status', "Payout #{$payout->payout_number} rejected and courier balance released.");
    }
}
