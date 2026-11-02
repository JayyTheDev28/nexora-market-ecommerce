@extends('layouts.app')

@section('title', 'Add New Sorting Hub — Nexora Logistics')

@section('content')
<div class="w-full min-h-screen bg-surface">
    <div class="flex flex-col lg:flex-row w-full">
        <x-logistics-nav active="hubs" />

        <div class="flex-1 min-w-0 p-4 sm:p-6 lg:p-8 bg-surface">
            <div class="w-full max-w-3xl mx-auto space-y-6">

                <div class="flex items-center gap-3 mb-6">
                    <a href="{{ route('logistics.hubs.index') }}" class="w-9 h-9 rounded-xl border border-outline-variant bg-surface-container flex items-center justify-center text-on-surface hover:bg-surface-container-high transition-colors">
                        <span class="material-symbols-outlined text-[20px]">arrow_back</span>
                    </a>
                    <div>
                        <h1 class="font-headline-lg text-2xl font-bold text-on-surface">Add New Hub Facility</h1>
                        <p class="font-body-md text-xs text-on-surface-variant">Register a physical receiving, sorting, or cross-docking center for your logistics company.</p>
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

                <form method="POST" action="{{ route('logistics.hubs.store') }}"
                      x-data="hubLocationPicker()"
                      class="bg-surface-container-lowest border border-outline-variant/80 rounded-2xl p-6 shadow-xs space-y-5">
                    @csrf

                    <div>
                        <h2 class="text-xs font-bold uppercase tracking-wider text-primary mb-3">Facility Identity</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-on-surface mb-1.5">Hub Facility Name <span class="text-rose-500">*</span></label>
                                <input type="text" name="name" value="{{ old('name') }}" required
                                       placeholder="e.g. Laguna Central Hub - Calamba"
                                       class="w-full px-3.5 py-2.5 rounded-xl border border-outline-variant bg-surface text-xs text-on-surface focus:outline-none focus:border-primary">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-on-surface mb-1.5">Facility Code <span class="text-rose-500">*</span></label>
                                <input type="text" name="code" value="{{ old('code') }}" required
                                       placeholder="e.g. HUB-LAG-CAL"
                                       class="w-full px-3.5 py-2.5 rounded-xl border border-outline-variant bg-surface text-xs font-mono uppercase text-on-surface focus:outline-none focus:border-primary">
                            </div>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-outline-variant/60">
                        <h2 class="text-xs font-bold uppercase tracking-wider text-primary mb-3">Official Geographic Location (PSGC)</h2>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            {{-- Province Picker --}}
                            <div>
                                <label class="block text-xs font-medium text-on-surface mb-1.5">Province <span class="text-rose-500">*</span></label>
                                <select x-model="selectedProvinceCode" @change="onProvinceChange()" required
                                        class="w-full px-3.5 py-2.5 rounded-xl border border-outline-variant bg-surface text-xs text-on-surface focus:outline-none focus:border-primary">
                                    <option value="">Select Province</option>
                                    <template x-for="p in provinces" :key="p.code">
                                        <option :value="p.code" x-text="p.name"></option>
                                    </template>
                                </select>
                                <input type="hidden" name="province_code" :value="selectedProvinceCode">
                                <input type="hidden" name="province_name" :value="selectedProvinceName">
                            </div>

                            {{-- City Picker --}}
                            <div>
                                <label class="block text-xs font-medium text-on-surface mb-1.5">City / Municipality <span class="text-rose-500">*</span></label>
                                <select x-model="selectedCityCode" @change="onCityChange()" :disabled="!selectedProvinceCode" required
                                        class="w-full px-3.5 py-2.5 rounded-xl border border-outline-variant bg-surface text-xs text-on-surface focus:outline-none focus:border-primary disabled:opacity-50">
                                    <option value="">Select City/Municipality</option>
                                    <template x-for="c in cities" :key="c.code">
                                        <option :value="c.code" x-text="c.name"></option>
                                    </template>
                                </select>
                                <input type="hidden" name="city_code" :value="selectedCityCode">
                                <input type="hidden" name="city_name" :value="selectedCityName">
                            </div>

                            {{-- Barangay Picker --}}
                            <div>
                                <label class="block text-xs font-medium text-on-surface mb-1.5">Barangay <span class="text-rose-500">*</span></label>
                                <select x-model="selectedBarangayCode" @change="onBarangayChange()" :disabled="!selectedCityCode" required
                                        class="w-full px-3.5 py-2.5 rounded-xl border border-outline-variant bg-surface text-xs text-on-surface focus:outline-none focus:border-primary disabled:opacity-50">
                                    <option value="">Select Barangay</option>
                                    <template x-for="b in barangays" :key="b.code">
                                        <option :value="b.code" x-text="b.name"></option>
                                    </template>
                                </select>
                                <input type="hidden" name="barangay_code" :value="selectedBarangayCode">
                                <input type="hidden" name="barangay_name" :value="selectedBarangayName">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div>
                                <label class="block text-xs font-medium text-on-surface mb-1.5">Street Address / Industrial Park</label>
                                <input type="text" name="street_address" value="{{ old('street_address') }}"
                                       placeholder="e.g. National Highway, Km 54"
                                       class="w-full px-3.5 py-2.5 rounded-xl border border-outline-variant bg-surface text-xs text-on-surface focus:outline-none focus:border-primary">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-on-surface mb-1.5">Contact Phone</label>
                                <input type="text" name="contact_number" value="{{ old('contact_number') }}"
                                       placeholder="e.g. 09171234567"
                                       class="w-full px-3.5 py-2.5 rounded-xl border border-outline-variant bg-surface text-xs text-on-surface focus:outline-none focus:border-primary">
                            </div>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-outline-variant/60 flex items-center justify-end gap-3">
                        <a href="{{ route('logistics.hubs.index') }}" class="px-4 py-2.5 rounded-xl border border-outline-variant text-xs font-semibold text-on-surface hover:bg-surface-container">
                            Cancel
                        </a>
                        <button type="submit" class="px-5 py-2.5 rounded-xl bg-primary text-on-primary text-xs font-semibold hover:bg-primary/90 shadow-xs">
                            Create Hub
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

