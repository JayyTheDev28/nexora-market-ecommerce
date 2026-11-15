@extends('layouts.app')

@section('title', 'Incoming Parcels & Receiving — Nexora Logistics')

@section('content')
<div class="w-full min-h-screen bg-surface" x-data="{ flagModal: false, activeOrder: null, flagActionUrl: '' }">
    <div class="flex flex-col lg:flex-row w-full">

            <x-logistics-nav active="incoming" />

            <div class="flex-1 min-w-0 p-4 sm:p-6 lg:p-8 bg-surface">
            <div class="w-full max-w-7xl mx-auto space-y-6">

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

        {{-- Scan Incoming Parcel Station Box --}}
        <div class="rounded-3xl border border-[#bfdbfe] bg-gradient-to-br from-[#eff6ff] via-white to-[#fff7ed]/60 p-6 mb-8 shadow-sm">
            {{-- Stacked Full-Width Header --}}
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-4 border-b border-[#e2e8f0]">
                <div>
                    <div class="inline-flex items-center gap-2 text-[#0058be] font-bold text-xs uppercase tracking-wider mb-1">
                        <span class="material-symbols-outlined text-[20px]">barcode_scanner</span>
                        <span>One-Scan Automatic Receiving</span>
                    </div>
                    <h2 class="font-headline-md text-xl font-bold text-[#0f172a]">Scan Incoming Parcel</h2>
                    <p class="font-body-sm text-xs text-[#475569] mt-1">
                        Point the camera at any waybill or scan via USB/manual entry (<code class="font-mono text-[#0058be] font-bold">NX-TRK-...</code>). Valid parcels are received automatically.
                    </p>
                </div>
                <div class="shrink-0">
                    <span class="inline-flex items-center gap-2 rounded-full border border-[#86efac] bg-[#f0fdf4] px-3.5 py-1.5 text-xs font-bold text-[#166534]">
                        <span class="h-2 w-2 rounded-full bg-[#16a34a]"></span>
                        <span>Parcels Received This Session: <strong id="incoming-session-count" class="font-mono text-sm">0</strong></span>
                    </span>
                </div>
            </div>

            {{-- Streamlined One-Scan Form (No separate Lookup or Confirm steps) --}}
            <form id="incoming-scan-form" method="POST" action="{{ route('logistics.incoming.scan') }}" class="mt-4 space-y-3">
                @csrf
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-3 items-end">
                    @if ($myHubs->isNotEmpty())
                        <div class="lg:col-span-4">
                            <label for="incoming-hub-select" class="block text-[11px] font-bold uppercase tracking-wider text-[#334155] mb-1.5">
                                Receiving Hub
                            </label>
                            <select
                                id="incoming-hub-select"
                                name="hub_id"
                                class="w-full px-3.5 py-3 bg-white border-2 border-[#cbd5e1] rounded-2xl text-sm font-semibold text-[#0f172a] focus:border-[#0058be] focus:ring-4 focus:ring-[#0058be]/10 transition-all"
                            >
                                <option value="">Auto-Detect Verified Hub</option>
                                @foreach ($myHubs as $hub)
                                    <option value="{{ $hub->id }}" {{ old('hub_id', request('hub_id')) == $hub->id ? 'selected' : '' }}>
                                        {{ $hub->name }} ({{ $hub->city_name }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="{{ $myHubs->isNotEmpty() ? 'lg:col-span-8' : 'lg:col-span-12' }}">
                        <label for="incoming-identifier-input" class="block text-[11px] font-bold uppercase tracking-wider text-[#334155] mb-1.5">
                            Waybill Tracking # or Order #
                        </label>
                        <div class="relative flex items-center">
                            <span class="material-symbols-outlined absolute left-4 text-[#0058be] text-[22px] pointer-events-none">qr_code_scanner</span>
                            <input
                                id="incoming-identifier-input"
                                type="text"
                                name="identifier"
                                value="{{ old('identifier') }}"
                                autofocus
                                required
                                autocomplete="off"
                                spellcheck="false"
                                placeholder="Scan or enter Tracking # (e.g. NX-TRK-...)"
                                class="w-full pl-12 pr-10 py-3 bg-white border-2 border-[#93c5fd] rounded-2xl font-mono text-base font-bold tracking-wide text-[#0f172a] placeholder:font-sans placeholder:text-sm placeholder:font-normal placeholder:text-[#94a3b8] focus:border-[#0058be] focus:ring-4 focus:ring-[#0058be]/10 transition-all"
                            >
                            <button
                                type="button"
                                id="incoming-clear-input-btn"
                                onclick="window.clearIncomingScannerInput()"
                                class="hidden absolute right-3 rounded-lg p-1 text-[#64748b] hover:bg-[#f1f5f9] hover:text-[#0f172a]"
                                title="Clear barcode input"
                            >
                                <span class="material-symbols-outlined text-[18px]">close</span>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Streamlined Action Row --}}
                <div class="flex flex-wrap items-center justify-between gap-3 pt-1">
                    <div class="flex flex-wrap items-center gap-2.5">
                        <button
                            id="incoming-camera-toggle-btn"
                            type="button"
                            onclick="window.toggleIncomingCamera()"
                            class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-[#0f172a] text-white font-label-md text-xs font-bold hover:bg-[#1e293b] transition-all cursor-pointer whitespace-nowrap shadow-xs"
                        >
                            <span class="material-symbols-outlined text-[18px]">photo_camera</span>
                            <span id="incoming-camera-toggle-label">Open Camera</span>
                        </button>

                        <button
                            id="incoming-manual-submit-btn"
                            type="submit"
                            class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-[#0058be] text-white font-label-md text-xs font-bold hover:bg-[#004494] transition-all cursor-pointer whitespace-nowrap shadow-xs"
                        >
                            <span class="material-symbols-outlined text-[18px]">check_circle</span>
                            <span>Receive Parcel</span>
                        </button>
                    </div>
                </div>
            </form>

            @include('logistics.components.camera_scanner_modal', [
                'scannerId' => 'incoming-camera',
                'title' => 'Incoming Parcel Auto-Scanner',
                'subtitle' => 'Point camera at any waybill — valid parcels are received automatically for continuous batch scanning.',
                'manualFocusInputId' => 'incoming-identifier-input',
            ])

            {{-- Live Result Notification Card --}}
            <div id="incoming-scan-feedback" class="hidden mt-4 rounded-2xl border p-4"></div>
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
                <div id="incoming-count-awaiting" class="font-headline-md text-2xl font-bold text-on-surface mt-2">{{ number_format($counts['awaiting']) }}</div>
                <p class="font-body-xs text-xs text-on-surface-variant mt-1">Picked up from seller, en route</p>
            </a>

            <a href="{{ route('logistics.incoming.index', ['tab' => 'received']) }}" class="bg-surface-container-lowest rounded-2xl border transition-all p-5 hover:border-primary {{ $tab === 'received' ? 'border-primary ring-2 ring-primary/10' : 'border-outline-variant' }}">
                <div class="flex items-center justify-between">
                    <span class="font-body-sm text-xs font-medium text-on-surface-variant">Checked In at Hub</span>
                    <span class="w-8 h-8 rounded-full bg-emerald-500/10 text-emerald-700 flex items-center justify-center">
                        <span class="material-symbols-outlined text-[18px]">warehouse</span>
                    </span>
                </div>
                <div id="incoming-count-received" class="font-headline-md text-2xl font-bold text-on-surface mt-2">{{ number_format($counts['received']) }}</div>
                <p class="font-body-xs text-xs text-on-surface-variant mt-1">Received, ready for area sorting</p>
            </a>

            <a href="{{ route('logistics.incoming.index', ['tab' => 'all']) }}" class="bg-surface-container-lowest rounded-2xl border transition-all p-5 hover:border-primary {{ $tab === 'all' ? 'border-primary ring-2 ring-primary/10' : 'border-outline-variant' }}">
                <div class="flex items-center justify-between">
                    <span class="font-body-sm text-xs font-medium text-on-surface-variant">All Received & In-Transit</span>
                    <span class="w-8 h-8 rounded-full bg-surface-container-high text-on-surface flex items-center justify-center">
                        <span class="material-symbols-outlined text-[18px]">inventory</span>
                    </span>
                </div>
                <div id="incoming-count-all" class="font-headline-md text-2xl font-bold text-on-surface mt-2">{{ number_format($counts['all']) }}</div>
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
                                    @elseif ($parcel->status === 'in_transit_hub')
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-indigo-500/10 text-indigo-700 text-xs font-medium border border-indigo-500/20">
                                            <span class="w-1.5 h-1.5 rounded-full bg-indigo-600 animate-pulse"></span>
                                            In Transit Between Hubs
                                        </span>
                                    @elseif (in_array($parcel->status, ['received_at_hub', 'at_sorting_center']))
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-500/10 text-emerald-700 text-xs font-medium">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>
                                            Received at Hub
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
                                        @elseif (in_array($parcel->status, ['at_sorting_center', 'received_at_hub']))
                                            @if ($parcel->requiresHubTransfer() && !empty($parcel->destination_hub_id) && (int)$parcel->current_hub_id !== (int)$parcel->destination_hub_id)
                                                <a
                                                    href="{{ route('logistics.transfers.index', ['q' => $parcel->order_number]) }}"
                                                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-primary text-on-primary font-label-md text-xs font-semibold hover:bg-primary/90 transition-all">
                                                    <span class="material-symbols-outlined text-[15px]">local_shipping</span>
                                                    <span>Dispatch Hub Transfer</span>
                                                </a>
                                            @else
                                                <a
                                                    href="{{ route('logistics.sorting.index', ['q' => $parcel->order_number]) }}"
                                                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-secondary text-on-secondary font-label-md text-xs font-semibold hover:bg-secondary/90 transition-all">
                                                    <span class="material-symbols-outlined text-[15px]">sort</span>
                                                    <span>Sort Parcel</span>
                                                </a>
                                            @endif
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

@push('scripts')
<script>
    (function () {
        const SCANNER_ID = 'incoming-camera';
        const form = document.getElementById('incoming-scan-form');
        const input = document.getElementById('incoming-identifier-input');
        const hubSelect = document.getElementById('incoming-hub-select');
        const clearBtn = document.getElementById('incoming-clear-input-btn');
        const toggleLabel = document.getElementById('incoming-camera-toggle-label');
        const feedbackBox = document.getElementById('incoming-scan-feedback');
        const sessionCountEl = document.getElementById('incoming-session-count');
        const countAwaitingEl = document.getElementById('incoming-count-awaiting');
        const countReceivedEl = document.getElementById('incoming-count-received');
        const countAllEl = document.getElementById('incoming-count-all');

        let sessionReceivedCount = 0;
        let scanRequestSeq = 0;

        function normalizeCode(raw) {
            if (window.NexoraCameraScanner && typeof window.NexoraCameraScanner.normalizeBarcode === 'function') {
                return window.NexoraCameraScanner.normalizeBarcode(raw);
            }
            return String(raw || '').trim().replace(/[\x00-\x1F\x7F\s]+/g, '').toUpperCase();
        }

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function currentHubValue() {
            return hubSelect ? String(hubSelect.value || '') : '';
        }

        function setScannerStatus(statusCode, message, decodedTracking) {
            if (window.NexoraCameraScanner && typeof window.NexoraCameraScanner.setStatus === 'function') {
                window.NexoraCameraScanner.setStatus(SCANNER_ID, statusCode, message, decodedTracking);
            }
        }

        function scheduleResume(delayMs) {
            if (window.NexoraCameraScanner && typeof window.NexoraCameraScanner.scheduleResumeScanning === 'function') {
                window.NexoraCameraScanner.scheduleResumeScanning(SCANNER_ID, delayMs);
            }
        }

        function playFeedback(kind) {
            if (window.NexoraCameraScanner && typeof window.NexoraCameraScanner.feedback === 'function') {
                window.NexoraCameraScanner.feedback(kind);
            }
        }

        function updatePipelineCounts(counts) {
            if (!counts) return;
            if (countAwaitingEl && typeof counts.awaiting === 'number') {
                countAwaitingEl.textContent = Number(counts.awaiting).toLocaleString();
            }
            if (countReceivedEl && typeof counts.received === 'number') {
                countReceivedEl.textContent = Number(counts.received).toLocaleString();
            }
            if (countAllEl && typeof counts.all === 'number') {
                countAllEl.textContent = Number(counts.all).toLocaleString();
            }
        }

        function syncClearButton() {
            if (clearBtn && input) {
                clearBtn.classList.toggle('hidden', !input.value.trim());
            }
        }

        window.clearIncomingScannerInput = function () {
            if (input) {
                input.value = '';
                input.focus();
            }
            syncClearButton();
        };

        window.toggleIncomingCamera = async function () {
            if (!window.NexoraCameraScanner) return;
            const isOpen = await window.NexoraCameraScanner.toggle(SCANNER_ID, function (code) {
                if (input) {
                    input.value = code;
                }
                syncClearButton();
                return window.receiveIncomingParcel(code, true);
            });
            if (toggleLabel) {
                toggleLabel.textContent = isOpen ? 'Close Camera' : 'Open Camera';
            }
        };

        window.receiveIncomingParcel = async function (overrideCode, fromAutoScan) {
            const rawValue = overrideCode !== undefined ? overrideCode : (input ? input.value : '');
            const identifier = normalizeCode(rawValue);
            if (input && identifier) {
                input.value = identifier;
            }
            syncClearButton();

            if (!identifier || !feedbackBox) {
                return;
            }

            const hubId = currentHubValue();
            const requestSeq = ++scanRequestSeq;

            setScannerStatus('RECEIVING', 'Processing parcel ' + identifier + '...', identifier);

            try {
                const response = await fetch('{{ route('logistics.incoming.scan') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    },
                    body: JSON.stringify({
                        identifier: identifier,
                        hub_id: hubId || null,
                        auto_scan: Boolean(fromAutoScan),
                    }),
                });

                if (requestSeq !== scanRequestSeq) {
                    return;
                }

                const payload = await response.json().catch(() => ({}));
                const outcome = payload.outcome || 'error';
                const msg = payload.message || 'Unable to process parcel receipt.';
                const d = payload.data || {};

                if (payload.counts) {
                    updatePipelineCounts(payload.counts);
                }

                if (response.ok && outcome === 'received') {
                    if (payload.newly_received) {
                        sessionReceivedCount += 1;
                        if (sessionCountEl) {
                            sessionCountEl.textContent = String(sessionReceivedCount);
                        }
                    }

                    playFeedback('success');
                    setScannerStatus('RECEIVED', 'PARCEL RECEIVED — ' + (d.tracking_number || identifier), d.tracking_number || identifier);

                    const hubName = d.resolved_hub ? d.resolved_hub.name : (d.resolved_hub_label || 'Verified Hub');
                    feedbackBox.classList.remove('hidden');
                    feedbackBox.className = 'mt-4 rounded-2xl border-2 border-[#86efac] bg-[#f0fdf4] p-4 text-xs text-[#166534] shadow-xs';
                    feedbackBox.innerHTML = `
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="space-y-1">
                                <div class="inline-flex items-center gap-1.5 font-extrabold text-sm text-[#15803d]">
                                    <span class="material-symbols-outlined text-[20px]">check_circle</span>
                                    <span>PARCEL RECEIVED</span>
                                </div>
                                <div class="font-mono text-sm font-bold text-[#0f172a]">
                                    Tracking: ${escapeHtml(d.tracking_number || identifier)}
                                    <span class="font-sans text-xs font-medium text-[#475569] ml-2">Order #${escapeHtml(d.order_number || '')}</span>
                                </div>
                                <div class="text-[#166534]">
                                    <strong>Received at:</strong> ${escapeHtml(hubName)} · <strong>Session Count:</strong> ${sessionReceivedCount}
                                </div>
                                ${d.route_info ? `<div class="text-[#334155]">${escapeHtml(d.route_info)}</div>` : ''}
                            </div>
                        </div>
                    `;

                    if (input && !fromAutoScan) {
                        input.value = '';
                        input.focus();
                        syncClearButton();
                    }
                    scheduleResume(1800);
                    return;
                }

                if (outcome === 'already_received') {
                    playFeedback('error');
                    setScannerStatus('ALREADY_RECEIVED', msg, d.tracking_number || identifier);
                    feedbackBox.classList.remove('hidden');
                    feedbackBox.className = 'mt-4 rounded-2xl border-2 border-[#fcd34d] bg-[#fffbeb] p-4 text-xs text-[#92400e]';
                    feedbackBox.innerHTML = `
                        <div class="font-extrabold text-sm">ALREADY RECEIVED</div>
                        <div class="mt-1 font-mono font-bold text-[#0f172a]">Tracking: ${escapeHtml(d.tracking_number || identifier)}</div>
                        <div class="mt-1">${escapeHtml(msg)}</div>
                    `;
                    scheduleResume(2600);
                    return;
                }

                // Rejected outcomes
                playFeedback('error');
                const statusMap = {
                    not_found: 'INVALID_BARCODE',
                    invalid_barcode: 'INVALID_BARCODE',
                    unauthorized_company: 'INVALID_BARCODE',
                    wrong_hub: 'WRONG_HUB',
                    invalid_hub: 'WRONG_HUB',
                    manifest_required: 'MANIFEST_REQUIRED',
                };
                setScannerStatus(statusMap[outcome] || 'ERROR', msg, identifier);

                const manifestBtn = payload.manifest && payload.manifest.url
                    ? '<div class="mt-2"><a href="' + escapeHtml(payload.manifest.url) + '" class="inline-flex items-center gap-1.5 rounded-xl bg-[#d97706] px-3.5 py-2 text-xs font-bold text-white hover:bg-[#b45309]">Open Manifest ' + escapeHtml(payload.manifest.manifest_number) + '</a></div>'
                    : '';

                feedbackBox.classList.remove('hidden');
                feedbackBox.className = 'mt-4 rounded-2xl border-2 border-[#fca5a5] bg-[#fef2f2] p-4 text-xs text-[#991b1b]';
                feedbackBox.innerHTML = `
                    <div class="font-extrabold text-sm">${escapeHtml(outcome === 'manifest_required' ? 'MANIFEST REQUIRED' : (outcome === 'wrong_hub' ? 'WRONG HUB' : 'SCAN REJECTED'))}</div>
                    <div class="mt-1">${escapeHtml(msg)}</div>
                    ${manifestBtn}
                `;
                scheduleResume(3000);
            } catch (err) {
                if (requestSeq !== scanRequestSeq) return;
                playFeedback('error');
                setScannerStatus('ERROR', 'Server connection error — please try again.', identifier);
                feedbackBox.classList.remove('hidden');
                feedbackBox.className = 'mt-4 rounded-2xl border-2 border-[#fca5a5] bg-[#fef2f2] p-4 text-xs text-[#991b1b]';
                feedbackBox.innerHTML = '<div class="font-extrabold text-sm">NETWORK ERROR</div><div class="mt-1">Unable to reach Laravel server. Check connection and retry.</div>';
                scheduleResume(3000);
            }
        };

        if (input) {
            syncClearButton();
            input.addEventListener('input', syncClearButton);
        }

        if (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                window.receiveIncomingParcel(input ? input.value : '', false);
            });
        }

        document.addEventListener('click', function (e) {
            if (e.target.closest('[data-camera-close="' + SCANNER_ID + '"], [data-camera-manual="' + SCANNER_ID + '"]')) {
                if (toggleLabel) toggleLabel.textContent = 'Open Camera';
            }
        });
    })();
</script>
@endpush
