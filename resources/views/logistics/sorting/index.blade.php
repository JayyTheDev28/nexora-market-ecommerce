@extends('layouts.app')

@section('title', 'Parcel Sorting by Delivery Area — Nexora Logistics')

@section('content')
<div class="w-full bg-surface py-8 min-h-[75vh]" x-data="{ sortModal: false, activeOrder: null, actionUrl: '', selectedAreaId: '', currentAreaId: '', isOverride: false }">
    <div class="max-w-none px-margin-mobile md:px-margin-desktop">

        <x-logistics-nav active="sorting" />

        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="font-headline-lg text-2xl md:text-3xl font-bold text-on-surface">Parcel Sorting Hub</h1>
                <p class="font-body-md text-sm text-on-surface-variant mt-1">Route arriving packages to their designated geographic delivery zones and prepare for courier dispatch.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('logistics.assignments.index') }}" class="inline-flex items-center gap-2 bg-secondary text-on-secondary px-4 py-2.5 rounded-full font-label-md text-xs font-semibold shadow-xs hover:bg-secondary/90 transition-all">
                    <span class="material-symbols-outlined text-[18px]">assignment_ind</span>
                    <span>Go to Rider Assignments</span>
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

        {{-- Delivery Areas Overview Row --}}
        <div class="mb-8">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-headline-sm text-sm font-bold text-on-surface flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px] text-primary">map</span>
                    <span>Facility Delivery Areas & Pipeline</span>
                </h2>
                @if ($areaFilter)
                    <a href="{{ route('logistics.sorting.index', ['tab' => $tab, 'q' => $search]) }}" class="font-label-md text-xs text-primary hover:underline flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px]">clear</span>
                        <span>Clear Area Filter</span>
                    </a>
                @endif
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                @forelse ($deliveryAreas as $area)
                    <a
                        href="{{ route('logistics.sorting.index', ['tab' => $tab, 'area_id' => $area->id, 'q' => $search]) }}"
                        class="bg-surface-container-lowest rounded-2xl border transition-all p-4 hover:border-primary {{ $areaFilter == $area->id ? 'border-primary ring-2 ring-primary/10' : 'border-outline-variant' }}">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-on-surface text-xs truncate max-w-[170px]" title="{{ $area->name }}">{{ $area->name }}</span>
                            <span class="w-6 h-6 rounded-full bg-secondary/10 text-secondary flex items-center justify-center text-[11px] font-bold">
                                {{ $area->unsorted_orders_count }}
                            </span>
                        </div>
                        <div class="flex items-center gap-1.5 text-[11px] text-on-surface-variant mt-2">
                            <span class="material-symbols-outlined text-[13px]">two_wheeler</span>
                            <span class="truncate">{{ $area->courier?->full_name ?? 'No Rider Assigned' }}</span>
                        </div>
                        <div class="mt-2.5 pt-2 border-t border-outline-variant/60 flex items-center justify-between text-[11px]">
                            <span class="text-amber-700 font-medium">{{ $area->unsorted_orders_count }} Awaiting Sort</span>
                            <span class="text-emerald-700 font-medium">{{ $area->sorted_orders_count }} Sorted</span>
                        </div>
                    </a>
                @empty
                    <div class="col-span-full bg-surface-container-lowest rounded-2xl border border-outline-variant p-4 text-center text-xs text-on-surface-variant">
                        No delivery areas registered. <a href="{{ route('logistics.riders.index') }}" class="text-primary font-semibold hover:underline">Add delivery coverage areas</a> in Rider Management.
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Filters & Search --}}
        <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4 mb-6">
            <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-1 overflow-x-auto w-full md:w-auto pb-1 md:pb-0">
                    <a href="{{ route('logistics.sorting.index', ['tab' => 'unsorted', 'area_id' => $areaFilter, 'q' => $search]) }}"
                        class="px-3.5 py-1.5 rounded-full font-label-md text-xs font-medium transition-colors whitespace-nowrap {{ $tab === 'unsorted' ? 'bg-primary text-on-primary' : 'bg-surface-container-high text-on-surface hover:bg-surface-container-highest' }}">
                        Awaiting Sorting ({{ $counts['unsorted'] }})
                    </a>
                    <a href="{{ route('logistics.sorting.index', ['tab' => 'sorted', 'area_id' => $areaFilter, 'q' => $search]) }}"
                        class="px-3.5 py-1.5 rounded-full font-label-md text-xs font-medium transition-colors whitespace-nowrap {{ $tab === 'sorted' ? 'bg-primary text-on-primary' : 'bg-surface-container-high text-on-surface hover:bg-surface-container-highest' }}">
                        Sorted & Ready ({{ $counts['sorted'] }})
                    </a>
                    <a href="{{ route('logistics.sorting.index', ['tab' => 'all', 'area_id' => $areaFilter, 'q' => $search]) }}"
                        class="px-3.5 py-1.5 rounded-full font-label-md text-xs font-medium transition-colors whitespace-nowrap {{ $tab === 'all' ? 'bg-primary text-on-primary' : 'bg-surface-container-high text-on-surface hover:bg-surface-container-highest' }}">
                        All Parcels ({{ $counts['all'] }})
                    </a>
                </div>

                <form method="GET" action="{{ route('logistics.sorting.index') }}" class="w-full md:w-80 flex items-center">
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    @if ($areaFilter)
                        <input type="hidden" name="area_id" value="{{ $areaFilter }}">
                    @endif
                    <div class="relative w-full">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-[18px]">search</span>
                        <input
                            type="text"
                            name="q"
                            value="{{ $search }}"
                            placeholder="Filter by tracking, buyer, address..."
                            class="w-full pl-9 pr-4 py-2 bg-surface-container-high border-none rounded-xl font-body-sm text-xs text-on-surface placeholder:text-on-surface-variant/60 focus:ring-2 focus:ring-primary">
                    </div>
                </form>
            </div>
        </div>

        {{-- Table --}}
        <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant overflow-hidden shadow-xs">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-outline-variant bg-surface-container-low/50 font-label-md text-xs text-on-surface-variant font-semibold">
                            <th class="px-5 py-3.5">Tracking / Order</th>
                            <th class="px-5 py-3.5">Buyer & Delivery Address</th>
                            <th class="px-5 py-3.5">Destination Area</th>
                            <th class="px-5 py-3.5">Designated Rider</th>
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
                                    <div class="text-[11px] text-on-surface-variant mt-0.5" title="{{ $parcel->address?->full_address }}">
                                        {{ $parcel->address?->street ?? 'Street not specified' }}, {{ $parcel->address?->province_name }}
                                    </div>
                                </td>

                                <td class="px-5 py-4">
                                    @if ($parcel->deliveryArea)
                                        <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-secondary/10 text-secondary font-bold text-xs">
                                            <span class="material-symbols-outlined text-[15px]">near_me</span>
                                            <span>{{ $parcel->deliveryArea->name }}</span>
                                        </div>
                                    @else
                                        {{-- Heuristic match suggestion --}}
                                        @php
                                            $suggested = $deliveryAreas->first(fn ($a) => stripos($a->name, $parcel->address?->municipality_name ?? '') !== false);
                                        @endphp
                                        @if ($suggested)
                                            <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-amber-500/10 text-amber-700 text-xs font-medium">
                                                <span class="material-symbols-outlined text-[14px]">auto_fix_high</span>
                                                <span>Suggested: {{ $suggested->name }}</span>
                                            </div>
                                        @else
                                            <div class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-surface-container-high text-on-surface-variant text-xs">
                                                <span class="material-symbols-outlined text-[14px]">help</span>
                                                <span>Unresolved Area</span>
                                            </div>
                                        @endif
                                    @endif
                                </td>

                                <td class="px-5 py-4">
                                    @if ($parcel->deliveryArea?->courier)
                                        <div class="font-medium text-on-surface">{{ $parcel->deliveryArea->courier->full_name }}</div>
                                        <div class="text-[11px] text-on-surface-variant">{{ $parcel->deliveryArea->courier->courierProfile?->plate_number ?? 'Rider' }}</div>
                                    @else
                                        <span class="text-on-surface-variant italic text-xs">No area rider</span>
                                    @endif
                                </td>

                                <td class="px-5 py-4">
                                    @if ($parcel->status === 'at_sorting_center')
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-amber-500/10 text-amber-700 text-xs font-medium">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-600"></span>
                                            Needs Sorting
                                        </span>
                                    @elseif ($parcel->status === 'sorted')
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-500/10 text-emerald-700 text-xs font-medium">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>
                                            Sorted
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-surface-container-high text-on-surface text-xs font-medium">
                                            {{ ucwords(str_replace('_', ' ', $parcel->status)) }}
                                        </span>
                                    @endif
                                </td>

                                <td class="px-5 py-4 text-right whitespace-nowrap">
                                    <div class="inline-flex items-center gap-2">
                                        @if ($parcel->status === 'at_sorting_center')
                                            {{-- If already has area, quick 1-click confirm --}}
                                            @if ($parcel->delivery_area_id)
                                                <form method="POST" action="{{ route('logistics.sorting.confirm', $parcel) }}" class="inline">
                                                    @csrf
                                                    <input type="hidden" name="delivery_area_id" value="{{ $parcel->delivery_area_id }}">
                                                    <button
                                                        type="submit"
                                                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-emerald-600 text-white font-label-md text-xs font-semibold hover:bg-emerald-700 transition-all cursor-pointer">
                                                        <span class="material-symbols-outlined text-[15px]">check</span>
                                                        <span>Confirm Sort</span>
                                                    </button>
                                                </form>
                                            @endif

                                            <button
                                                type="button"
                                                @click="activeOrder = {{ $parcel->toJson() }}; actionUrl = '{{ route('logistics.sorting.confirm', $parcel) }}'; selectedAreaId = '{{ $parcel->delivery_area_id ?? ($suggested->id ?? '') }}'; currentAreaId = '{{ $parcel->delivery_area_id }}'; isOverride = (currentAreaId !== ''); sortModal = true;"
                                                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-primary text-on-primary font-label-md text-xs font-semibold hover:bg-primary/90 transition-all cursor-pointer">
                                                <span class="material-symbols-outlined text-[15px]">sort</span>
                                                <span>{{ $parcel->delivery_area_id ? 'Change Area' : 'Assign Area' }}</span>
                                            </button>
                                        @elseif ($parcel->status === 'sorted')
                                            <a
                                                href="{{ route('logistics.assignments.index', ['q' => $parcel->order_number]) }}"
                                                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-secondary text-on-secondary font-label-md text-xs font-semibold hover:bg-secondary/90 transition-all">
                                                <span class="material-symbols-outlined text-[15px]">assignment_ind</span>
                                                <span>Assign Rider</span>
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-12 text-center">
                                    <div class="flex flex-col items-center justify-center">
                                        <span class="w-12 h-12 rounded-full bg-surface-container-high text-on-surface-variant flex items-center justify-center mb-3">
                                            <span class="material-symbols-outlined text-[24px]">sort</span>
                                        </span>
                                        <p class="font-headline-sm text-sm font-semibold text-on-surface">No parcels to display</p>
                                        <p class="font-body-sm text-xs text-on-surface-variant mt-1">There are no parcels matching your current sorting hub filter.</p>
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

    {{-- Assign / Override Delivery Area Modal --}}
    <div
        x-show="sortModal"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
        @keydown.escape.window="sortModal = false">
        <div class="bg-surface-container-lowest rounded-2xl max-w-md w-full p-6 shadow-xl border border-outline-variant" @click.outside="sortModal = false">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary text-[22px]">near_me</span>
                    <h3 class="font-headline-sm text-base font-bold text-on-surface">Destination Area Sorting</h3>
                </div>
                <button @click="sortModal = false" class="text-on-surface-variant hover:text-on-surface">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>

            <p class="font-body-sm text-xs text-on-surface-variant mb-4">
                Select the delivery coverage area for this package based on the buyer's destination municipality and barangay.
            </p>

            <form :action="actionUrl" method="POST">
                @csrf
                <div class="mb-4">
                    <label class="block font-label-md text-xs font-semibold text-on-surface mb-1">Select Delivery Area</label>
                    <select
                        name="delivery_area_id"
                        x-model="selectedAreaId"
                        required
                        class="w-full px-3 py-2 bg-surface-container-high border-none rounded-xl font-body-sm text-xs text-on-surface focus:ring-2 focus:ring-primary">
                        <option value="">-- Choose Delivery Area --</option>
                        @foreach ($deliveryAreas as $area)
                            <option value="{{ $area->id }}">
                                {{ $area->name }} (Rider: {{ $area->courier?->full_name ?? 'None' }})
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Override reason required if reassigning --}}
                <div class="mb-5" x-show="isOverride && selectedAreaId !== currentAreaId">
                    <label class="block font-label-md text-xs font-semibold text-on-surface mb-1">
                        Reason for Manual Override <span class="text-rose-600">*</span>
                    </label>
                    <textarea
                        name="override_reason"
                        rows="2"
                        placeholder="Explain reason for overriding default area (e.g. boundary adjustment, road closure)..."
                        class="w-full px-3 py-2 bg-surface-container-high border-none rounded-xl font-body-sm text-xs text-on-surface focus:ring-2 focus:ring-primary"></textarea>
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" @click="sortModal = false" class="px-4 py-2 rounded-xl bg-surface-container-high text-on-surface font-label-md text-xs font-semibold hover:bg-surface-container-highest transition-colors">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-primary text-on-primary font-label-md text-xs font-semibold hover:bg-primary/90 transition-all">
                        Save Sorting Decision
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
