<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\CompensationPolicy;
use App\Services\CompensationPolicyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompensationController extends Controller
{
    public function __construct(protected CompensationPolicyService $policyService) {}

    public function index(Request $request): View
    {
        $this->policyService->ensureDefaultPolicies();
        $companyId = $request->user()->logisticsCompanyId();

        $activeRiderPolicy = $this->policyService->getActivePolicy(CompensationPolicy::ROLE_RIDER, $companyId);
        $activeCourierPolicy = $this->policyService->getActivePolicy(CompensationPolicy::ROLE_COURIER, $companyId);

        $policies = CompensationPolicy::where(function ($q) use ($companyId) {
            $q->where('logistics_company_id', $companyId)
                ->orWhereNull('logistics_company_id');
        })
            ->with(['creator', 'approver'])
            ->orderByRaw("FIELD(status, 'active', 'approved', 'proposed', 'draft', 'archived')")
            ->orderByDesc('version')
            ->get();

        return view('logistics.compensation.index', compact(
            'activeRiderPolicy',
            'activeCourierPolicy',
            'policies',
            'companyId'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'string', 'in:rider,courier'],
            'name' => ['required', 'string', 'max:120'],
            'status' => ['required', 'string', 'in:draft,proposed'],
            'pickup_rate' => ['nullable', 'numeric', 'min:0'],
            'delivery_rate' => ['nullable', 'numeric', 'min:0'],
            'failed_attempt_rate' => ['nullable', 'numeric', 'min:0'],
            'transfer_trip_base_rate' => ['nullable', 'numeric', 'min:0'],
            'transfer_per_km_rate' => ['nullable', 'numeric', 'min:0'],
            'transfer_per_parcel_rate' => ['nullable', 'numeric', 'min:0'],
            'transfer_per_kg_rate' => ['nullable', 'numeric', 'min:0'],
            'transfer_fuel_allowance' => ['nullable', 'numeric', 'min:0'],
            'transfer_exception_rate' => ['nullable', 'numeric', 'min:0'],
            'pay_on_exception' => ['nullable', 'boolean'],
            'effective_from' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $policy = $this->policyService->createPolicy($validated, $request->user());

        return back()->with('status', "Compensation policy v{$policy->version} ({$policy->name}) saved as {$policy->status}. Historical completed trips remain unchanged.");
    }

    public function activate(Request $request, CompensationPolicy $policy): RedirectResponse
    {
        $activated = $this->policyService->approveAndActivatePolicy($policy, $request->user());

        return back()->with('status', "Policy \"{$activated->name}\" v{$activated->version} is now ACTIVE for your new dispatches and trips.");
    }
}
