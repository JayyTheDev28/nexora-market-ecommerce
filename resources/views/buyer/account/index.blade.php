@extends('layouts.app')

@section('title', 'My Account — Nexora Market')

@section('content')
<div x-data="accountAddress()" x-init="loadProvinces()" class="w-full bg-surface py-8 min-h-[70vh]">
    <div class="max-w-none px-margin-mobile md:px-margin-desktop">

        <x-buyer-nav active="account" />

        <h1 class="font-headline-lg text-headline-lg text-on-surface mb-6">My Account</h1>

        @if (session('status'))
            <div class="mb-6 bg-primary/10 border border-primary/30 rounded-2xl p-4">
                <p class="font-body-sm text-body-sm text-primary">{{ session('status') }}</p>
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 bg-error-container border border-error/30 rounded-2xl p-4">
                <p class="font-label-md text-label-md text-on-error-container">Please fix the following:</p>
                <ul class="list-disc list-inside font-body-sm text-body-sm text-on-error-container">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 max-w-4xl">

            {{-- Profile Info --}}
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-6 flex flex-col gap-5">
                <h2 class="flex items-center gap-2 font-headline-sm text-headline-sm text-on-surface">
                    <span class="material-symbols-outlined text-primary text-[20px]">person</span>
                    Profile Information
                </h2>

                <form method="POST" action="{{ route('buyer.account.profile') }}" class="flex flex-col gap-4">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="flex flex-col gap-2">
                            <label class="font-label-md text-label-md text-on-surface-variant">First Name</label>
                            <input type="text" name="first_name" required value="{{ old('first_name', $user->first_name) }}"
                                class="rounded-xl border border-outline-variant px-3.5 py-2.5 font-body-md text-body-md text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="font-label-md text-label-md text-on-surface-variant">Last Name</label>
                            <input type="text" name="last_name" required value="{{ old('last_name', $user->last_name) }}"
                                class="rounded-xl border border-outline-variant px-3.5 py-2.5 font-body-md text-body-md text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col gap-2">
                            <label class="font-label-md text-label-md text-on-surface-variant">Middle Initial</label>
                            <input type="text" name="middle_initial" maxlength="10" value="{{ old('middle_initial', $user->middle_initial) }}"
                                class="rounded-xl border border-outline-variant px-3.5 py-2.5 font-body-md text-body-md text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="font-label-md text-label-md text-on-surface-variant">Sex</label>
                            <select name="sex" required class="rounded-xl border border-outline-variant px-3.5 py-2.5 font-body-md text-body-md text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                                <option value="male" {{ old('sex', $user->sex) === 'male' ? 'selected' : '' }}>Male</option>
                                <option value="female" {{ old('sex', $user->sex) === 'female' ? 'selected' : '' }}>Female</option>
                                <option value="other" {{ old('sex', $user->sex) === 'other' ? 'selected' : '' }}>Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex flex-col gap-2">
                        <label class="font-label-md text-label-md text-on-surface-variant">Birthday</label>
                        <input type="date" name="birthday" required value="{{ old('birthday', $user->birthday->toDateString()) }}"
                            class="rounded-xl border border-outline-variant px-3.5 py-2.5 font-body-md text-body-md text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>

                    <div class="flex flex-col gap-2">
                        <label class="font-label-md text-label-md text-on-surface-variant">Contact Number</label>
                        <input type="tel" name="contact_number" required value="{{ old('contact_number', $user->contact_number) }}"
                            class="rounded-xl border border-outline-variant px-3.5 py-2.5 font-body-md text-body-md text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>

                    <div class="flex flex-col gap-2">
                        <label class="font-label-md text-label-md text-on-surface-variant">Email</label>
                        <input type="email" value="{{ $user->email }}" disabled
                            class="rounded-xl border border-outline-variant px-3.5 py-2.5 font-body-md text-body-md text-on-surface-variant bg-surface-container cursor-not-allowed">
                        <p class="font-body-sm text-body-sm text-on-surface-variant">Email changes aren't supported yet.</p>
                    </div>

                    <button type="submit" class="self-start bg-primary text-on-primary font-label-md text-label-md px-6 py-2.5 rounded-full hover:bg-primary/90 transition-colors">
                        Save Profile
                    </button>
                </form>
            </div>

            {{-- Address + Password --}}
            <div class="flex flex-col gap-6">

                <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-6 flex flex-col gap-5">
                    <h2 class="flex items-center gap-2 font-headline-sm text-headline-sm text-on-surface">
                        <span class="material-symbols-outlined text-primary text-[20px]">location_on</span>
                        Address
                    </h2>

                    @if($user->address)
                        <div class="bg-surface-container-low rounded-xl p-3 font-body-sm text-body-sm text-on-surface-variant">
                            Currently on file: <span class="text-on-surface">{{ $user->address->full_address }}</span>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('buyer.account.address') }}" class="flex flex-col gap-4">
                        @csrf
                        @method('PUT')

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div class="flex flex-col gap-2">
                                <label class="font-label-md text-label-md text-on-surface-variant">Province</label>
                                <select name="province_code" required x-model="selectedProvince" @change="onProvinceChange()"
                                    class="rounded-xl border border-outline-variant px-3 py-2.5 font-body-sm text-body-sm text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                                    <option value="" disabled selected x-text="loadingProvinces ? 'Loading…' : 'Select'"></option>
                                    <template x-for="p in provinces" :key="p.code">
                                        <option :value="p.code" x-text="p.name"></option>
                                    </template>
                                </select>
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="font-label-md text-label-md text-on-surface-variant">City</label>
                                <select name="municipality_code" required x-model="selectedMunicipality" @change="onMunicipalityChange()" :disabled="!selectedProvince"
                                    class="rounded-xl border border-outline-variant px-3 py-2.5 font-body-sm text-body-sm text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary disabled:bg-surface-container">
                                    <option value="" disabled selected x-text="loadingMunicipalities ? 'Loading…' : 'Select'"></option>
                                    <template x-for="m in municipalities" :key="m.code">
                                        <option :value="m.code" x-text="m.name"></option>
                                    </template>
                                </select>
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="font-label-md text-label-md text-on-surface-variant">Barangay</label>
                                <select name="barangay_code" required x-model="selectedBarangay" :disabled="!selectedMunicipality"
                                    class="rounded-xl border border-outline-variant px-3 py-2.5 font-body-sm text-body-sm text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary disabled:bg-surface-container">
                                    <option value="" disabled selected x-text="loadingBarangays ? 'Loading…' : 'Select'"></option>
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
                            <div class="flex flex-col gap-2">
                                <label class="font-label-md text-label-md text-on-surface-variant">Street</label>
                                <input type="text" name="street" value="{{ old('street', $user->address->street ?? '') }}"
                                    class="rounded-xl border border-outline-variant px-3.5 py-2.5 font-body-sm text-body-sm text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="font-label-md text-label-md text-on-surface-variant">House / Unit No.</label>
                                <input type="text" name="house_number" value="{{ old('house_number', $user->address->house_number ?? '') }}"
                                    class="rounded-xl border border-outline-variant px-3.5 py-2.5 font-body-sm text-body-sm text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                            </div>
                        </div>

                        <button type="submit" class="self-start bg-primary text-on-primary font-label-md text-label-md px-6 py-2.5 rounded-full hover:bg-primary/90 transition-colors">
                            Save Address
                        </button>
                    </form>
                </div>

                <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-6 flex flex-col gap-5">
                    <h2 class="flex items-center gap-2 font-headline-sm text-headline-sm text-on-surface">
                        <span class="material-symbols-outlined text-primary text-[20px]">lock</span>
                        Change Password
                    </h2>

                    <form method="POST" action="{{ route('buyer.account.password') }}" class="flex flex-col gap-4">
                        @csrf
                        @method('PUT')

                        <div class="flex flex-col gap-2">
                            <label class="font-label-md text-label-md text-on-surface-variant">Current Password</label>
                            <input type="password" name="current_password" required
                                class="rounded-xl border border-outline-variant px-3.5 py-2.5 font-body-md text-body-md text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="font-label-md text-label-md text-on-surface-variant">New Password</label>
                            <input type="password" name="password" required minlength="8"
                                class="rounded-xl border border-outline-variant px-3.5 py-2.5 font-body-md text-body-md text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="font-label-md text-label-md text-on-surface-variant">Confirm New Password</label>
                            <input type="password" name="password_confirmation" required
                                class="rounded-xl border border-outline-variant px-3.5 py-2.5 font-body-md text-body-md text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>

                        <button type="submit" class="self-start bg-primary text-on-primary font-label-md text-label-md px-6 py-2.5 rounded-full hover:bg-primary/90 transition-colors">
                            Update Password
                        </button>
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
