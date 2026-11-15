@extends('layouts.app')

@section('title', 'Finances, Sales & Payouts — Seller Hub')

@section('content')
<div class="w-full min-h-screen bg-surface">
    <div class="flex flex-col lg:flex-row w-full">

        <x-seller-nav active="reports" />

        <div class="flex-1 min-w-0 p-4 sm:p-6 lg:p-8 bg-surface">
            <div class="w-full max-w-7xl mx-auto space-y-6">

                @if (session('status'))
                    <div class="bg-emerald-500/10 border border-emerald-500/30 rounded-2xl p-4 flex items-center gap-2 text-emerald-800 text-xs font-semibold shadow-2xs">
                        <span class="material-symbols-outlined text-[20px] text-emerald-600">check_circle</span>
                        {{ session('status') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="bg-rose-500/10 border border-rose-500/20 rounded-2xl p-4 text-rose-700 text-xs font-medium">
                        <ul class="list-disc pl-5 space-y-1">
                            @foreach($errors->all() as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-2">
                    <div>
                        <h1 class="font-headline-lg text-xl sm:text-2xl font-bold text-on-surface">Finances & Payouts</h1>
                        <p class="text-xs text-on-surface-variant mt-0.5">Merchant sales ledger, platform commission deduction (10%), and earnings withdrawal requests.</p>
                    </div>

                    <button
                        type="button"
                        onclick="document.getElementById('payoutModal').classList.remove('hidden')"
                        :disabled="{{ $availableBalance < 500 ? 'true' : 'false' }}"
                        class="inline-flex items-center gap-1.5 bg-primary text-on-primary text-xs font-bold px-6 py-2.5 rounded-full hover:bg-primary/90 transition-all shadow-md shadow-primary/20 w-fit cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed">
                        <span class="material-symbols-outlined text-[18px]">account_balance_wallet</span>
                        <span>Request Payout</span>
                    </button>
                </div>

                {{-- Financial Balance Cards (Section 7 & 9) --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    {{-- 1. Available for Withdrawal --}}
                    <div class="bg-surface-container-lowest rounded-3xl border-2 border-primary/40 p-5 shadow-xs flex flex-col justify-between">
                        <div>
                            <span class="text-[10px] font-bold text-primary uppercase tracking-wider block">Available for Withdrawal</span>
                            <span class="text-2xl sm:text-3xl font-black text-primary mt-1 block">
                                ₱{{ number_format($availableBalance, 2) }}
                            </span>
                        </div>
                        <p class="text-[11px] text-on-surface-variant mt-2 pt-2 border-t border-outline-variant/50">
                            Verified funds ready to withdraw (Min ₱500)
                        </p>
                    </div>

                    {{-- 2. Waiting for Logistics Remittance --}}
                    <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-5 shadow-xs flex flex-col justify-between">
                        <div>
                            <span class="text-[10px] font-bold text-amber-800 uppercase tracking-wider block flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">local_shipping</span>
                                <span>Awaiting Carrier Remittance</span>
                            </span>
                            <span class="text-2xl sm:text-3xl font-black text-amber-900 mt-1 block">
                                ₱{{ number_format($earningsWaitingLogistics, 2) }}
                            </span>
                        </div>
                        <p class="text-[11px] text-on-surface-variant mt-2 pt-2 border-t border-outline-variant/50">
                            Delivered orders awaiting carrier turnover to Admin
                        </p>
                    </div>

                    {{-- 3. In-Transit / Processing Orders --}}
                    <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-5 shadow-xs flex flex-col justify-between">
                        <div>
                            <span class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider block">In-Transit / Processing</span>
                            <span class="text-2xl sm:text-3xl font-black text-on-surface mt-1 block">
                                ₱{{ number_format($pendingEarnings, 2) }}
                            </span>
                        </div>
                        <p class="text-[11px] text-on-surface-variant mt-2 pt-2 border-t border-outline-variant/50">
                            Orders being packed, sorted, or out for delivery
                        </p>
                    </div>

                    {{-- 4. Settled Earnings --}}
                    <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-5 shadow-xs flex flex-col justify-between">
                        <div>
                            <span class="text-[10px] font-bold text-emerald-800 uppercase tracking-wider block">Verified Settled Earnings</span>
                            <span class="text-2xl sm:text-3xl font-black text-emerald-800 mt-1 block">
                                ₱{{ number_format($settledEarnings, 2) }}
                            </span>
                        </div>
                        <p class="text-[11px] text-on-surface-variant mt-2 pt-2 border-t border-outline-variant/50">
                            Admin confirmed; 10% fee deducted
                        </p>
                    </div>
                </div>

                {{-- Date Range Filter --}}
                <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-5 shadow-xs">
                    <form method="GET" action="{{ route('seller.reports.index') }}" class="flex flex-wrap items-end gap-3">
                        <div>
                            <label class="block text-[11px] font-semibold text-on-surface-variant mb-1">From Date</label>
                            <input type="date" name="from" value="{{ $from->toDateString() }}" class="rounded-xl border border-outline-variant px-3 py-1.5 text-xs text-on-surface bg-surface focus:outline-none focus:border-primary">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-on-surface-variant mb-1">To Date</label>
                            <input type="date" name="to" value="{{ $to->toDateString() }}" class="rounded-xl border border-outline-variant px-3 py-1.5 text-xs text-on-surface bg-surface focus:outline-none focus:border-primary">
                        </div>
                        <button type="submit" class="px-5 py-2 rounded-xl bg-primary text-on-primary text-xs font-bold hover:bg-primary/90 transition-colors cursor-pointer">
                            Update Date Range
                        </button>
                    </form>
                </div>

                {{-- Range Summary Cards --}}
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4">
                        <p class="text-xs text-on-surface-variant font-medium">Orders in Range</p>
                        <p class="text-2xl font-bold text-on-surface mt-1">{{ $orderCount }}</p>
                    </div>
                    <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4">
                        <p class="text-xs text-on-surface-variant font-medium">Gross Merchandise Sales</p>
                        <p class="text-xl font-bold text-on-surface mt-1">₱{{ number_format($grossSales, 2) }}</p>
                    </div>
                    <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4">
                        <p class="text-xs text-on-surface-variant font-medium">Platform Commission (10%)</p>
                        <p class="text-xl font-bold text-rose-600 mt-1">-₱{{ number_format($commission, 2) }}</p>
                    </div>
                    <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4">
                        <p class="text-xs text-on-surface-variant font-medium">Net Merchant Share (90%)</p>
                        <p class="text-xl font-bold text-emerald-600 mt-1">₱{{ number_format($netPayout, 2) }}</p>
                    </div>
                </div>

                {{-- Transaction Stream & Pipeline --}}
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                    {{-- Transaction Stream --}}
                    <div class="lg:col-span-2 bg-surface-container-lowest rounded-3xl border border-outline-variant overflow-hidden shadow-xs">
                        <div class="px-6 py-4 bg-surface-container-low/60 border-b border-outline-variant/60 font-bold text-xs text-on-surface-variant uppercase">
                            Order Transaction Ledger ({{ $from->format('M j') }} - {{ $to->format('M j, Y') }})
                        </div>
                        @if($orders->isEmpty())
                            <div class="p-8 text-center text-xs text-on-surface-variant">
                                No orders recorded in this date period.
                            </div>
                        @else
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-xs">
                                    <thead>
                                        <tr class="border-b border-outline-variant font-semibold text-on-surface-variant bg-surface-container-low/30">
                                            <th class="px-4 py-3">Order #</th>
                                            <th class="px-4 py-3">Date</th>
                                            <th class="px-4 py-3">Customer</th>
                                            <th class="px-4 py-3 text-right">Merchandise</th>
                                            <th class="px-4 py-3 text-right">Commission (10%)</th>
                                            <th class="px-4 py-3 text-right text-emerald-800">Net (90%)</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-outline-variant/40">
                                        @foreach($orders as $o)
                                            @php
                                                $oGross = $o->items->sum(fn ($i) => $i->price * $i->quantity);
                                                $oComm = round($oGross * 0.10, 2);
                                                $oNet = round($oGross - $oComm, 2);
                                            @endphp
                                            <tr class="hover:bg-surface-container-low/40">
                                                <td class="px-4 py-3 font-mono font-bold text-primary">
                                                    <a href="{{ route('seller.orders.show', $o) }}" class="hover:underline">
                                                        {{ $o->order_number }}
                                                    </a>
                                                </td>
                                                <td class="px-4 py-3 text-on-surface-variant">{{ $o->placed_at->format('M j, Y') }}</td>
                                                <td class="px-4 py-3 text-on-surface">{{ $o->buyer?->full_name }}</td>
                                                <td class="px-4 py-3 text-right">₱{{ number_format($oGross, 2) }}</td>
                                                <td class="px-4 py-3 text-right text-rose-600 font-medium">-₱{{ number_format($oComm, 2) }}</td>
                                                <td class="px-4 py-3 text-right font-bold text-emerald-800">₱{{ number_format($oNet, 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                    {{-- Orders by Status Breakdown --}}
                    <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 shadow-xs">
                        <h2 class="text-xs font-bold text-on-surface uppercase tracking-wider border-b border-outline-variant/60 pb-3 mb-4">
                            Pipeline Volume Breakdown
                        </h2>
                        <div class="space-y-3 text-xs">
                            @forelse($byStatus as $st => $cnt)
                                <div class="flex items-center justify-between py-1 border-b border-outline-variant/40 last:border-0">
                                    <span class="text-on-surface font-medium capitalize">{{ str_replace('_', ' ', $st) }}</span>
                                    <span class="px-2.5 py-0.5 rounded-full font-bold bg-surface-container text-on-surface">{{ $cnt }}</span>
                                </div>
                            @empty
                                <p class="text-on-surface-variant text-center py-4">No volume data.</p>
                            @endforelse
                        </div>
                    </div>

                </div>

                {{-- Payout Requests History (Section 7) --}}
                <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant overflow-hidden shadow-xs">
                    <div class="px-6 py-4 bg-surface-container-low/60 border-b border-outline-variant/60 flex items-center justify-between">
                        <div>
                            <h2 class="text-sm font-bold text-on-surface">Withdrawal & Payout History</h2>
                            <p class="text-[11px] text-on-surface-variant">Payout disbursements approved and transferred by Nexora Finance.</p>
                        </div>
                    </div>

                    @if($payoutRequests->isEmpty())
                        <div class="p-8 text-center text-xs text-on-surface-variant">
                            You have not submitted any payout requests yet.
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="border-b border-outline-variant bg-surface-container-low/30 font-bold text-on-surface-variant uppercase text-[11px]">
                                        <th class="px-5 py-3">Payout Ref #</th>
                                        <th class="px-5 py-3">Requested At</th>
                                        <th class="px-5 py-3 text-right">Amount</th>
                                        <th class="px-5 py-3">Channel</th>
                                        <th class="px-5 py-3">Account Details</th>
                                        <th class="px-5 py-3">Transfer Reference</th>
                                        <th class="px-5 py-3">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-outline-variant/40">
                                    @foreach($payoutRequests as $pr)
                                        <tr class="hover:bg-surface-container-low/40">
                                            <td class="px-5 py-3.5 font-mono font-bold text-primary">
                                                {{ $pr->reference_number ?? ('SPR-' . str_pad($pr->id, 6, '0', STR_PAD_LEFT)) }}
                                            </td>
                                            <td class="px-5 py-3.5 text-on-surface-variant">{{ $pr->created_at->format('M j, Y h:i A') }}</td>
                                            <td class="px-5 py-3.5 text-right font-extrabold text-on-surface text-sm">₱{{ number_format($pr->amount, 2) }}</td>
                                            <td class="px-5 py-3.5 font-semibold uppercase text-primary text-[10px]">{{ str_replace('_', ' ', $pr->payout_method) }}</td>
                                            <td class="px-5 py-3.5 text-on-surface-variant">
                                                <p class="font-medium text-on-surface">{{ $pr->account_name }}</p>
                                                <p class="font-mono text-[11px] text-outline">{{ $pr->account_number }}</p>
                                            </td>
                                            <td class="px-5 py-3.5 font-mono text-xs">
                                                @if($pr->transaction_reference)
                                                    <span class="font-bold text-emerald-800">{{ $pr->transaction_reference }}</span>
                                                    @if($pr->proof_path)
                                                        <a href="{{ Storage::url($pr->proof_path) }}" target="_blank" class="text-[10px] text-primary block hover:underline">View Receipt</a>
                                                    @endif
                                                @else
                                                    <span class="text-outline italic">—</span>
                                                @endif
                                            </td>
                                            <td class="px-5 py-3.5">
                                                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold border uppercase tracking-wider {{ $pr->statusBadgeClass() }}">
                                                    {{ $pr->status === 'processed' ? 'PAID' : $pr->status }}
                                                </span>
                                                @if($pr->isRejected() && $pr->rejection_reason)
                                                    <span class="text-[10px] text-rose-600 block mt-0.5 truncate max-w-[130px]" title="{{ $pr->rejection_reason }}">{{ $pr->rejection_reason }}</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="px-5 py-3 border-t border-outline-variant bg-surface-container-low/50">
                            {{ $payoutRequests->links() }}
                        </div>
                    @endif
                </div>

            </div>
        </div>
    </div>
</div>

{{-- Request Payout Modal (Section 7) --}}
<div id="payoutModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 sm:p-8 max-w-md w-full shadow-xl space-y-4">
        <div class="flex items-center justify-between border-b border-outline-variant/60 pb-3">
            <h3 class="font-bold text-base text-on-surface flex items-center gap-2">
                <span class="material-symbols-outlined text-primary text-[22px]">account_balance_wallet</span>
                <span>Request Earnings Payout</span>
            </h3>
            <button type="button" onclick="document.getElementById('payoutModal').classList.add('hidden')" class="p-1 rounded-xl text-on-surface-variant hover:bg-surface-container cursor-pointer">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
        </div>

        <div class="p-4 rounded-2xl bg-surface-container-low border border-outline-variant/60 text-xs space-y-1">
            <div class="flex justify-between items-baseline">
                <span class="text-on-surface-variant">Verified Available Balance:</span>
                <span class="font-extrabold text-primary text-base font-mono">₱{{ number_format($availableBalance, 2) }}</span>
            </div>
            <p class="text-[10px] text-outline leading-tight">Minimum payout is ₱500.00. Funds come exclusively from orders verified by Nexora Finance.</p>
        </div>

        <form method="POST" action="{{ route('seller.payouts.request') }}" class="space-y-4">
            @csrf

            <div>
                <label for="payout_amount" class="block text-xs font-semibold text-on-surface mb-1">
                    Withdrawal Amount (₱) <span class="text-rose-500">*</span>
                </label>
                <input
                    type="number"
                    step="0.01"
                    min="500"
                    max="{{ $availableBalance }}"
                    id="payout_amount"
                    name="amount"
                    required
                    value="{{ min($availableBalance, 500) }}"
                    class="w-full rounded-xl border border-outline-variant bg-surface px-3.5 py-2.5 text-sm text-on-surface font-mono focus:border-primary focus:outline-none">
            </div>

            <div>
                <label for="payout_method" class="block text-xs font-semibold text-on-surface mb-1">
                    Disbursement Channel <span class="text-rose-500">*</span>
                </label>
                <select id="payout_method" name="payout_method" required class="w-full rounded-xl border border-outline-variant bg-surface px-3.5 py-2.5 text-xs text-on-surface focus:border-primary focus:outline-none">
                    <option value="bank_transfer">Direct Bank Transfer (BDO, BPI, UnionBank, Metrobank)</option>
                    <option value="gcash">GCash Account</option>
                    <option value="maya">Maya Account</option>
                </select>
            </div>

            <div>
                <label for="account_name" class="block text-xs font-semibold text-on-surface mb-1">
                    Account Beneficiary Name <span class="text-rose-500">*</span>
                </label>
                <input
                    type="text"
                    id="account_name"
                    name="account_name"
                    required
                    placeholder="e.g. Juan Dela Cruz / Apex Tech Enterprises"
                    class="w-full rounded-xl border border-outline-variant bg-surface px-3.5 py-2.5 text-xs text-on-surface focus:border-primary focus:outline-none">
            </div>

            <div>
                <label for="account_number" class="block text-xs font-semibold text-on-surface mb-1">
                    Account / Mobile Number <span class="text-rose-500">*</span>
                </label>
                <input
                    type="text"
                    id="account_number"
                    name="account_number"
                    required
                    placeholder="e.g. 09171234567 / 1092-8374-6512"
                    class="w-full rounded-xl border border-outline-variant bg-surface px-3.5 py-2.5 text-xs text-on-surface font-mono focus:border-primary focus:outline-none">
            </div>

            <div>
                <label for="payout_notes" class="block text-xs font-semibold text-on-surface mb-1">
                    Notes / Remarks (Optional)
                </label>
                <input
                    type="text"
                    id="payout_notes"
                    name="notes"
                    placeholder="e.g. November 1st cycle withdrawal"
                    class="w-full rounded-xl border border-outline-variant bg-surface px-3.5 py-2.5 text-xs text-on-surface focus:border-primary focus:outline-none">
            </div>

            <div class="flex items-center justify-end gap-3 pt-3 border-t border-outline-variant/60">
                <button
                    type="button"
                    onclick="document.getElementById('payoutModal').classList.add('hidden')"
                    class="px-5 py-2.5 rounded-full border border-outline-variant text-xs font-semibold text-on-surface-variant hover:bg-surface-container transition-colors cursor-pointer">
                    Cancel
                </button>
                <button
                    type="submit"
                    class="px-6 py-2.5 rounded-full bg-primary text-on-primary text-xs font-bold shadow-md shadow-primary/20 hover:bg-primary/90 transition-all cursor-pointer">
                    Submit Payout Request
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
