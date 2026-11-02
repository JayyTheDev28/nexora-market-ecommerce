@extends('layouts.app')

@section('title', 'Coverage Zones — Nexora Logistics')

@section('content')
<div class="w-full min-h-screen bg-surface">
    <div class="flex flex-col lg:flex-row w-full">
        <x-logistics-nav active="zones" />

        <div class="flex-1 min-w-0 p-4 sm:p-6 lg:p-8 bg-surface">
            <div class="w-full max-w-7xl mx-auto space-y-6">

                {{-- Header --}}
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
                    <div>
                        <h1 class="font-headline-lg text-2xl md:text-3xl font-bold text-on-surface">Coverage Zones</h1>
                        <p class="font-body-md text-sm text-on-surface-variant mt-1">Manage barangay-level operational areas, associate sorting hubs, and assign dedicated delivery riders.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('logistics.zones.create') }}" class="inline-flex items-center gap-2 bg-primary text-on-primary px-4 py-2.5 rounded-full font-label-md text-xs font-semibold shadow-xs hover:bg-primary/90 transition-all">
                            <span class="material-symbols-outlined text-[18px]">add_location_alt</span>
                            <span>Create New Zone</span>
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

                {{-- Zones Grid / List --}}
                @if ($zones->isEmpty())
                    <div class="bg-surface-container-low border border-outline-variant/80 rounded-2xl p-12 text-center">
                        <span class="material-symbols-outlined text-4xl text-on-surface-variant mb-3">share_location</span>
                        <h3 class="text-base font-semibold text-on-surface">No Zones Defined Yet</h3>
                        <p class="text-xs text-on-surface-variant mt-1 max-w-md mx-auto">Create zones to partition your coverage by city and barangays, and assign your active riders to handle pickup and delivery dispatch.</p>
                        <a href="{{ route('logistics.zones.create') }}" class="inline-flex items-center gap-2 mt-4 px-4 py-2 bg-primary text-on-primary rounded-xl text-xs font-semibold">
                            <span class="material-symbols-outlined text-[16px]">add</span>
                            <span>Define First Zone</span>
                        </a>
                    </div>
                @else
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach ($zones as $zone)
                            <div class="bg-surface-container-lowest border border-outline-variant/80 rounded-2xl p-5 shadow-xs flex flex-col justify-between hover:border-primary/40 transition-colors">
                                <div>
                                    <div class="flex items-start justify-between gap-3 mb-2">
                                        <div>
                                            <h3 class="text-base font-bold text-on-surface">{{ $zone->name }}</h3>
                                            <p class="text-xs text-primary font-medium mt-0.5 flex items-center gap-1">
                                                <span class="material-symbols-outlined text-[15px]">location_on</span>
                                                <span>{{ $zone->city_name }}, {{ $zone->province_name }}</span>
                                            </p>
                                        </div>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold shrink-0 {{ $zone->is_active ? 'bg-emerald-500/10 text-emerald-700 border border-emerald-500/20' : 'bg-surface-container-high text-on-surface-variant' }}">
                                            {{ $zone->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </div>

                                    @if ($zone->hub)
                                        <div class="flex items-center gap-1.5 text-xs text-on-surface-variant bg-surface-container-low px-2.5 py-1.5 rounded-lg my-2">
                                            <span class="material-symbols-outlined text-[16px] text-amber-600">warehouse</span>
                                            <span>Hub: <strong>{{ $zone->hub->name }}</strong></span>
                                        </div>
                                    @else
                                        <div class="flex items-center gap-1.5 text-xs text-on-surface-variant/70 italic px-2.5 py-1.5 rounded-lg my-2">
                                            <span class="material-symbols-outlined text-[16px]">warehouse</span>
                                            <span>No Hub Linked</span>
                                        </div>
                                    @endif

                                    {{-- Barangay Badges preview --}}
                                    <div class="mt-3">
                                        <p class="text-[11px] font-semibold text-on-surface-variant uppercase tracking-wider mb-1.5">
                                            Covered Barangays ({{ $zone->barangays_count }})
                                        </p>
                                        <div class="flex flex-wrap gap-1 max-h-20 overflow-y-auto">
                                            @foreach ($zone->barangays->take(6) as $zb)
                                                <span class="px-2 py-0.5 bg-surface-container-high text-on-surface text-[11px] rounded-md">
                                                    {{ $zb->barangay_name }}
                                                </span>
                                            @endforeach
                                            @if ($zone->barangays_count > 6)
                                                <span class="px-2 py-0.5 bg-surface-container-high text-primary font-semibold text-[11px] rounded-md">
                                                    +{{ $zone->barangays_count - 6 }} more
                                                </span>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Assigned Riders preview --}}
                                    <div class="mt-4 pt-3 border-t border-outline-variant/60">
                                        <p class="text-[11px] font-semibold text-on-surface-variant uppercase tracking-wider mb-1.5">
                                            Assigned Riders ({{ $zone->riders_count }})
                                        </p>
                                        @if ($zone->riders->isEmpty())
                                            <p class="text-xs text-rose-500 flex items-center gap-1">
                                                <span class="material-symbols-outlined text-[14px]">warning</span>
                                                <span>No riders assigned yet</span>
                                            </p>
                                        @else
                                            <div class="flex flex-wrap gap-1.5">
                                                @foreach ($zone->riders as $rider)
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-primary/10 text-primary text-xs font-medium">
                                                        <span class="material-symbols-outlined text-[14px]">two_wheeler</span>
                                                        <span>{{ $rider->full_name }}</span>
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                {{-- Actions --}}
                                <div class="pt-4 mt-4 border-t border-outline-variant/60 flex items-center justify-end gap-2">
                                    <a href="{{ route('logistics.zones.edit', $zone) }}" class="px-3 py-1.5 rounded-lg border border-outline-variant hover:bg-surface-container text-xs font-medium text-on-surface transition-colors">
                                        Edit Zone
                                    </a>
                                    <form method="POST" action="{{ route('logistics.zones.destroy', $zone) }}" onsubmit="return confirm('Are you sure you want to delete this zone?');" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="px-3 py-1.5 rounded-lg border border-rose-500/30 text-xs font-medium text-rose-600 hover:bg-rose-500/10 transition-colors">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-6">
                        {{ $zones->links() }}
                    </div>
                @endif

            </div>
        </div>
    </div>
</div>
@endsection

