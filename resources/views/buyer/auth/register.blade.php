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

            @if ($errors->any())
                <div class="mt-6 bg-error-container border border-error/30 rounded-2xl p-4 flex flex-col gap-1">
                    <p class="font-label-md text-label-md text-on-error-container">Please fix the following:</p>
                    <ul class="list-disc list-inside font-body-sm text-body-sm text-on-error-container">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form
                class="flex flex-col mt-8 bg-surface-container-lowest rounded-3xl border border-outline-variant shadow-sm overflow-hidden"
                action="{{ route('register.store') }}" method="POST" enctype="multipart/form-data">
                @csrf

                {{-- Account Type --}}
                <div class="p-5 md:p-7 border-b border-outline-variant border-l-4 border-l-primary flex flex-col gap-4">
                    <h2 class="flex items-center gap-2 font-headline-sm text-headline-sm text-on-surface">
                        <span class="material-symbols-outlined text-primary text-[22px]">switch_account</span>
                        Register As
                    </h2>
                    <div class="grid grid-cols-3 gap-3">
                        <template x-for="option in roleOptions" :key="option.value">
                            <label
                                class="flex flex-col items-center gap-2 p-4 rounded-xl border-2 cursor-pointer transition-colors text-center"
                                :class="role === option.value ? 'border-primary bg-primary/5' : 'border-outline-variant hover:border-outline'">
                                <input type="radio" name="role" :value="option.value" x-model="role" class="sr-only">
                                <span class="material-symbols-outlined text-[22px]" :class="role === option.value ? 'text-primary' : 'text-on-surface-variant'" x-text="option.icon"></span>
                                <span class="font-label-md text-label-md text-on-surface" x-text="option.label"></span>
                            </label>
                        </template>
                    </div>
                    <p class="font-body-sm text-body-sm text-on-surface-variant">
                        This determines which dashboard you'll get access to once your application is approved.
                    </p>
                </div>

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

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="flex flex-col gap-2">
                            <label for="password" class="font-label-md text-label-md text-on-surface-variant">Password</label>
                            <div class="relative">
                                <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline text-[20px]">lock</span>
                                <input :type="showPassword ? 'text' : 'password'" id="password" name="password" required minlength="8" placeholder="At least 8 characters"
                                    class="w-full rounded-xl border border-outline-variant pl-11 pr-11 py-3 font-body-md text-body-md text-on-surface bg-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary">
                                <button type="button" @click="showPassword = !showPassword" class="absolute right-4 top-1/2 -translate-y-1/2 text-outline hover:text-on-surface-variant" aria-label="Toggle password visibility">
                                    <span class="material-symbols-outlined text-[20px]" x-text="showPassword ? 'visibility_off' : 'visibility'"></span>
                                </button>
                            </div>
                        </div>
                        <div class="flex flex-col gap-2">
                            <label for="password_confirmation" class="font-label-md text-label-md text-on-surface-variant">Confirm Password</label>
                            <div class="relative">
                                <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline text-[20px]">lock</span>
                                <input :type="showPasswordConfirm ? 'text' : 'password'" id="password_confirmation" name="password_confirmation" required placeholder="Re-enter your password"
                                    class="w-full rounded-xl border border-outline-variant pl-11 pr-11 py-3 font-body-md text-body-md text-on-surface bg-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary">
                                <button type="button" @click="showPasswordConfirm = !showPasswordConfirm" class="absolute right-4 top-1/2 -translate-y-1/2 text-outline hover:text-on-surface-variant" aria-label="Toggle password visibility">
                                    <span class="material-symbols-outlined text-[20px]" x-text="showPasswordConfirm ? 'visibility_off' : 'visibility'"></span>
                                </button>
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

                    {{-- Selects above carry the PSGC code as their value (needed to fetch children);
                         these hidden fields carry the resolved display name alongside it. --}}
                    <input type="hidden" name="province_name" :value="selectedProvinceName">
                    <input type="hidden" name="municipality_name" :value="selectedMunicipalityName">
                    <input type="hidden" name="barangay_name" :value="selectedBarangayName">
                </div>

                {{-- Business Information — only for Seller / Logistics --}}
                <div x-show="role === 'seller' || role === 'sorting_center'" x-cloak class="p-5 md:p-7 border-b border-outline-variant border-l-4 border-l-primary flex flex-col gap-6">
                    <h2 class="flex items-center gap-2 font-headline-sm text-headline-sm text-on-surface">
                        <span class="material-symbols-outlined text-primary text-[22px]">store</span>
                        Business Information
                    </h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="flex flex-col gap-2">
                            <label for="business_name" class="font-label-md text-label-md text-on-surface-variant">Business Name</label>
                            <input type="text" id="business_name" name="business_name" :required="role === 'seller' || role === 'sorting_center'" placeholder="e.g. Nexus Home"
                                class="rounded-xl border border-outline-variant px-3.5 py-2.5 font-body-md text-body-md text-on-surface bg-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        <div class="flex flex-col gap-2" x-show="role === 'seller'">
                            <label for="category" class="font-label-md text-label-md text-on-surface-variant">Line of Business</label>
                            <select id="category" name="category" :required="role === 'seller'"
                                class="rounded-xl border border-outline-variant px-3.5 py-2.5 font-body-md text-body-md text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                                <option value="" disabled selected>Select a category</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat['slug'] }}">{{ $cat['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="flex flex-col gap-2">
                        <label class="font-label-md text-label-md text-on-surface-variant">Upload Business / DTI Permit</label>
                        <label
                            for="business_permit"
                            class="cursor-pointer rounded-2xl border-2 border-dashed border-outline-variant hover:border-primary transition-colors bg-surface-container-low flex flex-col items-center justify-center gap-2 py-6 px-5 text-center">
                            <span class="material-symbols-outlined text-[22px] text-primary">upload_file</span>
                            <span class="font-label-md text-label-md text-on-surface" x-text="businessPermitFileName || 'Click to upload or drag and drop'"></span>
                            <span class="font-body-sm text-body-sm text-on-surface-variant">PDF, JPG, or PNG (max. 5MB)</span>
                        </label>
                        <input
                            type="file" id="business_permit" name="business_permit"
                            :required="role === 'seller' || role === 'sorting_center'"
                            accept=".jpg,.jpeg,.png,.pdf" class="hidden"
                            @change="businessPermitFileName = $event.target.files[0]?.name ?? ''">
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
                        <input type="checkbox" name="terms" required
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

</div>

@push('scripts')
<script>
    function registerForm() {
        return {
            // Account type
            role: 'buyer',
            roleOptions: [
                { value: 'buyer', label: 'Buyer', icon: 'shopping_bag' },
                { value: 'seller', label: 'Seller', icon: 'storefront' },
                { value: 'sorting_center', label: 'Logistics', icon: 'local_shipping' },
            ],

            // Password visibility
            showPassword: false,
            showPasswordConfirm: false,

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

            // File inputs — filename display only
            idFileName: '',
            businessPermitFileName: '',
        };
    }
</script>
@endpush

@endsection
