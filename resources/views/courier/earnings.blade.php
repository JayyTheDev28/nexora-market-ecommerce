@extends('layouts.app')

@section('title', 'Rider Earnings & COD Remittance — Nexora Logistics')

@section('content')
<div class="w-full bg-surface py-6 min-h-[85vh]" x-data="{ remitModalOpen: false, submitting: false }">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- Breadcrumb & Back --}}
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-2 text-xs">
                <a href="{{ route('courier.dashboard') }}" class="text-primary font-bold hover:underline flex items-center gap-1">
                    <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                    <span>Back to Operations</span>
                </a>
                <span class="text-outline-variant">/</span>
                <span class="text-on-surface-variant">Earnings & COD Ledger</span>
            </div>

            <button type="button" @click="remitModalOpen = true" :disabled="{{ $unremittedCod <= 0 ? 'true' : 'false' }}"
                class="px-4 py-2.5 rounded-2xl text-xs font-bold transition-all shadow-xs flex items-center gap-2 {{ $unremittedCod > 0 ? 'bg-amber-600 hover:bg-amber-700 text-white' : 'bg-surface-container text-on-surface-variant cursor-not-allowed opacity-60' }}">
                <span class="material-symbols-outlined text-[18px]">account_balance_wallet</span>
                <span>Submit EOD Remittance (₱{{ number_format($unremittedCod, 2) }})</span>
            </button>
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

        {{-- Summary Cards (Module R7 Payout Formula) --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-5 shadow-xs">
                <span class="text-[11px] text-on-surface-variant font-medium block">Total Calculated Pay</span>
                <span class="text-2xl font-bold text-primary mt-1 block">₱{{ number_format($calculatedTotalPay, 2) }}</span>
                <span class="text-[10px] text-outline mt-1 block">Formula: (Pickups × ₱30) + (Deliveries × ₱50)</span>
            </div>

            <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-5 shadow-xs">
                <span class="text-[11px] text-on-surface-variant font-medium block">Completed Pickups</span>
                <div class="flex items-baseline gap-2 mt-1">
                    <span class="text-2xl font-bold text-on-surface">{{ $pickupsCount }}</span>
                    <span class="text-xs text-emerald-800 font-bold">+₱{{ number_format($pickupsCount * 30, 2) }}</span>
                </div>
                <span class="text-[10px] text-outline mt-1 block">₱30.00 per seller pickup</span>
            </div>

            <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-5 shadow-xs">
                <span class="text-[11px] text-on-surface-variant font-medium block">Successful Deliveries</span>
                <div class="flex items-baseline gap-2 mt-1">
                    <span class="text-2xl font-bold text-on-surface">{{ $deliveriesCount }}</span>
                    <span class="text-xs text-emerald-800 font-bold">+₱{{ number_format($deliveriesCount * 50, 2) }}</span>
                </div>
                <span class="text-[10px] text-outline mt-1 block">₱50.00 per completed delivery</span>
            </div>

            <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-5 shadow-xs">
                <span class="text-[11px] text-on-surface-variant font-medium block">Unremitted COD Balance</span>
                <span class="text-2xl font-bold {{ $unremittedCod > 0 ? 'text-amber-800' : 'text-on-surface' }} mt-1 block">
                    ₱{{ number_format($unremittedCod, 2) }}
                </span>
                <span class="text-[10px] text-outline mt-1 block">Held cash to be remitted to hub</span>
            </div>
        </div>

        {{-- Filter Strip --}}
        <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4 shadow-2xs mb-8">
            <form method="GET" action="{{ route('courier.earnings') }}" class="flex flex-wrap items-center justify-between gap-4 text-xs">
                <div class="flex items-center gap-3 flex-wrap">
                    <span class="font-bold text-on-surface">Date Range Filter:</span>
                    <div class="flex items-center gap-1.5">
                        <label class="text-on-surface-variant">From:</label>
                        <input type="date" name="start_date" value="{{ $startDate->format('Y-m-d') }}"
                            class="px-3 py-1.5 rounded-xl border border-outline-variant bg-surface-container-lowest text-xs text-on-surface">
                    </div>
                    <div class="flex items-center gap-1.5">
                        <label class="text-on-surface-variant">To:</label>
                        <input type="date" name="end_date" value="{{ $endDate->format('Y-m-d') }}"
                            class="px-3 py-1.5 rounded-xl border border-outline-variant bg-surface-container-lowest text-xs text-on-surface">
                    </div>
                    <button type="submit" class="px-3 py-1.5 rounded-xl bg-primary text-on-primary font-bold hover:bg-primary-hover shadow-2xs">
                        Filter
                    </button>
                </div>

                <a href="{{ route('courier.earnings') }}" class="text-outline hover:text-on-surface text-[11px] underline">
                    Reset to Last 30 Days
                </a>
            </form>
        </div>

        {{-- Two-Column Layout: COD Reconciliation & Remittance Requests --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            
            {{-- COD Ledger --}}
            <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant overflow-hidden shadow-xs">
                <div class="p-5 md:px-6 bg-surface-container-low/60 border-b border-outline-variant/60 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-amber-800 text-[20px]">receipt_long</span>
                        <h2 class="text-sm font-bold text-on-surface">COD Ledger (Recent Cash Transactions)</h2>
                    </div>
                    <span class="text-[11px] text-on-surface-variant">Current: ₱{{ number_format($unremittedCod, 2) }}</span>
                </div>

                <div class="p-5">
                    @if($codLedger->isEmpty())
                        <p class="text-center text-xs text-on-surface-variant py-6">No COD cash transactions recorded yet.</p>
                    @else
                        <div class="divide-y divide-outline-variant/40">
                            @foreach($codLedger as $entry)
                                <div class="py-2.5 flex items-center justify-between text-xs">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $entry->type === 'collected' ? 'bg-amber-500/10 text-amber-900' : 'bg-emerald-500/10 text-emerald-800' }}">
                                                {{ ucfirst($entry->type) }}
                                            </span>
                                            <span class="font-medium text-on-surface">{{ $entry->description }}</span>
                                        </div>
                                        <span class="text-[10px] text-outline mt-0.5 block">{{ $entry->created_at->format('M d, Y h:i A') }}</span>
                                    </div>
                                    <div class="text-right">
                                        <span class="font-bold {{ $entry->type === 'collected' ? 'text-amber-900' : 'text-emerald-800' }} block">
                                            {{ $entry->type === 'collected' ? '+' : '-' }}₱{{ number_format(abs($entry->amount), 2) }}
                                        </span>
                                        <span class="text-[10px] text-on-surface-variant">Bal: ₱{{ number_format($entry->balance_after, 2) }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- Submitted Remittances History --}}
            <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant overflow-hidden shadow-xs">
                <div class="p-5 md:px-6 bg-surface-container-low/60 border-b border-outline-variant/60 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary text-[20px]">account_balance</span>
                        <h2 class="text-sm font-bold text-on-surface">End-of-Day Remittance Requests</h2>
                    </div>
                    <span class="text-[11px] text-on-surface-variant">Logistics Verification</span>
                </div>

                <div class="p-5">
                    @if($remittances->isEmpty())
                        <p class="text-center text-xs text-on-surface-variant py-6">No remittance requests submitted yet.</p>
                    @else
                        <div class="divide-y divide-outline-variant/40">
                            @foreach($remittances as $remit)
                                <div class="py-3 flex items-center justify-between text-xs">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono font-bold text-primary">{{ $remit->remittance_number }}</span>
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold
                                                {{ $remit->status === 'approved' ? 'bg-emerald-500/10 text-emerald-800' :
                                                   ($remit->status === 'discrepancy_flagged' ? 'bg-rose-500/10 text-rose-800' : 'bg-amber-500/10 text-amber-900') }}">
                                                {{ ucfirst(str_replace('_', ' ', $remit->status)) }}
                                            </span>
                                        </div>
                                        <span class="text-[10px] text-on-surface-variant block mt-0.5">
                                            Submitted: {{ $remit->remitted_at->format('M d, Y h:i A') }}
                                            @if($remit->reviewed_at)
                                                · Reviewed: {{ $remit->reviewed_at->format('M d, Y') }}
                                            @endif
                                        </span>
                                        @if($remit->operator_notes)
                                            <p class="text-[11px] text-rose-700 font-medium mt-1">
                                                Logistics Note: {{ $remit->operator_notes }}
                                            </p>
                                        @endif
                                    </div>
                                    <div class="text-right">
                                        <span class="font-bold text-sm text-on-surface block">₱{{ number_format($remit->amount, 2) }}</span>
                                        <span class="text-[10px] text-outline">Expected: ₱{{ number_format($remit->expected_amount, 2) }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Handled Orders History --}}
        <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant overflow-hidden shadow-xs">
            <div class="p-5 md:px-6 bg-surface-container-low/60 border-b border-outline-variant/60 flex items-center justify-between">
                <h2 class="text-sm font-bold text-on-surface">Handled Orders & Earnings Breakdown</h2>
                <span class="text-[11px] text-on-surface-variant">Showing filtered date range</span>
            </div>

            <div class="p-5 md:p-6 overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-outline-variant text-[11px] text-on-surface-variant uppercase font-semibold">
                            <th class="pb-3">Order Number</th>
                            <th class="pb-3">Role</th>
                            <th class="pb-3">Current Status</th>
                            <th class="pb-3">Payment</th>
                            <th class="pb-3 text-right">Order Total</th>
                            <th class="pb-3 text-right">Rider Pay</th>
                            <th class="pb-3 text-right">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/40">
                        @forelse($handledOrders as $order)
                            @php
                                $isPickup = (int) $order->pickup_courier_id === (int) $rider->id;
                                $isDelivery = (int) $order->delivery_courier_id === (int) $rider->id;
                                $earnings = $order->riderEarnings;
                                $earnedTotal = $earnings->sum('amount');
                            @endphp
                            <tr class="hover:bg-surface-container-low/40 transition-colors">
                                <td class="py-3 font-mono font-bold text-primary">{{ $order->order_number }}</td>
                                <td class="py-3">
                                    @if($isPickup && $isDelivery)
                                        <span class="text-primary font-semibold">Pickup + Delivery</span>
                                    @elseif($isPickup)
                                        <span class="text-amber-800 font-semibold">Pickup Courier</span>
                                    @else
                                        <span class="text-emerald-800 font-semibold">Delivery Courier</span>
                                    @endif
                                </td>
                                <td class="py-3">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-surface-container text-on-surface">
                                        {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                                    </span>
                                </td>
                                <td class="py-3 uppercase text-[11px] font-bold text-on-surface-variant">
                                    {{ $order->payment_method }}
                                </td>
                                <td class="py-3 text-right font-medium text-on-surface">₱{{ number_format($order->total, 2) }}</td>
                                <td class="py-3 text-right font-bold text-emerald-800">
                                    ₱{{ number_format($earnedTotal, 2) }}
                                </td>
                                <td class="py-3 text-right text-on-surface-variant text-[11px]">
                                    {{ $order->updated_at->format('M d, Y') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-8 text-center text-on-surface-variant">
                                    No handled orders found within this date range.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="mt-4">
                    {{ $handledOrders->links() }}
                </div>
            </div>
        </div>
    </div>

    {{-- MODAL: Submit EOD Remittance --}}
    <div x-show="remitModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
        <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant max-w-md w-full p-6 shadow-xl" @click.away="remitModalOpen = false">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-amber-800 text-[24px]">account_balance_wallet</span>
                    <h3 class="text-base font-bold text-on-surface">Submit EOD COD Remittance</h3>
                </div>
                <button type="button" @click="remitModalOpen = false" class="text-outline hover:text-on-surface">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <p class="text-xs text-on-surface-variant mb-4">
                Enter the total cash amount collected from completed COD deliveries being submitted to the Logistics Sorting Center.
            </p>

            <form method="POST" action="{{ route('courier.remittance.submit') }}" @submit="submitting = true">
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ uniqid('rem_', true) }}">

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-on-surface mb-1">
                            Remittance Cash Amount (₱) <span class="text-rose-500">*</span>
                        </label>
                        <input type="number" step="0.01" min="1" max="{{ $unremittedCod }}" name="amount" required value="{{ $unremittedCod }}"
                            class="w-full px-4 py-2.5 rounded-2xl text-base font-mono font-bold text-on-surface bg-surface-container-lowest border border-outline-variant">
                        <span class="text-[10px] text-outline mt-1 block">Expected Unremitted Balance: ₱{{ number_format($unremittedCod, 2) }}</span>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-on-surface mb-1">Notes / Discrepancy Explanation (Optional)</label>
                        <textarea name="notes" rows="2" placeholder="e.g. Turn-in cash envelope to operator at Shift End"
                            class="w-full px-4 py-2 rounded-xl text-xs bg-surface-container-lowest border border-outline-variant text-on-surface"></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 mt-6 pt-4 border-t border-outline-variant">
                    <button type="button" @click="remitModalOpen = false" class="px-4 py-2 rounded-xl text-xs font-semibold bg-surface-container text-on-surface">
                        Cancel
                    </button>
                    <button type="submit" :disabled="submitting" class="px-5 py-2.5 rounded-2xl text-xs font-bold bg-amber-600 hover:bg-amber-700 text-white shadow-xs">
                        Submit Remittance
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
