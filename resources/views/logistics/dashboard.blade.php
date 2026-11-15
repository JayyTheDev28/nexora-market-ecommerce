@extends('layouts.app')

@section('title', 'Sorting Center Operations Dashboard — Nexora Logistics')

@section('content')
<div class="w-full min-h-screen bg-surface">
    <div class="flex flex-col lg:flex-row w-full">

        <x-logistics-nav active="dashboard" />

        <div class="flex-1 min-w-0 p-4 sm:p-6 lg:p-8 bg-surface">
            <div class="w-full max-w-7xl mx-auto space-y-6">

                <x-platform-announcements />

                {{-- Facility Header --}}
                <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 shadow-xs">
                    <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-6">
                        <div class="space-y-1.5">
                            <div class="flex items-center gap-2 text-primary font-bold text-xs uppercase tracking-wider">
                                <span class="material-symbols-outlined text-[18px]">warehouse</span>
                                <span>Central Logistics Hub</span>
                                <span class="px-2 py-0.5 rounded-full bg-primary/10 text-primary text-[10px] font-semibold lowercase">
                                    active
                                </span>
                            </div>
                            <h1 class="font-headline-lg text-2xl md:text-3xl font-bold text-on-surface">
                                {{ auth()->user()->sortingCenterProfile?->business_name ?? 'Nexora Logistics Sorting Hub' }}
                            </h1>
                            <div class="flex flex-wrap items-center gap-4 pt-1 font-body-sm text-xs text-on-surface-variant">
                                <div class="flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-[16px] text-primary">location_on</span>
                                    <span>{{ auth()->user()->address?->full_address ?? 'Laguna, Philippines' }}</span>
                                </div>
                                <span class="text-outline-variant">•</span>
                                <div>Manager: <strong class="text-on-surface">{{ auth()->user()->full_name }}</strong></div>
                                @if ($stats['pendingRiders'] > 0)
                                    <span class="text-outline-variant">•</span>
                                    <a href="{{ route('logistics.riders.index', ['tab' => 'pending']) }}" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-amber-500/10 text-amber-800 font-semibold hover:bg-amber-500/20 transition-colors">
                                        <span class="material-symbols-outlined text-[14px]">notification_important</span>
                                        <span>{{ $stats['pendingRiders'] }} Rider Application{{ $stats['pendingRiders'] > 1 ? 's' : '' }} Pending</span>
                                    </a>
                                @endif
                            </div>
                        </div>

                        {{-- Action Buttons --}}
                        <div class="flex flex-wrap items-center gap-2.5 w-full lg:w-auto shrink-0">
                            <a href="{{ route('logistics.incoming.index') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl bg-primary text-on-primary font-label-md text-xs font-semibold shadow-xs hover:bg-primary/90 transition-all cursor-pointer">
                                <span class="material-symbols-outlined text-[20px]">qr_code_scanner</span>
                                <span>Scan Inbound Parcel</span>
                            </a>

                            <a href="{{ route('logistics.sorting.index') }}" class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-2xl bg-surface-container-high text-on-surface font-label-md text-xs font-semibold hover:bg-surface-container-highest border border-outline-variant/60 transition-all cursor-pointer">
                                <span class="material-symbols-outlined text-[18px]">hub</span>
                                <span>Sorting Center</span>
                            </a>

                            <a href="{{ route('logistics.riders.index') }}" class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-2xl bg-surface-container-high text-on-surface font-label-md text-xs font-semibold hover:bg-surface-container-highest border border-outline-variant/60 transition-all cursor-pointer">
                                <span class="material-symbols-outlined text-[18px]">two_wheeler</span>
                                <span>Fleet</span>
                            </a>
                        </div>
                    </div>
                </div>

                {{-- Operational Pipeline Overview (4 Clean Stage Cards) --}}
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="font-headline-sm text-xs font-bold uppercase tracking-wider text-on-surface-variant flex items-center gap-2">
                            <span class="material-symbols-outlined text-[18px] text-primary">route</span>
                            <span>Operational Pipeline Overview</span>
                        </h2>
                        <span class="text-[11px] text-on-surface-variant font-medium">Real-time status across fulfillment stages</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        {{-- 1. Inbound & Pickups --}}
                        <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4 shadow-xs hover:border-primary/60 transition-all flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs font-bold text-on-surface-variant uppercase tracking-wider">1. Inbound Pipeline</span>
                                    <div class="w-8 h-8 rounded-xl bg-blue-500/10 text-blue-700 flex items-center justify-center">
                                        <span class="material-symbols-outlined text-[18px]">move_to_inbox</span>
                                    </div>
                                </div>
                                <div class="font-headline-lg text-2xl font-bold text-on-surface">
                                    {{ number_format($stats['pendingPickups'] + $stats['scheduledPickups'] + $stats['awaitingReceipt']) }}
                                </div>
                                <div class="mt-3 space-y-1.5 font-body-sm text-[11px]">
                                    <div class="flex items-center justify-between text-on-surface-variant">
                                        <span>Pending Pickup:</span>
                                        <strong class="text-on-surface">{{ number_format($stats['pendingPickups']) }}</strong>
                                    </div>
                                    <div class="flex items-center justify-between text-on-surface-variant">
                                        <span>Rider Dispatched:</span>
                                        <strong class="text-on-surface">{{ number_format($stats['scheduledPickups']) }}</strong>
                                    </div>
                                    <div class="flex items-center justify-between text-on-surface-variant">
                                        <span>En Route to Hub:</span>
                                        <strong class="text-blue-700 font-semibold">{{ number_format($stats['awaitingReceipt']) }}</strong>
                                    </div>
                                </div>
                            </div>
                            <div class="pt-3 mt-3 border-t border-outline-variant/60 flex items-center justify-between">
                                <a href="{{ route('logistics.incoming.index') }}" class="text-xs font-semibold text-primary hover:underline flex items-center gap-1">
                                    <span>Receive Inbound</span>
                                    <span class="material-symbols-outlined text-[14px]">arrow_forward</span>
                                </a>
                                <a href="{{ route('logistics.pickups.index') }}" class="text-[11px] text-on-surface-variant hover:text-primary transition-colors">
                                    Pickups
                                </a>
                            </div>
                        </div>

                        {{-- 2. Hub Processing --}}
                        <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4 shadow-xs hover:border-primary/60 transition-all flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs font-bold text-on-surface-variant uppercase tracking-wider">2. Hub Processing</span>
                                    <div class="w-8 h-8 rounded-xl bg-indigo-500/10 text-indigo-700 flex items-center justify-center">
                                        <span class="material-symbols-outlined text-[18px]">hub</span>
                                    </div>
                                </div>
                                <div class="font-headline-lg text-2xl font-bold text-on-surface">
                                    {{ number_format($stats['receivedUnsorted'] + $stats['sortedUnassigned']) }}
                                </div>
                                <div class="mt-3 space-y-1.5 font-body-sm text-[11px]">
                                    <div class="flex items-center justify-between text-on-surface-variant">
                                        <span>Awaiting Sorting:</span>
                                        <strong class="{{ $stats['receivedUnsorted'] > 0 ? 'text-amber-700 font-semibold' : 'text-on-surface' }}">
                                            {{ number_format($stats['receivedUnsorted']) }}
                                        </strong>
                                    </div>
                                    <div class="flex items-center justify-between text-on-surface-variant">
                                        <span>Sorted & Ready:</span>
                                        <strong class="text-on-surface">{{ number_format($stats['sortedUnassigned']) }}</strong>
                                    </div>
                                    <div class="flex items-center justify-between text-on-surface-variant">
                                        <span>Hub Status:</span>
                                        <span class="text-emerald-700 font-semibold">Operational</span>
                                    </div>
                                </div>
                            </div>
                            <div class="pt-3 mt-3 border-t border-outline-variant/60 flex items-center justify-between">
                                <a href="{{ route('logistics.sorting.index') }}" class="text-xs font-semibold text-primary hover:underline flex items-center gap-1">
                                    <span>Sort Parcels</span>
                                    <span class="material-symbols-outlined text-[14px]">arrow_forward</span>
                                </a>
                                <a href="{{ route('logistics.assignments.index') }}" class="text-[11px] text-on-surface-variant hover:text-primary transition-colors">
                                    Assign
                                </a>
                            </div>
                        </div>

                        {{-- 3. Out for Delivery --}}
                        <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4 shadow-xs hover:border-primary/60 transition-all flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs font-bold text-on-surface-variant uppercase tracking-wider">3. Last-Mile Delivery</span>
                                    <div class="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-700 flex items-center justify-center">
                                        <span class="material-symbols-outlined text-[18px]">local_shipping</span>
                                    </div>
                                </div>
                                <div class="font-headline-lg text-2xl font-bold text-on-surface">
                                    {{ number_format($stats['assignedToRider'] + $stats['outForDelivery']) }}
                                </div>
                                <div class="mt-3 space-y-1.5 font-body-sm text-[11px]">
                                    <div class="flex items-center justify-between text-on-surface-variant">
                                        <span>Courier Assigned:</span>
                                        <strong class="text-on-surface">{{ number_format($stats['assignedToRider']) }}</strong>
                                    </div>
                                    <div class="flex items-center justify-between text-on-surface-variant">
                                        <span>On Active Run:</span>
                                        <strong class="text-emerald-700 font-semibold">{{ number_format($stats['outForDelivery']) }}</strong>
                                    </div>
                                    <div class="flex items-center justify-between text-on-surface-variant">
                                        <span>Transit Flow:</span>
                                        <span class="text-on-surface font-medium">In Flight</span>
                                    </div>
                                </div>
                            </div>
                            <div class="pt-3 mt-3 border-t border-outline-variant/60 flex items-center justify-between">
                                <a href="{{ route('logistics.deliveries.index') }}" class="text-xs font-semibold text-primary hover:underline flex items-center gap-1">
                                    <span>Track Deliveries</span>
                                    <span class="material-symbols-outlined text-[14px]">arrow_forward</span>
                                </a>
                                <a href="{{ route('logistics.assignments.index') }}" class="text-[11px] text-on-surface-variant hover:text-primary transition-colors">
                                    Dispatch
                                </a>
                            </div>
                        </div>

                        {{-- 4. Attention & Fleet --}}
                        <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4 shadow-xs hover:border-primary/60 transition-all flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs font-bold text-on-surface-variant uppercase tracking-wider">4. Fleet & Exceptions</span>
                                    <div class="w-8 h-8 rounded-xl {{ ($stats['exceptionsCount'] + $stats['pendingRiders']) > 0 ? 'bg-amber-500/10 text-amber-700' : 'bg-surface-container text-on-surface-variant' }} flex items-center justify-center">
                                        <span class="material-symbols-outlined text-[18px]">rule</span>
                                    </div>
                                </div>
                                <div class="font-headline-lg text-2xl font-bold {{ $stats['exceptionsCount'] > 0 ? 'text-rose-600' : 'text-on-surface' }}">
                                    {{ number_format($stats['exceptionsCount'] + $stats['pendingRiders']) }}
                                </div>
                                <div class="mt-3 space-y-1.5 font-body-sm text-[11px]">
                                    <div class="flex items-center justify-between text-on-surface-variant">
                                        <span>Delivery Exceptions:</span>
                                        <strong class="{{ $stats['exceptionsCount'] > 0 ? 'text-rose-600 font-semibold' : 'text-on-surface' }}">
                                            {{ number_format($stats['exceptionsCount']) }}
                                        </strong>
                                    </div>
                                    <div class="flex items-center justify-between text-on-surface-variant">
                                        <span>Pending Riders:</span>
                                        <strong class="{{ $stats['pendingRiders'] > 0 ? 'text-amber-700 font-semibold' : 'text-on-surface' }}">
                                            {{ number_format($stats['pendingRiders']) }}
                                        </strong>
                                    </div>
                                    <div class="flex items-center justify-between text-on-surface-variant">
                                        <span>Action Required:</span>
                                        <span class="{{ ($stats['exceptionsCount'] + $stats['pendingRiders']) > 0 ? 'text-amber-700 font-semibold' : 'text-emerald-700 font-semibold' }}">
                                            {{ ($stats['exceptionsCount'] + $stats['pendingRiders']) > 0 ? 'Review Items' : 'All Clear' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="pt-3 mt-3 border-t border-outline-variant/60 flex items-center justify-between">
                                <a href="{{ route('logistics.deliveries.index', ['tab' => 'exceptions']) }}" class="text-xs font-semibold text-primary hover:underline flex items-center gap-1">
                                    <span>Exceptions</span>
                                    <span class="material-symbols-outlined text-[14px]">arrow_forward</span>
                                </a>
                                <a href="{{ route('logistics.riders.index') }}" class="text-[11px] text-on-surface-variant hover:text-primary transition-colors">
                                    Riders
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Operations Workbench (Single Interactive Tabbed Workspace) --}}
                @php
                    $defaultTab = 'incoming';
                    if ($incomingParcels->isEmpty() && $pendingPickups->isNotEmpty()) {
                        $defaultTab = 'pickups';
                    } elseif ($incomingParcels->isEmpty() && $unsortedParcels->isNotEmpty()) {
                        $defaultTab = 'sorting';
                    } elseif ($incomingParcels->isEmpty() && $unassignedDeliveries->isNotEmpty()) {
                        $defaultTab = 'dispatch';
                    }
                @endphp

                <div
                    x-data="{ activeTab: '{{ $defaultTab }}' }"
                    class="bg-surface-container-lowest rounded-3xl border border-outline-variant overflow-hidden shadow-xs">

                    {{-- Workbench Header & Navigation Tabs --}}
                    <div class="p-5 border-b border-outline-variant bg-surface-container-low/30">
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                            <div>
                                <h3 class="font-headline-sm text-base font-bold text-on-surface flex items-center gap-2">
                                    <span class="material-symbols-outlined text-primary text-[20px]">table_rows</span>
                                    <span>Operations Workbench</span>
                                </h3>
                                <p class="text-xs text-on-surface-variant mt-0.5">
                                    Focused operational queues requiring warehouse actions
                                </p>
                            </div>

                            {{-- Tab Selector Pills --}}
                            <div class="flex flex-wrap items-center gap-1.5 bg-surface-container p-1 rounded-2xl border border-outline-variant/60">
                                {{-- Tab 1: Incoming En Route --}}
                                <button
                                    type="button"
                                    @click="activeTab = 'incoming'"
                                    class="flex items-center gap-2 px-3 py-1.5 rounded-xl text-xs font-semibold transition-all cursor-pointer"
                                    :class="activeTab === 'incoming' ? 'bg-surface-container-lowest text-primary shadow-xs' : 'text-on-surface-variant hover:text-on-surface'">
                                    <span class="material-symbols-outlined text-[16px]">local_shipping</span>
                                    <span>Incoming</span>
                                    <span class="px-1.5 py-0.2 rounded-full text-[10px] font-bold"
                                        :class="activeTab === 'incoming' ? 'bg-primary/10 text-primary' : 'bg-surface-container-highest text-on-surface-variant'">
                                        {{ $stats['awaitingReceipt'] }}
                                    </span>
                                </button>

                                {{-- Tab 2: Seller Pickups --}}
                                <button
                                    type="button"
                                    @click="activeTab = 'pickups'"
                                    class="flex items-center gap-2 px-3 py-1.5 rounded-xl text-xs font-semibold transition-all cursor-pointer"
                                    :class="activeTab === 'pickups' ? 'bg-surface-container-lowest text-primary shadow-xs' : 'text-on-surface-variant hover:text-on-surface'">
                                    <span class="material-symbols-outlined text-[16px]">hail</span>
                                    <span>Pickups</span>
                                    <span class="px-1.5 py-0.2 rounded-full text-[10px] font-bold"
                                        :class="activeTab === 'pickups' ? 'bg-primary/10 text-primary' : 'bg-surface-container-highest text-on-surface-variant'">
                                        {{ $stats['pendingPickups'] + $stats['scheduledPickups'] }}
                                    </span>
                                </button>

                                {{-- Tab 3: Sorting Station --}}
                                <button
                                    type="button"
                                    @click="activeTab = 'sorting'"
                                    class="flex items-center gap-2 px-3 py-1.5 rounded-xl text-xs font-semibold transition-all cursor-pointer"
                                    :class="activeTab === 'sorting' ? 'bg-surface-container-lowest text-primary shadow-xs' : 'text-on-surface-variant hover:text-on-surface'">
                                    <span class="material-symbols-outlined text-[16px]">hub</span>
                                    <span>Sorting</span>
                                    <span class="px-1.5 py-0.2 rounded-full text-[10px] font-bold"
                                        :class="activeTab === 'sorting' ? 'bg-primary/10 text-primary' : 'bg-surface-container-highest text-on-surface-variant'">
                                        {{ $stats['receivedUnsorted'] }}
                                    </span>
                                </button>

                                {{-- Tab 4: Dispatch Console --}}
                                <button
                                    type="button"
                                    @click="activeTab = 'dispatch'"
                                    class="flex items-center gap-2 px-3 py-1.5 rounded-xl text-xs font-semibold transition-all cursor-pointer"
                                    :class="activeTab === 'dispatch' ? 'bg-surface-container-lowest text-primary shadow-xs' : 'text-on-surface-variant hover:text-on-surface'">
                                    <span class="material-symbols-outlined text-[16px]">assignment_ind</span>
                                    <span>Ready for Courier</span>
                                    <span class="px-1.5 py-0.2 rounded-full text-[10px] font-bold"
                                        :class="activeTab === 'dispatch' ? 'bg-primary/10 text-primary' : 'bg-surface-container-highest text-on-surface-variant'">
                                        {{ $stats['sortedUnassigned'] }}
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Tab 1 Content: Incoming En Route to Hub --}}
                    <div x-show="activeTab === 'incoming'" x-cloak>
                        <div class="px-5 py-3 bg-surface-container-low/40 border-b border-outline-variant/60 flex items-center justify-between text-xs">
                            <span class="text-on-surface-variant font-medium">Parcels picked up by couriers and moving toward this facility</span>
                            <a href="{{ route('logistics.incoming.index') }}" class="font-semibold text-primary hover:underline flex items-center gap-1">
                                <span>Open Inbound Scanner ({{ $stats['awaitingReceipt'] }})</span>
                                <span class="material-symbols-outlined text-[14px]">arrow_forward</span>
                            </a>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse font-body-sm text-xs">
                                <thead>
                                    <tr class="border-b border-outline-variant bg-surface-container-low/20 text-on-surface-variant font-semibold">
                                        <th class="px-5 py-3">Tracking / Order</th>
                                        <th class="px-5 py-3">Destination Buyer</th>
                                        <th class="px-5 py-3">Destination City</th>
                                        <th class="px-5 py-3 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-outline-variant">
                                    @forelse ($incomingParcels as $order)
                                        <tr class="hover:bg-surface-container-low/40 transition-colors">
                                            <td class="px-5 py-3.5">
                                                <div class="font-mono text-primary font-bold text-xs">{{ $order->tracking_number ?? $order->order_number }}</div>
                                                <div class="text-[11px] text-on-surface-variant">{{ $order->items->count() }} item(s) • {{ $order->order_number }}</div>
                                            </td>
                                            <td class="px-5 py-3.5">
                                                <div class="font-medium text-on-surface">{{ $order->buyer?->full_name ?? 'Buyer' }}</div>
                                                <div class="text-[11px] text-on-surface-variant">{{ $order->address?->phone ?? 'Contact on file' }}</div>
                                            </td>
                                            <td class="px-5 py-3.5">
                                                <div class="font-medium text-on-surface">{{ $order->address?->municipality_name ?? 'N/A' }}</div>
                                                <div class="text-[11px] text-on-surface-variant">{{ $order->address?->barangay_name ?? $order->address?->province_name }}</div>
                                            </td>
                                            <td class="px-5 py-3.5 text-right">
                                                <form method="POST" action="{{ route('logistics.incoming.scan') }}" class="inline">
                                                    @csrf
                                                    <input type="hidden" name="identifier" value="{{ $order->tracking_number ?? $order->order_number }}">
                                                    <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-primary text-on-primary font-semibold text-xs hover:bg-primary/90 transition-all cursor-pointer shadow-2xs">
                                                        <span class="material-symbols-outlined text-[15px]">verified</span>
                                                        <span>Check In</span>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="px-5 py-10 text-center">
                                                <div class="w-10 h-10 rounded-full bg-emerald-500/10 text-emerald-700 mx-auto flex items-center justify-center mb-2">
                                                    <span class="material-symbols-outlined text-[22px]">check_circle</span>
                                                </div>
                                                <p class="text-xs font-semibold text-on-surface">No incoming parcels en route</p>
                                                <p class="text-[11px] text-on-surface-variant mt-0.5">All packages in transit have been received at this hub.</p>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Tab 2 Content: Pending Seller Pickups --}}
                    <div x-show="activeTab === 'pickups'" x-cloak>
                        <div class="px-5 py-3 bg-surface-container-low/40 border-b border-outline-variant/60 flex items-center justify-between text-xs">
                            <span class="text-on-surface-variant font-medium">Merchant orders awaiting courier dispatch and warehouse pickup</span>
                            <a href="{{ route('logistics.pickups.index') }}" class="font-semibold text-primary hover:underline flex items-center gap-1">
                                <span>Open Merchant Pickups ({{ $stats['pendingPickups'] + $stats['scheduledPickups'] }})</span>
                                <span class="material-symbols-outlined text-[14px]">arrow_forward</span>
                            </a>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse font-body-sm text-xs">
                                <thead>
                                    <tr class="border-b border-outline-variant bg-surface-container-low/20 text-on-surface-variant font-semibold">
                                        <th class="px-5 py-3">Order Number</th>
                                        <th class="px-5 py-3">Seller Store</th>
                                        <th class="px-5 py-3">Pickup Address</th>
                                        <th class="px-5 py-3 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-outline-variant">
                                    @forelse ($pendingPickups as $order)
                                        <tr class="hover:bg-surface-container-low/40 transition-colors">
                                            <td class="px-5 py-3.5">
                                                <div class="font-bold text-on-surface">{{ $order->order_number }}</div>
                                                <div class="text-[11px] text-on-surface-variant">Placed {{ $order->placed_at?->diffForHumans() ?? 'recently' }}</div>
                                            </td>
                                            <td class="px-5 py-3.5">
                                                <div class="font-medium text-on-surface">{{ $order->primary_seller?->sellerProfile?->business_name ?? 'Merchant Store' }}</div>
                                                <div class="text-[11px] text-on-surface-variant">{{ $order->primary_seller?->full_name }}</div>
                                            </td>
                                            <td class="px-5 py-3.5">
                                                <div class="font-medium text-on-surface">{{ $order->seller_address?->municipality_name ?? 'Origin Hub' }}</div>
                                                <div class="text-[11px] text-on-surface-variant">{{ $order->seller_address?->barangay_name ?? $order->seller_address?->province_name }}</div>
                                            </td>
                                            <td class="px-5 py-3.5 text-right">
                                                <a href="{{ route('logistics.pickups.show', $order) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-primary text-on-primary font-semibold text-xs hover:bg-primary/90 transition-all shadow-2xs">
                                                    <span>Manage Pickup</span>
                                                    <span class="material-symbols-outlined text-[15px]">arrow_forward</span>
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="px-5 py-10 text-center">
                                                <div class="w-10 h-10 rounded-full bg-emerald-500/10 text-emerald-700 mx-auto flex items-center justify-center mb-2">
                                                    <span class="material-symbols-outlined text-[22px]">check_circle</span>
                                                </div>
                                                <p class="text-xs font-semibold text-on-surface">No pending pickup requests</p>
                                                <p class="text-[11px] text-on-surface-variant mt-0.5">All merchant pickup requests have been scheduled or processed.</p>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Tab 3 Content: Sorting Station --}}
                    <div x-show="activeTab === 'sorting'" x-cloak>
                        <div class="px-5 py-3 bg-surface-container-low/40 border-b border-outline-variant/60 flex items-center justify-between text-xs">
                            <span class="text-on-surface-variant font-medium">Parcels safely checked in at facility awaiting zone sorting & bin assignment</span>
                            <a href="{{ route('logistics.sorting.index') }}" class="font-semibold text-primary hover:underline flex items-center gap-1">
                                <span>Open Sorting Console ({{ $stats['receivedUnsorted'] }})</span>
                                <span class="material-symbols-outlined text-[14px]">arrow_forward</span>
                            </a>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse font-body-sm text-xs">
                                <thead>
                                    <tr class="border-b border-outline-variant bg-surface-container-low/20 text-on-surface-variant font-semibold">
                                        <th class="px-5 py-3">Tracking / Order</th>
                                        <th class="px-5 py-3">Destination Municipality</th>
                                        <th class="px-5 py-3">Barangay / Area</th>
                                        <th class="px-5 py-3 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-outline-variant">
                                    @forelse ($unsortedParcels as $order)
                                        <tr class="hover:bg-surface-container-low/40 transition-colors">
                                            <td class="px-5 py-3.5">
                                                <div class="font-mono text-primary font-bold text-xs">{{ $order->tracking_number ?? $order->order_number }}</div>
                                                <div class="text-[11px] text-on-surface-variant">{{ $order->order_number }}</div>
                                            </td>
                                            <td class="px-5 py-3.5">
                                                <div class="font-medium text-on-surface">{{ $order->address?->municipality_name ?? 'N/A' }}</div>
                                                <div class="text-[11px] text-on-surface-variant">{{ $order->address?->province_name }}</div>
                                            </td>
                                            <td class="px-5 py-3.5">
                                                <div class="font-medium text-on-surface">{{ $order->address?->barangay_name ?? 'Zone Unassigned' }}</div>
                                                <div class="text-[11px] text-on-surface-variant">{{ $order->address?->street_address }}</div>
                                            </td>
                                            <td class="px-5 py-3.5 text-right">
                                                <a href="{{ route('logistics.sorting.index', ['q' => $order->order_number]) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-secondary text-on-secondary font-semibold text-xs hover:bg-secondary/90 transition-all shadow-2xs">
                                                    <span>Sort Parcel</span>
                                                    <span class="material-symbols-outlined text-[15px]">near_me</span>
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="px-5 py-10 text-center">
                                                <div class="w-10 h-10 rounded-full bg-emerald-500/10 text-emerald-700 mx-auto flex items-center justify-center mb-2">
                                                    <span class="material-symbols-outlined text-[22px]">check_circle</span>
                                                </div>
                                                <p class="text-xs font-semibold text-on-surface">All received packages have been sorted</p>
                                                <p class="text-[11px] text-on-surface-variant mt-0.5">Parcels are ready in their respective delivery zones.</p>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Tab 4 Content: Ready for Courier Dispatch --}}
                    <div x-show="activeTab === 'dispatch'" x-cloak>
                        <div class="px-5 py-3 bg-surface-container-low/40 border-b border-outline-variant/60 flex items-center justify-between text-xs">
                            <span class="text-on-surface-variant font-medium">Sorted parcels assigned to delivery zones waiting for courier assignment</span>
                            <a href="{{ route('logistics.assignments.index') }}" class="font-semibold text-primary hover:underline flex items-center gap-1">
                                <span>Open Dispatch Console ({{ $stats['sortedUnassigned'] }})</span>
                                <span class="material-symbols-outlined text-[14px]">arrow_forward</span>
                            </a>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse font-body-sm text-xs">
                                <thead>
                                    <tr class="border-b border-outline-variant bg-surface-container-low/20 text-on-surface-variant font-semibold">
                                        <th class="px-5 py-3">Tracking / Order</th>
                                        <th class="px-5 py-3">Recipient Buyer</th>
                                        <th class="px-5 py-3">Delivery Zone</th>
                                        <th class="px-5 py-3 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-outline-variant">
                                    @forelse ($unassignedDeliveries as $order)
                                        <tr class="hover:bg-surface-container-low/40 transition-colors">
                                            <td class="px-5 py-3.5">
                                                <div class="font-mono text-primary font-bold text-xs">{{ $order->tracking_number ?? $order->order_number }}</div>
                                                <div class="text-[11px] text-on-surface-variant">{{ $order->order_number }}</div>
                                            </td>
                                            <td class="px-5 py-3.5">
                                                <div class="font-medium text-on-surface">{{ $order->buyer?->full_name ?? 'Buyer' }}</div>
                                                <div class="text-[11px] text-on-surface-variant">{{ $order->address?->municipality_name }}</div>
                                            </td>
                                            <td class="px-5 py-3.5">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-lg bg-secondary/10 text-secondary text-[11px] font-bold">
                                                    {{ $order->deliveryArea?->name ?? 'Zone Unassigned' }}
                                                </span>
                                            </td>
                                            <td class="px-5 py-3.5 text-right">
                                                <a href="{{ route('logistics.assignments.index', ['q' => $order->order_number]) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-primary text-on-primary font-semibold text-xs hover:bg-primary/90 transition-all shadow-2xs">
                                                    <span>Assign Courier</span>
                                                    <span class="material-symbols-outlined text-[15px]">two_wheeler</span>
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="px-5 py-10 text-center">
                                                <div class="w-10 h-10 rounded-full bg-emerald-500/10 text-emerald-700 mx-auto flex items-center justify-center mb-2">
                                                    <span class="material-symbols-outlined text-[22px]">check_circle</span>
                                                </div>
                                                <p class="text-xs font-semibold text-on-surface">All sorted packages have couriers assigned</p>
                                                <p class="text-[11px] text-on-surface-variant mt-0.5">No parcels are currently waiting for delivery couriers.</p>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>

                {{-- Facility Quick Shortcuts Strip --}}
                <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4 shadow-xs">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-bold uppercase tracking-wider text-on-surface-variant flex items-center gap-2">
                            <span class="material-symbols-outlined text-[18px] text-primary">speed</span>
                            <span>Facility Operations Shortcuts</span>
                        </span>
                        <span class="text-[11px] text-on-surface-variant">Quick links to hub management tools</span>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <a href="{{ route('logistics.transfers.index') }}" class="p-3 rounded-xl bg-surface-container-low hover:bg-surface-container border border-outline-variant/60 hover:border-primary/40 transition-all flex items-center gap-3 group">
                            <div class="w-8 h-8 rounded-lg bg-indigo-500/10 text-indigo-700 flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
                                <span class="material-symbols-outlined text-[18px]">sync_alt</span>
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-bold text-on-surface group-hover:text-primary transition-colors truncate">Hub Transfers</p>
                                <p class="text-[10px] text-on-surface-variant truncate">Inter-hub linehaul</p>
                            </div>
                        </a>

                        <a href="{{ route('logistics.zones.index') }}" class="p-3 rounded-xl bg-surface-container-low hover:bg-surface-container border border-outline-variant/60 hover:border-primary/40 transition-all flex items-center gap-3 group">
                            <div class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-700 flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
                                <span class="material-symbols-outlined text-[18px]">share_location</span>
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-bold text-on-surface group-hover:text-primary transition-colors truncate">Coverage Zones</p>
                                <p class="text-[10px] text-on-surface-variant truncate">Barangays & routes</p>
                            </div>
                        </a>

                        <a href="{{ route('logistics.remittances.index') }}" class="p-3 rounded-xl bg-surface-container-low hover:bg-surface-container border border-outline-variant/60 hover:border-primary/40 transition-all flex items-center gap-3 group">
                            <div class="w-8 h-8 rounded-lg bg-amber-500/10 text-amber-700 flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
                                <span class="material-symbols-outlined text-[18px]">payments</span>
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-bold text-on-surface group-hover:text-primary transition-colors truncate">Rider COD</p>
                                <p class="text-[10px] text-on-surface-variant truncate">Verify collections</p>
                            </div>
                        </a>

                        <a href="{{ route('logistics.admin-remittances.index') }}" class="p-3 rounded-xl bg-surface-container-low hover:bg-surface-container border border-outline-variant/60 hover:border-primary/40 transition-all flex items-center gap-3 group">
                            <div class="w-8 h-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
                                <span class="material-symbols-outlined text-[18px]">account_balance</span>
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-bold text-on-surface group-hover:text-primary transition-colors truncate">Remit to Admin</p>
                                <p class="text-[10px] text-on-surface-variant truncate">Platform settlement</p>
                            </div>
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

@endsection
