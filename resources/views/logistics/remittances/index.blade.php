@extends('layouts.app')

@section('title', 'COD Remittance Reconciliation — Logistics Operations')

@section('content')
<div class="w-full bg-surface py-8 min-h-[85vh]" x-data="{ reviewModalOpen: false, activeRemitId: null, activeRemitNumber: '', activeCourier: '', activeAmount: 0, activeExpected: 0, decision: 'approve' }">
    <div class="max-w-6xl mx-auto px-margin-mobile md:px-margin-desktop">

        {{-- Breadcrumb --}}
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-2 text-xs">
                <a href="{{ route('logistics.dashboard') }}" class="text-primary font-bold hover:underline">Logistics Dashboard</a>
                <span class="text-outline-variant">/</span>
                <span class="text-on-surface-variant">COD Remittance Reconciliation</span>
            </div>
        </div>

        {{-- Alerts --}}
        @if (session('status'))
            <div class="mb-6 bg-primary/10 border border-primary/30 rounded-2xl p-4 flex items-center gap-3 shadow-2xs">
                <span class="material-symbols-outlined text-primary text-[22px]">check_circle</span>
                <p class="font-body-sm text-xs md:text-sm text-primary font-semibold">{{ session('status') }}</p>
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 bg-rose-500/10 border border-rose-500/20 rounded-2xl p-4 text-xs text-rose-700">
                <ul class="list-disc list-inside space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Header Card --}}
        <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 md:p-8 shadow-xs mb-8">
            <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                <div>
                    <h1 class="text-xl md:text-2xl font-bold text-on-surface flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary text-[26px]">account_balance</span>
                        <span>COD Cash Remittance & Reconciliation Dashboard</span>
                    </h1>
                    <p class="text-xs text-on-surface-variant mt-1">
                        Reconcile and approve cash remittances submitted by delivery couriers at end-of-day. Differences are automatically flagged for review.
                    </p>
                </div>
            </div>

            {{-- Stat Cards --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-6 pt-6 border-t border-outline-variant/60">
                <div class="bg-surface-container/50 rounded-2xl p-4 border border-outline-variant/40">
                    <span class="text-[10px] text-on-surface-variant uppercase font-medium block">Pending Remittances</span>
                    <span class="text-2xl font-bold text-amber-800 mt-1 block">{{ $pendingCount }}</span>
                    <span class="text-[10px] text-outline mt-0.5 block">Waiting for review</span>
                </div>
                <div class="bg-surface-container/50 rounded-2xl p-4 border border-outline-variant/40">
                    <span class="text-[10px] text-on-surface-variant uppercase font-medium block">Pending Cash Total</span>
                    <span class="text-2xl font-bold text-primary mt-1 block">₱{{ number_format($pendingTotal, 2) }}</span>
                    <span class="text-[10px] text-outline mt-0.5 block">Unsettled cash</span>
                </div>
                <div class="bg-surface-container/50 rounded-2xl p-4 border border-outline-variant/40">
                    <span class="text-[10px] text-on-surface-variant uppercase font-medium block">Total Approved Settled</span>
                    <span class="text-2xl font-bold text-emerald-800 mt-1 block">₱{{ number_format($approvedTotal, 2) }}</span>
                    <span class="text-[10px] text-outline mt-0.5 block">Turned over cash</span>
                </div>
                <div class="bg-surface-container/50 rounded-2xl p-4 border border-outline-variant/40">
                    <span class="text-[10px] text-on-surface-variant uppercase font-medium block">Flagged Discrepancies</span>
                    <span class="text-2xl font-bold {{ $discrepancyCount > 0 ? 'text-rose-600' : 'text-on-surface' }} mt-1 block">{{ $discrepancyCount }}</span>
                    <span class="text-[10px] text-outline mt-0.5 block">Requires investigation</span>
                </div>
            </div>
        </div>

        {{-- Remittances Table --}}
        <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant overflow-hidden shadow-xs">
            <div class="p-5 md:px-6 bg-surface-container-low/60 border-b border-outline-variant/60 flex items-center justify-between">
                <h2 class="text-sm font-bold text-on-surface">Courier Cash Remittance Submissions</h2>
            </div>

            <div class="p-5 md:p-6 overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-outline-variant text-[11px] text-on-surface-variant uppercase font-semibold">
                            <th class="pb-3">Remittance #</th>
                            <th class="pb-3">Courier Name</th>
                            <th class="pb-3 text-right">Submitted Cash</th>
                            <th class="pb-3 text-right">Expected COD</th>
                            <th class="pb-3 text-right">Discrepancy</th>
                            <th class="pb-3">Status</th>
                            <th class="pb-3">Submitted At</th>
                            <th class="pb-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/40">
                        @forelse($remittances as $remit)
                            @php
                                $diff = (float) $remit->amount - (float) $remit->expected_amount;
                                $hasDiff = abs($diff) > 0.01;
                            @endphp
                            <tr class="hover:bg-surface-container-low/40 transition-colors">
                                <td class="py-3 font-mono font-bold text-primary">{{ $remit->remittance_number }}</td>
                                <td class="py-3">
                                    <span class="font-bold text-on-surface block">{{ $remit->courier->full_name }}</span>
                                    <span class="text-[10px] text-on-surface-variant">{{ $remit->courier->contact_number }}</span>
                                </td>
                                <td class="py-3 text-right font-bold text-on-surface">₱{{ number_format($remit->amount, 2) }}</td>
                                <td class="py-3 text-right font-medium text-on-surface-variant">₱{{ number_format($remit->expected_amount, 2) }}</td>
                                <td class="py-3 text-right">
                                    @if($hasDiff)
                                        <span class="font-bold text-rose-600">
                                            {{ $diff > 0 ? '+' : '' }}₱{{ number_format($diff, 2) }}
                                        </span>
                                    @else
                                        <span class="text-emerald-700 font-semibold">Match (₱0.00)</span>
                                    @endif
                                </td>
                                <td class="py-3">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold
                                        {{ $remit->status === 'approved' ? 'bg-emerald-500/10 text-emerald-800' :
                                           ($remit->status === 'discrepancy_flagged' ? 'bg-rose-500/10 text-rose-800' : 'bg-amber-500/10 text-amber-900') }}">
                                        {{ ucfirst(str_replace('_', ' ', $remit->status)) }}
                                    </span>
                                </td>
                                <td class="py-3 text-on-surface-variant text-[11px]">
                                    {{ $remit->remitted_at->format('M d, Y h:i A') }}
                                </td>
                                <td class="py-3 text-right">
                                    @if($remit->status === 'pending')
                                        <button type="button"
                                            @click="activeRemitId = {{ $remit->id }}; activeRemitNumber = '{{ $remit->remittance_number }}'; activeCourier = '{{ $remit->courier->full_name }}'; activeAmount = '{{ number_format($remit->amount, 2) }}'; activeExpected = '{{ number_format($remit->expected_amount, 2) }}'; reviewModalOpen = true"
                                            class="px-3 py-1.5 rounded-xl text-xs font-bold bg-primary hover:bg-primary-hover text-on-primary transition-all shadow-2xs">
                                            Reconcile / Review
                                        </button>
                                    @else
                                        <span class="text-[11px] text-outline font-medium">Resolved</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-8 text-center text-on-surface-variant">
                                    No remittance submissions found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="mt-4">
                    {{ $remittances->links() }}
                </div>
            </div>
        </div>
    </div>

    {{-- Review Modal --}}
    <div x-show="reviewModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
        <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant max-w-md w-full p-6 shadow-xl" @click.away="reviewModalOpen = false">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base font-bold text-on-surface">Reconcile COD Remittance</h3>
                <button type="button" @click="reviewModalOpen = false" class="text-outline hover:text-on-surface">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <div class="bg-surface-container/40 rounded-2xl p-4 border border-outline-variant/50 text-xs space-y-1 mb-4">
                <div class="flex justify-between">
                    <span class="text-on-surface-variant">Remittance:</span>
                    <strong class="font-mono text-primary" x-text="activeRemitNumber"></strong>
                </div>
                <div class="flex justify-between">
                    <span class="text-on-surface-variant">Courier:</span>
                    <strong class="text-on-surface" x-text="activeCourier"></strong>
                </div>
                <div class="flex justify-between">
                    <span class="text-on-surface-variant">Submitted Amount:</span>
                    <strong class="text-on-surface" x-text="'₱' + activeAmount"></strong>
                </div>
                <div class="flex justify-between">
                    <span class="text-on-surface-variant">Expected Amount:</span>
                    <strong class="text-on-surface" x-text="'₱' + activeExpected"></strong>
                </div>
            </div>

            <form method="POST" :action="'/logistics/remittances/' + activeRemitId + '/review'">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-on-surface mb-1">Reconciliation Decision <span class="text-rose-500">*</span></label>
                        <select name="decision" x-model="decision" required class="w-full px-4 py-2.5 rounded-2xl text-xs bg-surface-container-lowest border border-outline-variant text-on-surface">
                            <option value="approve">Approve Remittance (Deduct from Courier COD Balance)</option>
                            <option value="flag">Flag Discrepancy (Hold for Audit)</option>
                            <option value="reject">Reject Remittance</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-on-surface mb-1">Operator Notes / Audit Reason</label>
                        <textarea name="operator_notes" rows="2" placeholder="e.g. Verified physical cash envelope against delivery receipts."
                            class="w-full px-4 py-2 rounded-xl text-xs bg-surface-container-lowest border border-outline-variant text-on-surface"></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 mt-6 pt-4 border-t border-outline-variant">
                    <button type="button" @click="reviewModalOpen = false" class="px-4 py-2 rounded-xl text-xs font-semibold bg-surface-container text-on-surface">
                        Cancel
                    </button>
                    <button type="submit" class="px-5 py-2.5 rounded-2xl text-xs font-bold bg-primary hover:bg-primary-hover text-on-primary shadow-xs">
                        Confirm Decision
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
