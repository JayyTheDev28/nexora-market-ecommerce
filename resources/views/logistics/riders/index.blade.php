@extends('layouts.app')

@section('title', 'Rider Management — Nexora Logistics')

@section('content')
<div class="w-full min-h-screen bg-surface" x-data="{ disapproveModal: false, activeRiderId: null, riderName: '', actionModal: false, actionType: 'deactivate' }">
    <div class="flex flex-col lg:flex-row w-full">

            <x-logistics-nav active="riders" />

            <div class="flex-1 min-w-0 p-4 sm:p-6 lg:p-8 bg-surface">
            <div class="w-full max-w-7xl mx-auto space-y-6">

        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="font-headline-lg text-2xl md:text-3xl font-bold text-on-surface">Rider Management</h1>
                <p class="font-body-md text-sm text-on-surface-variant mt-1">Review courier applications, assign delivery coverage zones, and monitor rider workload.</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a
                    href="{{ route('logistics.riders.create') }}"
                    class="inline-flex items-center gap-2 bg-primary text-on-primary px-4 py-2.5 rounded-full font-label-md text-xs font-semibold shadow-xs hover:bg-primary/90 transition-all cursor-pointer">
                    <span class="material-symbols-outlined text-[18px]">person_add</span>
                    <span>Add Rider</span>
                </a>
                <a
                    href="{{ route('logistics.couriers.create') }}"
                    class="inline-flex items-center gap-2 bg-indigo-600 text-white px-4 py-2.5 rounded-full font-label-md text-xs font-semibold shadow-xs hover:bg-indigo-700 transition-all cursor-pointer">
                    <span class="material-symbols-outlined text-[18px]">local_shipping</span>
                    <span>Create Transfer Courier</span>
                </a>
                <a
                    href="{{ route('logistics.zones.index') }}"
                    class="inline-flex items-center gap-2 bg-surface-container-high text-on-surface hover:bg-surface-container-highest px-4 py-2.5 rounded-full font-label-md text-xs font-semibold border border-outline-variant transition-all cursor-pointer">
                    <span class="material-symbols-outlined text-[18px]">share_location</span>
                    <span>Coverage Zones</span>
                </a>
            </div>
        </div>

        {{-- Personnel Category Switcher: Delivery Riders vs Transfer Couriers --}}
        <div class="flex flex-wrap items-center gap-2 mb-6">
            <a href="{{ route('logistics.riders.index', ['category' => 'all', 'tab' => $tab, 'q' => $search]) }}"
               class="px-4 py-2 rounded-full text-xs font-semibold border transition-all {{ ($category ?? 'all') === 'all' ? 'bg-on-surface text-surface border-on-surface' : 'bg-surface-container-lowest text-on-surface-variant border-outline-variant hover:border-primary' }}">
                All Personnel ({{ $categoryCounts['all'] ?? 0 }})
            </a>
            <a href="{{ route('logistics.riders.index', ['category' => 'riders', 'tab' => $tab, 'q' => $search]) }}"
               class="px-4 py-2 rounded-full text-xs font-semibold border transition-all {{ ($category ?? 'all') === 'riders' ? 'bg-primary text-on-primary border-primary' : 'bg-surface-container-lowest text-on-surface-variant border-outline-variant hover:border-primary' }}">
                Delivery Riders ({{ $categoryCounts['riders'] ?? 0 }})
            </a>
            <a href="{{ route('logistics.riders.index', ['category' => 'couriers', 'tab' => $tab, 'q' => $search]) }}"
               class="px-4 py-2 rounded-full text-xs font-semibold border transition-all {{ ($category ?? 'all') === 'couriers' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-surface-container-lowest text-on-surface-variant border-outline-variant hover:border-indigo-500' }}">
                Transfer Couriers ({{ $categoryCounts['couriers'] ?? 0 }})
            </a>
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

        {{-- Stat Cards --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <a href="{{ route('logistics.riders.index', ['tab' => 'pending_activation']) }}" class="bg-surface-container-lowest rounded-2xl border transition-all p-5 hover:border-primary {{ $tab === 'pending_activation' ? 'border-primary ring-2 ring-primary/10' : 'border-outline-variant' }}">
                <div class="flex items-center justify-between">
                    <span class="font-body-sm text-xs font-medium text-on-surface-variant">Pending Activation</span>
                    <span class="w-8 h-8 rounded-full bg-indigo-500/10 text-indigo-700 flex items-center justify-center">
                        <span class="material-symbols-outlined text-[18px]">mark_email_unread</span>
                    </span>
                </div>
                <p class="font-display-lg text-2xl font-bold text-on-surface mt-2">{{ $counts['pending_activation'] }}</p>
                <span class="text-[11px] text-indigo-700 font-medium">Awaiting rider password setup</span>
            </a>

            <a href="{{ route('logistics.riders.index', ['tab' => 'active']) }}" class="bg-surface-container-lowest rounded-2xl border transition-all p-5 hover:border-primary {{ $tab === 'active' ? 'border-primary ring-2 ring-primary/10' : 'border-outline-variant' }}">
                <div class="flex items-center justify-between">
                    <span class="font-body-sm text-xs font-medium text-on-surface-variant">Active Riders</span>
                    <span class="w-8 h-8 rounded-full bg-emerald-500/10 text-emerald-700 flex items-center justify-center">
                        <span class="material-symbols-outlined text-[18px]">check_circle</span>
                    </span>
                </div>
                <p class="font-display-lg text-2xl font-bold text-on-surface mt-2">{{ $counts['active'] }}</p>
                <span class="text-[11px] text-emerald-700 font-medium">Eligible for assignments</span>
            </a>

            <a href="{{ route('logistics.riders.index', ['tab' => 'inactive']) }}" class="bg-surface-container-lowest rounded-2xl border transition-all p-5 hover:border-primary {{ $tab === 'inactive' ? 'border-primary ring-2 ring-primary/10' : 'border-outline-variant' }}">
                <div class="flex items-center justify-between">
                    <span class="font-body-sm text-xs font-medium text-on-surface-variant">Deactivated</span>
                    <span class="w-8 h-8 rounded-full bg-rose-500/10 text-rose-700 flex items-center justify-center">
                        <span class="material-symbols-outlined text-[18px]">block</span>
                    </span>
                </div>
                <p class="font-display-lg text-2xl font-bold text-on-surface mt-2">{{ $counts['inactive'] }}</p>
                <span class="text-[11px] text-rose-600 font-medium">Assignments blocked</span>
            </a>

            <a href="{{ route('logistics.zones.index') }}" class="bg-surface-container-lowest rounded-2xl border border-outline-variant transition-all p-5 hover:border-primary">
                <div class="flex items-center justify-between">
                    <span class="font-body-sm text-xs font-medium text-on-surface-variant">Coverage Zones</span>
                    <span class="w-8 h-8 rounded-full bg-blue-500/10 text-blue-700 flex items-center justify-center">
                        <span class="material-symbols-outlined text-[18px]">share_location</span>
                    </span>
                </div>
                <p class="font-display-lg text-2xl font-bold text-on-surface mt-2">{{ $zones->count() }}</p>
                <span class="text-[11px] text-on-surface-variant">{{ $zones->filter(fn ($z) => $z->riders->isNotEmpty())->count() }} with assigned riders</span>
            </a>
        </div>

        {{-- Hub Delivery Coverage Zones Strip --}}
        <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-5 mb-8">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary text-[20px]">share_location</span>
                    <h3 class="font-headline-sm text-sm font-bold text-on-surface">Hub Delivery Coverage Zones</h3>
                </div>
                <a href="{{ route('logistics.zones.index') }}" class="text-xs text-primary font-semibold hover:underline inline-flex items-center gap-1">
                    <span>Manage Zones &amp; Barangays</span>
                    <span class="material-symbols-outlined text-[14px]">arrow_forward</span>
                </a>
            </div>

            @if($zones->isEmpty())
                <div class="p-4 rounded-xl bg-surface-container-low text-center">
                    <p class="text-xs text-on-surface-variant">No coverage zones configured yet. Visit <a href="{{ route('logistics.zones.index') }}" class="text-primary font-semibold underline">Coverage Zones</a> to configure zones and assign barangays.</p>
                </div>
            @else
                <div class="flex flex-wrap gap-2.5">
                    @foreach($zones as $zone)
                        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-surface-container border border-outline-variant text-xs">
                            <span class="font-semibold text-on-surface">{{ $zone->name }}</span>
                            <span class="text-[10px] text-on-surface-variant font-medium bg-surface px-1.5 py-0.5 rounded border border-outline-variant/60">
                                {{ $zone->city_name }}
                            </span>
                            @if($zone->riders->isNotEmpty())
                                <div class="flex items-center gap-1">
                                    @foreach($zone->riders as $zRider)
                                        <span class="px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-800 text-[10px] font-semibold flex items-center gap-1">
                                            <span class="material-symbols-outlined text-[12px]">two_wheeler</span>
                                            {{ $zRider->full_name }}
                                        </span>
                                    @endforeach
                                </div>
                            @else
                                <span class="px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-800 text-[10px] font-semibold">Unassigned</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Tabs & Search Filter --}}
        <div class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4 mb-4">
            <div class="flex items-center gap-2 overflow-x-auto pb-1 border-b border-outline-variant/60">
                <a href="{{ route('logistics.riders.index', ['tab' => 'active', 'q' => $search]) }}"
                   class="px-4 py-2 font-label-md text-xs font-semibold rounded-full transition-colors whitespace-nowrap {{ $tab === 'active' ? 'bg-primary text-on-primary shadow-xs' : 'text-on-surface-variant hover:bg-surface-container' }}">
                   Active Riders ({{ $counts['active'] }})
                </a>
                <a href="{{ route('logistics.riders.index', ['tab' => 'pending_activation', 'q' => $search]) }}"
                   class="px-4 py-2 font-label-md text-xs font-semibold rounded-full transition-colors whitespace-nowrap {{ $tab === 'pending_activation' ? 'bg-primary text-on-primary shadow-xs' : 'text-on-surface-variant hover:bg-surface-container' }}">
                   Pending Activation ({{ $counts['pending_activation'] }})
                </a>
                <a href="{{ route('logistics.riders.index', ['tab' => 'inactive', 'q' => $search]) }}"
                   class="px-4 py-2 font-label-md text-xs font-semibold rounded-full transition-colors whitespace-nowrap {{ $tab === 'inactive' ? 'bg-primary text-on-primary shadow-xs' : 'text-on-surface-variant hover:bg-surface-container' }}">
                   Deactivated ({{ $counts['inactive'] }})
                </a>
                <a href="{{ route('logistics.riders.index', ['tab' => 'all', 'q' => $search]) }}"
                   class="px-4 py-2 font-label-md text-xs font-semibold rounded-full transition-colors whitespace-nowrap {{ $tab === 'all' ? 'bg-primary text-on-primary shadow-xs' : 'text-on-surface-variant hover:bg-surface-container' }}">
                   All Riders ({{ $counts['all'] }})
                </a>
            </div>

            <form method="GET" action="{{ route('logistics.riders.index') }}" class="flex items-center gap-2">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <div class="relative flex-1 sm:w-64">
                    <span class="material-symbols-outlined text-[18px] text-on-surface-variant absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none">search</span>
                    <input
                        type="text" name="q" value="{{ $search }}"
                        placeholder="Search rider, email, plate..."
                        class="w-full pl-9 pr-3 py-2 text-xs rounded-full border border-outline-variant bg-surface-container-lowest focus:outline-none focus:ring-2 focus:ring-primary text-on-surface">
                </div>
                <button type="submit" class="px-4 py-2 rounded-full border border-outline-variant font-label-md text-xs font-semibold text-on-surface hover:bg-surface-container transition-colors">
                    Filter
                </button>
                @if($search)
                    <a href="{{ route('logistics.riders.index', ['tab' => $tab]) }}" class="text-xs text-on-surface-variant hover:text-on-surface">Reset</a>
                @endif
            </form>
        </div>

        {{-- Riders Table --}}
        <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant overflow-hidden shadow-xs">
            @if($riders->isEmpty())
                <div class="p-12 text-center flex flex-col items-center gap-2">
                    <span class="material-symbols-outlined text-[40px] text-outline">two_wheeler</span>
                    <p class="font-headline-sm text-sm font-bold text-on-surface">No riders found</p>
                    <p class="font-body-sm text-xs text-on-surface-variant">There are currently no courier records matching this filter.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-surface-container-low text-on-surface-variant border-b border-outline-variant font-label-md">
                            <tr>
                                <th class="px-5 py-3.5">Rider Details</th>
                                <th class="px-5 py-3.5">Vehicle & Plate</th>
                                <th class="px-5 py-3.5">Assigned Zones</th>
                                <th class="px-5 py-3.5">Workload</th>
                                <th class="px-5 py-3.5">Status</th>
                                <th class="px-5 py-3.5">Documents</th>
                                <th class="px-5 py-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant text-on-surface">
                            @foreach($riders as $rider)
                                <tr class="hover:bg-surface-container-low/50 transition-colors">
                                    <td class="px-5 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full bg-primary/10 text-primary font-bold text-xs flex items-center justify-center flex-shrink-0">
                                                {{ strtoupper(substr($rider->first_name, 0, 1)) }}
                                            </div>
                                            <div>
                                                <a href="{{ route('logistics.riders.show', $rider) }}" class="font-bold text-on-surface hover:text-primary transition-colors">
                                                    {{ $rider->full_name }}
                                                </a>
                                                <p class="text-[11px] text-on-surface-variant">{{ $rider->email }} · {{ $rider->contact_number }}</p>
                                                @if($rider->address)
                                                    <p class="text-[10px] text-on-surface-variant">{{ $rider->address->municipality_name }}, {{ $rider->address->province_name }}</p>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4">
                                        @if($rider->courierProfile)
                                            <p class="font-semibold text-on-surface">{{ $rider->courierProfile->vehicle_type }}</p>
                                            <div class="flex items-center gap-1.5 mt-0.5">
                                                <span class="inline-block px-2 py-0.5 rounded bg-surface-container font-mono text-[10px] text-on-surface font-semibold border border-outline-variant">
                                                    {{ $rider->courierProfile->plate_number }}
                                                </span>
                                                @if($rider->courierProfile->license_number)
                                                    <span class="inline-block px-1.5 py-0.5 rounded bg-surface-container font-mono text-[10px] text-on-surface-variant border border-outline-variant" title="Driver's License No.">
                                                        {{ $rider->courierProfile->license_number }}
                                                    </span>
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-on-surface-variant italic">No vehicle recorded</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4">
                                        @if($rider->assignedZones->isNotEmpty())
                                            <div class="flex flex-wrap gap-1">
                                                @foreach($rider->assignedZones as $zone)
                                                    <span class="px-2 py-0.5 rounded-md bg-secondary/10 text-secondary font-semibold text-[10px]" title="{{ $zone->city_name }}, {{ $zone->province_name }} ({{ $zone->barangays->count() }} barangays)">
                                                        {{ $zone->name }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @elseif($rider->deliveryAreas->isNotEmpty())
                                            <div class="flex flex-wrap gap-1">
                                                @foreach($rider->deliveryAreas as $area)
                                                    <span class="px-2 py-0.5 rounded-md bg-secondary/10 text-secondary font-semibold text-[10px]">
                                                        {{ $area->name }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-[11px] text-on-surface-variant italic">No zone assigned</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="flex flex-col gap-0.5">
                                            <span class="inline-flex items-center gap-1 font-semibold text-xs {{ $rider->active_deliveries_count > 0 ? 'text-primary' : 'text-on-surface-variant' }}">
                                                <span class="material-symbols-outlined text-[14px]">package_2</span>
                                                {{ $rider->active_deliveries_count }} active
                                            </span>
                                            <span class="text-[10px] text-on-surface-variant">{{ $rider->completed_deliveries_count }} delivered</span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4">
                                        @if($rider->isPendingActivation())
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-indigo-500/10 text-indigo-800 text-[11px] font-semibold">
                                                <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
                                                Pending Activation
                                            </span>
                                        @elseif($rider->isPending())
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-amber-500/10 text-amber-800 text-[11px] font-semibold">
                                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                                Pending Review
                                            </span>
                                        @elseif($rider->isDisapproved())
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-rose-500/10 text-rose-800 text-[11px] font-semibold" title="{{ $rider->disapproval_reason }}">
                                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                                Disapproved
                                            </span>
                                        @elseif($rider->isActive())
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-500/10 text-emerald-800 text-[11px] font-semibold">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                Active
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-slate-500/10 text-slate-700 text-[11px] font-semibold" title="{{ $rider->status_reason }}">
                                                <span class="w-1.5 h-1.5 rounded-full bg-slate-500"></span>
                                                Deactivated
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="flex flex-col gap-1 text-[11px]">
                                            @if($rider->valid_id_path || $rider->courierProfile?->license_path)
                                                <a href="{{ Storage::url($rider->valid_id_path ?? $rider->courierProfile?->license_path) }}" target="_blank" class="text-primary hover:underline flex items-center gap-1">
                                                    <span class="material-symbols-outlined text-[13px]">badge</span>
                                                    Driver License / ID
                                                </a>
                                            @endif
                                            @if($rider->courierProfile?->or_cr_path)
                                                <a href="{{ Storage::url($rider->courierProfile->or_cr_path) }}" target="_blank" class="text-primary hover:underline flex items-center gap-1">
                                                    <span class="material-symbols-outlined text-[13px]">description</span>
                                                    OR / CR
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            @if($rider->isPendingActivation())
                                                {{-- Resend Activation Email --}}
                                                <form method="POST" action="{{ route('logistics.riders.resend-activation', $rider) }}">
                                                    @csrf
                                                    <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full bg-indigo-50 border border-indigo-200 text-indigo-700 hover:bg-indigo-100 font-semibold text-[11px] transition-all cursor-pointer shadow-2xs" title="Resend 24-hour activation link">
                                                        <span class="material-symbols-outlined text-[14px]">send</span>
                                                        <span>Resend Link</span>
                                                    </button>
                                                </form>
                                            @elseif($rider->isPending())
                                                {{-- Approve --}}
                                                <form method="POST" action="{{ route('logistics.riders.approve', $rider) }}">
                                                    @csrf
                                                    <button type="submit" class="px-3 py-1.5 rounded-full bg-primary text-on-primary font-semibold text-[11px] hover:bg-primary/90 transition-all cursor-pointer">
                                                        Approve
                                                    </button>
                                                </form>

                                                {{-- Disapprove button --}}
                                                <button
                                                    type="button"
                                                    @click="disapproveModal = true; activeRiderId = {{ $rider->id }}; riderName = '{{ addslashes($rider->full_name) }}'"
                                                    class="px-3 py-1.5 rounded-full border border-rose-300 text-rose-700 hover:bg-rose-50 font-semibold text-[11px] transition-all cursor-pointer">
                                                    Disapprove
                                                </button>
                                            @elseif($rider->isApproved())
                                                {{-- Toggle Active / Deactivate --}}
                                                @if($rider->isActive())
                                                    <button
                                                        type="button"
                                                        @click="actionModal = true; activeRiderId = {{ $rider->id }}; riderName = '{{ addslashes($rider->full_name) }}'; actionType = 'deactivate'"
                                                        class="px-3 py-1.5 rounded-full border border-rose-300 text-rose-700 hover:bg-rose-50 font-semibold text-[11px] transition-all cursor-pointer">
                                                        Deactivate
                                                    </button>
                                                @else
                                                    <form method="POST" action="{{ route('logistics.riders.toggle-status', $rider) }}">
                                                        @csrf
                                                        <input type="hidden" name="action" value="activate">
                                                        <button type="submit" class="px-3 py-1.5 rounded-full border border-emerald-300 text-emerald-800 hover:bg-emerald-50 font-semibold text-[11px] transition-all cursor-pointer">
                                                            Activate
                                                        </button>
                                                    </form>
                                                @endif
                                            @endif

                                            <a href="{{ route('logistics.riders.show', $rider) }}" class="p-1.5 rounded-full hover:bg-surface-container text-on-surface-variant hover:text-on-surface transition-colors" title="View Full Profile">
                                                <span class="material-symbols-outlined text-[18px]">visibility</span>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($riders->hasPages())
                    <div class="px-6 py-4 border-t border-outline-variant bg-surface-container-low">
                        {{ $riders->links() }}
                    </div>
                @endif
            @endif
                    </div>
        </div>
    </div>
</div>



    {{-- Modal: Disapprove Application --}}
    <div x-show="disapproveModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
        <div @click.outside="disapproveModal = false" class="bg-surface-container-lowest rounded-3xl border border-outline-variant shadow-xl w-full max-w-md p-6">
            <h3 class="font-headline-sm text-base font-bold text-on-surface mb-2">Disapprove Courier Application</h3>
            <p class="font-body-sm text-xs text-on-surface-variant mb-4">
                Please provide a clear reason for declining <span class="font-semibold text-on-surface" x-text="riderName"></span>. This will be emailed to the applicant.
            </p>

            <form :action="'{{ url('/logistics/riders') }}/' + activeRiderId + '/disapprove'" method="POST" class="flex flex-col gap-4">
                @csrf
                <div class="flex flex-col gap-1.5">
                    <label for="disapprove_reason" class="font-label-md text-xs font-semibold text-on-surface-variant">Reason for Disapproval</label>
                    <textarea
                        id="disapprove_reason" name="reason" rows="3" required
                        placeholder="e.g. Incomplete or expired driver's license submitted..."
                        class="rounded-xl border border-outline-variant p-3 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary"></textarea>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <button type="button" @click="disapproveModal = false" class="px-4 py-2 rounded-full border border-outline-variant text-xs font-semibold text-on-surface hover:bg-surface-container">
                        Cancel
                    </button>
                    <button type="submit" class="px-5 py-2 rounded-full bg-rose-600 text-white text-xs font-semibold hover:bg-rose-700">
                        Confirm Disapproval
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal: Deactivate Rider --}}
    <div x-show="actionModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
        <div @click.outside="actionModal = false" class="bg-surface-container-lowest rounded-3xl border border-outline-variant shadow-xl w-full max-w-md p-6">
            <h3 class="font-headline-sm text-base font-bold text-on-surface mb-2">Deactivate Rider Account</h3>
            <p class="font-body-sm text-xs text-on-surface-variant mb-4">
                Deactivating <span class="font-semibold text-on-surface" x-text="riderName"></span> will immediately prevent them from receiving new delivery assignments.
            </p>

            <form :action="'{{ url('/logistics/riders') }}/' + activeRiderId + '/toggle-status'" method="POST" class="flex flex-col gap-4">
                @csrf
                <input type="hidden" name="action" value="deactivate">
                <div class="flex flex-col gap-1.5">
                    <label for="deactivate_reason" class="font-label-md text-xs font-semibold text-on-surface-variant">Reason for Deactivation</label>
                    <textarea
                        id="deactivate_reason" name="reason" rows="3"
                        placeholder="e.g. Temporary leave of absence / disciplinary review..."
                        class="rounded-xl border border-outline-variant p-3 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary"></textarea>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <button type="button" @click="actionModal = false" class="px-4 py-2 rounded-full border border-outline-variant text-xs font-semibold text-on-surface hover:bg-surface-container">
                        Cancel
                    </button>
                    <button type="submit" class="px-5 py-2 rounded-full bg-rose-600 text-white text-xs font-semibold hover:bg-rose-700">
                        Confirm Deactivation
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
