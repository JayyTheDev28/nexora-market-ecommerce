@extends('layouts.app')

@section('title', 'Seller Pickup Requests — Nexora Logistics')

@section('content')
<div class="w-full min-h-screen bg-surface" x-data="{ scheduleModal: false, rejectModal: false, activeOrder: null, actionUrl: '' }">
    <div class="flex flex-col lg:flex-row w-full">

            <x-logistics-nav active="pickups" />

            <div class="flex-1 min-w-0 p-4 sm:p-6 lg:p-8 bg-surface">
            <div class="w-full max-w-7xl mx-auto space-y-6">

        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="font-headline-lg text-2xl md:text-3xl font-bold text-on-surface">Seller Pickup Requests</h1>
                <p class="font-body-md text-sm text-on-surface-variant mt-1">Review orders packed and marked ready for pickup by marketplace sellers.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('logistics.incoming.index') }}" class="inline-flex items-center gap-2 bg-surface-container-high text-on-surface px-4 py-2.5 rounded-full font-label-md text-xs font-semibold hover:bg-surface-container-highest transition-all">
                    <span class="material-symbols-outlined text-[18px]">qr_code_scanner</span>
                    <span>Incoming Parcels</span>
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
            <a href="{{ route('logistics.pickups.index', ['tab' => 'pending']) }}" class="bg-surface-container-lowest rounded-2xl border transition-all p-5 hover:border-primary {{ $tab === 'pending' ? 'border-primary ring-2 ring-primary/10' : 'border-outline-variant' }}">
                <div class="flex items-center justify-between">
                    <span class="font-body-sm text-xs font-medium text-on-surface-variant">Pending Pickup</span>
                    <span class="w-8 h-8 rounded-full bg-amber-500/10 text-amber-700 flex items-center justify-center">
                        <span class="material-symbols-outlined text-[18px]">schedule</span>
                    </span>
                </div>
                <div class="font-headline-md text-2xl font-bold text-on-surface mt-2">{{ number_format($counts['pending']) }}</div>
                <p class="font-body-xs text-xs text-on-surface-variant mt-1">Awaiting rider dispatch</p>
            </a>

            <a href="{{ route('logistics.pickups.index', ['tab' => 'scheduled']) }}" class="bg-surface-container-lowest rounded-2xl border transition-all p-5 hover:border-primary {{ $tab === 'scheduled' ? 'border-primary ring-2 ring-primary/10' : 'border-outline-variant' }}">
                <div class="flex items-center justify-between">
                    <span class="font-body-sm text-xs font-medium text-on-surface-variant">Rider Dispatched</span>
                    <span class="w-8 h-8 rounded-full bg-blue-500/10 text-blue-700 flex items-center justify-center">
                        <span class="material-symbols-outlined text-[18px]">two_wheeler</span>
                    </span>
                </div>
                <div class="font-headline-md text-2xl font-bold text-on-surface mt-2">{{ number_format($counts['scheduled']) }}</div>
                <p class="font-body-xs text-xs text-on-surface-variant mt-1">Assigned for collection</p>
            </a>

            <a href="{{ route('logistics.pickups.index', ['tab' => 'picked_up']) }}" class="bg-surface-container-lowest rounded-2xl border transition-all p-5 hover:border-primary {{ $tab === 'picked_up' ? 'border-primary ring-2 ring-primary/10' : 'border-outline-variant' }}">
                <div class="flex items-center justify-between">
                    <span class="font-body-sm text-xs font-medium text-on-surface-variant">Collected / En Route</span>
                    <span class="w-8 h-8 rounded-full bg-emerald-500/10 text-emerald-700 flex items-center justify-center">
                        <span class="material-symbols-outlined text-[18px]">local_shipping</span>
                    </span>
                </div>
                <div class="font-headline-md text-2xl font-bold text-on-surface mt-2">{{ number_format($counts['picked_up']) }}</div>
                <p class="font-body-xs text-xs text-on-surface-variant mt-1">Heading to sorting center</p>
            </a>

            <a href="{{ route('logistics.pickups.index', ['tab' => 'all']) }}" class="bg-surface-container-lowest rounded-2xl border transition-all p-5 hover:border-primary {{ $tab === 'all' ? 'border-primary ring-2 ring-primary/10' : 'border-outline-variant' }}">
                <div class="flex items-center justify-between">
                    <span class="font-body-sm text-xs font-medium text-on-surface-variant">All Requests</span>
                    <span class="w-8 h-8 rounded-full bg-surface-container-high text-on-surface flex items-center justify-center">
                        <span class="material-symbols-outlined text-[18px]">inventory</span>
                    </span>
                </div>
                <div class="font-headline-md text-2xl font-bold text-on-surface mt-2">{{ number_format($counts['all']) }}</div>
                <p class="font-body-xs text-xs text-on-surface-variant mt-1">Total active pickup workflow</p>
            </a>
        </div>

        {{-- Filters & Search --}}
        <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4 mb-6">
            <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                {{-- Tabs --}}
                <div class="flex items-center gap-1 overflow-x-auto w-full md:w-auto pb-1 md:pb-0">
                    <a href="{{ route('logistics.pickups.index', ['tab' => 'pending', 'q' => $search]) }}"
                        class="px-3.5 py-1.5 rounded-full font-label-md text-xs font-medium transition-colors whitespace-nowrap {{ $tab === 'pending' ? 'bg-primary text-on-primary' : 'bg-surface-container-high text-on-surface hover:bg-surface-container-highest' }}">
                        Pending ({{ $counts['pending'] }})
                    </a>
                    <a href="{{ route('logistics.pickups.index', ['tab' => 'scheduled', 'q' => $search]) }}"
                        class="px-3.5 py-1.5 rounded-full font-label-md text-xs font-medium transition-colors whitespace-nowrap {{ $tab === 'scheduled' ? 'bg-primary text-on-primary' : 'bg-surface-container-high text-on-surface hover:bg-surface-container-highest' }}">
                        Dispatched ({{ $counts['scheduled'] }})
                    </a>
                    <a href="{{ route('logistics.pickups.index', ['tab' => 'picked_up', 'q' => $search]) }}"
                        class="px-3.5 py-1.5 rounded-full font-label-md text-xs font-medium transition-colors whitespace-nowrap {{ $tab === 'picked_up' ? 'bg-primary text-on-primary' : 'bg-surface-container-high text-on-surface hover:bg-surface-container-highest' }}">
                        Picked Up ({{ $counts['picked_up'] }})
                    </a>
                    <a href="{{ route('logistics.pickups.index', ['tab' => 'all', 'q' => $search]) }}"
                        class="px-3.5 py-1.5 rounded-full font-label-md text-xs font-medium transition-colors whitespace-nowrap {{ $tab === 'all' ? 'bg-primary text-on-primary' : 'bg-surface-container-high text-on-surface hover:bg-surface-container-highest' }}">
                        All ({{ $counts['all'] }})
                    </a>
                </div>

                {{-- Search --}}
                <form method="GET" action="{{ route('logistics.pickups.index') }}" class="w-full md:w-80 flex items-center">
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <div class="relative w-full">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-[18px]">search</span>
                        <input
                            type="text"
                            name="q"
                            value="{{ $search }}"
                            placeholder="Search order, tracking, seller..."
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
                            <th class="px-5 py-3.5">Order & Tracking</th>
                            <th class="px-5 py-3.5">Seller Origin</th>
                            <th class="px-5 py-3.5">Buyer Destination</th>
                            <th class="px-5 py-3.5">Parcel Items</th>
                            <th class="px-5 py-3.5">Pickup Rider</th>
                            <th class="px-5 py-3.5">Status</th>
                            <th class="px-5 py-3.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant font-body-sm text-xs text-on-surface">
                        @forelse ($orders as $order)
                            @php
                                $seller = $order->primary_seller;
                                $sellerAddress = $order->seller_address;
                            @endphp
                            <tr class="hover:bg-surface-container-low/30 transition-colors">
                                <td class="px-5 py-4">
                                    <div class="font-bold text-on-surface text-sm">
                                        <a href="{{ route('logistics.pickups.show', $order) }}" class="hover:text-primary transition-colors">
                                            {{ $order->order_number }}
                                        </a>
                                    </div>
                                    @if ($order->tracking_number)
                                        <div class="font-mono text-[11px] text-primary font-medium mt-0.5">
                                            {{ $order->tracking_number }}
                                        </div>
                                    @else
                                        <div class="text-[11px] text-on-surface-variant/70 italic mt-0.5">Tracking generated on dispatch</div>
                                    @endif
                                    <div class="text-[11px] text-on-surface-variant mt-1">Placed {{ $order->placed_at?->diffForHumans() }}</div>
                                </td>

                                <td class="px-5 py-4">
                                    <div class="font-medium text-on-surface">
                                        {{ $seller?->sellerProfile?->business_name ?? $seller?->full_name ?? 'Marketplace Seller' }}
                                    </div>
                                    <div class="text-[11px] text-on-surface-variant mt-0.5 flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[13px]">location_on</span>
                                        <span>{{ $sellerAddress?->municipality_name ?? 'Origin Hub' }}, {{ $sellerAddress?->province_name ?? 'Laguna' }}</span>
                                    </div>
                                    <div class="text-[11px] text-on-surface-variant/80 mt-0.5">
                                        {{ $seller?->contact_number }}
                                    </div>
                                </td>

                                <td class="px-5 py-4">
                                    <div class="font-medium text-on-surface">{{ $order->buyer?->full_name }}</div>
                                    <div class="text-[11px] text-on-surface-variant mt-0.5">
                                        {{ $order->address?->municipality_name }}, {{ $order->address?->province_name }}
                                    </div>
                                </td>

                                <td class="px-5 py-4">
                                    <div class="font-medium text-on-surface">
                                        {{ $order->items->count() }} item(s) · ₱{{ number_format($order->total, 2) }}
                                    </div>
                                    <div class="text-[11px] text-on-surface-variant mt-0.5 truncate max-w-[180px]">
                                        {{ $order->items->first()?->product_name }}
                                        @if ($order->items->count() > 1)
                                            +{{ $order->items->count() - 1 }} more
                                        @endif
                                    </div>
                                </td>

                                <td class="px-5 py-4">
                                    @if ($order->pickupCourier)
                                        <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-blue-500/10 text-blue-700 font-medium text-xs">
                                            <span class="material-symbols-outlined text-[14px]">two_wheeler</span>
                                            <span>{{ $order->pickupCourier->full_name }}</span>
                                        </div>
                                        <div class="text-[10px] text-on-surface-variant mt-1">
                                            {{ $order->pickupCourier->courierProfile?->plate_number ?? 'Motorcycle' }}
                                        </div>
                                    @else
                                        <span class="text-on-surface-variant italic text-xs">Unassigned</span>
                                    @endif
                                </td>

                                <td class="px-5 py-4">
                                    @if ($order->status === 'ready_for_pickup')
                                        @if ($order->pickup_courier_id)
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-blue-500/10 text-blue-700 text-xs font-medium">
                                                <span class="w-1.5 h-1.5 rounded-full bg-blue-600"></span>
                                                Rider Dispatched
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-amber-500/10 text-amber-700 text-xs font-medium">
                                                <span class="w-1.5 h-1.5 rounded-full bg-amber-600"></span>
                                                Ready for Pickup
                                            </span>
                                        @endif
                                    @elseif ($order->status === 'picked_up')
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-500/10 text-emerald-700 text-xs font-medium">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>
                                            Picked Up
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-surface-container-high text-on-surface text-xs font-medium">
                                            {{ ucwords(str_replace('_', ' ', $order->status)) }}
                                        </span>
                                    @endif
                                </td>

                                <td class="px-5 py-4 text-right whitespace-nowrap">
                                    <div class="inline-flex items-center gap-2">
                                        @if ($order->status === 'ready_for_pickup')
                                            {{-- Dispatch rider button --}}
                                            <button
                                                type="button"
                                                @click="activeOrder = {{ $order->toJson() }}; actionUrl = '{{ route('logistics.pickups.schedule', $order) }}'; scheduleModal = true;"
                                                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-primary text-on-primary font-label-md text-xs font-semibold hover:bg-primary/90 transition-all cursor-pointer">
                                                <span class="material-symbols-outlined text-[15px]">two_wheeler</span>
                                                <span>{{ $order->pickup_courier_id ? 'Change Rider' : 'Assign Rider' }}</span>
                                            </button>

                                            {{-- Confirm physical collection --}}
                                            <form method="POST" action="{{ route('logistics.pickups.confirm', $order) }}" class="inline" onsubmit="return confirm('Confirm that parcel {{ $order->order_number }} has been physically collected from the seller?');">
                                                @csrf
                                                <button
                                                    type="submit"
                                                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-emerald-600 text-white font-label-md text-xs font-semibold hover:bg-emerald-700 transition-all cursor-pointer">
                                                    <span class="material-symbols-outlined text-[15px]">check</span>
                                                    <span>Confirm Pickup</span>
                                                </button>
                                            </form>
                                        @elseif ($order->status === 'picked_up')
                                            <a
                                                href="{{ route('logistics.incoming.index', ['q' => $order->tracking_number ?? $order->order_number]) }}"
                                                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-secondary text-on-secondary font-label-md text-xs font-semibold hover:bg-secondary/90 transition-all">
                                                <span class="material-symbols-outlined text-[15px]">qr_code_scanner</span>
                                                <span>Receive at Hub</span>
                                            </a>
                                        @endif

                                        <a
                                            href="{{ route('logistics.pickups.show', $order) }}"
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-surface-container-high text-on-surface hover:bg-surface-container-highest transition-colors">
                                            <span class="material-symbols-outlined text-[18px]">chevron_right</span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-12 text-center">
                                    <div class="flex flex-col items-center justify-center">
                                        <span class="w-12 h-12 rounded-full bg-surface-container-high text-on-surface-variant flex items-center justify-center mb-3">
                                            <span class="material-symbols-outlined text-[24px]">hail</span>
                                        </span>
                                        <p class="font-headline-sm text-sm font-semibold text-on-surface">No pickup requests found</p>
                                        <p class="font-body-sm text-xs text-on-surface-variant mt-1">There are no seller pickup requests matching the current filter.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($orders->hasPages())
                <div class="px-5 py-4 border-t border-outline-variant">
                    {{ $orders->links() }}
                </div>
            @endif
                    </div>
        </div>
    </div>
