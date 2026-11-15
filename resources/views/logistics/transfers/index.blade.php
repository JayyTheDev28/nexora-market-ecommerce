@extends('layouts.app')

@section('title', 'Hub Transfers & Cross-Docking — Nexora Logistics')

@section('content')
<div
    x-data="{
        modalOpen: false,
        selectedOrderId: '',
        senderHubId: '',
        receiverHubId: '',
        notes: '',
        stagedOrders: @js($awaitingDispatch->map(fn($o) => [
            'id' => (string) $o->id,
            'order_number' => $o->order_number,
            'status' => str_replace('_', ' ', $o->status),
            'destination' => $o->shipping_barangay ? ($o->shipping_barangay . ', ' . $o->shipping_city) : ($o->shipping_city ?? 'Destination'),
            'current_hub_id' => (string) ($o->current_hub_id ?: $o->origin_hub_id),
            'current_hub_name' => $o->currentHub?->name ?? ($o->originHub?->name ?? 'Origin Hub'),
            'destination_hub_id' => (string) ($o->destination_hub_id ?? ''),
            'destination_hub_name' => $o->destinationHub?->name ?? 'Destination Hub',
            'is_internal' => $o->isInternalTransfer(),
            'partner_name' => $o->destinationHub?->logisticsCompany?->full_name,
        ])),
        openModal(orderId = null) {
            if (orderId) {
                this.selectedOrderId = String(orderId);
                const order = this.stagedOrders.find(o => String(o.id) === String(orderId));
                if (order) {
                    this.senderHubId = order.current_hub_id || '';
                    this.receiverHubId = (order.destination_hub_id && String(order.destination_hub_id) !== String(this.senderHubId))
                        ? order.destination_hub_id
                        : '';
                }
            } else if (this.stagedOrders.length > 0) {
                this.selectedOrderId = String(this.stagedOrders[0].id);
                this.senderHubId = this.stagedOrders[0].current_hub_id || '';
                this.receiverHubId = (this.stagedOrders[0].destination_hub_id && String(this.stagedOrders[0].destination_hub_id) !== String(this.senderHubId))
                    ? this.stagedOrders[0].destination_hub_id
                    : '';
            }
            this.modalOpen = true;
        },
        onOrderChange() {
            const order = this.stagedOrders.find(o => String(o.id) === String(this.selectedOrderId));
            if (order) {
                if (order.current_hub_id) this.senderHubId = order.current_hub_id;
                this.receiverHubId = (order.destination_hub_id && String(order.destination_hub_id) !== String(this.senderHubId))
                    ? order.destination_hub_id
                    : '';
            }
        },
        get selectedOrder() {
            return this.stagedOrders.find(o => String(o.id) === String(this.selectedOrderId)) || null;
        },
        get formAction() {
            return this.selectedOrderId ? ('/logistics/transfers/' + this.selectedOrderId + '/dispatch') : '';
        }
    }"
    class="w-full min-h-screen bg-surface">

    <div class="flex flex-col lg:flex-row w-full">
        <x-logistics-nav active="transfers" />

        <div class="flex-1 min-w-0 p-4 sm:p-6 lg:p-8 bg-surface">
            <div class="w-full max-w-7xl mx-auto space-y-6">

                {{-- Header with Primary Action --}}
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2 text-primary font-bold text-xs uppercase tracking-wider mb-1">
                            <span class="material-symbols-outlined text-[18px]">sync_alt</span>
                            <span>Linehaul & Inter-Hub Network</span>
                        </div>
                        <h1 class="font-headline-lg text-2xl md:text-3xl font-bold text-on-surface">Hub Transfers & Cross-Docking</h1>
                        <p class="font-body-md text-xs sm:text-sm text-on-surface-variant mt-1">
                            Manage internal facility transfers and cross-docking handoffs between logistics partners.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 shrink-0">
                        <button
                            type="button"
                            @click="$dispatch('open-manifest-modal')"
                            class="px-4 py-2.5 rounded-2xl bg-indigo-600 text-white text-xs font-semibold hover:bg-indigo-700 shadow-xs flex items-center gap-2 transition-all cursor-pointer">
                            <span class="material-symbols-outlined text-[18px]">assignment</span>
                            <span>Create Transfer Manifest</span>
                            @if (($eligibleForManifest ?? collect())->isNotEmpty())
                                <span class="px-2 py-0.5 rounded-full bg-white/20 text-white text-[10px] font-bold">
                                    {{ $eligibleForManifest->count() }}
                                </span>
                            @endif
                        </button>
                        @if ($awaitingDispatch->isNotEmpty())
                            <button
                                type="button"
                                @click="openModal()"
                                class="px-4 py-2.5 rounded-2xl bg-primary text-on-primary text-xs font-semibold hover:bg-primary/90 shadow-xs flex items-center gap-2 transition-all cursor-pointer">
                                <span class="material-symbols-outlined text-[18px]">local_shipping</span>
                                <span>Single Parcel Dispatch</span>
                                <span class="px-2 py-0.5 rounded-full bg-white/20 text-white text-[10px] font-bold">
                                    {{ $awaitingDispatch->count() }}
                                </span>
                            </button>
                        @endif
                    </div>
                </div>

                {{-- Alerts --}}
                @if (session('status'))
                    <div class="bg-primary/10 border border-primary/30 rounded-2xl p-4 flex items-center gap-3">
                        <span class="material-symbols-outlined text-primary text-[20px]">check_circle</span>
                        <p class="font-body-sm text-xs md:text-sm text-primary font-medium">{{ session('status') }}</p>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="bg-rose-500/10 border border-rose-500/20 rounded-2xl p-4">
                        <p class="font-label-md text-xs font-bold text-rose-700">Please correct the following errors:</p>
                        <ul class="list-disc list-inside text-xs text-rose-600 mt-1 space-y-0.5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Transfer Manifests Table --}}
                @if (($manifests ?? collect())->isNotEmpty())
                    <div class="bg-surface-container-lowest border border-outline-variant rounded-2xl overflow-hidden shadow-xs">
                        <div class="px-5 py-4 border-b border-outline-variant flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-indigo-600 text-[20px]">assignment</span>
                                <h2 class="text-xs font-bold text-on-surface uppercase tracking-wider">Transfer Manifests ({{ $manifests->count() }})</h2>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-surface-container-low text-on-surface-variant uppercase text-[11px]">
                                    <tr>
                                        <th class="px-4 py-3">Manifest #</th>
                                        <th class="px-4 py-3">Route</th>
                                        <th class="px-4 py-3">Courier</th>
                                        <th class="px-4 py-3">Parcels</th>
                                        <th class="px-4 py-3">Status</th>
                                        <th class="px-4 py-3 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-outline-variant/60">
                                    @foreach ($manifests as $manifest)
                                        <tr class="hover:bg-surface-container-low/40">
                                            <td class="px-4 py-3 font-mono font-bold text-on-surface">{{ $manifest->manifest_number }}</td>
                                            <td class="px-4 py-3">
                                                <div class="font-semibold text-on-surface">{{ $manifest->originHub?->name }} → {{ $manifest->destinationHub?->name }}</div>
                                                <div class="text-[11px] text-on-surface-variant">{{ $manifest->is_internal ? 'Internal' : 'Inter-Company' }}</div>
                                            </td>
                                            <td class="px-4 py-3 text-on-surface">
                                                {{ $manifest->assignedCourier?->full_name ?? 'Unassigned' }}
                                                @if ($manifest->vehicle_plate_number)
                                                    <div class="text-[10px] text-on-surface-variant">Plate: {{ $manifest->vehicle_plate_number }}</div>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 font-semibold">{{ $manifest->total_parcels }}</td>
                                            <td class="px-4 py-3">
                                                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold uppercase bg-primary/10 text-primary">
                                                    {{ str_replace('_', ' ', $manifest->status) }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-right">
                                                <a href="{{ route('logistics.transfers.manifests.show', $manifest) }}" class="px-3 py-1.5 rounded-full bg-surface-container-high text-on-surface text-[11px] font-semibold hover:bg-primary hover:text-on-primary transition-colors">
                                                    Manage / Verify
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                {{-- Staged Parcels Strip (Clean & Uncluttered) --}}
                @if ($awaitingDispatch->isNotEmpty())
                    <div class="bg-surface-container-lowest border border-outline-variant rounded-2xl p-4 sm:p-5 shadow-xs">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-outline-variant/60">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-amber-600 text-[20px]">swap_horizontal_circle</span>
                                <h2 class="text-xs font-bold text-on-surface uppercase tracking-wider">
                                    Parcels Staged for Hub Dispatch ({{ $awaitingDispatch->count() }})
                                </h2>
                            </div>
                            <span class="text-[11px] text-on-surface-variant">Click any parcel below to configure origin and destination hub</span>
                        </div>

                        <div class="divide-y divide-outline-variant/40 mt-1">
                            @foreach ($awaitingDispatch as $order)
                                <div class="py-3 flex flex-col md:flex-row md:items-center justify-between gap-3 hover:bg-surface-container-low/30 px-2 rounded-xl transition-colors">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="font-mono text-xs font-bold text-primary">#{{ $order->order_number }}</span>
                                            <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-surface-container text-on-surface-variant uppercase">
                                                {{ str_replace('_', ' ', $order->status) }}
                                            </span>
                                            @if ($order->isInternalTransfer())
                                                <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-blue-500/10 text-blue-700 border border-blue-500/20">
                                                    Internal Move
                                                </span>
                                            @else
                                                <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-500/10 text-amber-700 border border-amber-500/20">
                                                    Inter-Company Linehaul
                                                </span>
                                            @endif
                                        </div>
                                        <div class="text-[11px] text-on-surface-variant mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                            <span>Destination: <strong class="text-on-surface">{{ $order->shipping_city }}, {{ $order->shipping_province }}</strong></span>
                                            <span class="text-outline-variant">•</span>
                                            <span>Route: <strong>{{ $order->currentHub?->name ?? ($order->originHub?->name ?? 'Origin Hub') }}</strong> → <strong>{{ $order->destinationHub?->name ?? 'Destination Hub' }}</strong></span>
                                        </div>
                                    </div>

                                    <button
                                        type="button"
                                        @click="openModal({{ $order->id }})"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-primary text-on-primary font-semibold text-xs hover:bg-primary/90 transition-all cursor-pointer shadow-2xs shrink-0 self-start md:self-auto">
                                        <span class="material-symbols-outlined text-[15px]">local_shipping</span>
                                        <span>Pick & Dispatch</span>
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Transfers Management Table Card --}}
                <div class="bg-surface-container-lowest border border-outline-variant rounded-2xl p-5 shadow-xs">
                    {{-- Tabs & Search Toolbar --}}
                    <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-4 border-b border-outline-variant/60 pb-4">
                        {{-- Tabs --}}
                        <div class="flex items-center gap-1.5 overflow-x-auto bg-surface-container p-1 rounded-2xl border border-outline-variant/60">
                            <a href="{{ route('logistics.transfers.index', ['tab' => 'incoming']) }}"
                               class="px-3 py-1.5 rounded-xl text-xs font-semibold transition-all flex items-center gap-2 shrink-0 {{ $tab === 'incoming' ? 'bg-surface-container-lowest text-primary shadow-xs' : 'text-on-surface-variant hover:text-on-surface' }}">
                                <span>Incoming Transfers</span>
                                <span class="px-1.5 py-0.2 rounded-full text-[10px] font-bold {{ $tab === 'incoming' ? 'bg-primary/10 text-primary' : 'bg-surface-container-highest text-on-surface-variant' }}">
                                    {{ $counts['incoming'] }}
                                </span>
                            </a>

                            <a href="{{ route('logistics.transfers.index', ['tab' => 'in_transit']) }}"
                               class="px-3 py-1.5 rounded-xl text-xs font-semibold transition-all flex items-center gap-2 shrink-0 {{ $tab === 'in_transit' ? 'bg-surface-container-lowest text-primary shadow-xs' : 'text-on-surface-variant hover:text-on-surface' }}">
                                <span>Outgoing in Transit</span>
                                <span class="px-1.5 py-0.2 rounded-full text-[10px] font-bold {{ $tab === 'in_transit' ? 'bg-primary/10 text-primary' : 'bg-surface-container-highest text-on-surface-variant' }}">
                                    {{ $counts['in_transit'] }}
                                </span>
                            </a>

                            <a href="{{ route('logistics.transfers.index', ['tab' => 'internal']) }}"
                               class="px-3 py-1.5 rounded-xl text-xs font-semibold transition-all flex items-center gap-2 shrink-0 {{ $tab === 'internal' ? 'bg-surface-container-lowest text-primary shadow-xs' : 'text-on-surface-variant hover:text-on-surface' }}">
                                <span>Internal Moves</span>
                                <span class="px-1.5 py-0.2 rounded-full text-[10px] font-bold {{ $tab === 'internal' ? 'bg-primary/10 text-primary' : 'bg-surface-container-highest text-on-surface-variant' }}">
                                    {{ $counts['internal'] }}
                                </span>
                            </a>

                            <a href="{{ route('logistics.transfers.index', ['tab' => 'completed']) }}"
                               class="px-3 py-1.5 rounded-xl text-xs font-semibold transition-all flex items-center gap-2 shrink-0 {{ $tab === 'completed' ? 'bg-surface-container-lowest text-primary shadow-xs' : 'text-on-surface-variant hover:text-on-surface' }}">
                                <span>History</span>
                                <span class="px-1.5 py-0.2 rounded-full text-[10px] font-bold {{ $tab === 'completed' ? 'bg-primary/10 text-primary' : 'bg-surface-container-highest text-on-surface-variant' }}">
                                    {{ $counts['completed'] }}
                                </span>
                            </a>
                        </div>

                        {{-- Search --}}
                        <form method="GET" action="{{ route('logistics.transfers.index') }}" class="flex items-center gap-2">
                            <input type="hidden" name="tab" value="{{ $tab }}">
                            <div class="relative w-full sm:w-64">
                                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-[18px]">search</span>
                                <input type="text" name="q" value="{{ $search }}" placeholder="Search tracking #, order..."
                                       class="w-full pl-9 pr-3 py-1.5 rounded-xl border border-outline-variant bg-surface text-xs text-on-surface focus:outline-none focus:border-primary">
                            </div>
                            @if ($search)
                                <a href="{{ route('logistics.transfers.index', ['tab' => $tab]) }}" class="text-xs text-on-surface-variant hover:text-on-surface shrink-0">Clear</a>
                            @endif
                        </form>
                    </div>

                    {{-- Transfers Table --}}
                    <div class="mt-4 overflow-x-auto">
                        @if ($transfers->isEmpty())
                            <div class="py-12 text-center text-xs text-on-surface-variant">
                                <div class="w-10 h-10 rounded-full bg-surface-container-low text-on-surface-variant/70 mx-auto flex items-center justify-center mb-2">
                                    <span class="material-symbols-outlined text-[22px]">move_item</span>
                                </div>
                                <p class="font-semibold text-on-surface">No transfers found in this view</p>
                                <p class="text-[11px] text-on-surface-variant mt-0.5">Parcels will appear here as they are dispatched or staged.</p>
                            </div>
                        @else
                            <table class="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr class="border-b border-outline-variant text-[11px] font-bold text-on-surface-variant uppercase tracking-wider bg-surface-container-low/20">
                                        <th class="py-3 px-3">Transfer / Order</th>
                                        <th class="py-3 px-3">Transit Route</th>
                                        <th class="py-3 px-3">Timeline</th>
                                        <th class="py-3 px-3">Custody</th>
                                        <th class="py-3 px-3 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-outline-variant/60">
                                    @foreach ($transfers as $t)
                                        <tr class="hover:bg-surface-container-low/30 transition-colors">
                                            <td class="py-3.5 px-3">
                                                <p class="font-mono font-bold text-primary">{{ $t->tracking_number }}</p>
                                                <p class="text-[11px] text-on-surface-variant mt-0.5">Order #{{ $t->order?->order_number }}</p>
                                                <span class="inline-block mt-1 px-2 py-0.5 rounded text-[10px] font-semibold {{ $t->is_internal ? 'bg-blue-500/10 text-blue-700' : 'bg-amber-500/10 text-amber-700' }}">
                                                    {{ $t->is_internal ? 'Internal' : 'Inter-Company' }}
                                                </span>
                                            </td>

                                            <td class="py-3.5 px-3">
                                                <div class="flex items-center gap-1.5 text-xs font-semibold text-on-surface">
                                                    <span>{{ $t->senderHub?->name }}</span>
                                                    <span class="material-symbols-outlined text-[14px] text-primary">arrow_forward</span>
                                                    <span>{{ $t->receiverHub?->name }}</span>
                                                </div>
                                                <div class="text-[11px] text-on-surface-variant mt-0.5">
                                                    {{ $t->senderLogistics?->full_name }} → {{ $t->receiverLogistics?->full_name }}
                                                </div>
                                            </td>

                                            <td class="py-3.5 px-3">
                                                <p class="text-on-surface font-medium">{{ $t->dispatched_at?->format('M d, Y h:i A') }}</p>
                                                @if ($t->received_at)
                                                    <p class="text-[11px] text-emerald-700 font-semibold mt-0.5">Received: {{ $t->received_at->format('M d, Y h:i A') }}</p>
                                                @else
                                                    <p class="text-[11px] text-amber-700 font-medium mt-0.5">In Transit</p>
                                                @endif
                                            </td>

                                            <td class="py-3.5 px-3">
                                                @if ($t->is_internal)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-blue-500/10 text-blue-700">
                                                        Internal Facility
                                                    </span>
                                                @elseif ($t->status === 'received')
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/10 text-emerald-700">
                                                        With Receiver
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-500/10 text-amber-700">
                                                        With Sender
                                                    </span>
                                                @endif
                                            </td>

                                            <td class="py-3.5 px-3 text-right">
                                                @if (in_array($tab, ['incoming', 'internal']) && $t->status === 'in_transit' && (int)$t->receiver_logistics_id === (int)auth()->id())
                                                    <form method="POST" action="{{ route('logistics.transfers.confirm', $t) }}"
                                                          onsubmit="return confirm('Confirm receipt of parcel #{{ $t->order?->order_number }} at {{ $t->receiverHub?->name }}?');">
                                                        @csrf
                                                        <button type="submit" class="px-3 py-1.5 rounded-xl bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-700 shadow-2xs inline-flex items-center gap-1 cursor-pointer">
                                                            <span class="material-symbols-outlined text-[16px]">how_to_reg</span>
                                                            <span>Confirm Receipt</span>
                                                        </button>
                                                    </form>
                                                @else
                                                    <span class="text-[11px] text-on-surface-variant font-medium">
                                                        {{ ucfirst(str_replace('_', ' ', $t->status)) }}
                                                    </span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>

                            <div class="mt-4">
                                {{ $transfers->links() }}
                            </div>
                        @endif
                    </div>
                </div>

            </div>
        </div>
    </div>

    {{-- Dispatch Hub Transfer Modal --}}
    <div
        x-show="modalOpen"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto"
        role="dialog"
        aria-modal="true"
        @keydown.escape.window="modalOpen = false">

        {{-- Backdrop --}}
        <div
            x-show="modalOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @click="modalOpen = false"
            class="fixed inset-0 bg-black/50 backdrop-blur-xs"></div>

        {{-- Modal Dialog --}}
        <div class="flex min-h-full items-center justify-center p-4">
            <div
                x-show="modalOpen"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95 translate-y-2"
                x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                x-transition:leave-end="opacity-0 scale-95 translate-y-2"
                class="relative w-full max-w-lg bg-surface-container-lowest rounded-3xl border border-outline-variant shadow-2xl overflow-hidden">

                {{-- Modal Header --}}
                <div class="p-5 border-b border-outline-variant/60 bg-surface-container-low/30 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
                            <span class="material-symbols-outlined text-[20px]">local_shipping</span>
                        </div>
                        <div>
                            <h3 class="font-headline-sm text-sm font-bold text-on-surface">Dispatch Hub Transfer</h3>
                            <p class="text-[11px] text-on-surface-variant">Select parcel and configure linehaul routing</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="modalOpen = false"
                        class="w-8 h-8 rounded-lg text-on-surface-variant hover:text-on-surface hover:bg-surface-container flex items-center justify-center transition-colors cursor-pointer"
                        aria-label="Close modal">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                {{-- Modal Form --}}
                <form method="POST" :action="formAction" class="p-5 space-y-4">
                    @csrf

                    {{-- 1. Select Staged Parcel --}}
                    <div>
                        <label class="block text-xs font-bold text-on-surface mb-1">
                            Select Staged Parcel <span class="text-error">*</span>
                        </label>
                        <select
                            x-model="selectedOrderId"
                            @change="onOrderChange()"
                            required
                            class="w-full px-3 py-2.5 rounded-xl border border-outline-variant bg-surface text-xs text-on-surface focus:outline-none focus:border-primary">
                            <option value="">Choose a parcel to transfer...</option>
                            <template x-for="order in stagedOrders" :key="order.id">
                                <option
                                    :value="order.id"
                                    x-text="'#' + order.order_number + ' — ' + order.destination + ' (' + (order.is_internal ? 'Internal Move' : 'Partner Linehaul') + ')'">
                                </option>
                            </template>
                        </select>
                    </div>

                    {{-- Selected Order Summary Strip --}}
                    <template x-if="selectedOrder">
                        <div class="p-3 rounded-xl bg-surface-container-low border border-outline-variant/60 text-xs space-y-1">
                            <div class="flex items-center justify-between">
                                <span class="font-semibold text-on-surface">Target Delivery:</span>
                                <span class="font-medium text-on-surface-variant" x-text="selectedOrder.destination"></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="font-semibold text-on-surface">Transfer Category:</span>
                                <span
                                    class="px-2 py-0.5 rounded text-[10px] font-bold"
                                    :class="selectedOrder.is_internal ? 'bg-blue-500/10 text-blue-700' : 'bg-amber-500/10 text-amber-700'"
                                    x-text="selectedOrder.is_internal ? 'Company Internal' : 'Partner Logistics Handoff'">
                                </span>
                            </div>
                        </div>
                    </template>

                    {{-- 2. Sender / Origin Facility --}}
                    <div>
                        <label class="block text-xs font-bold text-on-surface mb-1">
                            Dispatching Facility (Origin) <span class="text-error">*</span>
                        </label>
                        <select
                            name="sender_hub_id"
                            x-model="senderHubId"
                            required
                            class="w-full px-3 py-2.5 rounded-xl border border-outline-variant bg-surface text-xs text-on-surface focus:outline-none focus:border-primary">
                            <option value="">Select dispatching facility...</option>
                            @foreach ($myHubs as $mHub)
                                <option value="{{ $mHub->id }}">
                                    {{ $mHub->name }} ({{ $mHub->city_name }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- 3. Receiver / Destination Facility --}}
                    <div>
                        <label class="block text-xs font-bold text-on-surface mb-1">
                            Receiving Facility (Destination) <span class="text-error">*</span>
                        </label>
                        <select
                            name="receiver_hub_id"
                            x-model="receiverHubId"
                            required
                            class="w-full px-3 py-2.5 rounded-xl border border-outline-variant bg-surface text-xs text-on-surface focus:outline-none focus:border-primary">
                            <option value="">Select receiving facility...</option>
                            <optgroup label="Internal Facilities (Your Company)">
                                @foreach ($myHubs as $mHub)
                                    <option value="{{ $mHub->id }}">
                                        {{ $mHub->name }} (Internal)
                                    </option>
                                @endforeach
                            </optgroup>
                            @if ($allDestinationHubs->where('logistics_company_id', '!=', auth()->id())->isNotEmpty())
                                <optgroup label="Partner Logistics Hubs">
                                    @foreach ($allDestinationHubs->where('logistics_company_id', '!=', auth()->id()) as $pHub)
                                        <option value="{{ $pHub->id }}">
                                            {{ $pHub->name }} ({{ $pHub->logisticsCompany?->full_name }})
                                        </option>
                                    @endforeach
                                </optgroup>
                            @endif
                        </select>
                    </div>

                    {{-- 4. Dispatch Notes --}}
                    <div>
                        <label class="block text-xs font-bold text-on-surface mb-1">
                            Linehaul Notes <span class="text-on-surface-variant font-normal">(Optional)</span>
                        </label>
                        <input
                            type="text"
                            name="notes"
                            x-model="notes"
                            maxlength="500"
                            placeholder="Vehicle plate #, driver name, or manifest code..."
                            class="w-full px-3 py-2 rounded-xl border border-outline-variant bg-surface text-xs text-on-surface focus:outline-none focus:border-primary">
                    </div>

                    {{-- Modal Footer --}}
                    <div class="pt-3 border-t border-outline-variant/60 flex items-center justify-end gap-2.5">
                        <button
                            type="button"
                            @click="modalOpen = false"
                            class="px-4 py-2 rounded-xl border border-outline-variant text-xs font-semibold text-on-surface-variant hover:bg-surface-container transition-colors cursor-pointer">
                            Cancel
                        </button>

                        <button
                            type="submit"
                            :disabled="!selectedOrderId || !senderHubId || !receiverHubId"
                            class="px-4 py-2 rounded-xl bg-primary text-on-primary text-xs font-semibold hover:bg-primary/90 transition-all flex items-center gap-1.5 shadow-xs cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed">
                            <span class="material-symbols-outlined text-[16px]">local_shipping</span>
                            <span>Confirm & Dispatch</span>
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>

    {{-- Create Transfer Manifest Modal (Multi-Parcel Workflow) --}}
    <div
        x-data="{
            manifestModalOpen: false,
            parcelSearch: '',
            selectedOrderIds: [],
            originHubId: '{{ $myHubs->first()?->id ?? '' }}',
            destinationHubId: '',
            assignedCourierId: '',
            vehiclePlateNumber: '',
            manifestNotes: '',
            eligibleParcels: @js(($eligibleForManifest ?? collect())->map(fn($o) => [
                'id' => (string) $o->id,
                'order_number' => $o->order_number,
                'tracking_number' => $o->tracking_number ?: $o->order_number,
                'destination' => $o->shipping_barangay ? ($o->shipping_barangay . ', ' . $o->shipping_city) : ($o->shipping_city ?? 'Destination'),
                'dest_hub' => $o->destinationHub?->name ?? 'Unrouted',
            ])->values()),
            get filteredParcels() {
                if (!this.parcelSearch) return this.eligibleParcels;
                const q = this.parcelSearch.toLowerCase();
                return this.eligibleParcels.filter(p => p.tracking_number.toLowerCase().includes(q) || p.order_number.toLowerCase().includes(q));
            }
        }"
        @open-manifest-modal.window="manifestModalOpen = true"
        x-show="manifestModalOpen"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.outside="manifestModalOpen = false" class="bg-surface-container-lowest border border-outline-variant rounded-3xl w-full max-w-2xl p-6 shadow-xl space-y-5">
            <div class="flex items-center justify-between border-b border-outline-variant pb-3">
                <div>
                    <h3 class="text-base font-bold text-on-surface">Create Transfer Manifest</h3>
                    <p class="text-xs text-on-surface-variant">Group eligible parcels into one trip. Parcels stay in origin hub until loaded &amp; departed.</p>
                </div>
                <button type="button" @click="manifestModalOpen = false" class="p-1.5 rounded-full hover:bg-surface-container text-on-surface-variant cursor-pointer">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>

            <form action="{{ route('logistics.transfers.manifests.store') }}" method="POST" class="space-y-4">
                @csrf

                {{-- Step 1: Origin & Destination --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-on-surface mb-1">1. Origin Hub <span class="text-rose-500">*</span></label>
                        <select name="origin_hub_id" x-model="originHubId" required class="w-full px-3 py-2 rounded-xl border border-outline-variant bg-surface text-xs">
                            @foreach ($myHubs as $mHub)
                                <option value="{{ $mHub->id }}">{{ $mHub->name }} ({{ $mHub->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-on-surface mb-1">Destination Hub <span class="text-rose-500">*</span></label>
                        <select name="destination_hub_id" x-model="destinationHubId" required class="w-full px-3 py-2 rounded-xl border border-outline-variant bg-surface text-xs">
                            <option value="">Select destination hub...</option>
                            @foreach ($allDestinationHubs as $dHub)
                                <option value="{{ $dHub->id }}">
                                    {{ $dHub->name }} ({{ (int) $dHub->logistics_company_id === (int) auth()->id() ? 'Internal' : $dHub->logisticsCompany?->full_name }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Step 2: Search & Multi-Select Eligible Parcels --}}
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="text-xs font-bold text-on-surface">2. Select Parcels (<span x-text="selectedOrderIds.length"></span> selected)</label>
                        <input type="text" x-model="parcelSearch" placeholder="Search tracking #..." class="px-3 py-1 rounded-full border border-outline-variant bg-surface text-xs w-48">
                    </div>
                    <div class="max-h-44 overflow-y-auto border border-outline-variant rounded-xl divide-y divide-outline-variant/50 bg-surface/50">
                        <template x-for="parcel in filteredParcels" :key="parcel.id">
                            <label class="flex items-center justify-between px-3 py-2 text-xs hover:bg-surface-container-low cursor-pointer">
                                <div class="flex items-center gap-2.5">
                                    <input type="checkbox" name="order_ids[]" :value="parcel.id" x-model="selectedOrderIds" class="rounded border-outline-variant text-primary">
                                    <div>
                                        <span class="font-mono font-bold text-on-surface" x-text="parcel.tracking_number"></span>
                                        <span class="text-on-surface-variant ml-1" x-text="'(#' + parcel.order_number + ')'"></span>
                                        <div class="text-[11px] text-on-surface-variant" x-text="parcel.destination + ' → ' + parcel.dest_hub"></div>
                                    </div>
                                </div>
                            </label>
                        </template>
                        <div x-show="filteredParcels.length === 0" class="p-4 text-center text-xs text-on-surface-variant">
                            No eligible parcels available at origin hub.
                        </div>
                    </div>
                </div>

                {{-- Step 3 & 4: Assign Transfer Courier & Optional Vehicle Plate --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-on-surface mb-1">3. Assign Transfer Courier (Optional)</label>
                        <select name="assigned_courier_id" x-model="assignedCourierId" class="w-full px-3 py-2 rounded-xl border border-outline-variant bg-surface text-xs">
                            <option value="">Leave as Draft (Assign later)</option>
                            @foreach (($transferCouriers ?? collect()) as $tc)
                                @php $hasActiveTrip = $tc->assignedManifests->isNotEmpty(); @endphp
                                <option value="{{ $tc->id }}" {{ $hasActiveTrip ? 'disabled' : '' }}>
                                    {{ $tc->full_name }} — {{ $hasActiveTrip ? 'Active Trip (' . $tc->assignedManifests->first()->manifest_number . ')' : 'Available' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-on-surface mb-1">4. Truck Plate Number (Optional)</label>
                        <input type="text" name="vehicle_plate_number" x-model="vehiclePlateNumber" placeholder="e.g. NXA 4512" class="w-full uppercase px-3 py-2 rounded-xl border border-outline-variant bg-surface text-xs">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-on-surface mb-1">Transport Notes (Optional)</label>
                    <input type="text" name="notes" x-model="manifestNotes" maxlength="500" placeholder="Special handling or dispatch notes..." class="w-full px-3 py-2 rounded-xl border border-outline-variant bg-surface text-xs">
                </div>

                <div class="pt-3 border-t border-outline-variant/60 flex items-center justify-end gap-2.5">
                    <button type="button" @click="manifestModalOpen = false" class="px-4 py-2 rounded-xl border border-outline-variant text-xs font-semibold text-on-surface-variant cursor-pointer">
                        Cancel
                    </button>
                    <button type="submit" :disabled="!originHubId || !destinationHubId || selectedOrderIds.length === 0" class="px-5 py-2 rounded-xl bg-indigo-600 text-white text-xs font-semibold hover:bg-indigo-700 disabled:opacity-40 cursor-pointer">
                        Create Manifest (<span x-text="selectedOrderIds.length"></span> parcels)
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
