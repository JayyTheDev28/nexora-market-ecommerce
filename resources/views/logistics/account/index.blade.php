@extends('layouts.app')

@section('title', 'Facility Account & Settings — Nexora Logistics')

@section('content')
<div class="w-full bg-surface py-8 min-h-[75vh]">
    <div class="max-w-4xl mx-auto px-margin-mobile md:px-margin-desktop">

        <x-logistics-nav active="account" />

        <div class="mb-6">
            <h1 class="font-headline-lg text-2xl font-bold text-on-surface">Sorting Facility Account</h1>
            <p class="font-body-md text-sm text-on-surface-variant mt-1">Manage sorting center facility information, staff contact, and account security.</p>
        </div>

        {{-- Alerts --}}
        @if (session('status'))
            <div class="mb-6 bg-primary/10 border border-primary/30 rounded-2xl p-4 flex items-center gap-3">
                <span class="material-symbols-outlined text-primary text-[20px]">check_circle</span>
                <p class="font-body-sm text-xs md:text-sm text-primary font-medium">{{ session('status') }}</p>
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 bg-rose-500/10 border border-rose-500/20 rounded-2xl p-4">
                <p class="font-label-md text-xs font-bold text-rose-700">Please correct the following errors:</p>
                <ul class="list-disc list-inside text-xs text-rose-600 mt-1 space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Account & Facility Info Form --}}
        <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-6 mb-8 shadow-xs">
            <h2 class="font-headline-sm text-base font-bold text-on-surface mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-primary text-[20px]">warehouse</span>
                <span>Facility Information & Staff In-Charge</span>
            </h2>

            <form method="POST" action="{{ route('logistics.account.profile') }}">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-5">
                    <div class="md:col-span-2">
                        <label class="block font-label-md text-xs font-semibold text-on-surface mb-1">Facility / Sorting Center Business Name</label>
                        <input
                            type="text"
                            name="business_name"
                            value="{{ old('business_name', $user->sortingCenterProfile?->business_name) }}"
                            required
                            class="w-full px-3 py-2 bg-surface-container-high border-none rounded-xl font-body-sm text-xs text-on-surface focus:ring-2 focus:ring-primary">
                    </div>

                    <div>
                        <label class="block font-label-md text-xs font-semibold text-on-surface mb-1">Staff First Name</label>
                        <input
                            type="text"
                            name="first_name"
                            value="{{ old('first_name', $user->first_name) }}"
                            required
                            class="w-full px-3 py-2 bg-surface-container-high border-none rounded-xl font-body-sm text-xs text-on-surface focus:ring-2 focus:ring-primary">
                    </div>

                    <div>
                        <label class="block font-label-md text-xs font-semibold text-on-surface mb-1">Staff Last Name</label>
                        <input
                            type="text"
                            name="last_name"
                            value="{{ old('last_name', $user->last_name) }}"
                            required
                            class="w-full px-3 py-2 bg-surface-container-high border-none rounded-xl font-body-sm text-xs text-on-surface focus:ring-2 focus:ring-primary">
                    </div>

                    <div>
                        <label class="block font-label-md text-xs font-semibold text-on-surface mb-1">Registered Email (Read-only)</label>
                        <input
                            type="email"
                            value="{{ $user->email }}"
                            disabled
                            class="w-full px-3 py-2 bg-surface-container-high/50 border-none rounded-xl font-body-sm text-xs text-on-surface-variant cursor-not-allowed">
                    </div>

                    <div>
                        <label class="block font-label-md text-xs font-semibold text-on-surface mb-1">Contact Phone Number</label>
                        <input
                            type="text"
                            name="contact_number"
                            value="{{ old('contact_number', $user->contact_number) }}"
                            required
                            class="w-full px-3 py-2 bg-surface-container-high border-none rounded-xl font-body-sm text-xs text-on-surface focus:ring-2 focus:ring-primary">
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-primary text-on-primary font-label-md text-xs font-bold shadow-xs hover:bg-primary/90 transition-all cursor-pointer">
                        Save Facility Profile
                    </button>
                </div>
            </form>
        </div>

        {{-- Facility Physical Address Form --}}
        <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-6 mb-8 shadow-xs">
            <h2 class="font-headline-sm text-base font-bold text-on-surface mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-secondary text-[20px]">location_on</span>
                <span>Facility Physical Hub Location</span>
            </h2>

            <form method="POST" action="{{ route('logistics.account.address') }}">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-5">
                    <div>
                        <label class="block font-label-md text-xs font-semibold text-on-surface mb-1">Province</label>
                        <input
                            type="text"
                            name="province_name"
                            value="{{ old('province_name', $user->address?->province_name) }}"
                            required
                            placeholder="e.g. Laguna"
                            class="w-full px-3 py-2 bg-surface-container-high border-none rounded-xl font-body-sm text-xs text-on-surface focus:ring-2 focus:ring-primary">
                        <input type="hidden" name="province_code" value="{{ $user->address?->province_code ?? '043400000' }}">
                    </div>

                    <div>
                        <label class="block font-label-md text-xs font-semibold text-on-surface mb-1">Municipality / City</label>
                        <input
                            type="text"
                            name="municipality_name"
                            value="{{ old('municipality_name', $user->address?->municipality_name) }}"
                            required
                            placeholder="e.g. Santa Cruz"
                            class="w-full px-3 py-2 bg-surface-container-high border-none rounded-xl font-body-sm text-xs text-on-surface focus:ring-2 focus:ring-primary">
                        <input type="hidden" name="municipality_code" value="{{ $user->address?->municipality_code ?? '043426000' }}">
                    </div>

                    <div>
                        <label class="block font-label-md text-xs font-semibold text-on-surface mb-1">Barangay</label>
                        <input
                            type="text"
                            name="barangay_name"
                            value="{{ old('barangay_name', $user->address?->barangay_name) }}"
                            required
                            placeholder="e.g. Poblacion I"
                            class="w-full px-3 py-2 bg-surface-container-high border-none rounded-xl font-body-sm text-xs text-on-surface focus:ring-2 focus:ring-primary">
                        <input type="hidden" name="barangay_code" value="{{ $user->address?->barangay_code ?? '043426001' }}">
                    </div>

                    <div>
                        <label class="block font-label-md text-xs font-semibold text-on-surface mb-1">Street / Highway</label>
                        <input
                            type="text"
                            name="street"
                            value="{{ old('street', $user->address?->street) }}"
                            placeholder="e.g. Logistics Boulevard"
                            class="w-full px-3 py-2 bg-surface-container-high border-none rounded-xl font-body-sm text-xs text-on-surface focus:ring-2 focus:ring-primary">
                    </div>

                    <div>
                        <label class="block font-label-md text-xs font-semibold text-on-surface mb-1">Building / Unit / Hub #</label>
                        <input
                            type="text"
                            name="house_number"
                            value="{{ old('house_number', $user->address?->house_number) }}"
                            placeholder="e.g. Warehouse 4B"
                            class="w-full px-3 py-2 bg-surface-container-high border-none rounded-xl font-body-sm text-xs text-on-surface focus:ring-2 focus:ring-primary">
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-secondary text-on-secondary font-label-md text-xs font-bold shadow-xs hover:bg-secondary/90 transition-all cursor-pointer">
                        Update Location
                    </button>
                </div>
            </form>
        </div>

        {{-- Security / Password Change Form --}}
        <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-6 shadow-xs">
            <h2 class="font-headline-sm text-base font-bold text-on-surface mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-primary text-[20px]">lock</span>
                <span>Security & Password</span>
            </h2>

            <form method="POST" action="{{ route('logistics.account.password') }}">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-5">
                    <div>
                        <label class="block font-label-md text-xs font-semibold text-on-surface mb-1">Current Password</label>
                        <input
                            type="password"
                            name="current_password"
                            required
                            class="w-full px-3 py-2 bg-surface-container-high border-none rounded-xl font-body-sm text-xs text-on-surface focus:ring-2 focus:ring-primary">
                    </div>

                    <div>
                        <label class="block font-label-md text-xs font-semibold text-on-surface mb-1">New Password</label>
                        <input
                            type="password"
                            name="password"
                            required
                            class="w-full px-3 py-2 bg-surface-container-high border-none rounded-xl font-body-sm text-xs text-on-surface focus:ring-2 focus:ring-primary">
                    </div>

                    <div>
                        <label class="block font-label-md text-xs font-semibold text-on-surface mb-1">Confirm New Password</label>
                        <input
                            type="password"
                            name="password_confirmation"
                            required
                            class="w-full px-3 py-2 bg-surface-container-high border-none rounded-xl font-body-sm text-xs text-on-surface focus:ring-2 focus:ring-primary">
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-primary text-on-primary font-label-md text-xs font-bold shadow-xs hover:bg-primary/90 transition-all cursor-pointer">
                        Change Password
                    </button>
                </div>
            </form>
        </div>

    </div>
</div>
@endsection