<script>
function hubLocationPicker() {
    return {
        provinces: @json($provinces),
        cities: [],
        barangays: [],
        selectedProvinceCode: '{{ old('province_code', '') }}',
        selectedProvinceName: '{{ old('province_name', '') }}',
        selectedCityCode: '{{ old('city_code', '') }}',
        selectedCityName: '{{ old('city_name', '') }}',
        selectedBarangayCode: '{{ old('barangay_code', '') }}',
        selectedBarangayName: '{{ old('barangay_name', '') }}',

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
            this.selectedBarangayCode = '';
            this.selectedBarangayName = '';
            this.cities = [];
            this.barangays = [];
            if (this.selectedProvinceCode) {
                await this.fetchCities(this.selectedProvinceCode);
            }
        },

        async onCityChange() {
            const c = this.cities.find(item => item.code === this.selectedCityCode);
            this.selectedCityName = c ? c.name : '';
            this.selectedBarangayCode = '';
            this.selectedBarangayName = '';
            this.barangays = [];
            if (this.selectedCityCode) {
                await this.fetchBarangays(this.selectedCityCode);
            }
        },

        onBarangayChange() {
            const b = this.barangays.find(item => item.code === this.selectedBarangayCode);
            this.selectedBarangayName = b ? b.name : '';
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
            try {
                const res = await fetch(`/locations/cities/${cityCode}/barangays`);
                this.barangays = await res.json();
            } catch (e) {
                console.error('Failed to load barangays:', e);
            }
        }
    }
}
</script>
@endsection

