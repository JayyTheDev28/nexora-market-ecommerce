@extends('layouts.app')

@section('title', 'Hub Transfers & Cross-Docking — Nexora Logistics')

@section('content')
<div
    x-data="{
        searchQuery: '',
        filterOriginHubId: '',
        filterDestHubId: '',
        filterCompanyId: '',
        filterRouteType: 'all',
        selectedIds: [],
        actionChoiceModalOpen: false,
        selectedExistingManifestId: '',
        eligibleParcels: @js(($eligibleForManifest ?? collect())->map(fn($o) => [
            'id' => (string) $o->id,
            'order_number' => $o->order_number,
            'tracking_number' => $o->tracking_number ?: $o->order_number,
            'status_label' => ucwords(str_replace('_', ' ', $o->status)),
            'destination' => $o->formatted_destination,
            'current_hub_id' => (string) ($o->current_hub_id ?? ''),
            'current_hub_name' => $o->currentHub?->name ?? 'Origin Hub',
            'destination_hub_id' => (string) ($o->destination_hub_id ?? ''),
            'destination_hub_name' => $o->destinationHub?->name ?? 'Destination Hub',
            'sender_logistics_id' => (string) ($o->currentHub?->logistics_company_id ?? ''),
            'receiver_logistics_id' => (string) ($o->destinationHub?->logistics_company_id ?? ''),
            'receiver_company_name' => $o->destinationHub?->logisticsCompany?->full_name ?? 'Internal',
            'is_internal' => $o->isInternalTransfer(),
        ])->values()),
        editableManifests: @js(($editableManifests ?? collect())->map(fn($m) => [
            'id' => (string) $m->id,
            'manifest_number' => $m->manifest_number,
            'status' => strtoupper(str_replace('_', ' ', $m->status)),
            'origin_hub_id' => (string) $m->origin_hub_id,
            'destination_hub_id' => (string) $m->destination_hub_id,
            'origin_hub_name' => $m->originHub?->name ?? '',
            'destination_hub_name' => $m->destinationHub?->name ?? '',
            'total_parcels' => $m->total_parcels,
            'courier_name' => $m->assignedCourier?->full_name ?? 'Unassigned',
        ])->values()),
        get filteredAvailableParcels() {
            return this.eligibleParcels.filter(p => {
                if (this.filterOriginHubId && p.current_hub_id !== String(this.filterOriginHubId)) return false;
                if (this.filterDestHubId && p.destination_hub_id !== String(this.filterDestHubId)) return false;
                if (this.filterCompanyId && p.receiver_logistics_id !== String(this.filterCompanyId)) return false;
                if (this.filterRouteType === 'internal' && !p.is_internal) return false;
                if (this.filterRouteType === 'inter_company' && p.is_internal) return false;
                if (this.searchQuery.trim() !== '') {
                    const q = this.searchQuery.trim().toLowerCase();
                    if (!p.tracking_number.toLowerCase().includes(q) && !p.order_number.toLowerCase().includes(q)) return false;
                }
                return true;
            });
        },
        get selectedParcelObjects() {
            return this.eligibleParcels.filter(p => this.selectedIds.includes(p.id));
        },
        get selectionCompatibilityError() {
            const list = this.selectedParcelObjects;
            if (list.length <= 1) return '';
            const first = list[0];
            for (const p of list) {
                if (p.current_hub_id !== first.current_hub_id || p.destination_hub_id !== first.destination_hub_id) {
                    return 'Selected parcels belong to different origin/destination hub routes. Please group compatible parcels into separate manifests.';
                }
                if (p.sender_logistics_id !== first.sender_logistics_id || p.receiver_logistics_id !== first.receiver_logistics_id) {
                    return 'Selected parcels belong to different logistics companies. Please group compatible parcels into separate manifests.';
                }
            }
            return '';
        },
        get compatibleExistingManifests() {
            const list = this.selectedParcelObjects;
            if (list.length === 0 || this.selectionCompatibilityError !== '') return [];
            const first = list[0];
            return this.editableManifests.filter(m =>
                m.origin_hub_id === first.current_hub_id &&
                m.destination_hub_id === first.destination_hub_id
            );
        },
        selectAllFiltered() {
            const ids = this.filteredAvailableParcels.map(p => p.id);
            this.selectedIds = Array.from(new Set([...this.selectedIds, ...ids]));
        },
        deselectAll() {
            this.selectedIds = [];
        },
        openActionChoiceModal() {
            if (this.selectedIds.length === 0 || this.selectionCompatibilityError !== '') return;
            const compat = this.compatibleExistingManifests;
            this.selectedExistingManifestId = compat.length > 0 ? compat[0].id : '';
            this.actionChoiceModalOpen = true;
        },
        proceedToCreateNewManifest() {
            const list = this.selectedParcelObjects;
            const first = list[0] || null;
            this.actionChoiceModalOpen = false;
            this.$dispatch('open-manifest-modal', {
                originHubId: first ? first.current_hub_id : '',
                destinationHubId: first ? first.destination_hub_id : '',
                orderIds: [...this.selectedIds],
            });
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
                                        <th class="px-4 py-3">Parcels (Loaded)</th>
                                        <th class="px-4 py-3">Status &amp; Timestamps</th>
                                        <th class="px-4 py-3 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-outline-variant/60">
                                    @foreach ($manifests as $manifest)
                                        @php $loadedCount = $manifest->items->where('loading_status', 'loaded')->count(); @endphp
                                        <tr class="hover:bg-surface-container-low/40">
                                            <td class="px-4 py-3">
                                                <div class="font-mono font-bold text-on-surface">{{ $manifest->manifest_number }}</div>
                                                <div class="text-[10px] text-on-surface-variant">{{ $manifest->created_at?->format('M d, Y H:i') }}</div>
                                            </td>
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
                                            <td class="px-4 py-3 font-semibold">
                                                {{ $manifest->total_parcels }}
                                                <span class="text-[11px] font-normal text-on-surface-variant">({{ $loadedCount }}/{{ $manifest->total_parcels }} loaded)</span>
                                            </td>
                                            <td class="px-4 py-3">
                                                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold uppercase bg-primary/10 text-primary">
                                                    {{ str_replace('_', ' ', $manifest->status) }}
                                                </span>
                                                @if ($manifest->dispatched_at)
                                                    <div class="text-[10px] text-on-surface-variant mt-1">Dep: {{ $manifest->dispatched_at->format('M d, H:i') }}</div>
                                                @endif
                                                @if ($manifest->arrived_at)
                                                    <div class="text-[10px] text-on-surface-variant">Arr: {{ $manifest->arrived_at->format('M d, H:i') }}</div>
                                                @endif
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

                {{-- Available Parcels for Transfer Section --}}
                <div class="bg-surface-container-lowest border border-outline-variant rounded-2xl p-4 sm:p-5 shadow-xs space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-outline-variant/60">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary text-[20px]">inventory_2</span>
                            <h2 class="text-xs font-bold text-on-surface uppercase tracking-wider">
                                AVAILABLE PARCELS FOR TRANSFER ({{ ($eligibleForManifest ?? collect())->count() }})
                            </h2>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <button type="button" @click="selectAllFiltered()" :disabled="filteredAvailableParcels.length === 0" class="px-3 py-1.5 rounded-xl border border-outline-variant text-[11px] font-semibold text-on-surface hover:bg-surface-container disabled:opacity-40 cursor-pointer">
                                Select All Eligible
                            </button>
                            <button type="button" @click="deselectAll()" :disabled="selectedIds.length === 0" class="px-3 py-1.5 rounded-xl border border-outline-variant text-[11px] font-semibold text-on-surface-variant hover:bg-surface-container disabled:opacity-40 cursor-pointer">
                                Deselect All
                            </button>
                        </div>
                    </div>

                    {{-- Search & Filters --}}
                    @if (($eligibleForManifest ?? collect())->isNotEmpty())
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-2.5">
                            <input type="text" x-model="searchQuery" placeholder="Search tracking or order #..." class="px-3 py-2 rounded-xl border border-outline-variant bg-surface text-xs text-on-surface focus:outline-none focus:border-primary">
                            <select x-model="filterOriginHubId" class="px-3 py-2 rounded-xl border border-outline-variant bg-surface text-xs text-on-surface">
                                <option value="">All Origin Hubs</option>
                                @foreach ($myHubs as $mHub)
                                    <option value="{{ $mHub->id }}">{{ $mHub->name }}</option>
                                @endforeach
                            </select>
                            <select x-model="filterDestHubId" class="px-3 py-2 rounded-xl border border-outline-variant bg-surface text-xs text-on-surface">
                                <option value="">All Destination Hubs</option>
                                @foreach ($allDestinationHubs as $dHub)
                                    <option value="{{ $dHub->id }}">{{ $dHub->name }}</option>
                                @endforeach
                            </select>
                            <select x-model="filterCompanyId" class="px-3 py-2 rounded-xl border border-outline-variant bg-surface text-xs text-on-surface">
                                <option value="">All Logistics Companies</option>
                                @foreach ($allDestinationHubs->pluck('logisticsCompany')->filter()->unique('id') as $comp)
                                    <option value="{{ $comp->id }}">{{ $comp->full_name }}</option>
                                @endforeach
                            </select>
                            <select x-model="filterRouteType" class="px-3 py-2 rounded-xl border border-outline-variant bg-surface text-xs text-on-surface">
                                <option value="all">All Route Types</option>
                                <option value="internal">Internal Move</option>
                                <option value="inter_company">Inter-Company Linehaul</option>
                            </select>
                        </div>

                        {{-- Multi-Select Sticky Action Bar --}}
                        <div x-show="selectedIds.length > 0" x-cloak class="sticky top-2 z-20 rounded-2xl border px-4 py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-sm"
                             :class="selectionCompatibilityError ? 'bg-rose-500/10 border-rose-500/30' : 'bg-primary/10 border-primary/30'">
                            <div class="space-y-0.5">
                                <div class="text-xs font-bold text-on-surface">
                                    <span x-text="selectedIds.length"></span> Parcel(s) Selected
                                </div>
                                <div x-show="selectionCompatibilityError" class="text-[11px] font-semibold text-rose-700" x-text="selectionCompatibilityError"></div>
                            </div>
                            <button
                                type="button"
                                @click="openActionChoiceModal()"
                                :disabled="selectionCompatibilityError !== ''"
                                class="px-4 py-2 rounded-xl bg-primary text-on-primary text-xs font-semibold hover:bg-primary/90 disabled:opacity-40 cursor-pointer shrink-0 flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-[16px]">assignment_add</span>
                                <span>Add Selected to Manifest</span>
                            </button>
                        </div>

                        {{-- Eligible Parcels List --}}
                        <div class="divide-y divide-outline-variant/40">
                            <template x-for="parcel in filteredAvailableParcels" :key="parcel.id">
                                <label class="py-3 flex items-start sm:items-center justify-between gap-3 hover:bg-surface-container-low/40 px-2.5 rounded-xl transition-colors cursor-pointer">
                                    <div class="flex items-start gap-3 min-w-0">
                                        <input type="checkbox" :value="parcel.id" x-model="selectedIds" class="mt-0.5 sm:mt-0 rounded border-outline-variant text-primary focus:ring-primary">
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <span class="font-mono text-xs font-bold text-primary" x-text="parcel.tracking_number"></span>
                                                <span class="text-[11px] text-on-surface-variant" x-text="'(#' + parcel.order_number + ')'"></span>
                                                <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-surface-container text-on-surface-variant uppercase" x-text="parcel.status_label"></span>
                                                <span class="px-2 py-0.5 rounded text-[10px] font-semibold border"
                                                      :class="parcel.is_internal ? 'bg-blue-500/10 text-blue-700 border-blue-500/20' : 'bg-amber-500/10 text-amber-700 border-amber-500/20'"
                                                      x-text="parcel.is_internal ? 'Internal Move' : 'Inter-Company Linehaul'"></span>
                                            </div>
                                            <div class="text-[11px] text-on-surface-variant mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                                <span>Destination: <strong class="text-on-surface" x-text="parcel.destination"></strong></span>
                                                <span class="text-outline-variant">•</span>
                                                <span>Route: <strong x-text="parcel.current_hub_name"></strong> → <strong x-text="parcel.destination_hub_name"></strong></span>
                                            </div>
                                        </div>
                                    </div>
                                </label>
                            </template>
                            <div x-show="filteredAvailableParcels.length === 0" class="py-6 text-center text-xs text-on-surface-variant">
                                No parcels match your current search or filter criteria.
                            </div>
                        </div>
                    @else
                        <div class="py-8 text-center space-y-1">
                            <span class="material-symbols-outlined text-[32px] text-on-surface-variant/50">check_circle</span>
                            <p class="text-xs font-bold text-on-surface">No Parcels Available for Transfer</p>
                            <p class="text-[11px] text-on-surface-variant">All eligible parcels have already been assigned to manifests, or there are currently no parcels requiring hub transfer.</p>
                        </div>
                    @endif

                    {{-- Routing Review Required Subsection --}}
                    @if (($routingReviewParcels ?? collect())->isNotEmpty())
                        <div class="mt-4 pt-4 border-t border-outline-variant/60 space-y-2">
                            <div class="flex items-center gap-2 text-amber-700 text-xs font-bold">
                                <span class="material-symbols-outlined text-[18px]">warning</span>
                                <span>Routing Review Required ({{ $routingReviewParcels->count() }})</span>
                            </div>
                            <div class="divide-y divide-outline-variant/40 bg-amber-500/5 border border-amber-500/20 rounded-xl px-3">
                                @foreach ($routingReviewParcels as $reviewOrder)
                                    <div class="py-2.5 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs">
                                        <div>
                                            <span class="font-mono font-bold text-on-surface">{{ $reviewOrder->tracking_number ?: $reviewOrder->order_number }}</span>
                                            <span class="ml-2 px-2 py-0.5 rounded bg-amber-500/15 text-amber-800 text-[10px] font-bold uppercase">Routing Review Required</span>
                                            <div class="text-[11px] text-on-surface-variant mt-0.5">
                                                Destination: <strong>{{ $reviewOrder->formatted_destination }}</strong> •
                                                Origin: <strong>{{ $reviewOrder->currentHub?->name ?? 'Missing' }}</strong> →
                                                Dest: <strong>{{ $reviewOrder->destinationHub?->name ?? 'Missing' }}</strong>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

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

    {{-- Option A / Option B Choice Modal for Selected Parcels --}}
    <div
        x-show="actionChoiceModalOpen"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.outside="actionChoiceModalOpen = false" class="bg-surface-container-lowest border border-outline-variant rounded-3xl w-full max-w-lg p-6 shadow-xl space-y-5">
            <div class="flex items-center justify-between border-b border-outline-variant pb-3">
                <div>
                    <h3 class="text-base font-bold text-on-surface">Add <span x-text="selectedIds.length"></span> Selected Parcel(s) to Manifest</h3>
                    <p class="text-xs text-on-surface-variant">Create a new manifest or append to an existing pre-dispatch draft manifest.</p>
                </div>
                <button type="button" @click="actionChoiceModalOpen = false" class="p-1.5 rounded-full hover:bg-surface-container text-on-surface-variant cursor-pointer">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>

            {{-- Option A: Create New Manifest --}}
            <div class="p-4 rounded-2xl border border-outline-variant bg-surface space-y-2">
                <div class="text-xs font-bold text-on-surface">Option A — Create New Manifest</div>
                <p class="text-[11px] text-on-surface-variant">Auto-fills origin &amp; destination hubs and preselects your <span x-text="selectedIds.length"></span> parcel(s).</p>
                <button type="button" @click="proceedToCreateNewManifest()" class="w-full py-2.5 rounded-xl bg-indigo-600 text-white text-xs font-semibold hover:bg-indigo-700 cursor-pointer">
                    Create New Manifest with Selected
                </button>
            </div>

            {{-- Option B: Add to Existing Draft Manifest --}}
            <div class="p-4 rounded-2xl border border-outline-variant bg-surface space-y-3">
                <div class="text-xs font-bold text-on-surface">Option B — Add to Existing Draft / Pre-Dispatch Manifest</div>
                <template x-if="compatibleExistingManifests.length > 0">
                    <form :action="'/logistics/transfers/manifests/' + selectedExistingManifestId + '/parcels'" method="POST" class="space-y-3">
                        @csrf
                        <template x-for="id in selectedIds" :key="'append-' + id">
                            <input type="hidden" name="order_ids[]" :value="id">
                        </template>
                        <select x-model="selectedExistingManifestId" required class="w-full px-3 py-2 rounded-xl border border-outline-variant bg-surface-container-lowest text-xs">
                            <template x-for="m in compatibleExistingManifests" :key="m.id">
                                <option :value="m.id" x-text="m.manifest_number + ' (' + m.status + ' — ' + m.total_parcels + ' parcels, ' + m.courier_name + ')'"></option>
                            </template>
                        </select>
                        <button type="submit" :disabled="!selectedExistingManifestId" class="w-full py-2.5 rounded-xl bg-primary text-on-primary text-xs font-semibold hover:bg-primary/90 disabled:opacity-40 cursor-pointer">
                            Append to Selected Manifest
                        </button>
                    </form>
                </template>
                <div x-show="compatibleExistingManifests.length === 0" class="text-[11px] text-on-surface-variant">
                    No compatible draft/pre-dispatch manifests found for this route. Use Option A to create a new manifest.
                </div>
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
                'current_hub_id' => (string) ($o->current_hub_id ?? ''),
                'current_hub_name' => $o->currentHub?->name ?? 'Unassigned Hub',
                'destination' => $o->formatted_destination,
                'dest_hub' => $o->destinationHub?->name ?? 'Unrouted',
            ])->values()),
            get isSameHub() {
                return Boolean(this.originHubId && this.destinationHubId && String(this.originHubId) === String(this.destinationHubId));
            },
            get filteredParcels() {
                return this.eligibleParcels.filter(p => {
                    if (this.originHubId && p.current_hub_id && String(p.current_hub_id) !== String(this.originHubId)) {
                        return false;
                    }
                    if (!this.parcelSearch) return true;
                    const q = this.parcelSearch.toLowerCase();
                    return p.tracking_number.toLowerCase().includes(q) || p.order_number.toLowerCase().includes(q);
                });
            }
        }"
        @open-manifest-modal.window="
            manifestModalOpen = true;
            if ($event.detail?.originHubId) {
                originHubId = String($event.detail.originHubId);
            }
            if ($event.detail?.destinationHubId) {
                destinationHubId = String($event.detail.destinationHubId);
            }
            if (Array.isArray($event.detail?.orderIds)) {
                selectedOrderIds = $event.detail.orderIds.map(String);
            } else if ($event.detail?.orderId && !selectedOrderIds.includes(String($event.detail.orderId))) {
                selectedOrderIds.push(String($event.detail.orderId));
            }
            if (String(destinationHubId) === String(originHubId)) {
                destinationHubId = '';
            }
        "
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
                        <select name="origin_hub_id" x-model="originHubId" @change="selectedOrderIds = []; if (String(destinationHubId) === String(originHubId)) destinationHubId = '';" required class="w-full px-3 py-2 rounded-xl border border-outline-variant bg-surface text-xs">
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
                                <option value="{{ $dHub->id }}" :disabled="String(originHubId) === '{{ $dHub->id }}'">
                                    {{ $dHub->name }} ({{ (int) $dHub->logistics_company_id === (int) auth()->id() ? 'Internal' : $dHub->logisticsCompany?->full_name }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div x-show="isSameHub" x-cloak class="rounded-xl border border-rose-500/30 bg-rose-500/10 px-3 py-2 text-xs font-semibold text-rose-700 flex items-center gap-2">
                    <span class="material-symbols-outlined text-[16px]">warning</span>
                    <span>Destination hub cannot be the same as the origin/current hub. Please select a different hub.</span>
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
                    <button type="submit" :disabled="!originHubId || !destinationHubId || isSameHub || selectedOrderIds.length === 0" class="px-5 py-2 rounded-xl bg-indigo-600 text-white text-xs font-semibold hover:bg-indigo-700 disabled:opacity-40 cursor-pointer">
                        Create Manifest (<span x-text="selectedOrderIds.length"></span> parcels)
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
