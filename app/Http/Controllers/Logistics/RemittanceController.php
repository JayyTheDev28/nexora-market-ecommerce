<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\CodRemittance;
use App\Services\RiderPayoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RemittanceController extends Controller
{
    public function __construct(protected RiderPayoutService $payoutService) {}

    /**
     * Display COD Remittance Reconciliation Dashboard for Logistics.
     */
    public function index(Request $request): View
    {
        $centerId = $request->user()->id;

        $remittances = CodRemittance::where('sorting_center_id', $centerId)
            ->with(['courier.courierProfile', 'reviewer'])
            ->latest('remitted_at')
            ->paginate(20);

        $pendingCount = CodRemittance::where('sorting_center_id', $centerId)->where('status', 'pending')->count();
        $pendingTotal = CodRemittance::where('sorting_center_id', $centerId)->where('status', 'pending')->sum('amount');
        $approvedTotal = CodRemittance::where('sorting_center_id', $centerId)->where('status', 'approved')->sum('amount');
        $discrepancyCount = CodRemittance::where('sorting_center_id', $centerId)->where('status', 'discrepancy_flagged')->count();

        return view('logistics.remittances.index', compact(
            'remittances',
            'pendingCount',
            'pendingTotal',
            'approvedTotal',
            'discrepancyCount'
        ));
    }

    /**
     * Review, approve, or flag a courier COD remittance.
     */
    public function review(Request $request, CodRemittance $remittance): RedirectResponse
    {
        abort_unless((int) $remittance->sorting_center_id === (int) $request->user()->id, 403, 'Unauthorized remittance access.');

        $validated = $request->validate([
            'decision' => ['required', 'string', 'in:approve,flag,reject'],
            'operator_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $this->payoutService->reviewRemittance(
            $remittance,
            $request->user(),
            $validated['decision'],
            $validated['operator_notes'] ?? null
        );

        $actionLabel = match ($validated['decision']) {
            'approve' => 'Approved',
            'flag' => 'Flagged for Discrepancy',
            'reject' => 'Rejected',
        };

        return back()->with('status', "Remittance #{$remittance->remittance_number} marked as {$actionLabel}.");
    }
}
