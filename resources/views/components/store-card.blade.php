@props([
    'store', // array|object: name, logo, rating, productCount, url
])

@php $store = (object) $store; @endphp

<div class="group relative bg-surface-container-lowest rounded-3xl border border-outline-variant/60 hover:border-primary/50 overflow-hidden hover:shadow-xl transition-all duration-300 flex flex-col">
    {{-- Header Banner Background --}}
    <div class="h-20 bg-gradient-to-r from-primary/10 via-surface-container to-tertiary/10 relative">
        <div class="absolute top-3 right-3 px-2.5 py-0.5 rounded-full bg-surface-container-lowest/90 backdrop-blur-md text-[10px] font-bold text-primary border border-primary/20 shadow-xs">
            Verified Store
        </div>
    </div>

    {{-- Store Profile Body --}}
    <div class="px-5 pb-5 -mt-10 flex flex-col items-center text-center flex-1">
        <div class="relative w-18 h-18 rounded-2xl p-1 bg-surface-container-lowest shadow-md mb-3 group-hover:scale-105 transition-transform">
            <img src="{{ $store->logo }}" alt="{{ $store->name }} logo" class="w-full h-full rounded-xl object-cover bg-surface-container">
        </div>

        <h3 class="font-headline-sm text-sm md:text-base font-bold text-on-surface group-hover:text-primary transition-colors line-clamp-1">
            {{ $store->name }}
        </h3>

        <div class="flex items-center gap-2 mt-1.5 text-xs">
            <div class="flex items-center gap-1 text-tertiary font-bold">
                <span class="material-symbols-outlined text-[15px]" style="font-variation-settings: 'FILL' 1">star</span>
                <span>{{ number_format($store->rating, 1) }}</span>
            </div>
            <span class="text-outline-variant">•</span>
            <span class="text-on-surface-variant font-medium">{{ $store->productCount }}+ Products</span>
        </div>

        <div class="w-full border-t border-outline-variant/60 my-4"></div>

        @php
            $storeUrl = !empty($store->url) && $store->url !== '#'
                ? $store->url
                : (!empty($store->id) ? route('stores.show', $store->id) : (!empty($store->seller_id) ? route('stores.show', $store->seller_id) : url('/catalog')));
        @endphp
        <a
            href="{{ $storeUrl }}"
            class="w-full text-center py-2.5 px-4 rounded-xl bg-surface-container hover:bg-primary hover:text-on-primary font-label-md text-xs font-semibold text-on-surface transition-all duration-200">
            Visit Store
        </a>
    </div>
</div>
