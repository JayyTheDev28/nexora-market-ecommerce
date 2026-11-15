@extends('layouts.app')

@section('title', "Manifest {$manifest->manifest_number} — Nexora Logistics")

@section('content')
<div class="w-full min-h-screen bg-surface">
    <div class="flex flex-col lg:flex-row w-full">
        <x-logistics-nav active="transfers" />

        <div class="flex-1 min-w-0 p-4 sm:p-6 lg:p-8 bg-surface">
            <div class="w-full max-w-7xl mx-auto space-y-6">

                {{-- Breadcrumb & Header --}}
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2 text-xs text-on-surface-variant mb-1">
                            <a href="{{ route('logistics.transfers.index') }}" class="hover:text-primary flex items-center gap-1">
                                <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                                <span>Hub Transfers & Manifests</span>
                            </a>
                            <span>/</span>
                            <span class="font-semibold text-on-surface">{{ $manifest->manifest_number }}</span>
                        </div>
                        <h1 class="font-headline-lg text-2xl font-bold text-on-surface flex items-center gap-3">
                            <span>{{ $manifest->manifest_number }}</span>
                            <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wide
                                {{ match($manifest->status) {
                                    'completed' => 'bg-emerald-500/15 text-emerald-700',
                                    'exception' => 'bg-rose-500/15 text-rose-700',
                                    'in_transit' => 'bg-indigo-500/15 text-indigo-700',
                                    'arrived', 'receiving' => 'bg-amber-500/15 text-amber-700',
                                    'cancelled' => 'bg-slate-500/15 text-slate-600',
                                    default => 'bg-primary/15 text-primary',
                                } }}">
                                {{ str_replace('_', ' ', $manifest->status) }}
                            </span>
                        </h1>
                        <p class="text-xs text-on-surface-variant mt-1">
                            {{ $manifest->originHub?->name }} ({{ $manifest->originHub?->code }}) → {{ $manifest->destinationHub?->name }} ({{ $manifest->destinationHub?->code }})
                            · {{ $manifest->is_internal ? 'Internal Hub Transfer' : 'Inter-Company Custody Transfer' }}
                        </p>
                    </div>

                    @if ((int) $manifest->sender_logistics_id === (int) auth()->id() && in_array($manifest->status, ['draft', 'loading', 'ready_to_dispatch']))
                        <form action="{{ route('logistics.transfers.manifests.cancel', $manifest) }}" method="POST" onsubmit="return confirm('Cancel this transfer manifest?');">
                            @csrf
                            <button type="submit" class="px-4 py-2 rounded-full border border-rose-500/40 text-rose-700 text-xs font-semibold hover:bg-rose-500/10 cursor-pointer">
                                Cancel Manifest
                            </button>
                        </form>
                    @endif
                </div>

                @if (session('status'))
                    <div class="bg-primary/10 border border-primary/30 rounded-2xl p-4 flex items-center gap-3">
                        <span class="material-symbols-outlined text-primary text-[20px]">check_circle</span>
                        <p class="text-xs sm:text-sm text-primary font-medium">{{ session('status') }}</p>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="bg-rose-500/10 border border-rose-500/20 rounded-2xl p-4">
                        <ul class="list-disc list-inside text-xs text-rose-600 space-y-0.5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Summary Cards --}}
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-4">
                    <div class="bg-surface-container-lowest border border-outline-variant rounded-2xl p-4">
                        <span class="text-[11px] text-on-surface-variant">Expected</span>
                        <p id="manifest-count-expected" class="text-xl font-bold text-on-surface mt-1">{{ $receivingCounts['expected'] }}</p>
                    </div>
                    <div class="bg-surface-container-lowest border border-outline-variant rounded-2xl p-4">
                        <span class="text-[11px] text-emerald-700">Received</span>
                        <p id="manifest-count-received" class="text-xl font-bold text-emerald-700 mt-1">{{ $receivingCounts['received'] }}</p>
                    </div>
                    <div class="bg-surface-container-lowest border border-outline-variant rounded-2xl p-4">
                        <span class="text-[11px] text-amber-700">Outstanding</span>
                        <p id="manifest-count-outstanding" class="text-xl font-bold text-amber-700 mt-1">{{ $receivingCounts['outstanding'] }}</p>
                    </div>
                    <div class="bg-surface-container-lowest border border-outline-variant rounded-2xl p-4">
                        <span class="text-[11px] text-rose-700">Missing</span>
                        <p id="manifest-count-missing" class="text-xl font-bold text-rose-700 mt-1">{{ $receivingCounts['missing'] }}</p>
                    </div>
                    <div class="bg-surface-container-lowest border border-outline-variant rounded-2xl p-4">
                        <span class="text-[11px] text-rose-700">Damaged / Discrepancy</span>
                        <p id="manifest-count-issues" class="text-xl font-bold text-rose-700 mt-1">{{ $receivingCounts['damaged'] + $receivingCounts['discrepancy'] }}</p>
                    </div>
                </div>

                {{-- Unresolved Post-Receipt / Manifest Incidents Alert Strip --}}
                @if (($receivingCounts['open_incidents'] ?? 0) > 0)
                    <div class="rounded-2xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 flex flex-wrap items-center justify-between gap-2 text-xs text-rose-800">
                        <div class="flex items-center gap-2 font-semibold">
                            <span class="material-symbols-outlined text-[18px]">warning</span>
                            <span>
                                Manifest Exceptions Active:
                                <strong>{{ $receivingCounts['open_damage_incidents'] ?? 0 }}</strong> unresolved damage incident(s),
                                <strong>{{ $receivingCounts['open_missing_incidents'] ?? 0 }}</strong> unresolved missing/lost parcel(s).
                            </span>
                        </div>
                        <span class="text-[11px] font-medium">Parcels with unresolved incidents are held from normal dispatch until resolved.</span>
                    </div>
                @endif

                {{-- Assign Courier (Sender before dispatch) --}}
                @if ((int) $manifest->sender_logistics_id === (int) auth()->id() && in_array($manifest->status, ['draft', 'loading', 'ready_to_dispatch']))
                    <div class="bg-surface-container-lowest border border-outline-variant rounded-2xl p-5">
                        <h2 class="text-sm font-bold text-on-surface mb-3">Assign / Update Transfer Courier</h2>
                        <form action="{{ route('logistics.transfers.manifests.assign-courier', $manifest) }}" method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
                            @csrf
                            <div>
                                <label class="block text-xs font-semibold text-on-surface mb-1">Transfer Courier</label>
                                <select name="assigned_courier_id" required class="w-full rounded-xl border border-outline-variant bg-surface px-3 py-2 text-xs">
                                    <option value="">Select approved Transfer Courier...</option>
                                    @foreach ($transferCouriers as $tc)
                                        @php $busy = $tc->assignedManifests->where('id', '!=', $manifest->id)->isNotEmpty(); @endphp
                                        <option value="{{ $tc->id }}" {{ (int) $manifest->assigned_courier_id === (int) $tc->id ? 'selected' : '' }} {{ $busy ? 'disabled' : '' }}>
                                            {{ $tc->full_name }} {{ $busy ? '(Active Trip)' : '(Available)' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-on-surface mb-1">Vehicle Plate Number (Optional)</label>
                                <input type="text" name="vehicle_plate_number" value="{{ $manifest->vehicle_plate_number }}" placeholder="e.g. ABC 1234" class="w-full uppercase rounded-xl border border-outline-variant bg-surface px-3 py-2 text-xs">
                            </div>
                            <button type="submit" class="px-4 py-2.5 rounded-xl bg-primary text-on-primary text-xs font-semibold hover:bg-primary/90 cursor-pointer">
                                Save Courier Assignment
                            </button>
                        </form>
                    </div>
                @endif

                {{-- Receiving Hub Verification Form (Receiver after arrival) --}}
                @if ((int) $manifest->receiver_logistics_id === (int) auth()->id() && in_array($manifest->status, ['arrived', 'receiving', 'exception']))
                    <div class="bg-surface-container-lowest border-2 border-primary/30 rounded-2xl p-5">
                        <div class="flex flex-wrap items-center justify-between gap-3 mb-2">
                            <div>
                                <h2 class="text-sm font-bold text-on-surface">Receiving Hub Auto-Scanner & Verification</h2>
                                <p class="text-xs text-on-surface-variant mt-0.5">
                                    Camera scans automatically accept eligible parcels as <strong class="text-emerald-700">Received</strong>. Flagged exceptions are held for manual review.
                                </p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2.5">
                                <span class="inline-flex items-center gap-1.5 rounded-full border border-[#86efac] bg-[#f0fdf4] px-3 py-1 text-xs font-bold text-[#166534]">
                                    <span class="h-2 w-2 rounded-full bg-[#16a34a]"></span>
                                    <span>Parcels Received This Session: <strong id="manifest-session-count" class="font-mono">0</strong></span>
                                </span>
                                <button type="button" onclick="window.toggleManifestCamera()" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-slate-900 text-white text-xs font-semibold hover:bg-slate-800 cursor-pointer">
                                    <span class="material-symbols-outlined text-[16px]">photo_camera</span>
                                    <span id="manifest-camera-toggle-label">Open Camera</span>
                                </button>
                            </div>
                        </div>

                        <form id="manifest-verify-form" action="{{ route('logistics.transfers.manifests.verify-parcel', $manifest) }}" method="POST" class="mt-3 grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                            @csrf
                            <div class="md:col-span-4">
                                <label for="manifest-tracking-input" class="block text-xs font-semibold text-on-surface mb-1">Tracking Number</label>
                                <input id="manifest-tracking-input" type="text" name="tracking_number" required autofocus autocomplete="off" spellcheck="false" placeholder="Scan or type tracking #" class="w-full font-mono text-sm font-bold tracking-wide rounded-xl border-2 border-primary/30 bg-surface px-3 py-2.5 text-on-surface focus:border-primary focus:ring-4 focus:ring-primary/10">
                            </div>
                            <div class="md:col-span-3">
                                <label for="manifest-classification-select" class="block text-xs font-semibold text-on-surface mb-1">Classification</label>
                                <select id="manifest-classification-select" name="classification" required class="w-full rounded-xl border border-outline-variant bg-surface px-3 py-2.5 text-xs font-semibold">
                                    <option value="received">Received (Accept & Transfer Custody)</option>
                                    <option value="missing">Missing</option>
                                    <option value="damaged">Damaged</option>
                                    <option value="discrepancy">Discrepancy / Needs Review</option>
                                </select>
                            </div>
                            <div class="md:col-span-3">
                                <label for="manifest-notes-input" class="block text-xs font-semibold text-on-surface mb-1">Notes (Optional)</label>
                                <input id="manifest-notes-input" type="text" name="notes" placeholder="Condition or discrepancy details" class="w-full rounded-xl border border-outline-variant bg-surface px-3 py-2.5 text-xs">
                            </div>
                            <div class="md:col-span-2">
                                <button id="manifest-verify-btn" type="submit" class="w-full px-4 py-2.5 rounded-xl bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-700 cursor-pointer">
                                    Submit Entry
                                </button>
                            </div>
                        </form>

                        @include('logistics.components.camera_scanner_modal', [
                            'scannerId' => 'manifest-camera',
                            'title' => 'Transfer Manifest Auto-Scanner',
                            'subtitle' => 'Point camera at any parcel on this manifest to receive it automatically.',
                            'manualFocusInputId' => 'manifest-tracking-input',
                        ])

                        {{-- Live Manifest Scan Notification Card --}}
                        <div id="manifest-scan-feedback" class="hidden mt-4 rounded-xl border p-4 text-xs"></div>
                    </div>
                @endif

                {{-- Manifest Parcels Table --}}
                @php
                    $isAuthorizedReceiver = (int) $manifest->receiver_logistics_id === (int) auth()->id();
                    $canClassifyManifest = $isAuthorizedReceiver && in_array($manifest->status, ['arrived', 'receiving', 'exception']);
                    $canReportPostReceipt = $isAuthorizedReceiver && $manifest->status !== 'cancelled';

                    $manifestItemsJson = $manifest->items->mapWithKeys(function ($it) {
                        $cond = $it->condition_summary;
                        return [
                            $it->id => [
                                'id' => $it->id,
                                'tracking_number' => $it->tracking_number,
                                'order_number' => $it->order?->order_number ?? '',
                                'receiving_status' => $it->receiving_status,
                                'received_at' => $it->received_at?->format('M d, Y h:i A'),
                                'received_by' => $it->receivedByUser?->full_name,
                                'condition' => $cond,
                                'incidents' => $it->incidents->map(fn ($inc) => [
                                    'id' => $inc->id,
                                    'incident_type' => $inc->incident_type,
                                    'damage_type' => $inc->damage_type,
                                    'damage_type_label' => $inc->damage_type_label,
                                    'source_stage' => $inc->source_stage,
                                    'investigation_status' => $inc->investigation_status,
                                    'status_label' => $inc->status_label,
                                    'description' => $inc->description,
                                    'resolution_notes' => $inc->resolution_notes,
                                    'evidence_url' => $inc->evidence_url,
                                    'hub_name' => $inc->hub?->name,
                                    'reporter_name' => $inc->reporter?->full_name ?? 'Operator',
                                    'resolver_name' => $inc->resolver?->full_name,
                                    'reported_at' => $inc->reported_at?->format('M d, Y h:i A'),
                                    'found_at' => $inc->found_at?->format('M d, Y h:i A'),
                                    'resolved_at' => $inc->resolved_at?->format('M d, Y h:i A'),
                                ])->values()->all(),
                            ],
                        ];
                    });
                @endphp
                <div class="bg-surface-container-lowest border border-outline-variant rounded-2xl overflow-hidden">
                    <div class="px-5 py-4 border-b border-outline-variant flex items-center justify-between">
                        <h2 class="text-sm font-bold text-on-surface">Manifest Parcels ({{ $manifest->items->count() }})</h2>
                        <span class="text-xs text-on-surface-variant">
                            Courier: <strong>{{ $manifest->assignedCourier?->full_name ?? 'Unassigned' }}</strong>
                            @if ($manifest->vehicle_plate_number)
                                · Plate: <strong>{{ $manifest->vehicle_plate_number }}</strong>
                            @endif
                        </span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-surface-container-low text-on-surface-variant uppercase text-[11px]">
                                <tr>
                                    <th class="px-4 py-3">Tracking #</th>
                                    <th class="px-4 py-3">Order #</th>
                                    <th class="px-4 py-3">Loading Status</th>
                                    <th class="px-4 py-3">Receiving Status</th>
                                    <th class="px-4 py-3">Parcel Condition</th>
                                    <th class="px-4 py-3">Notes</th>
                                    <th class="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline-variant/60">
                                @foreach ($manifest->items as $item)
                                    @php
                                        $cond = $item->condition_summary;
                                        $primaryIncident = $cond['primary_incident'];
                                    @endphp
                                    <tr data-manifest-item-id="{{ $item->id }}">
                                        <td class="px-4 py-3 font-mono font-semibold text-on-surface">{{ $item->tracking_number }}</td>
                                        <td class="px-4 py-3 text-on-surface-variant">#{{ $item->order?->order_number }}</td>
                                        <td class="px-4 py-3">
                                            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold {{ $item->loading_status === 'loaded' ? 'bg-emerald-500/15 text-emerald-700' : 'bg-slate-500/15 text-slate-600' }}">
                                                {{ ucfirst($item->loading_status) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3" data-item-status-cell="{{ $item->id }}">
                                            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold {{ match($item->receiving_status) {
                                                'received' => 'bg-emerald-500/15 text-emerald-700',
                                                'missing', 'damaged', 'discrepancy' => 'bg-rose-500/15 text-rose-700',
                                                default => 'bg-amber-500/15 text-amber-700',
                                            } }}">
                                                {{ ucfirst($item->receiving_status) }}
                                            </span>
                                            @if ($item->receiving_status === 'received' && $item->received_at)
                                                <div class="text-[10px] text-on-surface-variant mt-0.5">{{ $item->received_at->format('M d, h:i A') }}</div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3" data-item-condition-cell="{{ $item->id }}">
                                            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold {{ $cond['classes'] }}">
                                                {{ $cond['label'] }}
                                            </span>
                                            @if (! empty($cond['sublabel']))
                                                <div class="text-[10px] text-on-surface-variant mt-0.5">{{ $cond['sublabel'] }}</div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-on-surface-variant max-w-xs" data-item-notes-cell="{{ $item->id }}">{{ $item->issue_notes ?? '—' }}</td>
                                        <td class="px-4 py-3 text-right" data-item-action-cell="{{ $item->id }}">
                                            <div class="inline-flex flex-wrap items-center justify-end gap-1.5">
                                                @if ($item->receiving_status === 'pending')
                                                    @if ($canClassifyManifest)
                                                        <button type="button" onclick="window.openClassifyModal({{ $item->id }}, 'received')" class="px-3 py-1 rounded-full bg-primary text-on-primary text-[11px] font-semibold hover:bg-primary/90 cursor-pointer">
                                                            Classify
                                                        </button>
                                                    @else
                                                        <span class="text-[11px] text-on-surface-variant">Awaiting Receipt</span>
                                                    @endif
                                                @elseif ($item->receiving_status === 'received')
                                                    @if ($cond['has_damage_report'])
                                                        <button type="button" onclick="window.openIncidentHistoryModal({{ $item->id }})" class="px-3 py-1 rounded-full bg-rose-500/15 text-rose-700 text-[11px] font-semibold hover:bg-rose-500/25 cursor-pointer">
                                                            View Damage Report
                                                        </button>
                                                    @elseif ($item->incidents->isNotEmpty())
                                                        <button type="button" onclick="window.openIncidentHistoryModal({{ $item->id }})" class="px-3 py-1 rounded-full bg-amber-500/15 text-amber-800 text-[11px] font-semibold hover:bg-amber-500/25 cursor-pointer">
                                                            View Incident
                                                        </button>
                                                    @endif

                                                    @if ($canReportPostReceipt && ! $cond['has_open_damage'])
                                                        <button type="button" onclick="window.openDamageReportModal({{ $item->id }})" class="px-3 py-1 rounded-full border border-rose-500/40 text-rose-700 text-[11px] font-semibold hover:bg-rose-500/10 cursor-pointer">
                                                            Report Damage
                                                        </button>
                                                    @endif

                                                    @if ($canReportPostReceipt && ! $cond['has_open_missing'])
                                                        <button type="button" onclick="window.openMissingReportModal({{ $item->id }})" class="px-3 py-1 rounded-full border border-amber-600/40 text-amber-800 text-[11px] font-semibold hover:bg-amber-500/10 cursor-pointer">
                                                            Report Missing
                                                        </button>
                                                    @endif
                                                @else
                                                    @if ($item->receiving_status === 'missing' && $canClassifyManifest)
                                                        @if ($primaryIncident && in_array($primaryIncident->investigation_status, ['open', 'under_investigation'], true))
                                                            <form action="{{ route('logistics.transfers.manifests.incidents.update-status', [$manifest, $primaryIncident]) }}" method="POST" class="inline">
                                                                @csrf
                                                                @method('PATCH')
                                                                <input type="hidden" name="investigation_status" value="found">
                                                                <input type="hidden" name="resolution_notes" value="Parcel located at receiving hub; awaiting receipt completion.">
                                                                <button type="submit" class="px-3 py-1 rounded-full bg-indigo-600 text-white text-[11px] font-semibold hover:bg-indigo-700 cursor-pointer">
                                                                    Mark Found
                                                                </button>
                                                            </form>
                                                        @elseif ($cond['has_found_missing'])
                                                            <button type="button" onclick="window.openClassifyModal({{ $item->id }}, 'received')" class="px-3 py-1 rounded-full bg-emerald-600 text-white text-[11px] font-semibold hover:bg-emerald-700 cursor-pointer">
                                                                Complete Receiving
                                                            </button>
                                                        @endif
                                                    @endif

                                                    @if ($item->incidents->isNotEmpty())
                                                        <button type="button" onclick="window.openIncidentHistoryModal({{ $item->id }})" class="px-3 py-1 rounded-full bg-surface-container-high text-on-surface text-[11px] font-semibold hover:bg-primary hover:text-on-primary cursor-pointer">
                                                            View Incident
                                                        </button>
                                                    @endif

                                                    @if ($canClassifyManifest)
                                                        <button type="button" onclick="window.openClassifyModal({{ $item->id }}, '{{ $item->receiving_status }}')" class="px-3 py-1 rounded-full border border-outline-variant text-on-surface text-[11px] font-semibold hover:bg-surface-container cursor-pointer">
                                                            Classify
                                                        </button>
                                                    @endif
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Audit Trail --}}
                @if ($auditLogs->isNotEmpty())
                    <div class="bg-surface-container-lowest border border-outline-variant rounded-2xl p-5">
                        <h2 class="text-sm font-bold text-on-surface mb-3">Manifest Audit Trail</h2>
                        <div class="space-y-2 text-xs">
                            @foreach ($auditLogs as $log)
                                <div class="flex items-center justify-between py-1.5 border-b border-outline-variant/40 last:border-0">
                                    <div>
                                        <span class="font-semibold text-on-surface">{{ str_replace('_', ' ', ucfirst($log->action)) }}</span>
                                        <span class="text-on-surface-variant">by {{ $log->operator?->full_name ?? 'System' }}</span>
                                        @if ($log->reason)
                                            <span class="text-rose-700">— {{ $log->reason }}</span>
                                        @endif
                                    </div>
                                    <span class="text-on-surface-variant text-[11px]">{{ $log->created_at?->format('M d, Y h:i A') }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

            </div>
        </div>
    </div>
</div>

{{-- Classify Pending / Unreceived Parcel Modal --}}
<div id="classify-parcel-modal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-surface-container-lowest border border-outline-variant rounded-3xl w-full max-w-md p-6 shadow-xl space-y-4">
        <div class="flex items-center justify-between border-b border-outline-variant pb-3">
            <div>
                <h3 class="text-base font-bold text-on-surface">Classify Manifest Parcel</h3>
                <p class="text-xs text-on-surface-variant">Select physical receiving classification and confirm submission.</p>
            </div>
            <button type="button" onclick="window.closeManifestModals()" class="p-1.5 rounded-full hover:bg-surface-container text-on-surface-variant cursor-pointer">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
        </div>

        <form action="{{ route('logistics.transfers.manifests.verify-parcel', $manifest) }}" method="POST" class="space-y-4">
            @csrf
            <input type="hidden" name="tracking_number" id="classify-tracking-input">

            <div class="rounded-xl bg-surface-container-low p-3 text-xs space-y-1">
                <div>Tracking #: <strong id="classify-tracking-display" class="font-mono text-on-surface"></strong></div>
                <div>Order #: <strong id="classify-order-display" class="text-on-surface-variant"></strong></div>
            </div>

            <div>
                <label for="classify-classification-select" class="block text-xs font-semibold text-on-surface mb-1">Classification</label>
                <select name="classification" id="classify-classification-select" required class="w-full rounded-xl border border-outline-variant bg-surface px-3 py-2 text-xs font-semibold">
                    <option value="received">Received</option>
                    <option value="damaged">Damaged</option>
                    <option value="missing">Missing</option>
                    <option value="discrepancy">Discrepancy</option>
                </select>
            </div>

            <div>
                <label for="classify-notes-input" id="classify-notes-label" class="block text-xs font-semibold text-on-surface mb-1">Notes (Optional for normal receipt)</label>
                <textarea name="notes" id="classify-notes-input" rows="3" placeholder="Enter inspection, condition, or discrepancy details..." class="w-full rounded-xl border border-outline-variant bg-surface px-3 py-2 text-xs"></textarea>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-outline-variant">
                <button type="button" onclick="window.closeManifestModals()" class="px-4 py-2 rounded-xl border border-outline-variant text-xs font-semibold text-on-surface-variant cursor-pointer">Cancel</button>
                <button type="submit" class="px-4 py-2 rounded-xl bg-primary text-on-primary text-xs font-semibold hover:bg-primary/90 cursor-pointer">Submit Classification</button>
            </div>
        </form>
    </div>
</div>

{{-- Report Post-Receipt Damage Modal --}}
<div id="report-damage-modal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-surface-container-lowest border border-outline-variant rounded-3xl w-full max-w-lg p-6 shadow-xl space-y-4">
        <div class="flex items-center justify-between border-b border-outline-variant pb-3">
            <div>
                <h3 class="text-base font-bold text-rose-700">Report Post-Receipt Damage</h3>
                <p class="text-xs text-on-surface-variant">Logs a separate damage incident while preserving the original Received timestamp &amp; custody.</p>
            </div>
            <button type="button" onclick="window.closeManifestModals()" class="p-1.5 rounded-full hover:bg-surface-container text-on-surface-variant cursor-pointer">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
        </div>

        <form id="report-damage-form" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div class="grid grid-cols-2 gap-3 rounded-xl bg-surface-container-low p-3 text-xs">
                <div>
                    <span class="text-[11px] text-on-surface-variant block">Tracking Number</span>
                    <strong id="damage-tracking-display" class="font-mono text-on-surface"></strong>
                </div>
                <div>
                    <span class="text-[11px] text-on-surface-variant block">Current Receiving Status</span>
                    <strong id="damage-receiving-status-display" class="text-emerald-700">Received</strong>
                </div>
            </div>

            <div>
                <label for="damage-type-select" class="block text-xs font-semibold text-on-surface mb-1">Damage Classification / Type</label>
                <select name="damage_type" id="damage-type-select" required class="w-full rounded-xl border border-outline-variant bg-surface px-3 py-2 text-xs font-semibold">
                    @foreach (\App\Models\ParcelIncident::DAMAGE_TYPES as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="damage-description-input" class="block text-xs font-semibold text-on-surface mb-1">Description <span class="text-rose-600">*</span></label>
                <textarea name="description" id="damage-description-input" rows="3" required minlength="3" placeholder="Describe observed damage, affected packaging/contents, and handling notes..." class="w-full rounded-xl border border-outline-variant bg-surface px-3 py-2 text-xs"></textarea>
            </div>

            <div>
                <label for="damage-evidence-input" class="block text-xs font-semibold text-on-surface mb-1">Photo Evidence (JPG/PNG/WEBP, max 5MB)</label>
                <input type="file" name="evidence" id="damage-evidence-input" accept="image/jpeg,image/png,image/webp" class="w-full rounded-xl border border-outline-variant bg-surface px-3 py-2 text-xs">
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-outline-variant">
                <button type="button" onclick="window.closeManifestModals()" class="px-4 py-2 rounded-xl border border-outline-variant text-xs font-semibold text-on-surface-variant cursor-pointer">Cancel</button>
                <button type="submit" class="px-4 py-2 rounded-xl bg-rose-600 text-white text-xs font-semibold hover:bg-rose-700 cursor-pointer">Submit Damage Report</button>
            </div>
        </form>
    </div>
</div>

{{-- Report Post-Receipt Missing Modal --}}
<div id="report-missing-modal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-surface-container-lowest border border-outline-variant rounded-3xl w-full max-w-md p-6 shadow-xl space-y-4">
        <div class="flex items-center justify-between border-b border-outline-variant pb-3">
            <div>
                <h3 class="text-base font-bold text-rose-700">Report Parcel Missing After Receipt</h3>
                <p class="text-xs text-on-surface-variant">Opens a missing-parcel investigation without deleting the original receipt record.</p>
            </div>
            <button type="button" onclick="window.closeManifestModals()" class="p-1.5 rounded-full hover:bg-surface-container text-on-surface-variant cursor-pointer">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
        </div>

        <form id="report-missing-form" method="POST" class="space-y-4">
            @csrf
            <div class="grid grid-cols-2 gap-3 rounded-xl bg-surface-container-low p-3 text-xs">
                <div>
                    <span class="text-[11px] text-on-surface-variant block">Tracking Number</span>
                    <strong id="missing-tracking-display" class="font-mono text-on-surface"></strong>
                </div>
                <div>
                    <span class="text-[11px] text-on-surface-variant block">Original Receiving Status</span>
                    <strong class="text-emerald-700">Received</strong>
                </div>
            </div>

            <div>
                <label for="missing-description-input" class="block text-xs font-semibold text-on-surface mb-1">Missing Details &amp; Last Known Hub Location <span class="text-rose-600">*</span></label>
                <textarea name="description" id="missing-description-input" rows="3" required minlength="3" placeholder="Explain where parcel was last scanned/staged and investigation details..." class="w-full rounded-xl border border-outline-variant bg-surface px-3 py-2 text-xs"></textarea>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-outline-variant">
                <button type="button" onclick="window.closeManifestModals()" class="px-4 py-2 rounded-xl border border-outline-variant text-xs font-semibold text-on-surface-variant cursor-pointer">Cancel</button>
                <button type="submit" class="px-4 py-2 rounded-xl bg-rose-600 text-white text-xs font-semibold hover:bg-rose-700 cursor-pointer">Submit Missing Report</button>
            </div>
        </form>
    </div>
</div>

{{-- View Incident History & Investigation Status Modal --}}
<div id="incident-history-modal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-surface-container-lowest border border-outline-variant rounded-3xl w-full max-w-2xl p-6 shadow-xl space-y-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-outline-variant pb-3">
            <div>
                <h3 class="text-base font-bold text-on-surface">Parcel Incident History &amp; Resolution</h3>
                <p id="incident-modal-subtitle" class="text-xs text-on-surface-variant"></p>
            </div>
            <button type="button" onclick="window.closeManifestModals()" class="p-1.5 rounded-full hover:bg-surface-container text-on-surface-variant cursor-pointer">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
        </div>

        <div id="incident-modal-body" class="space-y-3 text-xs"></div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        const SCANNER_ID = 'manifest-camera';
        const form = document.getElementById('manifest-verify-form');
        const input = document.getElementById('manifest-tracking-input');
        const classSelect = document.getElementById('manifest-classification-select');
        const notesInput = document.getElementById('manifest-notes-input');
        const toggleLabel = document.getElementById('manifest-camera-toggle-label');
        const feedbackBox = document.getElementById('manifest-scan-feedback');
        const sessionCountEl = document.getElementById('manifest-session-count');

        let manifestSessionCount = 0;
        let manifestRequestSeq = 0;

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

        function setManifestStatus(statusCode, message, decodedTracking) {
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

        const MANIFEST_BASE_URL = '{{ url('/logistics/transfers/manifests/' . $manifest->id) }}';
        const IS_AUTHORIZED_RECEIVER = {{ (int) $manifest->receiver_logistics_id === (int) auth()->id() ? 'true' : 'false' }};
        const CAN_REPORT_POST_RECEIPT = {{ (int) $manifest->receiver_logistics_id === (int) auth()->id() && $manifest->status !== 'cancelled' ? 'true' : 'false' }};
        const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const MANIFEST_ITEMS_DATA = @js($manifestItemsJson ?? []);

        const classifyModal = document.getElementById('classify-parcel-modal');
        const damageModal = document.getElementById('report-damage-modal');
        const missingModal = document.getElementById('report-missing-modal');
        const historyModal = document.getElementById('incident-history-modal');
        const classifySelect = document.getElementById('classify-classification-select');
        const classifyNotesInput = document.getElementById('classify-notes-input');
        const classifyNotesLabel = document.getElementById('classify-notes-label');

        function syncClassifyNotesRequirement() {
            if (!classifySelect || !classifyNotesInput || !classifyNotesLabel) return;
            const requiresNotes = ['damaged', 'missing', 'discrepancy'].includes(classifySelect.value);
            classifyNotesInput.required = requiresNotes;
            classifyNotesLabel.textContent = requiresNotes
                ? 'Notes (Required for Damaged / Missing / Discrepancy)'
                : 'Notes (Optional for normal receipt)';
        }

        if (classifySelect) {
            classifySelect.addEventListener('change', syncClassifyNotesRequirement);
        }

        window.closeManifestModals = function () {
            [classifyModal, damageModal, missingModal, historyModal].forEach((m) => {
                if (m) m.classList.add('hidden');
            });
        };

        window.openClassifyModal = function (itemId, presetClassification) {
            const item = MANIFEST_ITEMS_DATA[itemId];
            if (!item || !classifyModal) return;
            window.closeManifestModals();
            document.getElementById('classify-tracking-input').value = item.tracking_number;
            document.getElementById('classify-tracking-display').textContent = item.tracking_number;
            document.getElementById('classify-order-display').textContent = '#' + (item.order_number || '');
            if (classifySelect && presetClassification) {
                classifySelect.value = presetClassification === 'pending' ? 'received' : presetClassification;
            }
            if (classifyNotesInput) classifyNotesInput.value = '';
            syncClassifyNotesRequirement();
            classifyModal.classList.remove('hidden');
        };

        window.openDamageReportModal = function (itemId) {
            const item = MANIFEST_ITEMS_DATA[itemId];
            if (!item || !damageModal) return;
            window.closeManifestModals();
            const dmgForm = document.getElementById('report-damage-form');
            dmgForm.action = MANIFEST_BASE_URL + '/items/' + itemId + '/report-damage';
            document.getElementById('damage-tracking-display').textContent = item.tracking_number;
            document.getElementById('damage-receiving-status-display').textContent =
                item.receiving_status ? (item.receiving_status.charAt(0).toUpperCase() + item.receiving_status.slice(1)) : 'Received';
            document.getElementById('damage-description-input').value = '';
            document.getElementById('damage-evidence-input').value = '';
            damageModal.classList.remove('hidden');
        };

        window.openMissingReportModal = function (itemId) {
            const item = MANIFEST_ITEMS_DATA[itemId];
            if (!item || !missingModal) return;
            window.closeManifestModals();
            const msgForm = document.getElementById('report-missing-form');
            msgForm.action = MANIFEST_BASE_URL + '/items/' + itemId + '/report-missing';
            document.getElementById('missing-tracking-display').textContent = item.tracking_number;
            document.getElementById('missing-description-input').value = '';
            missingModal.classList.remove('hidden');
        };

        window.openIncidentHistoryModal = function (itemId) {
            const item = MANIFEST_ITEMS_DATA[itemId];
            if (!item || !historyModal) return;
            window.closeManifestModals();

            const sub = document.getElementById('incident-modal-subtitle');
            const body = document.getElementById('incident-modal-body');
            if (sub) {
                const recInfo = item.received_at
                    ? (' · Receipt preserved: ' + item.received_at + (item.received_by ? ' by ' + item.received_by : ''))
                    : '';
                sub.textContent = 'Tracking: ' + item.tracking_number + ' · Receiving Status: ' + item.receiving_status.toUpperCase() + recInfo;
            }

            if (body) {
                const incidents = Array.isArray(item.incidents) ? item.incidents : [];
                if (incidents.length === 0) {
                    body.innerHTML = '<div class="rounded-2xl border border-outline-variant p-4 text-center text-on-surface-variant">No recorded damage or missing incidents for this parcel.</div>';
                } else {
                    body.innerHTML = incidents.map((inc) => {
                        const statusOptions = [
                            ['open', 'Open'],
                            ['under_investigation', 'Under Investigation'],
                            ...(inc.incident_type !== 'damage' ? [['found', 'Found']] : []),
                            ['resolved', 'Resolved'],
                        ].map(([val, lbl]) => `<option value="${val}" ${inc.investigation_status === val ? 'selected' : ''}>${lbl}</option>`).join('');

                        const updateFormHtml = (IS_AUTHORIZED_RECEIVER && inc.investigation_status !== 'resolved')
                            ? `
                                <form action="${MANIFEST_BASE_URL}/incidents/${inc.id}/status" method="POST" class="mt-3 pt-3 border-t border-outline-variant/60 grid grid-cols-1 sm:grid-cols-12 gap-2 items-end">
                                    <input type="hidden" name="_token" value="${escapeHtml(CSRF_TOKEN)}">
                                    <input type="hidden" name="_method" value="PATCH">
                                    <div class="sm:col-span-4">
                                        <label class="block text-[10px] font-bold uppercase text-on-surface-variant mb-1">Update Investigation Status</label>
                                        <select name="investigation_status" class="w-full rounded-xl border border-outline-variant bg-surface px-2.5 py-1.5 text-xs font-semibold">${statusOptions}</select>
                                    </div>
                                    <div class="sm:col-span-5">
                                        <label class="block text-[10px] font-bold uppercase text-on-surface-variant mb-1">Resolution / Finding Notes</label>
                                        <input type="text" name="resolution_notes" value="${escapeHtml(inc.resolution_notes || '')}" placeholder="Inspection or resolution details" class="w-full rounded-xl border border-outline-variant bg-surface px-2.5 py-1.5 text-xs">
                                    </div>
                                    <div class="sm:col-span-3">
                                        <button type="submit" class="w-full px-3 py-1.5 rounded-xl bg-primary text-on-primary text-xs font-semibold hover:bg-primary/90 cursor-pointer">Update Status</button>
                                    </div>
                                </form>
                            `
                            : '';

                        const evidenceHtml = inc.evidence_url
                            ? `<div class="mt-2"><a href="${escapeHtml(inc.evidence_url)}" target="_blank" class="inline-flex items-center gap-1 text-primary font-semibold underline"><span class="material-symbols-outlined text-[15px]">image</span><span>View Photo Evidence</span></a><img src="${escapeHtml(inc.evidence_url)}" alt="Incident evidence" class="mt-1.5 max-h-40 rounded-xl border border-outline-variant object-contain"></div>`
                            : '';

                        return `
                            <div class="rounded-2xl border border-outline-variant bg-surface p-4 space-y-1.5">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-rose-500/15 text-rose-700">${escapeHtml(inc.incident_type.toUpperCase())}</span>
                                        ${inc.damage_type_label ? `<span class="font-semibold text-on-surface">${escapeHtml(inc.damage_type_label)}</span>` : ''}
                                        <span class="text-[11px] text-on-surface-variant">(${escapeHtml(inc.source_stage.replace('_', ' '))})</span>
                                    </div>
                                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-500/15 text-amber-800">${escapeHtml(inc.status_label)}</span>
                                </div>
                                <p class="text-on-surface">${escapeHtml(inc.description)}</p>
                                <div class="text-[11px] text-on-surface-variant">
                                    Reported by <strong>${escapeHtml(inc.reporter_name)}</strong>${inc.hub_name ? ' at <strong>' + escapeHtml(inc.hub_name) + '</strong>' : ''} on ${escapeHtml(inc.reported_at || '')}
                                </div>
                                ${inc.found_at ? `<div class="text-[11px] text-indigo-700 font-medium">Marked Found: ${escapeHtml(inc.found_at)}</div>` : ''}
                                ${inc.resolved_at ? `<div class="text-[11px] text-emerald-700 font-medium">Resolved${inc.resolver_name ? ' by ' + escapeHtml(inc.resolver_name) : ''} on ${escapeHtml(inc.resolved_at)}${inc.resolution_notes ? ' — ' + escapeHtml(inc.resolution_notes) : ''}</div>` : ''}
                                ${evidenceHtml}
                                ${updateFormHtml}
                            </div>
                        `;
                    }).join('');
                }
            }
            historyModal.classList.remove('hidden');
        };

        function updateManifestCountsAndRow(counts, item) {
            if (counts) {
                const map = {
                    'manifest-count-expected': counts.expected,
                    'manifest-count-received': counts.received,
                    'manifest-count-outstanding': counts.outstanding,
                    'manifest-count-missing': counts.missing,
                    'manifest-count-issues': (Number(counts.damaged || 0) + Number(counts.discrepancy || 0)),
                };
                for (const [id, val] of Object.entries(map)) {
                    const el = document.getElementById(id);
                    if (el && val !== undefined) el.textContent = String(val);
                }
            }

            if (item && item.id) {
                if (MANIFEST_ITEMS_DATA[item.id]) {
                    MANIFEST_ITEMS_DATA[item.id].receiving_status = item.receiving_status;
                    if (item.condition_summary) {
                        MANIFEST_ITEMS_DATA[item.id].condition = item.condition_summary;
                    }
                }

                const statusCell = document.querySelector('[data-item-status-cell="' + item.id + '"]');
                if (statusCell) {
                    const st = String(item.receiving_status || '').toLowerCase();
                    const cls = st === 'received'
                        ? 'bg-emerald-500/15 text-emerald-700'
                        : (['missing', 'damaged', 'discrepancy'].includes(st) ? 'bg-rose-500/15 text-rose-700' : 'bg-amber-500/15 text-amber-700');
                    const label = st ? (st.charAt(0).toUpperCase() + st.slice(1)) : 'Updated';
                    statusCell.innerHTML = '<span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold ' + cls + '">' + escapeHtml(label) + '</span>';
                }

                const condCell = document.querySelector('[data-item-condition-cell="' + item.id + '"]');
                if (condCell && item.condition_summary) {
                    condCell.innerHTML = '<span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold ' + escapeHtml(item.condition_summary.classes || 'bg-emerald-500/15 text-emerald-700') + '">' + escapeHtml(item.condition_summary.label || 'Good') + '</span>';
                }

                const notesCell = document.querySelector('[data-item-notes-cell="' + item.id + '"]');
                if (notesCell && item.issue_notes) {
                    notesCell.textContent = item.issue_notes;
                }

                const actionCell = document.querySelector('[data-item-action-cell="' + item.id + '"]');
                if (actionCell && item.receiving_status === 'received' && CAN_REPORT_POST_RECEIPT) {
                    actionCell.innerHTML = '<div class="inline-flex flex-wrap items-center justify-end gap-1.5">'
                        + '<button type="button" onclick="window.openDamageReportModal(' + Number(item.id) + ')" class="px-3 py-1 rounded-full border border-rose-500/40 text-rose-700 text-[11px] font-semibold hover:bg-rose-500/10 cursor-pointer">Report Damage</button>'
                        + '<button type="button" onclick="window.openMissingReportModal(' + Number(item.id) + ')" class="px-3 py-1 rounded-full border border-amber-600/40 text-amber-800 text-[11px] font-semibold hover:bg-amber-500/10 cursor-pointer">Report Missing</button>'
                        + '</div>';
                }
            }
        }

        window.toggleManifestCamera = async function () {
            if (!window.NexoraCameraScanner) return;
            const isOpen = await window.NexoraCameraScanner.toggle(SCANNER_ID, function (code) {
                if (input) {
                    input.value = code;
                }
                return window.submitManifestParcelScan(code, true, 'received', null);
            });
            if (toggleLabel) {
                toggleLabel.textContent = isOpen ? 'Close Camera' : 'Open Camera';
            }
        };

        window.submitManifestParcelScan = async function (overrideCode, fromAutoScan, overrideClass, overrideNotes) {
            const rawCode = overrideCode !== undefined ? overrideCode : (input ? input.value : '');
            const trackingNumber = normalizeCode(rawCode);
            const classification = overrideClass || (classSelect ? classSelect.value : 'received') || 'received';
            const notes = overrideNotes !== undefined ? overrideNotes : (notesInput ? notesInput.value : null);

            if (input && trackingNumber) {
                input.value = trackingNumber;
            }
            if (!trackingNumber || !feedbackBox) return;

            const requestSeq = ++manifestRequestSeq;
            setManifestStatus('RECEIVING', 'Processing ' + trackingNumber + ' on manifest...', trackingNumber);

            try {
                const response = await fetch('{{ route('logistics.transfers.manifests.verify-parcel', $manifest) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    },
                    body: JSON.stringify({
                        tracking_number: trackingNumber,
                        classification: classification,
                        notes: notes || null,
                        auto_scan: Boolean(fromAutoScan),
                    }),
                });

                if (requestSeq !== manifestRequestSeq) {
                    return;
                }

                const payload = await response.json().catch(() => ({}));
                const outcome = payload.outcome || 'error';
                const msg = payload.message || 'Unable to process manifest parcel.';

                if (!response.ok) {
                    playFeedback('error');
                    const mapped = outcome === 'not_on_manifest' ? 'INVALID_BARCODE' : (outcome === 'unauthorized' ? 'INVALID_BARCODE' : 'ERROR');
                    setManifestStatus(mapped, msg, trackingNumber);
                    feedbackBox.classList.remove('hidden');
                    feedbackBox.className = 'mt-4 rounded-xl border-2 border-[#fca5a5] bg-[#fef2f2] p-4 text-xs text-[#991b1b]';
                    feedbackBox.innerHTML = '<div class="font-extrabold text-sm">' + escapeHtml(outcome === 'flagged_for_investigation' ? 'FLAGGED FOR INVESTIGATION' : 'SCAN REJECTED') + '</div><div class="mt-1">' + escapeHtml(msg) + '</div>';
                    scheduleResume(3000);
                    return;
                }

                updateManifestCountsAndRow(payload.counts, payload.item);

                if (outcome === 'already_received') {
                    playFeedback('error');
                    setManifestStatus('ALREADY_RECEIVED', msg, trackingNumber);
                    feedbackBox.classList.remove('hidden');
                    feedbackBox.className = 'mt-4 rounded-xl border-2 border-[#fcd34d] bg-[#fffbeb] p-4 text-xs text-[#92400e]';
                    feedbackBox.innerHTML = '<div class="font-extrabold text-sm">ALREADY RECEIVED</div><div class="mt-1 font-mono font-bold text-[#0f172a]">Tracking: ' + escapeHtml(payload.item ? payload.item.tracking_number : trackingNumber) + '</div><div class="mt-1">' + escapeHtml(msg) + '</div>';
                    scheduleResume(2500);
                    return;
                }

                if (outcome === 'received') {
                    if (payload.newly_received) {
                        manifestSessionCount += 1;
                        if (sessionCountEl) {
                            sessionCountEl.textContent = String(manifestSessionCount);
                        }
                    }
                    playFeedback('success');
                    setManifestStatus('RECEIVED', 'PARCEL RECEIVED — ' + (payload.item ? payload.item.tracking_number : trackingNumber), payload.item ? payload.item.tracking_number : trackingNumber);
                    feedbackBox.classList.remove('hidden');
                    feedbackBox.className = 'mt-4 rounded-xl border-2 border-[#86efac] bg-[#f0fdf4] p-4 text-xs text-[#166534]';
                    feedbackBox.innerHTML = `
                        <div class="inline-flex items-center gap-1.5 font-extrabold text-sm text-[#15803d]">
                            <span class="material-symbols-outlined text-[20px]">check_circle</span>
                            <span>PARCEL RECEIVED</span>
                        </div>
                        <div class="mt-1 font-mono text-sm font-bold text-[#0f172a]">Tracking: ${escapeHtml(payload.item ? payload.item.tracking_number : trackingNumber)}</div>
                        <div class="mt-0.5"><strong>Received at:</strong> ${escapeHtml(payload.destination_hub || 'Destination Hub')} · <strong>Session Count:</strong> ${manifestSessionCount}</div>
                    `;
                } else {
                    playFeedback('success');
                    setManifestStatus('RECEIVED', msg, trackingNumber);
                    feedbackBox.classList.remove('hidden');
                    feedbackBox.className = 'mt-4 rounded-xl border-2 border-[#fdba74] bg-[#fff7ed] p-4 text-xs text-[#924700]';
                    feedbackBox.innerHTML = '<div class="font-extrabold text-sm">EXCEPTION RECORDED</div><div class="mt-1">' + escapeHtml(msg) + '</div>';
                }

                if (input && !fromAutoScan) {
                    input.value = '';
                    input.focus();
                }
                if (notesInput && !fromAutoScan) notesInput.value = '';
                scheduleResume(1800);
            } catch (err) {
                if (requestSeq !== manifestRequestSeq) return;
                playFeedback('error');
                setManifestStatus('ERROR', 'Server connection error — please try again.', trackingNumber);
                feedbackBox.classList.remove('hidden');
                feedbackBox.className = 'mt-4 rounded-xl border-2 border-[#fca5a5] bg-[#fef2f2] p-4 text-xs text-[#991b1b]';
                feedbackBox.innerHTML = '<div class="font-extrabold text-sm">NETWORK ERROR</div><div class="mt-1">Unable to submit manifest scan right now. Check connection and try again.</div>';
                scheduleResume(3000);
            }
        };

        if (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                window.submitManifestParcelScan(
                    input ? input.value : '',
                    false,
                    classSelect ? classSelect.value : 'received',
                    notesInput ? notesInput.value : null
                );
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
