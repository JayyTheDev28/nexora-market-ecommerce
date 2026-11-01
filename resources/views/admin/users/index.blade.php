@extends('layouts.app')

@section('title', 'User Accounts — Admin Portal — Nexora Market')

@section('content')
<div class="w-full min-h-screen bg-surface" x-data="{
    statusModalOpen: false,
    selectedUserId: null,
    selectedUserName: '',
    currentStatus: 'active',
    newStatus: 'active',
    statusReason: '',
    openStatusModal(id, name, status) {
        this.selectedUserId = id;
        this.selectedUserName = name;
        this.currentStatus = status;
        this.newStatus = status;
        this.statusReason = '';
        this.statusModalOpen = true;
    }
}">
    <div class="flex flex-col lg:flex-row w-full">

            <x-admin-nav active="users" />

            <div class="flex-1 min-w-0 p-4 sm:p-6 lg:p-8 bg-surface">
            <div class="w-full max-w-7xl mx-auto space-y-6">

        {{-- Flash messages --}}
        @if (session('status'))
            <div class="mb-6 bg-primary/10 border border-primary/30 rounded-2xl p-4 flex items-center justify-between">
                <div class="flex items-center gap-2 text-primary font-body-sm text-sm">
                    <span class="material-symbols-outlined text-[20px]">check_circle</span>
                    <span>{{ session('status') }}</span>
                </div>
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 bg-error/10 border border-error/30 rounded-2xl p-4">
                <ul class="list-disc list-inside text-xs text-error font-medium">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Page Header --}}
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="font-headline-lg text-2xl font-bold text-on-surface">User Accounts Management</h1>
                <p class="font-body-md text-sm text-on-surface-variant">View, monitor, and regulate accounts across all roles (Buyers, Sellers, Logistics Hubs, Riders, Admins).</p>
            </div>
        </div>

        {{-- Account Status Metric Cards --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4 shadow-xs">
                <span class="text-xs text-on-surface-variant font-medium block">Total Registered Accounts</span>
                <div class="font-headline-md text-2xl font-bold text-on-surface mt-1">{{ number_format($stats['total']) }}</div>
                <span class="text-[11px] text-on-surface-variant mt-0.5 block">All platform roles</span>
            </div>

            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4 shadow-xs">
                <span class="text-xs text-on-surface-variant font-medium block">Active Accounts</span>
                <div class="font-headline-md text-2xl font-bold text-emerald-600 mt-1">{{ number_format($stats['active']) }}</div>
                <span class="text-[11px] text-emerald-600 font-medium mt-0.5 block">In good standing</span>
            </div>

            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4 shadow-xs">
                <span class="text-xs text-on-surface-variant font-medium block">Suspended Accounts</span>
                <div class="font-headline-md text-2xl font-bold text-amber-600 mt-1">{{ number_format($stats['suspended']) }}</div>
                <span class="text-[11px] text-amber-600 font-medium mt-0.5 block">Restricted from access</span>
            </div>

            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4 shadow-xs">
                <span class="text-xs text-on-surface-variant font-medium block">Deactivated Accounts</span>
                <div class="font-headline-md text-2xl font-bold text-rose-600 mt-1">{{ number_format($stats['deactivated']) }}</div>
                <span class="text-[11px] text-on-surface-variant mt-0.5 block">Closed accounts</span>
            </div>
        </div>

        {{-- Filters & Search Row --}}
        <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4 mb-6 shadow-xs">
            <form method="GET" action="{{ route('admin.users.index') }}" class="flex flex-col md:flex-row items-center gap-3">
                <div class="relative flex-1 w-full">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-[18px]">search</span>
                    <input type="text" name="q" value="{{ $search }}"
                           placeholder="Search by name, email, phone, or merchant store..."
                           class="w-full pl-9 pr-4 py-2 bg-surface-container rounded-xl text-xs text-on-surface placeholder:text-outline border border-outline-variant focus:outline-none focus:border-primary">
                </div>

                <div class="w-full md:w-40">
                    <select name="role" class="w-full px-3 py-2 bg-surface-container rounded-xl text-xs text-on-surface border border-outline-variant focus:outline-none focus:border-primary">
                        <option value="">All Roles</option>
                        <option value="buyer" {{ $role === 'buyer' ? 'selected' : '' }}>Buyer</option>
                        <option value="seller" {{ $role === 'seller' ? 'selected' : '' }}>Seller</option>
                        <option value="sorting_center" {{ $role === 'sorting_center' ? 'selected' : '' }}>Logistics Hub</option>
                        <option value="courier" {{ $role === 'courier' ? 'selected' : '' }}>Rider / Courier</option>
                        <option value="admin" {{ $role === 'admin' ? 'selected' : '' }}>Administrator</option>
                    </select>
                </div>

                <div class="w-full md:w-40">
                    <select name="status" class="w-full px-3 py-2 bg-surface-container rounded-xl text-xs text-on-surface border border-outline-variant focus:outline-none focus:border-primary">
                        <option value="">All Statuses</option>
                        <option value="active" {{ $status === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="suspended" {{ $status === 'suspended' ? 'selected' : '' }}>Suspended</option>
                        <option value="deactivated" {{ $status === 'deactivated' ? 'selected' : '' }}>Deactivated</option>
                    </select>
                </div>

                <div class="w-full md:w-40">
                    <select name="approval" class="w-full px-3 py-2 bg-surface-container rounded-xl text-xs text-on-surface border border-outline-variant focus:outline-none focus:border-primary">
                        <option value="">Approval Status</option>
                        <option value="approved" {{ $approval === 'approved' ? 'selected' : '' }}>Approved</option>
                        <option value="pending" {{ $approval === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="disapproved" {{ $approval === 'disapproved' ? 'selected' : '' }}>Disapproved</option>
                    </select>
                </div>

                <div class="flex items-center gap-2 w-full md:w-auto">
                    <button type="submit" class="w-full md:w-auto px-4 py-2 rounded-xl bg-primary text-on-primary font-label-md text-xs font-semibold shadow-xs hover:bg-primary/90 transition-all">
                        Filter
                    </button>
                    @if($search || $role || $status || $approval)
                        <a href="{{ route('admin.users.index') }}" class="w-full md:w-auto px-3 py-2 rounded-xl border border-outline-variant text-on-surface-variant text-xs font-semibold hover:bg-surface-container transition-all text-center">
                            Reset
                        </a>
                    @endif
                </div>
            </form>
        </div>

        {{-- Users Table --}}
        <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant overflow-hidden shadow-xs">
            @if($users->isEmpty())
                <div class="p-12 text-center flex flex-col items-center justify-center">
                    <span class="material-symbols-outlined text-[48px] text-outline mb-3">group_off</span>
                    <h3 class="font-headline-sm text-base font-bold text-on-surface">No user accounts found</h3>
                    <p class="font-body-sm text-xs text-on-surface-variant mt-1">Try adjusting your filters or search query.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-surface-container-low border-b border-outline-variant text-on-surface-variant font-semibold">
                            <tr>
                                <th class="px-5 py-3.5">User</th>
                                <th class="px-5 py-3.5">Role</th>
                                <th class="px-5 py-3.5">Email / Phone</th>
                                <th class="px-5 py-3.5">Account Status</th>
                                <th class="px-5 py-3.5">Approval Status</th>
                                <th class="px-5 py-3.5">Joined Date</th>
                                <th class="px-5 py-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant">
                            @foreach($users as $user)
                                <tr class="hover:bg-surface-container-low/50 transition-colors">
                                    <td class="px-5 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-9 h-9 rounded-full bg-primary/10 text-primary font-bold text-xs flex items-center justify-center flex-shrink-0">
                                                {{ strtoupper(substr($user->first_name, 0, 1)) }}
                                            </div>
                                            <div>
                                                <a href="{{ route('admin.users.show', $user) }}" class="font-bold text-on-surface hover:text-primary transition-colors block">
                                                    {{ $user->full_name }}
                                                </a>
                                                @if($user->sellerProfile)
                                                    <span class="text-[10px] text-primary block">Store: {{ $user->sellerProfile->business_name }}</span>
                                                @elseif($user->sortingCenterProfile)
                                                    <span class="text-[10px] text-primary block">Hub: {{ $user->sortingCenterProfile->business_name }}</span>
                                                @elseif($user->courierProfile)
                                                    <span class="text-[10px] text-secondary block">Plate: {{ $user->courierProfile->plate_number ?? 'Pending vehicle' }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-surface-container-high text-on-surface">
                                            {{ $user->roleLabel() }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="font-medium text-on-surface block">{{ $user->email }}</span>
                                        <span class="text-[11px] text-on-surface-variant block">{{ $user->contact_number }}</span>
                                    </td>
                                    <td class="px-5 py-4">
                                        @if($user->account_status === 'active')
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-700">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span> Active
                                            </span>
                                        @elseif($user->account_status === 'suspended')
                                            <div>
                                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-700">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-600"></span> Suspended
                                                </span>
                                                @if($user->status_reason)
                                                    <span class="text-[10px] text-outline block mt-0.5 truncate max-w-[140px]" title="{{ $user->status_reason }}">
                                                        {{ $user->status_reason }}
                                                    </span>
                                                @endif
                                            </div>
                                        @elseif($user->account_status === 'deactivated')
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-500/10 text-rose-700">
                                                <span class="w-1.5 h-1.5 rounded-full bg-rose-600"></span> Deactivated
                                            </span>
                                        @else
                                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-surface-container-high text-on-surface">
                                                {{ ucfirst($user->account_status ?? 'Active') }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4">
                                        @if($user->approval_status === 'approved')
                                            <span class="text-[11px] font-semibold text-emerald-600">Approved</span>
                                        @elseif($user->approval_status === 'disapproved')
                                            <span class="text-[11px] font-semibold text-rose-600">Disapproved</span>
                                        @else
                                            <span class="text-[11px] font-semibold text-amber-600">Pending</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 text-on-surface-variant text-[11px]">
                                        {{ $user->created_at->format('M d, Y') }}
                                    </td>
                                    <td class="px-5 py-4 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ route('admin.users.show', $user) }}"
                                               class="px-3 py-1.5 rounded-full bg-surface-container-high text-on-surface hover:bg-surface-container-highest font-label-md text-xs font-semibold transition-all">
                                                Profile
                                            </a>

                                            @if($user->id !== auth()->id())
                                                <button type="button"
                                                        @click="openStatusModal({{ $user->id }}, '{{ addslashes($user->full_name) }}', '{{ $user->account_status }}')"
                                                        class="px-3 py-1.5 rounded-full border border-outline-variant text-on-surface-variant font-label-md text-xs font-semibold hover:bg-surface-container transition-all">
                                                    Status
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
                @if($users->hasPages())
                    <div class="px-6 py-4 border-t border-outline-variant bg-surface-container-low">
                        {{ $users->links() }}
                    </div>
                @endif
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
            <h3 class="font-headline-sm text-lg font-bold text-on-surface">Manage Account Access</h3>
            <p class="font-body-sm text-xs text-on-surface-variant mt-1">
                Change account standing for <strong class="text-on-surface" x-text="selectedUserName"></strong>.
            </p>

            <form :action="'/admin/users/' + selectedUserId + '/status'" method="POST" class="mt-4">
                @csrf

                <div class="space-y-2 mb-4">
                    <label class="flex items-center gap-3 p-3 rounded-2xl border border-outline-variant cursor-pointer hover:bg-surface-container-low transition-colors"
                           :class="newStatus === 'active' ? 'bg-primary/5 border-primary' : ''">
                        <input type="radio" name="status" value="active" x-model="newStatus" class="text-primary focus:ring-primary">
                        <div>
                            <span class="font-bold text-xs text-on-surface block">Activate Account</span>
                            <span class="text-[11px] text-on-surface-variant">Full access to platform services, buying/selling/delivery operations.</span>
                        </div>
                    </label>

                    <label class="flex items-center gap-3 p-3 rounded-2xl border border-outline-variant cursor-pointer hover:bg-surface-container-low transition-colors"
                           :class="newStatus === 'suspended' ? 'bg-amber-500/5 border-amber-500' : ''">
                        <input type="radio" name="status" value="suspended" x-model="newStatus" class="text-amber-600 focus:ring-amber-500">
                        <div>
                            <span class="font-bold text-xs text-amber-700 block">Suspend Account</span>
                            <span class="text-[11px] text-on-surface-variant">Temporarily blocks login and ongoing operations. Active sessions evicted immediately.</span>
                        </div>
                    </label>

                    <label class="flex items-center gap-3 p-3 rounded-2xl border border-outline-variant cursor-pointer hover:bg-surface-container-low transition-colors"
                           :class="newStatus === 'deactivated' ? 'bg-error/5 border-error' : ''">
                        <input type="radio" name="status" value="deactivated" x-model="newStatus" class="text-error focus:ring-error">
                        <div>
                            <span class="font-bold text-xs text-error block">Deactivate Account</span>
                            <span class="text-[11px] text-on-surface-variant">Permanently close or terminate access.</span>
                        </div>
                    </label>
                </div>

                <div class="mb-4" x-show="newStatus !== 'active'">
                    <label class="block text-xs font-semibold text-on-surface mb-1">Reason for Status Change <span class="text-error">*</span></label>
                    <textarea name="reason" rows="3" x-model="statusReason" :required="newStatus !== 'active'"
                              placeholder="Explain why this account is being suspended or deactivated (will be logged in audit trail and emailed to user)..."
                              class="w-full px-3 py-2 bg-surface-container rounded-xl text-xs text-on-surface border border-outline-variant focus:outline-none focus:border-primary"></textarea>
                </div>

                <div class="flex items-center justify-end gap-2">
                    <button type="button" @click="statusModalOpen = false"
                            class="px-4 py-2 rounded-xl border border-outline-variant text-xs font-semibold text-on-surface hover:bg-surface-container">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 rounded-xl bg-primary text-on-primary text-xs font-semibold hover:bg-primary/90">
                        Apply Status Update
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
