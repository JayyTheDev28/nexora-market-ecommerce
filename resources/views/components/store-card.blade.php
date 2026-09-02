@props([
    'store', // array|object: name, logo, rating, productCount, url
])

@php $store = (object) $store; @endphp

<div class="bg-surface-container-lowest rounded-3xl p-8 border border-outline-variant hover:shadow-xl transition-shadow flex flex-col items-center text-center gap-4">
    <img src="{{ $store->logo }}" alt="{{ $store->name }} logo" class="w-24 h-24 rounded-full object-cover shadow-md">
    <h3 class="font-headline-md text-headline-md text-on-surface">{{ $store->name }}</h3>
    <div class="flex items-center gap-2 text-tertiary">
        <span class="material-symbols-outlined text-[20px]" style="font-variation-settings: 'FILL' 1">star</span>
        <span class="font-label-md text-label-md text-on-surface">{{ number_format($store->rating, 1) }} Rating</span>
    </div>
    <p class="font-body-sm text-body-sm text-on-surface-variant">{{ $store->productCount }}+ Products</p>
    <a
        href="{{ $store->url ?? '#' }}"
        class="mt-4 px-6 py-2 rounded-full border-2 border-primary text-primary font-label-md hover:bg-primary/5 transition-colors">
        Visit Store
    </a>
</div>
