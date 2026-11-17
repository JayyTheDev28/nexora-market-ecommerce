@extends('layouts.app')

@section('title', 'Admin Pricing Controls & Shipping Policy Governance')

@section('content')
<div class="w-full min-h-screen bg-surface">
    <div class="flex flex-col lg:flex-row w-full">
        <x-admin-nav active="shipping-policies" />

        <div class="flex-1 min-w-0 p-4 sm:p-6 lg:p-8 bg-surface">
            <div class="w-full max-w-7xl mx-auto space-y-6">

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 class="text-2xl font-bold text-on-surface">Smart Shipping Pricing &amp; Policy Governance</h1>
                        <p class="text-xs text-on-surface-variant mt-1">Configure cost + target margin pricing, review multi-carrier revenue sharing, evaluate Rider compensation proposals, and monitor unprofitable routes.</p>
                    </div>
                    <a href="{{ route('admin.logistics-remittances.index') }}" class="text-xs font-bold text-primary hover:underline">← Back to Admin Remittances</a>
                </div>

                @if (session('status'))
                    <div class="bg-emerald-500/10 border border-emerald-500/30 rounded-2xl p-4 text-xs text-emerald-800 font-semibold">{{ session('status') }}</div>
                @endif
                @if ($errors->any())
                    <div class="bg-rose-500/10 border border-rose-500/20 rounded-2xl p-4 text-xs text-rose-700">
                        <ul class="list-disc list-inside space-y-0.5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif

                {{-- 1. Create / Configure Smart Shipping Pricing Policy --}}
                <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 shadow-xs space-y-4">
                    <div>
                        <h2 class="text-sm font-bold text-on-surface">Create &amp; Approve Shipping Pricing Policy</h2>
                        <p class="text-[11px] text-on-surface-variant">Formula in Smart Cost + Margin mode: <strong>Estimated Fee = Total Estimated Logistics Cost ÷ (1 − Target Profit Margin %)</strong>. Historical orders keep their rate snapshot.</p>
                    </div>

                    <form method="POST" action="{{ route('admin.shipping-policies.store') }}" class="grid grid-cols-2 md:grid-cols-4 gap-3 text-xs">
                        @csrf
                        <div class="col-span-2">
                            <label class="block font-semibold mb-1">Policy Name</label>
                            <input type="text" name="name" required placeholder="e.g. Smart Regional Cost + 20% Margin" class="w-full px-3 py-2 rounded-xl border border-outline-variant bg-surface">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Logistics Scope</label>
                            <select name="logistics_company_id" class="w-full px-3 py-2 rounded-xl border border-outline-variant bg-surface">
                                <option value="">Platform Default (All)</option>
                                @foreach($logisticsCompanies as $co)
                                    <option value="{{ $co->id }}">{{ $co->sortingCenterProfile?->business_name ?? $co->full_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Pricing Mode</label>
                            <select name="pricing_mode" class="w-full px-3 py-2 rounded-xl border border-outline-variant bg-surface">
                                <option value="smart_cost_margin">Smart Cost + Margin Formula</option>
                                <option value="weight_tiered">Legacy Weight-Tiered</option>
                            </select>
                        </div>

                        <div>
                            <label class="block font-semibold mb-1">Est. Pickup Rider (₱)</label>
                            <input type="number" step="0.01" min="0" name="estimated_pickup_cost" value="10.00" required class="w-full px-3 py-2 rounded-xl border border-outline-variant bg-surface">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Est. Delivery Rider (₱)</label>
                            <input type="number" step="0.01" min="0" name="estimated_delivery_cost" value="30.00" required class="w-full px-3 py-2 rounded-xl border border-outline-variant bg-surface">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Est. Transfer / Parcel (₱)</label>
                            <input type="number" step="0.01" min="0" name="estimated_transfer_cost_per_parcel" value="20.00" required class="w-full px-3 py-2 rounded-xl border border-outline-variant bg-surface">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Est. Hub Expense (₱)</label>
                            <input type="number" step="0.01" min="0" name="estimated_hub_operating_cost" value="10.00" required class="w-full px-3 py-2 rounded-xl border border-outline-variant bg-surface">
                        </div>

                        <div>
                            <label class="block font-semibold mb-1">Target Margin (%)</label>
                            <input type="number" step="0.01" min="0" max="85" name="target_profit_margin_pct" value="20.00" required class="w-full px-3 py-2 rounded-xl border border-outline-variant bg-surface">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Rounding Increment (₱)</label>
                            <input type="number" step="1" min="0" name="rounding_increment" value="5.00" required class="w-full px-3 py-2 rounded-xl border border-outline-variant bg-surface">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Minimum Fee (₱)</label>
                            <input type="number" step="0.01" min="0" name="minimum_fee" value="50.00" required class="w-full px-3 py-2 rounded-xl border border-outline-variant bg-surface">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Base / Add'l Kg Fee (₱)</label>
                            <div class="flex gap-1">
                                <input type="number" step="0.01" min="0" name="base_fee" value="50.00" required class="w-1/2 px-2 py-2 rounded-xl border border-outline-variant bg-surface" title="Base Fee">
                                <input type="number" step="0.01" min="0" name="per_additional_kg_fee" value="20.00" required class="w-1/2 px-2 py-2 rounded-xl border border-outline-variant bg-surface" title="Per Add'l Kg">
                            </div>
                            <input type="hidden" name="base_weight_kg" value="1.00">
                        </div>

                        <div>
                            <label class="block font-semibold mb-1">Same-Province Surcharge (₱)</label>
                            <input type="number" step="0.01" min="0" name="same_province_surcharge" value="0.00" class="w-full px-3 py-2 rounded-xl border border-outline-variant bg-surface">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Interprovincial Surcharge (₱)</label>
                            <input type="number" step="0.01" min="0" name="interprovincial_surcharge" value="0.00" class="w-full px-3 py-2 rounded-xl border border-outline-variant bg-surface">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Oversized Surcharge (₱)</label>
                            <input type="number" step="0.01" min="0" name="oversized_surcharge" value="15.00" class="w-full px-3 py-2 rounded-xl border border-outline-variant bg-surface">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Contract Ref &amp; Effective Date</label>
                            <div class="flex gap-1">
                                <input type="text" name="contract_reference" required value="SMART-2026-01" class="w-1/2 px-2 py-2 rounded-xl border border-outline-variant bg-surface">
                                <input type="date" name="effective_from" value="{{ now()->toDateString() }}" class="w-1/2 px-2 py-2 rounded-xl border border-outline-variant bg-surface">
                            </div>
                        </div>

                        <div>
                            <label class="block font-semibold mb-1">Origin Share (%)</label>
                            <input type="number" step="0.01" min="0" max="100" name="origin_logistics_share_pct" value="35.00" required class="w-full px-3 py-2 rounded-xl border border-outline-variant bg-surface">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Transfer Share (%)</label>
                            <input type="number" step="0.01" min="0" max="100" name="transfer_carrier_share_pct" value="20.00" required class="w-full px-3 py-2 rounded-xl border border-outline-variant bg-surface">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Delivery Share (%)</label>
                            <input type="number" step="0.01" min="0" max="100" name="delivery_logistics_share_pct" value="45.00" required class="w-full px-3 py-2 rounded-xl border border-outline-variant bg-surface">
                        </div>
                        <div class="flex items-end justify-end gap-2">
                            <input type="hidden" name="activate_now" value="1">
                            <button type="submit" class="w-full px-4 py-2 rounded-xl bg-primary text-on-primary font-bold cursor-pointer">Save &amp; Activate Policy</button>
                        </div>
                    </form>
                </div>

                {{-- 2. Shipping Rate & Intercompany Agreements Table --}}
                <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant overflow-hidden shadow-xs p-5 overflow-x-auto">
                    <h2 class="text-sm font-bold mb-3">Shipping Rate &amp; Inter-Company Revenue Sharing Policies</h2>
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-outline-variant text-[11px] uppercase text-on-surface-variant">
                                <th class="pb-3">Policy &amp; Mode</th>
                                <th class="pb-3">Scope</th>
                                <th class="pb-3">Tariff / Margin</th>
                                <th class="pb-3">Split (O / T / D)</th>
                                <th class="pb-3">Contract &amp; Effective</th>
                                <th class="pb-3">Status</th>
                                <th class="pb-3 text-right">Admin Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant/40">
                            @foreach($policies as $p)
                                <tr>
                                    <td class="py-3 font-semibold">
                                        {{ $p->name }} <span class="text-[10px] font-mono text-outline">v{{ $p->version }}</span>
                                        <span class="block text-[10px] text-on-surface-variant">{{ strtoupper(str_replace('_', ' ', $p->pricing_mode ?? 'weight_tiered')) }}</span>
                                    </td>
                                    <td class="py-3">{{ $p->logisticsCompany?->full_name ?? 'Platform Default' }}</td>
                                    <td class="py-3">
                                        Min ₱{{ number_format($p->minimum_fee, 0) }} · {{ number_format($p->target_profit_margin_pct ?? 20, 0) }}% margin
                                        <span class="block text-[10px] text-outline">Base ₱{{ number_format($p->base_fee, 0) }} + ₱{{ number_format($p->per_additional_kg_fee, 0) }}/kg</span>
                                    </td>
                                    <td class="py-3">{{ $p->origin_logistics_share_pct }}% / {{ $p->transfer_carrier_share_pct }}% / {{ $p->delivery_logistics_share_pct }}%</td>
                                    <td class="py-3 font-mono">
                                        {{ $p->contract_reference ?? '—' }}
                                        <span class="block text-[10px] text-outline">{{ $p->effective_from?->format('M d, Y') ?? 'Pending' }}</span>
                                    </td>
                                    <td class="py-3 uppercase font-bold">{{ $p->status }} / {{ $p->review_status }}</td>
                                    <td class="py-3 text-right space-x-2">
                                        @if($p->review_status !== 'approved')
                                            <form method="POST" action="{{ route('admin.shipping-policies.approve', $p) }}" class="inline-flex items-center gap-1">
                                                @csrf
                                                <input type="text" name="contract_reference" value="{{ $p->contract_reference ?: 'CONTRACT-' . $p->id }}" class="px-2 py-1 rounded border border-outline-variant text-[11px] w-28" required>
                                                <input type="hidden" name="activate" value="1">
                                                <button type="submit" class="px-3 py-1 rounded-xl bg-emerald-600 text-white font-bold cursor-pointer">Approve</button>
                                            </form>
                                        @else
                                            <span class="text-emerald-700 font-semibold">Approved {{ $p->reviewed_at?->format('M d, Y') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- 3. Rider & Transfer Courier Compensation Proposals --}}
                <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant overflow-hidden shadow-xs p-5 overflow-x-auto">
                    <h2 class="text-sm font-bold mb-3">Rider &amp; Transfer Courier Compensation Policy Proposals</h2>
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-outline-variant text-[11px] uppercase text-on-surface-variant">
                                <th class="pb-3">Schedule</th>
                                <th class="pb-3">Role / Company</th>
                                <th class="pb-3">Rates</th>
                                <th class="pb-3">Status</th>
                                <th class="pb-3 text-right">Admin Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant/40">
                            @foreach($compensationPolicies as $cp)
                                <tr>
                                    <td class="py-3 font-semibold">{{ $cp->name }} <span class="font-mono text-outline">v{{ $cp->version }}</span></td>
                                    <td class="py-3 uppercase">{{ $cp->role }} · {{ $cp->logisticsCompany?->full_name ?? 'Platform' }}</td>
                                    <td class="py-3">
                                        @if($cp->role === 'rider')
                                            Pickup ₱{{ number_format($cp->pickup_rate, 2) }} · Delivery ₱{{ number_format($cp->delivery_rate, 2) }} · Failed ₱{{ number_format($cp->failed_attempt_rate, 2) }}
                                        @else
                                            Trip Base ₱{{ number_format($cp->transfer_trip_base_rate, 2) }} + ₱{{ number_format($cp->transfer_per_km_rate, 2) }}/km + ₱{{ number_format($cp->transfer_per_parcel_rate, 2) }}/parcel
                                        @endif
                                    </td>
                                    <td class="py-3 uppercase font-bold">{{ $cp->status }}</td>
                                    <td class="py-3 text-right space-x-2">
                                        @if(in_array($cp->status, ['draft', 'proposed'], true))
                                            <form method="POST" action="{{ route('admin.compensation-policies.approve', $cp) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="px-3 py-1 rounded-xl bg-emerald-600 text-white font-bold cursor-pointer">Approve &amp; Activate</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.compensation-policies.reject', $cp) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="px-3 py-1 rounded-xl bg-rose-600 text-white font-bold cursor-pointer">Reject</button>
                                            </form>
                                        @else
                                            <span class="text-on-surface-variant">{{ $cp->approved_at?->format('M d, Y') ?? 'Baseline' }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- 4. Platform-Wide Unprofitable Routes Monitor --}}
                <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant overflow-hidden shadow-xs p-5 overflow-x-auto">
                    <h2 class="text-sm font-bold mb-3">Unprofitable Routes Monitor (Last 30 Days)</h2>
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-outline-variant text-[11px] uppercase text-on-surface-variant">
                                <th class="pb-2">Logistics Company</th>
                                <th class="pb-2">Route</th>
                                <th class="pb-2 text-right">Orders</th>
                                <th class="pb-2 text-right">Revenue</th>
                                <th class="pb-2 text-right">Direct Cost</th>
                                <th class="pb-2 text-right">Net Loss</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant/40">
                            @forelse($unprofitableRoutes as $ur)
                                <tr>
                                    <td class="py-2 font-semibold">{{ $ur['company_name'] }}</td>
                                    <td class="py-2">{{ $ur['route'] }}</td>
                                    <td class="py-2 text-right">{{ $ur['orders_count'] }}</td>
                                    <td class="py-2 text-right">₱{{ number_format($ur['shipping_revenue'], 2) }}</td>
                                    <td class="py-2 text-right">₱{{ number_format($ur['direct_courier_cost'], 2) }}</td>
                                    <td class="py-2 text-right font-bold text-rose-700">₱{{ number_format($ur['net_contribution'], 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="py-4 text-center text-outline">No unprofitable routes detected across active logistics operators.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>
</div>
@endsection
