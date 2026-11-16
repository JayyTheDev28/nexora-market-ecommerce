@extends('layouts.app')

@section('title', 'Incident ' . ($incident->incident_number ?? '#' . $incident->id))

@section('content')
<div class="w-full min-h-screen bg-surface">
    <div class="flex flex-col lg:flex-row w-full">
        <x-logistics-nav active="exceptions" />

        <div class="flex-1 min-w-0 p-4 sm:p-6 lg:p-8 bg-surface">
            <div class="w-full max-w-7xl mx-auto space-y-6">

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <a href="{{ route('logistics.exceptions.index') }}" class="p-2 rounded-full border border-outline-variant text-on-surface-variant hover:text-on-surface">
                            <span class="material-symbols-outlined text-[20px]">arrow_back</span>
                        </a>
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h1 class="text-xl font-black text-on-surface font-mono">{{ $incident->incident_number ?? ('INC-' . $incident->id) }}</h1>
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase bg-primary/10 text-primary">{{ $incident->status_label }}</span>
                                @if($incident->isQuarantined())
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-purple-100 text-purple-800">QUARANTINED</span>
                                @endif
                                @if($incident->isOverdue())
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-100 text-rose-800">OVERDUE SLA</span>
                                @endif
                            </div>
                            <p class="text-xs text-on-surface-variant mt-0.5">
                                Tracking <strong class="font-mono text-on-surface">{{ $incident->tracking_number }}</strong>
                                @if($incident->manifest)
                                    • Manifest <a href="{{ route('logistics.transfers.manifests.show', $incident->manifest) }}" class="text-primary underline font-semibold">{{ $incident->manifest->manifest_number }}</a>
                                @endif
                                • Financial Hold: <strong>{{ strtoupper(str_replace('_', ' ', $incident->financial_hold_status ?? 'none')) }}</strong>
                            </p>
                        </div>
                    </div>
                </div>

                @if(session('status'))
                    <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm">{{ session('status') }}</div>
                @endif
                @if($errors->any())
                    <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs space-y-1">
                        @foreach($errors->all() as $err)
                            <div>• {{ $err }}</div>
                        @endforeach
                    </div>
                @endif

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
                    {{-- Left 2/3: Summary, Evidence, Immutable Timeline --}}
                    <div class="lg:col-span-2 space-y-6">
                        <div class="bg-surface-container-lowest p-5 rounded-xl border border-outline-variant space-y-4 text-xs">
                            <h2 class="text-sm font-bold text-on-surface border-b border-outline-variant pb-2">Incident Details &amp; Custody State</h2>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                <div><span class="text-on-surface-variant">Type:</span> <strong class="block uppercase">{{ $incident->incident_type }}</strong></div>
                                <div><span class="text-on-surface-variant">Source Stage:</span> <strong class="block">{{ str_replace('_', ' ', $incident->source_stage) }}</strong></div>
                                <div><span class="text-on-surface-variant">Handling Restriction:</span> <strong class="block">{{ str_replace('_', ' ', $incident->handling_restriction ?? 'none') }}</strong></div>
                                <div><span class="text-on-surface-variant">Disposition:</span> <strong class="block">{{ $incident->disposition_label ?? 'None yet' }}</strong></div>
                                <div><span class="text-on-surface-variant">Last Known Location:</span> <strong class="block">{{ $incident->last_known_location ?? 'Not recorded' }}</strong></div>
                                <div><span class="text-on-surface-variant">Reported By:</span> <strong class="block">{{ $incident->reporter?->full_name ?? 'System' }} ({{ $incident->reported_at?->format('M j, Y H:i') }})</strong></div>
                            </div>
                            <div class="p-3 rounded-lg bg-surface-container-low border border-outline-variant">
                                <span class="font-bold text-on-surface-variant uppercase text-[10px]">Reported Description</span>
                                <p class="text-on-surface mt-1 whitespace-pre-line">{{ $incident->description }}</p>
                            </div>
                            @if($incident->evidence_url || $incident->recovery_evidence_url)
                                <div class="flex flex-wrap gap-4">
                                    @if($incident->evidence_url)
                                        <div>
                                            <span class="block font-bold text-[10px] uppercase text-on-surface-variant mb-1">Incident Evidence</span>
                                            <a href="{{ $incident->evidence_url }}" target="_blank" class="text-primary underline font-semibold">View Uploaded Evidence</a>
                                        </div>
                                    @endif
                                    @if($incident->recovery_evidence_url)
                                        <div>
                                            <span class="block font-bold text-[10px] uppercase text-on-surface-variant mb-1">Recovery Evidence</span>
                                            <a href="{{ $incident->recovery_evidence_url }}" target="_blank" class="text-emerald-700 underline font-semibold">View Recovery Photo</a>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>

                        {{-- Immutable Investigation & Custody Timeline --}}
                        <div class="bg-surface-container-lowest p-5 rounded-xl border border-outline-variant space-y-4">
                            <h2 class="text-sm font-bold text-on-surface border-b border-outline-variant pb-2">Immutable Investigation &amp; Custody Timeline</h2>
                            <div class="space-y-3 text-xs">
                                @forelse($incident->events as $event)
                                    <div class="p-3 rounded-lg bg-surface-container-low border border-outline-variant/80 flex flex-col gap-1">
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="font-bold text-on-surface uppercase text-[11px]">{{ str_replace('_', ' ', $event->event_type) }}</span>
                                            <span class="text-[11px] text-on-surface-variant">{{ $event->occurred_at?->format('M j, Y g:i A') }}</span>
                                        </div>
                                        @if($event->notes)
                                            <p class="text-on-surface">{{ $event->notes }}</p>
                                        @endif
                                        <div class="text-[11px] text-on-surface-variant flex flex-wrap gap-3">
                                            <span>By: {{ $event->actor?->full_name ?? 'System' }} ({{ $event->actor_role ?? 'system' }})</span>
                                            @if($event->location)
                                                <span>Location: {{ $event->location }}</span>
                                            @endif
                                            @if($event->disposition)
                                                <span>Disposition: {{ str_replace('_', ' ', $event->disposition) }}</span>
                                            @endif
                                            @if($event->evidence_url)
                                                <a href="{{ $event->evidence_url }}" target="_blank" class="text-primary underline">Attachment</a>
                                            @endif
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-xs text-on-surface-variant">No timeline events logged yet.</p>
                                @endforelse
                            </div>

                            @if($incident->isUnresolved())
                                <form method="POST" action="{{ route('logistics.exceptions.events', $incident) }}" enctype="multipart/form-data" class="pt-3 border-t border-outline-variant space-y-3 text-xs">
                                    @csrf
                                    <h3 class="font-bold text-on-surface">Log Investigation Note / Update Evidence</h3>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <select name="investigation_status" class="px-3 py-2 rounded-lg border border-outline-variant bg-surface">
                                            <option value="">Keep current status ({{ $incident->status_label }})</option>
                                            <option value="under_investigation">Under Investigation</option>
                                            <option value="awaiting_evidence">Awaiting Evidence</option>
                                            <option value="awaiting_disposition">Awaiting Disposition</option>
                                        </select>
                                        <input type="text" name="location" placeholder="Current location / hub area (optional)" class="px-3 py-2 rounded-lg border border-outline-variant bg-surface">
                                    </div>
                                    <textarea name="notes" rows="2" required placeholder="Enter verified investigation findings..." class="w-full px-3 py-2 rounded-lg border border-outline-variant bg-surface"></textarea>
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <input type="file" name="evidence_photo" accept="image/*,.pdf" class="text-[11px]">
                                        <button type="submit" class="px-4 py-2 rounded-lg bg-primary text-on-primary font-bold">Add Log Entry</button>
                                    </div>
                                </form>
                            @endif
                        </div>
                    </div>

                    {{-- Right 1/3: Action Panels --}}
                    <div class="space-y-5 text-xs">
                        {{-- Assign Investigator --}}
                        <form method="POST" action="{{ route('logistics.exceptions.assign', $incident) }}" class="bg-surface-container-lowest p-4 rounded-xl border border-outline-variant space-y-3">
                            @csrf
                            <h3 class="font-bold text-on-surface text-sm">Assign Investigator</h3>
                            <select name="assigned_to" required class="w-full px-3 py-2 rounded-lg border border-outline-variant bg-surface">
                                @foreach($investigators as $inv)
                                    <option value="{{ $inv->id }}" {{ (int) $incident->assigned_to === (int) $inv->id ? 'selected' : '' }}>{{ $inv->full_name }} ({{ $inv->role }})</option>
                                @endforeach
                            </select>
                            <input type="text" name="notes" placeholder="Assignment instruction (optional)" class="w-full px-3 py-2 rounded-lg border border-outline-variant bg-surface">
                            <button type="submit" class="w-full py-2 rounded-lg border border-primary text-primary font-bold hover:bg-primary/5">Assign Investigator</button>
                        </form>

                        @if($incident->isUnresolved())
                            {{-- Missing Recovery Verification --}}
                            @if($incident->incident_type === 'missing')
                                <form method="POST" action="{{ route('logistics.exceptions.recover', $incident) }}" enctype="multipart/form-data" class="bg-emerald-50 p-4 rounded-xl border border-emerald-200 space-y-3">
                                    @csrf
                                    <h3 class="font-bold text-emerald-900 text-sm">Verify Missing Parcel Recovery</h3>
                                    <input type="text" name="recovery_location" required placeholder="Exact location found (e.g. Bay 3 Shelf B)" class="w-full px-3 py-2 rounded-lg border border-emerald-300 bg-white">
                                    <textarea name="notes" rows="2" required placeholder="Recovery verification notes..." class="w-full px-3 py-2 rounded-lg border border-emerald-300 bg-white"></textarea>
                                    <label class="flex items-center gap-2 font-semibold text-emerald-900">
                                        <input type="checkbox" name="confirm_physical_receipt" value="1" checked class="rounded">
                                        Confirm Physical Receipt &amp; Resolve Incident
                                    </label>
                                    <input type="file" name="recovery_evidence" accept="image/*" class="text-[11px] w-full">
                                    <button type="submit" class="w-full py-2 rounded-lg bg-emerald-600 text-white font-bold">Confirm Located / Recovered</button>
                                </form>
                            @endif

                            {{-- Place in Quarantine --}}
                            @if(! $incident->isQuarantined() && in_array($incident->incident_type, ['damage', 'discrepancy'], true))
                                <form method="POST" action="{{ route('logistics.exceptions.quarantine', $incident) }}" class="bg-purple-50 p-4 rounded-xl border border-purple-200 space-y-3">
                                    @csrf
                                    <h3 class="font-bold text-purple-900 text-sm">Place Into Hub Quarantine</h3>
                                    <input type="text" name="location" placeholder="Quarantine cage / hub area" class="w-full px-3 py-2 rounded-lg border border-purple-300 bg-white">
                                    <textarea name="notes" rows="2" required placeholder="Reason for holding parcel in quarantine..." class="w-full px-3 py-2 rounded-lg border border-purple-300 bg-white"></textarea>
                                    <button type="submit" class="w-full py-2 rounded-lg bg-purple-700 text-white font-bold">Lock in Quarantine</button>
                                </form>
                            @endif

                            {{-- Wrong-Hub Rerouting --}}
                            @if($incident->incident_type === 'wrong_hub' || $incident->handling_restriction === 'reroute_required')
                                <form method="POST" action="{{ route('logistics.exceptions.reroute', $incident) }}" class="bg-amber-50 p-4 rounded-xl border border-amber-200 space-y-3">
                                    @csrf
                                    <h3 class="font-bold text-amber-900 text-sm">Authorize Parcel Reroute</h3>
                                    <select name="target_hub_id" required class="w-full px-3 py-2 rounded-lg border border-amber-300 bg-white">
                                        @foreach($allActiveHubs as $h)
                                            <option value="{{ $h->id }}" {{ (int) ($incident->expected_hub_id ?? $incident->order?->destination_hub_id) === (int) $h->id ? 'selected' : '' }}>{{ $h->name }} ({{ $h->code }})</option>
                                        @endforeach
                                    </select>
                                    <label class="flex items-center gap-2 font-semibold text-amber-900">
                                        <input type="checkbox" name="override_destination_hub" value="1" class="rounded">
                                        Update final destination hub to target
                                    </label>
                                    <textarea name="notes" rows="2" required placeholder="Authorization notes for reroute leg..." class="w-full px-3 py-2 rounded-lg border border-amber-300 bg-white"></textarea>
                                    <button type="submit" class="w-full py-2 rounded-lg bg-amber-600 text-white font-bold">Create Reroute Leg &amp; Resolve</button>
                                </form>
                            @endif

                            {{-- Final Disposition Panel --}}
                            <form method="POST" action="{{ route('logistics.exceptions.disposition', $incident) }}" enctype="multipart/form-data" class="bg-surface-container-lowest p-4 rounded-xl border border-outline-variant space-y-3">
                                @csrf
                                <h3 class="font-bold text-on-surface text-sm">Apply Final Disposition</h3>
                                <select name="disposition" required class="w-full px-3 py-2 rounded-lg border border-outline-variant bg-surface">
                                    @foreach(\App\Models\ParcelIncident::FINAL_DISPOSITIONS as $key)
                                        <option value="{{ $key }}">{{ \App\Models\ParcelIncident::ALL_DISPOSITIONS[$key] }}</option>
                                    @endforeach
                                </select>
                                <textarea name="resolution_notes" rows="3" required placeholder="Mandatory disposition & audit explanation..." class="w-full px-3 py-2 rounded-lg border border-outline-variant bg-surface"></textarea>
                                <label class="flex items-center gap-2 font-semibold text-on-surface">
                                    <input type="checkbox" name="close_immediately" value="1" checked class="rounded">
                                    Close incident immediately after disposition
                                </label>
                                <input type="file" name="evidence_photo" accept="image/*,.pdf" class="text-[11px] w-full">
                                <button type="submit" class="w-full py-2 rounded-lg bg-slate-900 text-white font-bold hover:bg-slate-800">Apply Disposition</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
