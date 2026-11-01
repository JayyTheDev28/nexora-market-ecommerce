@props(['active' => 'dashboard'])

@php
    $user = auth()->user();
    $userName = $user->first_name ?: 'Admin';
    $adminFullName = $user->full_name ?: 'Platform Administrator';
    $userInitial = strtoupper(substr($userName, 0, 1));

    $adminAvatarUrl = null;
    if ($user && !empty($user->avatar_path)) {
        $adminAvatarUrl = str_starts_with($user->avatar_path, 'http')
            ? $user->avatar_path
            : \Illuminate\Support\Facades\Storage::url($user->avatar_path);
    }

    $pendingApplicationsCount = \App\Models\User::where('approval_status', 'pending')
        ->whereIn('role', ['buyer', 'seller', 'sorting_center'])
        ->count();

    $openDisputesCount = \App\Models\Dispute::whereIn('status', ['open', 'under_review'])->count();

    $sections = [
        [
            'title' => null,
            'items' => [
                'dashboard' => ['label' => 'Dashboard', 'icon' => 'grid_view', 'url' => route('admin.dashboard'), 'badge' => null],
            ],
        ],
        [
            'title' => 'PLATFORM GOVERNANCE',
            'items' => [
                'applications' => ['label' => 'Partner applications', 'icon' => 'how_to_reg', 'url' => route('admin.applications.index'), 'badge' => $pendingApplicationsCount ?: null, 'badgeColor' => 'bg-primary text-on-primary'],
                'users' => ['label' => 'User directory', 'icon' => 'group', 'url' => route('admin.users.index'), 'badge' => null],
                'compliance' => ['label' => 'Seller compliance', 'icon' => 'gavel', 'url' => route('admin.compliance.index'), 'badge' => null],
                'disputes' => ['label' => 'Dispute claims', 'icon' => 'report_problem', 'url' => route('admin.disputes.index'), 'badge' => $openDisputesCount ?: null, 'badgeColor' => 'bg-error text-on-error'],
            ],
        ],
        [
            'title' => 'FINANCES & AUDITS',
            'items' => [
                'commissions' => ['label' => 'Commissions (10%)', 'icon' => 'payments', 'url' => route('admin.commissions.index'), 'badge' => null],
                'reports' => ['label' => 'Reports & analytics', 'icon' => 'bar_chart', 'url' => route('admin.reports.index'), 'badge' => null],
                'audit-logs' => ['label' => 'Audit trail', 'icon' => 'history', 'url' => route('admin.audit-logs.index'), 'badge' => null],
                'settings' => ['label' => 'Platform settings', 'icon' => 'tune', 'url' => route('admin.settings.index'), 'badge' => null],
                'account' => ['label' => 'Admin profile', 'icon' => 'admin_panel_settings', 'url' => route('admin.account.show'), 'badge' => null],
            ],
        ],
    ];

    $activeLabel = 'Overview';
    foreach ($sections as $sec) {
        if (isset($sec['items'][$active])) {
            $activeLabel = $sec['items'][$active]['label'];
            break;
        }
    }
@endphp

