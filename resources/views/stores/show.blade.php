@extends('layouts.app')

@section('title', ($profile->business_name ?? $seller->full_name) . ' — Official Store on Nexora Market')

@section('content')
<div class="w-full bg-surface min-h-screen pb-16">

    {{-- Banner Area --}}
    <div class="w-full h-48 md:h-64 bg-gradient-to-r from-primary/30 to-surface-container relative overflow-hidden">
        @if(!empty($profile->banner_path))
            <img src="{{ Storage::url($profile->banner_path) }}" alt="Banner" class="w-full h-full object-cover">
        @else
            <div class="w-full h-full flex items-center justify-center bg-gradient-to-r from-primary/20 via-surface-container-high to-primary/10">
                <span class="font-display-lg text-primary/30 text-5xl font-black uppercase tracking-wider select-none">
                    {{ $profile->business_name ?? 'Nexora Merchant' }}
                </span>
            </div>
        @endif
    </div>

    <div class="max-w-none px-margin-mobile md:px-margin-desktop -mt-16 relative z-10">

        {{-- Store Header Dossier --}}
        <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 md:p-8 shadow-sm mb-8">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">

                <div class="flex items-center gap-5">
                    <div class="w-24 h-24 rounded-2xl overflow-hidden border-2 border-surface bg-surface-container-high shadow-md shrink-0 flex items-center justify-center">
                        @if(!empty($profile->logo_path))
                            <img src="{{ Storage::url($profile->logo_path) }}" alt="Logo" class="w-full h-full object-cover">
                        @else
                            <span class="text-3xl font-black text-primary">
                                {{ strtoupper(substr($profile->business_name ?? 'S', 0, 1)) }}
                            </span>
                        @endif
                    </div>
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <h1 class="font-headline-lg text-headline-lg text-on-surface font-extrabold">
                                {{ $profile->business_name ?? $seller->full_name }}
                            </h1>
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-primary/10 text-primary border border-primary/20">
                                <span class="material-symbols-outlined text-[14px]">verified</span> Verified Store
                            </span>
                        </div>
                        <p class="font-body-sm text-xs text-on-surface-variant mt-1 max-w-xl">
                            {{ $profile->description ?: 'Welcome to our official store on Nexora Market. Discover authentic goods, fast fulfillment, and guaranteed quality.' }}
                        </p>
                        <div class="flex items-center gap-4 text-xs text-on-surface-variant mt-3 flex-wrap">
                            <span class="flex items-center gap-1">
                                <span class="material-symbols-outlined text-[16px] text-amber-500 fill-1">star</span>
                                <strong class="text-on-surface">{{ $averageRating }}</strong> ({{ $totalReviews }} reviews)
                            </span>
                            <span>•</span>
                            <span class="flex items-center gap-1">
                                <span class="material-symbols-outlined text-[16px] text-primary">group</span>
                                <strong class="text-on-surface">{{ $followersCount }}</strong> followers
                            </span>
                            <span>•</span>
                            <span class="flex items-center gap-1">
                                <span class="material-symbols-outlined text-[16px] text-emerald-500">inventory_2</span>
                                <strong class="text-on-surface">{{ $products->total() }}</strong> products
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Store Actions --}}
                <div class="flex items-center gap-3 shrink-0">
                    @auth
                        @if(auth()->user()->role === 'buyer')
                            <form method="POST" action="{{ route('buyer.stores.follow-toggle', $seller->id) }}">
                                @csrf
                                <button type="submit" class="px-5 py-2.5 rounded-full font-label-md text-sm font-bold transition-colors flex items-center gap-1.5 {{ $isFollowing ? 'bg-surface-container border border-outline-variant text-on-surface hover:bg-surface-container-high' : 'bg-primary text-on-primary hover:bg-primary/90' }}">
                                    <span class="material-symbols-outlined text-[18px]">{{ $isFollowing ? 'check' : 'person_add' }}</span>
                                    {{ $isFollowing ? 'Following' : 'Follow Store' }}
                                </button>
                            </form>
                            <form method="POST" action="{{ route('buyer.chat.start') }}">
                                @csrf
                                <input type="hidden" name="seller_id" value="{{ $seller->id }}">
                                <button type="submit" class="px-4 py-2.5 rounded-full font-label-md text-sm font-bold border border-outline-variant text-on-surface hover:bg-surface-container transition-colors flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-[18px]">chat</span>
                                    Chat
                                </button>
                            </form>
                        @endif
                    @else
                        <a href="{{ route('login') }}" class="px-5 py-2.5 rounded-full font-label-md text-sm font-bold bg-primary text-on-primary hover:bg-primary/90 transition-colors">
                            Follow Store
                        </a>
                    @endauth
                </div>

            </div>
        </div>

        {{-- Vacation Mode Alert --}}
        @if(!empty($profile->is_vacation_mode))
            <div class="mb-8 p-5 rounded-2xl bg-amber-500/10 border-2 border-amber-500/30 flex items-start gap-4">
                <span class="material-symbols-outlined text-amber-500 text-[28px] shrink-0">beach_access</span>
                <div>
                    <h3 class="font-headline-sm text-sm font-bold text-on-surface">Store is Currently on Vacation</h3>
                    <p class="font-body-sm text-xs text-on-surface-variant mt-1">
                        {{ $profile->vacation_notice ?: 'This seller is temporarily away. You can explore their catalog, but adding to cart and checkout is temporarily disabled.' }}
                    </p>
                </div>
            </div>
        @endif

        {{-- Store Vouchers Section --}}
        @if($storeVouchers->isNotEmpty())
            <div class="mb-8">
                <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold mb-4 flex items-center gap-2">
                    <span class="material-symbols-outlined text-amber-500 text-[22px]">confirmation_number</span>
                    Exclusive Store Vouchers
                </h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    @foreach($storeVouchers as $voucher)
                        @php
                            $isClaimed = in_array($voucher->id, $claimedVoucherIds, true);
                        @endphp
                        <div class="bg-surface-container-lowest border border-outline-variant rounded-2xl p-4 flex flex-col justify-between relative overflow-hidden shadow-sm">
                            <div class="absolute -right-6 -bottom-6 opacity-5 pointer-events-none">
                                <span class="material-symbols-outlined text-[100px]">loyalty</span>
                            </div>
                            <div>
                                <div class="flex items-center justify-between gap-2 mb-2">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-600 border border-amber-500/20 uppercase tracking-wider">
                                        {{ $voucher->code }}
                                    </span>
                                    <span class="text-xs text-on-surface-variant">Min: ₱{{ number_format($voucher->min_spend, 0) }}</span>
                                </div>
                                <p class="font-headline-md text-headline-md text-on-surface font-extrabold text-[22px]">
                                    @if($voucher->type === 'flat')
                                        ₱{{ number_format($voucher->value, 0) }} OFF
                                    @else
                                        {{ (int) $voucher->value }}% OFF
                                    @endif
                                </p>
                                @if($voucher->max_discount)
                                    <p class="text-[11px] text-on-surface-variant">Max discount: ₱{{ number_format($voucher->max_discount, 0) }}</p>
                                @endif
                            </div>
                            <div class="mt-4 pt-3 border-t border-outline-variant/60 flex items-center justify-between">
                                <span class="text-[10px] text-on-surface-variant">
                                    {{ $voucher->expires_at ? 'Exp: ' . $voucher->expires_at->format('M j') : 'No expiry' }}
                                </span>
                                @auth
                                    @if(auth()->user()->role === 'buyer')
                                        <button
                                            type="button"
                                            onclick="claimStoreVoucher({{ $voucher->id }}, this)"
                                            class="px-3 py-1 rounded-full text-xs font-bold transition-colors {{ $isClaimed ? 'bg-surface-container text-on-surface-variant cursor-default' : 'bg-primary text-on-primary hover:bg-primary/90' }}"
                                            {{ $isClaimed ? 'disabled' : '' }}>
                                            {{ $isClaimed ? 'Claimed' : 'Claim' }}
                                        </button>
                                    @endif
                                @else
                                    <a href="{{ route('login') }}" class="px-3 py-1 rounded-full text-xs font-bold bg-primary text-on-primary">Claim</a>
                                @endauth
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Store Category Filter Tabs & Search --}}
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div class="flex items-center gap-1.5 overflow-x-auto pb-1 scrollbar-none">
                <a href="{{ route('stores.show', $seller->id) }}" class="px-4 py-2 rounded-full text-xs font-bold whitespace-nowrap transition-colors {{ !request()->query('store_category') ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface hover:bg-surface-container-high' }}">
                    All Products
                </a>
                @foreach($storeCategories as $category)
                    <a href="{{ route('stores.show', [$seller->id, 'store_category' => $category->id]) }}" class="px-4 py-2 rounded-full text-xs font-bold whitespace-nowrap transition-colors {{ request()->query('store_category') == $category->id ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface hover:bg-surface-container-high' }}">
                        {{ $category->name }}
                    </a>
                @endforeach
            </div>

            <form method="GET" action="{{ route('stores.show', $seller->id) }}" class="flex items-center gap-2">
                @if(request()->query('store_category'))
                    <input type="hidden" name="store_category" value="{{ request()->query('store_category') }}">
                @endif
                <div class="relative">
                    <input
                        type="text"
                        name="q"
                        value="{{ request()->query('q') }}"
                        placeholder="Search store products..."
                        class="pl-8 pr-3 py-1.5 rounded-full text-xs bg-surface-container-lowest border border-outline-variant text-on-surface focus:outline-none focus:border-primary w-48 sm:w-64">
                    <span class="material-symbols-outlined text-[16px] text-outline-variant absolute left-2.5 top-2 pointer-events-none">search</span>
                </div>
                <select name="sort" onchange="this.form.submit()" class="py-1.5 px-3 rounded-full text-xs bg-surface-container-lowest border border-outline-variant text-on-surface focus:outline-none focus:border-primary cursor-pointer">
                    <option value="latest" {{ request()->query('sort') === 'latest' ? 'selected' : '' }}>Latest</option>
                    <option value="price_low" {{ request()->query('sort') === 'price_low' ? 'selected' : '' }}>Price: Low to High</option>
                    <option value="price_high" {{ request()->query('sort') === 'price_high' ? 'selected' : '' }}>Price: High to Low</option>
                    <option value="name" {{ request()->query('sort') === 'name' ? 'selected' : '' }}>Name A-Z</option>
                </select>
            </form>
        </div>

        {{-- Products Grid --}}
        @if($products->isEmpty())
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-12 text-center">
                <span class="material-symbols-outlined text-[48px] text-outline-variant mb-2">search_off</span>
                <h3 class="font-headline-sm text-headline-sm text-on-surface font-bold">No Products Found</h3>
                <p class="font-body-sm text-xs text-on-surface-variant mt-1">This store does not have products matching the current filter.</p>
            </div>
        @else
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
                @foreach($products as $product)
                    <a href="{{ route('products.show', $product->id) }}" class="group bg-surface-container-lowest rounded-2xl border border-outline-variant overflow-hidden hover:shadow-lg transition-all flex flex-col justify-between">
                        <div class="aspect-square w-full bg-surface-container-low overflow-hidden relative">
                            @php
                                $img = !empty($product->gallery[0]) ? $product->gallery[0] : 'https://placehold.co/600x600/e5eeff/0058be?text=' . urlencode($product->name);
                            @endphp
                            <img src="{{ $img }}" alt="{{ $product->name }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                            @if($product->isFlashSaleActive())
                                <span class="absolute top-2 left-2 px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-error text-on-error shadow-sm flex items-center gap-0.5">
                                    <span class="material-symbols-outlined text-[12px]">flash_on</span> Flash Sale
                                </span>
                            @elseif($product->compare_price && $product->compare_price > $product->price)
                                @php
                                    $pct = round((($product->compare_price - $product->price) / $product->compare_price) * 100);
                                @endphp
                                <span class="absolute top-2 left-2 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500 text-white shadow-sm">
                                    -{{ $pct }}%
                                </span>
                            @endif
                            @if($product->stock <= 0)
                                <div class="absolute inset-0 bg-black/60 backdrop-blur-xs flex items-center justify-center">
                                    <span class="px-3 py-1 rounded-full text-xs font-bold bg-surface-container text-on-surface">Out of Stock</span>
                                </div>
                            @endif
                        </div>
                        <div class="p-3.5 flex flex-col justify-between flex-1">
                            <div>
                                <h3 class="font-label-md text-xs font-bold text-on-surface line-clamp-2 group-hover:text-primary transition-colors">
                                    {{ $product->name }}
                                </h3>
                                @if($product->storeCategory)
                                    <span class="text-[10px] text-on-surface-variant inline-block mt-0.5">{{ $product->storeCategory->name }}</span>
                                @endif
                            </div>
                            <div class="mt-3 pt-2 border-t border-outline-variant/50 flex items-baseline justify-between">
                                <div>
                                    <span class="font-headline-sm text-sm font-black text-primary">
                                        ₱{{ number_format($product->effective_price, 2) }}
                                    </span>
                                    @if($product->compare_price && $product->compare_price > $product->price)
                                        <span class="text-[10px] text-on-surface-variant line-through ml-1">
                                            ₱{{ number_format($product->compare_price, 2) }}
                                        </span>
                                    @endif
                                </div>
                                <span class="text-[10px] text-on-surface-variant flex items-center gap-0.5">
                                    <span class="material-symbols-outlined text-[12px] text-amber-500 fill-1">star</span>
                                    {{ $product->averageRating() }}
                                </span>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="mt-8">
                {{ $products->links() }}
            </div>
        @endif

    </div>
</div>

<script>
function claimStoreVoucher(voucherId, btn) {
    btn.disabled = true;
    btn.innerText = 'Claiming...';

    fetch(`/buyer/vouchers/${voucherId}/claim`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json'
        }
    })
    .then(r => r.json())
    .then(data => {
        btn.innerText = 'Claimed';
        btn.classList.remove('bg-primary', 'text-on-primary', 'hover:bg-primary/90');
        btn.classList.add('bg-surface-container', 'text-on-surface-variant', 'cursor-default');
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerText = 'Retry';
    });
}
</script>
@endsection
