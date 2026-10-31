@extends('layouts.app')

@section('title', 'Account Applications — Admin Portal — Nexora Market')

@section('content')
<div class="w-full bg-surface py-8 min-h-[75vh]" x-data="{
    declineModalOpen: false,
    applicantId: null,
    applicantName: '',
    openDeclineModal(id, name) {
        this.applicantId = id;
        this.applicantName = name;
        this.declineModalOpen = true;
    }
}">
    <div class="max-w-none px-margin-mobile md:px-margin-desktop">

        <x-admin-nav active="applications" />

        {{-- Flash messages --}}
        @if (session('status'))
            <div class="mb-6 bg-primary/10 border border-primary/30 rounded-2xl p-4 flex items-center justify-between">
                <div class="flex items-center gap-2 text-primary font-body-sm text-sm">
                    <span class="material-symbols-outlined text-[20px]">check_circle</span>
                    <span>{{ session('status') }}</span>
                </div>
            </div>
        @endif

        {{-- Page Header --}}
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="font-headline-lg text-2xl font-bold text-on-surface">Manage Account Applications</h1>
                <p class="font-body-md text-sm text-on-surface-variant">Review and verify applicant credentials for Buyers, Sellers, and Logistics Sorting Centers.</p>
            </div>

            {{-- Status Tabs --}}
            <div class="flex items-center gap-1 bg-surface-container-low p-1 rounded-2xl border border-outline-variant">
                <a href="{{ route('admin.applications.index', ['status' => 'pending', 'role' => $role, 'q' => $search]) }}"
                   class="px-3.5 py-1.5 rounded-xl font-label-md text-xs font-semibold transition-all {{ $status === 'pending' ? 'bg-primary text-on-primary shadow-xs' : 'text-on-surface-variant hover:text-on-surface' }}">
                    Pending ({{ $counts['pending'] }})
                </a>
                <a href="{{ route('admin.applications.index', ['status' => 'approved', 'role' => $role, 'q' => $search]) }}"
                   class="px-3.5 py-1.5 rounded-xl font-label-md text-xs font-semibold transition-all {{ $status === 'approved' ? 'bg-primary text-on-primary shadow-xs' : 'text-on-surface-variant hover:text-on-surface' }}">
                    Approved ({{ $counts['approved'] }})
                </a>
                <a href="{{ route('admin.applications.index', ['status' => 'disapproved', 'role' => $role, 'q' => $search]) }}"
                   class="px-3.5 py-1.5 rounded-xl font-label-md text-xs font-semibold transition-all {{ $status === 'disapproved' ? 'bg-primary text-on-primary shadow-xs' : 'text-on-surface-variant hover:text-on-surface' }}">
                    Disapproved ({{ $counts['disapproved'] }})
                </a>
                <a href="{{ route('admin.applications.index', ['status' => 'all', 'role' => $role, 'q' => $search]) }}"
                   class="px-3.5 py-1.5 rounded-xl font-label-md text-xs font-semibold transition-all {{ $status === 'all' ? 'bg-primary text-on-primary shadow-xs' : 'text-on-surface-variant hover:text-on-surface' }}">
                    All
                </a>
            </div>
        </div>

        {{-- Filters & Search Row --}}
        <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4 mb-6 shadow-xs">
            <form method="GET" action="{{ route('admin.applications.index') }}" class="flex flex-col md:flex-row items-center gap-3">
                <input type="hidden" name="status" value="{{ $status }}">

                <div class="relative flex-1 w-full">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-[18px]">search</span>
                    <input type="text" name="q" value="{{ $search }}"
                           placeholder="Search by name, email, phone, or registered business..."
                           class="w-full pl-9 pr-4 py-2 bg-surface-container rounded-xl text-xs text-on-surface placeholder:text-outline border border-outline-variant focus:outline-none focus:border-primary">
                </div>

                <div class="w-full md:w-48">
                    <select name="role" class="w-full px-3 py-2 bg-surface-container rounded-xl text-xs text-on-surface border border-outline-variant focus:outline-none focus:border-primary">
                        <option value="">All Roles</option>
                        <option value="buyer" {{ $role === 'buyer' ? 'selected' : '' }}>Buyers</option>
                        <option value="seller" {{ $role === 'seller' ? 'selected' : '' }}>Sellers / Merchants</option>
                        <option value="sorting_center" {{ $role === 'sorting_center' ? 'selected' : '' }}>Logistics Hubs</option>
                    </select>
                </div>

                <div class="flex items-center gap-2 w-full md:w-auto">
                    <button type="submit" class="w-full md:w-auto px-4 py-2 rounded-xl bg-primary text-on-primary font-label-md text-xs font-semibold shadow-xs hover:bg-primary/90 transition-all">
                        Filter
                    </button>
                    @if($search || $role || $status !== 'pending')
                        <a href="{{ route('admin.applications.index') }}" class="w-full md:w-auto px-3 py-2 rounded-xl border border-outline-variant text-on-surface-variant text-xs font-semibold hover:bg-surface-container transition-all text-center">
                            Reset
                        </a>
                    @endif
                </div>
            </form>
        </div>

        {{-- Applications Table --}}
        <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant overflow-hidden shadow-xs">
            @if($applications->isEmpty())
                <div class="p-12 text-center flex flex-col items-center justify-center">
                    <span class="material-symbols-outlined text-[48px] text-outline mb-3">inbox</span>
                    <h3 class="font-headline-sm text-base font-bold text-on-surface">No applications found</h3>
                    <p class="font-body-sm text-xs text-on-surface-variant mt-1">There are currently no registration applications matching the specified criteria.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-surface-container-low border-b border-outline-variant text-on-surface-variant font-semibold">
                            <tr>
                                <th class="px-5 py-3.5">Applicant Details</th>
                                <th class="px-5 py-3.5">Role</th>
                                <th class="px-5 py-3.5">Contact Information</th>
                                <th class="px-5 py-3.5">Email Verified</th>
                                <th class="px-5 py-3.5">Required Documents</th>
                                <th class="px-5 py-3.5">Status</th>
                                <th class="px-5 py-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant">
                            @foreach($applications as $applicant)
                                <tr class="hover:bg-surface-container-low/50 transition-colors">
                                    <td class="px-5 py-4">
                                        <span class="font-bold text-on-surface text-sm block">{{ $applicant->full_name }}</span>
                                        @if($applicant->logisticsProfile)
                                            <div class="text-[11px] text-primary font-semibold mt-0.5">
                                                <span>Carrier: {{ $applicant->logisticsProfile->company_name }}</span>
                                                <span class="text-on-surface-variant block font-normal text-[10px]">SEC: {{ $applicant->logisticsProfile->sec_registration_no }} • TIN: {{ $applicant->logisticsProfile->tin }}</span>
                                                <span class="text-on-surface-variant block font-normal text-[10px]">Coverage: {{ implode(', ', (array) $applicant->logisticsProfile->service_areas) }}</span>
                                            </div>
                                        @elseif($applicant->sellerProfile)
                                            <div class="text-[11px] text-primary font-semibold mt-0.5">
                                                <span>Store: {{ $applicant->sellerProfile->business_name }}</span>
                                                <span class="text-on-surface-variant block font-normal text-[10px]">LOB: {{ $applicant->sellerProfile->line_of_business }}</span>
                                            </div>
                                        @elseif($applicant->sortingCenterProfile)
                                            <div class="text-[11px] text-primary font-semibold mt-0.5">
                                                <span>Hub: {{ $applicant->sortingCenterProfile->business_name }}</span>
                                            </div>
                                        @endif
                                        <span class="text-[10px] text-on-surface-variant block mt-1">Applied: {{ $applicant->created_at->format('M d, Y h:i A') }}</span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-surface-container-high text-on-surface">
                                            {{ $applicant->roleLabel() }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="font-medium text-on-surface block">{{ $applicant->email }}</span>
                                        <span class="text-[11px] text-on-surface-variant block">{{ $applicant->contact_number }}</span>
                                    </td>
                                    <td class="px-5 py-4">
                                        @if($applicant->hasVerifiedEmail())
                                            <span class="inline-flex items-center gap-1 font-semibold text-emerald-600">
                                                <span class="material-symbols-outlined text-[16px]">verified</span>
                                                <span>Verified</span>
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 font-semibold text-amber-600">
                                                <span class="material-symbols-outlined text-[16px]">pending</span>
                                                <span>Unverified</span>
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="flex flex-col gap-1.5">
                                            @if($applicant->logisticsProfile)
                                                @if($applicant->logisticsProfile->sec_certificate_path)
                                                    <a href="{{ Storage::url($applicant->logisticsProfile->sec_certificate_path) }}" target="_blank"
                                                       class="inline-flex items-center gap-1 text-primary hover:underline font-semibold text-[11px]">
                                                        <span class="material-symbols-outlined text-[15px]">verified</span>
                                                        <span>SEC Certificate</span>
                                                    </a>
                                                @endif
                                                @if($applicant->logisticsProfile->bir_2303_path)
                                                    <a href="{{ Storage::url($applicant->logisticsProfile->bir_2303_path) }}" target="_blank"
                                                       class="inline-flex items-center gap-1 text-emerald-600 hover:underline font-semibold text-[11px]">
                                                        <span class="material-symbols-outlined text-[15px]">receipt_long</span>
                                                        <span>BIR Form 2303</span>
                                                    </a>
                                                @endif
                                                @if($applicant->logisticsProfile->mayors_permit_path)
                                                    <a href="{{ Storage::url($applicant->logisticsProfile->mayors_permit_path) }}" target="_blank"
                                                       class="inline-flex items-center gap-1 text-secondary hover:underline font-semibold text-[11px]">
                                                        <span class="material-symbols-outlined text-[15px]">description</span>
                                                        <span>Mayor's Permit</span>
                                                    </a>
                                                @endif
                                                @if($applicant->logisticsProfile->valid_id_path || $applicant->valid_id_path)
                                                    <a href="{{ Storage::url($applicant->logisticsProfile->valid_id_path ?? $applicant->valid_id_path) }}" target="_blank"
                                                       class="inline-flex items-center gap-1 text-amber-700 hover:underline font-semibold text-[11px]">
                                                        <span class="material-symbols-outlined text-[15px]">badge</span>
                                                        <span>Signatory ID</span>
                                                    </a>
                                                @endif
                                            @else
                                                @if($applicant->valid_id_path)
                                                    <a href="{{ Storage::url($applicant->valid_id_path) }}" target="_blank"
                                                       class="inline-flex items-center gap-1 text-primary hover:underline font-semibold text-[11px]">
                                                        <span class="material-symbols-outlined text-[15px]">badge</span>
                                                        <span>Government ID</span>
                                                    </a>
                                                @endif
                                                @if($applicant->sellerProfile?->business_permit_path)
                                                    <a href="{{ Storage::url($applicant->sellerProfile->business_permit_path) }}" target="_blank"
                                                       class="inline-flex items-center gap-1 text-secondary hover:underline font-semibold text-[11px]">
                                                        <span class="material-symbols-outlined text-[15px]">description</span>
                                                        <span>Mayor's / DTI Permit</span>
                                                    </a>
                                                @endif
                                                @if($applicant->sortingCenterProfile?->business_permit_path)
                                                    <a href="{{ Storage::url($applicant->sortingCenterProfile->business_permit_path) }}" target="_blank"
                                                       class="inline-flex items-center gap-1 text-secondary hover:underline font-semibold text-[11px]">
                                                        <span class="material-symbols-outlined text-[15px]">description</span>
                                                        <span>Hub DTI Permit</span>
                                                    </a>
                                                @endif
                                                @if(!$applicant->valid_id_path && !$applicant->sellerProfile?->business_permit_path && !$applicant->sortingCenterProfile?->business_permit_path)
                                                    <span class="text-[11px] text-outline">None uploaded</span>
                                                @endif
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-5 py-4">
                                        @if($applicant->approval_status === 'approved')
                                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-700">Approved</span>
                                        @elseif($applicant->approval_status === 'disapproved')
                                            <div>
                                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-500/10 text-rose-700">Disapproved</span>
                                                @if($applicant->disapproval_reason)
                                                    <span class="text-[10px] text-outline block mt-0.5 truncate max-w-[150px]" title="{{ $applicant->disapproval_reason }}">
                                                        {{ $applicant->disapproval_reason }}
                                                    </span>
                                                @endif
                                            </div>
                                        @else
                                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-700">Pending Review</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            @if($applicant->approval_status !== 'approved')
                                                <form method="POST" action="{{ route('admin.applications.approve', $applicant) }}"
                                                      onsubmit="return confirm('Approve account application for {{ addslashes($applicant->full_name) }}?');">
                                                    @csrf
                                                    <button type="submit" class="px-3 py-1.5 rounded-full bg-primary text-on-primary font-label-md text-xs font-semibold hover:bg-primary/90 transition-all">
                                                        Approve
                                                    </button>
                                                </form>
                                            @endif

                                            @if($applicant->approval_status !== 'disapproved')
                                                <button type="button"
                                                        @click="openDeclineModal({{ $applicant->id }}, '{{ addslashes($applicant->full_name) }}')"
                                                        class="px-3 py-1.5 rounded-full border border-outline-variant text-on-surface-variant font-label-md text-xs font-semibold hover:bg-surface-container transition-all">
                                                    Decline
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                @if($applications->hasPages())
                    <div class="px-6 py-4 border-t border-outline-variant bg-surface-container-low">
                        {{ $applications->links() }}
                    </div>
                @endif
            @endif
        </div>

    </div>

    {{-- Decline Modal --}}
    <div x-show="declineModalOpen" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs"
         @keydown.escape.window="declineModalOpen = false">
        <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant max-w-md w-full p-6 shadow-xl"
             @click.outside="declineModalOpen = false">
            <h3 class="font-headline-sm text-lg font-bold text-on-surface">Decline Application</h3>
            <p class="font-body-sm text-xs text-on-surface-variant mt-1">
                State the reason for declining <strong class="text-on-surface" x-text="applicantName"></strong>. The applicant will be notified via email.
            </p>

            <form :action="'/admin/applications/' + applicantId + '/disapprove'" method="POST" class="mt-4">
                @csrf
                <div class="mb-4">
                    <label class="block text-xs font-semibold text-on-surface mb-1">Reason for Rejection</label>
                    <textarea name="reason" rows="3" required
                              placeholder="e.g. Invalid government ID provided, business permit unverified, blurred documents..."
                              class="w-full px-3 py-2 bg-surface-container rounded-xl text-xs text-on-surface border border-outline-variant focus:outline-none focus:border-primary"></textarea>
                </div>

                <div class="flex items-center justify-end gap-2">
                    <button type="button" @click="declineModalOpen = false"
                            class="px-4 py-2 rounded-xl border border-outline-variant text-xs font-semibold text-on-surface hover:bg-surface-container">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 rounded-xl bg-error text-on-error text-xs font-semibold hover:bg-error/90">
                        Confirm Rejection
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
