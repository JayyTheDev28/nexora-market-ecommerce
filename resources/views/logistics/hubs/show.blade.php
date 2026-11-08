@extends('layouts.app')

@section('title', "{$hub->name} — Parcel Inventory & Facility")

@section('content')
<div class="w-full min-h-screen bg-surface">
    <div class="flex flex-col lg:flex-row w-full">
        <x-logistics-nav active="hubs" />

        <div class="flex-1 min-w-0 p-4 sm:p-6 lg:p-8 bg-surface">
            <div class="w-full max-w-7xl mx-auto space-y-6">

                {{-- Back Link & Header --}}
                <div>
                    <a href="{{ route('logistics.hubs.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-primary hover:underline mb-3">
                        <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                        <span>Back to All Hubs</span>
                    </a>
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-2.5">
                                <h1 class="font-headline-lg text-2xl md:text-3xl font-bold text-on-surface">{{ $hub->name }}</h1>
                                <span class="px-2.5 py-0.5 rounded-md text-xs font-mono font-bold bg-surface-container-high text-on-surface-variant uppercase">{{ $hub->code }}</span>
                                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold {{ $hub->is_active ? 'bg-emerald-500/10 text-emerald-700 border border-emerald-500/20' : 'bg-surface-container-high text-on-surface-variant' }}">
                                    {{ $hub->is_active ? 'Active Facility' : 'Inactive' }}
                                </span>
                            </div>
                            <p class="font-body-md text-xs sm:text-sm text-on-surface-variant mt-1.5 flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-[18px] text-primary">location_on</span>
                                <span>{{ $hub->full_location }}</span>
                                @if ($hub->contact_number)
                                    <span class="text-outline-variant">&bull;</span>
                                    <span class="material-symbols-outlined text-[16px]">phone</span>
                                    <span>{{ $hub->contact_number }}</span>
                                @endif
                            </p>
                        </div>

                        <div class="flex items-center gap-2">
                            <a href="{{ route('logistics.incoming.index', ['hub_id' => $hub->id]) }}" class="inline-flex items-center gap-2 bg-primary text-on-primary px-4 py-2 rounded-xl text-xs font-semibold shadow-xs hover:bg-primary/90 transition-all">
                                <span class="material-symbols-outlined text-[18px]">qr_code_scanner</span>
                                <span>Check In Parcels at this Hub</span>
                            </a>
                            <a href="{{ route('logistics.hubs.edit', $hub) }}" class="inline-flex items-center gap-1 px-3 py-2 rounded-xl border border-outline-variant bg-surface-container-lowest hover:bg-surface-container text-xs font-medium text-on-surface transition-colors">
                                <span class="material-symbols-outlined text-[16px]">edit</span>
                                <span>Edit Hub</span>
                            </a>
                        </div>
                    </div>
                </div>

                {{-- Inventory Stat Cards --}}
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <a href="{{ route('logistics.hubs.show', [$hub, 'tab' => 'all']) }}"
                       class="bg-surface-container-lowest rounded-2xl border p-5 transition-all hover:border-primary {{ $tab === 'all' ? 'border-primary ring-2 ring-primary/10' : 'border-outline-variant' }}">
                        <div class="flex items-center justify-between">
                            <span class="font-body-sm text-xs font-medium text-on-surface-variant">Total Hub Inventory</span>
                            <span class="w-8 h-8 rounded-full bg-primary/10 text-primary flex items-center justify-center">
                                <span class="material-symbols-outlined text-[18px]">inventory_2</span>
                            </span>
                        </div>
                        <div class="font-headline-md text-2xl font-bold text-on-surface mt-2">{{ number_format($counts['all']) }}</div>
                        <p class="font-body-xs text-[11px] text-on-surface-variant mt-1">Parcels physically at this hub</p>
                    </a>

                    <a href="{{ route('logistics.hubs.show', [$hub, 'tab' => 'staged_transfer']) }}"
                       class="bg-surface-container-lowest rounded-2xl border p-5 transition-all hover:border-primary {{ $tab === 'staged_transfer' ? 'border-primary ring-2 ring-primary/10' : 'border-outline-variant' }}">
                        <div class="flex items-center justify-between">
                            <span class="font-body-sm text-xs font-medium text-on-surface-variant">Staged for Hub Transfer</span>
                            <span class="w-8 h-8 rounded-full bg-amber-500/10 text-amber-700 flex items-center justify-center">
                                <span class="material-symbols-outlined text-[18px]">swap_horizontal_circle</span>
                            </span>
                        </div>
                        <div class="font-headline-md text-2xl font-bold text-amber-600 mt-2">{{ number_format($counts['staged_transfer']) }}</div>
                        <p class="font-body-xs text-[11px] text-on-surface-variant mt-1">Awaiting dispatch to destination hub</p>
                    </a>

                    <a href="{{ route('logistics.hubs.show', [$hub, 'tab' => 'local_delivery']) }}"
                       class="bg-surface-container-lowest rounded-2xl border p-5 transition-all hover:border-primary {{ $tab === 'local_delivery' ? 'border-primary ring-2 ring-primary/10' : 'border-outline-variant' }}">
                        <div class="flex items-center justify-between">
                            <span class="font-body-sm text-xs font-medium text-on-surface-variant">Local Delivery Queue</span>
                            <span class="w-8 h-8 rounded-full bg-emerald-500/10 text-emerald-700 flex items-center justify-center">
                                <span class="material-symbols-outlined text-[18px]">local_shipping</span>
                            </span>
                        </div>
                        <div class="font-headline-md text-2xl font-bold text-emerald-700 mt-2">{{ number_format($counts['local_delivery']) }}</div>
                        <p class="font-body-xs text-[11px] text-on-surface-variant mt-1">Destination hub reached; ready for rider</p>
                    </a>
                </div>

                {{-- Table Box --}}
                <div class="bg-surface-container-lowest border border-outline-variant/80 rounded-2xl p-5 shadow-xs">
                    {{-- Tabs & Search --}}
                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4 border-b border-outline-variant/60 pb-4 mb-4">
                        <div class="flex items-center gap-2 overflow-x-auto">
                            <a href="{{ route('logistics.hubs.show', [$hub, 'tab' => 'all']) }}"
                               class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-colors flex items-center gap-1.5 shrink-0 {{ $tab === 'all' ? 'bg-primary text-on-primary' : 'bg-surface-container hover:bg-surface-container-high text-on-surface' }}">
                                <span>All Inventory</span>
                                <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $tab === 'all' ? 'bg-white/20 text-white' : 'bg-surface text-on-surface-variant' }}">
                                    {{ $counts['all'] }}
                                </span>
                            </a>

                            <a href="{{ route('logistics.hubs.show', [$hub, 'tab' => 'staged_transfer']) }}"
                               class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-colors flex items-center gap-1.5 shrink-0 {{ $tab === 'staged_transfer' ? 'bg-primary text-on-primary' : 'bg-surface-container hover:bg-surface-container-high text-on-surface' }}">
                                <span>Awaiting Transfer</span>
                                <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $tab === 'staged_transfer' ? 'bg-white/20 text-white' : 'bg-surface text-amber-700' }}">
                                    {{ $counts['staged_transfer'] }}
                                </span>
                            </a>

                            <a href="{{ route('logistics.hubs.show', [$hub, 'tab' => 'local_delivery']) }}"
                               class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-colors flex items-center gap-1.5 shrink-0 {{ $tab === 'local_delivery' ? 'bg-primary text-on-primary' : 'bg-surface-container hover:bg-surface-container-high text-on-surface' }}">
                                <span>Local Delivery Queue</span>
                                <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $tab === 'local_delivery' ? 'bg-white/20 text-white' : 'bg-surface text-emerald-700' }}">
                                    {{ $counts['local_delivery'] }}
                                </span>
                            </a>
                        </div>

                        {{-- Search --}}
                        <form method="GET" action="{{ route('logistics.hubs.show', $hub) }}" class="flex items-center gap-2">
                            <input type="hidden" name="tab" value="{{ $tab }}">
                            <div class="relative">
                                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-[18px]">search</span>
                                <input type="text" name="q" value="{{ $search }}" placeholder="Search tracking #, order, buyer..."
                                       class="pl-9 pr-3 py-1.5 rounded-xl border border-outline-variant bg-surface text-xs text-on-surface focus:outline-none focus:border-primary w-64">
                            </div>
                            @if ($search)
                                <a href="{{ route('logistics.hubs.show', [$hub, 'tab' => $tab]) }}" class="text-xs text-on-surface-variant hover:text-on-surface">Clear</a>
                            @endif
                        </form>
                    </div>

                    {{-- Parcels Table --}}
                    @if ($parcels->isEmpty())
                        <div class="p-12 text-center text-xs text-on-surface-variant">
                            <span class="material-symbols-outlined text-4xl text-on-surface-variant/60 mb-2">inventory_2</span>
                            <h3 class="text-sm font-semibold text-on-surface">No Parcels in {{ $hub->name }} Inventory</h3>
                            <p class="text-xs text-on-surface-variant mt-1">Parcels received at this hub after seller collection will appear here.</p>
                            <a href="{{ route('logistics.incoming.index', ['hub_id' => $hub->id]) }}" class="inline-flex items-center gap-1.5 mt-4 px-4 py-2 bg-primary text-on-primary rounded-xl text-xs font-semibold">
                                <span class="material-symbols-outlined text-[16px]">qr_code_scanner</span>
                                <span>Scan Incoming Parcels to this Hub</span>
                            </a>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="border-b border-outline-variant text-[11px] font-bold text-on-surface-variant uppercase tracking-wider">
                                        <th class="py-3 px-3">Tracking / Order</th>
                                        <th class="py-3 px-3">Buyer & Delivery Location</th>
                                        <th class="py-3 px-3">Route Classification</th>
                                        <th class="py-3 px-3">Destination Hub</th>
                                        <th class="py-3 px-3">Status</th>
                                        <th class="py-3 px-3 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-outline-variant/60">
                                    @foreach ($parcels as $parcel)
                                        <tr class="hover:bg-surface-container/40 transition-colors">
                                            <td class="py-3 px-3">
                                                <p class="font-mono font-bold text-primary">{{ $parcel->tracking_number ?? 'NO-TRACKING' }}</p>
                                                <p class="text-[11px] text-on-surface font-semibold mt-0.5">Order #{{ $parcel->order_number }}</p>
                                                <p class="text-[10px] text-on-surface-variant mt-0.5">₱{{ number_format($parcel->total, 2) }} &bull; {{ $parcel->items->count() }} item(s)</p>
                                            </td>

                                            <td class="py-3 px-3">
                                                <p class="font-semibold text-on-surface">{{ $parcel->buyer?->full_name }}</p>
                                                <p class="text-[11px] text-on-surface-variant mt-0.5">
                                                    {{ $parcel->address?->barangay_name }}, {{ $parcel->address?->municipality_name }}, {{ $parcel->address?->province_name }}
                                                </p>
                                            </td>

                                            <td class="py-3 px-3">
                                                @if ((int)$parcel->destination_hub_id === (int)$hub->id || $parcel->isSameHub())
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/10 text-emerald-700 border border-emerald-500/20">
                                                        <span class="material-symbols-outlined text-[13px]">home_pin</span>
                                                        <span>Direct Local Delivery</span>
                                                    </span>
                                                @elseif ($parcel->isInternalTransfer())
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-blue-500/10 text-blue-700 border border-blue-500/20">
                                                        <span class="material-symbols-outlined text-[13px]">sync_alt</span>
                                                        <span>Internal Hub Transfer</span>
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-500/10 text-amber-700 border border-amber-500/20">
                                                        <span class="material-symbols-outlined text-[13px]">hub</span>
                                                        <span>Inter-Company Transfer</span>
                                                    </span>
                                                @endif
                                            </td>

                                            <td class="py-3 px-3">
                                                @if ($parcel->destinationHub)
                                                    <p class="font-semibold text-on-surface">{{ $parcel->destinationHub->name }}</p>
                                                    <p class="text-[11px] text-on-surface-variant">
                                                        {{ $parcel->destinationHub->city_name }}, {{ $parcel->destinationHub->province_name }}
                                                        @if ((int)$parcel->destinationHub->logistics_company_id !== (int)auth()->id())
                                                            <span class="text-primary font-medium">({{ $parcel->destinationHub->logisticsCompany?->full_name }})</span>
                                                        @endif
                                                    </p>
                                                @else
                                                    <span class="text-amber-600 italic text-xs">Unassigned</span>
                                                @endif
                                            </td>

                                            <td class="py-3 px-3">
                                                @if (in_array($parcel->status, ['received_at_hub', 'at_sorting_center']))
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/10 text-emerald-700">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>
                                                        <span>Received at Hub</span>
                                                    </span>
                                                @elseif ($parcel->status === 'in_transit_hub')
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-indigo-500/10 text-indigo-700 border border-indigo-500/20">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-indigo-600 animate-pulse"></span>
                                                        <span>In Transit Between Hubs</span>
                                                    </span>
                                                @elseif ($parcel->status === 'sorted')
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-primary/10 text-primary">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-primary"></span>
                                                        <span>Sorted for Delivery</span>
                                                    </span>
                                                @elseif ($parcel->status === 'assigned_to_rider')
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-sky-500/10 text-sky-700">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-sky-600"></span>
                                                        <span>Assigned to Rider</span>
                                                    </span>
                                                @else
                                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-surface-container text-on-surface-variant">
                                                        {{ ucwords(str_replace('_', ' ', $parcel->status)) }}
                                                    </span>
                                                @endif
                                            </td>

                                            <td class="py-3 px-3 text-right">
                                                @if ((int)$parcel->destination_hub_id !== (int)$hub->id && !$parcel->isSameHub())
                                                    <a href="{{ route('logistics.transfers.index', ['q' => $parcel->order_number]) }}" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-primary text-on-primary text-[11px] font-semibold hover:bg-primary/90 transition-colors shadow-xs">
                                                        <span class="material-symbols-outlined text-[14px]">local_shipping</span>
                                                        <span>Dispatch Transfer</span>
                                                    </a>
                                                @else
                                                    <a href="{{ route('logistics.assignments.index', ['q' => $parcel->order_number]) }}" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-emerald-600 text-white text-[11px] font-semibold hover:bg-emerald-700 transition-colors shadow-xs">
                                                        <span class="material-symbols-outlined text-[14px]">person_add</span>
                                                        <span>Assign Rider</span>
                                                    </a>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4">
                            {{ $parcels->links() }}
                        </div>
                    @endif
                </div>

            </div>
        </div>
    </div>
</div>
@endsection

