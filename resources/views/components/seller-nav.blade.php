@props(['active' => 'dashboard'])

@php
    $sellerId = auth()->id();
    $pendingOrdersCount = \App\Models\Order::where('seller_id', $sellerId)->where('status', 'placed')->count();
    $pendingReturnsCount = \App\Models\Dispute::whereHas('order', fn ($q) => $q->where('seller_id', $sellerId))->where('seller_status', 'pending')->count();

    $navGroups = [
        [
            'group' => 'Fulfillment & Stock',
            'links' => [
                'dashboard' => ['label' => 'Overview', 'icon' => 'dashboard', 'url' => route('seller.dashboard')],
                'orders' => ['label' => 'Orders', 'icon' => 'receipt_long', 'url' => route('seller.orders.index'), 'badge' => $pendingOrdersCount ?: null],
                'inventory' => ['label' => 'Inventory', 'icon' => 'warehouse', 'url' => route('seller.inventory.index')],
                'returns' => ['label' => 'Returns', 'icon' => 'assignment_return', 'url' => route('seller.returns.index'), 'badge' => $pendingReturnsCount ?: null],
            ]
        ],
        [
            'group' => 'Catalog & Marketing',
            'links' => [
                'products' => ['label' => 'Products', 'icon' => 'inventory_2', 'url' => route('seller.products.index')],
                'categories' => ['label' => 'Categories', 'icon' => 'category', 'url' => route('seller.categories.index')],
                'promotions' => ['label' => 'Sales & Flash', 'icon' => 'percent', 'url' => route('seller.promotions.index')],
                'vouchers' => ['label' => 'Vouchers', 'icon' => 'confirmation_number', 'url' => route('seller.vouchers.index')],
            ]
        ],
        [
            'group' => 'Store & Care',
            'links' => [
                'feedback' => ['label' => 'Feedback', 'icon' => 'rate_review', 'url' => route('seller.feedback.index')],
                'chat' => ['label' => 'Chat', 'icon' => 'chat', 'url' => route('seller.chat.index')],
                'reports' => ['label' => 'Finances & Payouts', 'icon' => 'account_balance_wallet', 'url' => route('seller.reports.index')],
                'store' => ['label' => 'Store Profile', 'icon' => 'storefront', 'url' => route('seller.store.edit')],
            ]
        ]
    ];
@endphp

<div class="mb-6 bg-surface-container-lowest rounded-2xl border border-outline-variant/70 p-2 shadow-xs">
    <div class="flex items-center justify-between gap-4 overflow-x-auto pb-1 scrollbar-none">
        <div class="flex items-center gap-1.5 flex-nowrap">
            @foreach($navGroups as $grp)
                @foreach($grp['links'] as $key => $link)
                    <a
                        href="{{ $link['url'] }}"
                        class="flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold whitespace-nowrap transition-all {{ $active === $key ? 'bg-primary text-on-primary shadow-xs font-bold' : 'text-on-surface-variant hover:text-on-surface hover:bg-surface-container' }}">
                        <span class="material-symbols-outlined text-[17px]">{{ $link['icon'] }}</span>
                        <span>{{ $link['label'] }}</span>
                        @if(!empty($link['badge']))
                            <span class="inline-flex items-center justify-center px-1.5 py-0.2 text-[10px] font-extrabold rounded-full {{ $active === $key ? 'bg-white text-primary' : 'bg-rose-500 text-white' }}">
                                {{ $link['badge'] }}
                            </span>
                        @endif
                    </a>
                @endforeach
            @endforeach
        </div>

        <div class="hidden xl:flex items-center gap-2 pl-2 border-l border-outline-variant/60 flex-shrink-0">
            <a href="{{ route('seller.products.create') }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-primary/10 text-primary hover:bg-primary/20 text-xs font-bold transition-colors">
                <span class="material-symbols-outlined text-[16px]">add</span>
                <span>Add Product</span>
            </a>
            <a href="{{ route('stores.show', auth()->id()) }}" target="_blank" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl border border-outline-variant text-on-surface-variant hover:text-on-surface text-xs font-medium hover:bg-surface-container transition-colors" title="View Storefront">
                <span class="material-symbols-outlined text-[16px]">open_in_new</span>
            </a>
        </div>
    </div>
</div>
