@props(['active' => 'dashboard'])

@php
    $links = [
        'dashboard' => ['label' => 'Overview', 'url' => route('buyer.dashboard'), 'icon' => 'dashboard'],
        'orders' => ['label' => 'My Orders', 'url' => route('buyer.orders'), 'icon' => 'package_2'],
        'wishlist' => ['label' => 'Wishlist', 'url' => route('buyer.wishlist'), 'icon' => 'favorite'],
        'vouchers' => ['label' => 'My Vouchers', 'url' => route('buyer.vouchers'), 'icon' => 'confirmation_number'],
        'chat' => ['label' => 'Messages', 'url' => route('buyer.chat.index'), 'icon' => 'chat'],
        'account' => ['label' => 'My Account', 'url' => route('buyer.account'), 'icon' => 'person'],
    ];
@endphp

<div class="flex items-center gap-1 p-1 bg-surface-container-low rounded-xl border border-outline-variant/60 w-fit mb-5 max-w-full overflow-x-auto scrollbar-none">
    @foreach($links as $key => $link)
        <a
            href="{{ $link['url'] }}"
            class="px-3.5 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition-all duration-150 flex items-center gap-1.5 {{ $active === $key ? 'bg-surface-container-lowest text-primary shadow-xs font-bold' : 'text-on-surface-variant hover:text-on-surface hover:bg-surface-container/50' }}">
            <span class="material-symbols-outlined text-[16px]">{{ $link['icon'] }}</span>
            <span>{{ $link['label'] }}</span>
        </a>
    @endforeach
</div>
