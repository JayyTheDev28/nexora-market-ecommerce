@extends('layouts.app')

@section('title', 'Parcel Exceptions & Quarantine')

@section('content')
<div class="w-full min-h-screen bg-surface">
    <div class="flex flex-col lg:flex-row w-full">
        <x-logistics-nav active="exceptions" />

        <div class="flex-1 min-w-0 p-4 sm:p-6 lg:p-8 bg-surface">
            <div class="w-full max-w-7xl mx-auto space-y-6">

                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h1 class="text-xl sm:text-2xl font-bold text-on-surface tracking-tight">Parcel Exceptions &amp; Quarantine</h1>
                        <p class="text-xs sm:text-sm text-on-surface-variant mt-0.5">Centralized custody exception workflow for missing, damaged, quarantined, and misrouted parcels.</p>
                    </div>
                    <form method="POST" action="{{ route('logistics.exceptions.overdue-alerts') }}">
                        @csrf
                        <button type="submit" class="px-4 py-2 rounded-xl border border-rose-300 bg-rose-50 hover:bg-rose-100 text-rose-800 text-xs font-bold inline-flex items-center gap-1.5 transition-colors">
                            <span class="material-symbols-outlined text-[16px]">notification_important</span>
                            Check &amp; Alert Overdue SLA ({{ $kpis['overdue'] }})
                        </button>
                    </form>
                </div>

                @if(session('status'))
                    <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px]">check_circle</span>
                        {{ session('status') }}
                    </div>
                @endif

                {{-- KPI Strip --}}
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                    <a href="{{ route('logistics.exceptions.index', ['status' => 'active']) }}" class="p-4 rounded-xl bg-surface-container-lowest border border-outline-variant hover:border-primary/40 transition">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-on-surface-variant">Active Exceptions</p>
                        <p class="text-2xl font-black text-on-surface mt-1">{{ $kpis['active'] }}</p>
                    </a>
                    <a href="{{ route('logistics.exceptions.index', ['type' => 'missing', 'status' => 'active']) }}" class="p-4 rounded-xl bg-rose-50 border border-rose-200">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-rose-700">Missing Active</p>
                        <p class="text-2xl font-black text-rose-800 mt-1">{{ $kpis['missing'] }}</p>
                    </a>
                    <a href="{{ route('logistics.exceptions.index', ['quarantined' => 1, 'status' => 'active']) }}" class="p-4 rounded-xl bg-purple-50 border border-purple-200">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-purple-700">Quarantined</p>
                        <p class="text-2xl font-black text-purple-800 mt-1">{{ $kpis['quarantined'] }}</p>
                    </a>
                    <a href="{{ route('logistics.exceptions.index', ['type' => 'wrong_hub', 'status' => 'active']) }}" class="p-4 rounded-xl bg-amber-50 border border-amber-200">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-amber-700">Wrong-Hub</p>
                        <p class="text-2xl font-black text-amber-800 mt-1">{{ $kpis['wrong_hub'] }}</p>
                    </a>
                    <a href="{{ route('logistics.exceptions.index', ['overdue' => 1]) }}" class="p-4 rounded-xl bg-orange-50 border border-orange-200">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-orange-700">Overdue SLA</p>
                        <p class="text-2xl font-black text-orange-800 mt-1">{{ $kpis['overdue'] }}</p>
                    </a>
                    <a href="{{ route('logistics.exceptions.index', ['status' => 'resolved']) }}" class="p-4 rounded-xl bg-emerald-50 border border-emerald-200">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-emerald-700">Resolved / Closed</p>
                        <p class="text-2xl font-black text-emerald-800 mt-1">{{ $kpis['resolved'] }}</p>
                    </a>
                </div>

                {{-- Filter Bar --}}
                <form method="GET" action="{{ route('logistics.exceptions.index') }}" class="bg-surface-container-lowest p-4 rounded-xl border border-outline-variant grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="INC #, tracking #, manifest #..." class="px-3 py-2 rounded-lg border border-outline-variant bg-surface text-xs text-on-surface lg:col-span-2">
                    <select name="status" class="px-3 py-2 rounded-lg border border-outline-variant bg-surface text-xs text-on-surface">
                        <option value="">All Statuses</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>All Active / Unresolved</option>
                        @foreach(\App\Models\ParcelIncident::ALL_STATUSES as $st)
                            <option value="{{ $st }}" {{ request('status') === $st ? 'selected' : '' }}>{{ ucwords(str_replace('_', ' ', $st)) }}</option>
                        @endforeach
                    </select>
                    <select name="type" class="px-3 py-2 rounded-lg border border-outline-variant bg-surface text-xs text-on-surface">
                        <option value="">All Types</option>
                        @foreach(\App\Models\ParcelIncident::ALL_TYPES as $t)
                            <option value="{{ $t }}" {{ request('type') === $t ? 'selected' : '' }}>{{ ucwords(str_replace('_', ' ', $t)) }}</option>
                        @endforeach
                    </select>
                    <select name="hub_id" class="px-3 py-2 rounded-lg border border-outline-variant bg-surface text-xs text-on-surface">
                        <option value="">All Hubs</option>
                        @foreach($hubs as $h)
                            <option value="{{ $h->id }}" {{ (string) request('hub_id') === (string) $h->id ? 'selected' : '' }}>{{ $h->name }}</option>
                        @endforeach
                    </select>
                    <div class="flex items-center gap-2">
                        <button type="submit" class="flex-1 py-2 px-3 rounded-lg bg-primary text-on-primary text-xs font-bold">Filter</button>
                        <a href="{{ route('logistics.exceptions.index') }}" class="py-2 px-3 rounded-lg border border-outline-variant text-xs font-semibold text-on-surface-variant">Reset</a>
                    </div>
                </form>

                {{-- Table --}}
                <div class="bg-surface-container-lowest rounded-xl border border-outline-variant overflow-hidden shadow-xs">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="bg-surface-container-low border-b border-outline-variant text-[11px] uppercase tracking-wider text-on-surface-variant font-bold">
                                    <th class="px-4 py-3">Incident #</th>
                                    <th class="px-4 py-3">Tracking / Order</th>
                                    <th class="px-4 py-3">Type &amp; Stage</th>
                                    <th class="px-4 py-3">Investigation</th>
                                    <th class="px-4 py-3">Disposition / Hold</th>
                                    <th class="px-4 py-3">Location / SLA</th>
                                    <th class="px-4 py-3 text-right">Workspace</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline-variant/60">
                                @forelse($incidents as $incident)
                                    <tr class="hover:bg-surface-container-low/50">
                                        <td class="px-4 py-3 font-mono font-bold text-on-surface">
                                            {{ $incident->incident_number ?? ('INC-' . $incident->id) }}
                                            @if($incident->isOverdue())
                                                <span class="block mt-0.5 text-[10px] font-sans font-bold text-rose-600">OVERDUE SLA</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="font-mono font-bold text-on-surface">{{ $incident->tracking_number }}</div>
                                            @if($incident->manifest)
                                                <div class="text-[11px] text-on-surface-variant">Manifest {{ $incident->manifest->manifest_number }}</div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="px-2 py-0.5 rounded-full text-[11px] font-bold uppercase
                                                {{ $incident->incident_type === 'missing' ? 'bg-rose-100 text-rose-800' : ($incident->incident_type === 'wrong_hub' ? 'bg-amber-100 text-amber-800' : 'bg-orange-100 text-orange-800') }}">
                                                {{ str_replace('_', ' ', $incident->incident_type) }}
                                            </span>
                                            <div class="text-[11px] text-on-surface-variant mt-0.5">{{ str_replace('_', ' ', $incident->source_stage) }}</div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-surface-container text-on-surface border border-outline-variant">
                                                {{ $incident->status_label }}
                                            </span>
                                            @if($incident->assignee)
                                                <div class="text-[11px] text-on-surface-variant mt-0.5">Assignee: {{ $incident->assignee->full_name }}</div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            @if($incident->isQuarantined())
                                                <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-purple-100 text-purple-800">QUARANTINED</span>
                                            @elseif($incident->disposition)
                                                <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-sky-100 text-sky-800">{{ $incident->disposition_label }}</span>
                                            @else
                                                <span class="text-on-surface-variant">Pending Review</span>
                                            @endif
                                            @if($incident->financial_hold_status && $incident->financial_hold_status !== 'cleared' && $incident->financial_hold_status !== 'none')
                                                <div class="text-[10px] font-bold text-rose-700 mt-0.5">Fin Hold: {{ strtoupper(str_replace('_', ' ', $incident->financial_hold_status)) }}</div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-on-surface-variant">
                                            <div>{{ $incident->last_known_location ?: ($incident->hub?->name ?? 'Unrecorded') }}</div>
                                            <div class="text-[10px]">Reported {{ $incident->reported_at?->diffForHumans() }}</div>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <a href="{{ route('logistics.exceptions.show', $incident) }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-primary text-on-primary text-xs font-bold hover:opacity-90">
                                                Manage
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-4 py-10 text-center text-on-surface-variant">No parcel incidents match your current filter.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($incidents->hasPages())
                        <div class="p-4 border-t border-outline-variant">
                            {{ $incidents->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
