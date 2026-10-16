@extends('layouts.app')

@section('title', 'Delivery Monitoring — Nexora Logistics')

@section('content')
<div class="w-full bg-surface py-8 min-h-[75vh]" x-data="{ resolveModal: false, activeOrder: null, actionUrl: '', orderNumber: '' }">
    <div class="max-w-none px-margin-mobile md:px-margin-desktop">

        <x-logistics-nav active="deliveries" />

        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="font-headline-lg text-2xl md:text-3xl font-bold text-on-surface">Delivery Operations Monitoring</h1>
                <p class="font-body-md text-sm text-on-surface-variant mt-1">Live tracking of dispatched courier deliveries, failed attempts, and exception resolutions.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('logistics.reports.index') }}" class="inline-flex items-center gap-2 bg-surface-container-high text-on-surface px-4 py-2.5 rounded-full font-label-md text-xs font-semibold hover:bg-surface-container-highest transition-all">
                    <span class="material-symbols-outlined text-[18px]">bar_chart</span>
                    <span>Performance Reports</span>
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

        {{-- Stat Cards --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <a href="{{ route('logistics.deliveries.index', ['tab' => 'in_flight']) }}" class="bg-surface-container-lowest rounded-2xl border transition-all p-5 hover:border-primary {{ $tab === 'in_flight' ? 'border-primary ring-2 ring-primary/10' : 'border-outline-variant' }}">
                <div class="flex items-center justify-between">
                    <span class="font-body-sm text-xs font-medium text-on-surface-variant">In-Flight Active</span>
                    <span class="w-8 h-8 rounded-full bg-blue-500/10 text-blue-700 flex items-center justify-center">
                        <span class="material-symbols-outlined text-[18px]">two_wheeler</span>
                    </span>
                </div>
                <div class="font-headline-md text-2xl font-bold text-on-surface mt-2">{{ number_format($counts['in_flight']) }}</div>
                <p class="font-body-xs text-xs text-on-surface-variant mt-1">Assigned or out with riders</p>
            </a>

            <a href="{{ route('logistics.deliveries.index', ['tab' => 'in_flight', 'status' => 'out_for_delivery']) }}" class="bg-surface-container-lowest rounded-2xl border transition-all p-5 hover:border-primary {{ $status === 'out_for_delivery' ? 'border-primary ring-2 ring-primary/10' : 'border-outline-variant' }}">
                <div class="flex items-center justify-between">
                    <span class="font-body-sm text-xs font-medium text-on-surface-variant">Out for Delivery</span>
                    <span class="w-8 h-8 rounded-full bg-amber-500/10 text-amber-700 flex items-center justify-center">
                        <span class="material-symbols-outlined text-[18px]">near_me</span>
                    </span>
                </div>
                <div class="font-headline-md text-2xl font-bold text-on-surface mt-2">{{ number_format($counts['out_for_delivery']) }}</div>
                <p class="font-body-xs text-xs text-on-surface-variant mt-1">Currently on rider delivery run</p>
            </a>

            <a href="{{ route('logistics.deliveries.index', ['tab' => 'exceptions']) }}" class="bg-surface-container-lowest rounded-2xl border transition-all p-5 hover:border-primary {{ $tab === 'exceptions' ? 'border-primary ring-2 ring-primary/10' : 'border-outline-variant' }}">
                <div class="flex items-center justify-between">
                    <span class="font-body-sm text-xs font-medium text-on-surface-variant">Exceptions / Failed</span>
                    <span class="w-8 h-8 rounded-full bg-rose-500/10 text-rose-700 flex items-center justify-center">
                        <span class="material-symbols-outlined text-[18px]">error</span>
                    </span>
                </div>
                <div class="font-headline-md text-2xl font-bold text-rose-600 mt-2">{{ number_format($counts['exceptions']) }}</div>
                <p class="font-body-xs text-xs text-on-surface-variant mt-1">Failed delivery or return required</p>
            </a>

            <a href="{{ route('logistics.deliveries.index', ['tab' => 'completed']) }}" class="bg-surface-container-lowest rounded-2xl border transition-all p-5 hover:border-primary {{ $tab === 'completed' ? 'border-primary ring-2 ring-primary/10' : 'border-outline-variant' }}">
                <div class="flex items-center justify-between">
                    <span class="font-body-sm text-xs font-medium text-on-surface-variant">Successfully Delivered</span>
                    <span class="w-8 h-8 rounded-full bg-emerald-500/10 text-emerald-700 flex items-center justify-center">
                        <span class="material-symbols-outlined text-[18px]">check_circle</span>
                    </span>
                </div>
                <div class="font-headline-md text-2xl font-bold text-on-surface mt-2">{{ number_format($counts['completed']) }}</div>
                <p class="font-body-xs text-xs text-on-surface-variant mt-1">Delivered to customer</p>
            </a>
        </div>

        {{-- Filters & Search --}}
        <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4 mb-6">
            <div class="flex flex-col lg:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-1 overflow-x-auto w-full lg:w-auto pb-1 lg:pb-0">
                    <a href="{{ route('logistics.deliveries.index', ['tab' => 'in_flight', 'area_id' => $areaId, 'courier_id' => $courierId, 'q' => $search]) }}"
                        class="px-3.5 py-1.5 rounded-full font-label-md text-xs font-medium transition-colors whitespace-nowrap {{ $tab === 'in_flight' ? 'bg-primary text-on-primary' : 'bg-surface-container-high text-on-surface hover:bg-surface-container-highest' }}">
                        In-Flight ({{ $counts['in_flight'] }})
                    </a>
                    <a href="{{ route('logistics.deliveries.index', ['tab' => 'exceptions', 'area_id' => $areaId, 'courier_id' => $courierId, 'q' => $search]) }}"
                        class="px-3.5 py-1.5 rounded-full font-label-md text-xs font-medium transition-colors whitespace-nowrap {{ $tab === 'exceptions' ? 'bg-primary text-on-primary' : 'bg-surface-container-high text-on-surface hover:bg-surface-container-highest' }}">
                        Exceptions ({{ $counts['exceptions'] }})
                    </a>
                    <a href="{{ route('logistics.deliveries.index', ['tab' => 'completed', 'area_id' => $areaId, 'courier_id' => $courierId, 'q' => $search]) }}"
                        class="px-3.5 py-1.5 rounded-full font-label-md text-xs font-medium transition-colors whitespace-nowrap {{ $tab === 'completed' ? 'bg-primary text-on-primary' : 'bg-surface-container-high text-on-surface hover:bg-surface-container-highest' }}">
                        Completed ({{ $counts['completed'] }})
                    </a>
                    <a href="{{ route('logistics.deliveries.index', ['tab' => 'all', 'area_id' => $areaId, 'courier_id' => $courierId, 'q' => $search]) }}"
                        class="px-3.5 py-1.5 rounded-full font-label-md text-xs font-medium transition-colors whitespace-nowrap {{ $tab === 'all' ? 'bg-primary text-on-primary' : 'bg-surface-container-high text-on-surface hover:bg-surface-container-highest' }}">
                        All ({{ $counts['all'] }})
                    </a>
                </div>

                <form method="GET" action="{{ route('logistics.deliveries.index') }}" class="flex flex-wrap items-center gap-2 w-full lg:w-auto">
                    <input type="hidden" name="tab" value="{{ $tab }}">

                    <select name="area_id" onchange="this.form.submit()" class="px-3 py-2 bg-surface-container-high border-none rounded-xl font-body-sm text-xs text-on-surface focus:ring-2 focus:ring-primary">
                        <option value="">All Zones</option>
                        @foreach ($deliveryAreas as $area)
                            <option value="{{ $area->id }}" {{ $areaId == $area->id ? 'selected' : '' }}>
                                {{ $area->name }}
                            </option>
                        @endforeach
                    </select>

                    <select name="courier_id" onchange="this.form.submit()" class="px-3 py-2 bg-surface-container-high border-none rounded-xl font-body-sm text-xs text-on-surface focus:ring-2 focus:ring-primary">
                        <option value="">All Riders</option>
                        @foreach ($couriers as $courier)
                            <option value="{{ $courier->id }}" {{ $courierId == $courier->id ? 'selected' : '' }}>
                                {{ $courier->full_name }}
                            </option>
                        @endforeach
                    </select>

                    <div class="relative flex-1 sm:w-60">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-[18px]">search</span>
                        <input
                            type="text"
                            name="q"
                            value="{{ $search }}"
                            placeholder="Search tracking, rider, buyer..."
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
                            <th class="px-5 py-3.5">Buyer & Destination</th>
                            <th class="px-5 py-3.5">Assigned Courier</th>
                            <th class="px-5 py-3.5">Zone</th>
                            <th class="px-5 py-3.5">Status</th>
                            <th class="px-5 py-3.5">Attempts</th>
                            <th class="px-5 py-3.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant font-body-sm text-xs text-on-surface">
                        @forelse ($deliveries as $delivery)
                            <tr class="hover:bg-surface-container-low/30 transition-colors">
                                <td class="px-5 py-4">
                                    <div class="font-mono font-bold text-primary text-xs">
                                        {{ $delivery->tracking_number ?? 'NO-TRACKING' }}
                                    </div>
                                    <div class="font-semibold text-on-surface text-xs mt-0.5">
                                        <a href="{{ route('logistics.deliveries.show', $delivery) }}" class="hover:text-primary transition-colors">
                                            {{ $delivery->order_number }}
                                        </a>
                                    </div>
                                    <div class="text-[11px] text-on-surface-variant mt-1">
                                        ₱{{ number_format($delivery->total, 2) }} · Updated {{ $delivery->updated_at->diffForHumans() }}
                                    </div>
                                </td>

                                <td class="px-5 py-4">
                                    <div class="font-medium text-on-surface">{{ $delivery->buyer?->full_name }}</div>
                                    <div class="text-xs font-semibold text-on-surface mt-0.5">
                                        {{ $delivery->address?->barangay_name }}, {{ $delivery->address?->municipality_name }}
                                    </div>
                                    <div class="text-[11px] text-on-surface-variant mt-0.5 truncate max-w-[200px]" title="{{ $delivery->address?->full_address }}">
                                        {{ $delivery->address?->full_address }}
                                    </div>
                                </td>

                                <td class="px-5 py-4">
                                    @if ($delivery->deliveryCourier)
                                        <div class="font-medium text-on-surface">{{ $delivery->deliveryCourier->full_name }}</div>
                                        <div class="text-[11px] text-on-surface-variant font-mono">
                                            {{ $delivery->deliveryCourier->courierProfile?->plate_number ?? 'Motorcycle' }} · {{ $delivery->deliveryCourier->contact_number }}
                                        </div>
                                    @else
                                        <span class="text-xs text-amber-700 italic font-medium">Unassigned</span>
                                    @endif
                                </td>

                                <td class="px-5 py-4">
                                    @if ($delivery->deliveryArea)
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-secondary/10 text-secondary font-medium text-xs">
                                            {{ $delivery->deliveryArea->name }}
                                        </span>
                                    @else
                                        <span class="text-xs text-on-surface-variant italic">Unspecified</span>
                                    @endif
                                </td>

                                <td class="px-5 py-4">
                                    @if ($delivery->status === 'assigned_to_rider')
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-blue-500/10 text-blue-700 text-xs font-medium">
                                            <span class="w-1.5 h-1.5 rounded-full bg-blue-600"></span>
                                            Assigned
                                        </span>
                                    @elseif ($delivery->status === 'out_for_delivery')
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-amber-500/10 text-amber-700 text-xs font-medium">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-600"></span>
                                            Out for Delivery
                                        </span>
                                    @elseif (in_array($delivery->status, ['delivered', 'completed']))
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-500/10 text-emerald-700 text-xs font-medium">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>
                                            Delivered
                                        </span>
                                    @elseif ($delivery->status === 'delivery_failed')
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-rose-500/10 text-rose-700 text-xs font-medium">
                                            <span class="w-1.5 h-1.5 rounded-full bg-rose-600"></span>
                                            Delivery Failed
                                        </span>
                                    @elseif ($delivery->status === 'returned')
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-surface-container-high text-on-surface text-xs font-medium">
                                            <span class="w-1.5 h-1.5 rounded-full bg-on-surface"></span>
                                            Returned to Seller
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-surface-container-high text-on-surface text-xs font-medium">
                                            {{ ucwords(str_replace('_', ' ', $delivery->status)) }}
                                        </span>
                                    @endif
                                </td>

                                <td class="px-5 py-4">
                                    @php
                                        $failedAttempts = $delivery->deliveryAttempts->where('result', 'failed')->count();
                                    @endphp
                                    @if ($delivery->deliveryAttempts->count() > 0)
                                        <div class="flex items-center gap-1.5">
                                            <span class="font-bold text-xs">{{ $delivery->deliveryAttempts->count() }} attempt(s)</span>
                                            @if ($failedAttempts > 0)
                                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-rose-500/10 text-rose-700" title="{{ $failedAttempts }} failed attempt(s)">
                                                    {{ $failedAttempts }} fail
                                                </span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-on-surface-variant text-xs">—</span>
                                    @endif
                                </td>

                                <td class="px-5 py-4 text-right whitespace-nowrap">
                                    <div class="inline-flex items-center gap-2">
                                        @if ($delivery->status === 'delivery_failed')
                                            <button
                                                type="button"
                                                @click="activeOrder = {{ $delivery->toJson() }}; orderNumber = '{{ $delivery->order_number }}'; actionUrl = '{{ route('logistics.deliveries.resolve', $delivery) }}'; resolveModal = true;"
                                                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-rose-600 text-white font-label-md text-xs font-semibold hover:bg-rose-700 transition-all cursor-pointer">
                                                <span class="material-symbols-outlined text-[15px]">healing</span>
                                                <span>Resolve Issue</span>
                                            </button>
                                        @endif

                                        <a
                                            href="{{ route('logistics.deliveries.show', $delivery) }}"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-surface-container-high text-on-surface font-label-md text-xs font-semibold hover:bg-surface-container-highest transition-colors">
                                            <span class="material-symbols-outlined text-[15px]">visibility</span>
                                            <span>Inspect</span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-12 text-center">
                                    <div class="flex flex-col items-center justify-center">
                                        <span class="w-12 h-12 rounded-full bg-surface-container-high text-on-surface-variant flex items-center justify-center mb-3">
                                            <span class="material-symbols-outlined text-[24px]">local_shipping</span>
                                        </span>
                                        <p class="font-headline-sm text-sm font-semibold text-on-surface">No deliveries found</p>
                                        <p class="font-body-sm text-xs text-on-surface-variant mt-1">There are no parcel deliveries matching the current filter criteria.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($deliveries->hasPages())
                <div class="px-5 py-4 border-t border-outline-variant">
                    {{ $deliveries->links() }}
                </div>
            @endif
        </div>

    </div>

    {{-- Resolve Delivery Exception Modal --}}
    <div
        x-show="resolveModal"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
        @keydown.escape.window="resolveModal = false">
        <div class="bg-surface-container-lowest rounded-2xl max-w-md w-full p-6 shadow-xl border border-outline-variant" @click.outside="resolveModal = false">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2 text-rose-600">
                    <span class="material-symbols-outlined text-[22px]">healing</span>
                    <h3 class="font-headline-sm text-base font-bold">Resolve Delivery Exception</h3>
                </div>
                <button @click="resolveModal = false" class="text-on-surface-variant hover:text-on-surface">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>

            <p class="font-body-sm text-xs text-on-surface-variant mb-4">
                Package <strong class="text-on-surface" x-text="orderNumber"></strong> encountered a delivery failure. Select an authorized resolution path:
            </p>

            <form :action="actionUrl" method="POST">
                @csrf
                <div class="space-y-3 mb-4">
                    <label class="flex items-start gap-3 p-3 rounded-xl border border-outline-variant cursor-pointer hover:bg-surface-container-low transition-colors">
                        <input type="radio" name="action" value="reschedule" checked class="mt-1 text-primary focus:ring-primary">
                        <div>
                            <div class="font-label-md text-xs font-bold text-on-surface">Reschedule Delivery Attempt</div>
                            <div class="font-body-xs text-[11px] text-on-surface-variant mt-0.5">Keep package at hub and re-dispatch with the assigned rider on the next cycle.</div>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 p-3 rounded-xl border border-outline-variant cursor-pointer hover:bg-surface-container-low transition-colors">
                        <input type="radio" name="action" value="return_to_seller" class="mt-1 text-rose-600 focus:ring-rose-500">
                        <div>
                            <div class="font-label-md text-xs font-bold text-rose-600">Return Package to Seller (RTS)</div>
                            <div class="font-body-xs text-[11px] text-on-surface-variant mt-0.5">Buyer uncontactable or delivery refused. Mark as 'Returned' and route back to merchant.</div>
                        </div>
                    </label>
                </div>

                <div class="mb-5">
                    <label class="block font-label-md text-xs font-semibold text-on-surface mb-1">Resolution Notes <span class="text-rose-600">*</span></label>
                    <textarea name="notes" rows="3" required placeholder="Specify details, e.g. Buyer requested redelivery on Saturday, buyer unreachable after 3 attempts..." class="w-full px-3 py-2 bg-surface-container-high border-none rounded-xl font-body-sm text-xs text-on-surface focus:ring-2 focus:ring-primary"></textarea>
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" @click="resolveModal = false" class="px-4 py-2 rounded-xl bg-surface-container-high text-on-surface font-label-md text-xs font-semibold hover:bg-surface-container-highest transition-colors">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-primary text-on-primary font-label-md text-xs font-semibold hover:bg-primary/90 transition-all">
                        Apply Resolution
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
