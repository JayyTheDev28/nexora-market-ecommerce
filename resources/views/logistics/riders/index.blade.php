@extends('layouts.app')

@section('title', 'Rider Management — Nexora Logistics')

@section('content')
<div class="w-full bg-surface py-8 min-h-[75vh]" x-data="{ addAreaModal: false, disapproveModal: false, activeRiderId: null, riderName: '', actionModal: false, actionType: 'deactivate' }">
    <div class="max-w-none px-margin-mobile md:px-margin-desktop">

        <x-logistics-nav active="riders" />

        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="font-headline-lg text-2xl md:text-3xl font-bold text-on-surface">Rider Management</h1>
                <p class="font-body-md text-sm text-on-surface-variant mt-1">Review courier applications, assign delivery coverage areas, and monitor rider workload.</p>
            </div>
            <div class="flex items-center gap-3">
                <a
                    href="{{ route('logistics.riders.create') }}"
                    class="inline-flex items-center gap-2 bg-primary text-on-primary px-4 py-2.5 rounded-full font-label-md text-xs font-semibold shadow-xs hover:bg-primary/90 transition-all cursor-pointer">
                    <span class="material-symbols-outlined text-[18px]">person_add</span>
                    <span>Add Rider</span>
                </a>
                <button
                    type="button"
                    @click="addAreaModal = true"
                    class="inline-flex items-center gap-2 bg-secondary text-on-secondary px-4 py-2.5 rounded-full font-label-md text-xs font-semibold shadow-xs hover:bg-secondary/90 transition-all cursor-pointer">
                    <span class="material-symbols-outlined text-[18px]">add_location_alt</span>
                    <span>New Delivery Area</span>
                </button>
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

            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-5">
                <div class="flex items-center justify-between">
                    <span class="font-body-sm text-xs font-medium text-on-surface-variant">Delivery Areas</span>
                    <span class="w-8 h-8 rounded-full bg-blue-500/10 text-blue-700 flex items-center justify-center">
                        <span class="material-symbols-outlined text-[18px]">map</span>
                    </span>
                </div>
                <p class="font-display-lg text-2xl font-bold text-on-surface mt-2">{{ $deliveryAreas->count() }}</p>
                <span class="text-[11px] text-on-surface-variant">{{ $deliveryAreas->whereNotNull('courier_id')->count() }} assigned with riders</span>
            </div>
        </div>

        {{-- Managed Delivery Areas Strip --}}
        <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-5 mb-8">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary text-[20px]">explore</span>
                    <h3 class="font-headline-sm text-sm font-bold text-on-surface">Hub Delivery Coverage Areas</h3>
                </div>
                <span class="text-xs text-on-surface-variant">Assigned riders handle destination parcels in these areas</span>
            </div>

            @if($deliveryAreas->isEmpty())
                <div class="p-4 rounded-xl bg-surface-container-low text-center">
                    <p class="text-xs text-on-surface-variant">No delivery areas configured yet. Click "New Delivery Area" above to define areas (e.g. Area A - Santa Cruz).</p>
                </div>
            @else
                <div class="flex flex-wrap gap-2.5">
                    @foreach($deliveryAreas as $area)
                        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-surface-container border border-outline-variant text-xs">
                            <span class="font-semibold text-on-surface">{{ $area->name }}</span>
                            @if($area->courier)
                                <span class="px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-800 text-[10px] font-semibold flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[12px]">two_wheeler</span>
                                    {{ $area->courier->full_name }}
                                </span>
                            @else
                                <span class="px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-800 text-[10px] font-semibold">Unassigned</span>
                            @endif
                            <form method="POST" action="{{ route('logistics.areas.destroy', $area) }}" onsubmit="return confirm('Remove delivery area \'{{ addslashes($area->name) }}\'?');" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-on-surface-variant hover:text-error transition-colors p-0.5" title="Remove Area">
                                    <span class="material-symbols-outlined text-[14px]">close</span>
                                </button>
                            </form>
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
                    <span class="material-symbols-outlined text-[18px] text-on-surface-variant absolute left-3 top-2.5">search</span>
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
                                <th class="px-5 py-3.5">Assigned Areas</th>
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
                                        @if($rider->deliveryAreas->isNotEmpty())
                                            <div class="flex flex-wrap gap-1">
                                                @foreach($rider->deliveryAreas as $area)
                                                    <span class="px-2 py-0.5 rounded-md bg-secondary/10 text-secondary font-semibold text-[10px]">
                                                        {{ $area->name }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-[11px] text-on-surface-variant italic">No area assigned</span>
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

    {{-- Modal: Create New Delivery Area --}}
    <div x-show="addAreaModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
        <div @click.outside="addAreaModal = false" class="bg-surface-container-lowest rounded-3xl border border-outline-variant shadow-xl w-full max-w-md p-6 overflow-hidden">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary text-[22px]">add_location_alt</span>
                    <h3 class="font-headline-sm text-base font-bold text-on-surface">New Hub Delivery Area</h3>
                </div>
                <button type="button" @click="addAreaModal = false" class="text-on-surface-variant hover:text-on-surface p-1">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>

            <form method="POST" action="{{ route('logistics.areas.store') }}" class="flex flex-col gap-4">
                @csrf
                <div class="flex flex-col gap-1.5">
                    <label for="name" class="font-label-md text-xs font-semibold text-on-surface-variant">Area Name / Coverage</label>
                    <input
                        type="text" id="name" name="name" required placeholder="e.g. Area A - Santa Cruz, Laguna"
                        class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                    <span class="text-[11px] text-on-surface-variant">Example: Area A (Santa Cruz), Area B (Pagsanjan), Area C (Los Baños)</span>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="courier_id" class="font-label-md text-xs font-semibold text-on-surface-variant">Assign Initial Rider (Optional)</label>
                    <select
                        id="courier_id" name="courier_id"
                        class="rounded-xl border border-outline-variant px-3 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                        <option value="">No rider assigned initially</option>
                        @foreach($riders->where('approval_status', 'approved')->where('account_status', 'active') as $activeRider)
                            <option value="{{ $activeRider->id }}">{{ $activeRider->full_name }} ({{ $activeRider->courierProfile?->vehicle_type ?? 'Courier' }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center justify-end gap-3 mt-2">
                    <button type="button" @click="addAreaModal = false" class="px-4 py-2 rounded-full border border-outline-variant text-xs font-semibold text-on-surface hover:bg-surface-container">
                        Cancel
                    </button>
                    <button type="submit" class="px-5 py-2 rounded-full bg-primary text-on-primary text-xs font-semibold hover:bg-primary/90">
                        Create Area
                    </button>
                </div>
            </form>
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
