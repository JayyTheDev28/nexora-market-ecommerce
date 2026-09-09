@extends('layouts.app')

@section('title', 'Create Your Account — Nexora Market')

@section('content')
<div
    x-data="registerForm()"
    x-init="loadProvinces()"
    class="w-full flex flex-col md:flex-row h-[calc(100vh-4rem)] bg-surface-container-lowest">

    <x-brand-panel caption="Join thousands of shoppers discovering something new every day." />

    {{-- RIGHT — scrollable form column --}}
    <div class="flex-1 h-full overflow-y-auto">
        <div class="max-w-2xl mx-auto px-6 md:px-10 py-6 md:py-8">

            <h1 class="font-display-lg text-headline-lg md:text-3xl text-on-surface">Create Your Account</h1>
            <p class="font-body-md text-body-md text-on-surface-variant mt-2">
                Join Nexora and unlock access to a world of premium curated goods.
            </p>

            {{-- This form has no action yet — frontend only, per the current build phase.
                 Validation, submission handling, and the database come in a later phase.
                 Submit currently just opens the pending-approval modal below. --}}
            <form class="flex flex-col mt-8 bg-surface-container-lowest rounded-3xl border border-outline-variant shadow-sm overflow-hidden" @submit.prevent="submitForm()">

                {{-- Personal Information --}}
                <div class="p-5 md:p-7 border-b border-outline-variant border-l-4 border-l-primary flex flex-col gap-6">
                    <h2 class="flex items-center gap-2 font-headline-sm text-headline-sm text-on-surface">
                        <span class="material-symbols-outlined text-primary text-[22px]">person</span>
                        Personal Information
                    </h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="flex flex-col gap-2">
                            <label for="first_name" class="font-label-md text-label-md text-on-surface-variant">First Name</label>
                            <input type="text" id="first_name" name="first_name" required placeholder="e.g. Jane"
                                class="rounded-xl border border-outline-variant px-3.5 py-2.5 font-body-md text-body-md text-on-surface bg-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        <div class="flex flex-col gap-2">
                            <label for="last_name" class="font-label-md text-label-md text-on-surface-variant">Last Name</label>
                            <input type="text" id="last_name" name="last_name" required placeholder="e.g. Doe"
                                class="rounded-xl border border-outline-variant px-3.5 py-2.5 font-body-md text-body-md text-on-surface bg-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                        <div class="flex flex-col gap-2">
                            <label for="middle_initial" class="font-label-md text-label-md text-on-surface-variant">Middle Initial</label>
                            <input type="text" id="middle_initial" name="middle_initial" maxlength="5" placeholder="M.I."
                                class="rounded-xl border border-outline-variant px-3.5 py-2.5 font-body-md text-body-md text-on-surface bg-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        <div class="flex flex-col gap-2">
                            <label for="sex" class="font-label-md text-label-md text-on-surface-variant">Sex</label>
                            <select id="sex" name="sex" required
                                class="rounded-xl border border-outline-variant px-3.5 py-2.5 font-body-md text-body-md text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                                <option value="" disabled selected>Select</option>
                                <option value="female">Female</option>
                                <option value="male">Male</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="flex flex-col gap-2">
                            <label for="birthday" class="font-label-md text-label-md text-on-surface-variant">Birthday</label>
                            <input type="date" id="birthday" name="birthday" required
                                x-model="birthday" @change="calculateAge()"
                                class="rounded-xl border border-outline-variant px-3.5 py-2.5 font-body-md text-body-md text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        <div class="flex flex-col gap-2">
                            <label for="age" class="font-label-md text-label-md text-on-surface-variant">Age</label>
                            <input type="text" id="age" name="age" readonly disabled
                                x-model="age" placeholder="--"
                                class="rounded-xl border border-outline-variant px-3.5 py-2.5 font-body-md text-body-md text-on-surface-variant bg-surface-container cursor-not-allowed">
                        </div>
                    </div>
                </div>

                {{-- Contact Details --}}
                <div class="p-5 md:p-7 border-b border-outline-variant border-l-4 border-l-primary flex flex-col gap-6">
                    <h2 class="flex items-center gap-2 font-headline-sm text-headline-sm text-on-surface">
                        <span class="material-symbols-outlined text-primary text-[22px]">mail</span>
                        Contact Details
                    </h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="flex flex-col gap-2">
                            <label for="email" class="font-label-md text-label-md text-on-surface-variant">Email Address</label>
                            <div class="relative">
                                <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline text-[20px]">mail</span>
                                <input type="email" id="email" name="email" required placeholder="jane.doe@example.com"
                                    class="w-full rounded-xl border border-outline-variant pl-11 pr-4 py-3 font-body-md text-body-md text-on-surface bg-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary">
                            </div>
                        </div>
                        <div class="flex flex-col gap-2">
                            <label for="contact_number" class="font-label-md text-label-md text-on-surface-variant">Contact Number</label>
                            <div class="relative">
                                <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline text-[20px]">call</span>
                                <input type="tel" id="contact_number" name="contact_number" required placeholder="+63 9XX XXX XXXX"
                                    class="w-full rounded-xl border border-outline-variant pl-11 pr-4 py-3 font-body-md text-body-md text-on-surface bg-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Residential Address --}}
                <div class="p-5 md:p-7 border-b border-outline-variant border-l-4 border-l-primary flex flex-col gap-6">
                    <h2 class="flex items-center gap-2 font-headline-sm text-headline-sm text-on-surface">
                        <span class="material-symbols-outlined text-primary text-[22px]">location_on</span>
                        Residential Address
                    </h2>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="flex flex-col gap-2">
                            <label for="province" class="font-label-md text-label-md text-on-surface-variant">Province / State</label>
                            <select id="province" name="province" required
                                x-model="selectedProvince" @change="onProvinceChange()"
                                class="rounded-xl border border-outline-variant px-3.5 py-2.5 font-body-md text-body-md text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary disabled:bg-surface-container disabled:cursor-not-allowed">
                                <option value="" disabled selected x-text="loadingProvinces ? 'Loading…' : 'Select Province'"></option>
                                <template x-for="province in provinces" :key="province.code">
                                    <option :value="province.code" x-text="province.name"></option>
                                </template>
                            </select>
                        </div>

                        <div class="flex flex-col gap-2">
                            <label for="municipality" class="font-label-md text-label-md text-on-surface-variant">City / Municipality</label>
                            <select id="municipality" name="municipality" required
                                x-model="selectedMunicipality" @change="onMunicipalityChange()"
                                :disabled="!selectedProvince || loadingMunicipalities"
                                class="rounded-xl border border-outline-variant px-3.5 py-2.5 font-body-md text-body-md text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary disabled:bg-surface-container disabled:cursor-not-allowed">
                                <option value="" disabled selected x-text="loadingMunicipalities ? 'Loading…' : 'Select City'"></option>
                                <template x-for="m in municipalities" :key="m.code">
                                    <option :value="m.code" x-text="m.name"></option>
                                </template>
                            </select>
                        </div>

                        <div class="flex flex-col gap-2">
                            <label for="barangay" class="font-label-md text-label-md text-on-surface-variant">Barangay / District</label>
                            <select id="barangay" name="barangay" required
                                x-model="selectedBarangay"
                                :disabled="!selectedMunicipality || loadingBarangays"
                                class="rounded-xl border border-outline-variant px-3.5 py-2.5 font-body-md text-body-md text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary disabled:bg-surface-container disabled:cursor-not-allowed">
                                <option value="" disabled selected x-text="loadingBarangays ? 'Loading…' : 'Select District'"></option>
                                <template x-for="b in barangays" :key="b.code">
                                    <option :value="b.code" x-text="b.name"></option>
                                </template>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="flex flex-col gap-2">
                            <label for="street" class="font-label-md text-label-md text-on-surface-variant">Street Name</label>
                            <input type="text" id="street" name="street" placeholder="e.g. Ayala Avenue"
                                class="rounded-xl border border-outline-variant px-3.5 py-2.5 font-body-md text-body-md text-on-surface bg-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        <div class="flex flex-col gap-2">
                            <label for="house_number" class="font-label-md text-label-md text-on-surface-variant">House / Unit / Bldg Number</label>
                            <input type="text" id="house_number" name="house_number" placeholder="e.g. Unit 14B"
                                class="rounded-xl border border-outline-variant px-3.5 py-2.5 font-body-md text-body-md text-on-surface bg-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                    </div>
                </div>

                {{-- Identity Verification --}}
                <div class="p-5 md:p-7 border-b border-outline-variant border-l-4 border-l-primary flex flex-col gap-6">
                    <h2 class="flex items-center gap-2 font-headline-sm text-headline-sm text-on-surface">
                        <span class="material-symbols-outlined text-primary text-[22px]">badge</span>
                        Identity Verification
                    </h2>
                    <p class="font-body-sm text-body-sm text-on-surface-variant -mt-4">
                        To ensure a secure marketplace, please upload a clear photo of a valid government-issued ID.
                    </p>

                    <label
                        for="valid_id"
                        class="cursor-pointer rounded-2xl border-2 border-dashed border-outline-variant hover:border-primary transition-colors bg-surface-container-low flex flex-col items-center justify-center gap-3 py-8 px-5 text-center">
                        <span class="w-11 h-11 rounded-full bg-primary/10 flex items-center justify-center">
                            <span class="material-symbols-outlined text-[20px] text-primary">cloud_upload</span>
                        </span>
                        <span class="font-label-md text-label-md text-on-surface" x-text="idFileName || 'Click to upload or drag and drop'"></span>
                        <span class="font-body-sm text-body-sm text-on-surface-variant">SVG, PNG, JPG or PDF (max. 5MB)</span>
                    </label>
                    <input
                        type="file" id="valid_id" name="valid_id" required
                        accept=".jpg,.jpeg,.png,.svg,.pdf" class="hidden"
                        @change="idFileName = $event.target.files[0]?.name ?? ''">
                </div>

                {{-- Submission --}}
                <div class="p-5 md:p-7 flex flex-col gap-5">
                    <label class="flex items-start gap-3 cursor-pointer bg-surface-container-low rounded-xl p-4">
                        <input type="checkbox" required
                            class="mt-1 w-5 h-5 rounded border-outline-variant text-primary focus:ring-primary">
                        <span class="font-body-sm text-body-sm text-on-surface-variant">
                            I hereby certify that the information provided above is true, accurate, and complete. I understand that any false statements may result in the termination of my Nexora account.
                        </span>
                    </label>

                    <button
                        type="submit"
                        class="w-full flex items-center justify-center gap-2 bg-primary text-on-primary font-label-md text-label-md px-6 py-3 rounded-full shadow-lg shadow-primary/20 hover:bg-primary/90 hover:scale-[1.01] transition-all">
                        Create Nexora Account
                        <span class="material-symbols-outlined text-[20px]">arrow_forward</span>
                    </button>

                    <p class="text-center font-body-sm text-body-sm text-on-surface-variant">
                        Already have an account?
                        <a href="{{ url('/login') }}" class="text-primary font-label-md hover:underline">Sign in</a>
                    </p>
                </div>

            </form>

            {{-- Compact footer — lives inside the scrollable column, not the full site footer,
                 since this page suppresses <x-footer /> via hideFooter. --}}
            <div class="mt-10 pt-6 border-t border-outline-variant flex flex-col sm:flex-row items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <span class="w-5 h-5 rounded-full bg-primary flex items-center justify-center flex-shrink-0">
                        <svg viewBox="0 0 24 24" width="11" height="11" fill="none"><path d="M6 8h12l-1 12a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L6 8z" fill="#ffffff"/></svg>
                    </span>
                    <span class="font-body-sm text-body-sm text-on-surface-variant">Nexora Market &copy; {{ now()->year }} Built for Excellence.</span>
                </div>
                <div class="flex items-center gap-4 font-body-sm text-body-sm text-on-surface-variant">
                    <a href="#" class="hover:text-primary">Privacy Policy</a>
                    <a href="#" class="hover:text-primary">Terms of Service</a>
                    <a href="#" class="hover:text-primary">Help Center</a>
                </div>
            </div>

        </div>
    </div>

    {{-- Pending-approval modal — shown on submit instead of navigating anywhere.
         No backend call happens yet; this is purely the frontend confirmation state. --}}
    <div
        x-show="showPendingModal"
        x-cloak
        x-transition.opacity
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-inverse-surface/60 backdrop-blur-sm"
        @keydown.escape.window="showPendingModal = false">
        <div
            x-show="showPendingModal"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            @click.outside="showPendingModal = false"
            class="relative max-w-md w-full bg-surface-container-lowest rounded-3xl shadow-2xl p-10 text-center flex flex-col items-center gap-5">

            <button
                type="button"
                @click="showPendingModal = false"
                class="absolute top-4 right-4 w-9 h-9 rounded-full flex items-center justify-center text-on-surface-variant hover:bg-surface-container hover:text-on-surface transition-colors"
                aria-label="Close">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>

            <div class="w-16 h-16 rounded-full bg-primary/10 flex items-center justify-center">
                <span class="material-symbols-outlined text-[30px] text-primary" style="font-variation-settings: 'FILL' 1">check_circle</span>
            </div>

            <h2 class="font-headline-lg text-headline-lg text-on-surface">Application Submitted</h2>

            <p class="font-body-md text-body-md text-on-surface-variant">
                Your registration has been submitted successfully.
            </p>
            <p class="font-body-md text-body-md text-on-surface-variant">
                Please wait while an administrator reviews your application.
            </p>
            <p class="font-body-md text-body-md text-on-surface-variant">
                You will receive an email regarding the result of your application.
            </p>

            <a
                href="{{ url('/') }}"
                class="mt-2 w-full inline-flex items-center justify-center gap-2 bg-primary text-on-primary font-label-md text-label-md px-6 py-3 rounded-full shadow-lg shadow-primary/20 hover:bg-primary/90 transition-all">
                Back to Home
            </a>
        </div>
    </div>

</div>

@push('scripts')
<script>
    function registerForm() {
        return {
            // Birthday → age
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

            // Address — PSGC public API (province → municipality/city → barangay)
            provinces: [],
            municipalities: [],
            barangays: [],
            selectedProvince: '',
            selectedMunicipality: '',
            selectedBarangay: '',
            loadingProvinces: false,
            loadingMunicipalities: false,
            loadingBarangays: false,

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

            // ID upload — filename display only, no actual upload yet
            idFileName: '',

            // Submission — no backend yet, just show the confirmation modal
            showPendingModal: false,
            submitForm() {
                this.showPendingModal = true;
            },
        };
    }
</script>
@endpush

@endsection
