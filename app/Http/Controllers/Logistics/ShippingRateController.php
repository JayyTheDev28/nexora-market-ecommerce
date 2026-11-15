<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\InterCompanyShippingAllocation;
use App\Models\ShippingRatePolicy;
use App\Services\ShippingPricingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ShippingRateController extends Controller
{
    public function __construct(protected ShippingPricingService $shippingPricing) {}

    public function index(Request $request): View
    {
        $this->shippingPricing->ensureDefaultPolicy();
        $companyId = $request->user()->logisticsCompanyId();

        $activePolicy = $this->shippingPricing->getActivePolicy($companyId);
        $policies = ShippingRatePolicy::where(function ($q) use ($companyId) {
            $q->where('logistics_company_id', $companyId)->orWhereNull('logistics_company_id');
        })
            ->orderByDesc('status')
            ->orderByDesc('version')
            ->get();

        return view('logistics.shipping_rates.index', compact('activePolicy', 'policies'));
    }

    public function store(Request $request): RedirectResponse
    {
        $companyId = $request->user()->logisticsCompanyId();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'contract_reference' => ['nullable', 'string', 'max:120'],
            'base_fee' => ['required', 'numeric', 'min:0'],
            'base_weight_kg' => ['required', 'numeric', 'min:0.5'],
            'per_additional_kg_fee' => ['required', 'numeric', 'min:0'],
            'minimum_fee' => ['required', 'numeric', 'min:0'],
            'origin_logistics_share_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'transfer_carrier_share_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'delivery_logistics_share_pct' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $pctSum = round(
            (float) $validated['origin_logistics_share_pct']
            + (float) $validated['transfer_carrier_share_pct']
            + (float) $validated['delivery_logistics_share_pct'],
            2
        );
        if (abs($pctSum - 100.00) > 0.01) {
            return back()->withErrors(['origin_logistics_share_pct' => 'Origin + Transfer + Delivery revenue share percentages must equal exactly 100%.'])->withInput();
        }

        $nextVersion = (int) ShippingRatePolicy::where('logistics_company_id', $companyId)->max('version') + 1;
        $policy = ShippingRatePolicy::create(array_merge($validated, [
            'logistics_company_id' => $companyId,
            'version' => $nextVersion,
            'status' => ShippingRatePolicy::STATUS_DRAFT,
            'review_status' => ShippingRatePolicy::REVIEW_PENDING,
            'platform_shipping_commission_pct' => 0.00,
            'created_by' => $request->user()->id,
        ]));

        AuditLog::log('shipping_policy_created', $policy, null, $policy->toArray(), 'Created draft shipping rate policy awaiting Admin review', $request->user()->id);

        return back()->with('status', "Draft shipping policy v{$policy->version} saved and submitted for Platform Admin review.");
    }

    public function activate(Request $request, ShippingRatePolicy $policy): RedirectResponse
    {
        $companyId = $request->user()->logisticsCompanyId();
        abort_unless((int) $policy->logistics_company_id === $companyId || $policy->logistics_company_id === null, 403);

        if ($policy->review_status !== ShippingRatePolicy::REVIEW_APPROVED) {
            return back()->withErrors(['error' => 'This shipping revenue-sharing policy must be reviewed and approved by a Platform Admin before activation.']);
        }

        DB::transaction(function () use ($policy, $companyId, $request) {
            if ($policy->logistics_company_id === null) {
                ShippingRatePolicy::where('logistics_company_id', $companyId)
                    ->where('status', ShippingRatePolicy::STATUS_ACTIVE)
                    ->update(['status' => ShippingRatePolicy::STATUS_ARCHIVED]);

                $nextVersion = (int) ShippingRatePolicy::where('logistics_company_id', $companyId)->max('version') + 1;
                $activated = ShippingRatePolicy::create([
                    'name' => $policy->name,
                    'logistics_company_id' => $companyId,
                    'version' => max($nextVersion, $policy->version),
                    'status' => ShippingRatePolicy::STATUS_ACTIVE,
                    'review_status' => $policy->review_status,
                    'contract_reference' => $policy->contract_reference,
                    'base_fee' => $policy->base_fee,
                    'base_weight_kg' => $policy->base_weight_kg,
                    'per_additional_kg_fee' => $policy->per_additional_kg_fee,
                    'minimum_fee' => $policy->minimum_fee,
                    'origin_logistics_share_pct' => $policy->origin_logistics_share_pct,
                    'transfer_carrier_share_pct' => $policy->transfer_carrier_share_pct,
                    'delivery_logistics_share_pct' => $policy->delivery_logistics_share_pct,
                    'platform_shipping_commission_pct' => $policy->platform_shipping_commission_pct,
                    'effective_from' => now(),
                    'created_by' => $request->user()->id,
                    'reviewed_by' => $policy->reviewed_by,
                    'reviewed_at' => $policy->reviewed_at,
                ]);
                AuditLog::log('shipping_policy_activated', $activated, null, $activated->toArray(), 'Activated shipping policy', $request->user()->id);

                return;
            }

            abort_unless((int) $policy->logistics_company_id === $companyId, 403);

            ShippingRatePolicy::where('logistics_company_id', $companyId)
                ->where('id', '!=', $policy->id)
                ->where('status', ShippingRatePolicy::STATUS_ACTIVE)
                ->update(['status' => ShippingRatePolicy::STATUS_ARCHIVED]);

            $policy->update([
                'status' => ShippingRatePolicy::STATUS_ACTIVE,
                'effective_from' => now(),
            ]);
            AuditLog::log('shipping_policy_activated', $policy, null, ['status' => 'active'], 'Activated shipping policy', $request->user()->id);
        });

        return back()->with('status', "Shipping policy \"{$policy->name}\" is now active for future checkouts and inter-company splits.");
    }

    public function interCompanyIndex(Request $request): View
    {
        $companyId = $request->user()->logisticsCompanyId();

        $allocations = InterCompanyShippingAllocation::where(function ($q) use ($companyId) {
            $q->where('collecting_logistics_id', $companyId)
                ->orWhere('pickup_logistics_id', $companyId)
                ->orWhere('transfer_logistics_id', $companyId)
                ->orWhere('delivery_logistics_id', $companyId);
        })
            ->with(['order:id,order_number,tracking_number,delivered_at', 'pickupLogistics', 'transferLogistics', 'deliveryLogistics', 'collectingLogistics'])
            ->latest('id')
            ->paginate(20);

        $stats = [
            'pickupReceivable' => (float) InterCompanyShippingAllocation::where('pickup_logistics_id', $companyId)
                ->where('collecting_logistics_id', '!=', $companyId)
                ->where('settlement_status', 'allocated')
                ->sum('pickup_share_amount'),
            'transferReceivable' => (float) InterCompanyShippingAllocation::where('transfer_logistics_id', $companyId)
                ->where('collecting_logistics_id', '!=', $companyId)
                ->where('settlement_status', 'allocated')
                ->sum('transfer_share_amount'),
            'payableToPartners' => (float) InterCompanyShippingAllocation::where('collecting_logistics_id', $companyId)
                ->where('settlement_status', 'allocated')
                ->get()
                ->sum(function ($a) use ($companyId) {
                    $owe = 0;
                    if ($a->pickup_logistics_id && (int) $a->pickup_logistics_id !== $companyId) {
                        $owe += (float) $a->pickup_share_amount;
                    }
                    if ($a->transfer_logistics_id && (int) $a->transfer_logistics_id !== $companyId) {
                        $owe += (float) $a->transfer_share_amount;
                    }

                    return $owe;
                }),
        ];

        return view('logistics.inter_company.index', compact('allocations', 'stats', 'companyId'));
    }

    public function settleAllocation(Request $request, InterCompanyShippingAllocation $allocation): RedirectResponse
    {
        $this->shippingPricing->settleAllocation($allocation, $request->user());

        return back()->with('status', "Inter-company shipping allocation for order #{$allocation->order?->order_number} marked as Settled.");
    }
}
