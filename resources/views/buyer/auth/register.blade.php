@extends('layouts.app')

@section('title', 'Create Your Account — Nexora Market')

@section('content')
@php
    $initialStep = 1;
    if ($errors->hasAny(['business_name', 'category', 'business_permit', 'vehicle_type', 'plate_number', 'or_cr', 'valid_id', 'terms'])) {
        $initialStep = 4;
    } elseif ($errors->hasAny(['province', 'province_code', 'province_name', 'municipality', 'municipality_code', 'municipality_name', 'barangay', 'barangay_code', 'barangay_name', 'street', 'house_number'])) {
        $initialStep = 3;
    } elseif ($errors->hasAny(['email', 'contact_number', 'password', 'password_confirmation'])) {
        $initialStep = 2;
    } elseif ($errors->hasAny(['first_name', 'last_name', 'middle_initial', 'sex', 'birthday', 'age'])) {
        $initialStep = 1;
    }
@endphp

<div
    x-data="registerForm({{ $initialStep }})"
    x-init="initForm()"
    class="w-full flex flex-col lg:flex-row min-h-[calc(100vh-4rem)] bg-surface-container-lowest">

    <x-brand-panel caption="Join thousands of shoppers, merchants, and logistics partners across the Philippines." />

    {{-- Form Column --}}
    <div class="flex-1 overflow-y-auto">
        <div class="max-w-2xl mx-auto px-6 md:px-10 py-8 md:py-10">

            {{-- Header --}}
            <div class="mb-6">
                <div class="flex items-center justify-between gap-4 mb-1">
                    <span class="text-xs font-semibold text-primary" x-text="'Step ' + step + ' of 4 — ' + stepTitles[step]"></span>
                    <span class="text-xs text-on-surface-variant font-medium" x-text="Math.round((step / 4) * 100) + '% completed'"></span>
                </div>
                <h1 class="font-headline-lg text-2xl md:text-3xl font-bold tracking-tight text-on-surface">Create an account</h1>
                <p class="font-body-md text-xs md:text-sm text-on-surface-variant mt-1" x-text="stepSubtitles[step]"></p>
            </div>

            {{-- Stepper Progress Bar --}}
            <div class="mb-6 bg-surface-container-low rounded-2xl p-2.5 border border-outline-variant/60">
                <div class="grid grid-cols-4 gap-2 text-center select-none">
                    <template x-for="(sTitle, idx) in ['Profile', 'Security', 'Address', 'Verify']" :key="idx + 1">
                        <button
                            type="button"
                            @click="goToStep(idx + 1)"
                            :disabled="idx + 1 > maxStepReached"
                            class="flex flex-col items-center gap-1 py-1 px-1 rounded-xl transition-all cursor-pointer disabled:cursor-not-allowed disabled:opacity-40"
                            :class="step === idx + 1 ? 'font-bold' : 'hover:bg-surface/50'">
                            <div class="flex items-center justify-center w-6 h-6 rounded-full text-xs font-semibold transition-colors"
                                :class="{
                                    'bg-primary text-on-primary': step === idx + 1,
                                    'bg-emerald-600 text-white': step > idx + 1,
                                    'bg-surface-container text-on-surface-variant': step < idx + 1
                                }">
                                <template x-if="step > idx + 1">
                                    <span class="material-symbols-outlined text-[15px]">check</span>
                                </template>
                                <template x-if="step <= idx + 1">
                                    <span x-text="idx + 1"></span>
                                </template>
                            </div>
                            <span class="text-[11px] truncate w-full"
                                :class="step === idx + 1 ? 'text-primary font-bold' : (step > idx + 1 ? 'text-on-surface font-medium' : 'text-on-surface-variant')"
                                x-text="sTitle"></span>
                        </button>
                    </template>
                </div>

                {{-- Progress Line --}}
                <div class="w-full bg-outline-variant/30 h-1.5 rounded-full overflow-hidden mt-2">
                    <div
                        class="bg-primary h-full rounded-full transition-all duration-300 ease-out"
                        :style="'width: ' + ((step / 4) * 100) + '%'"></div>
                </div>
            </div>

            {{-- Server Validation Errors Banner --}}
            @if ($errors->any())
                <div class="mb-5 bg-rose-500/10 border border-rose-500/20 rounded-2xl p-4">
                    <p class="font-label-md text-xs font-bold text-rose-700">Please review the following errors:</p>
                    <ul class="list-disc list-inside text-xs text-rose-600 mt-1 space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Client Validation Error Banner --}}
            <div x-show="stepError" x-cloak class="mb-5 bg-rose-500/10 border border-rose-500/20 rounded-2xl p-3.5">
                <p class="text-xs text-rose-700 font-medium" x-text="stepError"></p>
            </div>

            {{-- Registration Form --}}
            <form
                id="registrationForm"
                class="flex flex-col bg-surface-container-lowest rounded-3xl border border-outline-variant shadow-xs overflow-hidden"
                action="{{ route('register.store') }}"
                method="POST"
                enctype="multipart/form-data"
                novalidate
                @submit="handleSubmit($event)">
                @csrf

                {{-- ========================================================================= --}}
                {{-- STEP 1: Account Type & Personal Details                                   --}}
                {{-- ========================================================================= --}}
                <div x-show="step === 1" x-cloak class="p-5 md:p-6 flex flex-col gap-5">

                    {{-- Account Type --}}
                    <div>
                        <label class="block font-label-md text-xs font-semibold text-on-surface mb-1">Account Type</label>
                        <p class="text-xs text-on-surface-variant mb-3">Select how you will be using Nexora.</p>

                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                            <template x-for="option in roleOptions" :key="option.value">
                                <label
                                    class="flex flex-col items-center justify-center p-3 rounded-2xl border cursor-pointer transition-all select-none text-center"
                                    :class="role === option.value ? 'border-primary bg-primary/5 text-primary font-bold shadow-xs ring-1 ring-primary' : 'border-outline-variant text-on-surface-variant hover:border-outline hover:bg-surface-container-low/50'">
                                    <input type="radio" name="role" :value="option.value" x-model="role" class="sr-only">
                                    <span class="text-xs font-semibold" x-text="option.label"></span>
                                </label>
                            </template>
                        </div>
                    </div>

                    {{-- Legal Name --}}
                    <div class="pt-4 border-t border-outline-variant/60 flex flex-col gap-4">
                        <div>
                            <h2 class="font-headline-sm text-sm font-bold text-on-surface">Personal Information</h2>
                            <p class="text-xs text-on-surface-variant mt-0.5">Enter your legal name and demographic details.</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="flex flex-col gap-1.5">
                                <label for="first_name" class="font-label-md text-xs font-semibold text-on-surface-variant">First Name</label>
                                <input
                                    type="text" id="first_name" name="first_name"
                                    x-model="firstName"
                                    minlength="2" maxlength="50"
                                    placeholder="e.g. Maria"
                                    value="{{ old('first_name') }}"
                                    class="rounded-xl border px-3.5 py-2.5 text-xs text-on-surface bg-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary"
                                    :class="fieldErrors.first_name ? 'border-rose-500 ring-1 ring-rose-500' : 'border-outline-variant'">
                                <span class="text-[10px] text-on-surface-variant">Letters and spaces only (2-50 characters, no special characters).</span>
                                <span x-show="fieldErrors.first_name" x-text="fieldErrors.first_name" class="text-[11px] text-rose-600 font-medium"></span>
                            </div>

                            <div class="flex flex-col gap-1.5">
                                <label for="last_name" class="font-label-md text-xs font-semibold text-on-surface-variant">Last Name</label>
                                <input
                                    type="text" id="last_name" name="last_name"
                                    x-model="lastName"
                                    minlength="2" maxlength="50"
                                    placeholder="e.g. Santos"
                                    value="{{ old('last_name') }}"
                                    class="rounded-xl border px-3.5 py-2.5 text-xs text-on-surface bg-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary"
                                    :class="fieldErrors.last_name ? 'border-rose-500 ring-1 ring-rose-500' : 'border-outline-variant'">
                                <span class="text-[10px] text-on-surface-variant">Letters and spaces only (2-50 characters, no special characters).</span>
                                <span x-show="fieldErrors.last_name" x-text="fieldErrors.last_name" class="text-[11px] text-rose-600 font-medium"></span>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div class="flex flex-col gap-1.5">
                                <label for="middle_initial" class="font-label-md text-xs font-semibold text-on-surface-variant">Middle Initial</label>
                                <input
                                    type="text" id="middle_initial" name="middle_initial"
                                    x-model="middleInitial"
                                    maxlength="5" placeholder="M.I."
                                    value="{{ old('middle_initial') }}"
                                    class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary">
                            </div>

                            <div class="flex flex-col gap-1.5">
                                <label for="sex" class="font-label-md text-xs font-semibold text-on-surface-variant">Sex</label>
                                <select
                                    id="sex" name="sex"
                                    x-model="sex"
                                    class="rounded-xl border px-3 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary"
                                    :class="fieldErrors.sex ? 'border-rose-500 ring-1 ring-rose-500' : 'border-outline-variant'">
                                    <option value="" disabled selected>Select</option>
                                    <option value="female" {{ old('sex') === 'female' ? 'selected' : '' }}>Female</option>
                                    <option value="male" {{ old('sex') === 'male' ? 'selected' : '' }}>Male</option>
                                    <option value="other" {{ old('sex') === 'other' ? 'selected' : '' }}>Other</option>
                                </select>
                            </div>

                            <div class="flex flex-col gap-1.5">
                                <label for="birthday" class="font-label-md text-xs font-semibold text-on-surface-variant">Birthday</label>
                                <input
                                    type="date" id="birthday" name="birthday"
                                    max="2025-12-31"
                                    x-model="birthday" @change="calculateAge()"
                                    class="rounded-xl border px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary"
                                    :class="fieldErrors.birthday ? 'border-rose-500 ring-1 ring-rose-500' : 'border-outline-variant'">
                            </div>

                            <div class="flex flex-col gap-1.5">
                                <label for="age" class="font-label-md text-xs font-semibold text-on-surface-variant">Age</label>
                                <input
                                    type="text" id="age" name="age" readonly disabled
                                    x-model="age" placeholder="--"
                                    class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface-variant bg-surface-container cursor-not-allowed">
                            </div>

                            <div x-show="ageError" x-cloak class="col-span-2 md:col-span-4 -mt-2">
                                <p class="text-[11px] text-rose-600 font-medium" x-text="ageError"></p>
                            </div>
                        </div>
                    </div>

                    {{-- Step 1 Actions --}}
                    <div class="pt-4 border-t border-outline-variant/60 flex items-center justify-between gap-3">
                        <p class="text-xs text-on-surface-variant">
                            Already have an account?
                            <a href="{{ url('/login') }}" class="text-primary font-semibold hover:underline">Sign in</a>
                        </p>
                        <button
                            type="button"
                            @click="nextStep()"
                            class="py-2.5 px-6 rounded-full bg-primary text-on-primary font-label-md text-xs font-semibold shadow-xs hover:bg-primary/90 transition-all cursor-pointer">
                            Continue to Security
                        </button>
                    </div>

                </div>

                {{-- ========================================================================= --}}
                {{-- STEP 2: Credentials & Security                                            --}}
                {{-- ========================================================================= --}}
                <div x-show="step === 2" x-cloak class="p-5 md:p-6 flex flex-col gap-5">
                    <div>
                        <h2 class="font-headline-sm text-sm font-bold text-on-surface">Account & Security</h2>
                        <p class="text-xs text-on-surface-variant mt-0.5">Set up your credentials for logging into Nexora.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1.5">
                            <label for="email" class="font-label-md text-xs font-semibold text-on-surface-variant">Email Address</label>
                            <input
                                type="email" id="email" name="email"
                                x-model="email"
                                placeholder="name@example.com"
                                value="{{ old('email') }}"
                                class="w-full rounded-xl border px-3.5 py-2.5 text-xs text-on-surface bg-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary"
                                :class="fieldErrors.email ? 'border-rose-500 ring-1 ring-rose-500' : 'border-outline-variant'">
                            <span x-show="fieldErrors.email" x-text="fieldErrors.email" class="text-[11px] text-rose-600 font-medium"></span>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label for="contact_number" class="font-label-md text-xs font-semibold text-on-surface-variant">Contact Number (Philippines)</label>
                            <div
                                class="flex items-center rounded-xl border bg-surface focus-within:ring-2 focus-within:ring-primary overflow-hidden"
                                :class="fieldErrors.contact_number ? 'border-rose-500 ring-1 ring-rose-500' : 'border-outline-variant'">
                                <span class="px-3 py-2.5 bg-surface-container-low border-r border-outline-variant text-xs font-bold text-on-surface-variant select-none">
                                    +63
                                </span>
                                <input
                                    type="tel" id="contact_number" name="contact_number"
                                    x-model="contactNumber"
                                    maxlength="11"
                                    placeholder="09XXXXXXXXX"
                                    value="{{ old('contact_number') }}"
                                    x-on:input="contactNumber = $event.target.value.replace(/[^0-9]/g, '').slice(0, 11)"
                                    class="flex-1 px-3.5 py-2.5 text-xs text-on-surface bg-transparent placeholder:text-outline focus:outline-none">
                            </div>
                            <span class="text-[10px] text-on-surface-variant">Must be 11 digits starting with 09 (e.g. 09171234567, no special characters).</span>
                            <span x-show="fieldErrors.contact_number" x-text="fieldErrors.contact_number" class="text-[11px] text-rose-600 font-medium"></span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1.5">
                            <label for="password" class="font-label-md text-xs font-semibold text-on-surface-variant">Password</label>
                            <div class="relative">
                                <input
                                    :type="showPassword ? 'text' : 'password'" id="password" name="password"
                                    x-model="password"
                                    minlength="8" placeholder="At least 8 characters"
                                    class="w-full rounded-xl border pl-3.5 pr-11 py-2.5 text-xs text-on-surface bg-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary"
                                    :class="fieldErrors.password ? 'border-rose-500 ring-1 ring-rose-500' : 'border-outline-variant'">
                                <button type="button" @click="showPassword = !showPassword" class="absolute right-3 top-1/2 -translate-y-1/2 text-outline hover:text-on-surface-variant p-1 cursor-pointer" aria-label="Toggle password visibility">
                                    <span class="material-symbols-outlined text-[18px]" x-text="showPassword ? 'visibility_off' : 'visibility'"></span>
                                </button>
                            </div>
                            <span class="text-[10px] text-on-surface-variant">Must be at least 8 characters: uppercase, lowercase, number, and special character.</span>
                            <span x-show="fieldErrors.password" x-text="fieldErrors.password" class="text-[11px] text-rose-600 font-medium"></span>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label for="password_confirmation" class="font-label-md text-xs font-semibold text-on-surface-variant">Confirm Password</label>
                            <div class="relative">
                                <input
                                    :type="showPasswordConfirm ? 'text' : 'password'" id="password_confirmation" name="password_confirmation"
                                    x-model="passwordConfirmation"
                                    placeholder="Re-enter password"
                                    class="w-full rounded-xl border pl-3.5 pr-11 py-2.5 text-xs text-on-surface bg-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary"
                                    :class="fieldErrors.password_confirmation ? 'border-rose-500 ring-1 ring-rose-500' : 'border-outline-variant'">
                                <button type="button" @click="showPasswordConfirm = !showPasswordConfirm" class="absolute right-3 top-1/2 -translate-y-1/2 text-outline hover:text-on-surface-variant p-1 cursor-pointer" aria-label="Toggle password visibility">
                                    <span class="material-symbols-outlined text-[18px]" x-text="showPasswordConfirm ? 'visibility_off' : 'visibility'"></span>
                                </button>
                            </div>
                            <span class="text-[10px] text-on-surface-variant">Re-enter your password to confirm.</span>
                            <span x-show="fieldErrors.password_confirmation" x-text="fieldErrors.password_confirmation" class="text-[11px] text-rose-600 font-medium"></span>
                        </div>
                    </div>

                    {{-- Step 2 Actions --}}
                    <div class="pt-4 border-t border-outline-variant/60 flex items-center justify-between gap-3">
                        <button
                            type="button"
                            @click="prevStep()"
                            class="py-2.5 px-4 rounded-full border border-outline-variant text-on-surface font-label-md text-xs font-semibold hover:bg-surface-container transition-all cursor-pointer">
                            Back
                        </button>
                        <button
                            type="button"
                            @click="nextStep()"
                            class="py-2.5 px-6 rounded-full bg-primary text-on-primary font-label-md text-xs font-semibold shadow-xs hover:bg-primary/90 transition-all cursor-pointer">
                            Continue to Address
                        </button>
                    </div>

                </div>

                {{-- ========================================================================= --}}
                {{-- STEP 3: Residential Address                                               --}}
                {{-- ========================================================================= --}}
                <div x-show="step === 3" x-cloak class="p-5 md:p-6 flex flex-col gap-5">
                    <div>
                        <h2 class="font-headline-sm text-sm font-bold text-on-surface">Residential Address</h2>
                        <p class="text-xs text-on-surface-variant mt-0.5">Your official residence for order delivery and verification.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="flex flex-col gap-1.5">
                            <label for="province" class="font-label-md text-xs font-semibold text-on-surface-variant">Province</label>
                            <select
                                id="province" name="province"
                                x-model="selectedProvince" @change="onProvinceChange()"
                                class="rounded-xl border px-3 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary disabled:bg-surface-container disabled:cursor-not-allowed"
                                :class="fieldErrors.province ? 'border-rose-500 ring-1 ring-rose-500' : 'border-outline-variant'">
                                <option value="" disabled selected x-text="loadingProvinces ? 'Loading…' : 'Select Province'"></option>
                                <template x-for="province in provinces" :key="province.code">
                                    <option :value="province.code" x-text="province.name"></option>
                                </template>
                            </select>
                            <span x-show="fieldErrors.province" x-text="fieldErrors.province" class="text-[11px] text-rose-600 font-medium"></span>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label for="municipality" class="font-label-md text-xs font-semibold text-on-surface-variant">City / Municipality</label>
                            <select
                                id="municipality" name="municipality"
                                x-model="selectedMunicipality" @change="onMunicipalityChange()"
                                :disabled="!selectedProvince || loadingMunicipalities"
                                class="rounded-xl border px-3 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary disabled:bg-surface-container disabled:cursor-not-allowed"
                                :class="fieldErrors.municipality ? 'border-rose-500 ring-1 ring-rose-500' : 'border-outline-variant'">
                                <option value="" disabled selected x-text="loadingMunicipalities ? 'Loading…' : 'Select City'"></option>
                                <template x-for="m in municipalities" :key="m.code">
                                    <option :value="m.code" x-text="m.name"></option>
                                </template>
                            </select>
                            <span x-show="fieldErrors.municipality" x-text="fieldErrors.municipality" class="text-[11px] text-rose-600 font-medium"></span>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label for="barangay" class="font-label-md text-xs font-semibold text-on-surface-variant">Barangay</label>
                            <select
                                id="barangay" name="barangay"
                                x-model="selectedBarangay"
                                :disabled="!selectedMunicipality || loadingBarangays"
                                class="rounded-xl border px-3 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary disabled:bg-surface-container disabled:cursor-not-allowed"
                                :class="fieldErrors.barangay ? 'border-rose-500 ring-1 ring-rose-500' : 'border-outline-variant'">
                                <option value="" disabled selected x-text="loadingBarangays ? 'Loading…' : 'Select Barangay'"></option>
                                <template x-for="b in barangays" :key="b.code">
                                    <option :value="b.code" x-text="b.name"></option>
                                </template>
                            </select>
                            <span x-show="fieldErrors.barangay" x-text="fieldErrors.barangay" class="text-[11px] text-rose-600 font-medium"></span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1.5">
                            <label for="street" class="font-label-md text-xs font-semibold text-on-surface-variant">Street Name (Optional)</label>
                            <input
                                type="text" id="street" name="street"
                                x-model="street"
                                placeholder="e.g. Ayala Avenue"
                                value="{{ old('street') }}"
                                class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label for="house_number" class="font-label-md text-xs font-semibold text-on-surface-variant">House / Unit No. (Optional)</label>
                            <input
                                type="text" id="house_number" name="house_number"
                                x-model="houseNumber"
                                placeholder="e.g. Unit 14B"
                                value="{{ old('house_number') }}"
                                class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                    </div>

                    {{-- Persistence hidden inputs --}}
                    <input type="hidden" name="province_name" :value="selectedProvinceName">
                    <input type="hidden" name="municipality_name" :value="selectedMunicipalityName">
                    <input type="hidden" name="barangay_name" :value="selectedBarangayName">

                    {{-- Step 3 Actions --}}
                    <div class="pt-4 border-t border-outline-variant/60 flex items-center justify-between gap-3">
                        <button
                            type="button"
                            @click="prevStep()"
                            class="py-2.5 px-4 rounded-full border border-outline-variant text-on-surface font-label-md text-xs font-semibold hover:bg-surface-container transition-all cursor-pointer">
                            Back
                        </button>
                        <button
                            type="button"
                            @click="nextStep()"
                            class="py-2.5 px-6 rounded-full bg-primary text-on-primary font-label-md text-xs font-semibold shadow-xs hover:bg-primary/90 transition-all cursor-pointer">
                            Continue to Verification
                        </button>
                    </div>

                </div>

                {{-- ========================================================================= --}}
                {{-- STEP 4: Role-Specific Details, Verification & Terms                       --}}
                {{-- ========================================================================= --}}
                <div x-show="step === 4" x-cloak class="p-5 md:p-6 flex flex-col gap-5">

                    {{-- Business Information — Seller / Logistics --}}
                    <div x-show="role === 'seller' || role === 'sorting_center'" x-cloak class="flex flex-col gap-4">
                        <div>
                            <h2 class="font-headline-sm text-sm font-bold text-on-surface">Business Information</h2>
                            <p class="text-xs text-on-surface-variant mt-0.5">Required for verified sellers and logistics hubs.</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="flex flex-col gap-1.5">
                                <label for="business_name" class="font-label-md text-xs font-semibold text-on-surface-variant">Business Name</label>
                                <input
                                    type="text" id="business_name" name="business_name"
                                    x-model="businessName"
                                    placeholder="e.g. Apex Tech Store"
                                    value="{{ old('business_name') }}"
                                    class="rounded-xl border px-3.5 py-2.5 text-xs text-on-surface bg-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary"
                                    :class="fieldErrors.business_name ? 'border-rose-500 ring-1 ring-rose-500' : 'border-outline-variant'">
                                <span class="text-[10px] text-on-surface-variant">Must be unique (no duplicate business names allowed).</span>
                                <span x-show="fieldErrors.business_name" x-text="fieldErrors.business_name" class="text-[11px] text-rose-600 font-medium"></span>
                            </div>

                            <div class="flex flex-col gap-1.5" x-show="role === 'seller'">
                                <label for="category" class="font-label-md text-xs font-semibold text-on-surface-variant">Primary Product Category</label>
                                <select
                                    id="category" name="category"
                                    x-model="category"
                                    class="rounded-xl border px-3 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary"
                                    :class="fieldErrors.category ? 'border-rose-500 ring-1 ring-rose-500' : 'border-outline-variant'">
                                    <option value="" disabled selected>Select category</option>
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat['slug'] }}">{{ $cat['name'] }}</option>
                                    @endforeach
                                </select>
                                <span x-show="fieldErrors.category" x-text="fieldErrors.category" class="text-[11px] text-rose-600 font-medium"></span>
                            </div>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label class="font-label-md text-xs font-semibold text-on-surface-variant">Business / DTI Permit File</label>
                            <label
                                for="business_permit"
                                class="cursor-pointer rounded-2xl border-2 border-dashed hover:border-primary/60 transition-colors bg-surface-container-low/50 flex flex-col items-center justify-center gap-1.5 py-4 px-4 text-center"
                                :class="fieldErrors.business_permit ? 'border-rose-500 bg-rose-500/5' : 'border-outline-variant'">
                                <span class="text-xs font-semibold text-on-surface" x-text="businessPermitFileName || 'Click to select permit file'"></span>
                                <span class="text-[11px] text-on-surface-variant">Supported formats: PDF, JPG, PNG (Max 5MB)</span>
                            </label>
                            <input
                                type="file" id="business_permit" name="business_permit"
                                accept=".jpg,.jpeg,.png,.pdf" class="hidden"
                                @change="businessPermitFileName = $event.target.files[0]?.name ?? ''; fieldErrors.business_permit = ''">
                            <span x-show="fieldErrors.business_permit" x-text="fieldErrors.business_permit" class="text-[11px] text-rose-600 font-medium"></span>
                        </div>
                    </div>

                    {{-- Courier / Rider Information --}}
                    <div x-show="role === 'courier'" x-cloak class="flex flex-col gap-4">
                        <div>
                            <h2 class="font-headline-sm text-sm font-bold text-on-surface">Vehicle & Rider Information</h2>
                            <p class="text-xs text-on-surface-variant mt-0.5">Required for registered delivery partners and couriers.</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="flex flex-col gap-1.5">
                                <label for="vehicle_type" class="font-label-md text-xs font-semibold text-on-surface-variant">Vehicle Type</label>
                                <select
                                    id="vehicle_type" name="vehicle_type"
                                    x-model="vehicleType"
                                    class="rounded-xl border px-3 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary"
                                    :class="fieldErrors.vehicle_type ? 'border-rose-500 ring-1 ring-rose-500' : 'border-outline-variant'">
                                    <option value="" disabled selected>Select Vehicle Type</option>
                                    <option value="Motorcycle">Motorcycle</option>
                                    <option value="Scooter">Scooter</option>
                                    <option value="Bicycle">Bicycle</option>
                                    <option value="Sedan / Car">Sedan / Car</option>
                                    <option value="Van / MPV">Van / MPV</option>
                                    <option value="Light Truck">Light Truck</option>
                                </select>
                                <span x-show="fieldErrors.vehicle_type" x-text="fieldErrors.vehicle_type" class="text-[11px] text-rose-600 font-medium"></span>
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label for="plate_number" class="font-label-md text-xs font-semibold text-on-surface-variant">Plate / Sticker Number</label>
                                <input
                                    type="text" id="plate_number" name="plate_number"
                                    x-model="plateNumber"
                                    placeholder="e.g. ABC 1234"
                                    value="{{ old('plate_number') }}"
                                    class="rounded-xl border px-3.5 py-2.5 text-xs text-on-surface bg-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary"
                                    :class="fieldErrors.plate_number ? 'border-rose-500 ring-1 ring-rose-500' : 'border-outline-variant'">
                                <span x-show="fieldErrors.plate_number" x-text="fieldErrors.plate_number" class="text-[11px] text-rose-600 font-medium"></span>
                            </div>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label class="font-label-md text-xs font-semibold text-on-surface-variant">Vehicle OR/CR Document</label>
                            <label
                                for="or_cr"
                                class="cursor-pointer rounded-2xl border-2 border-dashed hover:border-primary/60 transition-colors bg-surface-container-low/50 flex flex-col items-center justify-center gap-1.5 py-4 px-4 text-center"
                                :class="fieldErrors.or_cr ? 'border-rose-500 bg-rose-500/5' : 'border-outline-variant'">
                                <span class="text-xs font-semibold text-on-surface" x-text="orCrFileName || 'Click to select OR/CR file'"></span>
                                <span class="text-[11px] text-on-surface-variant">Supported formats: PDF, JPG, PNG (Max 5MB)</span>
                            </label>
                            <input
                                type="file" id="or_cr" name="or_cr"
                                accept=".jpg,.jpeg,.png,.pdf" class="hidden"
                                @change="orCrFileName = $event.target.files[0]?.name ?? ''; fieldErrors.or_cr = ''">
                            <span x-show="fieldErrors.or_cr" x-text="fieldErrors.or_cr" class="text-[11px] text-rose-600 font-medium"></span>
                        </div>
                    </div>

                    {{-- Identity Verification --}}
                    <div class="flex flex-col gap-2">
                        <div>
                            <h2 class="font-headline-sm text-sm font-bold text-on-surface">Identity Verification</h2>
                            <p class="text-xs text-on-surface-variant mt-0.5">Upload a clear photo or scan of a valid government-issued ID.</p>
                        </div>

                        <label
                            for="valid_id"
                            class="cursor-pointer rounded-2xl border-2 border-dashed hover:border-primary/60 transition-colors bg-surface-container-low/50 flex flex-col items-center justify-center gap-1.5 py-5 px-4 text-center"
                            :class="fieldErrors.valid_id ? 'border-rose-500 bg-rose-500/5' : 'border-outline-variant'">
                            <span class="text-xs font-semibold text-on-surface" x-text="idFileName || 'Click to choose government ID'"></span>
                            <span class="text-[11px] text-on-surface-variant">Supported formats: JPG, PNG, PDF (Max 5MB)</span>
                        </label>
                        <input
                            type="file" id="valid_id" name="valid_id"
                            accept=".jpg,.jpeg,.png,.svg,.pdf" class="hidden"
                            @change="idFileName = $event.target.files[0]?.name ?? ''; fieldErrors.valid_id = ''">
                        <span x-show="fieldErrors.valid_id" x-text="fieldErrors.valid_id" class="text-[11px] text-rose-600 font-medium"></span>
                    </div>

                    {{-- Terms Agreement --}}
                    <div class="flex flex-col gap-1.5 pt-1">
                        <label class="flex items-start gap-3 cursor-pointer bg-surface-container-low rounded-xl p-3.5 select-none"
                            :class="fieldErrors.terms ? 'border border-rose-500 bg-rose-500/5' : ''">
                            <input
                                type="checkbox" name="terms" value="1"
                                x-model="termsAccepted"
                                class="mt-0.5 w-4 h-4 rounded border-outline-variant text-primary focus:ring-primary cursor-pointer">
                            <span class="text-xs text-on-surface-variant leading-relaxed">
                                I certify that the information provided is accurate and true. I agree to comply with Nexora Market
                                <a href="{{ route('terms') }}" target="_blank" class="text-primary font-semibold hover:underline">Terms of Service</a>
                                and
                                <a href="{{ route('privacy') }}" target="_blank" class="text-primary font-semibold hover:underline">Privacy Policy</a>.
                            </span>
                        </label>
                        <span x-show="fieldErrors.terms" x-text="fieldErrors.terms" class="text-[11px] text-rose-600 font-medium"></span>
                    </div>

                    {{-- Step 4 Actions --}}
                    <div class="pt-4 border-t border-outline-variant/60 flex items-center justify-between gap-3">
                        <button
                            type="button"
                            @click="prevStep()"
                            class="py-2.5 px-4 rounded-full border border-outline-variant text-on-surface font-label-md text-xs font-semibold hover:bg-surface-container transition-all cursor-pointer">
                            Back
                        </button>
                        <button
                            type="submit"
                            :disabled="isSubmitting"
                            class="py-2.5 px-6 rounded-full bg-primary text-on-primary font-label-md text-xs font-semibold shadow-xs hover:bg-primary/90 transition-all cursor-pointer disabled:opacity-50">
                            <span x-text="isSubmitting ? 'Registering…' : 'Complete Registration'"></span>
                        </button>
                    </div>

                </div>

            </form>

            {{-- Compact Footer --}}
            <div class="mt-8 pt-6 border-t border-outline-variant flex flex-col sm:flex-row items-center justify-between gap-3 text-[11px] text-on-surface-variant">
                <span>&copy; {{ now()->year }} Nexora Market. All rights reserved.</span>
                <div class="flex items-center gap-4">
                    <a href="{{ route('privacy') }}" target="_blank" class="hover:text-primary transition-colors">Privacy Policy</a>
                    <a href="{{ route('terms') }}" target="_blank" class="hover:text-primary transition-colors">Terms of Service</a>
                    <a href="#" class="hover:text-primary transition-colors">Help Center</a>
                </div>
            </div>

        </div>
    </div>