</div>


    {{-- Schedule / Assign Pickup Rider Modal --}}
    <div
        x-show="scheduleModal"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
        @keydown.escape.window="scheduleModal = false">
        <div class="bg-surface-container-lowest rounded-2xl max-w-md w-full p-6 shadow-xl border border-outline-variant" @click.outside="scheduleModal = false">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary text-[22px]">two_wheeler</span>
                    <h3 class="font-headline-sm text-base font-bold text-on-surface">Assign Pickup Rider</h3>
                </div>
                <button @click="scheduleModal = false" class="text-on-surface-variant hover:text-on-surface">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>

            <p class="font-body-sm text-xs text-on-surface-variant mb-4">
                Select an approved courier to dispatch for parcel collection from the seller's pickup location.
            </p>

            <form :action="actionUrl" method="POST">
                @csrf
                <div class="mb-4">
                    <label class="block font-label-md text-xs font-semibold text-on-surface mb-1">Select Rider</label>
                    <select name="courier_id" required class="w-full px-3 py-2 bg-surface-container-high border-none rounded-xl font-body-sm text-xs text-on-surface focus:ring-2 focus:ring-primary">
                        <option value="">-- Choose Courier --</option>
                        @foreach ($couriers as $courier)
                            <option value="{{ $courier->id }}">
                                {{ $courier->full_name }} ({{ $courier->courierProfile?->vehicle_type ?? 'Motorcycle' }} - {{ $courier->courierProfile?->plate_number ?? 'No Plate' }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-5">
                    <label class="block font-label-md text-xs font-semibold text-on-surface mb-1">Instructions / Notes (Optional)</label>
                    <textarea name="scheduled_notes" rows="2" placeholder="e.g. Pickup before 3 PM, gate code..." class="w-full px-3 py-2 bg-surface-container-high border-none rounded-xl font-body-sm text-xs text-on-surface focus:ring-2 focus:ring-primary"></textarea>
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" @click="scheduleModal = false" class="px-4 py-2 rounded-xl bg-surface-container-high text-on-surface font-label-md text-xs font-semibold hover:bg-surface-container-highest transition-colors">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-primary text-on-primary font-label-md text-xs font-semibold hover:bg-primary/90 transition-all">
                        Dispatch Rider
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
