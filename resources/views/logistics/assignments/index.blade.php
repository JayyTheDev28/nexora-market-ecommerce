@extends('layouts.app')

@section('title', 'Rider Parcel Assignments — Nexora Logistics')

@section('content')
<div class="w-full min-h-screen bg-surface" x-data="{ assignModal: false, activeOrder: null, actionUrl: '', selectedCourierId: '', targetAreaId: null, orderAreaName: '', activeCourierAreas: [] }">
    <div class="flex flex-col lg:flex-row w-full">

            <x-logistics-nav active="assignments" />

            <div class="flex-1 min-w-0 p-4 sm:p-6 lg:p-8 bg-surface">
            <div class="w-full max-w-7xl mx-auto space-y-6">

        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="font-headline-lg text-2xl md:text-3xl font-bold text-on-surface">Rider Delivery Assignments</h1>
                <p class="font-body-md text-sm text-on-surface-variant mt-1">Dispatch sorted parcels to eligible zone riders based on coverage areas and live active workloads.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('logistics.deliveries.index') }}" class="inline-flex items-center gap-2 bg-surface-container-high text-on-surface px-4 py-2.5 rounded-full font-label-md text-xs font-semibold hover:bg-surface-container-highest transition-all">
                    <span class="material-symbols-outlined text-[18px]">local_shipping</span>
                    <span>Delivery Monitoring</span>
                </a>
            </div>
        </div>

        {{-- Alerts --}}
        @if (session('status'))
            <div class="mb-6 bg-primary/10 border border-primary/30 rounded-2xl p-4 flex items-center gap-3">
                <span class="material-symbols-outlined text-primary text-[20px]">check_circle</span>
                <p class="font-body-sm text-xs md:text-sm text-primary font-medium">{{ session('status') }}</p>
            </div>
        @endif

        @if (session('info'))
            <div class="mb-6 bg-surface-container-high border border-outline-variant rounded-2xl p-4 flex items-center gap-3">
                <span class="material-symbols-outlined text-on-surface-variant text-[20px]">info</span>
                <p class="font-body-sm text-xs md:text-sm text-on-surface font-medium">{{ session('info') }}</p>
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 bg-rose-500/10 border border-rose-500/20 rounded-2xl p-4">
                <p class="font-label-md text-xs font-bold text-rose-700">Please correct the following errors:</p>
                <ul class="list-disc list-inside text-xs text-rose-600 mt-1 space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Active Courier Fleet Workload Bar --}}
        <div class="mb-8">
            <h2 class="font-headline-sm text-sm font-bold text-on-surface mb-3 flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px] text-primary">two_wheeler</span>
                <span>Active Courier Fleet & Live Workload</span>
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                @forelse ($couriers as $courier)
                    <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <div class="font-bold text-on-surface text-xs truncate max-w-[150px]">
                                    {{ $courier->full_name }}
                                </div>
                                <div class="text-[11px] text-on-surface-variant font-mono">
                                    {{ $courier->courierProfile?->plate_number ?? 'No Plate' }} · {{ $courier->courierProfile?->vehicle_type ?? 'Motorcycle' }}
                                </div>
                            </div>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-primary/10 text-primary">
                                {{ $courier->workload_count }} active
                            </span>
                        </div>

                        <div class="mt-2.5 pt-2 border-t border-outline-variant/60">
                            <div class="text-[10px] uppercase font-bold text-on-surface-variant tracking-wider mb-1">Assigned Areas</div>
                            <div class="flex flex-wrap gap-1">
                                @forelse ($courier->deliveryAreas as $cArea)
                                    <span class="inline-block px-2 py-0.5 rounded-md bg-secondary/10 text-secondary text-[10px] font-medium truncate max-w-[120px]" title="{{ $cArea->name }}">
                                        {{ $cArea->name }}
                                    </span>
                                @empty
                                    <span class="text-[10px] text-on-surface-variant/70 italic">No assigned zones</span>
                                @endforelse
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full bg-surface-container-lowest rounded-2xl border border-outline-variant p-4 text-center text-xs text-on-surface-variant">
                        No active riders available. Check <a href="{{ route('logistics.riders.index') }}" class="text-primary font-semibold hover:underline">Rider Management</a> to approve courier applications.
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Filters & Search --}}
        <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4 mb-6">
            <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-1 overflow-x-auto w-full md:w-auto pb-1 md:pb-0">
                    <a href="{{ route('logistics.assignments.index', ['tab' => 'unassigned', 'area_id' => $areaFilter, 'q' => $search]) }}"
                        class="px-3.5 py-1.5 rounded-full font-label-md text-xs font-medium transition-colors whitespace-nowrap {{ $tab === 'unassigned' ? 'bg-primary text-on-primary' : 'bg-surface-container-high text-on-surface hover:bg-surface-container-highest' }}">
                        Awaiting Rider ({{ $counts['unassigned'] }})
                    </a>
                    <a href="{{ route('logistics.assignments.index', ['tab' => 'assigned', 'area_id' => $areaFilter, 'q' => $search]) }}"
                        class="px-3.5 py-1.5 rounded-full font-label-md text-xs font-medium transition-colors whitespace-nowrap {{ $tab === 'assigned' ? 'bg-primary text-on-primary' : 'bg-surface-container-high text-on-surface hover:bg-surface-container-highest' }}">
                        Assigned ({{ $counts['assigned'] }})
                    </a>
                    <a href="{{ route('logistics.assignments.index', ['tab' => 'all', 'area_id' => $areaFilter, 'q' => $search]) }}"
                        class="px-3.5 py-1.5 rounded-full font-label-md text-xs font-medium transition-colors whitespace-nowrap {{ $tab === 'all' ? 'bg-primary text-on-primary' : 'bg-surface-container-high text-on-surface hover:bg-surface-container-highest' }}">
                        All ({{ $counts['all'] }})
                    </a>
                </div>

                <div class="flex items-center gap-2 w-full md:w-auto">
                    {{-- Area filter select --}}
                    <form method="GET" action="{{ route('logistics.assignments.index') }}" class="flex items-center gap-2 w-full md:w-auto">
                        <input type="hidden" name="tab" value="{{ $tab }}">
                        <select name="area_id" onchange="this.form.submit()" class="px-3 py-2 bg-surface-container-high border-none rounded-xl font-body-sm text-xs text-on-surface focus:ring-2 focus:ring-primary">
                            <option value="">All Areas</option>
                            @foreach ($deliveryAreas as $area)
                                <option value="{{ $area->id }}" {{ $areaFilter == $area->id ? 'selected' : '' }}>
                                    {{ $area->name }}
                                </option>
                            @endforeach
                        </select>

                        <div class="relative w-full md:w-64">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-[18px]">search</span>
                            <input
                                type="text"
                                name="q"
                                value="{{ $search }}"
                                placeholder="Search order, rider, buyer..."
                                class="w-full pl-9 pr-4 py-2 bg-surface-container-high border-none rounded-xl font-body-sm text-xs text-on-surface placeholder:text-on-surface-variant/60 focus:ring-2 focus:ring-primary">
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Table --}}
        <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant overflow-hidden shadow-xs">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-outline-variant bg-surface-container-low/50 font-label-md text-xs text-on-surface-variant font-semibold">
                            <th class="px-5 py-3.5">Tracking / Order</th>
                            <th class="px-5 py-3.5">Buyer & Destination</th>
                            <th class="px-5 py-3.5">Delivery Zone</th>
                            <th class="px-5 py-3.5">Assigned Courier</th>
                            <th class="px-5 py-3.5">Status</th>
                            <th class="px-5 py-3.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant font-body-sm text-xs text-on-surface">
                        @forelse ($parcels as $parcel)
                            <tr class="hover:bg-surface-container-low/30 transition-colors">
                                <td class="px-5 py-4">
                                    <div class="font-mono font-bold text-primary text-xs">
                                        {{ $parcel->tracking_number ?? 'NO-TRACKING' }}
                                    </div>
                                    <div class="font-semibold text-on-surface text-xs mt-0.5">
                                        {{ $parcel->order_number }}
                                    </div>
                                    <div class="text-[11px] text-on-surface-variant mt-1">
                                        ₱{{ number_format($parcel->total, 2) }} · {{ $parcel->items->count() }} item(s)
                                    </div>
                                </td>

                                <td class="px-5 py-4">
                                    <div class="font-medium text-on-surface">{{ $parcel->buyer?->full_name }}</div>
                                    <div class="text-xs font-semibold text-on-surface mt-0.5">
                                        {{ $parcel->address?->barangay_name }}, {{ $parcel->address?->municipality_name }}
                                    </div>
                                    <div class="text-[11px] text-on-surface-variant mt-0.5 truncate max-w-[200px]" title="{{ $parcel->address?->full_address }}">
                                        {{ $parcel->address?->full_address }}
                                    </div>
                                </td>

                                <td class="px-5 py-4">
                                    @if ($parcel->deliveryArea)
                                        <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-secondary/10 text-secondary font-bold text-xs">
                                            <span class="material-symbols-outlined text-[15px]">near_me</span>
                                            <span>{{ $parcel->deliveryArea->name }}</span>
                                        </div>
                                    @else
                                        <span class="text-xs text-rose-600 font-semibold">Unsorted (Needs Area)</span>
                                    @endif
                                </td>

                                <td class="px-5 py-4">
                                    @if ($parcel->deliveryCourier)
                                        <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-blue-500/10 text-blue-700 font-bold text-xs">
                                            <span class="material-symbols-outlined text-[15px]">two_wheeler</span>
                                            <span>{{ $parcel->deliveryCourier->full_name }}</span>
                                        </div>
                                        <div class="text-[11px] text-on-surface-variant mt-0.5">
                                            {{ $parcel->deliveryCourier->courierProfile?->plate_number ?? 'Rider' }} · {{ $parcel->deliveryCourier->contact_number }}
                                        </div>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-amber-500/10 text-amber-700 text-xs font-medium">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-600"></span>
                                            Awaiting Rider
                                        </span>
                                    @endif
                                </td>

                                <td class="px-5 py-4">
                                    @if ($parcel->status === 'sorted')
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-amber-500/10 text-amber-700 text-xs font-medium">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-600"></span>
                                            Ready for Dispatch
                                        </span>
                                    @elseif ($parcel->status === 'assigned_to_rider')
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-blue-500/10 text-blue-700 text-xs font-medium">
                                            <span class="w-1.5 h-1.5 rounded-full bg-blue-600"></span>
                                            Assigned to Rider
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-surface-container-high text-on-surface text-xs font-medium">
                                            {{ ucwords(str_replace('_', ' ', $parcel->status)) }}
                                        </span>
                                    @endif
                                </td>

                                <td class="px-5 py-4 text-right whitespace-nowrap">
                                    <button
                                        type="button"
                                        @click="activeOrder = {{ $parcel->toJson() }}; actionUrl = '{{ route('logistics.assignments.assign', $parcel) }}'; targetAreaId = {{ $parcel->delivery_area_id ?? 'null' }}; orderAreaName = '{{ $parcel->deliveryArea?->name ?? 'None' }}'; selectedCourierId = '{{ $parcel->delivery_courier_id ?? ($parcel->deliveryArea?->courier_id ?? '') }}'; assignModal = true;"
                                        class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg font-label-md text-xs font-semibold shadow-xs transition-all cursor-pointer {{ $parcel->delivery_courier_id ? 'bg-surface-container-high text-on-surface hover:bg-surface-container-highest' : 'bg-primary text-on-primary hover:bg-primary/90' }}">
                                        <span class="material-symbols-outlined text-[16px]">two_wheeler</span>
                                        <span>{{ $parcel->delivery_courier_id ? 'Reassign Rider' : 'Assign Rider' }}</span>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-12 text-center">
                                    <div class="flex flex-col items-center justify-center">
                                        <span class="w-12 h-12 rounded-full bg-surface-container-high text-on-surface-variant flex items-center justify-center mb-3">
                                            <span class="material-symbols-outlined text-[24px]">assignment_ind</span>
                                        </span>
                                        <p class="font-headline-sm text-sm font-semibold text-on-surface">No parcels for assignment</p>
                                        <p class="font-body-sm text-xs text-on-surface-variant mt-1">There are no parcels matching the current assignment filters.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($parcels->hasPages())
                <div class="px-5 py-4 border-t border-outline-variant">
                    {{ $parcels->links() }}
                </div>
            @endif
                    </div>
        </div>
    </div>