</div>

@push('scripts')
<script>
    function registerForm(initialStep = 1) {
        return {
            step: initialStep,
            maxStepReached: initialStep,
            isSubmitting: false,
            stepError: '',
            fieldErrors: {},

            stepTitles: {
                1: 'Profile Details',
                2: 'Security & Phone',
                3: 'Official Address',
                4: 'Verification'
            },
            stepSubtitles: {
                1: 'Choose your account type and enter your legal personal identification.',
                2: 'Configure your login email, phone number, and a strong password.',
                3: 'Provide your Philippine delivery address for verification.',
                4: 'Upload required verification files and accept marketplace terms.'
            },

            role: '{{ old('role', 'buyer') }}',
            roleOptions: [
                { value: 'buyer', label: 'Buyer' },
                { value: 'seller', label: 'Seller' },
                { value: 'sorting_center', label: 'Logistics' },
                { value: 'courier', label: 'Rider / Courier' },
            ],

            firstName: @json(old('first_name', '')),
            lastName: @json(old('last_name', '')),
            middleInitial: @json(old('middle_initial', '')),
            sex: @json(old('sex', '')),
            birthday: @json(old('birthday', '')),
            age: '',
            ageError: '',

            email: @json(old('email', '')),
            contactNumber: @json(old('contact_number', '')),
            password: '',
            passwordConfirmation: '',
            showPassword: false,
            showPasswordConfirm: false,

            street: @json(old('street', '')),
            houseNumber: @json(old('house_number', '')),
            provinces: [],
            municipalities: [],
            barangays: [],
            selectedProvince: @json(old('province', '')),
            selectedMunicipality: @json(old('municipality', '')),
            selectedBarangay: @json(old('barangay', '')),
            loadingProvinces: false,
            loadingMunicipalities: false,
            loadingBarangays: false,

            businessName: @json(old('business_name', '')),
            category: @json(old('category', '')),
            vehicleType: @json(old('vehicle_type', '')),
            plateNumber: @json(old('plate_number', '')),

            idFileName: '',
            businessPermitFileName: '',
            orCrFileName: '',
            termsAccepted: false,

            get selectedProvinceName() {
                return this.provinces.find((p) => p.code === this.selectedProvince)?.name ?? '';
            },
            get selectedMunicipalityName() {
                return this.municipalities.find((m) => m.code === this.selectedMunicipality)?.name ?? '';
            },
            get selectedBarangayName() {
                return this.barangays.find((b) => b.code === this.selectedBarangay)?.name ?? '';
            },

            initForm() {
                if (this.birthday) {
                    this.calculateAge();
                }
                this.loadProvinces();
            },

            calculateAge() {
                if (!this.birthday) { this.age = ''; this.ageError = ''; return; }
                const birthDate = new Date(this.birthday);
                const birthYear = birthDate.getFullYear();
                if (birthYear >= 2026) {
                    this.age = '';
                    this.ageError = 'Birth year cannot be in 2026 or later.';
                    return;
                }
                const today = new Date();
                let age = today.getFullYear() - birthYear;
                const m = today.getMonth() - birthDate.getMonth();
                if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
                    age--;
                }
                if (age <= 0) {
                    this.age = '0';
                    this.ageError = 'Age cannot be 0. You must be at least 1 year old.';
                    return;
                }
                this.ageError = '';
                this.age = age;
            },

            async loadProvinces() {
                this.loadingProvinces = true;
                try {
                    const res = await fetch('https://psgc.gitlab.io/api/provinces/');
                    const data = await res.json();
                    this.provinces = data.sort((a, b) => a.name.localeCompare(b.name));
                    if (this.selectedProvince) {
                        await this.onProvinceChange(true);
                    }
                } catch (e) {
                    console.error('Failed to load provinces', e);
                } finally {
                    this.loadingProvinces = false;
                }
            },

            async onProvinceChange(preserveSelected = false) {
                if (!preserveSelected) {
                    this.municipalities = [];
                    this.barangays = [];
                    this.selectedMunicipality = '';
                    this.selectedBarangay = '';
                }
                if (!this.selectedProvince) return;

                this.loadingMunicipalities = true;
                try {
                    const res = await fetch(`https://psgc.gitlab.io/api/provinces/${this.selectedProvince}/cities-municipalities/`);
                    const data = await res.json();
                    this.municipalities = data.sort((a, b) => a.name.localeCompare(b.name));
                    if (preserveSelected && this.selectedMunicipality) {
                        await this.onMunicipalityChange(true);
                    }
                } catch (e) {
                    console.error('Failed to load municipalities', e);
                } finally {
                    this.loadingMunicipalities = false;
                }
            },

            async onMunicipalityChange(preserveSelected = false) {
                if (!preserveSelected) {
                    this.barangays = [];
                    this.selectedBarangay = '';
                }
                if (!this.selectedMunicipality) return;

                this.loadingBarangays = true;
                try {
                    const res = await fetch(`https://psgc.gitlab.io/api/cities-municipalities/${this.selectedMunicipality}/barangays/`);
                    const data = await res.json();
                    this.barangays = data.sort((a, b) => a.name.localeCompare(b.name));
                } catch (e) {
                    console.error('Failed to load barangays', e);
                } finally {
                    this.loadingBarangays = false;
                }
            },

            validateStep(s) {
                this.stepError = '';
                this.fieldErrors = {};
                const nameRegex = /^[a-zA-Z\sñÑ]+$/;

                if (s === 1) {
                    const fName = (this.firstName || '').trim();
                    const lName = (this.lastName || '').trim();

                    if (!fName) {
                        this.fieldErrors.first_name = 'First name is required.';
                    } else if (fName.length < 2) {
                        this.fieldErrors.first_name = 'First name must be at least 2 characters.';
                    } else if (fName.length > 50) {
                        this.fieldErrors.first_name = 'First name cannot exceed 50 characters.';
                    } else if (!nameRegex.test(fName)) {
                        this.fieldErrors.first_name = 'First name must contain letters and spaces only (no special characters).';
                    }

                    if (!lName) {
                        this.fieldErrors.last_name = 'Last name is required.';
                    } else if (lName.length < 2) {
                        this.fieldErrors.last_name = 'Last name must be at least 2 characters.';
                    } else if (lName.length > 50) {
                        this.fieldErrors.last_name = 'Last name cannot exceed 50 characters.';
                    } else if (!nameRegex.test(lName)) {
                        this.fieldErrors.last_name = 'Last name must contain letters and spaces only (no special characters).';
                    }

                    if (fName && lName && fName.toLowerCase() === lName.toLowerCase()) {
                        this.fieldErrors.first_name = 'First name and last name cannot be identical.';
                    }

                    if (this.middleInitial && !nameRegex.test(this.middleInitial.trim())) {
                        this.fieldErrors.middle_initial = 'Middle initial may only contain letters.';
                    }

                    if (!this.sex) {
                        this.fieldErrors.sex = 'Please select your sex.';
                    }

                    this.calculateAge();
                    if (!this.birthday) {
                        this.fieldErrors.birthday = 'Birthday is required.';
                    } else if (this.ageError) {
                        this.fieldErrors.birthday = this.ageError;
                    }

                    if (Object.keys(this.fieldErrors).length > 0) {
                        this.stepError = 'Please correct the highlighted fields in your profile details.';
                        return false;
                    }
                    return true;
                }

                if (s === 2) {
                    const emailTrim = (this.email || '').trim();
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailTrim) {
                        this.fieldErrors.email = 'Email address is required.';
                    } else if (!emailRegex.test(emailTrim)) {
                        this.fieldErrors.email = 'Please provide a valid email address.';
                    }

                    const phoneTrim = (this.contactNumber || '').trim();
                    const phoneRegex = /^09\d{9}$/;
                    if (!phoneTrim) {
                        this.fieldErrors.contact_number = 'Contact number is required.';
                    } else if (!phoneRegex.test(phoneTrim)) {
                        this.fieldErrors.contact_number = 'Must be an 11-digit Philippine mobile number starting with 09.';
                    }

                    if (!this.password) {
                        this.fieldErrors.password = 'Password is required.';
                    } else {
                        const p = this.password;
                        const hasLength = p.length >= 8;
                        const hasUpper = /[A-Z]/.test(p);
                        const hasLower = /[a-z]/.test(p);
                        const hasNum = /[0-9]/.test(p);
                        const hasSym = /[^A-Za-z0-9]/.test(p);
                        if (!hasLength || !hasUpper || !hasLower || !hasNum || !hasSym) {
                            this.fieldErrors.password = 'Password must be at least 8 characters and include uppercase, lowercase, numbers, and special characters.';
                        }
                    }

                    if (!this.passwordConfirmation) {
                        this.fieldErrors.password_confirmation = 'Please confirm your password.';
                    } else if (this.password !== this.passwordConfirmation) {
                        this.fieldErrors.password_confirmation = 'Passwords do not match.';
                    }

                    if (Object.keys(this.fieldErrors).length > 0) {
                        this.stepError = 'Please correct the highlighted security credentials.';
                        return false;
                    }
                    return true;
                }

                if (s === 3) {
                    if (!this.selectedProvince) {
                        this.fieldErrors.province = 'Please select your province.';
                    }
                    if (!this.selectedMunicipality) {
                        this.fieldErrors.municipality = 'Please select your city or municipality.';
                    }
                    if (!this.selectedBarangay) {
                        this.fieldErrors.barangay = 'Please select your barangay.';
                    }

                    if (Object.keys(this.fieldErrors).length > 0) {
                        this.stepError = 'Please select your complete residential location.';
                        return false;
                    }
                    return true;
                }

                if (s === 4) {
                    if (this.role === 'seller' || this.role === 'sorting_center') {
                        const bName = (this.businessName || '').trim();
                        if (!bName) {
                            this.fieldErrors.business_name = 'Business name is required.';
                        } else if (bName.length < 2) {
                            this.fieldErrors.business_name = 'Business name must be at least 2 characters.';
                        }
                        if (this.role === 'seller' && !this.category) {
                            this.fieldErrors.category = 'Please choose a primary product category.';
                        }
                        const permitInput = document.getElementById('business_permit');
                        if (!this.businessPermitFileName && (!permitInput || !permitInput.files || permitInput.files.length === 0)) {
                            this.fieldErrors.business_permit = 'Please upload a business or DTI permit.';
                        }
                    }

                    if (this.role === 'courier') {
                        if (!this.vehicleType) {
                            this.fieldErrors.vehicle_type = 'Please select your vehicle type.';
                        }
                        if (!(this.plateNumber || '').trim()) {
                            this.fieldErrors.plate_number = 'Please enter your vehicle plate or sticker number.';
                        }
                        const orCrInput = document.getElementById('or_cr');
                        if (!this.orCrFileName && (!orCrInput || !orCrInput.files || orCrInput.files.length === 0)) {
                            this.fieldErrors.or_cr = 'Please upload your vehicle OR/CR document.';
                        }
                    }

                    const validIdInput = document.getElementById('valid_id');
                    if (!this.idFileName && (!validIdInput || !validIdInput.files || validIdInput.files.length === 0)) {
                        this.fieldErrors.valid_id = 'Please upload a clear copy of a valid government ID.';
                    }

                    if (!this.termsAccepted) {
                        this.fieldErrors.terms = 'You must agree to the Terms of Service and Privacy Policy to proceed.';
                    }

                    if (Object.keys(this.fieldErrors).length > 0) {
                        this.stepError = 'Please review required documents and agree to terms to complete registration.';
                        return false;
                    }
                    return true;
                }

                return true;
            },

            nextStep() {
                if (this.validateStep(this.step)) {
                    if (this.step < 4) {
                        this.step++;
                        this.maxStepReached = Math.max(this.maxStepReached, this.step);
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                }
            },

            prevStep() {
                if (this.step > 1) {
                    this.step--;
                    this.stepError = '';
                    this.fieldErrors = {};
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            },

            goToStep(targetStep) {
                if (targetStep <= this.maxStepReached) {
                    this.step = targetStep;
                    this.stepError = '';
                    this.fieldErrors = {};
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            },

            handleSubmit(e) {
                if (!this.validateStep(4)) {
                    e.preventDefault();
                    return;
                }
                this.isSubmitting = true;
            }
        };
    }
</script>
@endpush

@endsection
