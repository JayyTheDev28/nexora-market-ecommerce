@props(['active' => 'dashboard'])

@php
    $pendingApplicationsCount = \App\Models\User::where('approval_status', 'pending')
        ->whereIn('role', ['buyer', 'seller', 'sorting_center'])
        ->count();

    $openDisputesCount = \App\Models\Dispute::whereIn('status', ['open', 'under_review'])->count();

    $links = [
        'dashboard' => ['label' => 'Overview', 'icon' => 'dashboard', 'url' => route('admin.dashboard')],
        'applications' => [
            'label' => 'Applications',
            'icon' => 'how_to_reg',
            'url' => route('admin.applications.index'),
            'badge' => $pendingApplicationsCount > 0 ? $pendingApplicationsCount : null,
            'badgeColor' => 'bg-primary text-on-primary',
        ],
        'users' => ['label' => 'User Accounts', 'icon' => 'group', 'url' => route('admin.users.index')],
        'compliance' => ['label' => 'Seller Compliance', 'icon' => 'gavel', 'url' => route('admin.compliance.index')],
        'disputes' => [
            'label' => 'Disputes',
            'icon' => 'report_problem',
            'url' => route('admin.disputes.index'),
            'badge' => $openDisputesCount > 0 ? $openDisputesCount : null,
            'badgeColor' => 'bg-error text-on-error',
        ],
        'commissions' => ['label' => 'Commissions (10%)', 'icon' => 'payments', 'url' => route('admin.commissions.index')],
        'reports' => ['label' => 'Reports & Analytics', 'icon' => 'bar_chart', 'url' => route('admin.reports.index')],
        'settings' => ['label' => 'Platform Settings', 'icon' => 'tune', 'url' => route('admin.settings.index')],
        'audit-logs' => ['label' => 'Audit Trail', 'icon' => 'history', 'url' => route('admin.audit-logs.index')],
        'account' => ['label' => 'Admin Profile', 'icon' => 'admin_panel_settings', 'url' => route('admin.account.show')],
    ];
@endphp

<div class="flex gap-2 overflow-x-auto pb-1 mb-6 border-b border-outline-variant">
    @foreach($links as $key => $link)
        <a
            href="{{ $link['url'] }}"
            class="flex items-center gap-2 px-4 py-3 font-label-md text-label-md whitespace-nowrap border-b-2 -mb-px transition-colors {{ $active === $key ? 'border-primary text-primary font-semibold' : 'border-transparent text-on-surface-variant hover:text-on-surface' }}">
            <span class="material-symbols-outlined text-[18px]">{{ $link['icon'] }}</span>
            <span>{{ $link['label'] }}</span>
            @if(!empty($link['badge']))
                <span class="px-1.5 py-0.2 rounded-full text-[10px] font-bold {{ $link['badgeColor'] ?? 'bg-primary text-on-primary' }}">
                    {{ $link['badge'] }}
                </span>
            @endif
        </a>
    @endforeach
</div>
