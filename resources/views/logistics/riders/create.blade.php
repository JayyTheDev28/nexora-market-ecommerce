@extends('layouts.app')

@section('title', 'Add Rider — Nexora Logistics')

@section('content')
<div class="w-full min-h-screen bg-surface" x-data="{ submitting: false }">
    <div class="flex flex-col lg:flex-row w-full">

            <x-logistics-nav active="riders" />

            <div class="flex-1 min-w-0 p-4 sm:p-6 lg:p-8 bg-surface">
            <div class="w-full max-w-7xl mx-auto space-y-6">

        <div class="mb-6">
            <div class="flex items-center gap-2 text-xs text-on-surface-variant mb-2">
                <a href="{{ route('logistics.riders.index') }}" class="hover:text-primary transition-colors flex items-center gap-1">
                    <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                    <span>Rider Management</span>
                </a>
                <span>/</span>
                <span class="text-on-surface font-semibold">Add New Rider</span>
            </div>
            <h1 class="font-headline-lg text-2xl md:text-3xl font-bold text-on-surface">Add New Courier Rider</h1>
            <p class="font-body-md text-xs md:text-sm text-on-surface-variant mt-1">
                Register a new delivery rider under your logistics operations hub. An activation invitation with a secure setup link will be dispatched to the rider's email.
            </p>
        </div>

        {{-- Validation Errors --}}
        @if ($errors->any())
            <div class="mb-6 bg-rose-500/10 border border-rose-500/20 rounded-2xl p-4">
                <div class="flex items-center gap-2 text-rose-700 font-bold text-xs mb-1">
                    <span class="material-symbols-outlined text-[18px]">error</span>
                    <span>Please correct the errors below before submitting:</span>
                </div>
                <ul class="list-disc list-inside text-xs text-rose-600 space-y-0.5 ml-2">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form
            action="{{ route('logistics.riders.store') }}"
            method="POST"
            enctype="multipart/form-data"
            @submit="submitting = true"
            class="space-y-6">
            @csrf

            {{-- 1. Personal Details Card --}}
            <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 md:p-8 shadow-xs">
                <div class="flex items-center gap-3 pb-4 mb-6 border-b border-outline-variant">
                    <span class="w-9 h-9 rounded-xl bg-primary/10 text-primary flex items-center justify-center">
                        <span class="material-symbols-outlined text-[20px]">person</span>
                    </span>
                    <div>
                        <h2 class="font-headline-sm text-sm font-bold text-on-surface">1. Rider Personal Information</h2>
                        <p class="text-[11px] text-on-surface-variant">Legal name, demographic information, and contact credentials.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- First Name --}}
                    <div>
                        <label for="first_name" class="block font-label-md text-xs font-semibold text-on-surface mb-1">
                            First Name <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="first_name"
                            name="first_name"
                            value="{{ old('first_name') }}"
                            required
                            minlength="2"
                            maxlength="50"
                            placeholder="e.g. Juan"
                            class="w-full rounded-xl border {{ $errors->has('first_name') ? 'border-rose-500 ring-1 ring-rose-500' : 'border-outline-variant' }} bg-surface px-3.5 py-2.5 text-xs text-on-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary">
                        <span class="text-[10px] text-on-surface-variant mt-1 block">Letters and spaces only (2 to 50 characters).</span>
                    </div>

                    {{-- Last Name --}}
                    <div>
                        <label for="last_name" class="block font-label-md text-xs font-semibold text-on-surface mb-1">
                            Last Name <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="last_name"
                            name="last_name"
                            value="{{ old('last_name') }}"
                            required
                            minlength="2"
                            maxlength="50"
                            placeholder="e.g. Dela Cruz"
                            class="w-full rounded-xl border {{ $errors->has('last_name') ? 'border-rose-500 ring-1 ring-rose-500' : 'border-outline-variant' }} bg-surface px-3.5 py-2.5 text-xs text-on-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary">
                        <span class="text-[10px] text-on-surface-variant mt-1 block">Letters and spaces only (2 to 50 characters).</span>
                    </div>

                    {{-- Date of Birth --}}
                    <div>
                        <label for="birthday" class="block font-label-md text-xs font-semibold text-on-surface mb-1">
                            Date of Birth <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="date"
                            id="birthday"
                            name="birthday"
                            value="{{ old('birthday') }}"
                            max="{{ now()->subYears(18)->format('Y-m-d') }}"
                            required
                            class="w-full rounded-xl border {{ $errors->has('birthday') ? 'border-rose-500 ring-1 ring-rose-500' : 'border-outline-variant' }} bg-surface px-3.5 py-2.5 text-xs text-on-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary">
                        <span class="text-[10px] text-on-surface-variant mt-1 block">Must be at least 18 years old as of today.</span>
                    </div>

                    {{-- Mobile Number --}}
                    <div>
                        <label for="contact_number" class="block font-label-md text-xs font-semibold text-on-surface mb-1">
                            Mobile Number <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="contact_number"
                            name="contact_number"
                            value="{{ old('contact_number') }}"
                            required
                            placeholder="09170000001"
                            maxlength="11"
                            class="w-full rounded-xl border {{ $errors->has('contact_number') ? 'border-rose-500 ring-1 ring-rose-500' : 'border-outline-variant' }} bg-surface px-3.5 py-2.5 text-xs text-on-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary">
                        <span class="text-[10px] text-on-surface-variant mt-1 block">Must start with 09 and be exactly 11 digits.</span>
                    </div>

                    {{-- Email Address --}}
                    <div class="md:col-span-2">
                        <label for="email" class="block font-label-md text-xs font-semibold text-on-surface mb-1">
                            Email Address <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="{{ old('email') }}"
                            required
                            placeholder="rider.juan@example.com"
                            class="w-full rounded-xl border {{ $errors->has('email') ? 'border-rose-500 ring-1 ring-rose-500' : 'border-outline-variant' }} bg-surface px-3.5 py-2.5 text-xs text-on-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary">
                        <span class="text-[10px] text-on-surface-variant mt-1 block">Must be unique across all system accounts. Activation link will be delivered here.</span>
                    </div>
                </div>
            </div>

            {{-- 2. Vehicle & Driver License Card --}}
            <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 md:p-8 shadow-xs">
                <div class="flex items-center gap-3 pb-4 mb-6 border-b border-outline-variant">
                    <span class="w-9 h-9 rounded-xl bg-secondary/10 text-secondary flex items-center justify-center">
                        <span class="material-symbols-outlined text-[20px]">two_wheeler</span>
                    </span>
                    <div>
                        <h2 class="font-headline-sm text-sm font-bold text-on-surface">2. Driver License & Vehicle Details</h2>
                        <p class="text-[11px] text-on-surface-variant">Philippine driver's license accreditation and vehicle assignment.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- Driver's License No. --}}
                    <div>
                        <label for="license_number" class="block font-label-md text-xs font-semibold text-on-surface mb-1">
                            Driver's License No. <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="license_number"
                            name="license_number"
                            value="{{ old('license_number') }}"
                            required
                            placeholder="N01-23-456789"
                            class="w-full uppercase rounded-xl border {{ $errors->has('license_number') ? 'border-rose-500 ring-1 ring-rose-500' : 'border-outline-variant' }} bg-surface px-3.5 py-2.5 text-xs text-on-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary">
                        <span class="text-[10px] text-on-surface-variant mt-1 block">Philippine LTO format: 1 letter, 2 digits, hyphen, 2 digits, hyphen, 6 digits.</span>
                    </div>

                    {{-- License Expiry Date --}}
                    <div>
                        <label for="license_expiry_date" class="block font-label-md text-xs font-semibold text-on-surface mb-1">
                            License Expiry Date <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="date"
                            id="license_expiry_date"
                            name="license_expiry_date"
                            value="{{ old('license_expiry_date') }}"
                            min="{{ now()->format('Y-m-d') }}"
                            required
                            class="w-full rounded-xl border {{ $errors->has('license_expiry_date') ? 'border-rose-500 ring-1 ring-rose-500' : 'border-outline-variant' }} bg-surface px-3.5 py-2.5 text-xs text-on-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary">
                        <span class="text-[10px] text-on-surface-variant mt-1 block">Must be today or a future date.</span>
                    </div>

                    {{-- Vehicle Type --}}
                    <div>
                        <label for="vehicle_type" class="block font-label-md text-xs font-semibold text-on-surface mb-1">
                            Vehicle Type <span class="text-rose-500">*</span>
                        </label>
                        <select
                            id="vehicle_type"
                            name="vehicle_type"
                            required
                            class="w-full rounded-xl border {{ $errors->has('vehicle_type') ? 'border-rose-500 ring-1 ring-rose-500' : 'border-outline-variant' }} bg-surface px-3.5 py-2.5 text-xs text-on-surface focus:outline-none focus:ring-2 focus:ring-primary">
                            <option value="">Select vehicle type...</option>
                            <option value="Motorcycle" {{ old('vehicle_type') === 'Motorcycle' ? 'selected' : '' }}>Motorcycle (Standard Delivery)</option>
                            <option value="Van" {{ old('vehicle_type') === 'Van' ? 'selected' : '' }}>Van (Bulk / Multi-Parcel)</option>
                            <option value="Sedan" {{ old('vehicle_type') === 'Sedan' ? 'selected' : '' }}>Sedan / Car</option>
                            <option value="Truck" {{ old('vehicle_type') === 'Truck' ? 'selected' : '' }}>Light Truck / Delivery Van</option>
                            <option value="Bicycle" {{ old('vehicle_type') === 'Bicycle' ? 'selected' : '' }}>Bicycle (Local Express)</option>
                        </select>
                    </div>

                    {{-- Plate Number --}}
                    <div>
                        <label for="plate_number" class="block font-label-md text-xs font-semibold text-on-surface mb-1">
                            Vehicle Plate Number <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="text"
                            id="plate_number"
                            name="plate_number"
                            value="{{ old('plate_number') }}"
                            required
                            placeholder="e.g. ABC 1234 or MC-9876"
                            class="w-full uppercase rounded-xl border {{ $errors->has('plate_number') ? 'border-rose-500 ring-1 ring-rose-500' : 'border-outline-variant' }} bg-surface px-3.5 py-2.5 text-xs text-on-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary">
                        <span class="text-[10px] text-on-surface-variant mt-1 block">Must be unique across the platform.</span>
                    </div>
                </div>
            </div>

            {{-- 3. Document Uploads Card --}}
            <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 md:p-8 shadow-xs">
                <div class="flex items-center gap-3 pb-4 mb-6 border-b border-outline-variant">
                    <span class="w-9 h-9 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center">
                        <span class="material-symbols-outlined text-[20px]">upload_file</span>
                    </span>
                    <div>
                        <h2 class="font-headline-sm text-sm font-bold text-on-surface">3. Required Compliance Documents</h2>
                        <p class="text-[11px] text-on-surface-variant">Attach verified scanned copies. Supported formats: JPG, PNG, PDF (Max 5 MB each).</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {{-- Driver's License Upload --}}
                    <div class="p-4 rounded-2xl border border-dashed border-outline-variant bg-surface-container-low/50">
                        <label for="license_file" class="block font-label-md text-xs font-semibold text-on-surface mb-1 flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-[16px] text-primary">badge</span>
                            <span>Driver's License Document <span class="text-rose-500">*</span></span>
                        </label>
                        <p class="text-[11px] text-on-surface-variant mb-3">Front or back copy of driver's license.</p>
                        <input
                            type="file"
                            id="license_file"
                            name="license_file"
                            accept=".jpg,.jpeg,.png,.pdf"
                            required
                            class="w-full text-xs text-on-surface-variant file:mr-3 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-primary/10 file:text-primary hover:file:bg-primary/20 cursor-pointer">
                        <span class="text-[10px] text-on-surface-variant mt-2 block">Formats: JPG, PNG, PDF (Max 5MB).</span>
                    </div>

                    {{-- Vehicle OR/CR Upload --}}
                    <div class="p-4 rounded-2xl border border-dashed border-outline-variant bg-surface-container-low/50">
                        <label for="or_cr_file" class="block font-label-md text-xs font-semibold text-on-surface mb-1 flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-[16px] text-secondary">description</span>
                            <span>Vehicle OR / CR Document <span class="text-rose-500">*</span></span>
                        </label>
                        <p class="text-[11px] text-on-surface-variant mb-3">Official Receipt and Certificate of Registration.</p>
                        <input
                            type="file"
                            id="or_cr_file"
                            name="or_cr_file"
                            accept=".jpg,.jpeg,.png,.pdf"
                            required
                            class="w-full text-xs text-on-surface-variant file:mr-3 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-secondary/10 file:text-secondary hover:file:bg-secondary/20 cursor-pointer">
                        <span class="text-[10px] text-on-surface-variant mt-2 block">Formats: JPG, PNG, PDF (Max 5MB).</span>
                    </div>
                </div>
            </div>

            {{-- 4. Form Actions & Double-Submit Guard --}}
            <div class="flex items-center justify-end gap-3 pt-4">
                <a
                    href="{{ route('logistics.riders.index') }}"
                    class="px-5 py-2.5 rounded-full border border-outline-variant text-on-surface hover:bg-surface-container font-semibold text-xs transition-colors">
                    Cancel
                </a>

                <button
                    type="submit"
                    :disabled="submitting"
                    class="inline-flex items-center gap-2 px-6 py-2.5 rounded-full bg-primary text-on-primary font-semibold text-xs shadow-xs hover:bg-primary/90 transition-all cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                    <span x-show="submitting" class="material-symbols-outlined animate-spin text-[16px]">progress_activity</span>
                    <span class="material-symbols-outlined text-[16px]" x-show="!submitting">person_add</span>
                    <span x-text="submitting ? 'Registering Rider…' : 'Save & Send Activation Link'"></span>
                </button>
            </div>

        </form>

                </div>
        </div>
    </div>
</div>

@endsection
