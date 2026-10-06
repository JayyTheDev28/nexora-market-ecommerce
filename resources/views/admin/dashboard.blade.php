@extends('layouts.app')

@section('title', 'Admin Dashboard — Nexora Market')

@section('content')
    <x-dashboard-placeholder
        title="Administrator"
        icon="admin_panel_settings"
        accent="bg-inverse-surface"
        :features="[
            'Manage user accounts — activate, suspend, deactivate',
            'Monitor seller compliance',
            'Manage complaints and disputes',
            'Manage commission (10%)',
            'Generate sales and commission reports',
            'Manage platform settings and announcements',
        ]" />

    {{-- Account approvals is built for real here, since without it there'd be
         no way to approve registrations except the artisan command. --}}
    <div class="w-full bg-surface pb-12">
        <div class="max-w-none px-margin-mobile md:px-margin-desktop">

            @if (session('status'))
                <div class="mb-4 bg-primary/10 border border-primary/30 rounded-2xl p-4">
                    <p class="font-body-sm text-body-sm text-primary">{{ session('status') }}</p>
                </div>
            @endif

            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant overflow-hidden">
                <div class="px-6 py-4 border-b border-outline-variant bg-surface-container-low flex items-center justify-between">
                    <h2 class="font-headline-sm text-headline-sm text-on-surface">Pending Applications</h2>
                    <span class="px-2.5 py-1 rounded-full bg-primary text-on-primary text-[11px] font-bold">{{ $pending->count() }}</span>
                </div>

                @if($pending->isEmpty())
                    <div class="p-10 text-center flex flex-col items-center gap-2">
                        <span class="material-symbols-outlined text-[32px] text-outline">inbox</span>
                        <p class="font-body-sm text-body-sm text-on-surface-variant">No applications waiting for review.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b border-outline-variant">
                                    <th class="text-left px-6 py-3 font-label-md text-label-md text-on-surface-variant">Applicant</th>
                                    <th class="text-left px-6 py-3 font-label-md text-label-md text-on-surface-variant">Role</th>
                                    <th class="text-left px-6 py-3 font-label-md text-label-md text-on-surface-variant">Email</th>
                                    <th class="text-left px-6 py-3 font-label-md text-label-md text-on-surface-variant">Verified</th>
                                    <th class="text-left px-6 py-3 font-label-md text-label-md text-on-surface-variant">Documents</th>
                                    <th class="text-right px-6 py-3 font-label-md text-label-md text-on-surface-variant">Decision</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($pending as $applicant)
                                    <tr class="border-b border-outline-variant last:border-b-0">
                                        <td class="px-6 py-4 font-body-sm text-body-sm text-on-surface">
                                            {{ $applicant->full_name }}
                                            @if($applicant->sellerProfile)
                                                <span class="block text-on-surface-variant">{{ $applicant->sellerProfile->business_name }}</span>
                                            @elseif($applicant->sortingCenterProfile)
                                                <span class="block text-on-surface-variant">{{ $applicant->sortingCenterProfile->business_name }}</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 font-body-sm text-body-sm text-on-surface-variant">{{ $applicant->roleLabel() }}</td>
                                        <td class="px-6 py-4 font-body-sm text-body-sm text-on-surface-variant break-all">{{ $applicant->email }}</td>
                                        <td class="px-6 py-4">
                                            @if($applicant->hasVerifiedEmail())
                                                <span class="inline-flex items-center gap-1 font-label-sm text-label-sm text-primary">
                                                    <span class="material-symbols-outlined text-[14px]" style="font-variation-settings: 'FILL' 1">check_circle</span> Yes
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 font-label-sm text-label-sm text-tertiary">
                                                    <span class="material-symbols-outlined text-[14px]">schedule</span> Not yet
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 font-body-sm text-body-sm">
                                            <div class="flex flex-col gap-1">
                                                @if($applicant->valid_id_path)
                                                    <a href="{{ Storage::url($applicant->valid_id_path) }}" target="_blank" class="text-primary hover:underline">Valid ID</a>
                                                @endif
                                                @if($applicant->sellerProfile?->business_permit_path)
                                                    <a href="{{ Storage::url($applicant->sellerProfile->business_permit_path) }}" target="_blank" class="text-primary hover:underline">Business Permit</a>
                                                @endif
                                                @if($applicant->sortingCenterProfile?->business_permit_path)
                                                    <a href="{{ Storage::url($applicant->sortingCenterProfile->business_permit_path) }}" target="_blank" class="text-primary hover:underline">DTI Permit</a>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center justify-end gap-2">
                                                <form method="POST" action="{{ route('admin.applications.approve', $applicant) }}">
                                                    @csrf
                                                    <button type="submit" class="inline-flex items-center gap-1 bg-primary text-on-primary font-label-md text-label-md px-4 py-2 rounded-full hover:bg-primary/90 transition-colors">
                                                        Approve
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('admin.applications.disapprove', $applicant) }}"
                                                      onsubmit="return confirm('Disapprove this application? The applicant will not be able to log in.');">
                                                    @csrf
                                                    <input type="hidden" name="reason" value="Application did not meet our requirements.">
                                                    <button type="submit" class="inline-flex items-center gap-1 border border-outline-variant text-on-surface-variant font-label-md text-label-md px-4 py-2 rounded-full hover:bg-surface-container transition-colors">
                                                        Decline
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
