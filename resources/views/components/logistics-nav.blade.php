@props(['active' => 'dashboard'])

@php
    $links = [
        'dashboard' => ['label' => 'Overview', 'icon' => 'dashboard', 'url' => route('logistics.dashboard')],
        'riders' => ['label' => 'Rider Management', 'icon' => 'two_wheeler', 'url' => route('logistics.riders.index')],
        'pickups' => ['label' => 'Pickup Requests', 'icon' => 'hail', 'url' => route('logistics.pickups.index')],
        'incoming' => ['label' => 'Incoming Parcels', 'icon' => 'qr_code_scanner', 'url' => route('logistics.incoming.index')],
        'sorting' => ['label' => 'Sorting', 'icon' => 'sort', 'url' => route('logistics.sorting.index')],
        'assignments' => ['label' => 'Assignments', 'icon' => 'assignment_ind', 'url' => route('logistics.assignments.index')],
        'deliveries' => ['label' => 'Monitoring', 'icon' => 'local_shipping', 'url' => route('logistics.deliveries.index')],
        'reports' => ['label' => 'Reports', 'icon' => 'bar_chart', 'url' => route('logistics.reports.index')],
        'account' => ['label' => 'Facility Settings', 'icon' => 'settings', 'url' => route('logistics.account.show')],
    ];
@endphp

<div class="flex gap-2 overflow-x-auto pb-1 mb-6 border-b border-outline-variant">
    @foreach($links as $key => $link)
        <a
            href="{{ $link['url'] }}"
            class="flex items-center gap-2 px-4 py-3 font-label-md text-label-md whitespace-nowrap border-b-2 -mb-px transition-colors {{ $active === $key ? 'border-primary text-primary font-semibold' : 'border-transparent text-on-surface-variant hover:text-on-surface' }}">
            <span class="material-symbols-outlined text-[18px]">{{ $link['icon'] }}</span>
            {{ $link['label'] }}
        </a>
    @endforeach
</div>
