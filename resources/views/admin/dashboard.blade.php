@extends('layouts.app')

@section('title', 'Admin Dashboard — Nexora Market')

@section('content')
<div class="w-full min-h-screen bg-surface">
    <div class="flex flex-col lg:flex-row w-full">

            <x-admin-nav active="dashboard" />

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

        {{-- Executive Welcome & Quick Actions Header --}}
        <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 mb-8 shadow-xs">
            <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-6">
                <div>
                    <div class="flex items-center gap-2 text-primary font-bold text-xs uppercase tracking-wider mb-1">
                        <span class="material-symbols-outlined text-[18px]">security</span>
                        <span>Platform Administration & Operations</span>
                    </div>
                    <h1 class="font-headline-lg text-2xl md:text-3xl font-bold text-on-surface">
                        Welcome, {{ auth()->user()->full_name }}
                    </h1>
                    <div class="flex flex-wrap items-center gap-4 mt-2 font-body-sm text-xs text-on-surface-variant">
                        <div class="flex items-center gap-1">
                            <span class="material-symbols-outlined text-[15px]">badge</span>
                            <span>System Administrator</span>
                        </div>
                        <div class="flex items-center gap-1">
                            <span class="material-symbols-outlined text-[15px]">schedule</span>
                            <span>Server Time: {{ now()->format('M d, Y h:i A') }}</span>
                        </div>
                        @if ($stats['pendingApplications'] > 0)
                            <a href="{{ route('admin.applications.index', ['status' => 'pending']) }}" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-amber-500/10 text-amber-700 font-semibold hover:bg-amber-500/20 transition-colors">
                                <span class="material-symbols-outlined text-[14px]">notification_important</span>
                                <span>{{ $stats['pendingApplications'] }} Application(s) Pending Review</span>
                            </a>
                        @endif
                        @if ($stats['activeDisputes'] > 0)
                            <a href="{{ route('admin.disputes.index', ['status' => 'open']) }}" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-rose-500/10 text-rose-700 font-semibold hover:bg-rose-500/20 transition-colors">
                                <span class="material-symbols-outlined text-[14px]">gavel</span>
                                <span>{{ $stats['activeDisputes'] }} Active Dispute(s)</span>
                            </a>
                        @endif
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2 w-full lg:w-auto">
                    <a href="{{ route('admin.applications.index') }}" class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-2xl bg-primary text-on-primary font-label-md text-xs font-semibold shadow-xs hover:bg-primary/90 transition-all">
                        <span class="material-symbols-outlined text-[18px]">how_to_reg</span>
                        <span>Review Applications</span>
                    </a>
                    <a href="{{ route('admin.disputes.index') }}" class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-2xl bg-secondary text-on-secondary font-label-md text-xs font-semibold shadow-xs hover:bg-secondary/90 transition-all">
                        <span class="material-symbols-outlined text-[18px]">report_problem</span>
                        <span>Manage Disputes</span>
                    </a>
                    <a href="{{ route('admin.commissions.index') }}" class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-2xl bg-surface-container-high text-on-surface font-label-md text-xs font-semibold hover:bg-surface-container-highest transition-all">
                        <span class="material-symbols-outlined text-[18px]">payments</span>
                        <span>Commissions</span>
                    </a>
                </div>
            </div>
        </div>

        {{-- Core Platform KPI Metric Tiles --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-4 mb-8">
            {{-- 1. GMV --}}
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4 shadow-xs">
                <span class="text-xs text-on-surface-variant font-medium block">Platform GMV</span>
                <div class="font-headline-md text-xl md:text-2xl font-bold text-on-surface mt-1">
                    ₱{{ number_format($stats['totalGmv'], 2) }}
                </div>
                <span class="text-[11px] text-emerald-600 font-medium mt-1 flex items-center gap-0.5">
                    <span class="material-symbols-outlined text-[13px]">trending_up</span> Gross volume
                </span>
            </div>

            {{-- 2. Total Commission --}}
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4 shadow-xs">
                <span class="text-xs text-on-surface-variant font-medium block">Platform Commission</span>
                <div class="font-headline-md text-xl md:text-2xl font-bold text-primary mt-1">
                    ₱{{ number_format($stats['totalCommission'], 2) }}
                </div>
                <span class="text-[11px] text-on-surface-variant mt-1 block">
                    10% standard take-rate
                </span>
            </div>

            {{-- 3. Settled vs Pending Commission --}}
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4 shadow-xs">
                <span class="text-xs text-on-surface-variant font-medium block">Pending Settlement</span>
                <div class="font-headline-md text-xl md:text-2xl font-bold text-amber-600 mt-1">
                    ₱{{ number_format($stats['pendingCommission'], 2) }}
                </div>
                <span class="text-[11px] text-on-surface-variant mt-1 block">
                    Settled: ₱{{ number_format($stats['settledCommission'], 2) }}
                </span>
            </div>

            {{-- 4. Orders --}}
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4 shadow-xs">
                <span class="text-xs text-on-surface-variant font-medium block">Total Orders</span>
                <div class="font-headline-md text-xl md:text-2xl font-bold text-on-surface mt-1">
                    {{ number_format($stats['totalOrders']) }}
                </div>
                <span class="text-[11px] text-emerald-600 font-medium mt-1 block">
                    {{ number_format($stats['completedOrders']) }} completed
                </span>
            </div>

            {{-- 5. Active Users --}}
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4 shadow-xs">
                <span class="text-xs text-on-surface-variant font-medium block">Platform Merchants</span>
                <div class="font-headline-md text-xl md:text-2xl font-bold text-on-surface mt-1">
                    {{ number_format($stats['totalSellers']) }}
                </div>
                <span class="text-[11px] text-on-surface-variant mt-1 block">
                    {{ number_format($stats['totalBuyers']) }} registered buyers
                </span>
            </div>

            {{-- 6. Logistics Network --}}
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4 shadow-xs">
                <span class="text-xs text-on-surface-variant font-medium block">Logistics Network</span>
                <div class="font-headline-md text-xl md:text-2xl font-bold text-on-surface mt-1">
                    {{ number_format($stats['totalLogistics'] + $stats['totalCouriers']) }}
                </div>
                <span class="text-[11px] text-on-surface-variant mt-1 block">
                    {{ $stats['totalLogistics'] }} Hubs · {{ $stats['totalCouriers'] }} Riders
                </span>
            </div>
        </div>

        {{-- Split Sections: Applications & Disputes --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">

            {{-- 1. Pending Applications Waiting for Review --}}
            <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant overflow-hidden shadow-xs flex flex-col">
                <div class="px-6 py-4 border-b border-outline-variant bg-surface-container-low flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary text-[20px]">how_to_reg</span>
                        <h2 class="font-headline-sm text-base font-bold text-on-surface">Pending Account Registrations</h2>
                    </div>
                    <a href="{{ route('admin.applications.index') }}" class="font-label-md text-xs text-primary hover:underline font-semibold">
                        View All ({{ $stats['pendingApplications'] }})
                    </a>
                </div>

                @if($pendingApplications->isEmpty())
                    <div class="p-8 text-center flex flex-col items-center justify-center flex-1">
                        <span class="material-symbols-outlined text-[36px] text-outline mb-2">check_circle</span>
                        <p class="font-body-sm text-on-surface-variant">All registration applications have been reviewed.</p>
                    </div>
                @else
                    <div class="overflow-x-auto flex-1">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-surface-container-low border-b border-outline-variant text-on-surface-variant font-semibold">
                                <tr>
                                    <th class="px-4 py-3">Applicant</th>
                                    <th class="px-4 py-3">Role</th>
                                    <th class="px-4 py-3">Documents</th>
                                    <th class="px-4 py-3 text-right">Quick Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline-variant">
                                @foreach($pendingApplications as $applicant)
                                    <tr class="hover:bg-surface-container-low/50 transition-colors">
                                        <td class="px-4 py-3">
                                            <span class="font-bold text-on-surface block">{{ $applicant->full_name }}</span>
                                            <span class="text-[11px] text-on-surface-variant">{{ $applicant->email }}</span>
                                            @if($applicant->sellerProfile)
                                                <span class="text-[10px] text-primary block mt-0.5">Store: {{ $applicant->sellerProfile->business_name }}</span>
                                            @elseif($applicant->sortingCenterProfile)
                                                <span class="text-[10px] text-primary block mt-0.5">Hub: {{ $applicant->sortingCenterProfile->business_name }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-surface-container-high text-on-surface">
                                                {{ $applicant->roleLabel() }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-1.5">
                                                @if($applicant->valid_id_path)
                                                    <a href="{{ Storage::url($applicant->valid_id_path) }}" target="_blank" class="px-2 py-0.5 rounded-md bg-primary/10 text-primary text-[10px] font-semibold hover:bg-primary/20">
                                                        ID
                                                    </a>
                                                @endif
                                                @if($applicant->sellerProfile?->business_permit_path)
                                                    <a href="{{ Storage::url($applicant->sellerProfile->business_permit_path) }}" target="_blank" class="px-2 py-0.5 rounded-md bg-secondary/10 text-secondary text-[10px] font-semibold hover:bg-secondary/20">
                                                        Permit
                                                    </a>
                                                @endif
                                                @if($applicant->sortingCenterProfile?->business_permit_path)
                                                    <a href="{{ Storage::url($applicant->sortingCenterProfile->business_permit_path) }}" target="_blank" class="px-2 py-0.5 rounded-md bg-secondary/10 text-secondary text-[10px] font-semibold hover:bg-secondary/20">
                                                        DTI
                                                    </a>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <div class="flex items-center justify-end gap-1.5">
                                                <form method="POST" action="{{ route('admin.applications.approve', $applicant) }}">
                                                    @csrf
                                                    <button type="submit" class="px-2.5 py-1 rounded-full bg-primary text-on-primary text-[11px] font-semibold hover:bg-primary/90 transition-all">
                                                        Approve
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('admin.applications.disapprove', $applicant) }}"
                                                      onsubmit="return confirm('Disapprove this applicant?');">
                                                    @csrf
                                                    <input type="hidden" name="reason" value="Application did not meet requirements.">
                                                    <button type="submit" class="px-2.5 py-1 rounded-full border border-outline-variant text-on-surface-variant text-[11px] font-semibold hover:bg-surface-container transition-all">
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

            {{-- 2. Active Complaints & Disputes --}}
            <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant overflow-hidden shadow-xs flex flex-col">
                <div class="px-6 py-4 border-b border-outline-variant bg-surface-container-low flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-rose-600 text-[20px]">report_problem</span>
                        <h2 class="font-headline-sm text-base font-bold text-on-surface">Recent Disputes & Return Claims</h2>
                    </div>
                    <a href="{{ route('admin.disputes.index') }}" class="font-label-md text-xs text-primary hover:underline font-semibold">
                        View All ({{ $stats['activeDisputes'] }})
                    </a>
                </div>

                @if($recentDisputes->isEmpty())
                    <div class="p-8 text-center flex flex-col items-center justify-center flex-1">
                        <span class="material-symbols-outlined text-[36px] text-outline mb-2">sentiment_satisfied</span>
                        <p class="font-body-sm text-on-surface-variant">No unresolved disputes on the platform.</p>
                    </div>
                @else
                    <div class="overflow-x-auto flex-1">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-surface-container-low border-b border-outline-variant text-on-surface-variant font-semibold">
                                <tr>
                                    <th class="px-4 py-3">Order #</th>
                                    <th class="px-4 py-3">Claim Reason</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline-variant">
                                @foreach($recentDisputes as $dispute)
                                    <tr class="hover:bg-surface-container-low/50 transition-colors">
                                        <td class="px-4 py-3">
                                            <span class="font-bold text-on-surface block">#{{ $dispute->order->order_number }}</span>
                                            <span class="text-[11px] text-on-surface-variant">{{ $dispute->order->buyer->full_name ?? 'Buyer' }}</span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="font-semibold text-on-surface block">{{ $dispute->reason }}</span>
                                            <span class="text-[11px] text-on-surface-variant truncate block max-w-[200px]">{{ $dispute->details }}</span>
                                        </td>
                                        <td class="px-4 py-3">
                                            @if($dispute->status === 'open')
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-700">Open</span>
                                            @elseif($dispute->status === 'under_review')
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-500/10 text-blue-700">Under Review</span>
                                            @elseif($dispute->status === 'resolved')
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-700">Resolved</span>
                                            @else
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-surface-container-high text-on-surface-variant">Rejected</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <a href="{{ route('admin.disputes.show', $dispute) }}" class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-secondary/10 text-secondary hover:bg-secondary/20 font-semibold text-[11px] transition-colors">
                                                Review
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

        </div>

        {{-- Split Sections: Recent Audit Trail & Recent Orders --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- 3. Read-Only System Audit Trail --}}
            <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant overflow-hidden shadow-xs">
                <div class="px-6 py-4 border-b border-outline-variant bg-surface-container-low flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary text-[20px]">history</span>
                        <h2 class="font-headline-sm text-base font-bold text-on-surface">System Audit Trail</h2>
                    </div>
                    <a href="{{ route('admin.audit-logs.index') }}" class="font-label-md text-xs text-primary hover:underline font-semibold">
                        Full Audit Log
                    </a>
                </div>

                @if($recentAuditLogs->isEmpty())
                    <div class="p-8 text-center">
                        <p class="font-body-sm text-on-surface-variant">No audit logs recorded yet.</p>
                    </div>
                @else
                    <div class="divide-y divide-outline-variant">
                        @foreach($recentAuditLogs as $log)
                            <div class="p-4 hover:bg-surface-container-low/50 transition-colors flex items-start justify-between gap-4">
                                <div class="flex items-start gap-3">
                                    <div class="w-8 h-8 rounded-full bg-primary/10 text-primary flex items-center justify-center font-bold text-xs flex-shrink-0 mt-0.5">
                                        <span class="material-symbols-outlined text-[16px]">receipt_long</span>
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="font-bold text-xs text-on-surface">{{ ucwords(str_replace('_', ' ', $log->action)) }}</span>
                                            <span class="text-[10px] text-on-surface-variant font-mono bg-surface-container px-1.5 py-0.2 rounded">
                                                {{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}
                                            </span>
                                        </div>
                                        <p class="text-xs text-on-surface-variant mt-0.5">{{ $log->reason ?? 'Admin operation performed' }}</p>
                                        <span class="text-[10px] text-outline mt-1 block">By: {{ $log->operator?->full_name ?? 'System' }} (IP: {{ $log->ip_address ?? '127.0.0.1' }})</span>
                                    </div>
                                </div>
                                <span class="text-[10px] text-on-surface-variant font-medium whitespace-nowrap">
                                    {{ $log->created_at->diffForHumans() }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- 4. Recent Platform Orders --}}
            <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant overflow-hidden shadow-xs">
                <div class="px-6 py-4 border-b border-outline-variant bg-surface-container-low flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary text-[20px]">shopping_bag</span>
                        <h2 class="font-headline-sm text-base font-bold text-on-surface">Recent Platform Orders</h2>
                    </div>
                    <a href="{{ route('admin.reports.index') }}" class="font-label-md text-xs text-primary hover:underline font-semibold">
                        Sales Analytics
                    </a>
                </div>

                @if($recentOrders->isEmpty())
                    <div class="p-8 text-center">
                        <p class="font-body-sm text-on-surface-variant">No orders placed yet.</p>
                    </div>
                @else
                    <div class="divide-y divide-outline-variant">
                        @foreach($recentOrders as $order)
                            <div class="p-4 hover:bg-surface-container-low/50 transition-colors flex items-center justify-between gap-4">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-xs text-on-surface">#{{ $order->order_number }}</span>
                                        <span class="text-xs text-on-surface-variant">• {{ $order->seller->sellerProfile->business_name ?? 'Merchant' }}</span>
                                    </div>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="text-xs font-semibold text-primary">₱{{ number_format($order->total_amount, 2) }}</span>
                                        <span class="text-[11px] text-on-surface-variant">Buyer: {{ $order->buyer->full_name ?? 'Buyer' }}</span>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-surface-container-high text-on-surface capitalize">
                                        {{ str_replace('_', ' ', $order->status) }}
                                    </span>
                                    <span class="text-[10px] text-on-surface-variant block mt-1">
                                        {{ $order->placed_at?->diffForHumans() ?? $order->created_at->diffForHumans() }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

        </div>

                </div>
        </div>
    </div>
</div>

@endsection
