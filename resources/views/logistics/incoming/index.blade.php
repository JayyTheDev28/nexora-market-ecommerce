@extends('layouts.app')

@section('title', 'Incoming Parcels & Receiving — Nexora Logistics')

@section('content')
<div class="w-full bg-surface py-8 min-h-[75vh]" x-data="{ flagModal: false, activeOrder: null, flagActionUrl: '' }">
    <div class="max-w-none px-margin-mobile md:px-margin-desktop">

        <x-logistics-nav active="incoming" />

        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="font-headline-lg text-2xl md:text-3xl font-bold text-on-surface">Incoming Parcel Receiving</h1>
                <p class="font-body-md text-sm text-on-surface-variant mt-1">Scan arriving parcels from couriers, verify delivery addresses, and check into sorting inventory.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('logistics.sorting.index') }}" class="inline-flex items-center gap-2 bg-primary text-on-primary px-4 py-2.5 rounded-full font-label-md text-xs font-semibold shadow-xs hover:bg-primary/90 transition-all">
                    <span class="material-symbols-outlined text-[18px]">sort</span>
                    <span>Go to Sorting Hub</span>
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

        {{-- Barcode / Fast Scanner Station Box --}}
        <div class="bg-gradient-to-r from-primary/10 via-surface-container-lowest to-secondary/10 rounded-3xl border border-primary/20 p-6 mb-8 shadow-xs">
            <div class="flex flex-col lg:flex-row items-center justify-between gap-6">
                <div class="max-w-md">
                    <div class="flex items-center gap-2 text-primary font-bold text-xs uppercase tracking-wider mb-1">
                        <span class="material-symbols-outlined text-[20px]">barcode_scanner</span>
                        <span>Rapid Scanner Station</span>
                    </div>
                    <h2 class="font-headline-md text-xl font-bold text-on-surface">Physical Parcel Check-in</h2>
                    <p class="font-body-sm text-xs text-on-surface-variant mt-1">
                        Connect a USB/Bluetooth barcode scanner, or manually enter the Tracking Number (e.g. <code class="font-mono text-primary font-bold">NX-TRK-...</code>) or Order Number.
                    </p>
                </div>

                <form method="POST" action="{{ route('logistics.incoming.scan') }}" class="w-full lg:w-auto flex-1 max-w-xl">
                    @csrf
                    <div class="flex flex-col sm:flex-row items-stretch gap-2">
                        <div class="relative flex-1">
                            <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-primary text-[20px]">qr_code_scanner</span>
                            <input
                                type="text"
                                name="identifier"
                                value="{{ old('identifier') }}"
                                autofocus
                                required
                                placeholder="Scan or enter Tracking # or NX-..."
                                class="w-full pl-11 pr-4 py-3 bg-surface-container-lowest border-2 border-primary/30 rounded-2xl font-mono text-sm text-on-surface placeholder:font-sans placeholder:text-xs placeholder:text-on-surface-variant/60 focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all">
                        </div>
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-2xl bg-primary text-on-primary font-label-md text-xs font-bold shadow-md hover:bg-primary/90 transition-all cursor-pointer whitespace-nowrap">
                            <span class="material-symbols-outlined text-[18px]">verified</span>
                            <span>Check In Parcel</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Stat Cards --}}
        <div class="grid grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
            <a href="{{ route('logistics.incoming.index', ['tab' => 'awaiting']) }}" class="bg-surface-container-lowest rounded-2xl border transition-all p-5 hover:border-primary {{ $tab === 'awaiting' ? 'border-primary ring-2 ring-primary/10' : 'border-outline-variant' }}">
                <div class="flex items-center justify-between">
                    <span class="font-body-sm text-xs font-medium text-on-surface-variant">Awaiting Check-in</span>
                    <span class="w-8 h-8 rounded-full bg-amber-500/10 text-amber-700 flex items-center justify-center">
                        <span class="material-symbols-outlined text-[18px]">local_shipping</span>
                    </span>
                </div>
                <div class="font-headline-md text-2xl font-bold text-on-surface mt-2">{{ number_format($counts['awaiting']) }}</div>
                <p class="font-body-xs text-xs text-on-surface-variant mt-1">Picked up from seller, en route</p>
            </a>

            <a href="{{ route('logistics.incoming.index', ['tab' => 'received']) }}" class="bg-surface-container-lowest rounded-2xl border transition-all p-5 hover:border-primary {{ $tab === 'received' ? 'border-primary ring-2 ring-primary/10' : 'border-outline-variant' }}">
                <div class="flex items-center justify-between">
                    <span class="font-body-sm text-xs font-medium text-on-surface-variant">Checked In at Hub</span>
                    <span class="w-8 h-8 rounded-full bg-emerald-500/10 text-emerald-700 flex items-center justify-center">
                        <span class="material-symbols-outlined text-[18px]">warehouse</span>
                    </span>
                </div>
                <div class="font-headline-md text-2xl font-bold text-on-surface mt-2">{{ number_format($counts['received']) }}</div>
                <p class="font-body-xs text-xs text-on-surface-variant mt-1">Received, ready for area sorting</p>
            </a>

            <a href="{{ route('logistics.incoming.index', ['tab' => 'all']) }}" class="bg-surface-container-lowest rounded-2xl border transition-all p-5 hover:border-primary {{ $tab === 'all' ? 'border-primary ring-2 ring-primary/10' : 'border-outline-variant' }}">
                <div class="flex items-center justify-between">
                    <span class="font-body-sm text-xs font-medium text-on-surface-variant">All Received & In-Transit</span>
                    <span class="w-8 h-8 rounded-full bg-surface-container-high text-on-surface flex items-center justify-center">
                        <span class="material-symbols-outlined text-[18px]">inventory</span>
                    </span>
                </div>
                <div class="font-headline-md text-2xl font-bold text-on-surface mt-2">{{ number_format($counts['all']) }}</div>
                <p class="font-body-xs text-xs text-on-surface-variant mt-1">Combined incoming pipeline</p>
            </a>
        </div>

        {{-- Filters & Search --}}
        <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4 mb-6">
            <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-1 overflow-x-auto w-full md:w-auto pb-1 md:pb-0">
                    <a href="{{ route('logistics.incoming.index', ['tab' => 'awaiting', 'q' => $search]) }}"
                        class="px-3.5 py-1.5 rounded-full font-label-md text-xs font-medium transition-colors whitespace-nowrap {{ $tab === 'awaiting' ? 'bg-primary text-on-primary' : 'bg-surface-container-high text-on-surface hover:bg-surface-container-highest' }}">
                        Awaiting Check-in ({{ $counts['awaiting'] }})
                    </a>
                    <a href="{{ route('logistics.incoming.index', ['tab' => 'received', 'q' => $search]) }}"
                        class="px-3.5 py-1.5 rounded-full font-label-md text-xs font-medium transition-colors whitespace-nowrap {{ $tab === 'received' ? 'bg-primary text-on-primary' : 'bg-surface-container-high text-on-surface hover:bg-surface-container-highest' }}">
                        Checked In at Hub ({{ $counts['received'] }})
                    </a>
                    <a href="{{ route('logistics.incoming.index', ['tab' => 'all', 'q' => $search]) }}"
                        class="px-3.5 py-1.5 rounded-full font-label-md text-xs font-medium transition-colors whitespace-nowrap {{ $tab === 'all' ? 'bg-primary text-on-primary' : 'bg-surface-container-high text-on-surface hover:bg-surface-container-highest' }}">
                        All ({{ $counts['all'] }})
                    </a>
                </div>

                <form method="GET" action="{{ route('logistics.incoming.index') }}" class="w-full md:w-80 flex items-center">
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <div class="relative w-full">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-[18px]">search</span>
                        <input
                            type="text"
                            name="q"
                            value="{{ $search }}"
                            placeholder="Filter by tracking, buyer, municipality..."
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
                            <th class="px-5 py-3.5">Matched Area</th>
                            <th class="px-5 py-3.5">Seller Origin</th>
                            <th class="px-5 py-3.5">Pickup Rider</th>
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
                                    <div class="text-[11px] text-on-surface-variant mt-0.5">
                                        {{ $parcel->address?->municipality_name ?? 'N/A' }}, {{ $parcel->address?->province_name ?? 'N/A' }}
                                    </div>
                                    <div class="text-[11px] text-on-surface-variant/70 truncate max-w-[200px]" title="{{ $parcel->address?->full_address }}">
                                        {{ $parcel->address?->full_address }}
                                    </div>
                                </td>

                                <td class="px-5 py-4">
                                    @if ($parcel->deliveryArea)
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-secondary/10 text-secondary font-semibold text-xs">
                                            <span class="material-symbols-outlined text-[14px]">near_me</span>
                                            <span>{{ $parcel->deliveryArea->name }}</span>
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-amber-500/10 text-amber-700 text-[11px] font-medium">
                                            <span class="material-symbols-outlined text-[13px]">warning</span>
                                            <span>Pending Sorting</span>
                                        </span>
                                    @endif
                                </td>

                                <td class="px-5 py-4">
                                    <div class="font-medium text-on-surface">
                                        {{ $parcel->primary_seller?->sellerProfile?->business_name ?? $parcel->primary_seller?->full_name ?? 'Seller' }}
                                    </div>
                                    <div class="text-[11px] text-on-surface-variant mt-0.5">
                                        {{ $parcel->seller_address?->municipality_name ?? 'Laguna' }}
                                    </div>
                                </td>

                                <td class="px-5 py-4">
                                    @if ($parcel->pickupCourier)
                                        <div class="font-medium text-on-surface">{{ $parcel->pickupCourier->full_name }}</div>
                                        <div class="text-[11px] text-on-surface-variant">{{ $parcel->pickupCourier->courierProfile?->plate_number ?? 'Rider' }}</div>
                                    @else
                                        <span class="text-on-surface-variant italic text-xs">Direct/Internal</span>
                                    @endif
                                </td>

                                <td class="px-5 py-4">
                                    @if ($parcel->status === 'picked_up')
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-amber-500/10 text-amber-700 text-xs font-medium">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-600"></span>
                                            En Route to Hub
                                        </span>
                                    @elseif ($parcel->status === 'at_sorting_center')
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-500/10 text-emerald-700 text-xs font-medium">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>
                                            At Sorting Hub
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-surface-container-high text-on-surface text-xs font-medium">
                                            {{ ucwords(str_replace('_', ' ', $parcel->status)) }}
                                        </span>
                                    @endif
                                </td>

                                <td class="px-5 py-4 text-right whitespace-nowrap">
                                    <div class="inline-flex items-center gap-2">
                                        @if ($parcel->status === 'picked_up')
                                            <form method="POST" action="{{ route('logistics.incoming.scan') }}" class="inline">
                                                @csrf
                                                <input type="hidden" name="identifier" value="{{ $parcel->tracking_number ?? $parcel->order_number }}">
                                                <button
                                                    type="submit"
                                                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-primary text-on-primary font-label-md text-xs font-semibold hover:bg-primary/90 transition-all cursor-pointer">
                                                    <span class="material-symbols-outlined text-[15px]">verified</span>
                                                    <span>Receive at Hub</span>
                                                </button>
                                            </form>
                                        @elseif ($parcel->status === 'at_sorting_center')
                                            <a
                                                href="{{ route('logistics.sorting.index', ['q' => $parcel->order_number]) }}"
                                                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-secondary text-on-secondary font-label-md text-xs font-semibold hover:bg-secondary/90 transition-all">
                                                <span class="material-symbols-outlined text-[15px]">sort</span>
                                                <span>Sort Parcel</span>
                                            </a>
                                        @endif

                                        <button
                                            type="button"
                                            @click="activeOrder = {{ $parcel->toJson() }}; flagActionUrl = '{{ route('logistics.incoming.flag', $parcel) }}'; flagModal = true;"
                                            title="Flag Issue / Damaged Parcel"
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-surface-container-high text-on-surface-variant hover:text-rose-600 hover:bg-rose-500/10 transition-colors">
                                            <span class="material-symbols-outlined text-[18px]">flag</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-12 text-center">
                                    <div class="flex flex-col items-center justify-center">
                                        <span class="w-12 h-12 rounded-full bg-surface-container-high text-on-surface-variant flex items-center justify-center mb-3">
                                            <span class="material-symbols-outlined text-[24px]">qr_code_scanner</span>
                                        </span>
                                        <p class="font-headline-sm text-sm font-semibold text-on-surface">No incoming parcels found</p>
                                        <p class="font-body-sm text-xs text-on-surface-variant mt-1">No parcels currently awaiting check-in with the selected filter.</p>
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

    {{-- Flag Issue Modal --}}
    <div
        x-show="flagModal"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
        @keydown.escape.window="flagModal = false">
        <div class="bg-surface-container-lowest rounded-2xl max-w-md w-full p-6 shadow-xl border border-outline-variant" @click.outside="flagModal = false">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2 text-rose-600">
                    <span class="material-symbols-outlined text-[22px]">report_problem</span>
                    <h3 class="font-headline-sm text-base font-bold">Flag Parcel Issue</h3>
                </div>
                <button @click="flagModal = false" class="text-on-surface-variant hover:text-on-surface">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>

            <p class="font-body-sm text-xs text-on-surface-variant mb-4">
                Record an audit note regarding packaging damage, address discrepancy, or receiving exception.
            </p>

            <form :action="flagActionUrl" method="POST">
                @csrf
                <div class="mb-5">
                    <label class="block font-label-md text-xs font-semibold text-on-surface mb-1">Issue Description</label>
                    <textarea name="reason" rows="3" required placeholder="Describe the condition, e.g. Box crushed, label illegible, address missing house number..." class="w-full px-3 py-2 bg-surface-container-high border-none rounded-xl font-body-sm text-xs text-on-surface focus:ring-2 focus:ring-primary"></textarea>
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" @click="flagModal = false" class="px-4 py-2 rounded-xl bg-surface-container-high text-on-surface font-label-md text-xs font-semibold hover:bg-surface-container-highest transition-colors">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-rose-600 text-white font-label-md text-xs font-semibold hover:bg-rose-700 transition-all">
                        Record Issue
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
