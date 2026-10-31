@extends('layouts.app')

@section('title', 'Application Feedback & Resubmission — Nexora Market')

@section('content')
<div class="w-full min-h-[calc(100vh-4rem)] bg-surface-container-lowest py-8 md:py-12">
    <div class="max-w-4xl mx-auto px-4 sm:px-6">

        {{-- Header --}}
        <div class="text-center mb-8">
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-rose-500/10 text-rose-700 text-xs font-bold mb-3">
                <span class="material-symbols-outlined text-[18px]">assignment_late</span>
                <span>Application Revision Required</span>
            </div>
            <h1 class="font-headline-lg text-2xl sm:text-3xl font-extrabold text-on-surface tracking-tight">
                Review Compliance Feedback & Resubmit
            </h1>
            <p class="text-xs sm:text-sm text-on-surface-variant max-w-xl mx-auto mt-2 leading-relaxed">
                Your logistics partner application requires corrections before it can be approved. Please review the administrator's notes below and update your details or re-upload documents.
            </p>
        </div>

        {{-- Admin Rejection Reason Alert Box (TC-LOG-041) --}}
        <div class="mb-8 p-5 sm:p-6 rounded-3xl bg-rose-500/10 border-2 border-rose-500/30 flex flex-col gap-2.5">
            <div class="flex items-center gap-2 text-rose-700 font-extrabold text-sm">
                <span class="material-symbols-outlined text-[22px]">feedback</span>
                <span>Administrator Compliance Feedback</span>
            </div>
            <div class="p-4 rounded-2xl bg-surface-container-lowest border border-rose-500/20 text-xs text-on-surface leading-relaxed">
                <p class="font-semibold text-rose-800 mb-1">Reason for Rejection / Changes Requested:</p>
                <p class="italic text-on-surface">“{{ $profile->rejection_reason ?? $user->disapproval_reason ?? 'Your application did not satisfy all compliance guidelines. Please review uploaded permits and registration details.' }}”</p>
            </div>
        </div>

        {{-- Validation Errors --}}
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

        {{-- Resubmission Form (TC-LOG-042) --}}
        <form
            id="resubmitForm"
            method="POST"
            action="{{ route('logistics.resubmit.store') }}"
            enctype="multipart/form-data"
            x-data="resubmitForm()"
            class="flex flex-col gap-8 bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 sm:p-10 shadow-xs">
            @csrf

            {{-- 1. Company Information --}}
            <div class="flex flex-col gap-4">
                <div class="border-b border-outline-variant/60 pb-3">
                    <h2 class="font-headline-sm text-sm sm:text-base font-bold text-on-surface flex items-center gap-2">
                        <span class="w-6 h-6 rounded-lg bg-primary/10 text-primary text-xs font-extrabold flex items-center justify-center">1</span>
                        Company Details
                    </h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="flex flex-col gap-1 md:col-span-2">
                        <label class="text-xs font-semibold text-on-surface-variant">Corporate Company Name <span class="text-rose-500">*</span></label>
                        <input type="text" name="company_name" value="{{ old('company_name', $profile->company_name) }}" required class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-on-surface-variant">SEC Registration Number <span class="text-rose-500">*</span></label>
                        <input type="text" name="sec_registration_no" value="{{ old('sec_registration_no', $profile->sec_registration_no) }}" required class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-on-surface-variant">TIN (XXX-XXX-XXX-XXX) <span class="text-rose-500">*</span></label>
                        <input type="text" name="tin" value="{{ old('tin', $profile->tin) }}" required class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface font-mono focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                </div>
            </div>

            {{-- 2. Address --}}
            <div class="flex flex-col gap-4">
                <div class="border-b border-outline-variant/60 pb-3">
                    <h2 class="font-headline-sm text-sm sm:text-base font-bold text-on-surface flex items-center gap-2">
                        <span class="w-6 h-6 rounded-lg bg-primary/10 text-primary text-xs font-extrabold flex items-center justify-center">2</span>
                        Principal Address
                    </h2>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-on-surface-variant">Region <span class="text-rose-500">*</span></label>
                        <input type="text" name="region" value="{{ old('region', $profile->region) }}" required class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-on-surface-variant">Province <span class="text-rose-500">*</span></label>
                        <input type="text" name="province" value="{{ old('province', $profile->province) }}" required class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-on-surface-variant">City / Municipality <span class="text-rose-500">*</span></label>
                        <input type="text" name="city_municipality" value="{{ old('city_municipality', $profile->city_municipality) }}" required class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-on-surface-variant">Barangay <span class="text-rose-500">*</span></label>
                        <input type="text" name="barangay" value="{{ old('barangay', $profile->barangay) }}" required class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>

                    <div class="flex flex-col gap-1 sm:col-span-2">
                        <label class="text-xs font-semibold text-on-surface-variant">Street Address <span class="text-rose-500">*</span></label>
                        <input type="text" name="street_address" value="{{ old('street_address', $profile->street_address) }}" required class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-on-surface-variant">Postal Code <span class="text-rose-500">*</span></label>
                        <input type="text" name="postal_code" value="{{ old('postal_code', $profile->postal_code) }}" required maxlength="4" class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface font-mono focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                </div>
            </div>

            {{-- 3. Contact Person --}}
            <div class="flex flex-col gap-4">
                <div class="border-b border-outline-variant/60 pb-3">
                    <h2 class="font-headline-sm text-sm sm:text-base font-bold text-on-surface flex items-center gap-2">
                        <span class="w-6 h-6 rounded-lg bg-primary/10 text-primary text-xs font-extrabold flex items-center justify-center">3</span>
                        Authorized Signatory
                    </h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-on-surface-variant">First Name <span class="text-rose-500">*</span></label>
                        <input type="text" name="contact_person_first_name" value="{{ old('contact_person_first_name', $profile->contact_person_first_name) }}" required class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-on-surface-variant">Last Name <span class="text-rose-500">*</span></label>
                        <input type="text" name="contact_person_last_name" value="{{ old('contact_person_last_name', $profile->contact_person_last_name) }}" required class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-on-surface-variant">Position <span class="text-rose-500">*</span></label>
                        <input type="text" name="position" value="{{ old('position', $profile->position) }}" required class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-on-surface-variant">Mobile Number <span class="text-rose-500">*</span></label>
                        <input type="tel" name="mobile_number" value="{{ old('mobile_number', $profile->mobile_number) }}" required class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                </div>
            </div>

            {{-- 4. Regional Coverage --}}
            <div class="flex flex-col gap-4">
                <div class="border-b border-outline-variant/60 pb-3">
                    <h2 class="font-headline-sm text-sm sm:text-base font-bold text-on-surface flex items-center gap-2">
                        <span class="w-6 h-6 rounded-lg bg-primary/10 text-primary text-xs font-extrabold flex items-center justify-center">4</span>
                        Service Coverage Areas
                    </h2>
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
                        $selectedServiceAreas = (array) old('service_areas', $profile->service_areas ?? []);
                    @endphp

                    @foreach($regionsList as $area)
                        <label class="flex items-center gap-2.5 p-3 rounded-2xl border cursor-pointer transition-all"
                            :class="selectedAreas.includes('{{ $area }}') ? 'border-primary bg-primary/10 text-primary font-bold shadow-xs' : 'border-outline-variant/70 bg-surface hover:bg-surface-container-low text-on-surface'">
                            <input
                                type="checkbox"
                                name="service_areas[]"
                                value="{{ $area }}"
                                @change="toggleArea('{{ $area }}')"
                                {{ in_array($area, $selectedServiceAreas) ? 'checked' : '' }}
                                class="w-4 h-4 rounded text-primary focus:ring-primary border-outline-variant">
                            <span class="text-xs leading-tight">{{ $area }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- 5. Re-Upload Compliance Documents --}}
            <div class="flex flex-col gap-4">
                <div class="border-b border-outline-variant/60 pb-3">
                    <h2 class="font-headline-sm text-sm sm:text-base font-bold text-on-surface flex items-center gap-2">
                        <span class="w-6 h-6 rounded-lg bg-primary/10 text-primary text-xs font-extrabold flex items-center justify-center">5</span>
                        Compliance Documents
                    </h2>
                    <p class="text-xs text-on-surface-variant mt-0.5">Upload replacement documents if requested in the compliance feedback. Existing documents will be retained if not re-uploaded.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- SEC Certificate --}}
                    <div class="flex flex-col gap-1.5 p-4 rounded-2xl border border-dashed border-outline-variant bg-surface-container-low/40">
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-bold text-on-surface">SEC Registration Certificate</label>
                            @if($profile->sec_certificate_path)
                                <a href="{{ Storage::url($profile->sec_certificate_path) }}" target="_blank" class="text-[11px] text-primary font-semibold hover:underline">View Current File</a>
                            @endif
                        </div>
                        <input type="file" name="sec_certificate" accept=".pdf,.jpg,.jpeg,.png" class="text-xs text-on-surface-variant file:mr-2 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-primary file:text-on-primary">
                    </div>

                    {{-- BIR Form 2303 --}}
                    <div class="flex flex-col gap-1.5 p-4 rounded-2xl border border-dashed border-outline-variant bg-surface-container-low/40">
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-bold text-on-surface">BIR Form 2303 Certificate</label>
                            @if($profile->bir_2303_path)
                                <a href="{{ Storage::url($profile->bir_2303_path) }}" target="_blank" class="text-[11px] text-primary font-semibold hover:underline">View Current File</a>
                            @endif
                        </div>
                        <input type="file" name="bir_2303" accept=".pdf,.jpg,.jpeg,.png" class="text-xs text-on-surface-variant file:mr-2 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-primary file:text-on-primary">
                    </div>

                    {{-- Mayor's Permit --}}
                    <div class="flex flex-col gap-1.5 p-4 rounded-2xl border border-dashed border-outline-variant bg-surface-container-low/40">
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-bold text-on-surface">Mayor's / Business Permit</label>
                            @if($profile->mayors_permit_path)
                                <a href="{{ Storage::url($profile->mayors_permit_path) }}" target="_blank" class="text-[11px] text-primary font-semibold hover:underline">View Current File</a>
                            @endif
                        </div>
                        <input type="file" name="mayors_permit" accept=".pdf,.jpg,.jpeg,.png" class="text-xs text-on-surface-variant file:mr-2 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-primary file:text-on-primary">
                    </div>

                    {{-- Valid ID --}}
                    <div class="flex flex-col gap-1.5 p-4 rounded-2xl border border-dashed border-outline-variant bg-surface-container-low/40">
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-bold text-on-surface">Valid Government ID</label>
                            @if($profile->valid_id_path)
                                <a href="{{ Storage::url($profile->valid_id_path) }}" target="_blank" class="text-[11px] text-primary font-semibold hover:underline">View Current File</a>
                            @endif
                        </div>
                        <input type="file" name="valid_id" accept=".pdf,.jpg,.jpeg,.png" class="text-xs text-on-surface-variant file:mr-2 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-primary file:text-on-primary">
                    </div>
                </div>
            </div>

            {{-- Actions --}}
            <div class="pt-4 border-t border-outline-variant/60 flex items-center justify-between gap-4">
                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="text-xs text-on-surface-variant hover:text-error transition-colors cursor-pointer">
                        Sign Out
                    </button>
                </form>

                <button
                    type="submit"
                    class="px-8 py-3.5 rounded-full bg-primary text-on-primary font-bold text-xs shadow-md shadow-primary/20 hover:bg-primary/90 transition-all cursor-pointer">
                    Save Changes & Resubmit Application
                </button>
            </div>

        </form>

    </div>
</div>

@push('scripts')
<script>
    function resubmitForm() {
        return {
            selectedAreas: @json(old('service_areas', $profile->service_areas ?? [])),

            toggleArea(area) {
                const idx = this.selectedAreas.indexOf(area);
                if (idx === -1) {
                    this.selectedAreas.push(area);
                } else {
                    this.selectedAreas.splice(idx, 1);
                }
            }
        };
    }
</script>
@endpush
@endsection

