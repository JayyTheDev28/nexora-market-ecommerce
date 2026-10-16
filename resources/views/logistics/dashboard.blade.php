@extends('layouts.app')

@section('title', 'Sorting Center Operations Dashboard — Nexora Logistics')

@section('content')
<div class="w-full bg-surface py-8 min-h-[75vh]">
    <div class="max-w-none px-margin-mobile md:px-margin-desktop">

        <x-logistics-nav active="dashboard" />

        {{-- Facility Header & Quick Actions --}}
        <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 mb-8 shadow-xs">
            <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-6">
                <div>
                    <div class="flex items-center gap-2 text-primary font-bold text-xs uppercase tracking-wider mb-1">
                        <span class="material-symbols-outlined text-[18px]">local_shipping</span>
                        <span>Central Logistics & Sorting Operations</span>
                    </div>
                    <h1 class="font-headline-lg text-2xl md:text-3xl font-bold text-on-surface">
                        {{ auth()->user()->sortingCenterProfile?->business_name ?? 'Nexora Logistics Sorting Hub' }}
                    </h1>
                    <div class="flex flex-wrap items-center gap-4 mt-2 font-body-sm text-xs text-on-surface-variant">
                        <div class="flex items-center gap-1">
                            <span class="material-symbols-outlined text-[15px]">location_on</span>
                            <span>{{ auth()->user()->address?->full_address ?? 'Laguna, Philippines' }}</span>
                        </div>
                        <div>Staff In-Charge: <strong class="text-on-surface">{{ auth()->user()->full_name }}</strong></div>
                        @if ($stats['pendingRiders'] > 0)
                            <a href="{{ route('logistics.riders.index', ['tab' => 'pending']) }}" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-amber-500/10 text-amber-700 font-semibold hover:bg-amber-500/20 transition-colors">
                                <span class="material-symbols-outlined text-[14px]">notification_important</span>
                                <span>{{ $stats['pendingRiders'] }} Rider Application(s) Pending</span>
                            </a>
                        @endif
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2 w-full lg:w-auto">
                    <a href="{{ route('logistics.incoming.index') }}" class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-2xl bg-primary text-on-primary font-label-md text-xs font-semibold shadow-xs hover:bg-primary/90 transition-all">
                        <span class="material-symbols-outlined text-[18px]">qr_code_scanner</span>
                        <span>Scan Inbound</span>
                    </a>

                    <a href="{{ route('logistics.sorting.index') }}" class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-2xl bg-secondary text-on-secondary font-label-md text-xs font-semibold shadow-xs hover:bg-secondary/90 transition-all">
                        <span class="material-symbols-outlined text-[18px]">sort</span>
                        <span>Sort Parcels</span>
                    </a>

                    <a href="{{ route('logistics.riders.index') }}" class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-2xl bg-surface-container-high text-on-surface font-label-md text-xs font-semibold hover:bg-surface-container-highest transition-all">
                        <span class="material-symbols-outlined text-[18px]">two_wheeler</span>
                        <span>Manage Riders</span>
                    </a>
                </div>
            </div>
        </div>

        {{-- Operational Metric Tiles --}}
        <h2 class="font-headline-sm text-sm font-bold text-on-surface mb-3 flex items-center gap-2">
            <span class="material-symbols-outlined text-[18px] text-primary">route</span>
            <span>Live Parcel Pipeline Overview</span>
        </h2>

        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3 mb-8">
            {{-- 1. Pending Pickups --}}
            <a href="{{ route('logistics.pickups.index', ['tab' => 'pending']) }}" class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-3.5 hover:border-primary transition-all group">
                <span class="text-[11px] text-on-surface-variant font-medium block truncate">Pending Pickup</span>
                <div class="font-headline-md text-xl font-bold text-on-surface mt-1 group-hover:text-primary transition-colors">
                    {{ number_format($stats['pendingPickups']) }}
                </div>
                <span class="text-[10px] text-amber-700 font-medium mt-0.5 block">Needs rider</span>
            </a>

            {{-- 2. Scheduled Pickups --}}
            <a href="{{ route('logistics.pickups.index', ['tab' => 'scheduled']) }}" class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-3.5 hover:border-primary transition-all group">
                <span class="text-[11px] text-on-surface-variant font-medium block truncate">Pickup Dispatched</span>
                <div class="font-headline-md text-xl font-bold text-on-surface mt-1 group-hover:text-primary transition-colors">
                    {{ number_format($stats['scheduledPickups']) }}
                </div>
                <span class="text-[10px] text-blue-700 font-medium mt-0.5 block">Rider assigned</span>
            </a>

            {{-- 3. Awaiting Receipt --}}
            <a href="{{ route('logistics.incoming.index', ['tab' => 'awaiting']) }}" class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-3.5 hover:border-primary transition-all group">
                <span class="text-[11px] text-on-surface-variant font-medium block truncate">En Route to Hub</span>
                <div class="font-headline-md text-xl font-bold text-on-surface mt-1 group-hover:text-primary transition-colors">
                    {{ number_format($stats['awaitingReceipt']) }}
                </div>
                <span class="text-[10px] text-primary font-medium mt-0.5 block">Awaiting scan</span>
            </a>

            {{-- 4. In Hub / Needs Sorting --}}
            <a href="{{ route('logistics.sorting.index', ['tab' => 'unsorted']) }}" class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-3.5 hover:border-primary transition-all group">
                <span class="text-[11px] text-on-surface-variant font-medium block truncate">Needs Sorting</span>
                <div class="font-headline-md text-xl font-bold text-on-surface mt-1 group-hover:text-secondary transition-colors">
                    {{ number_format($stats['receivedUnsorted']) }}
                </div>
                <span class="text-[10px] text-secondary font-medium mt-0.5 block">At facility</span>
            </a>

            {{-- 5. Sorted / Ready for Rider --}}
            <a href="{{ route('logistics.assignments.index', ['tab' => 'unassigned']) }}" class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-3.5 hover:border-primary transition-all group">
                <span class="text-[11px] text-on-surface-variant font-medium block truncate">Sorted & Ready</span>
                <div class="font-headline-md text-xl font-bold text-on-surface mt-1 group-hover:text-primary transition-colors">
                    {{ number_format($stats['sortedUnassigned']) }}
                </div>
                <span class="text-[10px] text-amber-700 font-medium mt-0.5 block">Needs delivery rider</span>
            </a>

            {{-- 6. Assigned to Rider --}}
            <a href="{{ route('logistics.assignments.index', ['tab' => 'assigned']) }}" class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-3.5 hover:border-primary transition-all group">
                <span class="text-[11px] text-on-surface-variant font-medium block truncate">Assigned to Rider</span>
                <div class="font-headline-md text-xl font-bold text-on-surface mt-1 group-hover:text-primary transition-colors">
                    {{ number_format($stats['assignedToRider']) }}
                </div>
                <span class="text-[10px] text-blue-700 font-medium mt-0.5 block">Ready for dispatch</span>
            </a>

            {{-- 7. Out for Delivery --}}
            <a href="{{ route('logistics.deliveries.index', ['tab' => 'in_flight', 'status' => 'out_for_delivery']) }}" class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-3.5 hover:border-primary transition-all group">
                <span class="text-[11px] text-on-surface-variant font-medium block truncate">Out for Delivery</span>
                <div class="font-headline-md text-xl font-bold text-on-surface mt-1 group-hover:text-amber-700 transition-colors">
                    {{ number_format($stats['outForDelivery']) }}
                </div>
                <span class="text-[10px] text-amber-700 font-medium mt-0.5 block">On active run</span>
            </a>

            {{-- 8. Exceptions --}}
            <a href="{{ route('logistics.deliveries.index', ['tab' => 'exceptions']) }}" class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-3.5 hover:border-primary transition-all group">
                <span class="text-[11px] text-on-surface-variant font-medium block truncate">Exceptions / Fail</span>
                <div class="font-headline-md text-xl font-bold text-rose-600 mt-1">
                    {{ number_format($stats['exceptionsCount']) }}
                </div>
                <span class="text-[10px] text-rose-700 font-medium mt-0.5 block">Needs attention</span>
            </a>
        </div>

        {{-- Operational Work queues (2x2 Grid) --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">

            {{-- 1. Pending Seller Pickups --}}
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant overflow-hidden shadow-xs">
                <div class="p-4 border-b border-outline-variant bg-surface-container-low/40 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-amber-600 text-[18px]">hail</span>
                        <h3 class="font-headline-sm text-xs font-bold text-on-surface uppercase tracking-wider">Pending Pickup Requests</h3>
                    </div>
                    <a href="{{ route('logistics.pickups.index') }}" class="font-label-md text-xs text-primary hover:underline font-semibold">
                        View All ({{ $stats['pendingPickups'] + $stats['scheduledPickups'] }}) →
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse font-body-sm text-xs">
                        <tbody class="divide-y divide-outline-variant">
                            @forelse ($pendingPickups as $order)
                                <tr class="hover:bg-surface-container-low/30 transition-colors">
                                    <td class="px-4 py-3">
                                        <div class="font-bold text-on-surface">{{ $order->order_number }}</div>
                                        <div class="text-[11px] text-on-surface-variant">{{ $order->placed_at?->diffForHumans() }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-on-surface">{{ $order->primary_seller?->sellerProfile?->business_name ?? 'Seller' }}</div>
                                        <div class="text-[11px] text-on-surface-variant">{{ $order->seller_address?->municipality_name ?? 'Origin' }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('logistics.pickups.show', $order) }}" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-primary text-on-primary font-semibold text-[11px] hover:bg-primary/90 transition-all">
                                            <span>Manage</span>
                                            <span class="material-symbols-outlined text-[13px]">arrow_forward</span>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-4 py-8 text-center text-xs text-on-surface-variant italic">No pending seller pickup requests.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- 2. Incoming Parcels En Route --}}
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant overflow-hidden shadow-xs">
                <div class="p-4 border-b border-outline-variant bg-surface-container-low/40 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary text-[18px]">local_shipping</span>
                        <h3 class="font-headline-sm text-xs font-bold text-on-surface uppercase tracking-wider">Incoming En Route to Hub</h3>
                    </div>
                    <a href="{{ route('logistics.incoming.index') }}" class="font-label-md text-xs text-primary hover:underline font-semibold">
                        View All ({{ $stats['awaitingReceipt'] }}) →
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse font-body-sm text-xs">
                        <tbody class="divide-y divide-outline-variant">
                            @forelse ($incomingParcels as $order)
                                <tr class="hover:bg-surface-container-low/30 transition-colors">
                                    <td class="px-4 py-3">
                                        <div class="font-mono text-primary font-bold text-xs">{{ $order->tracking_number ?? $order->order_number }}</div>
                                        <div class="text-[11px] text-on-surface-variant">{{ $order->items->count() }} item(s)</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-on-surface">To: {{ $order->buyer?->full_name }}</div>
                                        <div class="text-[11px] text-on-surface-variant">{{ $order->address?->municipality_name }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <form method="POST" action="{{ route('logistics.incoming.scan') }}" class="inline">
                                            @csrf
                                            <input type="hidden" name="identifier" value="{{ $order->tracking_number ?? $order->order_number }}">
                                            <button type="submit" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-primary text-on-primary font-semibold text-[11px] hover:bg-primary/90 transition-all cursor-pointer">
                                                <span>Check In</span>
                                                <span class="material-symbols-outlined text-[13px]">verified</span>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-4 py-8 text-center text-xs text-on-surface-variant italic">No incoming parcels currently en route.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- 3. Received Parcels Awaiting Sorting --}}
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant overflow-hidden shadow-xs">
                <div class="p-4 border-b border-outline-variant bg-surface-container-low/40 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-secondary text-[18px]">sort</span>
                        <h3 class="font-headline-sm text-xs font-bold text-on-surface uppercase tracking-wider">Awaiting Zone Sorting</h3>
                    </div>
                    <a href="{{ route('logistics.sorting.index') }}" class="font-label-md text-xs text-primary hover:underline font-semibold">
                        View All ({{ $stats['receivedUnsorted'] }}) →
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse font-body-sm text-xs">
                        <tbody class="divide-y divide-outline-variant">
                            @forelse ($unsortedParcels as $order)
                                <tr class="hover:bg-surface-container-low/30 transition-colors">
                                    <td class="px-4 py-3">
                                        <div class="font-mono text-primary font-bold text-xs">{{ $order->tracking_number ?? $order->order_number }}</div>
                                        <div class="text-[11px] text-on-surface-variant">{{ $order->order_number }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-on-surface">{{ $order->address?->municipality_name }}</div>
                                        <div class="text-[11px] text-on-surface-variant">{{ $order->address?->barangay_name }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('logistics.sorting.index', ['q' => $order->order_number]) }}" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-secondary text-on-secondary font-semibold text-[11px] hover:bg-secondary/90 transition-all">
                                            <span>Sort</span>
                                            <span class="material-symbols-outlined text-[13px]">near_me</span>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-4 py-8 text-center text-xs text-on-surface-variant italic">All received packages have been sorted!</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- 4. Sorted Parcels Awaiting Rider --}}
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant overflow-hidden shadow-xs">
                <div class="p-4 border-b border-outline-variant bg-surface-container-low/40 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-blue-600 text-[18px]">assignment_ind</span>
                        <h3 class="font-headline-sm text-xs font-bold text-on-surface uppercase tracking-wider">Sorted — Awaiting Courier</h3>
                    </div>
                    <a href="{{ route('logistics.assignments.index') }}" class="font-label-md text-xs text-primary hover:underline font-semibold">
                        View All ({{ $stats['sortedUnassigned'] }}) →
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse font-body-sm text-xs">
                        <tbody class="divide-y divide-outline-variant">
                            @forelse ($unassignedDeliveries as $order)
                                <tr class="hover:bg-surface-container-low/30 transition-colors">
                                    <td class="px-4 py-3">
                                        <div class="font-mono text-primary font-bold text-xs">{{ $order->tracking_number ?? $order->order_number }}</div>
                                        <div class="text-[11px] text-on-surface-variant">{{ $order->buyer?->full_name }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-block px-2 py-0.5 rounded bg-secondary/10 text-secondary text-[10px] font-bold">
                                            {{ $order->deliveryArea?->name ?? 'Zone' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('logistics.assignments.index', ['q' => $order->order_number]) }}" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-primary text-on-primary font-semibold text-[11px] hover:bg-primary/90 transition-all">
                                            <span>Assign</span>
                                            <span class="material-symbols-outlined text-[13px]">two_wheeler</span>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-4 py-8 text-center text-xs text-on-surface-variant italic">All sorted packages have been assigned to couriers!</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

    </div>
</div>
@endsection
