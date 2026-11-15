@extends('layouts.app')

@section('title', 'Logistics Partner Registration — Nexora Market')

@section('content')
<div class="w-full min-h-[calc(100vh-4rem)] bg-surface-container-low py-8 md:py-12">
    <div class="max-w-4xl mx-auto px-4 sm:px-6">

        {{-- Header --}}
        <div class="text-center mb-8">
            <a href="{{ url('/') }}" class="inline-flex items-center gap-2 mb-3 group focus:outline-none">
                <img src="{{ asset('images/nexora-logo.png') }}" alt="Nexora Logo" class="w-10 h-10 rounded-xl object-cover shadow-xs group-hover:scale-105 transition-transform">
                <span class="font-headline-md text-xl font-extrabold tracking-tight text-on-surface">Nexora <span class="text-xs px-1.5 py-0.5 rounded bg-primary text-on-primary font-bold uppercase tracking-wider">Market</span></span>
            </a>
            <div class="flex items-center justify-center gap-2 mb-2">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-primary/10 text-primary text-xs font-semibold">
                    <span class="material-symbols-outlined text-[16px]">local_shipping</span>
                    <span>Corporate Fleet & Logistics Portal</span>
                </span>
            </div>
            <h1 class="font-headline-lg text-2xl sm:text-3xl font-extrabold text-on-surface tracking-tight">
                Logistics Partner Application
            </h1>
            <p class="text-xs sm:text-sm text-on-surface-variant max-w-xl mx-auto mt-2 leading-relaxed">
                Join our regional express delivery network. Connect your fleet, sorting facilities, and courier operations to thousands of merchants and shoppers across the Philippines.
            </p>
        </div>

        {{-- Validation Errors Banner --}}
        @if ($errors->any())
            <div class="mb-6 bg-rose-500/10 border border-rose-500/20 rounded-2xl p-4 sm:p-5">
                <div class="flex items-center gap-2 text-rose-700 font-bold text-xs">
                    <span class="material-symbols-outlined text-[18px]">error</span>
                    <span>Please correct the errors below before proceeding:</span>
                </div>
                <ul class="list-disc list-inside text-xs text-rose-600 mt-2 space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Registration Form --}}
        <form
            id="logisticsForm"
            method="POST"
            action="{{ route('logistics.register.store') }}"
            enctype="multipart/form-data"
            x-data="logisticsRegisterForm()"
            @submit="handleSubmit($event)"
            class="flex flex-col gap-8 bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 sm:p-10 shadow-xs">
            @csrf

            {{-- 1. Company Information --}}
            <div class="flex flex-col gap-4">
                <div class="border-b border-outline-variant/60 pb-3 flex items-center justify-between">
                    <div>
                        <h2 class="font-headline-sm text-sm sm:text-base font-bold text-on-surface flex items-center gap-2">
                            <span class="w-6 h-6 rounded-lg bg-primary/10 text-primary text-xs font-extrabold flex items-center justify-center">1</span>
                            Company Details
                        </h2>
                        <p class="text-xs text-on-surface-variant mt-0.5">Corporate legal entity information registered with SEC.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- Company Name --}}
                    <div class="flex flex-col gap-1 md:col-span-2">
                        <label for="company_name" class="text-xs font-semibold text-on-surface-variant">Registered Corporate Company Name <span class="text-rose-500">*</span></label>
                        <input
                            type="text"
                            id="company_name"
                            name="company_name"
                            value="{{ old('company_name') }}"
                            required
                            maxlength="255"
                            placeholder="e.g. Metro Express Logistics Inc."
                            class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>

                    {{-- SEC Registration Number --}}
                    <div class="flex flex-col gap-1">
                        <label for="sec_registration_no" class="text-xs font-semibold text-on-surface-variant">SEC Registration Number <span class="text-rose-500">*</span></label>
                        <input
                            type="text"
                            id="sec_registration_no"
                            name="sec_registration_no"
                            value="{{ old('sec_registration_no') }}"
                            required
                            maxlength="100"
                            placeholder="e.g. CS2021123456"
                            class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>

                    {{-- Tax Identification Number (TIN) --}}
                    <div class="flex flex-col gap-1">
                        <label for="tin" class="text-xs font-semibold text-on-surface-variant">Tax Identification Number (TIN: XXX-XXX-XXX-XXX) <span class="text-rose-500">*</span></label>
                        <input
                            type="text"
                            id="tin"
                            name="tin"
                            x-model="tin"
                            @input="formatTin($event)"
                            value="{{ old('tin') }}"
                            required
                            placeholder="000-123-456-789"
                            class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface font-mono focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                </div>
            </div>

            {{-- 2. Principal Hub & Office Address (Powered by Location API) --}}
            <div class="flex flex-col gap-4">
                <div class="border-b border-outline-variant/60 pb-3">
                    <h2 class="font-headline-sm text-sm sm:text-base font-bold text-on-surface flex items-center gap-2">
                        <span class="w-6 h-6 rounded-lg bg-primary/10 text-primary text-xs font-extrabold flex items-center justify-center">2</span>
                        Principal Hub & Office Address
                    </h2>
                    <p class="text-xs text-on-surface-variant mt-0.5">Physical dispatch origin and central hub facility location selected via official geographic location (PSGC).</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                    {{-- Province Selector --}}
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-on-surface-variant">Province / District <span class="text-rose-500">*</span></label>
                        <select
                            x-model="selectedProvinceCode"
                            @change="onProvinceChange()"
                            required
                            class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                            <option value="">Select Province</option>
                            <template x-for="p in provinces" :key="p.code">
                                <option :value="p.code" x-text="p.name" :selected="p.code === selectedProvinceCode || p.name === selectedProvinceName"></option>
                            </template>
                        </select>
                        <input type="hidden" name="province" :value="selectedProvinceName">
                        <input type="hidden" name="province_code" :value="selectedProvinceCode">
                        <input type="hidden" name="region" :value="selectedRegionName">
                    </div>

                    {{-- City / Municipality Selector --}}
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-on-surface-variant">City / Municipality <span class="text-rose-500">*</span></label>
                        <select
                            x-model="selectedCityCode"
                            @change="onCityChange()"
                            :disabled="!selectedProvinceCode"
                            required
                            class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary disabled:opacity-50">
                            <option value="">Select City / Municipality</option>
                            <template x-for="c in cities" :key="c.code">
                                <option :value="c.code" x-text="c.name" :selected="c.code === selectedCityCode || c.name === selectedCityName"></option>
                            </template>
                        </select>
                        <input type="hidden" name="city_municipality" :value="selectedCityName">
                        <input type="hidden" name="city_code" :value="selectedCityCode">
                    </div>

                    {{-- Barangay Selector --}}
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-on-surface-variant">Barangay <span class="text-rose-500">*</span></label>
                        <select
                            x-model="selectedBarangayCode"
                            @change="onBarangayChange()"
                            :disabled="!selectedCityCode"
                            required
                            class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary disabled:opacity-50">
                            <option value="">Select Barangay</option>
                            <template x-for="b in barangays" :key="b.code">
                                <option :value="b.code" x-text="b.name" :selected="b.code === selectedBarangayCode || b.name === selectedBarangayName"></option>
                            </template>
                        </select>
                        <input type="hidden" name="barangay" :value="selectedBarangayName">
                        <input type="hidden" name="barangay_code" :value="selectedBarangayCode">
                    </div>

                    {{-- Auto-derived Region Label --}}
                    <div class="flex flex-col gap-1 sm:col-span-2 md:col-span-3" x-show="selectedRegionName">
                        <span class="text-[11px] text-on-surface-variant">
                            Region: <strong class="text-primary" x-text="selectedRegionName"></strong>
                        </span>
                    </div>

                    {{-- Street Address --}}
                    <div class="flex flex-col gap-1 sm:col-span-2">
                        <label for="street_address" class="text-xs font-semibold text-on-surface-variant">Street Address / Building / Unit No. <span class="text-rose-500">*</span></label>
                        <input type="text" id="street_address" name="street_address" value="{{ old('street_address') }}" required placeholder="e.g. Unit 402 Logistics Tower, 32nd Street" class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>

                    {{-- Postal Code --}}
                    <div class="flex flex-col gap-1">
                        <label for="postal_code" class="text-xs font-semibold text-on-surface-variant">Postal Code (4 Digits) <span class="text-rose-500">*</span></label>
                        <input type="text" id="postal_code" name="postal_code" value="{{ old('postal_code') }}" required maxlength="4" placeholder="4004" class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface font-mono focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                </div>
            </div>

            {{-- 3. Authorized Contact Person --}}
            <div class="flex flex-col gap-4">
                <div class="border-b border-outline-variant/60 pb-3">
                    <h2 class="font-headline-sm text-sm sm:text-base font-bold text-on-surface flex items-center gap-2">
                        <span class="w-6 h-6 rounded-lg bg-primary/10 text-primary text-xs font-extrabold flex items-center justify-center">3</span>
                        Authorized Signatory / Liaison Officer
                    </h2>
                    <p class="text-xs text-on-surface-variant mt-0.5">Primary liaison responsible for this account and compliance liaison.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="flex flex-col gap-1">
                        <label for="contact_person_first_name" class="text-xs font-semibold text-on-surface-variant">First Name <span class="text-rose-500">*</span></label>
                        <input type="text" id="contact_person_first_name" name="contact_person_first_name" value="{{ old('contact_person_first_name') }}" required minlength="2" maxlength="50" placeholder="e.g. Eduardo" class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>

                    <div class="flex flex-col gap-1">
                        <label for="contact_person_last_name" class="text-xs font-semibold text-on-surface-variant">Last Name <span class="text-rose-500">*</span></label>
                        <input type="text" id="contact_person_last_name" name="contact_person_last_name" value="{{ old('contact_person_last_name') }}" required minlength="2" maxlength="50" placeholder="e.g. Santos" class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>

                    <div class="flex flex-col gap-1">
                        <label for="position" class="text-xs font-semibold text-on-surface-variant">Designation / Official Position <span class="text-rose-500">*</span></label>
                        <input type="text" id="position" name="position" value="{{ old('position') }}" required placeholder="e.g. Operations Director / Hub Manager" class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>

                    <div class="flex flex-col gap-1">
                        <label for="mobile_number" class="text-xs font-semibold text-on-surface-variant">Official Mobile Number (11 Digits) <span class="text-rose-500">*</span></label>
                        <input
                            type="tel"
                            id="mobile_number"
                            name="mobile_number"
                            value="{{ old('mobile_number') }}"
                            required
                            maxlength="11"
                            @input="$el.value = $el.value.replace(/[^0-9]/g, '').slice(0, 11)"
                            placeholder="09171234567"
                            class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>

                    <div class="flex flex-col gap-1 md:col-span-2">
                        <label for="email" class="text-xs font-semibold text-on-surface-variant">Official Corporate Email (Receives 6-digit OTP verification) <span class="text-rose-500">*</span></label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" required placeholder="operations@metroexpress.ph" class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                </div>
            </div>

            {{-- 4. Compliance Documents Upload --}}
            <div class="flex flex-col gap-4">
                <div class="border-b border-outline-variant/60 pb-3">
                    <h2 class="font-headline-sm text-sm sm:text-base font-bold text-on-surface flex items-center gap-2">
                        <span class="w-6 h-6 rounded-lg bg-primary/10 text-primary text-xs font-extrabold flex items-center justify-center">4</span>
                        Compliance Documents Upload
                    </h2>
                    <p class="text-xs text-on-surface-variant mt-0.5">Clear scans or PDF copies (Max 2MB per document; Allowed: PDF, JPG, PNG). Uploaded documents remain saved even if validation fails.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- SEC Certificate --}}
                    @php
                        $cachedSec = old('sec_certificate_cached') ?? session('logistics_temp_docs.sec_certificate.path');
                        $cachedSecName = session('logistics_temp_docs.sec_certificate.original_name') ?? ($cachedSec ? basename($cachedSec) : null);
                    @endphp
                    <div class="flex flex-col gap-1.5 p-4 rounded-2xl border border-dashed border-outline-variant bg-surface-container-low/40">
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-bold text-on-surface">1. SEC Registration Certificate <span class="text-rose-500">*</span></label>
                            <span class="text-[10px] text-outline">Max 2MB</span>
                        </div>
                        @if($cachedSec)
                            <div class="flex items-center justify-between p-2 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-xs text-emerald-800">
                                <div class="flex items-center gap-1.5 truncate">
                                    <span class="material-symbols-outlined text-[16px] text-emerald-600">check_circle</span>
                                    <span class="font-semibold truncate">Attached: {{ $cachedSecName }}</span>
                                </div>
                                <span class="text-[10px] bg-emerald-600 text-white px-2 py-0.5 rounded-full font-bold uppercase">Saved</span>
                            </div>
                            <input type="hidden" name="sec_certificate_cached" value="{{ $cachedSec }}">
                        @endif
                        <input type="file" name="sec_certificate" accept=".pdf,.jpg,.jpeg,.png" {{ $cachedSec ? '' : 'required' }} class="text-xs text-on-surface-variant file:mr-2 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-primary file:text-on-primary">
                        @if($cachedSec)
                            <span class="text-[10px] text-on-surface-variant">Leave empty to keep attached file, or upload a new file to replace.</span>
                        @endif
                    </div>

                    {{-- BIR Form 2303 --}}
                    @php
                        $cachedBir = old('bir_2303_cached') ?? session('logistics_temp_docs.bir_2303.path');
                        $cachedBirName = session('logistics_temp_docs.bir_2303.original_name') ?? ($cachedBir ? basename($cachedBir) : null);
                    @endphp
                    <div class="flex flex-col gap-1.5 p-4 rounded-2xl border border-dashed border-outline-variant bg-surface-container-low/40">
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-bold text-on-surface">2. BIR Form 2303 Certificate <span class="text-rose-500">*</span></label>
                            <span class="text-[10px] text-outline">Max 2MB</span>
                        </div>
                        @if($cachedBir)
                            <div class="flex items-center justify-between p-2 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-xs text-emerald-800">
                                <div class="flex items-center gap-1.5 truncate">
                                    <span class="material-symbols-outlined text-[16px] text-emerald-600">check_circle</span>
                                    <span class="font-semibold truncate">Attached: {{ $cachedBirName }}</span>
                                </div>
                                <span class="text-[10px] bg-emerald-600 text-white px-2 py-0.5 rounded-full font-bold uppercase">Saved</span>
                            </div>
                            <input type="hidden" name="bir_2303_cached" value="{{ $cachedBir }}">
                        @endif
                        <input type="file" name="bir_2303" accept=".pdf,.jpg,.jpeg,.png" {{ $cachedBir ? '' : 'required' }} class="text-xs text-on-surface-variant file:mr-2 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-primary file:text-on-primary">
                        @if($cachedBir)
                            <span class="text-[10px] text-on-surface-variant">Leave empty to keep attached file, or upload a new file to replace.</span>
                        @endif
                    </div>

                    {{-- Mayor's Permit --}}
                    @php
                        $cachedPermit = old('mayors_permit_cached') ?? session('logistics_temp_docs.mayors_permit.path');
                        $cachedPermitName = session('logistics_temp_docs.mayors_permit.original_name') ?? ($cachedPermit ? basename($cachedPermit) : null);
                    @endphp
                    <div class="flex flex-col gap-1.5 p-4 rounded-2xl border border-dashed border-outline-variant bg-surface-container-low/40">
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-bold text-on-surface">3. Mayor's / Business Permit <span class="text-rose-500">*</span></label>
                            <span class="text-[10px] text-outline">Max 2MB</span>
                        </div>
                        @if($cachedPermit)
                            <div class="flex items-center justify-between p-2 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-xs text-emerald-800">
                                <div class="flex items-center gap-1.5 truncate">
                                    <span class="material-symbols-outlined text-[16px] text-emerald-600">check_circle</span>
                                    <span class="font-semibold truncate">Attached: {{ $cachedPermitName }}</span>
                                </div>
                                <span class="text-[10px] bg-emerald-600 text-white px-2 py-0.5 rounded-full font-bold uppercase">Saved</span>
                            </div>
                            <input type="hidden" name="mayors_permit_cached" value="{{ $cachedPermit }}">
                        @endif
                        <input type="file" name="mayors_permit" accept=".pdf,.jpg,.jpeg,.png" {{ $cachedPermit ? '' : 'required' }} class="text-xs text-on-surface-variant file:mr-2 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-primary file:text-on-primary">
                        @if($cachedPermit)
                            <span class="text-[10px] text-on-surface-variant">Leave empty to keep attached file, or upload a new file to replace.</span>
                        @endif
                    </div>

                    {{-- Valid ID --}}
                    @php
                        $cachedId = old('valid_id_cached') ?? session('logistics_temp_docs.valid_id.path');
                        $cachedIdName = session('logistics_temp_docs.valid_id.original_name') ?? ($cachedId ? basename($cachedId) : null);
                    @endphp
                    <div class="flex flex-col gap-1.5 p-4 rounded-2xl border border-dashed border-outline-variant bg-surface-container-low/40">
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-bold text-on-surface">4. Valid Government ID of Signatory <span class="text-rose-500">*</span></label>
                            <span class="text-[10px] text-outline">Max 2MB</span>
                        </div>
                        @if($cachedId)
                            <div class="flex items-center justify-between p-2 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-xs text-emerald-800">
                                <div class="flex items-center gap-1.5 truncate">
                                    <span class="material-symbols-outlined text-[16px] text-emerald-600">check_circle</span>
                                    <span class="font-semibold truncate">Attached: {{ $cachedIdName }}</span>
                                </div>
                                <span class="text-[10px] bg-emerald-600 text-white px-2 py-0.5 rounded-full font-bold uppercase">Saved</span>
                            </div>
                            <input type="hidden" name="valid_id_cached" value="{{ $cachedId }}">
                        @endif
                        <input type="file" name="valid_id" accept=".pdf,.jpg,.jpeg,.png" {{ $cachedId ? '' : 'required' }} class="text-xs text-on-surface-variant file:mr-2 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-primary file:text-on-primary">
                        @if($cachedId)
                            <span class="text-[10px] text-on-surface-variant">Leave empty to keep attached file, or upload a new file to replace.</span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- 5. Account Password & Terms --}}
            <div class="flex flex-col gap-4">
                <div class="border-b border-outline-variant/60 pb-3">
                    <h2 class="font-headline-sm text-sm sm:text-base font-bold text-on-surface flex items-center gap-2">
                        <span class="w-6 h-6 rounded-lg bg-primary/10 text-primary text-xs font-extrabold flex items-center justify-center">5</span>
                        Account Security & Agreement
                    </h2>
                    <p class="text-xs text-on-surface-variant mt-0.5">Password must be at least 8 characters and include uppercase, lowercase, numbers, and symbols.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- Password with View Toggle --}}
                    <div class="flex flex-col gap-1">
                        <label for="password" class="text-xs font-semibold text-on-surface-variant">Account Password <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <input
                                :type="showPassword ? 'text' : 'password'"
                                id="password"
                                name="password"
                                required
                                placeholder="••••••••"
                                class="w-full rounded-xl border border-outline-variant pl-3.5 pr-10 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                            <button
                                type="button"
                                @click="showPassword = !showPassword"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant hover:text-on-surface transition-colors focus:outline-none"
                                title="Toggle password visibility">
                                <span class="material-symbols-outlined text-[18px]" x-text="showPassword ? 'visibility_off' : 'visibility'">visibility</span>
                            </button>
                        </div>
                    </div>

                    {{-- Confirm Password with View Toggle --}}
                    <div class="flex flex-col gap-1">
                        <label for="password_confirmation" class="text-xs font-semibold text-on-surface-variant">Confirm Password <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <input
                                :type="showConfirmPassword ? 'text' : 'password'"
                                id="password_confirmation"
                                name="password_confirmation"
                                required
                                placeholder="••••••••"
                                class="w-full rounded-xl border border-outline-variant pl-3.5 pr-10 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                            <button
                                type="button"
                                @click="showConfirmPassword = !showConfirmPassword"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant hover:text-on-surface transition-colors focus:outline-none"
                                title="Toggle password visibility">
                                <span class="material-symbols-outlined text-[18px]" x-text="showConfirmPassword ? 'visibility_off' : 'visibility'">visibility</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="pt-2">
                    <label class="flex items-start gap-3 cursor-pointer select-none bg-surface-container-low p-3.5 rounded-2xl border border-outline-variant/60">
                        <input type="checkbox" name="terms" value="1" required class="mt-0.5 w-4 h-4 rounded text-primary focus:ring-primary border-outline-variant">
                        <span class="text-xs text-on-surface-variant leading-relaxed">
                            I certify that I am an authorized corporate representative of this logistics company. I agree to comply with Nexora Market
                            <a href="{{ route('terms') }}" target="_blank" class="text-primary font-bold hover:underline">Terms of Service</a>
                            and the Logistics Carrier Operations Guidelines.
                        </span>
                    </label>
                </div>
            </div>

            {{-- Submit Action --}}
            <div class="pt-4 border-t border-outline-variant/60 flex flex-col sm:flex-row items-center justify-between gap-4">
                <a href="{{ route('login') }}" class="text-xs font-semibold text-on-surface-variant hover:text-primary transition-colors">
                    Already registered? Sign in instead →
                </a>

                <button
                    type="submit"
                    :disabled="isSubmitting"
                    class="w-full sm:w-auto px-8 py-3.5 rounded-full bg-primary text-on-primary font-bold text-xs shadow-md shadow-primary/20 hover:bg-primary/90 transition-all cursor-pointer disabled:opacity-50 flex items-center justify-center gap-2">
                    <span x-show="!isSubmitting">Submit Application & Receive Verification Code</span>
                    <span x-show="isSubmitting" class="flex items-center gap-2">
                        <span class="w-4 h-4 border-2 border-current border-t-transparent rounded-full animate-spin"></span>
                        <span>Processing Application…</span>
                    </span>
                </button>
            </div>

        </form>

    </div>
