@extends('layouts.app')

@section('title', 'Hub Facilities — Nexora Logistics')

@section('content')
<div class="w-full min-h-screen bg-surface">
    <div class="flex flex-col lg:flex-row w-full">
        <x-logistics-nav active="hubs" />

        <div class="flex-1 min-w-0 p-4 sm:p-6 lg:p-8 bg-surface">
            <div class="w-full max-w-7xl mx-auto space-y-6">

                {{-- Header --}}
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
                    <div>
                        <h1 class="font-headline-lg text-2xl md:text-3xl font-bold text-on-surface">Hub Facilities</h1>
                        <p class="font-body-md text-sm text-on-surface-variant mt-1">Physical sorting centers and warehouses owned by your logistics company for receiving, cross-docking, and dispatching parcels.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('logistics.hubs.create') }}" class="inline-flex items-center gap-2 bg-primary text-on-primary px-4 py-2.5 rounded-full font-label-md text-xs font-semibold shadow-xs hover:bg-primary/90 transition-all">
                            <span class="material-symbols-outlined text-[18px]">add</span>
                            <span>Add New Hub</span>
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

                {{-- Hubs Grid --}}
                @if ($hubs->isEmpty())
                    <div class="bg-surface-container-low border border-outline-variant/80 rounded-2xl p-12 text-center">
                        <span class="material-symbols-outlined text-4xl text-on-surface-variant mb-3">warehouse</span>
                        <h3 class="text-base font-semibold text-on-surface">No Hubs Configured Yet</h3>
                        <p class="text-xs text-on-surface-variant mt-1 max-w-md mx-auto">Create physical receiving and sorting locations for your logistics company to manage parcel routing and transfers.</p>
                        <a href="{{ route('logistics.hubs.create') }}" class="inline-flex items-center gap-2 mt-4 px-4 py-2 bg-primary text-on-primary rounded-xl text-xs font-semibold">
                            <span class="material-symbols-outlined text-[16px]">add</span>
                            <span>Create First Hub</span>
                        </a>
                    </div>
                @else
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach ($hubs as $hub)
                            <div class="bg-surface-container-lowest border border-outline-variant/80 rounded-2xl p-5 shadow-xs flex flex-col justify-between hover:border-primary/40 transition-colors">
                                <div>
                                    <div class="flex items-start justify-between gap-3 mb-3">
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <h3 class="text-base font-bold text-on-surface">{{ $hub->name }}</h3>
                                                <span class="px-2 py-0.5 rounded-md text-[10px] font-mono font-bold bg-surface-container-high text-on-surface-variant uppercase">{{ $hub->code }}</span>
                                            </div>
                                            <p class="text-xs text-on-surface-variant mt-1 flex items-center gap-1.5">
                                                <span class="material-symbols-outlined text-[16px] text-primary">location_on</span>
                                                <span>{{ $hub->full_location }}</span>
                                            </p>
                                        </div>
                                        <span class="px-2.5 py-1 rounded-full text-[11px] font-semibold shrink-0 {{ $hub->is_active ? 'bg-emerald-500/10 text-emerald-700 border border-emerald-500/20' : 'bg-surface-container-high text-on-surface-variant border border-outline-variant' }}">
                                            {{ $hub->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </div>

                                    @if ($hub->contact_number)
                                        <p class="text-xs text-on-surface-variant mb-4 flex items-center gap-1.5">
                                            <span class="material-symbols-outlined text-[16px]">phone</span>
                                            <span>{{ $hub->contact_number }}</span>
                                        </p>
                                    @endif

                                    {{-- Facility Statistics --}}
                                    <div class="grid grid-cols-3 gap-2 bg-surface-container-low rounded-xl p-3 my-3 text-center">
                                        <a href="{{ route('logistics.hubs.show', $hub) }}" class="hover:bg-surface-container-high/60 rounded-lg p-1 transition-colors block">
                                            <p class="text-[10px] uppercase font-bold text-on-surface-variant">Parcels at Hub</p>
                                            <p class="text-base font-bold text-primary mt-0.5">{{ $hub->parcels_at_hub_count }}</p>
                                        </a>
                                        <div>
                                            <p class="text-[10px] uppercase font-bold text-on-surface-variant">Linked Zones</p>
                                            <p class="text-base font-bold text-on-surface mt-0.5">{{ $hub->zones_count }}</p>
                                        </div>
                                        <div>
                                            <p class="text-[10px] uppercase font-bold text-on-surface-variant">Incoming Transfers</p>
                                            <p class="text-base font-bold text-amber-600 mt-0.5">{{ $hub->in_transit_incoming_count }}</p>
                                        </div>
                                    </div>
                                </div>

                                {{-- Actions --}}
                                <div class="pt-4 mt-2 border-t border-outline-variant/60 flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('logistics.hubs.show', $hub) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-primary text-on-primary text-xs font-semibold hover:bg-primary/90 transition-colors shadow-xs">
                                            <span class="material-symbols-outlined text-[15px]">inventory_2</span>
                                            <span>Inventory ({{ $hub->parcels_at_hub_count }})</span>
                                        </a>
                                        <a href="{{ route('logistics.zones.index') }}" class="text-xs text-primary font-semibold hover:underline flex items-center gap-1 ml-2">
                                            <span class="material-symbols-outlined text-[14px]">share_location</span>
                                            <span>Coverage</span>
                                        </a>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('logistics.hubs.edit', $hub) }}" class="px-3 py-1.5 rounded-lg border border-outline-variant hover:bg-surface-container text-xs font-medium text-on-surface transition-colors">
                                            Edit
                                        </a>
                                        <form method="POST" action="{{ route('logistics.hubs.toggle-status', $hub) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="px-3 py-1.5 rounded-lg border border-outline-variant text-xs font-medium text-on-surface-variant hover:bg-surface-container transition-colors">
                                                {{ $hub->is_active ? 'Deactivate' : 'Activate' }}
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-6">
                        {{ $hubs->links() }}
                    </div>
                @endif

            </div>
        </div>
    </div>
</div>
@endsection

