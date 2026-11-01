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

            {{-- 2. Principal Business Address --}}
            <div class="flex flex-col gap-4">
                <div class="border-b border-outline-variant/60 pb-3">
                    <h2 class="font-headline-sm text-sm sm:text-base font-bold text-on-surface flex items-center gap-2">
                        <span class="w-6 h-6 rounded-lg bg-primary/10 text-primary text-xs font-extrabold flex items-center justify-center">2</span>
                        Principal Hub & Office Address
                    </h2>
                    <p class="text-xs text-on-surface-variant mt-0.5">Physical dispatch origin and central hub facility location.</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                    <div class="flex flex-col gap-1">
                        <label for="region" class="text-xs font-semibold text-on-surface-variant">Region <span class="text-rose-500">*</span></label>
                        <input type="text" id="region" name="region" value="{{ old('region', 'National Capital Region (NCR)') }}" required class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>

                    <div class="flex flex-col gap-1">
                        <label for="province" class="text-xs font-semibold text-on-surface-variant">Province / District <span class="text-rose-500">*</span></label>
                        <input type="text" id="province" name="province" value="{{ old('province', 'Metro Manila') }}" required class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>

                    <div class="flex flex-col gap-1">
                        <label for="city_municipality" class="text-xs font-semibold text-on-surface-variant">City / Municipality <span class="text-rose-500">*</span></label>
                        <input type="text" id="city_municipality" name="city_municipality" value="{{ old('city_municipality') }}" required placeholder="e.g. Taguig City" class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>

                    <div class="flex flex-col gap-1">
                        <label for="barangay" class="text-xs font-semibold text-on-surface-variant">Barangay <span class="text-rose-500">*</span></label>
                        <input type="text" id="barangay" name="barangay" value="{{ old('barangay') }}" required placeholder="e.g. Fort Bonifacio" class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>

                    <div class="flex flex-col gap-1 sm:col-span-2">
                        <label for="street_address" class="text-xs font-semibold text-on-surface-variant">Street Address / Building / Unit No. <span class="text-rose-500">*</span></label>
                        <input type="text" id="street_address" name="street_address" value="{{ old('street_address') }}" required placeholder="e.g. Unit 402 Logistics Tower, 32nd Street" class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>

                    <div class="flex flex-col gap-1">
                        <label for="postal_code" class="text-xs font-semibold text-on-surface-variant">Postal Code (4 Digits) <span class="text-rose-500">*</span></label>
                        <input type="text" id="postal_code" name="postal_code" value="{{ old('postal_code') }}" required maxlength="4" placeholder="1630" class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface font-mono focus:outline-none focus:ring-2 focus:ring-primary">
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
                        <label for="mobile_number" class="text-xs font-semibold text-on-surface-variant">Official Mobile Number <span class="text-rose-500">*</span></label>
                        <input type="tel" id="mobile_number" name="mobile_number" value="{{ old('mobile_number') }}" required placeholder="09171234567" class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>

                    <div class="flex flex-col gap-1 md:col-span-2">
                        <label for="email" class="text-xs font-semibold text-on-surface-variant">Official Corporate Email (Receives 6-digit OTP verification) <span class="text-rose-500">*</span></label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" required placeholder="operations@metroexpress.ph" class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                </div>
            </div>

            {{-- 4. Regional Service Coverage Areas --}}
            <div class="flex flex-col gap-4">
                <div class="border-b border-outline-variant/60 pb-3">
                    <h2 class="font-headline-sm text-sm sm:text-base font-bold text-on-surface flex items-center gap-2">
                        <span class="w-6 h-6 rounded-lg bg-primary/10 text-primary text-xs font-extrabold flex items-center justify-center">4</span>
                        Regional Delivery & Service Coverage Areas
                    </h2>
                    <p class="text-xs text-on-surface-variant mt-0.5">Select all territories your courier riders and sorting facilities can cover.</p>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2.5 select-none">
                    @php
                        $regionsList = [
                            'Metro Manila (NCR)',
                            'Calabarzon (Region IV-A)',
                            'Central Luzon (Region III)',
                            'North Luzon (Ilocos & CAR)',
                            'Cagayan Valley (Region II)',
                            'Bicol Region (Region V)',
                            'Western Visayas (Region VI)',
                            'Central Visayas (Region VII)',
                            'Eastern Visayas (Region VIII)',
                            'Northern Mindanao (Region X)',
                            'Davao Region (Region XI)',
                            'SOCCSKSARGEN (Region XII)',
                        ];
                        $oldAreas = (array) old('service_areas', ['Metro Manila (NCR)']);
                    @endphp

                    @foreach($regionsList as $area)
                        <label class="flex items-center gap-2.5 p-3 rounded-2xl border cursor-pointer transition-all"
                            :class="selectedAreas.includes('{{ $area }}') ? 'border-primary bg-primary/10 text-primary font-bold shadow-xs' : 'border-outline-variant/70 bg-surface hover:bg-surface-container-low text-on-surface'">
                            <input
                                type="checkbox"
                                name="service_areas[]"
                                value="{{ $area }}"
                                @change="toggleArea('{{ $area }}')"
                                {{ in_array($area, $oldAreas) ? 'checked' : '' }}
                                class="w-4 h-4 rounded text-primary focus:ring-primary border-outline-variant">
                            <span class="text-xs leading-tight">{{ $area }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- 5. Compliance Documents Upload --}}
            <div class="flex flex-col gap-4">
                <div class="border-b border-outline-variant/60 pb-3">
                    <h2 class="font-headline-sm text-sm sm:text-base font-bold text-on-surface flex items-center gap-2">
                        <span class="w-6 h-6 rounded-lg bg-primary/10 text-primary text-xs font-extrabold flex items-center justify-center">5</span>
                        Compliance Documents Upload
                    </h2>
                    <p class="text-xs text-on-surface-variant mt-0.5">Clear scans or PDF copies (Maximum 2MB per document; Allowed: PDF, JPG, PNG).</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- SEC Certificate --}}
                    <div class="flex flex-col gap-1.5 p-4 rounded-2xl border border-dashed border-outline-variant bg-surface-container-low/40">
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-bold text-on-surface">1. SEC Registration Certificate <span class="text-rose-500">*</span></label>
                            <span class="text-[10px] text-outline">Max 2MB</span>
                        </div>
                        <input type="file" name="sec_certificate" accept=".pdf,.jpg,.jpeg,.png" required class="text-xs text-on-surface-variant file:mr-2 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-primary file:text-on-primary">
                    </div>

                    {{-- BIR Form 2303 --}}
                    <div class="flex flex-col gap-1.5 p-4 rounded-2xl border border-dashed border-outline-variant bg-surface-container-low/40">
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-bold text-on-surface">2. BIR Form 2303 Certificate <span class="text-rose-500">*</span></label>
                            <span class="text-[10px] text-outline">Max 2MB</span>
                        </div>
                        <input type="file" name="bir_2303" accept=".pdf,.jpg,.jpeg,.png" required class="text-xs text-on-surface-variant file:mr-2 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-primary file:text-on-primary">
                    </div>

                    {{-- Mayor's Permit --}}
                    <div class="flex flex-col gap-1.5 p-4 rounded-2xl border border-dashed border-outline-variant bg-surface-container-low/40">
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-bold text-on-surface">3. Mayor's / Business Permit <span class="text-rose-500">*</span></label>
                            <span class="text-[10px] text-outline">Max 2MB</span>
                        </div>
                        <input type="file" name="mayors_permit" accept=".pdf,.jpg,.jpeg,.png" required class="text-xs text-on-surface-variant file:mr-2 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-primary file:text-on-primary">
                    </div>

                    {{-- Valid ID --}}
                    <div class="flex flex-col gap-1.5 p-4 rounded-2xl border border-dashed border-outline-variant bg-surface-container-low/40">
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-bold text-on-surface">4. Valid Government ID of Signatory <span class="text-rose-500">*</span></label>
                            <span class="text-[10px] text-outline">Max 2MB</span>
                        </div>
                        <input type="file" name="valid_id" accept=".pdf,.jpg,.jpeg,.png" required class="text-xs text-on-surface-variant file:mr-2 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-primary file:text-on-primary">
                    </div>
                </div>
            </div>

            {{-- 6. Account Password & Terms --}}
            <div class="flex flex-col gap-4">
                <div class="border-b border-outline-variant/60 pb-3">
                    <h2 class="font-headline-sm text-sm sm:text-base font-bold text-on-surface flex items-center gap-2">
                        <span class="w-6 h-6 rounded-lg bg-primary/10 text-primary text-xs font-extrabold flex items-center justify-center">6</span>
                        Account Security & Agreement
                    </h2>
                    <p class="text-xs text-on-surface-variant mt-0.5">Password must be at least 8 characters and include uppercase, lowercase, numbers, and symbols.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="flex flex-col gap-1">
                        <label for="password" class="text-xs font-semibold text-on-surface-variant">Account Password <span class="text-rose-500">*</span></label>
                        <input type="password" id="password" name="password" required placeholder="••••••••" class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>

                    <div class="flex flex-col gap-1">
                        <label for="password_confirmation" class="text-xs font-semibold text-on-surface-variant">Confirm Password <span class="text-rose-500">*</span></label>
                        <input type="password" id="password_confirmation" name="password_confirmation" required placeholder="••••••••" class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
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
            tin: '{{ old('tin', '') }}',
            selectedAreas: @json(old('service_areas', ['Metro Manila (NCR)'])),

            formatTin(e) {
                let v = e.target.value.replace(/[^0-9]/g, '').slice(0, 12);
                let parts = [];
                for (let i = 0; i < v.length; i += 3) {
                    parts.push(v.substring(i, i + 3));
                }
                this.tin = parts.join('-');
            },

            toggleArea(area) {
                const idx = this.selectedAreas.indexOf(area);
                if (idx === -1) {
                    this.selectedAreas.push(area);
                } else {
                    this.selectedAreas.splice(idx, 1);
                }
            },

            handleSubmit(e) {
                if (this.selectedAreas.length === 0) {
                    e.preventDefault();
                    alert('Please select at least one regional service area of logistics coverage.');
                    return;
                }
                this.isSubmitting = true;
            }
        };
    }
</script>
@endpush
@endsection

