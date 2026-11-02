@extends('layouts.app')

@section('title', 'Edit Coverage Zone — Nexora Logistics')

@section('content')
<div class="w-full min-h-screen bg-surface">
    <div class="flex flex-col lg:flex-row w-full">
        <x-logistics-nav active="zones" />

        <div class="flex-1 min-w-0 p-4 sm:p-6 lg:p-8 bg-surface">
            <div class="w-full max-w-4xl mx-auto space-y-6">

                <div class="flex items-center gap-3 mb-6">
                    <a href="{{ route('logistics.zones.index') }}" class="w-9 h-9 rounded-xl border border-outline-variant bg-surface-container flex items-center justify-center text-on-surface hover:bg-surface-container-high transition-colors">
                        <span class="material-symbols-outlined text-[20px]">arrow_back</span>
                    </a>
                    <div>
                        <h1 class="font-headline-lg text-2xl font-bold text-on-surface">Edit Coverage Zone</h1>
                        <p class="font-body-md text-xs text-on-surface-variant">Update barangay boundaries, hub association, and assigned riders for {{ $zone->name }}.</p>
                    </div>
                </div>

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

                <form method="POST" action="{{ route('logistics.zones.update', $zone) }}"
                      x-data="zoneEditor()"
                      class="bg-surface-container-lowest border border-outline-variant/80 rounded-2xl p-6 shadow-xs space-y-6">
                    @csrf
                    @method('PUT')

                    {{-- Zone Details --}}
                    <div>
                        <h2 class="text-xs font-bold uppercase tracking-wider text-primary mb-3">Zone Information</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-on-surface mb-1.5">Zone Name <span class="text-rose-500">*</span></label>
                                <input type="text" name="name" value="{{ old('name', $zone->name) }}" required
                                       class="w-full px-3.5 py-2.5 rounded-xl border border-outline-variant bg-surface text-xs text-on-surface focus:outline-none focus:border-primary">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-on-surface mb-1.5">Associated Sorting Hub</label>
                                <select name="hub_id" class="w-full px-3.5 py-2.5 rounded-xl border border-outline-variant bg-surface text-xs text-on-surface focus:outline-none focus:border-primary">
                                    <option value="">No Hub (Standalone Zone)</option>
                                    @foreach ($hubs as $hub)
                                        <option value="{{ $hub->id }}" {{ old('hub_id', $zone->hub_id) == $hub->id ? 'selected' : '' }}>
                                            {{ $hub->name }} ({{ $hub->code }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    {{-- Location Hierarchy (Province & City) --}}
                    <div class="pt-4 border-t border-outline-variant/60">
                        <h2 class="text-xs font-bold uppercase tracking-wider text-primary mb-3">Geographic Region</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            {{-- Province --}}
                            <div>
                                <label class="block text-xs font-medium text-on-surface mb-1.5">Province <span class="text-rose-500">*</span></label>
                                <select x-model="selectedProvinceCode" @change="onProvinceChange()" required
                                        class="w-full px-3.5 py-2.5 rounded-xl border border-outline-variant bg-surface text-xs text-on-surface focus:outline-none focus:border-primary">
                                    <option value="">Select Province</option>
                                    <template x-for="p in provinces" :key="p.code">
                                        <option :value="p.code" x-text="p.name" :selected="p.code === selectedProvinceCode"></option>
                                    </template>
                                </select>
                                <input type="hidden" name="province_code" :value="selectedProvinceCode">
                                <input type="hidden" name="province_name" :value="selectedProvinceName">
                            </div>

                            {{-- City --}}
                            <div>
                                <label class="block text-xs font-medium text-on-surface mb-1.5">City / Municipality <span class="text-rose-500">*</span></label>
                                <select x-model="selectedCityCode" @change="onCityChange()" :disabled="!selectedProvinceCode" required
                                        class="w-full px-3.5 py-2.5 rounded-xl border border-outline-variant bg-surface text-xs text-on-surface focus:outline-none focus:border-primary disabled:opacity-50">
                                    <option value="">Select City/Municipality</option>
                                    <template x-for="c in cities" :key="c.code">
                                        <option :value="c.code" x-text="c.name" :selected="c.code === selectedCityCode"></option>
                                    </template>
                                </select>
                                <input type="hidden" name="city_code" :value="selectedCityCode">
                                <input type="hidden" name="city_name" :value="selectedCityName">
                            </div>
                        </div>
                    </div>

                    {{-- Barangay Picker with Coverage Indicator --}}
                    <div class="pt-4 border-t border-outline-variant/60">
                        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 mb-3">
                            <div>
                                <h2 class="text-xs font-bold uppercase tracking-wider text-primary">Barangay Coverage Selection</h2>
                                <p class="text-[11px] text-on-surface-variant">Each barangay belongs to exactly one zone. Grayed out items are covered by your other zones.</p>
                            </div>
                            <div class="flex items-center gap-2" x-show="barangays.length > 0">
                                <button type="button" @click="selectAllAvailable()" class="px-2.5 py-1 text-[11px] font-semibold bg-surface-container rounded-lg border border-outline-variant hover:bg-surface-container-high text-on-surface">
                                    Select All Available
                                </button>
                                <button type="button" @click="deselectAll()" class="px-2.5 py-1 text-[11px] font-semibold bg-surface-container rounded-lg border border-outline-variant hover:bg-surface-container-high text-on-surface-variant">
                                    Clear Selection
                                </button>
                            </div>
                        </div>

                        {{-- Loading / Empty state --}}
                        <div x-show="loading" class="p-8 text-center bg-surface-container-low rounded-xl">
                            <span class="material-symbols-outlined animate-spin text-2xl text-primary mb-1">progress_activity</span>
                            <p class="text-xs text-on-surface-variant">Loading barangays and verifying coverage status...</p>
                        </div>

                        {{-- Coverage Summary Stats --}}
                        <div x-show="!loading && barangays.length > 0" class="mb-3 flex items-center gap-3 text-xs">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-500/10 text-emerald-700 font-medium">
                                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                <span x-text="selectedCount + ' In this Zone'"></span>
                            </span>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-surface-container-high text-on-surface-variant font-medium">
                                <span class="w-2 h-2 rounded-full bg-outline"></span>
                                <span x-text="availableCount + ' Available in ' + selectedCityName"></span>
                            </span>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-amber-500/10 text-amber-700 font-medium">
                                <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                                <span x-text="otherCoveredCount + ' In Other Zones'"></span>
                            </span>
                        </div>

                        {{-- Barangays Grid --}}
                        <div x-show="!loading && barangays.length > 0" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2.5 max-h-80 overflow-y-auto p-1">
                            <template x-for="b in barangays" :key="b.code">
                                <label :class="{
                                    'opacity-50 bg-surface-container-low cursor-not-allowed border-dashed': isOtherZoneCovered(b),
                                    'border-primary/60 bg-primary/5': selectedBarangayCodes.includes(b.code),
                                    'hover:border-primary/40 bg-surface-container-lowest': !isOtherZoneCovered(b) && !selectedBarangayCodes.includes(b.code)
                                }" class="flex items-start gap-2.5 p-3 rounded-xl border border-outline-variant transition-all cursor-pointer">
                                    <input type="checkbox" name="barangay_codes[]" :value="b.code"
                                           :disabled="isOtherZoneCovered(b)"
                                           :checked="selectedBarangayCodes.includes(b.code)"
                                           @change="toggleBarangay(b.code)"
                                           class="mt-0.5 rounded text-primary focus:ring-primary disabled:opacity-40">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs font-semibold text-on-surface truncate" x-text="b.name"></p>
                                        <template x-if="isOtherZoneCovered(b)">
                                            <span class="inline-flex items-center gap-1 mt-1 text-[10px] font-semibold text-amber-700 bg-amber-500/10 px-1.5 py-0.5 rounded">
                                                <span class="material-symbols-outlined text-[12px]">lock</span>
                                                <span x-text="'In ' + (b.covered_by_zone_name || 'other zone')"></span>
                                            </span>
                                        </template>
                                        <template x-if="!isOtherZoneCovered(b)">
                                            <span class="inline-flex items-center gap-1 mt-1 text-[10px] font-medium text-emerald-700 bg-emerald-500/10 px-1.5 py-0.5 rounded">
                                                Available
                                            </span>
                                        </template>
                                    </div>
                                </label>
                            </template>
                        </div>
                    </div>

                    {{-- Rider Assignment --}}
                    <div class="pt-4 border-t border-outline-variant/60">
                        <h2 class="text-xs font-bold uppercase tracking-wider text-primary mb-2">Assign Dedicated Riders</h2>
                        <p class="text-[11px] text-on-surface-variant mb-3">Riders assigned to this zone will receive direct pickup and delivery jobs for these barangays.</p>

                        @if ($riders->isEmpty())
                            <div class="p-4 bg-surface-container-low rounded-xl text-xs text-on-surface-variant flex items-center gap-2">
                                <span class="material-symbols-outlined text-amber-600 text-[18px]">info</span>
                                <span>No approved riders currently registered under your company.</span>
                            </div>
                        @else
                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2.5">
                                @foreach ($riders as $rider)
                                    <label class="flex items-center gap-2.5 p-3 rounded-xl border border-outline-variant hover:border-primary/50 bg-surface-container-lowest cursor-pointer">
                                        <input type="checkbox" name="rider_ids[]" value="{{ $rider->id }}"
                                               {{ in_array($rider->id, old('rider_ids', $selectedRiderIds)) ? 'checked' : '' }}
                                               class="rounded text-primary focus:ring-primary">
                                        <div class="min-w-0">
                                            <p class="text-xs font-semibold text-on-surface">{{ $rider->full_name }}</p>
                                            <p class="text-[11px] text-on-surface-variant">{{ $rider->phone_number ?? $rider->email }}</p>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    {{-- Actions --}}
                    <div class="pt-4 border-t border-outline-variant/60 flex items-center justify-end gap-3">
                        <a href="{{ route('logistics.zones.index') }}" class="px-4 py-2.5 rounded-xl border border-outline-variant text-xs font-semibold text-on-surface hover:bg-surface-container">
                            Cancel
                        </a>
                        <button type="submit" :disabled="selectedBarangayCodes.length === 0" class="px-5 py-2.5 rounded-xl bg-primary text-on-primary text-xs font-semibold hover:bg-primary/90 shadow-xs disabled:opacity-50">
                            Save Zone Changes
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

<script>
function zoneEditor() {
    return {
        zoneId: {{ $zone->id }},
        provinces: @json($provinces),
        cities: [],
        barangays: [],
        loading: false,

        selectedProvinceCode: '{{ old('province_code', $zone->province_code) }}',
        selectedProvinceName: '{{ old('province_name', $zone->province_name) }}',
        selectedCityCode: '{{ old('city_code', $zone->city_code) }}',
        selectedCityName: '{{ old('city_name', $zone->city_name) }}',
        selectedBarangayCodes: @json(old('barangay_codes', $selectedBarangayCodes)),

        get selectedCount() {
            return this.selectedBarangayCodes.length;
        },
        get availableCount() {
            return this.barangays.filter(b => !this.isOtherZoneCovered(b)).length;
        },
        get otherCoveredCount() {
            return this.barangays.filter(b => this.isOtherZoneCovered(b)).length;
        },

        isOtherZoneCovered(b) {
            return b.is_covered && b.covered_by_zone_id !== this.zoneId;
        },

        async init() {
            if (this.selectedProvinceCode) {
                await this.fetchCities(this.selectedProvinceCode);
                if (this.selectedCityCode) {
                    await this.fetchBarangays(this.selectedCityCode);
                }
            }
        },

        async onProvinceChange() {
            const p = this.provinces.find(item => item.code === this.selectedProvinceCode);
            this.selectedProvinceName = p ? p.name : '';
            this.selectedCityCode = '';
            this.selectedCityName = '';
            this.selectedBarangayCodes = [];
            this.cities = [];
            this.barangays = [];
            if (this.selectedProvinceCode) {
                await this.fetchCities(this.selectedProvinceCode);
            }
        },

        async onCityChange() {
            const c = this.cities.find(item => item.code === this.selectedCityCode);
            this.selectedCityName = c ? c.name : '';
            this.selectedBarangayCodes = [];
            this.barangays = [];
            if (this.selectedCityCode) {
                await this.fetchBarangays(this.selectedCityCode);
            }
        },

        toggleBarangay(code) {
            const idx = this.selectedBarangayCodes.indexOf(code);
            if (idx > -1) {
                this.selectedBarangayCodes.splice(idx, 1);
            } else {
                this.selectedBarangayCodes.push(code);
            }
        },

        selectAllAvailable() {
            this.barangays.forEach(b => {
                if (!this.isOtherZoneCovered(b) && !this.selectedBarangayCodes.includes(b.code)) {
                    this.selectedBarangayCodes.push(b.code);
                }
            });
        },

        deselectAll() {
            this.selectedBarangayCodes = [];
        },

        async fetchCities(provCode) {
            try {
                const res = await fetch(`/locations/provinces/${provCode}/cities`);
                this.cities = await res.json();
            } catch (e) {
                console.error('Failed to load cities:', e);
            }
        },

        async fetchBarangays(cityCode) {
            this.loading = true;
            try {
                const res = await fetch(`/locations/cities/${cityCode}/barangays`);
                this.barangays = await res.json();
            } catch (e) {
                console.error('Failed to load barangays:', e);
            } finally {
                this.loading = false;
            }
        }
    }
}
</script>
@endsection

