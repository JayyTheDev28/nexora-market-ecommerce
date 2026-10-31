@extends('layouts.app')

@section('title', 'My Account — Nexora Market')

@section('content')
<div x-data="accountDashboard()" class="w-full bg-surface py-6 md:py-8 min-h-[75vh]">
    <div class="max-w-none px-4 md:px-8">

        {{-- Sub-Navigation --}}
        <x-buyer-nav active="account" />

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="font-headline-lg text-2xl md:text-3xl font-bold tracking-tight text-on-surface">My Account</h1>
                <p class="font-body-md text-xs md:text-sm text-on-surface-variant mt-1">Manage your profile, delivery addresses, and account security.</p>
            </div>
            
            {{-- Tabs --}}
            <div class="flex items-center gap-2 p-1 bg-surface-container-low rounded-2xl border border-outline-variant/60">
                <button
                    type="button"
                    @click="activeTab = 'profile'"
                    :class="activeTab === 'profile' ? 'bg-surface-container-lowest text-primary font-bold shadow-xs' : 'text-on-surface-variant hover:text-on-surface'"
                    class="px-4 py-1.5 rounded-xl text-xs font-semibold transition-all">
                    Profile & Security
                </button>
                <button
                    type="button"
                    @click="activeTab = 'addresses'"
                    :class="activeTab === 'addresses' ? 'bg-surface-container-lowest text-primary font-bold shadow-xs' : 'text-on-surface-variant hover:text-on-surface'"
                    class="px-4 py-1.5 rounded-xl text-xs font-semibold transition-all flex items-center gap-1.5">
                    <span>Address Book</span>
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-primary/10 text-primary font-extrabold">
                        {{ $addresses->count() }}/10
                    </span>
                </button>
            </div>
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

        {{-- Tab 1: Profile & Security --}}
        <div x-show="activeTab === 'profile'" class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start max-w-5xl">

            {{-- Profile Information Card --}}
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-5 md:p-6 shadow-xs flex flex-col gap-5">
                <div class="border-b border-outline-variant/60 pb-3 flex items-center justify-between">
                    <div>
                        <h2 class="font-headline-sm text-sm font-bold text-on-surface">Profile Information</h2>
                        <p class="text-xs text-on-surface-variant mt-0.5">Update your identity and avatar photo.</p>
                    </div>
                    <img src="{{ $user->avatar_url }}" alt="{{ $user->full_name }}" class="w-12 h-12 rounded-full object-cover border border-outline-variant">
                </div>

                <form method="POST" action="{{ route('buyer.account.profile') }}" enctype="multipart/form-data" class="flex flex-col gap-4">
                    @csrf
                    @method('PUT')

                    {{-- Avatar Upload with Live Preview --}}
                    <div x-data="{ avatarPreview: null }" class="flex items-center gap-4 p-3 bg-surface-container-low/50 rounded-2xl border border-outline-variant/50">
                        <template x-if="avatarPreview">
                            <img :src="avatarPreview" class="w-14 h-14 rounded-full object-cover border-2 border-primary">
                        </template>
                        <template x-if="!avatarPreview">
                            <img src="{{ $user->avatar_url }}" class="w-14 h-14 rounded-full object-cover border border-outline-variant">
                        </template>
                        <div class="flex-1">
                            <label class="block text-xs font-semibold text-on-surface mb-1">Change Profile Photo</label>
                            <input
                                type="file"
                                name="avatar"
                                accept="image/png,image/jpeg,image/webp"
                                @change="const file = $event.target.files[0]; if (file) { avatarPreview = URL.createObjectURL(file); }"
                                class="text-[11px] text-on-surface-variant file:mr-2 file:py-1 file:px-2.5 file:rounded-xl file:border-0 file:text-[11px] file:font-semibold file:bg-primary file:text-on-primary hover:file:opacity-90">
                        </div>
                    </div>

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

                    <div class="flex flex-col gap-1.5">
                        <label class="font-label-md text-xs font-semibold text-on-surface-variant">Birthday</label>
                        <input
                            type="date"
                            name="birthday"
                            required
                            value="{{ old('birthday', $user->birthday ? $user->birthday->toDateString() : '') }}"
                            class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>

                    <div class="pt-2">
                        <button
                            type="submit"
                            class="px-5 py-2.5 rounded-xl bg-primary text-on-primary font-label-md text-xs font-semibold hover:opacity-90 transition-opacity">
                            Save Profile Changes
                        </button>
                    </div>
                </form>
            </div>

            {{-- Security: Password & OTP Contact Updates --}}
            <div class="flex flex-col gap-6">

                {{-- Password Change Card --}}
                <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-5 md:p-6 shadow-xs flex flex-col gap-5">
                    <div class="border-b border-outline-variant/60 pb-3">
                        <h2 class="font-headline-sm text-sm font-bold text-on-surface">Change Password</h2>
                        <p class="text-xs text-on-surface-variant mt-0.5">Requires current password & strict complex validation.</p>
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
                                class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label class="font-label-md text-xs font-semibold text-on-surface-variant">New Password</label>
                            <input
                                type="password"
                                name="password"
                                required
                                placeholder="Min 8 chars, uppercase, lowercase, number, symbol"
                                class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label class="font-label-md text-xs font-semibold text-on-surface-variant">Confirm New Password</label>
                            <input
                                type="password"
                                name="password_confirmation"
                                required
                                class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>

                        <div class="pt-2">
                            <button
                                type="submit"
                                class="px-5 py-2.5 rounded-xl bg-primary text-on-primary font-label-md text-xs font-semibold hover:opacity-90 transition-opacity">
                                Update Password
                            </button>
                        </div>
                    </form>
                </div>

                {{-- Contact Changes via OTP Card --}}
                <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-5 md:p-6 shadow-xs flex flex-col gap-5">
                    <div class="border-b border-outline-variant/60 pb-3">
                        <h2 class="font-headline-sm text-sm font-bold text-on-surface">Email & Phone Updates via OTP</h2>
                        <p class="text-xs text-on-surface-variant mt-0.5">Secure verification required for changes.</p>
                    </div>

                    {{-- Email Change Flow --}}
                    <div class="p-4 rounded-xl border border-outline-variant/60 bg-surface flex flex-col gap-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="text-[11px] font-bold text-on-surface-variant uppercase tracking-wider">Current Email</span>
                                <p class="text-xs font-semibold text-on-surface">{{ $user->email }}</p>
                            </div>
                            <span class="px-2 py-0.5 rounded-md text-[10px] font-extrabold bg-emerald-500/10 text-emerald-700">Verified</span>
                        </div>

                        @if(!session('email_otp_requested') && !$user->pending_email)
                            <form method="POST" action="{{ route('buyer.account.email.request') }}" class="flex items-center gap-2 pt-2 border-t border-outline-variant/40">
                                @csrf
                                <input
                                    type="email"
                                    name="new_email"
                                    placeholder="Enter new email address"
                                    required
                                    class="flex-1 rounded-xl border border-outline-variant px-3 py-2 text-xs text-on-surface bg-surface-container-lowest focus:outline-none focus:ring-2 focus:ring-primary">
                                <button
                                    type="submit"
                                    class="px-3.5 py-2 rounded-xl bg-primary text-on-primary text-xs font-semibold hover:opacity-90 whitespace-nowrap">
                                    Send OTP
                                </button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('buyer.account.email.verify') }}" class="flex flex-col gap-2 pt-2 border-t border-outline-variant/40">
                                @csrf
                                <p class="text-[11px] text-primary font-medium">Verification OTP sent for: {{ $user->pending_email }}</p>
                                <div class="flex items-center gap-2">
                                    <input
                                        type="text"
                                        name="otp"
                                        maxlength="6"
                                        placeholder="6-digit OTP"
                                        required
                                        class="w-36 text-center tracking-widest font-mono text-xs rounded-xl border border-outline-variant px-3 py-2 text-on-surface bg-surface-container-lowest focus:outline-none focus:ring-2 focus:ring-primary">
                                    <button
                                        type="submit"
                                        class="px-3.5 py-2 rounded-xl bg-primary text-on-primary text-xs font-semibold hover:opacity-90">
                                        Verify & Save
                                    </button>
                                </div>
                            </form>
                        @endif
                    </div>

                    {{-- Phone Change Flow --}}
                    <div class="p-4 rounded-xl border border-outline-variant/60 bg-surface flex flex-col gap-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="text-[11px] font-bold text-on-surface-variant uppercase tracking-wider">Current Phone Number</span>
                                <p class="text-xs font-semibold text-on-surface">{{ $user->contact_number }}</p>
                            </div>
                        </div>

                        @if(!session('phone_otp_requested') && !$user->pending_phone)
                            <form method="POST" action="{{ route('buyer.account.phone.request') }}" class="flex items-center gap-2 pt-2 border-t border-outline-variant/40">
                                @csrf
                                <input
                                    type="text"
                                    name="new_phone"
                                    placeholder="e.g. 09123456789"
                                    required
                                    class="flex-1 rounded-xl border border-outline-variant px-3 py-2 text-xs text-on-surface bg-surface-container-lowest focus:outline-none focus:ring-2 focus:ring-primary">
                                <button
                                    type="submit"
                                    class="px-3.5 py-2 rounded-xl bg-primary text-on-primary text-xs font-semibold hover:opacity-90 whitespace-nowrap">
                                    Send OTP
                                </button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('buyer.account.phone.verify') }}" class="flex flex-col gap-2 pt-2 border-t border-outline-variant/40">
                                @csrf
                                <p class="text-[11px] text-primary font-medium">Verification OTP sent for: {{ $user->pending_phone }}</p>
                                <div class="flex items-center gap-2">
                                    <input
                                        type="text"
                                        name="otp"
                                        maxlength="6"
                                        placeholder="6-digit OTP"
                                        required
                                        class="w-36 text-center tracking-widest font-mono text-xs rounded-xl border border-outline-variant px-3 py-2 text-on-surface bg-surface-container-lowest focus:outline-none focus:ring-2 focus:ring-primary">
                                    <button
                                        type="submit"
                                        class="px-3.5 py-2 rounded-xl bg-primary text-on-primary text-xs font-semibold hover:opacity-90">
                                        Verify & Save
                                    </button>
                                </div>
                            </form>
                        @endif
                    </div>

                </div>

            </div>

        </div>

        {{-- Tab 2: Address Book --}}
        <div x-show="activeTab === 'addresses'" class="max-w-5xl flex flex-col gap-6">

            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-5 bg-surface-container-lowest rounded-2xl border border-outline-variant shadow-xs">
                <div>
                    <h2 class="text-base font-bold text-on-surface">Saved Delivery Addresses</h2>
                    <p class="text-xs text-on-surface-variant mt-0.5">Maximum 10 addresses per buyer. Default address is automatically applied at checkout.</p>
                </div>
                <button
                    type="button"
                    @click="showAddressModal = true"
                    :disabled="{{ $addresses->count() }} >= 10"
                    class="px-4 py-2 rounded-xl bg-primary text-on-primary text-xs font-semibold flex items-center gap-1.5 hover:opacity-90 transition-opacity disabled:opacity-50 disabled:cursor-not-allowed">
                    <span class="material-symbols-outlined text-[16px]">add_location_alt</span>
                    <span>Add New Address</span>
                </button>
            </div>

            {{-- Address Cards Grid --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @forelse($addresses as $addr)
                    <div class="p-5 rounded-2xl border {{ $addr->is_default ? 'border-primary bg-primary/5 shadow-xs' : 'border-outline-variant bg-surface-container-lowest' }} flex flex-col justify-between gap-4 relative">
                        <div class="flex flex-col gap-2">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-sm font-bold text-on-surface">{{ $addr->recipient_name ?? $user->full_name }}</span>
                                @if($addr->is_default)
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-primary text-on-primary">
                                        Default
                                    </span>
                                @endif
                            </div>

                            <p class="text-xs font-medium text-on-surface-variant flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-[14px]">call</span>
                                <span>{{ $addr->recipient_phone ?? $user->contact_number }}</span>
                            </p>

                            <p class="text-xs text-on-surface leading-relaxed mt-1">
                                {{ $addr->full_address }}
                            </p>
                        </div>

                        <div class="pt-3 border-t border-outline-variant/60 flex items-center justify-between gap-2">
                            @if(!$addr->is_default)
                                <form method="POST" action="{{ route('buyer.addresses.set-default', $addr->id) }}">
                                    @csrf
                                    <button
                                        type="submit"
                                        class="text-xs font-semibold text-primary hover:underline">
                                        Set as Default
                                    </button>
                                </form>
                            @else
                                <span class="text-xs text-outline font-medium">Default Protection Active</span>
                            @endif

                            <form method="POST" action="{{ route('buyer.addresses.destroy', $addr->id) }}" onsubmit="return confirm('Are you sure you want to delete this address?');">
                                @csrf
                                @method('DELETE')
                                <button
                                    type="submit"
                                    class="text-xs font-semibold text-rose-600 hover:text-rose-800">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full p-8 text-center bg-surface-container-lowest rounded-2xl border border-dashed border-outline-variant">
                        <span class="material-symbols-outlined text-outline text-[40px] mb-2">location_off</span>
                        <p class="text-xs font-bold text-on-surface">No saved addresses yet</p>
                        <p class="text-xs text-on-surface-variant mt-1">Add your delivery address to enjoy quick and smooth checkout.</p>
                    </div>
                @endforelse
            </div>

            {{-- Add Address Modal with Cascading Selector (Region -> Province -> City -> Barangay) --}}
            <div
                x-show="showAddressModal"
                x-cloak
                class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
                <div
                    @click.away="showAddressModal = false"
                    class="w-full max-w-xl bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 shadow-xl max-h-[90vh] overflow-y-auto flex flex-col gap-5">
                    
                    <div class="flex items-center justify-between pb-3 border-b border-outline-variant/60">
                        <div>
                            <h3 class="text-base font-bold text-on-surface">Add New Address</h3>
                            <p class="text-xs text-on-surface-variant">Cascading location selector: Region → Province → City → Barangay</p>
                        </div>
                        <button type="button" @click="showAddressModal = false" class="text-on-surface-variant hover:text-on-surface">
                            <span class="material-symbols-outlined text-[20px]">close</span>
                        </button>
                    </div>

                    <form method="POST" action="{{ route('buyer.addresses.store') }}" class="flex flex-col gap-4">
                        @csrf

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div class="flex flex-col gap-1">
                                <label class="text-xs font-semibold text-on-surface">Recipient Full Name</label>
                                <input type="text" name="recipient_name" required value="{{ old('recipient_name', $user->full_name) }}" class="rounded-xl border border-outline-variant px-3 py-2 text-xs bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                            </div>
                            <div class="flex flex-col gap-1">
                                <label class="text-xs font-semibold text-on-surface">Recipient Phone Number</label>
                                <input type="text" name="recipient_phone" required value="{{ old('recipient_phone', $user->contact_number) }}" placeholder="09XXXXXXXXX" class="rounded-xl border border-outline-variant px-3 py-2 text-xs bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                            </div>
                        </div>

                        {{-- Cascading Dropdowns --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            {{-- Region --}}
                            <div class="flex flex-col gap-1">
                                <label class="text-xs font-semibold text-on-surface">Region</label>
                                <select
                                    x-model="selectedRegion"
                                    @change="onRegionChange()"
                                    class="rounded-xl border border-outline-variant px-3 py-2 text-xs bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                                    <option value="">Select Region</option>
                                    <template x-for="r in regions" :key="r.code">
                                        <option :value="r.code" x-text="r.name"></option>
                                    </template>
                                </select>
                                <input type="hidden" name="region_code" :value="selectedRegion">
                                <input type="hidden" name="region_name" :value="selectedRegionName">
                            </div>

                            {{-- Province --}}
                            <div class="flex flex-col gap-1">
                                <label class="text-xs font-semibold text-on-surface">Province</label>
                                <select
                                    x-model="selectedProvince"
                                    @change="onProvinceChange()"
                                    :disabled="!selectedRegion && provinces.length === 0"
                                    required
                                    class="rounded-xl border border-outline-variant px-3 py-2 text-xs bg-surface focus:outline-none focus:ring-2 focus:ring-primary disabled:opacity-50">
                                    <option value="">Select Province</option>
                                    <template x-for="p in provinces" :key="p.code">
                                        <option :value="p.code" x-text="p.name"></option>
                                    </template>
                                </select>
                                <input type="hidden" name="province_code" :value="selectedProvince">
                                <input type="hidden" name="province_name" :value="selectedProvinceName">
                            </div>

                            {{-- City / Municipality --}}
                            <div class="flex flex-col gap-1">
                                <label class="text-xs font-semibold text-on-surface">City / Municipality</label>
                                <select
                                    x-model="selectedMunicipality"
                                    @change="onMunicipalityChange()"
                                    :disabled="!selectedProvince"
                                    required
                                    class="rounded-xl border border-outline-variant px-3 py-2 text-xs bg-surface focus:outline-none focus:ring-2 focus:ring-primary disabled:opacity-50">
                                    <option value="">Select City/Municipality</option>
                                    <template x-for="m in municipalities" :key="m.code">
                                        <option :value="m.code" x-text="m.name"></option>
                                    </template>
                                </select>
                                <input type="hidden" name="municipality_code" :value="selectedMunicipality">
                                <input type="hidden" name="municipality_name" :value="selectedMunicipalityName">
                            </div>

                            {{-- Barangay --}}
                            <div class="flex flex-col gap-1">
                                <label class="text-xs font-semibold text-on-surface">Barangay</label>
                                <select
                                    x-model="selectedBarangay"
                                    :disabled="!selectedMunicipality"
                                    required
                                    class="rounded-xl border border-outline-variant px-3 py-2 text-xs bg-surface focus:outline-none focus:ring-2 focus:ring-primary disabled:opacity-50">
                                    <option value="">Select Barangay</option>
                                    <template x-for="b in barangays" :key="b.code">
                                        <option :value="b.code" x-text="b.name"></option>
                                    </template>
                                </select>
                                <input type="hidden" name="barangay_code" :value="selectedBarangay">
                                <input type="hidden" name="barangay_name" :value="selectedBarangayName">
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-3">
                            <div class="col-span-2 flex flex-col gap-1">
                                <label class="text-xs font-semibold text-on-surface">Street / Building / Unit No.</label>
                                <input type="text" name="street" placeholder="e.g. 123 Rizal Ave, Bldg A" class="rounded-xl border border-outline-variant px-3 py-2 text-xs bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                            </div>
                            <div class="flex flex-col gap-1">
                                <label class="text-xs font-semibold text-on-surface">Postal Code</label>
                                <input type="text" name="postal_code" placeholder="e.g. 1000" class="rounded-xl border border-outline-variant px-3 py-2 text-xs bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                            </div>
                        </div>

                        <div class="flex items-center gap-2 pt-2">
                            <input type="checkbox" id="is_default_check" name="is_default" value="1" class="rounded border-outline-variant text-primary focus:ring-primary">
                            <label for="is_default_check" class="text-xs font-semibold text-on-surface">Set as default delivery address</label>
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-outline-variant/60">
                            <button type="button" @click="showAddressModal = false" class="px-4 py-2 rounded-xl text-xs font-semibold text-on-surface-variant hover:text-on-surface">
                                Cancel
                            </button>
                            <button type="submit" class="px-5 py-2 rounded-xl bg-primary text-on-primary text-xs font-semibold hover:opacity-90">
                                Save Address
                            </button>
                        </div>
                    </form>

                </div>
            </div>

        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
    function accountDashboard() {
        return {
            activeTab: 'profile',
            showAddressModal: false,

            // Cascading Location Selector Data
            regions: [],
            provinces: [],
            municipalities: [],
            barangays: [],

            selectedRegion: '',
            selectedProvince: '',
            selectedMunicipality: '',
            selectedBarangay: '',

            get selectedRegionName() {
                return this.regions.find(r => r.code === this.selectedRegion)?.name ?? '';
            },
            get selectedProvinceName() {
                return this.provinces.find(p => p.code === this.selectedProvince)?.name ?? '';
            },
            get selectedMunicipalityName() {
                return this.municipalities.find(m => m.code === this.selectedMunicipality)?.name ?? '';
            },
            get selectedBarangayName() {
                return this.barangays.find(b => b.code === this.selectedBarangay)?.name ?? '';
            },

            async init() {
                // Preload Philippine Regions
                try {
                    const res = await fetch('https://psgc.gitlab.io/api/regions/');
                    this.regions = (await res.json()).sort((a, b) => a.name.localeCompare(b.name));
                } catch (e) {
                    // Fallback to top regions
                    this.regions = [
                        { code: '010000000', name: 'Region I (Ilocos Region)' },
                        { code: '020000000', name: 'Region II (Cagayan Valley)' },
                        { code: '030000000', name: 'Region III (Central Luzon)' },
                        { code: '040000000', name: 'Region IV-A (CALABARZON)' },
                        { code: '130000000', name: 'NCR (National Capital Region)' },
                        { code: '070000000', name: 'Region VII (Central Visayas)' },
                        { code: '110000000', name: 'Region XI (Davao Region)' }
                    ];
                }
            },

            async onRegionChange() {
                this.provinces = [];
                this.municipalities = [];
                this.barangays = [];
                this.selectedProvince = '';
                this.selectedMunicipality = '';
                this.selectedBarangay = '';
                if (!this.selectedRegion) return;

                try {
                    const res = await fetch(`https://psgc.gitlab.io/api/regions/${this.selectedRegion}/provinces/`);
                    const data = await res.json();
                    if (data && data.length > 0) {
                        this.provinces = data.sort((a, b) => a.name.localeCompare(b.name));
                    } else {
                        // In case region has no provinces (like NCR), load cities-municipalities directly
                        const cityRes = await fetch(`https://psgc.gitlab.io/api/regions/${this.selectedRegion}/cities-municipalities/`);
                        this.municipalities = (await cityRes.json()).sort((a, b) => a.name.localeCompare(b.name));
                        this.provinces = [{ code: this.selectedRegion, name: this.selectedRegionName }];
                        this.selectedProvince = this.selectedRegion;
                    }
                } catch (e) {
                    console.error('Failed to load provinces', e);
                }
            },

            async onProvinceChange() {
                this.municipalities = [];
                this.barangays = [];
                this.selectedMunicipality = '';
                this.selectedBarangay = '';
                if (!this.selectedProvince) return;

                try {
                    const res = await fetch(`https://psgc.gitlab.io/api/provinces/${this.selectedProvince}/cities-municipalities/`);
                    this.municipalities = (await res.json()).sort((a, b) => a.name.localeCompare(b.name));
                } catch (e) {
                    console.error('Failed to load municipalities', e);
                }
            },

            async onMunicipalityChange() {
                this.barangays = [];
                this.selectedBarangay = '';
                if (!this.selectedMunicipality) return;

                try {
                    const res = await fetch(`https://psgc.gitlab.io/api/cities-municipalities/${this.selectedMunicipality}/barangays/`);
                    this.barangays = (await res.json()).sort((a, b) => a.name.localeCompare(b.name));
                } catch (e) {
                    console.error('Failed to load barangays', e);
                }
            }
        };
    }
</script>
@endpush
