@props(['active' => 'dashboard'])

@php
    $links = [
        'dashboard' => ['label' => 'Hub Overview', 'icon' => 'dashboard', 'url' => route('logistics.dashboard')],
        'incoming' => ['label' => 'Incoming Parcels', 'icon' => 'qr_code_scanner', 'url' => route('logistics.incoming.index')],
        'sorting' => ['label' => 'Sorting & Staging', 'icon' => 'sort', 'url' => route('logistics.sorting.index')],
        'pickups' => ['label' => 'Merchant Pickups', 'icon' => 'hail', 'url' => route('logistics.pickups.index')],
        'riders' => ['label' => 'Rider Fleet', 'icon' => 'two_wheeler', 'url' => route('logistics.riders.index')],
        'assignments' => ['label' => 'Dispatch Assignments', 'icon' => 'assignment_ind', 'url' => route('logistics.assignments.index')],
        'deliveries' => ['label' => 'Transit Monitoring', 'icon' => 'local_shipping', 'url' => route('logistics.deliveries.index')],
        'reports' => ['label' => 'Hub Reports', 'icon' => 'bar_chart', 'url' => route('logistics.reports.index')],
        'account' => ['label' => 'Facility Settings', 'icon' => 'settings', 'url' => route('logistics.account.show')],
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
                </a>
            @endforeach
        </div>

        <div class="hidden xl:flex items-center gap-2 pl-2 border-l border-outline-variant/60 flex-shrink-0">
            <a href="{{ route('logistics.riders.create') }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-primary/10 text-primary hover:bg-primary/20 text-xs font-bold transition-colors">
                <span class="material-symbols-outlined text-[16px]">person_add</span>
                <span>Add Rider</span>
            </a>
            <a href="{{ route('logistics.incoming.index') }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl border border-outline-variant text-on-surface-variant hover:text-on-surface text-xs font-semibold hover:bg-surface-container transition-colors">
                <span class="material-symbols-outlined text-[16px]">qr_code_scanner</span>
                <span>Scan Inbound</span>
            </a>
        </div>
    </div>
</div>
