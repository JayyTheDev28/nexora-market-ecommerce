@extends('layouts.app')

@section('title', "Remittance #{$remittance->reference_number} — Logistics Operations")

@section('content')
<div class="w-full min-h-screen bg-surface">
    <div class="flex flex-col lg:flex-row w-full">

        <x-logistics-nav active="admin-remittances" />

        <div class="flex-1 min-w-0 p-4 sm:p-6 lg:p-8 bg-surface">
            <div class="w-full max-w-7xl mx-auto space-y-6">

                {{-- Breadcrumb --}}
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-2 text-xs">
                        <a href="{{ route('logistics.dashboard') }}" class="text-primary font-bold hover:underline">Logistics Dashboard</a>
                        <span class="text-outline-variant">/</span>
                        <a href="{{ route('logistics.admin-remittances.index') }}" class="text-primary font-bold hover:underline">Admin Remittances</a>
                        <span class="text-outline-variant">/</span>
                        <span class="text-on-surface-variant font-mono">{{ $remittance->reference_number }}</span>
                    </div>

                    <a href="{{ route('logistics.admin-remittances.index') }}" class="inline-flex items-center gap-1 text-xs text-on-surface-variant hover:text-primary transition-colors">
                        <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                        <span>Back to Remittances</span>
                    </a>
                </div>

                {{-- Header & Status Card --}}
                <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 md:p-8 shadow-xs">
                    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 border-b border-outline-variant/60 pb-6">
                        <div>
                            <div class="flex items-center gap-2.5">
                                <h1 class="text-xl md:text-2xl font-extrabold text-on-surface font-mono">
                                    {{ $remittance->reference_number }}
                                </h1>
                                <span class="px-3 py-1 rounded-full text-xs font-bold border uppercase tracking-wider {{ $remittance->statusBadgeClass() }}">
                                    {{ str_replace('_', ' ', $remittance->status) }}
                                </span>
                            </div>
                            <p class="text-xs text-on-surface-variant mt-1">
                                Submitted on {{ $remittance->created_at->format('F d, Y \a\t h:i A') }} • Representing {{ $remittance->items->count() }} delivered COD parcel(s)
                            </p>
                        </div>

                        {{-- Proof Link --}}
                        <div class="flex items-center gap-2">
                            <a
                                href="{{ Storage::url($remittance->proof_path) }}"
                                target="_blank"
                                class="px-4 py-2 rounded-xl border border-outline-variant bg-surface hover:bg-surface-container text-xs font-bold text-primary flex items-center gap-1.5 transition-colors">
                                <span class="material-symbols-outlined text-[18px]">receipt_long</span>
                                <span>View Proof of Transfer</span>
                            </a>
                        </div>
                    </div>

                    {{-- Financial Distribution Cards (Section 1 & 4) --}}
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-6">
                        <div class="bg-surface-container/50 rounded-2xl p-4 border border-outline-variant/40">
                            <span class="text-[10px] text-on-surface-variant uppercase font-semibold block tracking-wider">Total COD Collected</span>
                            <span class="text-2xl font-extrabold text-on-surface mt-1 block">₱{{ number_format($remittance->total_cod_amount, 2) }}</span>
                            <span class="text-[10px] text-outline mt-0.5 block">Full amount collected by courier riders</span>
                        </div>

                        <div class="bg-emerald-500/10 rounded-2xl p-4 border border-emerald-500/20">
                            <span class="text-[10px] text-emerald-800 uppercase font-semibold block tracking-wider flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">local_shipping</span>
                                <span>Shipping Fees Retained</span>
                            </span>
                            <span class="text-2xl font-extrabold text-emerald-900 mt-1 block">₱{{ number_format($remittance->shipping_retained, 2) }}</span>
                            <span class="text-[10px] text-emerald-700 font-medium mt-0.5 block">100% Retained Courier Service Revenue</span>
                        </div>

                        <div class="bg-primary/10 rounded-2xl p-4 border border-primary/20">
                            <span class="text-[10px] text-primary uppercase font-semibold block tracking-wider flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">account_balance</span>
                                <span>Merchandise Remitted to Admin</span>
                            </span>
                            <span class="text-2xl font-extrabold text-primary mt-1 block">₱{{ number_format($remittance->merchandise_amount, 2) }}</span>
                            <span class="text-[10px] text-primary/80 mt-0.5 block">Transferred to Nexora for Seller settlement</span>
                        </div>
                    </div>

                    {{-- Transaction & Review Meta --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-6 pt-6 border-t border-outline-variant/60 text-xs">
                        <div class="space-y-1.5">
                            <span class="font-bold text-on-surface block">Transfer Details:</span>
                            <div class="text-on-surface-variant">Method: <strong class="text-on-surface uppercase">{{ str_replace('_', ' ', $remittance->transfer_method) }}</strong></div>
                            <div class="text-on-surface-variant">Transaction Ref: <strong class="font-mono text-on-surface">{{ $remittance->transfer_reference }}</strong></div>
                            @if($remittance->notes)
                                <div class="text-on-surface-variant mt-2 italic bg-surface-container-low p-2.5 rounded-xl border border-outline-variant/40">
                                    "{{ $remittance->notes }}"
                                </div>
                            @endif
                        </div>

                        <div class="space-y-1.5">
                            <span class="font-bold text-on-surface block">Admin Verification Status:</span>
                            @if($remittance->isVerified())
                                <div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-800">
                                    <span class="font-bold flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[16px]">verified</span>
                                        <span>Verified & Approved by Nexora Finance</span>
                                    </span>
                                    <span class="text-[11px] block mt-0.5">Approved on {{ $remittance->reviewed_at?->format('M d, Y h:i A') }}</span>
                                </div>
                            @elseif($remittance->isRejected())
                                <div class="p-3 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-800">
                                    <span class="font-bold flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[16px]">error</span>
                                        <span>Rejection Reason:</span>
                                    </span>
                                    <p class="text-xs mt-1">{{ $remittance->rejection_reason }}</p>
                                </div>
                            @else
                                <div class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-800">
                                    <span class="font-bold flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[16px]">pending</span>
                                        <span>Pending Admin Bank Verification</span>
                                    </span>
                                    <span class="text-[11px] block mt-0.5">Nexora Finance is verifying deposit receipt with transaction ref {{ $remittance->transfer_reference }}.</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Itemized Orders Table --}}
                <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant overflow-hidden shadow-xs">
                    <div class="p-5 md:px-6 bg-surface-container-low/60 border-b border-outline-variant/60 flex items-center justify-between">
                        <h2 class="text-sm font-bold text-on-surface">Itemized Parcels in This Remittance Batch</h2>
                        <span class="text-xs text-on-surface-variant">{{ $remittance->items->count() }} orders</span>
                    </div>

                    <div class="p-5 md:p-6 overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="border-b border-outline-variant text-[11px] text-on-surface-variant uppercase font-semibold">
                                    <th class="pb-3">Order Number</th>
                                    <th class="pb-3">Merchant / Seller</th>
                                    <th class="pb-3">Buyer</th>
                                    <th class="pb-3 text-right">Total COD</th>
                                    <th class="pb-3 text-right text-emerald-800">Shipping (Retained)</th>
                                    <th class="pb-3 text-right text-primary">Merchandise (Remitted)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline-variant/40">
                                @foreach($remittance->items as $item)
                                    @php $order = $item->order; @endphp
                                    <tr class="hover:bg-surface-container-low/40 transition-colors">
                                        <td class="py-3 font-mono font-bold text-on-surface">
                                            {{ $order->order_number }}
                                        </td>
                                        <td class="py-3">
                                            <span class="font-semibold text-on-surface block">
                                                {{ $order->seller?->sellerProfile?->business_name ?? $order->seller?->full_name }}
                                            </span>
                                            <span class="text-[10px] text-outline font-mono">{{ $order->seller?->email }}</span>
                                        </td>
                                        <td class="py-3">
                                            <span>{{ $order->buyer?->full_name }}</span>
                                        </td>
                                        <td class="py-3 text-right font-medium">
                                            ₱{{ number_format($item->total_cod_amount, 2) }}
                                        </td>
                                        <td class="py-3 text-right font-bold text-emerald-800">
                                            ₱{{ number_format($item->shipping_component, 2) }}
                                        </td>
                                        <td class="py-3 text-right font-extrabold text-primary">
                                            ₱{{ number_format($item->merchandise_component, 2) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-surface-container-low/50 font-bold border-t-2 border-outline-variant">
                                <tr>
                                    <td colspan="3" class="py-3 px-2 text-right uppercase text-[11px] text-on-surface-variant">Batch Totals:</td>
                                    <td class="py-3 text-right font-extrabold">₱{{ number_format($remittance->total_cod_amount, 2) }}</td>
                                    <td class="py-3 text-right font-extrabold text-emerald-800">₱{{ number_format($remittance->shipping_retained, 2) }}</td>
                                    <td class="py-3 text-right font-extrabold text-primary">₱{{ number_format($remittance->merchandise_amount, 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
@endsection

