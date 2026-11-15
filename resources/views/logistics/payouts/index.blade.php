@extends('layouts.app')

@section('title', 'Courier Earnings Payouts — Logistics Operations')

@section('content')
<div class="w-full min-h-screen bg-surface" x-data="{ payModal: false, rejectModal: false, activeId: null, activeNumber: '', activeAmount: '' }">
    <div class="flex flex-col lg:flex-row w-full">
        <x-logistics-nav active="payouts" />

        <div class="flex-1 min-w-0 p-4 sm:p-6 lg:p-8 bg-surface">
            <div class="w-full max-w-7xl mx-auto space-y-6">

                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2 text-xs">
                        <a href="{{ route('logistics.dashboard') }}" class="text-primary font-bold hover:underline">Logistics Dashboard</a>
                        <span class="text-outline-variant">/</span>
                        <span class="text-on-surface-variant">Courier Compensation Payouts</span>
                    </div>
                    <a href="{{ route('logistics.compensation.index') }}" class="text-xs text-primary font-bold hover:underline">Compensation Rate Schedules →</a>
                </div>

                @if (session('status'))
                    <div class="bg-primary/10 border border-primary/30 rounded-2xl p-4 text-xs md:text-sm text-primary font-semibold">{{ session('status') }}</div>
                @endif

                @if ($errors->any())
                    <div class="bg-rose-500/10 border border-rose-500/20 rounded-2xl p-4 text-xs text-rose-700">
                        <ul class="list-disc list-inside space-y-0.5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif

                {{-- KPI Summary --}}
                <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
                    <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4">
                        <span class="text-[10px] text-on-surface-variant uppercase font-semibold block">Pending Payout Requests</span>
                        <span class="text-2xl font-extrabold text-amber-800 mt-1 block">{{ $stats['requestedCount'] }}</span>
                    </div>
                    <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4">
                        <span class="text-[10px] text-on-surface-variant uppercase font-semibold block">Queued Payout Total</span>
                        <span class="text-2xl font-extrabold text-primary mt-1 block">₱{{ number_format($stats['requestedAmount'], 2) }}</span>
                    </div>
                    <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4">
                        <span class="text-[10px] text-on-surface-variant uppercase font-semibold block">Disbursed Payouts</span>
                        <span class="text-2xl font-extrabold text-emerald-800 mt-1 block">₱{{ number_format($stats['paidAmount'], 2) }}</span>
                    </div>
                    <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4">
                        <span class="text-[10px] text-on-surface-variant uppercase font-semibold block">Rider Earned Total</span>
                        <span class="text-xl font-bold text-on-surface mt-1 block">₱{{ number_format($stats['riderEarnedTotal'], 2) }}</span>
                    </div>
                    <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4">
                        <span class="text-[10px] text-on-surface-variant uppercase font-semibold block">Transfer Courier Earned</span>
                        <span class="text-xl font-bold text-indigo-800 mt-1 block">₱{{ number_format($stats['transferEarnedTotal'], 2) }}</span>
                    </div>
                </div>

                {{-- Payouts Table --}}
                <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant overflow-hidden shadow-xs">
                    <div class="p-5 bg-surface-container-low/60 border-b border-outline-variant/60 flex items-center justify-between">
                        <h2 class="text-sm font-bold text-on-surface">Rider & Transfer Courier Withdrawal Requests</h2>
                    </div>

                    <div class="p-5 overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="border-b border-outline-variant text-[11px] uppercase text-on-surface-variant">
                                    <th class="pb-3">Payout #</th>
                                    <th class="pb-3">Courier / Role</th>
                                    <th class="pb-3">Channel & Account</th>
                                    <th class="pb-3 text-right">Amount</th>
                                    <th class="pb-3">Status</th>
                                    <th class="pb-3">Requested</th>
                                    <th class="pb-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline-variant/40">
                                @forelse($payouts as $p)
                                    <tr>
                                        <td class="py-3 font-mono font-bold text-primary">{{ $p->payout_number }}</td>
                                        <td class="py-3">
                                            <span class="font-bold text-on-surface block">{{ $p->courier?->full_name }}</span>
                                            <span class="text-[10px] uppercase font-bold text-on-surface-variant">{{ $p->role === 'courier' ? 'Transfer Courier' : 'Rider' }}</span>
                                        </td>
                                        <td class="py-3">
                                            <span class="uppercase font-bold text-[10px] px-2 py-0.5 rounded bg-surface-container">{{ str_replace('_', ' ', $p->payout_method) }}</span>
                                            <span class="block text-[11px] mt-0.5">{{ $p->account_name }} ({{ $p->account_number }})</span>
                                            @if($p->external_reference)
                                                <span class="block text-[10px] font-mono text-emerald-700">Ref: {{ $p->external_reference }}</span>
                                            @endif
                                        </td>
                                        <td class="py-3 text-right font-extrabold text-on-surface">₱{{ number_format($p->amount, 2) }}</td>
                                        <td class="py-3">
                                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase
                                                {{ $p->status === 'paid' ? 'bg-emerald-500/10 text-emerald-800' : ($p->status === 'rejected' ? 'bg-rose-500/10 text-rose-800' : 'bg-amber-500/10 text-amber-800') }}">
                                                {{ $p->status }}
                                            </span>
                                        </td>
                                        <td class="py-3 text-[11px] text-on-surface-variant">{{ $p->requested_at?->format('M d, Y h:i A') }}</td>
                                        <td class="py-3 text-right space-x-1">
                                            @if(in_array($p->status, ['requested', 'under_review'], true))
                                                <form method="POST" action="{{ route('logistics.payouts.approve', $p) }}" class="inline">
                                                    @csrf
                                                    <button type="submit" class="px-2.5 py-1 rounded-lg bg-indigo-500/10 text-indigo-800 font-bold text-[11px] cursor-pointer">Approve</button>
                                                </form>
                                            @endif
                                            @if(in_array($p->status, ['requested', 'under_review', 'approved'], true))
                                                <button type="button" @click="activeId = {{ $p->id }}; activeNumber = '{{ $p->payout_number }}'; activeAmount = '{{ number_format($p->amount, 2) }}'; payModal = true"
                                                    class="px-2.5 py-1 rounded-lg bg-primary text-on-primary font-bold text-[11px] cursor-pointer">Mark Paid</button>
                                                <button type="button" @click="activeId = {{ $p->id }}; activeNumber = '{{ $p->payout_number }}'; rejectModal = true"
                                                    class="px-2.5 py-1 rounded-lg bg-rose-500/10 text-rose-700 font-bold text-[11px] cursor-pointer">Reject</button>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="py-8 text-center text-on-surface-variant">No courier payout requests recorded yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                        <div class="mt-4">{{ $payouts->links() }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Disbursement Modal --}}
    <div x-show="payModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
        <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant max-w-md w-full p-6 shadow-xl" @click.away="payModal = false">
            <h3 class="text-base font-bold mb-2">Confirm Manual Payout Disbursement</h3>
            <p class="text-xs text-on-surface-variant mb-4">Record verified payment reference and optional receipt proof for <strong class="font-mono" x-text="activeNumber"></strong> (₱<span x-text="activeAmount"></span>).</p>
            <form method="POST" :action="'/logistics/payouts/' + activeId + '/mark-paid'" enctype="multipart/form-data" class="space-y-3 text-xs">
                @csrf
                <div>
                    <label class="block font-bold mb-1">Transfer Reference # *</label>
                    <input type="text" name="external_reference" required placeholder="e.g. GCASH-99887766 or CASH-VOUCHER-12" class="w-full px-3.5 py-2 rounded-xl border border-outline-variant bg-surface">
                </div>
                <div>
                    <label class="block font-semibold mb-1">Disbursement Proof (Optional Image/PDF)</label>
                    <input type="file" name="proof_file" class="w-full text-xs">
                </div>
                <div>
                    <label class="block font-semibold mb-1">Notes</label>
                    <textarea name="operator_notes" rows="2" class="w-full px-3.5 py-2 rounded-xl border border-outline-variant bg-surface"></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="payModal = false" class="px-4 py-2 rounded-xl bg-surface-container font-semibold">Cancel</button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-primary text-on-primary font-bold">Confirm Paid</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Reject Modal --}}
    <div x-show="rejectModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
        <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant max-w-md w-full p-6 shadow-xl" @click.away="rejectModal = false">
            <h3 class="text-base font-bold text-rose-700 mb-2">Reject Payout Request</h3>
            <form method="POST" :action="'/logistics/payouts/' + activeId + '/reject'" class="space-y-3 text-xs">
                @csrf
                <div>
                    <label class="block font-bold mb-1">Rejection Reason *</label>
                    <textarea name="rejection_reason" required rows="3" placeholder="e.g. Invalid GCash number or account mismatch" class="w-full px-3.5 py-2 rounded-xl border border-outline-variant bg-surface"></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="rejectModal = false" class="px-4 py-2 rounded-xl bg-surface-container font-semibold">Cancel</button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-rose-600 text-white font-bold">Reject Request</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
