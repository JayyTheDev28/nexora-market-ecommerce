@extends('layouts.app')

@section('title', "User Profile: {$user->full_name} — Admin Portal")

@section('content')
<div class="w-full bg-surface py-8 min-h-[75vh]" x-data="{
    statusModalOpen: false,
    newStatus: '{{ $user->account_status }}',
    statusReason: '',
    openStatusModal() {
        this.newStatus = '{{ $user->account_status }}';
        this.statusReason = '{{ addslashes($user->status_reason ?? '') }}';
        this.statusModalOpen = true;
    }
}">
    <div class="max-w-none px-margin-mobile md:px-margin-desktop">

        <x-admin-nav active="users" />

        {{-- Flash messages --}}
        @if (session('status'))
            <div class="mb-6 bg-primary/10 border border-primary/30 rounded-2xl p-4 flex items-center justify-between">
                <div class="flex items-center gap-2 text-primary font-body-sm text-sm">
                    <span class="material-symbols-outlined text-[20px]">check_circle</span>
                    <span>{{ session('status') }}</span>
                </div>
            </div>
        @endif

        {{-- Back button & User Identity Header --}}
        <div class="mb-6">
            <a href="{{ route('admin.users.index') }}" class="inline-flex items-center gap-1.5 text-xs text-on-surface-variant hover:text-primary transition-colors mb-3">
                <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                <span>Back to User Accounts</span>
            </a>

            <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 shadow-xs flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
                <div class="flex items-center gap-4">
                    <div class="w-16 h-16 rounded-2xl bg-primary/10 border border-primary/20 text-primary font-bold text-2xl flex items-center justify-center flex-shrink-0">
                        {{ strtoupper(substr($user->first_name, 0, 1)) }}
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="font-headline-lg text-2xl font-bold text-on-surface">{{ $user->full_name }}</h1>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-surface-container-high text-on-surface">
                                {{ $user->roleLabel() }}
                            </span>
                            @if($user->account_status === 'active')
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-500/10 text-emerald-700">Active</span>
                            @elseif($user->account_status === 'suspended')
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-500/10 text-amber-700">Suspended</span>
                            @else
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-500/10 text-rose-700">Deactivated</span>
                            @endif
                        </div>
                        <p class="font-body-sm text-xs text-on-surface-variant mt-1">
                            User ID: #{{ $user->id }} · Member since {{ $user->created_at->format('F d, Y') }} ({{ $user->created_at->diffForHumans() }})
                        </p>
                    </div>
                </div>

                @if($user->id !== auth()->id())
                    <button type="button" @click="openStatusModal()"
                            class="px-4 py-2.5 rounded-2xl bg-primary text-on-primary font-label-md text-xs font-semibold shadow-xs hover:bg-primary/90 transition-all flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[18px]">manage_accounts</span>
                        <span>Manage Status</span>
                    </button>
                @endif
            </div>
        </div>

        {{-- Grid: Details & Metrics --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">

            {{-- Left column: Profile info & documents --}}
            <div class="space-y-6">
                {{-- Contact & Personal Info --}}
                <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 shadow-xs">
                    <h2 class="font-headline-sm text-sm font-bold text-on-surface mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px] text-primary">person</span>
                        <span>Personal & Contact Info</span>
                    </h2>

                    <dl class="space-y-3 text-xs">
                        <div>
                            <dt class="text-on-surface-variant">Email Address</dt>
                            <dd class="font-semibold text-on-surface mt-0.5 flex items-center gap-1.5">
                                <span>{{ $user->email }}</span>
                                @if($user->hasVerifiedEmail())
                                    <span class="text-emerald-600 material-symbols-outlined text-[15px]" title="Verified">verified</span>
                                @else
                                    <span class="text-amber-600 material-symbols-outlined text-[15px]" title="Unverified">pending</span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-on-surface-variant">Mobile Contact</dt>
                            <dd class="font-semibold text-on-surface mt-0.5">{{ $user->contact_number }}</dd>
                        </div>
                        <div>
                            <dt class="text-on-surface-variant">Birthday & Age</dt>
                            <dd class="font-semibold text-on-surface mt-0.5">
                                {{ $user->birthday ? $user->birthday->format('M d, Y') : 'N/A' }} ({{ $user->age ?? 'N/A' }} years old)
                            </dd>
                        </div>
                        <div>
                            <dt class="text-on-surface-variant">Approval Standing</dt>
                            <dd class="font-semibold text-on-surface mt-0.5 capitalize">
                                {{ $user->approval_status }}
                                @if($user->disapproval_reason)
                                    <span class="text-[11px] text-outline block mt-0.5">Reason: {{ $user->disapproval_reason }}</span>
                                @endif
                            </dd>
                        </div>
                        @if($user->status_reason)
                            <div>
                                <dt class="text-on-surface-variant">Account Restriction Reason</dt>
                                <dd class="font-medium text-amber-700 bg-amber-500/10 p-2 rounded-xl mt-0.5">
                                    {{ $user->status_reason }}
                                </dd>
                            </div>
                        @endif
                    </dl>
                </div>

                {{-- Registered Address --}}
                <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 shadow-xs">
                    <h2 class="font-headline-sm text-sm font-bold text-on-surface mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px] text-primary">location_on</span>
                        <span>Registered Address</span>
                    </h2>

                    @if($user->address)
                        <div class="text-xs space-y-1">
                            <p class="font-semibold text-on-surface">{{ $user->address->full_address }}</p>
                            <p class="text-on-surface-variant">
                                {{ $user->address->city_municipality }}, {{ $user->address->province }}
                            </p>
                            <p class="text-on-surface-variant">
                                Region {{ $user->address->region }} · {{ $user->address->postal_code }}
                            </p>
                        </div>
                    @else
                        <p class="text-xs text-outline">No primary address registered.</p>
                    @endif
                </div>

                {{-- Uploaded Verification Documents --}}
                <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 shadow-xs">
                    <h2 class="font-headline-sm text-sm font-bold text-on-surface mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px] text-primary">folder</span>
                        <span>Verification Documents</span>
                    </h2>

                    <div class="space-y-2">
                        @if($user->valid_id_path)
                            <a href="{{ Storage::url($user->valid_id_path) }}" target="_blank"
                               class="flex items-center justify-between p-3 rounded-2xl border border-outline-variant hover:bg-surface-container transition-colors text-xs">
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-outlined text-primary text-[18px]">badge</span>
                                    <span class="font-semibold text-on-surface">Government ID</span>
                                </div>
                                <span class="material-symbols-outlined text-[16px] text-outline">open_in_new</span>
                            </a>
                        @endif

                        @if($user->sellerProfile?->business_permit_path)
                            <a href="{{ Storage::url($user->sellerProfile->business_permit_path) }}" target="_blank"
                               class="flex items-center justify-between p-3 rounded-2xl border border-outline-variant hover:bg-surface-container transition-colors text-xs">
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-outlined text-secondary text-[18px]">description</span>
                                    <span class="font-semibold text-on-surface">Seller Business Permit</span>
                                </div>
                                <span class="material-symbols-outlined text-[16px] text-outline">open_in_new</span>
                            </a>
                        @endif

                        @if($user->sortingCenterProfile?->business_permit_path)
                            <a href="{{ Storage::url($user->sortingCenterProfile->business_permit_path) }}" target="_blank"
                               class="flex items-center justify-between p-3 rounded-2xl border border-outline-variant hover:bg-surface-container transition-colors text-xs">
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-outlined text-secondary text-[18px]">description</span>
                                    <span class="font-semibold text-on-surface">Logistics Hub DTI Permit</span>
                                </div>
                                <span class="material-symbols-outlined text-[16px] text-outline">open_in_new</span>
                            </a>
                        @endif

                        @if(!$user->valid_id_path && !$user->sellerProfile?->business_permit_path && !$user->sortingCenterProfile?->business_permit_path)
                            <p class="text-xs text-outline">No documents on file.</p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Right columns: Role profile details & audit logs --}}
            <div class="lg:col-span-2 space-y-6">

                {{-- Role-Specific Profile Section --}}
                @if($user->isSeller() && $user->sellerProfile)
                    <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 shadow-xs">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="font-headline-sm text-sm font-bold text-on-surface flex items-center gap-2">
                                <span class="material-symbols-outlined text-[18px] text-primary">store</span>
                                <span>Merchant Details ({{ $user->sellerProfile->business_name }})</span>
                            </h2>
                            <span class="text-xs text-on-surface-variant font-medium">LOB: {{ $user->sellerProfile->line_of_business }}</span>
                        </div>

                        <div class="grid grid-cols-3 gap-3 mb-4">
                            <div class="p-3 rounded-2xl bg-surface-container-low border border-outline-variant">
                                <span class="text-[11px] text-on-surface-variant block">Total Products</span>
                                <span class="font-bold text-lg text-on-surface mt-0.5 block">{{ $metrics['totalProducts'] ?? 0 }}</span>
                            </div>
                            <div class="p-3 rounded-2xl bg-surface-container-low border border-outline-variant">
                                <span class="text-[11px] text-on-surface-variant block">Active Listings</span>
                                <span class="font-bold text-lg text-emerald-600 mt-0.5 block">{{ $metrics['activeProducts'] ?? 0 }}</span>
                            </div>
                            <div class="p-3 rounded-2xl bg-surface-container-low border border-outline-variant">
                                <span class="text-[11px] text-on-surface-variant block">Completed Sales Volume</span>
                                <span class="font-bold text-lg text-primary mt-0.5 block">₱{{ number_format($metrics['totalSales'] ?? 0, 2) }}</span>
                            </div>
                        </div>

                        {{-- Compliance warnings history --}}
                        @if(!empty($warnings) && $warnings->isNotEmpty())
                            <div class="mt-4 pt-4 border-t border-outline-variant">
                                <h3 class="text-xs font-bold text-amber-700 mb-2 flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-[16px]">warning</span>
                                    <span>Issued Compliance Warnings ({{ $warnings->count() }})</span>
                                </h3>
                                <div class="space-y-2">
                                    @foreach($warnings as $warning)
                                        <div class="p-3 rounded-2xl bg-amber-500/5 border border-amber-500/20 text-xs">
                                            <div class="flex items-center justify-between">
                                                <span class="font-bold text-on-surface">{{ ucwords(str_replace('_', ' ', $warning->violation_type)) }}</span>
                                                <span class="text-[10px] text-on-surface-variant">{{ $warning->created_at->format('M d, Y') }}</span>
                                            </div>
                                            <p class="text-on-surface-variant mt-1">{{ $warning->details }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Activity Audit Trail for this user --}}
                <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 shadow-xs">
                    <h2 class="font-headline-sm text-sm font-bold text-on-surface mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px] text-primary">history</span>
                        <span>Account Audit Trail & Activity Log</span>
                    </h2>

                    @if($auditTrail->isEmpty())
                        <p class="text-xs text-outline py-4 text-center">No administrative changes recorded for this account.</p>
                    @else
                        <div class="divide-y divide-outline-variant">
                            @foreach($auditTrail as $log)
                                <div class="py-3 flex items-start justify-between gap-4 text-xs">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="font-bold text-on-surface">{{ ucwords(str_replace('_', ' ', $log->action)) }}</span>
                                            <span class="text-[10px] text-outline font-mono">By {{ $log->operator?->full_name ?? 'System' }}</span>
                                        </div>
                                        <p class="text-on-surface-variant mt-0.5">{{ $log->reason ?? 'Status update' }}</p>
                                    </div>
                                    <span class="text-[10px] text-on-surface-variant whitespace-nowrap">{{ $log->created_at->format('M d, Y h:i A') }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

            </div>
        </div>

    </div>

    {{-- Account Status Toggle Modal --}}
    <div x-show="statusModalOpen" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs"
         @keydown.escape.window="statusModalOpen = false">
        <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant max-w-md w-full p-6 shadow-xl"
             @click.outside="statusModalOpen = false">
            <h3 class="font-headline-sm text-lg font-bold text-on-surface">Manage Account Standing</h3>
            <p class="font-body-sm text-xs text-on-surface-variant mt-1">
                Update access permissions for <strong>{{ $user->full_name }}</strong>.
            </p>

            <form action="{{ route('admin.users.status', $user) }}" method="POST" class="mt-4">
                @csrf

                <div class="space-y-2 mb-4">
                    <label class="flex items-center gap-3 p-3 rounded-2xl border border-outline-variant cursor-pointer hover:bg-surface-container-low transition-colors"
                           :class="newStatus === 'active' ? 'bg-primary/5 border-primary' : ''">
                        <input type="radio" name="status" value="active" x-model="newStatus" class="text-primary focus:ring-primary">
                        <div>
                            <span class="font-bold text-xs text-on-surface block">Active</span>
                            <span class="text-[11px] text-on-surface-variant">Full platform access enabled.</span>
                        </div>
                    </label>

                    <label class="flex items-center gap-3 p-3 rounded-2xl border border-outline-variant cursor-pointer hover:bg-surface-container-low transition-colors"
                           :class="newStatus === 'suspended' ? 'bg-amber-500/5 border-amber-500' : ''">
                        <input type="radio" name="status" value="suspended" x-model="newStatus" class="text-amber-600 focus:ring-amber-500">
                        <div>
                            <span class="font-bold text-xs text-amber-700 block">Suspended</span>
                            <span class="text-[11px] text-on-surface-variant">Blocks login immediately. User sessions evicted.</span>
                        </div>
                    </label>

                    <label class="flex items-center gap-3 p-3 rounded-2xl border border-outline-variant cursor-pointer hover:bg-surface-container-low transition-colors"
                           :class="newStatus === 'deactivated' ? 'bg-error/5 border-error' : ''">
                        <input type="radio" name="status" value="deactivated" x-model="newStatus" class="text-error focus:ring-error">
                        <div>
                            <span class="font-bold text-xs text-error block">Deactivated</span>
                            <span class="text-[11px] text-on-surface-variant">Permanently close or terminate account access.</span>
                        </div>
                    </label>
                </div>

                <div class="mb-4" x-show="newStatus !== 'active'">
                    <label class="block text-xs font-semibold text-on-surface mb-1">Reason for Status Change <span class="text-error">*</span></label>
                    <textarea name="reason" rows="3" x-model="statusReason" :required="newStatus !== 'active'"
                              placeholder="Explain why this account is being suspended or deactivated..."
                              class="w-full px-3 py-2 bg-surface-container rounded-xl text-xs text-on-surface border border-outline-variant focus:outline-none focus:border-primary"></textarea>
                </div>

                <div class="flex items-center justify-end gap-2">
                    <button type="button" @click="statusModalOpen = false"
                            class="px-4 py-2 rounded-xl border border-outline-variant text-xs font-semibold text-on-surface hover:bg-surface-container">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 rounded-xl bg-primary text-on-primary text-xs font-semibold hover:bg-primary/90">
                        Save Status
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
