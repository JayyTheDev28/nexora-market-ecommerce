@props(['active' => 'dashboard'])

@php
    $links = [
        'dashboard' => ['label' => 'Overview', 'icon' => 'dashboard', 'url' => route('buyer.dashboard')],
        'orders' => ['label' => 'My Orders', 'icon' => 'receipt_long', 'url' => route('buyer.orders')],
        'account' => ['label' => 'My Account', 'icon' => 'account_circle', 'url' => route('buyer.account')],
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
