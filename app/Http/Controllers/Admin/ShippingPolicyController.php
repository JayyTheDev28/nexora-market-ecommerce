<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CompensationPolicy;
use App\Models\ShippingRatePolicy;
use App\Models\User;
use App\Services\CompensationPolicyService;
use App\Services\LogisticsProfitabilityService;
use App\Services\ShippingPricingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ShippingPolicyController extends Controller
{
    public function __construct(
        protected ShippingPricingService $shippingPricing,
        protected CompensationPolicyService $compensationService,
        protected LogisticsProfitabilityService $profitabilityService
    ) {}

    public function index(Request $request): View
    {
        $this->shippingPricing->ensureDefaultPolicy();
        $this->compensationService->ensureDefaultPolicies();

        $policies = ShippingRatePolicy::with(['logisticsCompany', 'reviewer'])
            ->orderByDesc('id')
            ->get();

        $compensationPolicies = CompensationPolicy::with(['logisticsCompany', 'creator', 'approver'])
            ->orderByDesc('id')
            ->get();

        $logisticsCompanies = User::whereIn('role', ['logistics', 'sorting_center'])
            ->where('approval_status', 'approved')
            ->orderBy('first_name')
            ->get();

        // Aggregate unprofitable routes across Logistics companies for Admin oversight
        $unprofitableRoutes = [];
        foreach ($logisticsCompanies as $company) {
            $report = $this->profitabilityService->getProfitabilityReport($company->id, now()->subDays(30), now());
            foreach ($report['route_profitability'] as $r) {
                if ($r['is_unprofitable']) {
                    $unprofitableRoutes[] = array_merge($r, [
                        'company_name' => $company->sortingCenterProfile?->business_name ?? $company->full_name,
                        'company_id' => $company->id,
                    ]);
                }
            }
        }

        return view('admin.shipping_policies.index', compact(
            'policies',
            'compensationPolicies',
            'logisticsCompanies',
            'unprofitableRoutes'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'logistics_company_id' => ['nullable', 'exists:users,id'],
            'pricing_mode' => ['required', 'in:weight_tiered,smart_cost_margin'],
            'contract_reference' => ['required', 'string', 'max:120'],
            'base_fee' => ['required', 'numeric', 'min:0'],
            'base_weight_kg' => ['required', 'numeric', 'min:0.5'],
            'per_additional_kg_fee' => ['required', 'numeric', 'min:0'],
            'minimum_fee' => ['required', 'numeric', 'min:0'],
            'target_profit_margin_pct' => ['required', 'numeric', 'min:0', 'max:85'],
            'rounding_increment' => ['required', 'numeric', 'min:0', 'max:100'],
            'estimated_pickup_cost' => ['required', 'numeric', 'min:0'],
            'estimated_delivery_cost' => ['required', 'numeric', 'min:0'],
            'estimated_transfer_cost_per_parcel' => ['required', 'numeric', 'min:0'],
            'estimated_hub_operating_cost' => ['required', 'numeric', 'min:0'],
            'same_province_surcharge' => ['nullable', 'numeric', 'min:0'],
            'interprovincial_surcharge' => ['nullable', 'numeric', 'min:0'],
            'oversized_surcharge' => ['nullable', 'numeric', 'min:0'],
            'origin_logistics_share_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'transfer_carrier_share_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'delivery_logistics_share_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'effective_from' => ['nullable', 'date'],
            'activate_now' => ['nullable', 'boolean'],
        ]);

        $pctSum = round(
            (float) $validated['origin_logistics_share_pct']
            + (float) $validated['transfer_carrier_share_pct']
            + (float) $validated['delivery_logistics_share_pct'],
            2
        );
        if (abs($pctSum - 100.00) > 0.01) {
            return back()->withErrors(['origin_logistics_share_pct' => 'Origin + Transfer + Delivery revenue share percentages must total 100%.'])->withInput();
        }

        $companyId = ! empty($validated['logistics_company_id']) ? (int) $validated['logistics_company_id'] : null;
        $activate = (bool) ($validated['activate_now'] ?? true);

        $policy = DB::transaction(function () use ($validated, $companyId, $activate, $request) {
            $nextVersion = (int) ShippingRatePolicy::where('logistics_company_id', $companyId)->max('version') + 1;

            if ($activate) {
                ShippingRatePolicy::where('logistics_company_id', $companyId)
                    ->where('status', ShippingRatePolicy::STATUS_ACTIVE)
                    ->update([
                        'status' => ShippingRatePolicy::STATUS_ARCHIVED,
                        'effective_until' => now(),
                    ]);
            }

            $created = ShippingRatePolicy::create([
                'name' => $validated['name'],
                'logistics_company_id' => $companyId,
                'version' => $nextVersion,
                'status' => $activate ? ShippingRatePolicy::STATUS_ACTIVE : ShippingRatePolicy::STATUS_DRAFT,
                'pricing_mode' => $validated['pricing_mode'],
                'review_status' => ShippingRatePolicy::REVIEW_APPROVED,
                'contract_reference' => $validated['contract_reference'],
                'base_fee' => $validated['base_fee'],
                'base_weight_kg' => $validated['base_weight_kg'],
                'per_additional_kg_fee' => $validated['per_additional_kg_fee'],
                'minimum_fee' => $validated['minimum_fee'],
                'target_profit_margin_pct' => $validated['target_profit_margin_pct'],
                'rounding_increment' => $validated['rounding_increment'],
                'estimated_pickup_cost' => $validated['estimated_pickup_cost'],
                'estimated_delivery_cost' => $validated['estimated_delivery_cost'],
                'estimated_transfer_cost_per_parcel' => $validated['estimated_transfer_cost_per_parcel'],
                'estimated_hub_operating_cost' => $validated['estimated_hub_operating_cost'],
                'same_province_surcharge' => $validated['same_province_surcharge'] ?? 0,
                'interprovincial_surcharge' => $validated['interprovincial_surcharge'] ?? 0,
                'oversized_surcharge' => $validated['oversized_surcharge'] ?? 0,
                'origin_logistics_share_pct' => $validated['origin_logistics_share_pct'],
                'transfer_carrier_share_pct' => $validated['transfer_carrier_share_pct'],
                'delivery_logistics_share_pct' => $validated['delivery_logistics_share_pct'],
                'platform_shipping_commission_pct' => 0.00,
                'effective_from' => ! empty($validated['effective_from']) ? $validated['effective_from'] : now(),
                'created_by' => $request->user()->id,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_notes' => 'Configured and approved by Platform Admin.',
            ]);

            AuditLog::log(
                'admin_shipping_policy_created',
                $created,
                null,
                $created->toArray(),
                "Admin created shipping pricing policy v{$created->version} ({$created->pricing_mode})",
                $request->user()->id
            );

            return $created;
        });

        return back()->with('status', "Shipping pricing policy \"{$policy->name}\" v{$policy->version} saved.");
    }

    public function approve(Request $request, ShippingRatePolicy $policy): RedirectResponse
    {
        $validated = $request->validate([
            'contract_reference' => ['required', 'string', 'max:120'],
            'review_notes' => ['nullable', 'string', 'max:500'],
            'activate' => ['nullable', 'boolean'],
        ]);

        $this->shippingPricing->approvePolicy(
            $policy,
            $request->user(),
            $validated['contract_reference'],
            $validated['review_notes'] ?? null,
            (bool) ($validated['activate'] ?? false)
        );

        return back()->with('status', "Shipping split policy \"{$policy->name}\" approved under contract {$validated['contract_reference']}.");
    }

    public function reject(Request $request, ShippingRatePolicy $policy): RedirectResponse
    {
        $validated = $request->validate([
            'review_notes' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $this->shippingPricing->rejectPolicy($policy, $request->user(), $validated['review_notes']);

        return back()->with('status', "Shipping split policy \"{$policy->name}\" marked Rejected.");
    }

    public function approveCompensation(Request $request, CompensationPolicy $compensationPolicy): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $activated = $this->compensationService->approveAndActivatePolicy($compensationPolicy, $request->user());

        return back()->with('status', "Compensation policy \"{$activated->name}\" v{$activated->version} approved and activated. Historical Rider earnings remain unchanged.");
    }

    public function rejectCompensation(Request $request, CompensationPolicy $compensationPolicy): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $old = $compensationPolicy->only(['status', 'notes']);
        $compensationPolicy->update([
            'status' => CompensationPolicy::STATUS_ARCHIVED,
            'notes' => trim(($compensationPolicy->notes ? $compensationPolicy->notes . ' | ' : '') . 'Rejected by Admin: ' . ($request->input('reason') ?: 'Schedule declined')),
        ]);
        AuditLog::log('compensation_policy_rejected', $compensationPolicy, $old, $compensationPolicy->only(['status', 'notes']), 'Admin rejected compensation policy proposal', $request->user()->id);

        return back()->with('status', "Compensation policy \"{$compensationPolicy->name}\" archived/rejected.");
    }
}