</div>

@push('scripts')
<script>
    function logisticsRegisterForm() {
        return {
            isSubmitting: false,
            showPassword: false,
            showConfirmPassword: false,
            tin: '{{ old('tin', '') }}',

            provinces: @json($provinces ?? []),
            cities: [],
            barangays: [],
            selectedProvinceCode: '{{ old('province_code', '') }}',
            selectedProvinceName: '{{ old('province', '') }}',
            selectedCityCode: '{{ old('city_code', '') }}',
            selectedCityName: '{{ old('city_municipality', '') }}',
            selectedBarangayCode: '{{ old('barangay_code', '') }}',
            selectedBarangayName: '{{ old('barangay', '') }}',
            selectedRegionName: '{{ old('region', '') }}',

            async init() {
                if (!this.selectedProvinceCode && this.selectedProvinceName) {
                    const found = this.provinces.find(p => p.name.toLowerCase() === this.selectedProvinceName.toLowerCase());
                    if (found) {
                        this.selectedProvinceCode = found.code;
                        this.selectedRegionName = found.region_name || found.name;
                    }
                }
                if (this.selectedProvinceCode) {
                    const p = this.provinces.find(item => item.code === this.selectedProvinceCode);
                    if (p && !this.selectedRegionName) {
                        this.selectedRegionName = p.region_name || p.name;
                    }
                    await this.fetchCities(this.selectedProvinceCode);
                    if (!this.selectedCityCode && this.selectedCityName) {
                        const foundCity = this.cities.find(c => c.name.toLowerCase() === this.selectedCityName.toLowerCase());
                        if (foundCity) this.selectedCityCode = foundCity.code;
                    }
                    if (this.selectedCityCode) {
                        await this.fetchBarangays(this.selectedCityCode);
                        if (!this.selectedBarangayCode && this.selectedBarangayName) {
                            const foundB = this.barangays.find(b => b.name.toLowerCase() === this.selectedBarangayName.toLowerCase());
                            if (foundB) this.selectedBarangayCode = foundB.code;
                        }
                    }
                }
            },

            async onProvinceChange() {
                const p = this.provinces.find(item => item.code === this.selectedProvinceCode);
                this.selectedProvinceName = p ? p.name : '';
                this.selectedRegionName = p ? (p.region_name || p.name) : '';
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
            },

            formatTin(e) {
                let v = e.target.value.replace(/[^0-9]/g, '').slice(0, 12);
                let parts = [];
                for (let i = 0; i < v.length; i += 3) {
                    parts.push(v.substring(i, i + 3));
                }
                this.tin = parts.join('-');
            },

            handleSubmit(e) {
                this.isSubmitting = true;
            }
        };
    }
</script>
@endpush
@endsection