<div
    x-data="{
        mobileNavOpen: false,
        sidebarCollapsed: localStorage.getItem('sidebar_collapsed') === 'true',
        toggleSidebar() {
            this.sidebarCollapsed = !this.sidebarCollapsed;
            localStorage.setItem('sidebar_collapsed', this.sidebarCollapsed);
        }
    }"
    class="shrink-0 transition-all duration-200"
    :class="sidebarCollapsed ? 'lg:w-16' : 'lg:w-64'">

    {{-- Small Screen (Mobile): Sticky Hamburger Header Bar --}}
    <div class="lg:hidden sticky top-0 z-30 bg-surface-container-lowest border-b border-outline-variant/80 px-4 py-3 shadow-xs flex items-center justify-between">
        <div class="flex items-center gap-3 min-w-0">
            <button
                type="button"
                @click="mobileNavOpen = true"
                class="w-9 h-9 rounded-xl border border-outline-variant bg-surface-container-low flex items-center justify-center text-on-surface hover:bg-surface-container hover:border-outline transition-colors cursor-pointer shrink-0"
                aria-label="Open sidebar menu">
                <span class="material-symbols-outlined text-[22px]">menu</span>
            </button>
            <div class="min-w-0">
                <p class="text-xs font-bold text-on-surface truncate">Platform Administration</p>
                <p class="text-[11px] text-on-surface-variant truncate flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-primary"></span>
                    <span>{{ $activeLabel }}</span>
                </p>
            </div>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <div class="w-8 h-8 rounded-full bg-primary/10 text-primary font-bold text-xs flex items-center justify-center overflow-hidden">
                @if($adminAvatarUrl)
                    <img src="{{ $adminAvatarUrl }}" alt="{{ $adminFullName }}" class="w-full h-full object-cover">
                @else
                    <span>{{ $userInitial }}</span>
                @endif
            </div>
        </div>
    </div>

    {{-- Mobile Slide-Over Drawer with Hamburger Close --}}
    <div
        x-show="mobileNavOpen"
        x-cloak
        class="fixed inset-0 z-50 lg:hidden"
        role="dialog"
        aria-modal="true">

        {{-- Backdrop --}}
        <div
            x-show="mobileNavOpen"
            x-transition:enter="transition-opacity ease-linear duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity ease-linear duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @click="mobileNavOpen = false"
            class="fixed inset-0 bg-black/50 backdrop-blur-xs"></div>

        {{-- Sliding Drawer Container --}}
        <div
            x-show="mobileNavOpen"
            x-transition:enter="transition ease-in-out duration-300 transform"
            x-transition:enter-start="-translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in-out duration-300 transform"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="-translate-x-full"
            class="relative mr-auto flex h-full w-full max-w-xs flex-col bg-surface-container-lowest shadow-2xl">

            {{-- Workspace Header in Drawer (Fixed at top) --}}
            <div class="p-4 pb-3 border-b border-outline-variant/60 shrink-0 flex items-center justify-between">
                <div class="flex items-center gap-2.5 min-w-0">
                    <div class="w-8 h-8 rounded-full bg-primary/10 text-primary font-bold text-xs flex items-center justify-center shrink-0 overflow-hidden">
                        @if($adminAvatarUrl)
                            <img src="{{ $adminAvatarUrl }}" alt="{{ $userName }}" class="w-full h-full object-cover">
                        @else
                            <span>{{ $userInitial }}</span>
                        @endif
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-bold text-on-surface truncate">{{ $userName }}</p>
                        <p class="text-[10px] text-on-surface-variant truncate">Platform Administration</p>
                    </div>
                </div>
                <button
                    type="button"
                    @click="mobileNavOpen = false"
                    class="w-8 h-8 rounded-lg text-on-surface-variant hover:text-on-surface hover:bg-surface-container flex items-center justify-center transition-colors cursor-pointer"
                    aria-label="Close sidebar menu">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>

            {{-- Mobile Drawer Nav Links (Scrollable in middle) --}}
            <div class="flex-1 overflow-y-auto min-h-0 px-4 py-3">
                <nav class="flex flex-col gap-0.5">
                    @foreach($sections as $sec)
                        @if(!empty($sec['title']))
                            <div class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant/70 px-3 pt-3 pb-1">
                                {{ $sec['title'] }}
                            </div>
                        @endif
                        @foreach($sec['items'] as $key => $link)
                            <a
                                href="{{ $link['url'] }}"
                                @click="mobileNavOpen = false"
                                class="flex items-center justify-between px-3 py-2 rounded-xl text-xs font-medium transition-colors {{ $active === $key ? 'bg-primary text-on-primary font-semibold shadow-xs' : 'text-on-surface-variant hover:text-primary hover:bg-surface-container-low' }}">
                                <div class="flex items-center gap-2.5 truncate">
                                    <span class="material-symbols-outlined text-[18px] shrink-0">{{ $link['icon'] }}</span>
                                    <span class="truncate">{{ $link['label'] }}</span>
                                </div>
                                @if(!empty($link['badge']))
                                    <span class="inline-flex items-center justify-center px-1.5 py-0.5 text-[10px] font-bold rounded-full shrink-0 ml-2 {{ $active === $key ? 'bg-white text-primary' : ($link['badgeColor'] ?? 'bg-primary text-on-primary') }}">
                                        {{ $link['badge'] }}
                                    </span>
                                @endif
                            </a>
                        @endforeach
                    @endforeach
                </nav>
            </div>

            {{-- Bottom Account Card & Log Out (Fixed at bottom) --}}
            <div class="p-4 pt-3 border-t border-outline-variant/60 shrink-0 bg-surface-container-lowest flex flex-col gap-2">
                <div class="flex items-center gap-2.5 p-2 rounded-xl bg-surface-container-low border border-outline-variant/40">
                    <div class="w-8 h-8 rounded-full bg-primary/10 text-primary font-bold text-xs flex items-center justify-center shrink-0 overflow-hidden">
                        @if($adminAvatarUrl)
                            <img src="{{ $adminAvatarUrl }}" alt="{{ $adminFullName }}" class="w-full h-full object-cover">
                        @else
                            <span>{{ $userInitial }}</span>
                        @endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-bold text-on-surface truncate" title="{{ $adminFullName }}">{{ $adminFullName }}</p>
                        <p class="text-[10px] text-on-surface-variant truncate">System Supervisor</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf
                    <button
                        type="submit"
                        class="w-full flex items-center gap-2 px-3 py-2 rounded-xl text-xs font-medium text-on-surface-variant hover:text-error hover:bg-error/5 transition-colors cursor-pointer">
                        <span class="material-symbols-outlined text-[18px]">logout</span>
                        <span>Log out</span>
                    </button>
                </form>
            </div>

        </div>
    </div>

    {{-- Desktop Sidebar (Docked to the left edge, full height, sticky) --}}
    <aside
        class="hidden lg:flex flex-col shrink-0 h-screen sticky top-0 bg-surface-container-lowest border-r border-outline-variant/60 select-none transition-all duration-200"
        :class="sidebarCollapsed ? 'w-16' : 'w-64'">

        {{-- Top Workspace Header: Fixed at top / Not scrollable --}}
        <div class="border-b border-outline-variant/60 shrink-0" :class="sidebarCollapsed ? 'p-3' : 'p-4 pb-3'">
            {{-- Expanded Header --}}
            <div x-show="!sidebarCollapsed" class="flex items-center justify-between">
                <div class="flex items-center gap-2.5 min-w-0">
                    <div class="w-8 h-8 rounded-full bg-primary/10 text-primary font-bold text-xs flex items-center justify-center shrink-0 overflow-hidden">
                        @if($adminAvatarUrl)
                            <img src="{{ $adminAvatarUrl }}" alt="{{ $userName }}" class="w-full h-full object-cover">
                        @else
                            <span>{{ $userInitial }}</span>
                        @endif
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-bold text-on-surface truncate">{{ $userName }}</p>
                        <p class="text-[10px] text-on-surface-variant truncate">Platform Administration</p>
                    </div>
                </div>
                <button
                    type="button"
                    @click="toggleSidebar()"
                    class="w-7 h-7 rounded-lg text-on-surface-variant hover:text-on-surface hover:bg-surface-container flex items-center justify-center transition-colors cursor-pointer"
                    title="Collapse sidebar">
                    <span class="material-symbols-outlined text-[18px]">menu</span>
                </button>
            </div>

            {{-- Collapsed Header (Icon-only rail) --}}
            <div x-show="sidebarCollapsed" class="flex items-center justify-center">
                <button
                    type="button"
                    @click="toggleSidebar()"
                    class="w-9 h-9 rounded-xl text-on-surface-variant hover:text-on-surface hover:bg-surface-container flex items-center justify-center transition-colors cursor-pointer"
                    title="Expand sidebar">
                    <span class="material-symbols-outlined text-[20px]">menu</span>
                </button>
            </div>
        </div>

        {{-- Scrollable Nav Links: Only the middle list scrolls if screen is short --}}
        <div class="flex-1 overflow-y-auto min-h-0" :class="sidebarCollapsed ? 'p-2 py-3' : 'px-4 py-3'">
            <nav class="flex flex-col gap-1">
                @foreach($sections as $sec)
                    {{-- Section Title (Only when expanded) --}}
                    @if(!empty($sec['title']))
                        <div x-show="!sidebarCollapsed" class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant/70 px-3 pt-3 pb-1">
                            {{ $sec['title'] }}
                        </div>
                        <div x-show="sidebarCollapsed" class="my-1.5 border-t border-outline-variant/40"></div>
                    @endif

                    @foreach($sec['items'] as $key => $link)
                        <a
                            href="{{ $link['url'] }}"
                            title="{{ $link['label'] }}"
                            class="flex items-center rounded-xl text-xs font-medium transition-colors {{ $active === $key ? 'bg-primary text-on-primary font-semibold shadow-xs' : 'text-on-surface-variant hover:text-primary hover:bg-surface-container-low' }}"
                            :class="sidebarCollapsed ? 'w-10 h-10 mx-auto justify-center p-0' : 'justify-between px-3 py-2'">
                            
                            {{-- Icon & Label --}}
                            <div class="flex items-center gap-2.5" :class="sidebarCollapsed ? 'justify-center' : 'truncate'">
                                <span class="material-symbols-outlined text-[20px] shrink-0">{{ $link['icon'] }}</span>
                                <span x-show="!sidebarCollapsed" class="truncate">{{ $link['label'] }}</span>
                            </div>

                            {{-- Badge --}}
                            @if(!empty($link['badge']))
                                <span
                                    x-show="!sidebarCollapsed"
                                    class="inline-flex items-center justify-center px-1.5 py-0.5 text-[10px] font-bold rounded-full shrink-0 ml-2 {{ $active === $key ? 'bg-white text-primary' : ($link['badgeColor'] ?? 'bg-primary text-on-primary') }}">
                                    {{ $link['badge'] }}
                                </span>
                            @endif
                        </a>
                    @endforeach
                @endforeach
            </nav>
        </div>

        {{-- Bottom Account Card & Log Out: Fixed at bottom / Stays there / Not scrollable --}}
        <div class="border-t border-outline-variant/60 shrink-0 bg-surface-container-lowest flex flex-col gap-2" :class="sidebarCollapsed ? 'p-2 py-3 items-center' : 'p-4 pt-3'">
            
            {{-- Expanded Admin Card --}}
            <div x-show="!sidebarCollapsed" class="flex items-center gap-2.5 p-2 rounded-xl bg-surface-container-low border border-outline-variant/40">
                <div class="w-8 h-8 rounded-full bg-primary/10 text-primary font-bold text-xs flex items-center justify-center shrink-0 overflow-hidden">
                    @if($adminAvatarUrl)
                        <img src="{{ $adminAvatarUrl }}" alt="{{ $adminFullName }}" class="w-full h-full object-cover">
                    @else
                        <span>{{ $userInitial }}</span>
                    @endif
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-bold text-on-surface truncate" title="{{ $adminFullName }}">{{ $adminFullName }}</p>
                    <p class="text-[10px] text-on-surface-variant truncate">System Supervisor</p>
                </div>
            </div>

            {{-- Collapsed Admin Avatar --}}
            <div x-show="sidebarCollapsed" class="flex items-center justify-center py-1">
                <div class="w-9 h-9 rounded-full bg-primary/10 text-primary font-bold text-xs flex items-center justify-center shrink-0 overflow-hidden" title="{{ $adminFullName }}">
                    @if($adminAvatarUrl)
                        <img src="{{ $adminAvatarUrl }}" alt="{{ $adminFullName }}" class="w-full h-full object-cover">
                    @else
                        <span>{{ $userInitial }}</span>
                    @endif
                </div>
            </div>

            {{-- Logout Form --}}
            <form method="POST" action="{{ route('logout') }}" class="w-full flex items-center justify-center">
                @csrf
                <button
                    type="submit"
                    title="Log out"
                    class="flex items-center rounded-xl text-xs font-medium text-on-surface-variant hover:text-error hover:bg-error/5 transition-colors cursor-pointer"
                    :class="sidebarCollapsed ? 'w-10 h-10 justify-center p-0' : 'w-full gap-2 px-3 py-2'">
                    <span class="material-symbols-outlined text-[20px]">logout</span>
                    <span x-show="!sidebarCollapsed">Log out</span>
                </button>
            </form>
        </div>

    </aside>

</div>
