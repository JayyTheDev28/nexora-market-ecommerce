@props(['active' => 'dashboard'])

@php
    $sellerId = auth()->id();
    $pendingOrdersCount = \App\Models\Order::where('seller_id', $sellerId)->where('status', 'placed')->count();
    $pendingReturnsCount = \App\Models\Dispute::whereHas('order', fn ($q) => $q->where('seller_id', $sellerId))->where('seller_status', 'pending')->count();

    $links = [
        'dashboard' => ['label' => 'Overview', 'icon' => 'dashboard', 'url' => route('seller.dashboard'), 'badge' => null],
        'products' => ['label' => 'Products', 'icon' => 'inventory_2', 'url' => route('seller.products.index'), 'badge' => null],
        'categories' => ['label' => 'Categories', 'icon' => 'category', 'url' => route('seller.categories.index'), 'badge' => null],
        'inventory' => ['label' => 'Inventory', 'icon' => 'warehouse', 'url' => route('seller.inventory.index'), 'badge' => null],
        'orders' => ['label' => 'Orders', 'icon' => 'receipt_long', 'url' => route('seller.orders.index'), 'badge' => $pendingOrdersCount ?: null],
        'returns' => ['label' => 'Returns', 'icon' => 'assignment_return', 'url' => route('seller.returns.index'), 'badge' => $pendingReturnsCount ?: null],
        'promotions' => ['label' => 'Sales & Flash', 'icon' => 'percent', 'url' => route('seller.promotions.index'), 'badge' => null],
        'vouchers' => ['label' => 'Vouchers', 'icon' => 'confirmation_number', 'url' => route('seller.vouchers.index'), 'badge' => null],
        'feedback' => ['label' => 'Feedback', 'icon' => 'rate_review', 'url' => route('seller.feedback.index'), 'badge' => null],
        'chat' => ['label' => 'Chat', 'icon' => 'chat', 'url' => route('seller.chat.index'), 'badge' => null],
        'reports' => ['label' => 'Finances & Payouts', 'icon' => 'account_balance_wallet', 'url' => route('seller.reports.index'), 'badge' => null],
        'store' => ['label' => 'Store Profile', 'icon' => 'storefront', 'url' => route('seller.store.edit'), 'badge' => null],
    ];
@endphp

<div class="flex gap-1 overflow-x-auto pb-1 mb-6 border-b border-outline-variant scrollbar-none">
    @foreach($links as $key => $link)
        <a
            href="{{ $link['url'] }}"
            class="flex items-center gap-1.5 px-3.5 py-2.5 font-label-md text-label-md whitespace-nowrap rounded-t-lg border-b-2 -mb-px transition-colors {{ $active === $key ? 'border-primary text-primary bg-primary/5 font-semibold' : 'border-transparent text-on-surface-variant hover:text-on-surface hover:bg-surface-container-low' }}">
            <span class="material-symbols-outlined text-[18px]">{{ $link['icon'] }}</span>
            <span>{{ $link['label'] }}</span>
            @if(!empty($link['badge']))
                <span class="inline-flex items-center justify-center px-1.5 py-0.5 text-[10px] font-bold rounded-full bg-error text-on-error">
                    {{ $link['badge'] }}
                </span>
            @endif
        </a>
    @endforeach
</div>
