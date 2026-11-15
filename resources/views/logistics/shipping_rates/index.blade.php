@extends('layouts.app')

@section('title', 'Shipping Rate & Revenue Split Policies — Logistics Operations')

@section('content')
<div class="w-full min-h-screen bg-surface">
    <div class="flex flex-col lg:flex-row w-full">
        <x-logistics-nav active="shipping-rates" />

        <div class="flex-1 min-w-0 p-4 sm:p-6 lg:p-8 bg-surface">
            <div class="w-full max-w-7xl mx-auto space-y-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2 text-xs">
                        <a href="{{ route('logistics.dashboard') }}" class="text-primary font-bold hover:underline">Logistics Dashboard</a>
                        <span class="text-outline-variant">/</span>
                        <span class="text-on-surface-variant">Shipping Rate & Split Policies</span>
                    </div>
                    <a href="{{ route('logistics.inter-company.index') }}" class="text-xs text-primary font-bold hover:underline">Inter-Company Revenue Splits →</a>
                </div>

                @if (session('status'))
                    <div class="bg-primary/10 border border-primary/30 rounded-2xl p-4 text-xs md:text-sm text-primary font-semibold">{{ session('status') }}</div>
                @endif
                @if ($errors->any())
                    <div class="bg-rose-500/10 border border-rose-500/20 rounded-2xl p-4 text-xs text-rose-700">
                        <ul class="list-disc list-inside space-y-0.5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif

                <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 shadow-xs">
                    <span class="px-2.5 py-1 rounded-full bg-emerald-500/10 text-emerald-800 text-[10px] font-bold uppercase">Currently Active Schedule</span>
                    <h1 class="text-xl font-bold text-on-surface mt-2">{{ $activePolicy->name }} <span class="text-xs font-mono text-outline">v{{ $activePolicy->version }}</span></h1>
                    <div class="grid grid-cols-2 md:grid-cols-6 gap-3 mt-4 text-xs">
                        <div class="bg-surface-container/50 p-3 rounded-2xl">
                            <span class="text-[10px] text-on-surface-variant block">Base Fee</span>
                            <strong class="text-lg font-extrabold text-primary">₱{{ number_format($activePolicy->base_fee, 2) }}</strong>
                        </div>
                        <div class="bg-surface-container/50 p-3 rounded-2xl">
                            <span class="text-[10px] text-on-surface-variant block">Base Weight</span>
                            <strong class="text-lg font-extrabold">{{ number_format($activePolicy->base_weight_kg, 0) }} kg</strong>
                        </div>
                        <div class="bg-surface-container/50 p-3 rounded-2xl">
                            <span class="text-[10px] text-on-surface-variant block">Add'l Kg Fee</span>
                            <strong class="text-lg font-extrabold text-primary">₱{{ number_format($activePolicy->per_additional_kg_fee, 2) }}</strong>
                        </div>
                        <div class="bg-surface-container/50 p-3 rounded-2xl">
                            <span class="text-[10px] text-on-surface-variant block">Origin Share</span>
                            <strong class="text-base font-bold">{{ number_format($activePolicy->origin_logistics_share_pct, 1) }}%</strong>
                        </div>
                        <div class="bg-surface-container/50 p-3 rounded-2xl">
                            <span class="text-[10px] text-on-surface-variant block">Transfer Share</span>
                            <strong class="text-base font-bold">{{ number_format($activePolicy->transfer_carrier_share_pct, 1) }}%</strong>
                        </div>
                        <div class="bg-surface-container/50 p-3 rounded-2xl">
                            <span class="text-[10px] text-on-surface-variant block">Delivery Share</span>
                            <strong class="text-base font-bold">{{ number_format($activePolicy->delivery_logistics_share_pct, 1) }}%</strong>
                        </div>
                    </div>
                </div>

                <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 shadow-xs">
                    <h2 class="text-sm font-bold mb-3">Draft New Shipping Policy Version</h2>
                    <form method="POST" action="{{ route('logistics.shipping-rates.store') }}" class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                        @csrf
                        <div class="col-span-2">
                            <label class="block font-semibold mb-1">Policy Name</label>
                            <input type="text" name="name" required placeholder="e.g. Regional Express Tiered Tariff" class="w-full px-3 py-2 rounded-xl border border-outline-variant bg-surface">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Base Fee (₱)</label>
                            <input type="number" step="0.01" min="0" name="base_fee" value="{{ $activePolicy->base_fee }}" required class="w-full px-3 py-2 rounded-xl border border-outline-variant bg-surface">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Base Weight (kg)</label>
                            <input type="number" step="0.5" min="0.5" name="base_weight_kg" value="{{ $activePolicy->base_weight_kg }}" required class="w-full px-3 py-2 rounded-xl border border-outline-variant bg-surface">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Per Add'l Kg (₱)</label>
                            <input type="number" step="0.01" min="0" name="per_additional_kg_fee" value="{{ $activePolicy->per_additional_kg_fee }}" required class="w-full px-3 py-2 rounded-xl border border-outline-variant bg-surface">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Minimum Fee (₱)</label>
                            <input type="number" step="0.01" min="0" name="minimum_fee" value="{{ $activePolicy->minimum_fee }}" required class="w-full px-3 py-2 rounded-xl border border-outline-variant bg-surface">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Origin %</label>
                            <input type="number" step="0.01" min="0" max="100" name="origin_logistics_share_pct" value="35" required class="w-full px-3 py-2 rounded-xl border border-outline-variant bg-surface">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Transfer %</label>
                            <input type="number" step="0.01" min="0" max="100" name="transfer_carrier_share_pct" value="20" required class="w-full px-3 py-2 rounded-xl border border-outline-variant bg-surface">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Delivery %</label>
                            <input type="number" step="0.01" min="0" max="100" name="delivery_logistics_share_pct" value="45" required class="w-full px-3 py-2 rounded-xl border border-outline-variant bg-surface">
                        </div>
                        <div class="col-span-2 sm:col-span-3 flex items-end justify-end">
                            <button type="submit" class="px-5 py-2 rounded-xl bg-primary text-on-primary font-bold cursor-pointer">Save Draft Tariff</button>
                        </div>
                    </form>
                </div>

                <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant overflow-hidden shadow-xs">
                    <div class="p-5 overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="border-b border-outline-variant text-[11px] uppercase text-on-surface-variant">
                                    <th class="pb-3">Name</th>
                                    <th class="pb-3">Tariff</th>
                                    <th class="pb-3">Inter-Company Split</th>
                                    <th class="pb-3">Governance / Status</th>
                                    <th class="pb-3 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline-variant/40">
                                @foreach($policies as $p)
                                    <tr>
                                        <td class="py-3 font-bold">
                                            {{ $p->name }} <span class="font-mono text-outline font-normal">v{{ $p->version }}</span>
                                            @if($p->contract_reference)
                                                <div class="text-[10px] font-mono text-on-surface-variant font-normal">Ref: {{ $p->contract_reference }}</div>
                                            @endif
                                        </td>
                                        <td class="py-3 font-mono">₱{{ number_format($p->base_fee, 2) }} ({{ number_format($p->base_weight_kg, 0) }}kg) + ₱{{ number_format($p->per_additional_kg_fee, 2) }}/kg</td>
                                        <td class="py-3 font-mono">{{ $p->origin_logistics_share_pct }}% / {{ $p->transfer_carrier_share_pct }}% / {{ $p->delivery_logistics_share_pct }}%</td>
                                        <td class="py-3">
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $p->status === 'active' ? 'bg-emerald-500/10 text-emerald-800' : 'bg-surface-container text-outline' }}">{{ $p->status }}</span>
                                            @php $reviewStatus = $p->review_status ?? 'pending_admin_review'; @endphp
                                            <span class="ml-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $reviewStatus === 'approved' ? 'bg-primary/10 text-primary' : ($reviewStatus === 'rejected' ? 'bg-rose-500/10 text-rose-700' : 'bg-amber-500/10 text-amber-800') }}">{{ str_replace('_', ' ', $reviewStatus) }}</span>
                                        </td>
                                        <td class="py-3 text-right">
                                            @if($reviewStatus !== 'approved')
                                                <span class="text-[11px] font-semibold text-amber-800">Requires Admin Approval</span>
                                            @elseif($p->status !== 'active' || !$p->logistics_company_id)
                                                <form method="POST" action="{{ route('logistics.shipping-rates.activate', $p) }}" class="inline">
                                                    @csrf
                                                    <button type="submit" class="px-3 py-1 rounded-xl bg-primary/10 text-primary font-bold cursor-pointer">Activate</button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
