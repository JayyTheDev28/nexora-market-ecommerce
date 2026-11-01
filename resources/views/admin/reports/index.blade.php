@extends('layouts.app')

@section('title', 'Platform Reports & Analytics — Admin Portal')

@section('content')
<div class="w-full min-h-screen bg-surface">
    <div class="flex flex-col lg:flex-row w-full">

            <x-admin-nav active="reports" />

            <div class="flex-1 min-w-0 p-4 sm:p-6 lg:p-8 bg-surface">
            <div class="w-full max-w-7xl mx-auto space-y-6">

        {{-- Page Header --}}
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="font-headline-lg text-2xl font-bold text-on-surface">Platform Analytics & Financial Reports</h1>
                <p class="font-body-md text-sm text-on-surface-variant">
                    Executive overview of marketplace sales performance, revenue commissions, and merchant throughput for {{ $startDate->format('M d, Y') }} — {{ $endDate->format('M d, Y') }}.
                </p>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('admin.reports.export', ['type' => 'sales', 'period' => $period, 'start_date' => request('start_date'), 'end_date' => request('end_date')]) }}"
                   class="inline-flex items-center gap-1.5 px-4 py-2 rounded-2xl bg-surface-container-high text-on-surface hover:bg-surface-container-highest font-label-md text-xs font-semibold transition-all">
                    <span class="material-symbols-outlined text-[16px]">download</span>
                    <span>Sales CSV</span>
                </a>
                <a href="{{ route('admin.reports.export', ['type' => 'commission', 'period' => $period, 'start_date' => request('start_date'), 'end_date' => request('end_date')]) }}"
                   class="inline-flex items-center gap-1.5 px-4 py-2 rounded-2xl bg-primary text-on-primary hover:bg-primary/90 font-label-md text-xs font-semibold shadow-xs transition-all">
                    <span class="material-symbols-outlined text-[16px]">download</span>
                    <span>Commission CSV</span>
                </a>
            </div>
        </div>

        {{-- Time Period Selector Pills --}}
        <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4 mb-6 shadow-xs flex flex-wrap items-center justify-between gap-4">
            <div class="flex flex-wrap items-center gap-1.5">
                <a href="{{ route('admin.reports.index', ['period' => 'today']) }}"
                   class="px-3 py-1.5 rounded-xl font-label-md text-xs font-semibold transition-all {{ $period === 'today' ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface hover:bg-surface-container-high' }}">
                    Today
                </a>
                <a href="{{ route('admin.reports.index', ['period' => '7_days']) }}"
                   class="px-3 py-1.5 rounded-xl font-label-md text-xs font-semibold transition-all {{ $period === '7_days' ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface hover:bg-surface-container-high' }}">
                    Last 7 Days
                </a>
                <a href="{{ route('admin.reports.index', ['period' => '30_days']) }}"
                   class="px-3 py-1.5 rounded-xl font-label-md text-xs font-semibold transition-all {{ $period === '30_days' ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface hover:bg-surface-container-high' }}">
                    Last 30 Days
                </a>
                <a href="{{ route('admin.reports.index', ['period' => 'this_month']) }}"
                   class="px-3 py-1.5 rounded-xl font-label-md text-xs font-semibold transition-all {{ $period === 'this_month' ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface hover:bg-surface-container-high' }}">
                    This Month
                </a>
                <a href="{{ route('admin.reports.index', ['period' => 'this_year']) }}"
                   class="px-3 py-1.5 rounded-xl font-label-md text-xs font-semibold transition-all {{ $period === 'this_year' ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface hover:bg-surface-container-high' }}">
                    This Year
                </a>
            </div>

            {{-- Custom Date Range Form --}}
            <form method="GET" action="{{ route('admin.reports.index') }}" class="flex items-center gap-2">
                <input type="hidden" name="period" value="custom">
                <input type="date" name="start_date" value="{{ request('start_date', $startDate->format('Y-m-d')) }}"
                       class="px-3 py-1.5 bg-surface-container rounded-xl text-xs text-on-surface border border-outline-variant focus:outline-none focus:border-primary">
                <span class="text-xs text-outline">to</span>
                <input type="date" name="end_date" value="{{ request('end_date', $endDate->format('Y-m-d')) }}"
                       class="px-3 py-1.5 bg-surface-container rounded-xl text-xs text-on-surface border border-outline-variant focus:outline-none focus:border-primary">
                <button type="submit" class="px-3 py-1.5 rounded-xl bg-surface-container-high text-on-surface hover:bg-surface-container-highest font-semibold text-xs">
                    Apply
                </button>
            </form>
        </div>

        {{-- Core Financial KPI Tiles --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4 shadow-xs">
                <span class="text-xs text-on-surface-variant font-medium block">Gross Sales (GMV)</span>
                <div class="font-headline-md text-xl md:text-2xl font-bold text-on-surface mt-1">₱{{ number_format($grossSales, 2) }}</div>
                <span class="text-[11px] text-emerald-600 font-medium mt-0.5 block">{{ number_format($totalOrders) }} total orders</span>
            </div>

            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4 shadow-xs">
                <span class="text-xs text-on-surface-variant font-medium block">Average Order Value</span>
                <div class="font-headline-md text-xl md:text-2xl font-bold text-on-surface mt-1">₱{{ number_format($avgOrderValue, 2) }}</div>
                <span class="text-[11px] text-on-surface-variant mt-0.5 block">Per placed transaction</span>
            </div>

            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4 shadow-xs">
                <span class="text-xs text-on-surface-variant font-medium block">Total Commission (10%)</span>
                <div class="font-headline-md text-xl md:text-2xl font-bold text-primary mt-1">₱{{ number_format($totalCommission, 2) }}</div>
                <span class="text-[11px] text-primary font-medium mt-0.5 block">Platform take-rate</span>
            </div>

            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4 shadow-xs">
                <span class="text-xs text-on-surface-variant font-medium block">Settled Commission</span>
                <div class="font-headline-md text-xl md:text-2xl font-bold text-emerald-600 mt-1">₱{{ number_format($settledCommission, 2) }}</div>
                <span class="text-[11px] text-emerald-600 font-medium mt-0.5 block">Disbursed balance</span>
            </div>

            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4 shadow-xs">
                <span class="text-xs text-on-surface-variant font-medium block">Pending Commission</span>
                <div class="font-headline-md text-xl md:text-2xl font-bold text-amber-600 mt-1">₱{{ number_format($pendingCommission, 2) }}</div>
                <span class="text-[11px] text-amber-600 font-medium mt-0.5 block">Awaiting settlement</span>
            </div>

            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4 shadow-xs">
                <span class="text-xs text-on-surface-variant font-medium block">Net Seller Payouts</span>
                <div class="font-headline-md text-xl md:text-2xl font-bold text-on-surface mt-1">₱{{ number_format($sellerPayouts, 2) }}</div>
                <span class="text-[11px] text-on-surface-variant mt-0.5 block">90% merchant share</span>
            </div>
        </div>

        {{-- Top Merchants Performance Table --}}
        <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant overflow-hidden shadow-xs mb-8">
            <div class="px-6 py-4 border-b border-outline-variant bg-surface-container-low flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary text-[20px]">leaderboard</span>
                    <h2 class="font-headline-sm text-base font-bold text-on-surface">Top Merchants by Sales Volume</h2>
                </div>
                <span class="text-xs text-on-surface-variant">Top 10 during selected period</span>
            </div>

            @if($sellerStats->isEmpty())
                <div class="p-8 text-center">
                    <p class="font-body-sm text-xs text-on-surface-variant">No merchant transactions in this date range.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-surface-container-low border-b border-outline-variant text-on-surface-variant font-semibold">
                            <tr>
                                <th class="px-5 py-3.5">Merchant Name</th>
                                <th class="px-5 py-3.5">Registered Email</th>
                                <th class="px-5 py-3.5 text-right">Orders Fulfilled</th>
                                <th class="px-5 py-3.5 text-right">Gross Sales Volume</th>
                                <th class="px-5 py-3.5 text-right">Commission Earned (10%)</th>
                                <th class="px-5 py-3.5 text-right">Net Merchant Payout</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant">
                            @foreach($sellerStats as $stat)
                                <tr class="hover:bg-surface-container-low/50 transition-colors">
                                    <td class="px-5 py-4 font-bold text-on-surface">
                                        {{ $stat->seller->sellerProfile->business_name ?? $stat->seller->full_name }}
                                    </td>
                                    <td class="px-5 py-4 text-on-surface-variant">
                                        {{ $stat->seller->email }}
                                    </td>
                                    <td class="px-5 py-4 text-right font-medium">
                                        {{ number_format($stat->orders_count) }}
                                    </td>
                                    <td class="px-5 py-4 text-right font-bold text-on-surface">
                                        ₱{{ number_format($stat->total_volume, 2) }}
                                    </td>
                                    <td class="px-5 py-4 text-right font-bold text-primary">
                                        ₱{{ number_format($stat->total_commission, 2) }}
                                    </td>
                                    <td class="px-5 py-4 text-right font-bold text-emerald-600">
                                        ₱{{ number_format($stat->total_payout, 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Order Status Breakdown & Catalog Distribution --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- 1. Order Status Breakdown --}}
            <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 shadow-xs">
                <h2 class="font-headline-sm text-sm font-bold text-on-surface mb-4 flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px] text-primary">pie_chart</span>
                    <span>Order Pipeline Status Distribution</span>
                </h2>

                <div class="space-y-2.5 text-xs">
                    @forelse($ordersByStatus as $statusName => $count)
                        @php
                            $percentage = $totalOrders > 0 ? round(($count / $totalOrders) * 100, 1) : 0;
                        @endphp
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <span class="font-semibold text-on-surface capitalize">{{ str_replace('_', ' ', $statusName) }}</span>
                                <span class="text-on-surface-variant font-medium">{{ $count }} ({{ $percentage }}%)</span>
                            </div>
                            <div class="w-full h-2 rounded-full bg-surface-container overflow-hidden">
                                <div class="h-full bg-primary rounded-full" style="width: {{ $percentage }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-outline py-4 text-center">No orders recorded in this timeframe.</p>
                    @endforelse
                </div>
            </div>

            {{-- 2. Catalog & Category Overview --}}
            <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 shadow-xs">
                <h2 class="font-headline-sm text-sm font-bold text-on-surface mb-4 flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px] text-primary">category</span>
                    <span>Catalog Categories Overview</span>
                </h2>

                <div class="divide-y divide-outline-variant text-xs">
                    @foreach($categories as $category)
                        <div class="py-2.5 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-[16px] text-on-surface-variant">{{ $category->icon ?? 'label' }}</span>
                                <span class="font-semibold text-on-surface">{{ $category->name }}</span>
                            </div>
                            <span class="px-2.5 py-0.5 rounded-full bg-surface-container text-on-surface-variant font-semibold">
                                {{ $category->products_count }} product(s)
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>

        </div>

                </div>
        </div>
    </div>
</div>

@endsection
