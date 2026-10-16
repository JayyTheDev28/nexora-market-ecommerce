@extends('layouts.app')

@section('title', 'Create Your Account — Nexora Market')

@section('content')
<div
    x-data="registerForm()"
    x-init="loadProvinces()"
    class="w-full flex flex-col md:flex-row min-h-[calc(100vh-4rem)] bg-surface-container-lowest">

    <x-brand-panel caption="Join thousands of shoppers discovering authentic products every day." />

    {{-- Form Column --}}
    <div class="flex-1 overflow-y-auto">
        <div class="max-w-2xl mx-auto px-6 md:px-10 py-8 md:py-10">

            <div class="mb-6">
                <h1 class="font-headline-lg text-2xl md:text-3xl font-bold tracking-tight text-on-surface">Create Your Account</h1>
                <p class="font-body-md text-xs md:text-sm text-on-surface-variant mt-1.5">
                    Register with Nexora to enjoy curated marketplace deals and fast deliveries.
                </p>
            </div>

            @if ($errors->any())
                <div class="mb-6 bg-rose-500/10 border border-rose-500/20 rounded-2xl p-4">
                    <p class="font-label-md text-xs md:text-sm font-bold text-rose-700">Please review the following errors:</p>
                    <ul class="list-disc list-inside text-xs text-rose-600 mt-1 space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form
                class="flex flex-col bg-surface-container-lowest rounded-3xl border border-outline-variant shadow-xs overflow-hidden"
                action="{{ route('register.store') }}" method="POST" enctype="multipart/form-data">
                @csrf

                {{-- Account Type --}}
                <div class="p-5 md:p-6 border-b border-outline-variant/60 flex flex-col gap-3">
                    <div>
                        <h2 class="font-headline-sm text-sm font-bold text-on-surface">Register As</h2>
                        <p class="text-xs text-on-surface-variant mt-0.5">Choose your account type on the platform.</p>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <template x-for="option in roleOptions" :key="option.value">
                            <label
                                class="flex flex-col items-center gap-1.5 p-3 rounded-2xl border cursor-pointer transition-all text-center select-none"
                                :class="role === option.value ? 'border-primary bg-primary/5 text-primary font-bold shadow-xs' : 'border-outline-variant text-on-surface-variant hover:border-outline hover:bg-surface-container-low'">
                                <input type="radio" name="role" :value="option.value" x-model="role" class="sr-only">
                                <span class="text-xs font-semibold" x-text="option.label"></span>
                            </label>
                        </template>
                    </div>
                </div>

                {{-- Personal Information --}}
                <div class="p-5 md:p-6 border-b border-outline-variant/60 flex flex-col gap-4">
                    <div>
                        <h2 class="font-headline-sm text-sm font-bold text-on-surface">Personal Information</h2>
                        <p class="text-xs text-on-surface-variant mt-0.5">Enter your legal name and demographic details.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1.5">
                            <label for="first_name" class="font-label-md text-xs font-semibold text-on-surface-variant">First Name</label>
                            <input
                                type="text" id="first_name" name="first_name" required placeholder="e.g. Maria"
                                value="{{ old('first_name') }}"
                                class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label for="last_name" class="font-label-md text-xs font-semibold text-on-surface-variant">Last Name</label>
                            <input
                                type="text" id="last_name" name="last_name" required placeholder="e.g. Santos"
                                value="{{ old('last_name') }}"
                                class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="flex flex-col gap-1.5">
                            <label for="middle_initial" class="font-label-md text-xs font-semibold text-on-surface-variant">Middle Initial</label>
                            <input
                                type="text" id="middle_initial" name="middle_initial" maxlength="5" placeholder="M.I."
                                value="{{ old('middle_initial') }}"
                                class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label for="sex" class="font-label-md text-xs font-semibold text-on-surface-variant">Sex</label>
                            <select
                                id="sex" name="sex" required
                                class="rounded-xl border border-outline-variant px-3 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                                <option value="" disabled selected>Select</option>
                                <option value="female" {{ old('sex') === 'female' ? 'selected' : '' }}>Female</option>
                                <option value="male" {{ old('sex') === 'male' ? 'selected' : '' }}>Male</option>
                                <option value="other" {{ old('sex') === 'other' ? 'selected' : '' }}>Other</option>
                            </select>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label for="birthday" class="font-label-md text-xs font-semibold text-on-surface-variant">Birthday</label>
                            <input
                                type="date" id="birthday" name="birthday" required
                                x-model="birthday" @change="calculateAge()"
                                class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label for="age" class="font-label-md text-xs font-semibold text-on-surface-variant">Age</label>
                            <input
                                type="text" id="age" name="age" readonly disabled
                                x-model="age" placeholder="--"
                                class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface-variant bg-surface-container cursor-not-allowed">
                        </div>
                    </div>
                </div>

                {{-- Contact Details --}}
                <div class="p-5 md:p-6 border-b border-outline-variant/60 flex flex-col gap-4">
                    <div>
                        <h2 class="font-headline-sm text-sm font-bold text-on-surface">Account & Security</h2>
                        <p class="text-xs text-on-surface-variant mt-0.5">Set up your credentials for logging into Nexora.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1.5">
                            <label for="email" class="font-label-md text-xs font-semibold text-on-surface-variant">Email Address</label>
                            <input
                                type="email" id="email" name="email" required placeholder="name@example.com"
                                value="{{ old('email') }}"
                                class="w-full rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label for="contact_number" class="font-label-md text-xs font-semibold text-on-surface-variant">Contact Number</label>
                            <input
                                type="tel" id="contact_number" name="contact_number" required placeholder="+63 9XX XXX XXXX"
                                value="{{ old('contact_number') }}"
                                class="w-full rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1.5">
                            <label for="password" class="font-label-md text-xs font-semibold text-on-surface-variant">Password</label>
                            <div class="relative">
                                <input
                                    :type="showPassword ? 'text' : 'password'" id="password" name="password" required minlength="8" placeholder="At least 8 characters"
                                    class="w-full rounded-xl border border-outline-variant pl-3.5 pr-11 py-2.5 text-xs text-on-surface bg-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary">
                                <button type="button" @click="showPassword = !showPassword" class="absolute right-3 top-1/2 -translate-y-1/2 text-outline hover:text-on-surface-variant p-1 cursor-pointer" aria-label="Toggle password visibility">
                                    <span class="material-symbols-outlined text-[18px]" x-text="showPassword ? 'visibility_off' : 'visibility'"></span>
                                </button>
                            </div>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label for="password_confirmation" class="font-label-md text-xs font-semibold text-on-surface-variant">Confirm Password</label>
                            <div class="relative">
                                <input
                                    :type="showPasswordConfirm ? 'text' : 'password'" id="password_confirmation" name="password_confirmation" required placeholder="Re-enter password"
                                    class="w-full rounded-xl border border-outline-variant pl-3.5 pr-11 py-2.5 text-xs text-on-surface bg-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary">
                                <button type="button" @click="showPasswordConfirm = !showPasswordConfirm" class="absolute right-3 top-1/2 -translate-y-1/2 text-outline hover:text-on-surface-variant p-1 cursor-pointer" aria-label="Toggle password visibility">
                                    <span class="material-symbols-outlined text-[18px]" x-text="showPasswordConfirm ? 'visibility_off' : 'visibility'"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Residential Address --}}
                <div class="p-5 md:p-6 border-b border-outline-variant/60 flex flex-col gap-4">
                    <div>
                        <h2 class="font-headline-sm text-sm font-bold text-on-surface">Residential Address</h2>
                        <p class="text-xs text-on-surface-variant mt-0.5">Your official residence for order delivery and verification.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="flex flex-col gap-1.5">
                            <label for="province" class="font-label-md text-xs font-semibold text-on-surface-variant">Province</label>
                            <select
                                id="province" name="province" required
                                x-model="selectedProvince" @change="onProvinceChange()"
                                class="rounded-xl border border-outline-variant px-3 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary disabled:bg-surface-container disabled:cursor-not-allowed">
                                <option value="" disabled selected x-text="loadingProvinces ? 'Loading…' : 'Select Province'"></option>
                                <template x-for="province in provinces" :key="province.code">
                                    <option :value="province.code" x-text="province.name"></option>
                                </template>
                            </select>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label for="municipality" class="font-label-md text-xs font-semibold text-on-surface-variant">City / Municipality</label>
                            <select
                                id="municipality" name="municipality" required
                                x-model="selectedMunicipality" @change="onMunicipalityChange()"
                                :disabled="!selectedProvince || loadingMunicipalities"
                                class="rounded-xl border border-outline-variant px-3 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary disabled:bg-surface-container disabled:cursor-not-allowed">
                                <option value="" disabled selected x-text="loadingMunicipalities ? 'Loading…' : 'Select City'"></option>
                                <template x-for="m in municipalities" :key="m.code">
                                    <option :value="m.code" x-text="m.name"></option>
                                </template>
                            </select>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label for="barangay" class="font-label-md text-xs font-semibold text-on-surface-variant">Barangay</label>
                            <select
                                id="barangay" name="barangay" required
                                x-model="selectedBarangay"
                                :disabled="!selectedMunicipality || loadingBarangays"
                                class="rounded-xl border border-outline-variant px-3 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary disabled:bg-surface-container disabled:cursor-not-allowed">
                                <option value="" disabled selected x-text="loadingBarangays ? 'Loading…' : 'Select Barangay'"></option>
                                <template x-for="b in barangays" :key="b.code">
                                    <option :value="b.code" x-text="b.name"></option>
                                </template>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1.5">
                            <label for="street" class="font-label-md text-xs font-semibold text-on-surface-variant">Street Name</label>
                            <input
                                type="text" id="street" name="street" placeholder="e.g. Ayala Avenue"
                                value="{{ old('street') }}"
                                class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label for="house_number" class="font-label-md text-xs font-semibold text-on-surface-variant">House / Unit No.</label>
                            <input
                                type="text" id="house_number" name="house_number" placeholder="e.g. Unit 14B"
                                value="{{ old('house_number') }}"
                                class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                    </div>

                    <input type="hidden" name="province_name" :value="selectedProvinceName">
                    <input type="hidden" name="municipality_name" :value="selectedMunicipalityName">
                    <input type="hidden" name="barangay_name" :value="selectedBarangayName">
                </div>

                {{-- Business Information — only for Seller / Logistics --}}
                <div x-show="role === 'seller' || role === 'sorting_center'" x-cloak class="p-5 md:p-6 border-b border-outline-variant/60 flex flex-col gap-4">
                    <div>
                        <h2 class="font-headline-sm text-sm font-bold text-on-surface">Business Information</h2>
                        <p class="text-xs text-on-surface-variant mt-0.5">Required for verified sellers and logistics hubs.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1.5">
                            <label for="business_name" class="font-label-md text-xs font-semibold text-on-surface-variant">Business Name</label>
                            <input
                                type="text" id="business_name" name="business_name" :required="role === 'seller' || role === 'sorting_center'" placeholder="e.g. Nexus Electronics"
                                value="{{ old('business_name') }}"
                                class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        <div class="flex flex-col gap-1.5" x-show="role === 'seller'">
                            <label for="category" class="font-label-md text-xs font-semibold text-on-surface-variant">Primary Product Category</label>
                            <select
                                id="category" name="category" :required="role === 'seller'"
                                class="rounded-xl border border-outline-variant px-3 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                                <option value="" disabled selected>Select a category</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat['slug'] }}">{{ $cat['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="font-label-md text-xs font-semibold text-on-surface-variant">Business / DTI Permit</label>
                        <label
                            for="business_permit"
                            class="cursor-pointer rounded-2xl border-2 border-dashed border-outline-variant hover:border-primary/60 transition-colors bg-surface-container-low/50 flex flex-col items-center justify-center gap-1.5 py-5 px-4 text-center">
                            <span class="text-xs font-semibold text-on-surface" x-text="businessPermitFileName || 'Click to select permit file'"></span>
                            <span class="text-[11px] text-on-surface-variant">Supported formats: PDF, JPG, PNG (max. 5MB)</span>
                        </label>
                        <input
                            type="file" id="business_permit" name="business_permit"
                            :required="role === 'seller' || role === 'sorting_center'"
                            accept=".jpg,.jpeg,.png,.pdf" class="hidden"
                            @change="businessPermitFileName = $event.target.files[0]?.name ?? ''">
                    </div>
                </div>

                {{-- Courier / Rider Information --}}
                <div x-show="role === 'courier'" x-cloak class="p-5 md:p-6 border-b border-outline-variant/60 flex flex-col gap-4">
                    <div>
                        <h2 class="font-headline-sm text-sm font-bold text-on-surface">Vehicle & Rider Information</h2>
                        <p class="text-xs text-on-surface-variant mt-0.5">Required for registered delivery partners and couriers.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1.5">
                            <label for="vehicle_type" class="font-label-md text-xs font-semibold text-on-surface-variant">Vehicle Type</label>
                            <select
                                id="vehicle_type" name="vehicle_type" :required="role === 'courier'"
                                class="rounded-xl border border-outline-variant px-3 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                                <option value="" disabled selected>Select Vehicle Type</option>
                                <option value="Motorcycle" {{ old('vehicle_type') === 'Motorcycle' ? 'selected' : '' }}>Motorcycle</option>
                                <option value="Scooter" {{ old('vehicle_type') === 'Scooter' ? 'selected' : '' }}>Scooter</option>
                                <option value="Bicycle" {{ old('vehicle_type') === 'Bicycle' ? 'selected' : '' }}>Bicycle</option>
                                <option value="Sedan / Car" {{ old('vehicle_type') === 'Sedan / Car' ? 'selected' : '' }}>Sedan / Car</option>
                                <option value="Van / MPV" {{ old('vehicle_type') === 'Van / MPV' ? 'selected' : '' }}>Van / MPV</option>
                                <option value="Light Truck" {{ old('vehicle_type') === 'Light Truck' ? 'selected' : '' }}>Light Truck</option>
                            </select>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label for="plate_number" class="font-label-md text-xs font-semibold text-on-surface-variant">Plate Number / Conduction Sticker</label>
                            <input
                                type="text" id="plate_number" name="plate_number" :required="role === 'courier'" placeholder="e.g. ABC 1234"
                                value="{{ old('plate_number') }}"
                                class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="font-label-md text-xs font-semibold text-on-surface-variant">Vehicle OR/CR Document</label>
                        <label
                            for="or_cr"
                            class="cursor-pointer rounded-2xl border-2 border-dashed border-outline-variant hover:border-primary/60 transition-colors bg-surface-container-low/50 flex flex-col items-center justify-center gap-1.5 py-5 px-4 text-center">
                            <span class="text-xs font-semibold text-on-surface" x-text="orCrFileName || 'Click to select OR/CR file'"></span>
                            <span class="text-[11px] text-on-surface-variant">Supported formats: PDF, JPG, PNG (max. 5MB)</span>
                        </label>
                        <input
                            type="file" id="or_cr" name="or_cr"
                            :required="role === 'courier'"
                            accept=".jpg,.jpeg,.png,.pdf" class="hidden"
                            @change="orCrFileName = $event.target.files[0]?.name ?? ''">
                    </div>
                </div>

                {{-- Identity Verification --}}
                <div class="p-5 md:p-6 border-b border-outline-variant/60 flex flex-col gap-4">
                    <div>
                        <h2 class="font-headline-sm text-sm font-bold text-on-surface">Identity Verification</h2>
                        <p class="text-xs text-on-surface-variant mt-0.5">Upload a clear photo or scan of a valid government-issued ID.</p>
                    </div>

                    <label
                        for="valid_id"
                        class="cursor-pointer rounded-2xl border-2 border-dashed border-outline-variant hover:border-primary/60 transition-colors bg-surface-container-low/50 flex flex-col items-center justify-center gap-1.5 py-6 px-4 text-center">
                        <span class="text-xs font-semibold text-on-surface" x-text="idFileName || 'Click to choose government ID'"></span>
                        <span class="text-[11px] text-on-surface-variant">Supported formats: JPG, PNG, PDF (max. 5MB)</span>
                    </label>
                    <input
                        type="file" id="valid_id" name="valid_id" required
                        accept=".jpg,.jpeg,.png,.svg,.pdf" class="hidden"
                        @change="idFileName = $event.target.files[0]?.name ?? ''">
                </div>

                {{-- Agreement & Submit --}}
                <div class="p-5 md:p-6 flex flex-col gap-4">
                    <label class="flex items-start gap-3 cursor-pointer bg-surface-container-low rounded-xl p-3.5 select-none">
                        <input
                            type="checkbox" name="terms" required
                            class="mt-0.5 w-4 h-4 rounded border-outline-variant text-primary focus:ring-primary">
                        <span class="text-xs text-on-surface-variant leading-relaxed">
                            I certify that the information provided is accurate and true. I agree to comply with Nexora Market Terms of Service and Privacy Policy.
                        </span>
                    </label>

                    <button
                        type="submit"
                        class="w-full flex items-center justify-center py-2.5 px-4 rounded-full bg-primary text-on-primary font-label-md text-xs font-semibold shadow-xs hover:bg-primary/90 transition-all mt-1 cursor-pointer">
                        Complete Registration
                    </button>

                    <p class="text-center text-xs text-on-surface-variant mt-1">
                        Already have an account?
                        <a href="{{ url('/login') }}" class="text-primary font-semibold hover:underline">Sign in</a>
                    </p>
                </div>

            </form>

            {{-- Compact Footer --}}
            <div class="mt-8 pt-6 border-t border-outline-variant flex flex-col sm:flex-row items-center justify-between gap-3 text-[11px] text-on-surface-variant">
                <span>&copy; {{ now()->year }} Nexora Market. All rights reserved.</span>
                <div class="flex items-center gap-4">
                    <a href="#" class="hover:text-primary transition-colors">Privacy Policy</a>
                    <a href="#" class="hover:text-primary transition-colors">Terms of Service</a>
                    <a href="#" class="hover:text-primary transition-colors">Help Center</a>
                </div>
            </div>

        </div>
    </div>

</div>

@push('scripts')
<script>
    function registerForm() {
        return {
            role: 'buyer',
            roleOptions: [
                { value: 'buyer', label: 'Buyer' },
                { value: 'seller', label: 'Seller' },
                { value: 'sorting_center', label: 'Logistics' },
                { value: 'courier', label: 'Rider / Courier' },
            ],

            showPassword: false,
            showPasswordConfirm: false,

            birthday: '',
            age: '',
            calculateAge() {
                if (!this.birthday) { this.age = ''; return; }
                const birthDate = new Date(this.birthday);
                const today = new Date();
                let age = today.getFullYear() - birthDate.getFullYear();
                const m = today.getMonth() - birthDate.getMonth();
                if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
                    age--;
                }
                this.age = age >= 0 ? age : '';
            },

            provinces: [],
            municipalities: [],
            barangays: [],
            selectedProvince: '',
            selectedMunicipality: '',
            selectedBarangay: '',
            loadingProvinces: false,
            loadingMunicipalities: false,
            loadingBarangays: false,

            get selectedProvinceName() {
                return this.provinces.find((p) => p.code === this.selectedProvince)?.name ?? '';
            },
            get selectedMunicipalityName() {
                return this.municipalities.find((m) => m.code === this.selectedMunicipality)?.name ?? '';
            },
            get selectedBarangayName() {
                return this.barangays.find((b) => b.code === this.selectedBarangay)?.name ?? '';
            },

            async loadProvinces() {
                this.loadingProvinces = true;
                try {
                    const res = await fetch('https://psgc.gitlab.io/api/provinces/');
                    const data = await res.json();
                    this.provinces = data.sort((a, b) => a.name.localeCompare(b.name));
                } catch (e) {
                    console.error('Failed to load provinces', e);
                } finally {
                    this.loadingProvinces = false;
                }
            },

            async onProvinceChange() {
                this.municipalities = [];
                this.barangays = [];
                this.selectedMunicipality = '';
                this.selectedBarangay = '';
                if (!this.selectedProvince) return;

                this.loadingMunicipalities = true;
                try {
                    const res = await fetch(`https://psgc.gitlab.io/api/provinces/${this.selectedProvince}/cities-municipalities/`);
                    const data = await res.json();
                    this.municipalities = data.sort((a, b) => a.name.localeCompare(b.name));
                } catch (e) {
                    console.error('Failed to load municipalities', e);
                } finally {
                    this.loadingMunicipalities = false;
                }
            },

            async onMunicipalityChange() {
                this.barangays = [];
                this.selectedBarangay = '';
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

            idFileName: '',
            businessPermitFileName: '',
            orCrFileName: '',
        };
    }
</script>
@endpush

@endsection
