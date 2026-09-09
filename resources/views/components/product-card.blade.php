@props([
    'product', // array|object: image, alt, seller, name, price, comparePrice, rating, reviewCount, badge, wishlisted, url
    'currency' => '₱',
])

@php
    $product = (object) $product;
    $badge = $product->badge ?? null; // 'new' | 'sale' | null
    $rating = $product->rating ?? null;
    $reviewCount = $product->reviewCount ?? null;
@endphp

<div class="group flex flex-col bg-surface-container-lowest rounded-2xl shadow-sm hover:shadow-xl transition-all duration-300">

    <div class="relative w-full aspect-square rounded-t-2xl overflow-hidden bg-surface-container">
        <img
            src="{{ $product->image }}"
            alt="{{ $product->alt ?? $product->name }}"
            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">

        <button
            type="button"
            class="absolute top-4 right-4 w-9 h-9 rounded-full bg-surface-container-lowest/90 backdrop-blur flex items-center justify-center shadow-sm transition-colors {{ ($product->wishlisted ?? false) ? 'text-error' : 'text-on-surface hover:text-error' }}"
            aria-label="Add to wishlist">
            <span class="material-symbols-outlined text-[20px]" @if($product->wishlisted ?? false) style="font-variation-settings: 'FILL' 1" @endif>favorite</span>
        </button>

        @if($badge === 'sale')
            <div class="absolute top-4 left-4 bg-error px-2 py-1 rounded text-on-error font-label-sm text-[10px] uppercase font-bold tracking-wider">
                Sale
            </div>
        @endif

        <div class="absolute bottom-4 left-4 bg-surface-container-lowest/90 backdrop-blur px-3 py-1 rounded-full flex items-center gap-1 shadow-sm">
            @if($rating)
                <span class="material-symbols-outlined text-[14px] text-tertiary" style="font-variation-settings: 'FILL' 1">star</span>
                <span class="font-label-sm text-label-sm text-on-surface">{{ number_format($rating, 1) }}</span>
                @if($reviewCount)
                    <span class="font-label-sm text-label-sm text-on-surface-variant ml-1">({{ $reviewCount }})</span>
                @endif
            @elseif($badge === 'new')
                <span class="material-symbols-outlined text-[14px] text-tertiary" style="font-variation-settings: 'FILL' 1">star</span>
                <span class="font-label-sm text-label-sm text-on-surface">NEW</span>
            @endif
        </div>
    </div>

    <a href="{{ $product->url ?? '#' }}" class="p-4 flex flex-col gap-2">
        <span class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider">{{ $product->seller }}</span>
        <h3 class="font-headline-sm text-headline-sm text-on-surface line-clamp-1">{{ $product->name }}</h3>
        <div class="flex items-center justify-between mt-2">
            <div class="flex items-center gap-2">
                <span class="font-headline-md text-headline-md text-primary">{{ $currency }}{{ number_format($product->price, 2) }}</span>
                @if(!empty($product->comparePrice))
                    <span class="font-body-sm text-body-sm text-outline line-through">{{ $currency }}{{ number_format($product->comparePrice, 2) }}</span>
                @endif
            </div>
            <button
                type="button"
                class="w-9 h-9 rounded-full bg-surface-container flex items-center justify-center text-on-surface hover:bg-primary hover:text-on-primary transition-colors"
                aria-label="Add to cart">
                <span class="material-symbols-outlined text-[20px]">shopping_cart</span>
            </button>
        </div>
    </a>

</div>
