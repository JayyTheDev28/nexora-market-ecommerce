@extends('layouts.app')

@section('title', 'COD Remittance Reconciliation — Logistics Operations')

@section('content')
<div class="w-full min-h-screen bg-surface" x-data="{
    reviewModalOpen: false,
    activeRemitId: null,
    activeRemitNumber: '',
    activeCourier: '',
    activeMethod: 'physical_cash',
    activeExternalRef: '',
    activeAmount: 0,
    activeExpected: 0,
    actualReceived: '',
    decision: 'verify_full'
}">
    <div class="flex flex-col lg:flex-row w-full">

        <x-logistics-nav active="remittances" />

        <div class="flex-1 min-w-0 p-4 sm:p-6 lg:p-8 bg-surface">
            <div class="w-full max-w-7xl mx-auto space-y-6">

                {{-- Breadcrumb --}}
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-2 text-xs">
                        <a href="{{ route('logistics.dashboard') }}" class="text-primary font-bold hover:underline">Logistics Dashboard</a>
                        <span class="text-outline-variant">/</span>
                        <span class="text-on-surface-variant">Rider COD Remittance Reconciliation</span>
                    </div>
                    <a href="{{ route('logistics.admin-remittances.index') }}" class="inline-flex items-center gap-1 text-xs text-primary font-bold hover:underline">
                        <span class="material-symbols-outlined text-[16px]">account_balance</span>
                        <span>Logistics → Admin Remittance</span>
                    </a>
                </div>

                {{-- Alerts --}}
                @if (session('status'))
                    <div class="bg-primary/10 border border-primary/30 rounded-2xl p-4 flex items-center gap-3 shadow-2xs">
                        <span class="material-symbols-outlined text-primary text-[22px]">check_circle</span>
                        <p class="font-body-sm text-xs md:text-sm text-primary font-semibold">{{ session('status') }}</p>
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

                {{-- Header & KPI Cards --}}
                <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 md:p-8 shadow-xs">
                    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                        <div>
                            <h1 class="text-xl md:text-2xl font-bold text-on-surface flex items-center gap-2">
                                <span class="material-symbols-outlined text-primary text-[26px]">payments</span>
                                <span>Rider COD Cash Accountability & Reconciliation</span>
                            </h1>
                            <p class="text-xs text-on-surface-variant mt-1">
                                Verify physical cash envelopes and manual GCash/Maya/Bank proofs submitted by delivery riders before releasing orders to Logistics-to-Admin batches.
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3.5 mt-6 pt-6 border-t border-outline-variant/60">
                        <div class="bg-surface-container/50 rounded-2xl p-4 border border-outline-variant/40">
                            <span class="text-[10px] text-on-surface-variant uppercase font-medium block">Awaiting Verification</span>
                            <span class="text-xl font-bold text-amber-800 mt-1 block">{{ $pendingCount }}</span>
                            <span class="text-[10px] text-outline mt-0.5 block">₱{{ number_format($pendingTotal, 2) }} submitted</span>
                        </div>
                        <div class="bg-surface-container/50 rounded-2xl p-4 border border-outline-variant/40">
                            <span class="text-[10px] text-on-surface-variant uppercase font-medium block">Verified Cash Received</span>
                            <span class="text-xl font-bold text-emerald-800 mt-1 block">₱{{ number_format($approvedTotal, 2) }}</span>
                            <span class="text-[10px] text-outline mt-0.5 block">Counted & settled</span>
                        </div>
                        <div class="bg-surface-container/50 rounded-2xl p-4 border border-outline-variant/40">
                            <span class="text-[10px] text-on-surface-variant uppercase font-medium block">Rider COD Still Held</span>
                            <span class="text-xl font-bold text-primary mt-1 block">₱{{ number_format($outstandingRiderCod, 2) }}</span>
                            <span class="text-[10px] text-outline mt-0.5 block">Open collections</span>
                        </div>
                        <div class="bg-surface-container/50 rounded-2xl p-4 border border-outline-variant/40">
                            <span class="text-[10px] text-on-surface-variant uppercase font-medium block">Flagged / Variance</span>
                            <span class="text-xl font-bold {{ $discrepancyCount > 0 ? 'text-rose-600' : 'text-on-surface' }} mt-1 block">{{ $discrepancyCount }}</span>
                            <span class="text-[10px] text-outline mt-0.5 block">Needs investigation</span>
                        </div>
                        <div class="bg-rose-500/5 rounded-2xl p-4 border border-rose-500/20">
                            <span class="text-[10px] text-rose-800 uppercase font-medium block">Recorded Shortages</span>
                            <span class="text-xl font-bold text-rose-700 mt-1 block">₱{{ number_format($shortageTotal, 2) }}</span>
                            <span class="text-[10px] text-rose-600/80 mt-0.5 block">Remains rider liability</span>
                        </div>
                        <div class="bg-indigo-500/5 rounded-2xl p-4 border border-indigo-500/20">
                            <span class="text-[10px] text-indigo-800 uppercase font-medium block">Recorded Overpayments</span>
                            <span class="text-xl font-bold text-indigo-700 mt-1 block">₱{{ number_format($overpaymentTotal, 2) }}</span>
                            <span class="text-[10px] text-indigo-600/80 mt-0.5 block">Pending adjustment</span>
                        </div>
                    </div>
                </div>

                {{-- Filter Bar --}}
                <form method="GET" action="{{ route('logistics.remittances.index') }}" class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4 flex flex-wrap items-center gap-3">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search remittance #, reference, rider..."
                        class="px-3.5 py-2 rounded-xl text-xs bg-surface-container/40 border border-outline-variant text-on-surface w-64">
                    <select name="status" class="px-3.5 py-2 rounded-xl text-xs bg-surface-container/40 border border-outline-variant text-on-surface">
                        <option value="">All Statuses</option>
                        <option value="open" @selected(request('status') === 'open')>Awaiting Verification</option>
                        <option value="verified" @selected(request('status') === 'verified')>Verified</option>
                        <option value="partially_verified" @selected(request('status') === 'partially_verified')>Partially Verified</option>
                        <option value="discrepancy_flagged" @selected(request('status') === 'discrepancy_flagged')>Discrepancy Flagged</option>
                        <option value="rejected" @selected(request('status') === 'rejected')>Rejected</option>
                    </select>
                    <select name="payment_method" class="px-3.5 py-2 rounded-xl text-xs bg-surface-container/40 border border-outline-variant text-on-surface">
                        <option value="">All Channels</option>
                        <option value="physical_cash" @selected(request('payment_method') === 'physical_cash')>Physical Cash</option>
                        <option value="gcash" @selected(request('payment_method') === 'gcash')>GCash (Manual Verify)</option>
                        <option value="maya" @selected(request('payment_method') === 'maya')>Maya (Manual Verify)</option>
                        <option value="bank_transfer" @selected(request('payment_method') === 'bank_transfer')>Bank Transfer</option>
                    </select>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-primary text-on-primary text-xs font-bold cursor-pointer">Filter</button>
                    @if(request()->hasAny(['search', 'status', 'payment_method']))
                        <a href="{{ route('logistics.remittances.index') }}" class="text-xs text-outline hover:text-on-surface underline">Reset</a>
                    @endif
                </form>

                {{-- Remittances Table --}}
                <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant overflow-hidden shadow-xs">
                    <div class="p-5 md:px-6 bg-surface-container-low/60 border-b border-outline-variant/60 flex items-center justify-between">
                        <h2 class="text-sm font-bold text-on-surface">Rider COD Remittance Submissions</h2>
                    </div>

                    <div class="p-5 md:p-6 overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="border-b border-outline-variant text-[11px] text-on-surface-variant uppercase font-semibold">
                                    <th class="pb-3">Remittance #</th>
                                    <th class="pb-3">Rider & Orders</th>
                                    <th class="pb-3">Channel / Proof</th>
                                    <th class="pb-3 text-right">Claimed / Expected</th>
                                    <th class="pb-3 text-right">Actual Verified</th>
                                    <th class="pb-3 text-right">Variance</th>
                                    <th class="pb-3">Status</th>
                                    <th class="pb-3">Submitted At</th>
                                    <th class="pb-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline-variant/40">
                                @forelse($remittances as $remit)
                                    @php
                                        $claimed = (float) ($remit->claimed_amount ?? $remit->amount);
                                        $expected = (float) $remit->expected_amount;
                                        $actual = $remit->actual_received_amount !== null ? (float) $remit->actual_received_amount : null;
                                        $shortage = (float) $remit->shortage_amount;
                                        $overpay = (float) $remit->overpayment_amount;
                                        $diff = $claimed - $expected;
                                        $isOpen = $remit->isOpen();
                                    @endphp
                                    <tr class="hover:bg-surface-container-low/40 transition-colors">
                                        <td class="py-3 font-mono font-bold text-primary">
                                            {{ $remit->remittance_number }}
                                            @if($remit->collectingHub)
                                                <span class="block text-[10px] font-sans text-on-surface-variant font-normal">{{ $remit->collectingHub->name }}</span>
                                            @endif
                                        </td>
                                        <td class="py-3">
                                            <span class="font-bold text-on-surface block">{{ $remit->courier?->full_name }}</span>
                                            <span class="text-[10px] text-on-surface-variant block">{{ $remit->courier?->contact_number }}</span>
                                            @if($remit->allocations->isNotEmpty())
                                                <span class="inline-block mt-1 px-2 py-0.5 rounded-md bg-surface-container text-[10px] font-mono text-on-surface-variant">
                                                    {{ $remit->allocations->count() }} order(s): {{ $remit->allocations->take(2)->map(fn($a) => $a->order?->order_number)->filter()->implode(', ') }}{{ $remit->allocations->count() > 2 ? '…' : '' }}
                                                </span>
                                            @endif
                                        </td>
                                        <td class="py-3">
                                            <span class="px-2 py-0.5 rounded-md bg-surface-container text-[10px] font-bold uppercase text-on-surface">
                                                {{ str_replace('_', ' ', $remit->payment_method ?? 'physical_cash') }}
                                            </span>
                                            @if($remit->external_reference)
                                                <span class="block text-[10px] font-mono text-on-surface-variant mt-0.5">Ref: {{ $remit->external_reference }}</span>
                                            @endif
                                            @if($remit->proof_path)
                                                <a href="{{ \Illuminate\Support\Facades\Storage::url($remit->proof_path) }}" target="_blank" class="inline-flex items-center gap-0.5 text-[10px] text-primary font-semibold hover:underline mt-0.5">
                                                    <span class="material-symbols-outlined text-[12px]">receipt_long</span>
                                                    View Proof
                                                </a>
                                            @endif
                                        </td>
                                        <td class="py-3 text-right">
                                            <span class="font-bold text-on-surface block">₱{{ number_format($claimed, 2) }}</span>
                                            <span class="text-[10px] text-on-surface-variant block">Exp: ₱{{ number_format($expected, 2) }}</span>
                                        </td>
                                        <td class="py-3 text-right font-bold text-emerald-800">
                                            {{ $actual !== null ? '₱' . number_format($actual, 2) : '—' }}
                                        </td>
                                        <td class="py-3 text-right">
                                            @if($shortage > 0)
                                                <span class="font-bold text-rose-600 block">-₱{{ number_format($shortage, 2) }} short</span>
                                            @elseif($overpay > 0)
                                                <span class="font-bold text-indigo-700 block">+₱{{ number_format($overpay, 2) }} over</span>
                                            @elseif(abs($diff) > 0.01)
                                                <span class="font-bold text-amber-700 block">{{ $diff > 0 ? '+' : '' }}₱{{ number_format($diff, 2) }}</span>
                                            @else
                                                <span class="text-emerald-700 font-semibold">Match (₱0.00)</span>
                                            @endif
                                        </td>
                                        <td class="py-3">
                                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold
                                                {{ $remit->isVerified() ? 'bg-emerald-500/10 text-emerald-800' :
                                                   ($remit->status === 'discrepancy_flagged' || $remit->status === 'rejected' ? 'bg-rose-500/10 text-rose-800' : 'bg-amber-500/10 text-amber-900') }}">
                                                {{ ucfirst(str_replace('_', ' ', $remit->status)) }}
                                            </span>
                                            @if($remit->discrepancy_reason)
                                                <span class="block text-[10px] text-rose-700 mt-0.5 max-w-[160px] truncate" title="{{ $remit->discrepancy_reason }}">{{ $remit->discrepancy_reason }}</span>
                                            @endif
                                        </td>
                                        <td class="py-3 text-on-surface-variant text-[11px]">
                                            {{ $remit->remitted_at?->format('M d, Y h:i A') }}
                                        </td>
                                        <td class="py-3 text-right">
                                            @if($isOpen)
                                                <button type="button"
                                                    @click="
                                                        activeRemitId = {{ $remit->id }};
                                                        activeRemitNumber = '{{ $remit->remittance_number }}';
                                                        activeCourier = '{{ addslashes($remit->courier?->full_name ?? '') }}';
                                                        activeMethod = '{{ str_replace('_', ' ', $remit->payment_method ?? 'physical_cash') }}';
                                                        activeExternalRef = '{{ addslashes($remit->external_reference ?? '') }}';
                                                        activeAmount = '{{ number_format($claimed, 2, '.', '') }}';
                                                        activeExpected = '{{ number_format($expected, 2, '.', '') }}';
                                                        actualReceived = '{{ number_format($claimed, 2, '.', '') }}';
                                                        decision = 'verify_full';
                                                        reviewModalOpen = true;
                                                    "
                                                    class="px-3 py-1.5 rounded-xl text-xs font-bold bg-primary hover:bg-primary-hover text-on-primary transition-all shadow-2xs cursor-pointer">
                                                    Verify / Reconcile
                                                </button>
                                            @else
                                                <span class="text-[11px] text-outline font-medium">Finalized</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="py-8 text-center text-on-surface-variant">
                                            No rider COD remittance submissions found.
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
        </div>
    </div>

    {{-- Reconciliation Modal --}}
    <div x-show="reviewModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
        <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant max-w-lg w-full p-6 shadow-xl" @click.away="reviewModalOpen = false">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base font-bold text-on-surface">Verify Rider COD Remittance</h3>
                <button type="button" @click="reviewModalOpen = false" class="text-outline hover:text-on-surface cursor-pointer">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <div class="bg-surface-container/40 rounded-2xl p-4 border border-outline-variant/50 text-xs space-y-1.5 mb-4">
                <div class="flex justify-between">
                    <span class="text-on-surface-variant">Remittance #:</span>
                    <strong class="font-mono text-primary" x-text="activeRemitNumber"></strong>
                </div>
                <div class="flex justify-between">
                    <span class="text-on-surface-variant">Rider:</span>
                    <strong class="text-on-surface" x-text="activeCourier"></strong>
                </div>
                <div class="flex justify-between">
                    <span class="text-on-surface-variant">Payment Channel:</span>
                    <strong class="uppercase text-on-surface" x-text="activeMethod"></strong>
                </div>
                <div class="flex justify-between" x-show="activeExternalRef">
                    <span class="text-on-surface-variant">External Reference:</span>
                    <strong class="font-mono text-on-surface" x-text="activeExternalRef"></strong>
                </div>
                <div class="flex justify-between">
                    <span class="text-on-surface-variant">Claimed Submitted Amount:</span>
                    <strong class="text-on-surface" x-text="'₱' + activeAmount"></strong>
                </div>
                <div class="flex justify-between">
                    <span class="text-on-surface-variant">Expected Collectible COD:</span>
                    <strong class="text-on-surface" x-text="'₱' + activeExpected"></strong>
                </div>
            </div>

            <form method="POST" :action="'/logistics/remittances/' + activeRemitId + '/review'">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-on-surface mb-1">Verification Decision <span class="text-rose-500">*</span></label>
                        <select name="decision" x-model="decision" required class="w-full px-4 py-2.5 rounded-2xl text-xs bg-surface-container-lowest border border-outline-variant text-on-surface">
                            <option value="verify_full">Verify Cash Received (Full / Counted Amount)</option>
                            <option value="verify_partial">Partially Verify & Record Shortage Balance</option>
                            <option value="under_review">Mark Under Review</option>
                            <option value="flag_discrepancy">Flag Discrepancy (Hold for Audit)</option>
                            <option value="reject">Reject Remittance (Release Orders to Rider)</option>
                        </select>
                    </div>

                    <div x-show="decision === 'verify_full' || decision === 'verify_partial'">
                        <label class="block text-xs font-semibold text-on-surface mb-1">Actual Cash / Transfer Verified (₱)</label>
                        <input type="number" step="0.01" min="0" name="actual_received_amount" x-model="actualReceived"
                            class="w-full px-4 py-2.5 rounded-xl text-xs bg-surface-container-lowest border border-outline-variant text-on-surface">
                        <p class="text-[10px] text-on-surface-variant mt-1">If counted amount is lower than expected, difference remains rider accountability and is recorded as a shortage.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-on-surface mb-1">Hub Receipt / Bank Audit Reference</label>
                        <input type="text" name="verification_reference" placeholder="e.g. HUB-CASH-2026-01 or GCASH-VERIFIED-991"
                            class="w-full px-4 py-2 rounded-xl text-xs bg-surface-container-lowest border border-outline-variant text-on-surface">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-on-surface mb-1">Discrepancy / Audit Reason</label>
                        <input type="text" name="discrepancy_reason" placeholder="Required if flagging discrepancy or short payment"
                            class="w-full px-4 py-2 rounded-xl text-xs bg-surface-container-lowest border border-outline-variant text-on-surface">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-on-surface mb-1">Operator Notes</label>
                        <textarea name="operator_notes" rows="2" placeholder="Counted physical bills / verified proof screenshot."
                            class="w-full px-4 py-2 rounded-xl text-xs bg-surface-container-lowest border border-outline-variant text-on-surface"></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 mt-6 pt-4 border-t border-outline-variant">
                    <button type="button" @click="reviewModalOpen = false" class="px-4 py-2 rounded-xl text-xs font-semibold bg-surface-container text-on-surface cursor-pointer">
                        Cancel
                    </button>
                    <button type="submit" class="px-5 py-2.5 rounded-2xl text-xs font-bold bg-primary hover:bg-primary-hover text-on-primary shadow-xs cursor-pointer">
                        Confirm Decision
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