</div>


    {{-- Assign / Reassign Rider Modal --}}
    <div
        x-show="assignModal"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
        @keydown.escape.window="assignModal = false">
        <div class="bg-surface-container-lowest rounded-2xl max-w-lg w-full p-6 shadow-xl border border-outline-variant" @click.outside="assignModal = false">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary text-[22px]">two_wheeler</span>
                    <h3 class="font-headline-sm text-base font-bold text-on-surface">Assign Delivery Courier</h3>
                </div>
                <button @click="assignModal = false" class="text-on-surface-variant hover:text-on-surface">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>

            <div class="bg-surface-container-low rounded-xl p-3 mb-4 text-xs font-body-sm">
                <div class="text-on-surface-variant">Destination Zone: <strong class="text-on-surface" x-text="orderAreaName"></strong></div>
            </div>

            <form :action="actionUrl" method="POST">
                @csrf
                <div class="mb-4">
                    <label class="block font-label-md text-xs font-semibold text-on-surface mb-1">Select Active Courier</label>
                    <select
                        name="courier_id"
                        x-model="selectedCourierId"
                        required
                        class="w-full px-3 py-2 bg-surface-container-high border-none rounded-xl font-body-sm text-xs text-on-surface focus:ring-2 focus:ring-primary">
                        <option value="">-- Choose Rider --</option>
                        @foreach ($couriers as $c)
                            @php
                                $areaNames = $c->deliveryAreas->pluck('name')->implode(', ');
                            @endphp
                            <option value="{{ $c->id }}">
                                {{ $c->full_name }} ({{ $c->workload_count }} active parcels) — Zones: {{ $areaNames ?: 'None' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Area Override Reason --}}
                <div class="mb-4">
                    <label class="block font-label-md text-xs font-semibold text-on-surface mb-1">
                        Out-of-Zone Override Reason (Optional / Required if rider does not cover this zone)
                    </label>
                    <textarea
                        name="override_reason"
                        rows="2"
                        placeholder="Provide reason if assigning a rider whose regular zone differs from this package's area..."
                        class="w-full px-3 py-2 bg-surface-container-high border-none rounded-xl font-body-sm text-xs text-on-surface focus:ring-2 focus:ring-primary"></textarea>
                </div>

                {{-- Delivery Notes --}}
                <div class="mb-5">
                    <label class="block font-label-md text-xs font-semibold text-on-surface mb-1">Special Delivery Instructions</label>
                    <textarea
                        name="notes"
                        rows="2"
                        placeholder="e.g. Fragile item, morning delivery preferred..."
                        class="w-full px-3 py-2 bg-surface-container-high border-none rounded-xl font-body-sm text-xs text-on-surface focus:ring-2 focus:ring-primary"></textarea>
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" @click="assignModal = false" class="px-4 py-2 rounded-xl bg-surface-container-high text-on-surface font-label-md text-xs font-semibold hover:bg-surface-container-highest transition-colors">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-primary text-on-primary font-label-md text-xs font-semibold hover:bg-primary/90 transition-all">
                        Confirm Assignment
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
