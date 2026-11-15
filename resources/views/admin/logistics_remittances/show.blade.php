@extends('layouts.app')

@section('title', "Review Remittance #{$remittance->reference_number} — Admin Verification")

@section('content')
<div class="w-full min-h-screen bg-surface" x-data="{ showVerifyModal: false, showRejectModal: false }">
    <div class="flex flex-col lg:flex-row w-full">

        <x-admin-nav active="logistics-remittances" />

        <div class="flex-1 min-w-0 p-4 sm:p-6 lg:p-8 bg-surface">
            <div class="w-full max-w-7xl mx-auto space-y-6">

                {{-- Breadcrumb --}}
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-2 text-xs">
                        <a href="{{ route('admin.dashboard') }}" class="text-primary font-bold hover:underline">Admin Dashboard</a>
                        <span class="text-outline-variant">/</span>
                        <a href="{{ route('admin.logistics-remittances.index') }}" class="text-primary font-bold hover:underline">Logistics Remittances</a>
                        <span class="text-outline-variant">/</span>
                        <span class="text-on-surface-variant font-mono">{{ $remittance->reference_number }}</span>
                    </div>

                    <a href="{{ route('admin.logistics-remittances.index') }}" class="inline-flex items-center gap-1 text-xs text-on-surface-variant hover:text-primary transition-colors">
                        <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                        <span>Back to All Remittances</span>
                    </a>
                </div>

                {{-- Alerts --}}
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

                {{-- Header & Action Controls --}}
                <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 md:p-8 shadow-xs">
                    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 border-b border-outline-variant/60 pb-6">
                        <div>
                            <div class="flex items-center gap-3 flex-wrap">
                                <h1 class="text-xl md:text-2xl font-black text-on-surface font-mono">
                                    {{ $remittance->reference_number }}
                                </h1>
                                <span class="px-3 py-1 rounded-full text-xs font-bold border uppercase tracking-wider {{ $remittance->statusBadgeClass() }}">
                                    {{ str_replace('_', ' ', $remittance->status) }}
                                </span>
                            </div>
                            <p class="text-xs text-on-surface-variant mt-1.5">
                                Submitted by <strong>{{ $remittance->logistics?->logisticsProfile?->company_name ?? $remittance->logistics?->full_name }}</strong> on {{ $remittance->created_at->format('F d, Y \a\t h:i A') }}
                            </p>
                        </div>

                        {{-- Action Buttons (Section 5) --}}
                        <div class="flex items-center gap-2 flex-wrap">
                            @if(!$remittance->isVerified() && !$remittance->isRejected())
                                @if($remittance->isSubmitted())
                                    <form method="POST" action="{{ route('admin.logistics-remittances.under-review', $remittance) }}">
                                        @csrf
                                        <button type="submit" class="px-4 py-2 rounded-xl border border-indigo-500/30 bg-indigo-500/10 text-indigo-800 text-xs font-bold hover:bg-indigo-500/20 transition-colors cursor-pointer">
                                            Mark Under Review
                                        </button>
                                    </form>
                                @endif

                                <button
                                    type="button"
                                    @click="showRejectModal = true"
                                    class="px-4 py-2 rounded-xl border border-rose-500/30 bg-rose-500/10 text-rose-700 text-xs font-bold hover:bg-rose-500/20 transition-colors cursor-pointer">
                                    Reject Remittance
                                </button>

                                <button
                                    type="button"
                                    @click="showVerifyModal = true"
                                    class="px-6 py-2.5 rounded-full bg-primary text-on-primary text-xs font-extrabold shadow-md shadow-primary/20 hover:bg-primary/90 transition-all cursor-pointer flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-[18px]">verified</span>
                                    <span>Verify Funds Received & Settle</span>
                                </button>
                            @elseif($remittance->isVerified())
                                <div class="px-4 py-2 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-800 text-xs font-bold flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-[18px]">check_circle</span>
                                    <span>Verified on {{ $remittance->reviewed_at?->format('M d, Y') }} by {{ $remittance->reviewer?->full_name ?? 'Admin' }}</span>
                                </div>
                            @elseif($remittance->isRejected())
                                <div class="px-4 py-2 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-800 text-xs font-bold flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-[18px]">cancel</span>
                                    <span>Rejected: {{ $remittance->rejection_reason }}</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- 5-Stage Financial Reconciliation Blueprint (Section 1 & 6) --}}
                    <div class="mt-6 pt-2">
                        <span class="text-xs font-bold text-on-surface uppercase tracking-wider block mb-3">
                            Official Cash Flow Breakdown (Reconciled Exact Distribution)
                        </span>

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                            {{-- Stage 1: Buyer to Rider --}}
                            <div class="p-4 rounded-2xl bg-surface-container/60 border border-outline-variant/60 flex flex-col justify-between">
                                <div>
                                    <span class="text-[10px] text-outline uppercase font-bold block">1. Total COD Collected</span>
                                    <span class="text-lg font-black text-on-surface mt-1 block">₱{{ number_format($remittance->total_cod_amount, 2) }}</span>
                                </div>
                                <span class="text-[10px] text-on-surface-variant mt-2 pt-2 border-t border-outline-variant/40">Buyer → Courier</span>
                            </div>

                            {{-- Stage 2: Courier to Logistics --}}
                            <div class="p-4 rounded-2xl bg-surface-container/60 border border-outline-variant/60 flex flex-col justify-between">
                                <div>
                                    <span class="text-[10px] text-outline uppercase font-bold block">2. Rider Remitted</span>
                                    <span class="text-lg font-black text-on-surface mt-1 block">₱{{ number_format($remittance->total_cod_amount, 2) }}</span>
                                </div>
                                <span class="text-[10px] text-on-surface-variant mt-2 pt-2 border-t border-outline-variant/40">Courier → Logistics</span>
                            </div>

                            {{-- Stage 3: Shipping Retained by Logistics --}}
                            <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 flex flex-col justify-between">
                                <div>
                                    <span class="text-[10px] text-emerald-800 uppercase font-bold block">3. Shipping Retained</span>
                                    <span class="text-lg font-black text-emerald-900 mt-1 block">₱{{ number_format($remittance->shipping_retained, 2) }}</span>
                                </div>
                                <span class="text-[10px] text-emerald-700 font-semibold mt-2 pt-2 border-t border-emerald-500/20">Logistics Courier Revenue</span>
                            </div>

                            {{-- Stage 4: Nexora Commission --}}
                            <div class="p-4 rounded-2xl bg-primary/10 border border-primary/20 flex flex-col justify-between">
                                <div>
                                    <span class="text-[10px] text-primary uppercase font-bold block">4. Nexora Commission (10%)</span>
                                    <span class="text-lg font-black text-primary mt-1 block">₱{{ number_format($totalCommission, 2) }}</span>
                                </div>
                                <span class="text-[10px] text-primary/80 font-medium mt-2 pt-2 border-t border-primary/20">Platform Earnings</span>
                            </div>

                            {{-- Stage 5: Net Seller Earnings --}}
                            <div class="p-4 rounded-2xl bg-surface-container-lowest border-2 border-primary/40 flex flex-col justify-between shadow-2xs">
                                <div>
                                    <span class="text-[10px] text-on-surface uppercase font-extrabold block">5. Seller Net Allocation (90%)</span>
                                    <span class="text-lg font-black text-on-surface mt-1 block">₱{{ number_format($totalSellerPayable, 2) }}</span>
                                </div>
                                <span class="text-[10px] text-emerald-700 font-bold mt-2 pt-2 border-t border-outline-variant/40">Payable to Merchants</span>
                            </div>
                        </div>

                        <div class="mt-3 p-3 rounded-xl bg-surface-container-low text-xs text-on-surface-variant flex items-center justify-between flex-wrap gap-2">
                            <span>
                                <strong>Actual Remitted Merchandise to Admin:</strong>
                                <span class="font-mono font-bold text-primary text-sm ml-1">₱{{ number_format($remittance->merchandise_amount, 2) }}</span>
                                <span class="text-outline">(= Commission ₱{{ number_format($totalCommission, 2) }} + Sellers ₱{{ number_format($totalSellerPayable, 2) }})</span>
                            </span>
                            <span class="text-emerald-800 font-semibold text-[11px]">
                                Shipping fee excluded from commission calculation ✓
                            </span>
                        </div>
                    </div>

                    {{-- Carrier Transfer Information & Proof --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6 pt-6 border-t border-outline-variant/60">
                        <div class="space-y-3">
                            <h3 class="text-xs font-bold text-on-surface uppercase tracking-wider">Transfer Details</h3>
                            <div class="bg-surface-container-low p-4 rounded-2xl border border-outline-variant/60 space-y-2 text-xs">
                                <div class="flex justify-between">
                                    <span class="text-on-surface-variant">Logistics Company:</span>
                                    <span class="font-bold text-on-surface">{{ $remittance->logistics?->logisticsProfile?->company_name ?? $remittance->logistics?->full_name }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-on-surface-variant">Transfer Method:</span>
                                    <span class="font-bold text-on-surface uppercase">{{ str_replace('_', ' ', $remittance->transfer_method) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-on-surface-variant">Transaction Reference:</span>
                                    <span class="font-mono font-bold text-primary">{{ $remittance->transfer_reference }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-on-surface-variant">Total Represented Orders:</span>
                                    <span class="font-bold text-on-surface">{{ $remittance->items->count() }} parcel(s)</span>
                                </div>
                                @if($remittance->notes)
                                    <div class="pt-2 border-t border-outline-variant/40">
                                        <span class="text-[11px] text-outline block">Carrier Notes:</span>
                                        <p class="italic text-on-surface mt-0.5">{{ $remittance->notes }}</p>
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Proof Document Preview --}}
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <h3 class="text-xs font-bold text-on-surface uppercase tracking-wider">Payment Proof Document</h3>
                                <a href="{{ Storage::url($remittance->proof_path) }}" target="_blank" class="text-xs text-primary font-bold hover:underline">
                                    Open Full Size ↗
                                </a>
                            </div>

                            <div class="bg-surface-container-low p-2 rounded-2xl border border-outline-variant/60 flex items-center justify-center max-h-60 overflow-hidden">
                                @php
                                    $extension = strtolower(pathinfo($remittance->proof_path, PATHINFO_EXTENSION));
                                @endphp
                                @if(in_array($extension, ['jpg', 'jpeg', 'png', 'webp']))
                                    <img src="{{ Storage::url($remittance->proof_path) }}" alt="Transfer Proof" class="max-h-56 object-contain rounded-xl shadow-xs">
                                @else
                                    <div class="py-10 text-center space-y-2">
                                        <span class="material-symbols-outlined text-[40px] text-primary">picture_as_pdf</span>
                                        <p class="text-xs font-bold text-on-surface">PDF Proof Document Attached</p>
                                        <a href="{{ Storage::url($remittance->proof_path) }}" target="_blank" class="px-4 py-1.5 rounded-full bg-primary text-on-primary text-xs font-bold inline-block">
                                            Download / View PDF
                                        </a>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Breakdown by Seller (Section 6) --}}
                <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant overflow-hidden shadow-xs">
                    <div class="p-5 md:px-6 bg-surface-container-low/60 border-b border-outline-variant/60 flex items-center justify-between">
                        <div>
                            <h2 class="text-sm font-bold text-on-surface">Seller Settlement Allocations Preview</h2>
                            <p class="text-[11px] text-on-surface-variant">Allocated to individual merchants based on verified merchandise orders in this batch.</p>
                        </div>
                        <span class="text-xs font-bold text-primary">{{ count($sellerAllocations) }} merchant(s)</span>
                    </div>

                    <div class="p-5 md:p-6 overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="border-b border-outline-variant text-[11px] text-on-surface-variant uppercase font-semibold">
                                    <th class="pb-3">Merchant / Store Name</th>
                                    <th class="pb-3">Orders</th>
                                    <th class="pb-3 text-right">Eligible Merchandise</th>
                                    <th class="pb-3 text-right text-primary">Nexora 10% Fee</th>
                                    <th class="pb-3 text-right text-emerald-800">Net Seller Payable (90%)</th>
                                    <th class="pb-3">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline-variant/40">
                                @foreach($sellerAllocations as $sellerId => $alloc)
                                    <tr class="hover:bg-surface-container-low/40">
                                        <td class="py-3 font-semibold text-on-surface">
                                            <span>{{ $alloc['seller']?->sellerProfile?->business_name ?? $alloc['seller']?->full_name ?? 'Merchant #' . $sellerId }}</span>
                                            <span class="block text-[10px] text-outline font-mono">{{ $alloc['seller']?->email }}</span>
                                        </td>
                                        <td class="py-3">
                                            <span class="px-2 py-0.5 rounded-md bg-surface-container border border-outline-variant/60 font-semibold">
                                                {{ $alloc['orders_count'] }} order(s)
                                            </span>
                                        </td>
                                        <td class="py-3 text-right font-medium">
                                            ₱{{ number_format($alloc['merchandise_total'], 2) }}
                                        </td>
                                        <td class="py-3 text-right font-bold text-primary">
                                            ₱{{ number_format($alloc['commission_total'], 2) }}
                                        </td>
                                        <td class="py-3 text-right font-extrabold text-emerald-800">
                                            ₱{{ number_format($alloc['payable_total'], 2) }}
                                        </td>
                                        <td class="py-3">
                                            @if($remittance->isVerified())
                                                <span class="px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-800 text-[10px] font-bold border border-emerald-500/20">
                                                    Settled / Available
                                                </span>
                                            @else
                                                <span class="px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-800 text-[10px] font-bold border border-amber-500/20">
                                                    Pending Verification
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Full Itemized Orders List --}}
                <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant overflow-hidden shadow-xs">
                    <div class="p-5 md:px-6 bg-surface-container-low/60 border-b border-outline-variant/60 flex items-center justify-between">
                        <h2 class="text-sm font-bold text-on-surface">Itemized Delivered Parcels</h2>
                        <span class="text-xs text-on-surface-variant">{{ $remittance->items->count() }} total</span>
                    </div>

                    <div class="p-5 md:p-6 overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="border-b border-outline-variant text-[11px] text-on-surface-variant uppercase font-semibold">
                                    <th class="pb-3">Order Number</th>
                                    <th class="pb-3">Merchant</th>
                                    <th class="pb-3">Buyer</th>
                                    <th class="pb-3 text-right">Total COD</th>
                                    <th class="pb-3 text-right text-emerald-800">Shipping (Retained)</th>
                                    <th class="pb-3 text-right text-primary">Merchandise Amount</th>
                                    <th class="pb-3 text-right">10% Platform</th>
                                    <th class="pb-3 text-right font-bold text-emerald-800">90% Seller</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline-variant/40">
                                @foreach($remittance->items as $item)
                                    @php
                                        $order = $item->order;
                                        $comm = round($item->merchandise_component * 0.10, 2);
                                        $sell = round($item->merchandise_component * 0.90, 2);
                                    @endphp
                                    <tr class="hover:bg-surface-container-low/40">
                                        <td class="py-3 font-mono font-bold text-on-surface">{{ $order->order_number }}</td>
                                        <td class="py-3">{{ $order->seller?->sellerProfile?->business_name ?? $order->seller?->full_name }}</td>
                                        <td class="py-3">{{ $order->buyer?->full_name }}</td>
                                        <td class="py-3 text-right font-medium">₱{{ number_format($item->total_cod_amount, 2) }}</td>
                                        <td class="py-3 text-right font-bold text-emerald-800">₱{{ number_format($item->shipping_component, 2) }}</td>
                                        <td class="py-3 text-right font-extrabold text-primary">₱{{ number_format($item->merchandise_component, 2) }}</td>
                                        <td class="py-3 text-right text-outline">₱{{ number_format($comm, 2) }}</td>
                                        <td class="py-3 text-right font-bold text-emerald-800">₱{{ number_format($sell, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>

    {{-- VERIFY REMITTANCE MODAL --}}
    <div
        x-show="showVerifyModal"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
        <div
            @click.away="showVerifyModal = false"
            class="bg-surface-container-lowest rounded-3xl border border-outline-variant max-w-lg w-full p-6 sm:p-8 shadow-xl space-y-4">

            <div class="flex items-center gap-2 text-primary font-bold text-base">
                <span class="material-symbols-outlined text-[24px]">verified</span>
                <span>Confirm Actual Receipt of Merchandise Funds</span>
            </div>

            <p class="text-xs text-on-surface-variant leading-relaxed">
                By approving, you certify that you have verified the actual receipt of
                <strong class="text-primary font-mono text-sm">₱{{ number_format($remittance->merchandise_amount, 2) }}</strong>
                via transfer reference <strong class="font-mono text-on-surface">{{ $remittance->transfer_reference }}</strong>.
            </p>

            <div class="bg-surface-container-low p-3.5 rounded-2xl text-xs space-y-1.5 border border-outline-variant/60">
                <div class="flex justify-between">
                    <span class="text-on-surface-variant">Nexora Platform Commission (10%):</span>
                    <strong class="text-primary font-mono">₱{{ number_format($totalCommission, 2) }}</strong>
                </div>
                <div class="flex justify-between">
                    <span class="text-on-surface-variant">Sellers Credited Allocation (90%):</span>
                    <strong class="text-emerald-800 font-mono">₱{{ number_format($totalSellerPayable, 2) }}</strong>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.logistics-remittances.verify', $remittance) }}" class="space-y-4">
                @csrf
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold text-on-surface-variant">Admin Audit Note (Optional)</label>
                    <textarea name="admin_notes" rows="2" placeholder="e.g. Verified on UnionBank business portal statement" class="rounded-xl border border-outline-variant p-3 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary"></textarea>
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" @click="showVerifyModal = false" class="px-5 py-2.5 rounded-full border border-outline-variant text-xs font-semibold text-on-surface-variant hover:bg-surface-container transition-colors cursor-pointer">
                        Cancel
                    </button>
                    <button type="submit" class="px-6 py-2.5 rounded-full bg-primary text-on-primary text-xs font-bold shadow-md shadow-primary/20 hover:bg-primary/90 transition-all cursor-pointer">
                        Confirm & Settle Funds
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- REJECT REMITTANCE MODAL --}}
    <div
        x-show="showRejectModal"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
        <div
            @click.away="showRejectModal = false"
            class="bg-surface-container-lowest rounded-3xl border border-outline-variant max-w-lg w-full p-6 sm:p-8 shadow-xl space-y-4">

            <div class="flex items-center gap-2 text-rose-700 font-bold text-base">
                <span class="material-symbols-outlined text-[24px]">warning</span>
                <span>Reject Logistics Remittance</span>
            </div>

            <p class="text-xs text-on-surface-variant leading-relaxed">
                Rejecting this remittance will unlock its {{ $remittance->items->count() }} orders so the carrier can investigate the discrepancy and re-submit.
            </p>

            <form method="POST" action="{{ route('admin.logistics-remittances.reject', $remittance) }}" class="space-y-4">
                @csrf
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold text-on-surface-variant">Reason for Rejection <span class="text-rose-500">*</span></label>
                    <textarea name="rejection_reason" required rows="3" placeholder="e.g. Reference number not found in bank statement / Deposit slip image unreadable" class="rounded-xl border border-outline-variant p-3 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-rose-500"></textarea>
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" @click="showRejectModal = false" class="px-5 py-2.5 rounded-full border border-outline-variant text-xs font-semibold text-on-surface-variant hover:bg-surface-container transition-colors cursor-pointer">
                        Cancel
                    </button>
                    <button type="submit" class="px-6 py-2.5 rounded-full bg-rose-600 text-white text-xs font-bold shadow-md shadow-rose-600/20 hover:bg-rose-700 transition-all cursor-pointer">
                        Reject Remittance Batch
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection

