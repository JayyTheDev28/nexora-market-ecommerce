<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShippingRatePolicy;
use App\Services\ShippingPricingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShippingPolicyController extends Controller
{
    public function __construct(protected ShippingPricingService $shippingPricing) {}

    public function index(): View
    {
        $this->shippingPricing->ensureDefaultPolicy();

        $policies = ShippingRatePolicy::with(['logisticsCompany', 'reviewer'])
            ->orderByDesc('id')
            ->get();

        return view('admin.shipping_policies.index', compact('policies'));
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
}
