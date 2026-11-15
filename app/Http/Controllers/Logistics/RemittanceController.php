<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\CodCollection;
use App\Models\CodRemittance;
use App\Services\CodCollectionService;
use App\Services\RiderPayoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RemittanceController extends Controller
{
    public function __construct(
        protected RiderPayoutService $payoutService,
        protected CodCollectionService $codCollectionService,
    ) {}

    /**
     * Display COD Remittance Reconciliation Dashboard for Logistics.
     */
    public function index(Request $request): View
    {
        $companyId = $request->user()->logisticsCompanyId();

        $query = CodRemittance::where('sorting_center_id', $companyId)
            ->with(['courier.courierProfile', 'reviewer', 'collectingHub', 'allocations.order'])
            ->latest('remitted_at');

        if ($request->filled('status')) {
            $status = $request->string('status')->toString();
            if ($status === 'open') {
                $query->whereIn('status', CodRemittance::OPEN_STATUSES);
            } else {
                $query->where('status', $status);
            }
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->string('payment_method')->toString());
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('remittance_number', 'like', "%{$search}%")
                    ->orWhere('external_reference', 'like', "%{$search}%")
                    ->orWhereHas('courier', fn ($cq) => $cq->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"));
            });
        }

        $remittances = $query->paginate(20)->withQueryString();

        $pendingCount = CodRemittance::where('sorting_center_id', $companyId)
            ->whereIn('status', CodRemittance::OPEN_STATUSES)
            ->count();
        $pendingTotal = (float) CodRemittance::where('sorting_center_id', $companyId)
            ->whereIn('status', CodRemittance::OPEN_STATUSES)
            ->sum('amount');
        $approvedTotal = (float) CodRemittance::where('sorting_center_id', $companyId)
            ->whereIn('status', CodRemittance::VERIFIED_STATUSES)
            ->selectRaw('COALESCE(SUM(COALESCE(actual_received_amount, amount)), 0) as total')
            ->value('total');
        $discrepancyCount = CodRemittance::where('sorting_center_id', $companyId)
            ->where(function ($q) {
                $q->where('status', CodRemittance::STATUS_DISCREPANCY_FLAGGED)
                    ->orWhere('shortage_amount', '>', 0)
                    ->orWhere('overpayment_amount', '>', 0);
            })
            ->count();
        $shortageTotal = (float) CodRemittance::where('sorting_center_id', $companyId)->sum('shortage_amount');
        $overpaymentTotal = (float) CodRemittance::where('sorting_center_id', $companyId)->sum('overpayment_amount');
        $outstandingRiderCod = (float) CodCollection::where('collecting_logistics_id', $companyId)
            ->where('outstanding_amount', '>', 0)
            ->sum('outstanding_amount');

        return view('logistics.remittances.index', compact(
            'remittances',
            'pendingCount',
            'pendingTotal',
            'approvedTotal',
            'discrepancyCount',
            'shortageTotal',
            'overpaymentTotal',
            'outstandingRiderCod'
        ));
    }

    /**
     * Review, verify (full/partial), mark under review, flag, or reject a courier COD remittance.
     */
    public function review(Request $request, CodRemittance $remittance): RedirectResponse
    {
        $companyId = $request->user()->logisticsCompanyId();
        abort_unless((int) $remittance->sorting_center_id === $companyId, 403, 'Unauthorized remittance access.');

        $validated = $request->validate([
            'decision' => ['required', 'string', 'in:approve,verify_full,verify_partial,under_review,flag,flag_discrepancy,reject'],
            'actual_received_amount' => ['nullable', 'numeric', 'min:0'],
            'verification_reference' => ['nullable', 'string', 'max:120'],
            'discrepancy_reason' => ['nullable', 'string', 'max:500'],
            'operator_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $updated = $this->payoutService->reviewRemittance(
            $remittance,
            $request->user(),
            $validated['decision'],
            $validated['operator_notes'] ?? null,
            [
                'actual_received_amount' => $validated['actual_received_amount'] ?? null,
                'verification_reference' => $validated['verification_reference'] ?? null,
                'discrepancy_reason' => $validated['discrepancy_reason'] ?? null,
            ]
        );

        $actionLabel = ucfirst(str_replace('_', ' ', $updated->status));

        return back()->with('status', "Remittance #{$remittance->remittance_number} marked as {$actionLabel}.");
    }
}
