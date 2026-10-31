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
            'badgeColor' => 'bg-amber-500 text-white',
        ],
        'users' => ['label' => 'User Directory', 'icon' => 'group', 'url' => route('admin.users.index')],
        'compliance' => ['label' => 'Seller Compliance', 'icon' => 'gavel', 'url' => route('admin.compliance.index')],
        'disputes' => [
            'label' => 'Disputes',
            'icon' => 'report_problem',
            'url' => route('admin.disputes.index'),
            'badge' => $openDisputesCount > 0 ? $openDisputesCount : null,
            'badgeColor' => 'bg-rose-500 text-white',
        ],
        'commissions' => ['label' => 'Commissions (10%)', 'icon' => 'payments', 'url' => route('admin.commissions.index')],
        'reports' => ['label' => 'Reports & Analytics', 'icon' => 'bar_chart', 'url' => route('admin.reports.index')],
        'settings' => ['label' => 'Platform Settings', 'icon' => 'tune', 'url' => route('admin.settings.index')],
        'audit-logs' => ['label' => 'Audit Trail', 'icon' => 'history', 'url' => route('admin.audit-logs.index')],
        'account' => ['label' => 'Admin Profile', 'icon' => 'admin_panel_settings', 'url' => route('admin.account.show')],
    ];
@endphp

<div class="mb-6 bg-surface-container-lowest rounded-2xl border border-outline-variant/70 p-2 shadow-xs">
    <div class="flex items-center justify-between gap-4 overflow-x-auto pb-1 scrollbar-none">
        <div class="flex items-center gap-1.5 flex-nowrap">
            @foreach($links as $key => $link)
                <a
                    href="{{ $link['url'] }}"
                    class="flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold whitespace-nowrap transition-all {{ $active === $key ? 'bg-primary text-on-primary shadow-xs font-bold' : 'text-on-surface-variant hover:text-on-surface hover:bg-surface-container' }}">
                    <span class="material-symbols-outlined text-[17px]">{{ $link['icon'] }}</span>
                    <span>{{ $link['label'] }}</span>
                    @if(!empty($link['badge']))
                        <span class="inline-flex items-center justify-center px-1.5 py-0.2 text-[10px] font-extrabold rounded-full {{ $active === $key ? 'bg-white text-primary' : ($link['badgeColor'] ?? 'bg-primary text-white') }}">
                            {{ $link['badge'] }}
                        </span>
                    @endif
                </a>
            @endforeach
        </div>

        <div class="hidden xl:flex items-center gap-2 pl-2 border-l border-outline-variant/60 flex-shrink-0">
            <a href="{{ route('admin.applications.index', ['status' => 'pending']) }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-amber-500/10 text-amber-700 hover:bg-amber-500/20 text-xs font-bold transition-colors">
                <span class="material-symbols-outlined text-[16px]">pending_actions</span>
                <span>Pending Approvals</span>
            </a>
            <a href="{{ route('admin.disputes.index', ['status' => 'open']) }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-rose-500/10 text-rose-700 hover:bg-rose-500/20 text-xs font-bold transition-colors">
                <span class="material-symbols-outlined text-[16px]">warning</span>
                <span>Active Claims</span>
            </a>
        </div>
    </div>
</div>
