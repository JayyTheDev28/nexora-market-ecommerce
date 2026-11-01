@extends('layouts.app')

@section('title', 'Platform Commissions (10%) — Admin Portal')

@section('content')
<div class="w-full min-h-screen bg-surface" x-data="{ batchModalOpen: false }">
    <div class="flex flex-col lg:flex-row w-full">

            <x-admin-nav active="commissions" />

            <div class="flex-1 min-w-0 p-4 sm:p-6 lg:p-8 bg-surface">
            <div class="w-full max-w-7xl mx-auto space-y-6">

        {{-- Flash messages --}}
        @if (session('status'))
            <div class="mb-6 bg-primary/10 border border-primary/30 rounded-2xl p-4 flex items-center justify-between">
                <div class="flex items-center gap-2 text-primary font-body-sm text-sm">
                    <span class="material-symbols-outlined text-[20px]">check_circle</span>
                    <span>{{ session('status') }}</span>
                </div>
            </div>
        @endif

        {{-- Page Header --}}
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="font-headline-lg text-2xl font-bold text-on-surface">Platform Commission Management (10%)</h1>
                <p class="font-body-md text-sm text-on-surface-variant">Track marketplace commissions, review order earnings, and settle merchant disbursement balances.</p>
            </div>

            @if($stats['pendingCount'] > 0)
                <button type="button" @click="batchModalOpen = true"
                        class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-2xl bg-emerald-600 text-white font-label-md text-xs font-semibold shadow-xs hover:bg-emerald-700 transition-all">
                    <span class="material-symbols-outlined text-[18px]">price_check</span>
                    <span>Batch Settle Commissions</span>
                </button>
            @endif
        </div>

        {{-- Revenue & Commission Metric Tiles --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4 shadow-xs">
                <span class="text-xs text-on-surface-variant font-medium block">Total Commission Generated</span>
                <div class="font-headline-md text-2xl font-bold text-primary mt-1">₱{{ number_format($stats['totalCommission'], 2) }}</div>
                <span class="text-[11px] text-on-surface-variant mt-0.5 block">10% of ₱{{ number_format($stats['totalVolume'], 2) }} GMV</span>
            </div>

            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4 shadow-xs">
                <span class="text-xs text-on-surface-variant font-medium block">Settled Commission</span>
                <div class="font-headline-md text-2xl font-bold text-emerald-600 mt-1">₱{{ number_format($stats['settledCommission'], 2) }}</div>
                <span class="text-[11px] text-emerald-600 font-medium mt-0.5 block">{{ $stats['settledCount'] }} settled transactions</span>
            </div>

            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4 shadow-xs">
                <span class="text-xs text-on-surface-variant font-medium block">Pending Settlement</span>
                <div class="font-headline-md text-2xl font-bold text-amber-600 mt-1">₱{{ number_format($stats['pendingCommission'], 2) }}</div>
                <span class="text-[11px] text-amber-600 font-medium mt-0.5 block">{{ $stats['pendingCount'] }} pending payouts</span>
            </div>

            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4 shadow-xs">
                <span class="text-xs text-on-surface-variant font-medium block">Total Seller Payouts</span>
                <div class="font-headline-md text-2xl font-bold text-on-surface mt-1">₱{{ number_format($stats['totalSellerPayout'], 2) }}</div>
                <span class="text-[11px] text-on-surface-variant mt-0.5 block">90% net merchant earnings</span>
            </div>
        </div>

        {{-- Filters & Search Row --}}
        <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4 mb-6 shadow-xs">
            <form method="GET" action="{{ route('admin.commissions.index') }}" class="flex flex-col md:flex-row items-center gap-3">
                <div class="relative flex-1 w-full">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-[18px]">search</span>
                    <input type="text" name="q" value="{{ $search }}"
                           placeholder="Search by order number or seller name..."
                           class="w-full pl-9 pr-4 py-2 bg-surface-container rounded-xl text-xs text-on-surface placeholder:text-outline border border-outline-variant focus:outline-none focus:border-primary">
                </div>

                <div class="w-full md:w-44">
                    <select name="status" class="w-full px-3 py-2 bg-surface-container rounded-xl text-xs text-on-surface border border-outline-variant focus:outline-none focus:border-primary">
                        <option value="all">All Settlement Statuses</option>
                        <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="settled" {{ $status === 'settled' ? 'selected' : '' }}>Settled</option>
                    </select>
                </div>

                <div class="w-full md:w-48">
                    <select name="seller_id" class="w-full px-3 py-2 bg-surface-container rounded-xl text-xs text-on-surface border border-outline-variant focus:outline-none focus:border-primary">
                        <option value="">All Merchants</option>
                        @foreach($sellers as $seller)
                            <option value="{{ $seller->id }}" {{ (string)$sellerId === (string)$seller->id ? 'selected' : '' }}>
                                {{ $seller->sellerProfile->business_name ?? $seller->full_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center gap-2 w-full md:w-auto">
                    <input type="date" name="start_date" value="{{ $startDate }}"
                           class="px-3 py-2 bg-surface-container rounded-xl text-xs text-on-surface border border-outline-variant focus:outline-none focus:border-primary">
                    <span class="text-xs text-outline">to</span>
                    <input type="date" name="end_date" value="{{ $endDate }}"
                           class="px-3 py-2 bg-surface-container rounded-xl text-xs text-on-surface border border-outline-variant focus:outline-none focus:border-primary">
                </div>

                <div class="flex items-center gap-2 w-full md:w-auto">
                    <button type="submit" class="w-full md:w-auto px-4 py-2 rounded-xl bg-primary text-on-primary font-label-md text-xs font-semibold shadow-xs hover:bg-primary/90 transition-all">
                        Filter
                    </button>
                    @if($search || $status !== 'all' || $sellerId || $startDate || $endDate)
                        <a href="{{ route('admin.commissions.index') }}" class="w-full md:w-auto px-3 py-2 rounded-xl border border-outline-variant text-on-surface-variant text-xs font-semibold hover:bg-surface-container transition-all text-center">
                            Reset
                        </a>
                    @endif
                </div>
            </form>
        </div>

        {{-- Commissions Table --}}
        <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant overflow-hidden shadow-xs">
            @if($commissions->isEmpty())
                <div class="p-12 text-center flex flex-col items-center justify-center">
                    <span class="material-symbols-outlined text-[48px] text-outline mb-3">account_balance_wallet</span>
                    <h3 class="font-headline-sm text-base font-bold text-on-surface">No commission records found</h3>
                    <p class="font-body-sm text-xs text-on-surface-variant mt-1">There are no commission entries matching your query.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-surface-container-low border-b border-outline-variant text-on-surface-variant font-semibold">
                            <tr>
                                <th class="px-5 py-3.5">Order Info</th>
                                <th class="px-5 py-3.5">Merchant / Store</th>
                                <th class="px-5 py-3.5 text-right">Order Subtotal</th>
                                <th class="px-5 py-3.5 text-right">Take Rate</th>
                                <th class="px-5 py-3.5 text-right">Commission (10%)</th>
                                <th class="px-5 py-3.5 text-right">Seller Payout (90%)</th>
                                <th class="px-5 py-3.5">Settlement Status</th>
                                <th class="px-5 py-3.5 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant">
                            @foreach($commissions as $comm)
                                <tr class="hover:bg-surface-container-low/50 transition-colors">
                                    <td class="px-5 py-4">
                                        <span class="font-bold text-on-surface block">#{{ $comm->order->order_number ?? 'Order #' . $comm->order_id }}</span>
                                        <span class="text-[10px] text-on-surface-variant">{{ $comm->created_at->format('M d, Y h:i A') }}</span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <a href="{{ route('admin.users.show', $comm->seller) }}" class="font-semibold text-on-surface hover:text-primary transition-colors block">
                                            {{ $comm->seller->sellerProfile->business_name ?? $comm->seller->full_name }}
                                        </a>
                                        <span class="text-[10px] text-on-surface-variant">{{ $comm->seller->email }}</span>
                                    </td>
                                    <td class="px-5 py-4 text-right font-medium text-on-surface">
                                        ₱{{ number_format($comm->order_total, 2) }}
                                    </td>
                                    <td class="px-5 py-4 text-right font-medium text-on-surface-variant">
                                        {{ number_format($comm->rate * 100, 1) }}%
                                    </td>
                                    <td class="px-5 py-4 text-right font-bold text-primary">
                                        ₱{{ number_format($comm->commission_amount, 2) }}
                                    </td>
                                    <td class="px-5 py-4 text-right font-bold text-emerald-600">
                                        ₱{{ number_format($comm->seller_payout, 2) }}
                                    </td>
                                    <td class="px-5 py-4">
                                        @if($comm->status === 'settled')
                                            <div>
                                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-700">
                                                    <span class="material-symbols-outlined text-[13px]">check_circle</span> Settled
                                                </span>
                                                @if($comm->settled_at)
                                                    <span class="text-[10px] text-outline block mt-0.5">{{ $comm->settled_at->format('M d, Y') }}</span>
                                                @endif
                                            </div>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-700">
                                                <span class="material-symbols-outlined text-[13px]">hourglass_empty</span> Pending
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 text-right">
                                        @if($comm->status !== 'settled')
                                            <form method="POST" action="{{ route('admin.commissions.settle', $comm) }}"
                                                  onsubmit="return confirm('Mark commission #{{ $comm->id }} of ₱{{ number_format($comm->commission_amount, 2) }} as settled?');">
                                                @csrf
                                                <button type="submit" class="px-3 py-1.5 rounded-full bg-primary text-on-primary font-label-md text-xs font-semibold hover:bg-primary/90 transition-all">
                                                    Settle
                                                </button>
                                            </form>
                                        @else
                                            <span class="text-[11px] text-outline">Settled</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                @if($commissions->hasPages())
                    <div class="px-6 py-4 border-t border-outline-variant bg-surface-container-low">
                        {{ $commissions->links() }}
                    </div>
                @endif
            @endif
                    </div>
        </div>
    </div>
</div>


    {{-- Batch Settle Modal --}}
    <div x-show="batchModalOpen" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs"
         @keydown.escape.window="batchModalOpen = false">
        <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant max-w-md w-full p-6 shadow-xl"
             @click.outside="batchModalOpen = false">
            <h3 class="font-headline-sm text-lg font-bold text-on-surface">Batch Settle Commissions</h3>
            <p class="font-body-sm text-xs text-on-surface-variant mt-1">
                Mark pending commission records as settled and record payouts to merchants.
            </p>

            <form action="{{ route('admin.commissions.batch-settle') }}" method="POST" class="mt-4 space-y-4">
                @csrf

                <div>
                    <label class="block text-xs font-semibold text-on-surface mb-1">Target Merchant</label>
                    <select name="seller_id" class="w-full px-3 py-2 bg-surface-container rounded-xl text-xs text-on-surface border border-outline-variant focus:outline-none focus:border-primary">
                        <option value="">All Merchants with Pending Commissions ({{ $stats['pendingCount'] }})</option>
                        @foreach($sellers as $seller)
                            <option value="{{ $seller->id }}">
                                {{ $seller->sellerProfile->business_name ?? $seller->full_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="p-3 rounded-2xl bg-primary/5 border border-primary/20 text-xs text-primary font-medium">
                    This operation will update eligible commission records to "Settled" status with a timestamp of right now and record an audit log entry.
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" @click="batchModalOpen = false"
                            class="px-4 py-2 rounded-xl border border-outline-variant text-xs font-semibold text-on-surface hover:bg-surface-container">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 rounded-xl bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-700">
                        Confirm Batch Settlement
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
