@props(['active' => 'dashboard'])

@php
    $links = [
        'dashboard' => ['label' => 'Overview', 'icon' => 'dashboard', 'url' => route('seller.dashboard')],
        'products' => ['label' => 'Inventory', 'icon' => 'inventory_2', 'url' => route('seller.products.index')],
        'orders' => ['label' => 'Orders', 'icon' => 'receipt_long', 'url' => route('seller.orders.index')],
        'reports' => ['label' => 'Reports', 'icon' => 'bar_chart', 'url' => route('seller.reports.index')],
        'feedback' => ['label' => 'Feedback', 'icon' => 'rate_review', 'url' => route('seller.feedback.index')],
    ];
@endphp

<div class="flex gap-2 overflow-x-auto pb-1 mb-6 border-b border-outline-variant">
    @foreach($links as $key => $link)
        <a
            href="{{ $link['url'] }}"
            class="flex items-center gap-2 px-4 py-3 font-label-md text-label-md whitespace-nowrap border-b-2 -mb-px transition-colors {{ $active === $key ? 'border-primary text-primary' : 'border-transparent text-on-surface-variant hover:text-on-surface' }}">
            <span class="material-symbols-outlined text-[18px]">{{ $link['icon'] }}</span>
            {{ $link['label'] }}
        </a>
    @endforeach
</div>
