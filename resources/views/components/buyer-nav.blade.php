@props(['active' => 'dashboard'])

@php
    $links = [
        'dashboard' => ['label' => 'Overview', 'url' => route('buyer.dashboard')],
        'orders' => ['label' => 'My Orders', 'url' => route('buyer.orders')],
        'account' => ['label' => 'My Account', 'url' => route('buyer.account')],
    ];
@endphp

<div class="flex items-center gap-1.5 p-1 bg-surface-container-low rounded-2xl border border-outline-variant/60 w-fit mb-8 max-w-full overflow-x-auto">
    @foreach($links as $key => $link)
        <a
            href="{{ $link['url'] }}"
            class="px-5 py-2 rounded-xl text-xs font-semibold whitespace-nowrap transition-all duration-200 {{ $active === $key ? 'bg-surface-container-lowest text-primary shadow-xs font-bold' : 'text-on-surface-variant hover:text-on-surface hover:bg-surface-container/50' }}">
            {{ $link['label'] }}
        </a>
    @endforeach
</div>
