@extends('layouts.app')

@section('title', 'My Account — Nexora Market')

@section('content')
<div x-data="accountAddress()" x-init="loadProvinces()" class="w-full bg-surface py-6 md:py-8 min-h-[75vh]">
    <div class="max-w-none px-4 md:px-8">

        {{-- Sub-Navigation --}}
        <x-buyer-nav active="account" />

        <div class="mb-6">
            <h1 class="font-headline-lg text-2xl md:text-3xl font-bold tracking-tight text-on-surface">My Account</h1>
            <p class="font-body-md text-xs md:text-sm text-on-surface-variant mt-1">Manage your personal information, delivery addresses, and account security.</p>
        </div>

        @if (session('status'))
            <div class="mb-6 bg-primary/10 border border-primary/20 rounded-2xl p-4 flex items-center justify-between">
                <p class="font-body-sm text-xs md:text-sm font-medium text-primary">{{ session('status') }}</p>
            </div>
        @endif

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

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start max-w-5xl">

            {{-- Profile Information Card --}}
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-5 md:p-6 shadow-xs flex flex-col gap-5">
                <div class="border-b border-outline-variant/60 pb-3">
                    <h2 class="font-headline-sm text-sm font-bold text-on-surface">Personal Information</h2>
                    <p class="text-xs text-on-surface-variant mt-0.5">Update your contact information and identity details.</p>
                </div>

                <form method="POST" action="{{ route('buyer.account.profile') }}" class="flex flex-col gap-4">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1.5">
                            <label class="font-label-md text-xs font-semibold text-on-surface-variant">First Name</label>
                            <input
                                type="text"
                                name="first_name"
                                required
                                value="{{ old('first_name', $user->first_name) }}"
                                class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="font-label-md text-xs font-semibold text-on-surface-variant">Last Name</label>
                            <input
                                type="text"
                                name="last_name"
                                required
                                value="{{ old('last_name', $user->last_name) }}"
                                class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1.5">
                            <label class="font-label-md text-xs font-semibold text-on-surface-variant">Middle Initial</label>
                            <input
                                type="text"
                                name="middle_initial"
                                maxlength="10"
                                value="{{ old('middle_initial', $user->middle_initial) }}"
                                class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="font-label-md text-xs font-semibold text-on-surface-variant">Sex</label>
                            <select
                                name="sex"
                                required
                                class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                                <option value="male" {{ old('sex', $user->sex) === 'male' ? 'selected' : '' }}>Male</option>
                                <option value="female" {{ old('sex', $user->sex) === 'female' ? 'selected' : '' }}>Female</option>
                                <option value="other" {{ old('sex', $user->sex) === 'other' ? 'selected' : '' }}>Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1.5">
                            <label class="font-label-md text-xs font-semibold text-on-surface-variant">Birthday</label>
                            <input
                                type="date"
                                name="birthday"
                                required
                                value="{{ old('birthday', $user->birthday->toDateString()) }}"
                                class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="font-label-md text-xs font-semibold text-on-surface-variant">Contact Number</label>
                            <input
                                type="tel"
                                name="contact_number"
                                required
                                value="{{ old('contact_number', $user->contact_number) }}"
                                class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="font-label-md text-xs font-semibold text-on-surface-variant">Email Address</label>
                        <input
                            type="email"
                            value="{{ $user->email }}"
                            disabled
                            class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface-variant bg-surface-container cursor-not-allowed">
                        <p class="text-[11px] text-on-surface-variant">Email addresses are tied to your authentication credentials and cannot be changed directly.</p>
                    </div>

                    <div class="pt-2">
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center bg-primary text-on-primary font-label-md text-xs font-semibold px-6 py-2.5 rounded-full shadow-xs hover:bg-primary/90 transition-colors">
                            Save Profile Changes
                        </button>
                    </div>
                </form>
            </div>

            {{-- Right Column: Address and Password --}}
            <div class="flex flex-col gap-6">

                {{-- Delivery Address Card --}}
                <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-5 md:p-6 shadow-xs flex flex-col gap-5">
                    <div class="border-b border-outline-variant/60 pb-3">
                        <h2 class="font-headline-sm text-sm font-bold text-on-surface">Default Shipping Address</h2>
                        <p class="text-xs text-on-surface-variant mt-0.5">Used for standard checkout and doorstep parcel delivery.</p>
                    </div>

                    @if($user->address)
                        <div class="p-3 bg-surface-container rounded-xl text-xs text-on-surface-variant leading-relaxed">
                            <span class="font-semibold text-on-surface">Current address on file:</span>
                            <p class="text-on-surface mt-0.5 font-medium">{{ $user->address->full_address }}</p>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('buyer.account.address') }}" class="flex flex-col gap-4">
                        @csrf
                        @method('PUT')

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div class="flex flex-col gap-1.5">
                                <label class="font-label-md text-xs font-semibold text-on-surface-variant">Province</label>
                                <select
                                    name="province_code"
                                    required
                                    x-model="selectedProvince"
                                    @change="onProvinceChange()"
                                    class="rounded-xl border border-outline-variant px-3 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                                    <option value="" disabled selected x-text="loadingProvinces ? 'Loading…' : 'Select Province'"></option>
                                    <template x-for="p in provinces" :key="p.code">
                                        <option :value="p.code" x-text="p.name"></option>
                                    </template>
                                </select>
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label class="font-label-md text-xs font-semibold text-on-surface-variant">City / Municipality</label>
                                <select
                                    name="municipality_code"
                                    required
                                    x-model="selectedMunicipality"
                                    @change="onMunicipalityChange()"
                                    :disabled="!selectedProvince || loadingMunicipalities"
                                    class="rounded-xl border border-outline-variant px-3 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary disabled:bg-surface-container disabled:cursor-not-allowed">
                                    <option value="" disabled selected x-text="loadingMunicipalities ? 'Loading…' : 'Select City'"></option>
                                    <template x-for="m in municipalities" :key="m.code">
                                        <option :value="m.code" x-text="m.name"></option>
                                    </template>
                                </select>
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label class="font-label-md text-xs font-semibold text-on-surface-variant">Barangay</label>
                                <select
                                    name="barangay_code"
                                    required
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

                        <input type="hidden" name="province_name" :value="selectedProvinceName">
                        <input type="hidden" name="municipality_name" :value="selectedMunicipalityName">
                        <input type="hidden" name="barangay_name" :value="selectedBarangayName">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="flex flex-col gap-1.5">
                                <label class="font-label-md text-xs font-semibold text-on-surface-variant">Street Name</label>
                                <input
                                    type="text"
                                    name="street"
                                    value="{{ old('street', $user->address->street ?? '') }}"
                                    placeholder="e.g. Rizal Avenue"
                                    class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label class="font-label-md text-xs font-semibold text-on-surface-variant">House / Unit No.</label>
                                <input
                                    type="text"
                                    name="house_number"
                                    value="{{ old('house_number', $user->address->house_number ?? '') }}"
                                    placeholder="e.g. Unit 4B"
                                    class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                            </div>
                        </div>

                        <div class="pt-2">
                            <button
                                type="submit"
                                class="inline-flex items-center justify-center bg-primary text-on-primary font-label-md text-xs font-semibold px-6 py-2.5 rounded-full shadow-xs hover:bg-primary/90 transition-colors">
                                Save Address
                            </button>
                        </div>
                    </form>
                </div>

                {{-- Change Password Card --}}
                <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-5 md:p-6 shadow-xs flex flex-col gap-5">
                    <div class="border-b border-outline-variant/60 pb-3">
                        <h2 class="font-headline-sm text-sm font-bold text-on-surface">Security & Password</h2>
                        <p class="text-xs text-on-surface-variant mt-0.5">Ensure your account is protected by using a strong password.</p>
                    </div>

                    <form method="POST" action="{{ route('buyer.account.password') }}" class="flex flex-col gap-4">
                        @csrf
                        @method('PUT')

                        <div class="flex flex-col gap-1.5">
                            <label class="font-label-md text-xs font-semibold text-on-surface-variant">Current Password</label>
                            <input
                                type="password"
                                name="current_password"
                                required
                                placeholder="••••••••"
                                class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="font-label-md text-xs font-semibold text-on-surface-variant">New Password</label>
                            <input
                                type="password"
                                name="password"
                                required
                                minlength="8"
                                placeholder="At least 8 characters"
                                class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="font-label-md text-xs font-semibold text-on-surface-variant">Confirm New Password</label>
                            <input
                                type="password"
                                name="password_confirmation"
                                required
                                placeholder="Re-enter your new password"
                                class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>

                        <div class="pt-2">
                            <button
                                type="submit"
                                class="inline-flex items-center justify-center bg-primary text-on-primary font-label-md text-xs font-semibold px-6 py-2.5 rounded-full shadow-xs hover:bg-primary/90 transition-colors">
                                Update Password
                            </button>
                        </div>
                    </form>
                </div>

            </div>

        </div>
    </div>
</div>

@push('scripts')
<script>
    function accountAddress() {
        return {
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
                    this.provinces = (await res.json()).sort((a, b) => a.name.localeCompare(b.name));
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
                    this.municipalities = (await res.json()).sort((a, b) => a.name.localeCompare(b.name));
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
                    this.barangays = (await res.json()).sort((a, b) => a.name.localeCompare(b.name));
                } catch (e) {
                    console.error('Failed to load barangays', e);
                } finally {
                    this.loadingBarangays = false;
                }
            },
        };
    }
</script>
@endpush

@endsection
