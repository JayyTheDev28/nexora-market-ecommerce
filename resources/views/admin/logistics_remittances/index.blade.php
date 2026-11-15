@extends('layouts.app')

@section('title', 'Logistics Remittances — Platform Administration')

@section('content')
<div class="w-full min-h-screen bg-surface">
    <div class="flex flex-col lg:flex-row w-full">

        <x-admin-nav active="logistics-remittances" />

        <div class="flex-1 min-w-0 p-4 sm:p-6 lg:p-8 bg-surface">
            <div class="w-full max-w-7xl mx-auto space-y-6">

                {{-- Breadcrumb --}}
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-2 text-xs">
                        <a href="{{ route('admin.dashboard') }}" class="text-primary font-bold hover:underline">Admin Dashboard</a>
                        <span class="text-outline-variant">/</span>
                        <span class="text-on-surface-variant">Logistics Remittances</span>
                    </div>

                    <a href="{{ route('admin.seller-payouts.index') }}" class="inline-flex items-center gap-1.5 text-xs text-primary font-semibold hover:underline">
                        <span class="material-symbols-outlined text-[16px]">account_balance_wallet</span>
                        <span>Seller Payout Management →</span>
                    </a>
                </div>

                {{-- Status Alert --}}
                @if (session('status'))
                    <div class="bg-primary/10 border border-primary/30 rounded-2xl p-4 flex items-center gap-3 shadow-2xs">
                        <span class="material-symbols-outlined text-primary text-[22px]">check_circle</span>
                        <p class="text-xs md:text-sm text-primary font-semibold">{{ session('status') }}</p>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="bg-rose-500/10 border border-rose-500/20 rounded-2xl p-4 text-xs text-rose-700">
                        <ul class="list-disc list-inside space-y-0.5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Page Header & Statistics Cards (Section 5) --}}
                <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 md:p-8 shadow-xs">
                    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                        <div>
                            <h1 class="text-xl md:text-2xl font-bold text-on-surface flex items-center gap-2">
                                <span class="material-symbols-outlined text-primary text-[28px]">payments</span>
                                <span>Logistics Remittances</span>
                            </h1>
                            <p class="text-xs text-on-surface-variant mt-1.5 max-w-2xl leading-relaxed">
                                Review, verify, and settle COD merchandise funds remitted by partner logistics carriers. Under Nexora policy, <strong>carriers keep 100% of shipping fees</strong>; only merchandise amounts are received and credited to sellers after deducting the 10% platform commission.
                            </p>
                        </div>
                    </div>

                    {{-- Stat Cards --}}
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mt-6 pt-6 border-t border-outline-variant/60">
                        <div class="bg-surface-container/50 rounded-2xl p-4 border border-outline-variant/40">
                            <span class="text-[10px] text-on-surface-variant uppercase font-semibold block">Total Merchandise Verified</span>
                            <span class="text-xl sm:text-2xl font-extrabold text-emerald-800 mt-1 block">₱{{ number_format($stats['totalRemitted'], 2) }}</span>
                            <span class="text-[10px] text-outline mt-0.5 block">{{ $stats['verifiedCount'] }} verified batch(es)</span>
                        </div>

                        <div class="bg-surface-container/50 rounded-2xl p-4 border border-outline-variant/40">
                            <span class="text-[10px] text-on-surface-variant uppercase font-semibold block">Shipping Retained by Logistics</span>
                            <span class="text-xl sm:text-2xl font-extrabold text-on-surface mt-1 block">₱{{ number_format($stats['totalShippingRetained'], 2) }}</span>
                            <span class="text-[10px] text-outline mt-0.5 block">Logistics courier revenue</span>
                        </div>

                        <div class="bg-amber-500/10 rounded-2xl p-4 border border-amber-500/20">
                            <span class="text-[10px] text-amber-800 uppercase font-semibold block">Pending Verification</span>
                            <span class="text-xl sm:text-2xl font-extrabold text-amber-900 mt-1 block">₱{{ number_format($stats['pendingAmount'], 2) }}</span>
                            <span class="text-[10px] text-amber-700 font-medium mt-0.5 block">{{ $stats['pendingCount'] }} batch(es) awaiting review</span>
                        </div>

                        <div class="bg-surface-container/50 rounded-2xl p-4 border border-outline-variant/40">
                            <span class="text-[10px] text-on-surface-variant uppercase font-semibold block">Platform Commission Earned</span>
                            <span class="text-xl sm:text-2xl font-extrabold text-primary mt-1 block">₱{{ number_format($stats['totalRemitted'] * 0.10, 2) }}</span>
                            <span class="text-[10px] text-outline mt-0.5 block">10% on verified merchandise</span>
                        </div>
                    </div>
                </div>

                {{-- Filter & Remittance Table --}}
                <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant overflow-hidden shadow-xs">
                    {{-- Search & Filter Controls --}}
                    <div class="p-5 md:px-6 bg-surface-container-low/60 border-b border-outline-variant/60 flex flex-col md:flex-row md:items-center justify-between gap-3">
                        {{-- Status Pills --}}
                        <div class="flex items-center gap-1.5 overflow-x-auto text-xs pb-1 md:pb-0">
                            <a href="{{ route('admin.logistics-remittances.index') }}" class="px-3 py-1 rounded-full border transition-all {{ empty($status) ? 'bg-primary text-on-primary font-bold border-primary' : 'bg-surface text-on-surface-variant border-outline-variant hover:border-outline' }}">
                                All
                            </a>
                            <a href="{{ route('admin.logistics-remittances.index', ['status' => 'submitted']) }}" class="px-3 py-1 rounded-full border transition-all {{ $status === 'submitted' ? 'bg-amber-600 text-white font-bold border-amber-600' : 'bg-surface text-on-surface-variant border-outline-variant hover:border-outline' }}">
                                Submitted
                            </a>
                            <a href="{{ route('admin.logistics-remittances.index', ['status' => 'under_review']) }}" class="px-3 py-1 rounded-full border transition-all {{ $status === 'under_review' ? 'bg-indigo-600 text-white font-bold border-indigo-600' : 'bg-surface text-on-surface-variant border-outline-variant hover:border-outline' }}">
                                Under Review
                            </a>
                            <a href="{{ route('admin.logistics-remittances.index', ['status' => 'verified']) }}" class="px-3 py-1 rounded-full border transition-all {{ $status === 'verified' ? 'bg-emerald-600 text-white font-bold border-emerald-600' : 'bg-surface text-on-surface-variant border-outline-variant hover:border-outline' }}">
                                Verified
                            </a>
                            <a href="{{ route('admin.logistics-remittances.index', ['status' => 'rejected']) }}" class="px-3 py-1 rounded-full border transition-all {{ $status === 'rejected' ? 'bg-rose-600 text-white font-bold border-rose-600' : 'bg-surface text-on-surface-variant border-outline-variant hover:border-outline' }}">
                                Rejected
                            </a>
                        </div>

                        {{-- Search & Carrier Filter Form --}}
                        <form method="GET" action="{{ route('admin.logistics-remittances.index') }}" class="flex items-center gap-2">
                            @if($status)
                                <input type="hidden" name="status" value="{{ $status }}">
                            @endif

                            <select name="logistics_id" onchange="this.form.submit()" class="rounded-xl border border-outline-variant px-3 py-1.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-1 focus:ring-primary">
                                <option value="">All Carriers</option>
                                @foreach($logisticsProviders as $carrier)
                                    <option value="{{ $carrier->id }}" {{ (string)$logisticsId === (string)$carrier->id ? 'selected' : '' }}>
                                        {{ $carrier->logisticsProfile?->company_name ?? $carrier->full_name }}
                                    </option>
                                @endforeach
                            </select>

                            <div class="relative">
                                <input
                                    type="text"
                                    name="search"
                                    value="{{ $search }}"
                                    placeholder="Search ref #, carrier..."
                                    class="rounded-xl border border-outline-variant pl-8 pr-3 py-1.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-1 focus:ring-primary">
                                <span class="material-symbols-outlined text-[16px] text-outline absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none">search</span>
                            </div>
                        </form>
                    </div>

                    {{-- Table per Section 5 --}}
                    <div class="p-5 md:p-6 overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="border-b border-outline-variant text-[11px] text-on-surface-variant uppercase font-semibold">
                                    <th class="pb-3">Logistics Carrier</th>
                                    <th class="pb-3">Remittance #</th>
                                    <th class="pb-3 text-right">Total COD</th>
                                    <th class="pb-3 text-right">Shipping Retained</th>
                                    <th class="pb-3 text-right">Merchandise Remitted</th>
                                    <th class="pb-3">Transfer Method</th>
                                    <th class="pb-3">Transaction Reference</th>
                                    <th class="pb-3">Proof</th>
                                    <th class="pb-3">Date Submitted</th>
                                    <th class="pb-3">Status</th>
                                    <th class="pb-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline-variant/40">
                                @forelse($remittances as $remit)
                                    <tr class="hover:bg-surface-container-low/40 transition-colors">
                                        {{-- Carrier --}}
                                        <td class="py-3.5">
                                            <span class="font-bold text-on-surface block">
                                                {{ $remit->logistics?->logisticsProfile?->company_name ?? $remit->logistics?->full_name }}
                                            </span>
                                            <span class="text-[10px] text-outline font-mono">{{ $remit->logistics?->email }}</span>
                                        </td>

                                        {{-- Remittance # --}}
                                        <td class="py-3.5 font-mono font-bold text-primary">
                                            <a href="{{ route('admin.logistics-remittances.show', $remit) }}" class="hover:underline">
                                                {{ $remit->reference_number }}
                                            </a>
                                        </td>

                                        {{-- Total COD --}}
                                        <td class="py-3.5 text-right font-medium">
                                            ₱{{ number_format($remit->total_cod_amount, 2) }}
                                        </td>

                                        {{-- Shipping Retained --}}
                                        <td class="py-3.5 text-right font-bold text-emerald-800">
                                            ₱{{ number_format($remit->shipping_retained, 2) }}
                                        </td>

                                        {{-- Merchandise Remitted --}}
                                        <td class="py-3.5 text-right font-extrabold text-primary">
                                            ₱{{ number_format($remit->merchandise_amount, 2) }}
                                        </td>

                                        {{-- Method --}}
                                        <td class="py-3.5 uppercase text-[10px] font-semibold text-on-surface-variant">
                                            {{ str_replace('_', ' ', $remit->transfer_method) }}
                                        </td>

                                        {{-- Transaction Ref --}}
                                        <td class="py-3.5 font-mono text-xs text-on-surface font-semibold">
                                            {{ $remit->transfer_reference }}
                                        </td>

                                        {{-- Proof --}}
                                        <td class="py-3.5">
                                            <a href="{{ Storage::url($remit->proof_path) }}" target="_blank" class="inline-flex items-center gap-1 text-[11px] text-primary font-bold hover:underline" title="View Proof Receipt">
                                                <span class="material-symbols-outlined text-[15px]">attachment</span>
                                                <span>Proof</span>
                                            </a>
                                        </td>

                                        {{-- Date --}}
                                        <td class="py-3.5 text-outline">
                                            {{ $remit->created_at->format('M d, Y') }}
                                        </td>

                                        {{-- Status --}}
                                        <td class="py-3.5">
                                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold border uppercase tracking-wider {{ $remit->statusBadgeClass() }}">
                                                {{ str_replace('_', ' ', $remit->status) }}
                                            </span>
                                        </td>

                                        {{-- Actions --}}
                                        <td class="py-3.5 text-right">
                                            <a href="{{ route('admin.logistics-remittances.show', $remit) }}" class="px-3 py-1 rounded-xl bg-primary text-on-primary font-bold text-xs hover:bg-primary/90 transition-colors shadow-2xs">
                                                Review & Verify
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="11" class="py-12 text-center text-on-surface-variant">
                                            <div class="flex flex-col items-center justify-center gap-2">
                                                <span class="material-symbols-outlined text-[40px] text-outline">account_balance</span>
                                                <span class="font-bold text-sm">No logistics remittances found.</span>
                                                <p class="text-xs text-outline max-w-sm">When logistics companies submit COD collections, they will appear here for verification and seller commission settlement.</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($remittances->hasPages())
                        <div class="p-4 border-t border-outline-variant/60">
                            {{ $remittances->links() }}
                        </div>
                    @endif
                </div>

            </div>
        </div>
    </div>
</div>
@endsection

