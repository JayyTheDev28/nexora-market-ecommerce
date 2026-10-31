@extends('layouts.app')

@section('title', 'Logistics Reports & Analytics — Nexora Logistics')

@section('content')
<div class="w-full bg-surface py-8 min-h-[75vh]">
    <div class="max-w-none px-margin-mobile md:px-margin-desktop">

        <x-logistics-nav active="reports" />

        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="font-headline-lg text-2xl md:text-3xl font-bold text-on-surface">Logistics Operational Reports</h1>
                <p class="font-body-md text-sm text-on-surface-variant mt-1">Real-time throughput, geographic sorting volumes, courier delivery metrics, and exception analytics.</p>
            </div>
            <div>
                <button onclick="window.print()" class="inline-flex items-center gap-2 bg-surface-container-high text-on-surface px-4 py-2.5 rounded-full font-label-md text-xs font-semibold hover:bg-surface-container-highest transition-all cursor-pointer">
                    <span class="material-symbols-outlined text-[18px]">print</span>
                    <span>Print Report</span>
                </button>
            </div>
        </div>

        {{-- Date Filter Bar --}}
        <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4 mb-8 shadow-xs">
            <form method="GET" action="{{ route('logistics.reports.index') }}" class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="font-label-md text-xs font-bold text-on-surface flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[18px] text-primary">calendar_today</span>
                        <span>Date Range:</span>
                    </span>

                    <div class="flex items-center gap-2">
                        <input
                            type="date"
                            name="from"
                            value="{{ $fromInput }}"
                            class="px-3 py-1.5 bg-surface-container-high border-none rounded-xl font-body-sm text-xs text-on-surface focus:ring-2 focus:ring-primary">
                        <span class="text-xs text-on-surface-variant font-medium">to</span>
                        <input
                            type="date"
                            name="to"
                            value="{{ $toInput }}"
                            class="px-3 py-1.5 bg-surface-container-high border-none rounded-xl font-body-sm text-xs text-on-surface focus:ring-2 focus:ring-primary">
                    </div>

                    <button
                        type="submit"
                        class="px-4 py-1.5 rounded-xl bg-primary text-on-primary font-label-md text-xs font-semibold hover:bg-primary/90 transition-all cursor-pointer">
                        Apply Filter
                    </button>
                </div>

                <div class="flex items-center gap-1.5 overflow-x-auto">
                    <a href="{{ route('logistics.reports.index', ['from' => now()->subDays(7)->toDateString(), 'to' => now()->toDateString()]) }}" class="px-3 py-1 rounded-lg bg-surface-container-high text-on-surface text-xs font-medium hover:bg-surface-container-highest transition-colors whitespace-nowrap">
                        Last 7 Days
                    </a>
                    <a href="{{ route('logistics.reports.index', ['from' => now()->subDays(30)->toDateString(), 'to' => now()->toDateString()]) }}" class="px-3 py-1 rounded-lg bg-surface-container-high text-on-surface text-xs font-medium hover:bg-surface-container-highest transition-colors whitespace-nowrap">
                        Last 30 Days
                    </a>
                    <a href="{{ route('logistics.reports.index', ['from' => now()->startOfMonth()->toDateString(), 'to' => now()->toDateString()]) }}" class="px-3 py-1 rounded-lg bg-surface-container-high text-on-surface text-xs font-medium hover:bg-surface-container-highest transition-colors whitespace-nowrap">
                        This Month
                    </a>
                </div>
            </form>
        </div>

        {{-- KPI Summary Cards --}}
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4">
                <span class="font-body-sm text-xs text-on-surface-variant block">Parcels Processed</span>
                <div class="font-headline-md text-2xl font-bold text-on-surface mt-1">{{ number_format($metrics['total_processed']) }}</div>
                <span class="text-[11px] text-on-surface-variant mt-0.5 block">Total in period</span>
            </div>

            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4">
                <span class="font-body-sm text-xs text-on-surface-variant block">Received at Hub</span>
                <div class="font-headline-md text-2xl font-bold text-primary mt-1">{{ number_format($metrics['received']) }}</div>
                <span class="text-[11px] text-on-surface-variant mt-0.5 block">Scanned check-ins</span>
            </div>

            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4">
                <span class="font-body-sm text-xs text-on-surface-variant block">Sorted to Zones</span>
                <div class="font-headline-md text-2xl font-bold text-secondary mt-1">{{ number_format($metrics['sorted']) }}</div>
                <span class="text-[11px] text-on-surface-variant mt-0.5 block">Area-routed</span>
            </div>

            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4">
                <span class="font-body-sm text-xs text-on-surface-variant block">Delivered Success</span>
                <div class="font-headline-md text-2xl font-bold text-emerald-600 mt-1">{{ number_format($metrics['delivered']) }}</div>
                <span class="text-[11px] text-emerald-700 mt-0.5 block font-medium">Completed run</span>
            </div>

            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4">
                <span class="font-body-sm text-xs text-on-surface-variant block">Delivery Failures</span>
                <div class="font-headline-md text-2xl font-bold text-rose-600 mt-1">{{ number_format($metrics['failed']) }}</div>
                <span class="text-[11px] text-rose-700 mt-0.5 block font-medium">Exceptions</span>
            </div>

            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4">
                <span class="font-body-sm text-xs text-on-surface-variant block">Returned (RTS)</span>
                <div class="font-headline-md text-2xl font-bold text-on-surface mt-1">{{ number_format($metrics['returned']) }}</div>
                <span class="text-[11px] text-on-surface-variant mt-0.5 block">Returned to seller</span>
            </div>
        </div>

        {{-- 2-Column Breakdown Tables --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            {{-- Delivery Area Volume Breakdown --}}
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant overflow-hidden shadow-xs">
                <div class="p-4 border-b border-outline-variant bg-surface-container-low/40 flex items-center justify-between">
                    <h3 class="font-headline-sm text-sm font-bold text-on-surface flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px] text-primary">map</span>
                        <span>Volume by Delivery Coverage Zone</span>
                    </h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse font-body-sm text-xs">
                        <thead>
                            <tr class="border-b border-outline-variant text-on-surface-variant font-semibold bg-surface-container-low/20">
                                <th class="px-5 py-3">Zone / Delivery Area</th>
                                <th class="px-5 py-3 text-center">Total Packages</th>
                                <th class="px-5 py-3 text-center">Delivered</th>
                                <th class="px-5 py-3 text-center">Failed</th>
                                <th class="px-5 py-3 text-right">Success Rate</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant text-on-surface">
                            @forelse ($areaBreakdown as $area)
                                @php
                                    $rate = $area->total_orders > 0 ? round(($area->delivered_orders / $area->total_orders) * 100) : 0;
                                @endphp
                                <tr>
                                    <td class="px-5 py-3.5 font-bold text-on-surface">{{ $area->name }}</td>
                                    <td class="px-5 py-3.5 text-center font-medium">{{ number_format($area->total_orders) }}</td>
                                    <td class="px-5 py-3.5 text-center text-emerald-700 font-medium">{{ number_format($area->delivered_orders) }}</td>
                                    <td class="px-5 py-3.5 text-center text-rose-700 font-medium">{{ number_format($area->failed_orders) }}</td>
                                    <td class="px-5 py-3.5 text-right font-bold text-on-surface">
                                        {{ $rate }}%
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-5 py-6 text-center text-on-surface-variant italic">No area data recorded.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Courier Fleet Performance --}}
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant overflow-hidden shadow-xs">
                <div class="p-4 border-b border-outline-variant bg-surface-container-low/40 flex items-center justify-between">
                    <h3 class="font-headline-sm text-sm font-bold text-on-surface flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px] text-secondary">two_wheeler</span>
                        <span>Courier Fleet Activity</span>
                    </h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse font-body-sm text-xs">
                        <thead>
                            <tr class="border-b border-outline-variant text-on-surface-variant font-semibold bg-surface-container-low/20">
                                <th class="px-5 py-3">Rider Name</th>
                                <th class="px-5 py-3">Vehicle / Plate</th>
                                <th class="px-5 py-3 text-center">Active Workload</th>
                                <th class="px-5 py-3 text-right">Delivered in Period</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant text-on-surface">
                            @forelse ($riderPerformance as $rider)
                                <tr>
                                    <td class="px-5 py-3.5 font-bold text-on-surface">{{ $rider->full_name }}</td>
                                    <td class="px-5 py-3.5 text-on-surface-variant font-mono text-[11px]">
                                        {{ $rider->courierProfile?->plate_number ?? 'No Plate' }} ({{ $rider->courierProfile?->vehicle_type ?? 'Motorcycle' }})
                                    </td>
                                    <td class="px-5 py-3.5 text-center">
                                        <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-primary/10 text-primary">
                                            {{ $rider->active_count }} active
                                        </span>
                                    </td>
                                    <td class="px-5 py-3.5 text-right font-bold text-emerald-700">
                                        {{ number_format($rider->delivered_count) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-5 py-6 text-center text-on-surface-variant italic">No courier activity in this period.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Delivery Exception Reasons Table --}}
        @if ($failureReasons->count() > 0)
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant overflow-hidden shadow-xs mb-8">
                <div class="p-4 border-b border-outline-variant bg-surface-container-low/40">
                    <h3 class="font-headline-sm text-sm font-bold text-rose-700 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px]">report_problem</span>
                        <span>Delivery Failure Reasons Distribution</span>
                    </h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse font-body-sm text-xs">
                        <thead>
                            <tr class="border-b border-outline-variant text-on-surface-variant font-semibold bg-surface-container-low/20">
                                <th class="px-5 py-3">Recorded Exception Reason</th>
                                <th class="px-5 py-3 text-right">Count</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant text-on-surface">
                            @foreach ($failureReasons as $reason)
                                <tr>
                                    <td class="px-5 py-3 font-medium">{{ $reason->failure_reason ?? 'Unspecified rider issue' }}</td>
                                    <td class="px-5 py-3 text-right font-bold text-rose-700">{{ $reason->count }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

    </div>
</div>
@endsection
