<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\LogisticsAdminRemittance;
use App\Services\CodFinancialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminRemittanceController extends Controller
{
    public function __construct(protected CodFinancialService $financialService) {}

    /**
     * Display the Logistics-to-Admin Remittance Dashboard.
     */
    public function index(Request $request): View
    {
        $logistics = $request->user();

        $companyId = $logistics->logisticsCompanyId();

        $summary = $this->financialService->getLogisticsCodSummary($logistics);
        $eligibleOrders = $this->financialService->getEligibleOrdersForRemittance($logistics);

        $statusFilter = $request->query('status');
        $query = LogisticsAdminRemittance::where('logistics_id', $companyId)
            ->with(['items.order.seller', 'reviewer'])
            ->latest();

        if ($statusFilter && in_array($statusFilter, ['submitted', 'under_review', 'verified', 'rejected', 'disputed'], true)) {
            $query->where('status', $statusFilter);
        }

        $remittances = $query->paginate(15)->withQueryString();

        return view('logistics.admin_remittances.index', compact(
            'summary',
            'eligibleOrders',
            'remittances',
            'statusFilter'
        ));
    }

    /**
     * Display a specific remittance batch details.
     */
    public function show(Request $request, LogisticsAdminRemittance $remittance): View
    {
        abort_unless((int) $remittance->logistics_id === $request->user()->logisticsCompanyId(), 403, 'Unauthorized remittance access.');

        $remittance->load(['items.order.seller.sellerProfile', 'items.order.buyer', 'reviewer']);

        return view('logistics.admin_remittances.show', compact('remittance'));
    }

    /**
     * Submit a new remittance to Nexora Admin.
     */
    public function store(Request $request): RedirectResponse
    {
        $logistics = $request->user();

        $validated = $request->validate([
            'order_ids' => ['required', 'array', 'min:1'],
            'order_ids.*' => ['required', 'integer'],
            'transfer_method' => ['required', 'string', 'in:bank_transfer,gcash,maya,cash_deposit,check'],
            'transfer_reference' => ['required', 'string', 'min:3', 'max:100'],
            'proof_file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'], // max 5MB
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'order_ids.required' => 'Please select at least one delivered COD order to remit.',
            'transfer_reference.required' => 'Please provide the transaction or bank reference number.',
            'proof_file.required' => 'Proof of transfer receipt screenshot or document is required.',
        ]);

        $remittance = $this->financialService->createLogisticsAdminRemittance(
            $logistics,
            $validated['order_ids'],
            $validated,
            $request->file('proof_file')
        );

        return redirect()->route('logistics.admin-remittances.index')
            ->with('status', "Remittance #{$remittance->reference_number} of ₱" . number_format($remittance->merchandise_amount, 2) . " submitted for Admin verification.");
    }
}

