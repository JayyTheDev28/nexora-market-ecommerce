@props([
    'product', // array|object: id, image, alt, seller, name, price, comparePrice, rating, reviewCount, badge, wishlisted, url
    'currency' => '₱',
])

@php
    $product = (object) $product;
    $badge = $product->badge ?? null; // 'new' | 'sale' | null
    $rating = $product->rating ?? null;
    $reviewCount = $product->reviewCount ?? null;
    $price = (float) ($product->price ?? 0);
    $comparePrice = !empty($product->comparePrice) ? (float) $product->comparePrice : null;
    $discountPercent = ($comparePrice && $comparePrice > $price) ? round((($comparePrice - $price) / $comparePrice) * 100) : null;
@endphp

<div
    class="group relative flex flex-col bg-surface-container-lowest rounded-2xl border border-outline-variant/60 hover:border-primary/50 shadow-xs hover:shadow-xl transition-all duration-300 overflow-hidden"
    x-data="{ justAdded: false, adding: false, wishlisted: {{ ($product->wishlisted ?? false) ? 'true' : 'false' }} }">

    {{-- Product Image Container --}}
    <div class="relative w-full aspect-square overflow-hidden bg-surface-container">
        <a href="{{ $product->url ?? '#' }}" class="block w-full h-full">
            <img
                src="{{ $product->image }}"
                alt="{{ $product->alt ?? $product->name }}"
                loading="lazy"
                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500 ease-out">
        </a>

        {{-- Wishlist Button --}}
        <button
            type="button"
            @click.stop.prevent="wishlisted = !wishlisted"
            class="absolute top-2.5 right-2.5 w-8 h-8 rounded-full bg-surface-container-lowest/80 backdrop-blur-md flex items-center justify-center shadow-xs transition-transform active:scale-90 hover:bg-surface-container-lowest cursor-pointer z-10"
            :class="wishlisted ? 'text-error' : 'text-on-surface-variant hover:text-error'"
            aria-label="Add to wishlist">
            <span class="material-symbols-outlined text-[18px]" :style="wishlisted ? `font-variation-settings: 'FILL' 1` : ''">favorite</span>
        </button>

        {{-- Discount & Status Badges --}}
        @if($discountPercent)
            <div class="absolute top-2.5 left-2.5 bg-gradient-to-r from-error to-rose-600 text-on-error font-extrabold text-[11px] px-2 py-0.5 rounded-md shadow-xs tracking-tight flex items-center gap-0.5">
                <span>-{{ $discountPercent }}%</span>
            </div>
        @elseif($badge === 'sale')
            <div class="absolute top-2.5 left-2.5 bg-error text-on-error font-bold text-[10px] uppercase px-2 py-0.5 rounded-md shadow-xs tracking-wider">
                Sale
            </div>
        @elseif($badge === 'new')
            <div class="absolute top-2.5 left-2.5 bg-primary text-on-primary font-bold text-[10px] uppercase px-2 py-0.5 rounded-md shadow-xs tracking-wider">
                New
            </div>
        @endif

        {{-- Rating Overlay Badge --}}
        @if($rating)
            <div class="absolute bottom-2.5 left-2.5 bg-surface-container-lowest/90 backdrop-blur-md px-2 py-0.5 rounded-md flex items-center gap-1 shadow-xs border border-outline-variant/40">
                <span class="material-symbols-outlined text-[13px] text-tertiary" style="font-variation-settings: 'FILL' 1">star</span>
                <span class="font-bold text-[11px] text-on-surface">{{ number_format($rating, 1) }}</span>
                @if($reviewCount)
                    <span class="text-[10px] text-on-surface-variant font-medium">({{ $reviewCount }})</span>
                @endif
            </div>
        @endif
    </div>

    {{-- Product Information & Price --}}
    <div class="p-3.5 flex flex-col flex-1 justify-between gap-2.5">
        <div class="flex flex-col gap-1">
            {{-- Seller Line --}}
            <span class="text-[11px] font-medium text-on-surface-variant uppercase tracking-wider truncate">
                {{ $product->seller ?? 'Verified Seller' }}
            </span>

            {{-- Product Name --}}
            <a href="{{ $product->url ?? '#' }}" class="group-hover:text-primary transition-colors">
                <h3 class="font-headline-sm text-xs md:text-sm font-semibold text-on-surface line-clamp-2 leading-snug min-h-[2.4rem]">
                    {{ $product->name }}
                </h3>
            </a>
        </div>

        {{-- Price & Cart Action Row --}}
        <div class="flex items-end justify-between gap-2 pt-1 border-t border-outline-variant/40">
            <div class="flex flex-col min-w-0">
                @if(!empty($comparePrice) && $comparePrice > $price)
                    <span class="text-[11px] text-outline line-through leading-none">
                        {{ $currency }}{{ number_format($comparePrice, 2) }}
                    </span>
                @endif
                <span class="font-headline-md text-sm md:text-base font-extrabold text-primary tracking-tight">
                    {{ $currency }}{{ number_format($price, 2) }}
                </span>
            </div>

            {{-- Quick Add to Cart Button --}}
            <button
                type="button"
                @click.stop.prevent="
                    if (adding) return;
                    adding = true;
                    api('/cart/items', { method: 'POST', body: { product_id: {{ (int) ($product->id ?? 0) }}, quantity: 1 } })
                        .then((data) => {
                            if (!data) return; // 401 -> redirected to /login
                            window.dispatchEvent(new CustomEvent('cart-updated', { detail: { count: data.cartCount } }));
                            justAdded = true;
                            setTimeout(() => justAdded = false, 1500);
                        })
                        .catch((e) => alert(e.message))
                        .finally(() => adding = false);
                "
                :disabled="adding"
                class="w-9 h-9 rounded-xl flex items-center justify-center transition-all duration-200 cursor-pointer shadow-xs disabled:opacity-60 flex-shrink-0"
                :class="justAdded ? 'bg-emerald-600 text-white scale-105' : 'bg-surface-container text-on-surface hover:bg-primary hover:text-on-primary hover:shadow-md'"
                :aria-label="justAdded ? 'Added to cart' : 'Add to cart'">
                
                {{-- Spinner while adding --}}
                <span x-show="adding" x-cloak class="inline-block w-4 h-4 border-2 border-current border-t-transparent rounded-full animate-spin"></span>
                {{-- Checkmark on added --}}
                <span x-show="!adding && justAdded" x-cloak class="material-symbols-outlined text-[18px]">check</span>
                {{-- Cart icon idle --}}
                <span x-show="!adding && !justAdded" class="material-symbols-outlined text-[19px]">add_shopping_cart</span>
            </button>
        </div>
    </div>

</div>
