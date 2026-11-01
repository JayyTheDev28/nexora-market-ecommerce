@extends('layouts.app')

@section('title', 'Finances, Sales & Payouts — Seller Hub')

@section('content')
<div class="w-full min-h-screen bg-surface">
    <div class="flex flex-col lg:flex-row w-full">

            <x-seller-nav active="reports" />

            <div class="flex-1 min-w-0 p-4 sm:p-6 lg:p-8 bg-surface">
            <div class="w-full max-w-7xl mx-auto space-y-6">

        @if (session('status'))
            <div class="mb-6 bg-emerald-500/10 border border-emerald-500/30 rounded-2xl p-4 flex items-center gap-2 text-emerald-700 dark:text-emerald-300 font-label-md">
                <span class="material-symbols-outlined text-[20px]">check_circle</span>
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 bg-error/10 border border-error/30 rounded-2xl p-4 text-error font-body-sm">
                <ul class="list-disc pl-5 space-y-1">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="font-headline-lg text-headline-lg text-on-surface">Finances & Payouts</h1>
                <p class="font-body-md text-on-surface-variant">Merchant revenue breakdown, platform commission audit, and earnings withdrawal requests.</p>
            </div>
            <button
                type="button"
                onclick="document.getElementById('payoutModal').classList.remove('hidden')"
                class="inline-flex items-center gap-1.5 bg-primary text-on-primary font-label-md text-sm px-5 py-2.5 rounded-full hover:bg-primary/90 transition-colors shadow-sm w-fit">
                <span class="material-symbols-outlined text-[18px]">account_balance_wallet</span>
                Request Payout
            </button>
        </div>

        {{-- Financial Balance Cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
            <div class="bg-surface-container-lowest rounded-2xl border-2 border-primary/30 p-6 shadow-sm">
                <p class="text-xs font-bold text-on-surface-variant uppercase tracking-wider">Available for Payout</p>
                <p class="font-display-lg text-3xl font-black text-primary mt-2">
                    ₱{{ number_format($availableBalance, 2) }}
                </p>
                <p class="text-[11px] text-on-surface-variant mt-1.5">From delivered & completed customer orders</p>
            </div>

            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-6 shadow-sm">
                <p class="text-xs font-bold text-on-surface-variant uppercase tracking-wider">Pending Settlement</p>
                <p class="font-display-lg text-3xl font-black text-amber-500 mt-2">
                    ₱{{ number_format($pendingEarnings, 2) }}
                </p>
                <p class="text-[11px] text-on-surface-variant mt-1.5">Orders in preparation, transit, or delivery</p>
            </div>

            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-6 shadow-sm">
                <p class="text-xs font-bold text-on-surface-variant uppercase tracking-wider">All-Time Completed Sales</p>
                <p class="font-display-lg text-3xl font-black text-on-surface mt-2">
                    ₱{{ number_format($lifetimeCompletedGross, 2) }}
                </p>
                <p class="text-[11px] text-on-surface-variant mt-1.5">Gross volume fulfilled on Nexora Market</p>
            </div>
        </div>

        {{-- Date Range Filter --}}
        <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-5 mb-6 shadow-sm">
            <form method="GET" action="{{ route('seller.reports.index') }}" class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="block text-[11px] font-semibold text-on-surface-variant mb-1">From Date</label>
                    <input type="date" name="from" value="{{ $from->toDateString() }}" class="rounded-xl border border-outline-variant px-3 py-1.5 text-xs text-on-surface bg-surface focus:outline-none focus:border-primary">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-on-surface-variant mb-1">To Date</label>
                    <input type="date" name="to" value="{{ $to->toDateString() }}" class="rounded-xl border border-outline-variant px-3 py-1.5 text-xs text-on-surface bg-surface focus:outline-none focus:border-primary">
                </div>
                <button type="submit" class="px-5 py-2 rounded-xl bg-primary text-on-primary text-xs font-bold hover:bg-primary/90 transition-colors">
                    Update Report
                </button>
            </form>
        </div>

        {{-- Range Summary Cards --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4">
                <p class="text-xs text-on-surface-variant font-medium">Orders in Range</p>
                <p class="font-headline-lg text-2xl font-bold text-on-surface mt-1">{{ $orderCount }}</p>
            </div>
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4">
                <p class="text-xs text-on-surface-variant font-medium">Gross Merchandise Sales</p>
                <p class="font-headline-lg text-xl font-bold text-on-surface mt-1">₱{{ number_format($grossSales, 2) }}</p>
            </div>
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4">
                <p class="text-xs text-on-surface-variant font-medium">Platform Fee (10%)</p>
                <p class="font-headline-lg text-xl font-bold text-error mt-1">-₱{{ number_format($commission, 2) }}</p>
            </div>
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4">
                <p class="text-xs text-on-surface-variant font-medium">Net Merchant Earnings</p>
                <p class="font-headline-lg text-xl font-bold text-emerald-600 mt-1">₱{{ number_format($netPayout, 2) }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">

            {{-- Transaction Stream --}}
            <div class="lg:col-span-2 bg-surface-container-lowest rounded-2xl border border-outline-variant overflow-hidden shadow-sm">
                <div class="px-6 py-4 bg-surface-container-low border-b border-outline-variant font-bold text-xs text-on-surface-variant uppercase">
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
                                <tr class="border-b border-outline-variant font-semibold text-on-surface-variant bg-surface-container-low/50">
                                    <th class="px-4 py-2.5">Order #</th>
                                    <th class="px-4 py-2.5">Date</th>
                                    <th class="px-4 py-2.5">Customer</th>
                                    <th class="px-4 py-2.5">Gross</th>
                                    <th class="px-4 py-2.5">Commission (10%)</th>
                                    <th class="px-4 py-2.5">Net</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline-variant">
                                @foreach($orders as $o)
                                    @php
                                        $oGross = $o->items->sum(fn ($i) => $i->price * $i->quantity);
                                        $oComm = $oGross * 0.10;
                                        $oNet = $oGross - $oComm;
                                    @endphp
                                    <tr class="hover:bg-surface-container-low/40">
                                        <td class="px-4 py-2.5 font-bold text-primary">
                                            <a href="{{ route('seller.orders.show', $o) }}" class="hover:underline">
                                                {{ $o->order_number }}
                                            </a>
                                        </td>
                                        <td class="px-4 py-2.5 text-on-surface-variant">{{ $o->placed_at->format('M j, Y') }}</td>
                                        <td class="px-4 py-2.5 text-on-surface">{{ $o->buyer->full_name }}</td>
                                        <td class="px-4 py-2.5">₱{{ number_format($oGross, 2) }}</td>
                                        <td class="px-4 py-2.5 text-error">-₱{{ number_format($oComm, 2) }}</td>
                                        <td class="px-4 py-2.5 font-bold text-emerald-600">₱{{ number_format($oNet, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Orders by Status Breakdown --}}
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-6 shadow-sm">
                <h2 class="font-headline-sm text-sm font-bold text-on-surface border-b border-outline-variant pb-2 mb-4">
                    Pipeline Volume Breakdown
                </h2>
                <div class="space-y-3 text-xs">
                    @forelse($byStatus as $st => $cnt)
                        <div class="flex items-center justify-between py-1 border-b border-outline-variant/50 last:border-0">
                            <span class="text-on-surface font-medium capitalize">{{ str_replace('_', ' ', $st) }}</span>
                            <span class="px-2.5 py-0.5 rounded-full font-bold bg-surface-container text-on-surface">{{ $cnt }}</span>
                        </div>
                    @empty
                        <p class="text-on-surface-variant text-center py-4">No volume data.</p>
                    @endforelse
                </div>
            </div>

        </div>

        {{-- Payout Requests History --}}
        <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant overflow-hidden shadow-sm">
            <div class="px-6 py-4 bg-surface-container-low border-b border-outline-variant flex items-center justify-between">
                <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold">Withdrawal & Payout History</h2>
                <span class="text-xs text-on-surface-variant">Processed by platform financial administration</span>
            </div>
            @if($payoutRequests->isEmpty())
                <div class="p-8 text-center text-xs text-on-surface-variant">
                    You have not submitted any payout requests yet.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-outline-variant bg-surface-container-low/50 font-bold text-on-surface-variant uppercase">
                                <th class="px-5 py-3">Requested At</th>
                                <th class="px-5 py-3">Amount</th>
                                <th class="px-5 py-3">Payout Method</th>
                                <th class="px-5 py-3">Account Details</th>
                                <th class="px-5 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant">
                            @foreach($payoutRequests as $pr)
                                <tr class="hover:bg-surface-container-low/40">
                                    <td class="px-5 py-3 text-on-surface-variant">{{ $pr->created_at->format('M j, Y H:i') }}</td>
                                    <td class="px-5 py-3 font-bold text-on-surface">₱{{ number_format($pr->amount, 2) }}</td>
                                    <td class="px-5 py-3 font-semibold uppercase text-primary">{{ str_replace('_', ' ', $pr->payout_method) }}</td>
                                    <td class="px-5 py-3 text-on-surface-variant">
                                        <p class="font-medium text-on-surface">{{ $pr->account_name }}</p>
                                        <p class="font-mono text-[11px]">{{ $pr->account_number }}</p>
                                    </td>
                                    <td class="px-5 py-3">
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase
                                            {{ $pr->status === 'processed' ? 'bg-emerald-500/10 text-emerald-600' : ($pr->status === 'rejected' ? 'bg-error/10 text-error' : 'bg-amber-500/10 text-amber-600') }}">
                                            {{ $pr->status }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-5 py-3 border-t border-outline-variant bg-surface-container-low">
                    {{ $payoutRequests->links() }}
                </div>
            @endif
        </div>

                </div>
        </div>
    </div>
</div>


{{-- Request Payout Modal --}}
<div id="payoutModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 max-w-md w-full shadow-xl">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-headline-sm text-headline-sm text-on-surface font-bold flex items-center gap-2">
                <span class="material-symbols-outlined text-primary text-[22px]">account_balance_wallet</span>
                Request Earnings Payout
            </h3>
            <button type="button" onclick="document.getElementById('payoutModal').classList.add('hidden')" class="p-1 rounded-full text-on-surface-variant hover:bg-surface-container">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
        </div>

        <div class="p-3.5 rounded-xl bg-surface-container-low border border-outline-variant mb-4 text-xs">
            <span class="text-on-surface-variant">Available Balance:</span>
            <span class="font-bold text-primary text-sm ml-1">₱{{ number_format($availableBalance, 2) }}</span>
            <p class="text-[10px] text-on-surface-variant mt-0.5">Minimum withdrawal amount is ₱500.00.</p>
        </div>

        <form method="POST" action="{{ route('seller.payouts.request') }}" class="space-y-4">
            @csrf

            <div>
                <label for="payout_amount" class="block font-label-md text-xs font-semibold text-on-surface mb-1">
                    Payout Amount (₱) <span class="text-error">*</span>
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
                    class="w-full rounded-xl border border-outline-variant bg-surface px-3 py-2 text-sm text-on-surface focus:border-primary focus:outline-none">
            </div>

            <div>
                <label for="payout_method" class="block font-label-md text-xs font-semibold text-on-surface mb-1">
                    Disbursement Channel <span class="text-error">*</span>
                </label>
                <select id="payout_method" name="payout_method" required class="w-full rounded-xl border border-outline-variant bg-surface px-3 py-2 text-xs text-on-surface focus:border-primary focus:outline-none">
                    <option value="bank_transfer">Bank Transfer (BDO / BPI / Metrobank / UnionBank)</option>
                    <option value="gcash">GCash E-Wallet</option>
                    <option value="maya">Maya E-Wallet</option>
                </select>
            </div>

            <div>
                <label for="account_name" class="block font-label-md text-xs font-semibold text-on-surface mb-1">
                    Account Name <span class="text-error">*</span>
                </label>
                <input type="text" id="account_name" name="account_name" required placeholder="Full registered name" class="w-full rounded-xl border border-outline-variant bg-surface px-3 py-2 text-xs text-on-surface focus:border-primary focus:outline-none">
            </div>

            <div>
                <label for="account_number" class="block font-label-md text-xs font-semibold text-on-surface mb-1">
                    Account / Mobile Number <span class="text-error">*</span>
                </label>
                <input type="text" id="account_number" name="account_number" required placeholder="Bank account # or mobile 09xxxxxxxxx" class="w-full rounded-xl border border-outline-variant bg-surface px-3 py-2 text-xs text-on-surface focus:border-primary focus:outline-none font-mono">
            </div>

            <div class="pt-3 border-t border-outline-variant flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('payoutModal').classList.add('hidden')" class="px-4 py-2 rounded-full border border-outline-variant text-xs font-semibold text-on-surface hover:bg-surface-container">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2 rounded-full bg-primary text-on-primary text-xs font-bold hover:bg-primary/90" {{ $availableBalance < 500 ? 'disabled' : '' }}>
                    Submit Request
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
