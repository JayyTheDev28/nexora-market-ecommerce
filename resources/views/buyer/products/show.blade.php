@extends('layouts.app')

@section('title', $product['name'] . ' — Nexora Market')

@section('content')
<div
    x-data="productDetail({
        product: {{ collect($product)->only(['id','name','price','image','seller'])->toJson() }},
        gallery: {{ json_encode($product['gallery'] ?: [$product['image']]) }},
        colors: {{ json_encode($product['colors']) }},
        sizes: {{ json_encode($product['sizes']) }},
        stock: {{ (int) $product['stock'] }}
    })"
    class="w-full bg-surface py-6 md:py-10">

    <div class="max-w-none px-4 md:px-8">

        {{-- Minimal Breadcrumb Navigation --}}
        <nav class="flex items-center gap-2 text-xs font-medium text-on-surface-variant mb-6 md:mb-8 overflow-x-auto whitespace-nowrap">
            <a href="{{ auth()->check() ? url(auth()->user()->homeRoute()) : url('/') }}" class="hover:text-primary transition-colors">Home</a>
            <span class="text-outline-variant">/</span>
            <a href="{{ url('/catalog') }}" class="hover:text-primary transition-colors">Catalog</a>
            <span class="text-outline-variant">/</span>
            <span class="text-on-surface font-semibold truncate max-w-xs md:max-w-md">{{ $product['name'] }}</span>
        </nav>

        {{-- Main Product Layout (2 Columns on Desktop) --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12 items-start">

            {{-- Left Column: Gallery & Assurances --}}
            <div class="lg:col-span-6 flex flex-col gap-4 md:gap-6 sticky top-24">
                
                {{-- Main Gallery Image --}}
                <div class="relative w-full aspect-square rounded-3xl overflow-hidden bg-surface-container border border-outline-variant/60 shadow-xs">
                    <template x-for="(img, i) in gallery" :key="i">
                        <img
                            :src="img"
                            x-show="activeImage === i"
                            x-cloak
                            class="w-full h-full object-cover transition-all duration-300">
                    </template>
                </div>

                {{-- Gallery Thumbnails --}}
                <div class="flex gap-3 overflow-x-auto pb-1" x-show="gallery.length > 1">
                    <template x-for="(img, i) in gallery" :key="i">
                        <button
                            type="button"
                            @click="activeImage = i"
                            class="w-16 h-16 md:w-20 md:h-20 rounded-2xl overflow-hidden border-2 flex-shrink-0 cursor-pointer transition-all duration-200"
                            :class="activeImage === i ? 'border-primary ring-2 ring-primary/20 scale-102' : 'border-outline-variant/60 hover:border-outline opacity-80 hover:opacity-100'">
                            <img :src="img" class="w-full h-full object-cover">
                        </button>
                    </template>
                </div>

                {{-- Buyer Protection & Assurances Card (Clean text list) --}}
                <div class="p-5 bg-surface-container-lowest rounded-2xl border border-outline-variant/60 flex flex-col gap-3 shadow-xs">
                    <h4 class="text-xs font-bold text-on-surface uppercase tracking-wider">Nexora Buyer Assurance</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs text-on-surface-variant">
                        <div class="flex items-center gap-2">
                            <span class="w-1.5 h-1.5 rounded-full bg-primary flex-shrink-0"></span>
                            <span>100% Authentic</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="w-1.5 h-1.5 rounded-full bg-primary flex-shrink-0"></span>
                            <span>7-Day Return Policy</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="w-1.5 h-1.5 rounded-full bg-primary flex-shrink-0"></span>
                            <span>Cash on Delivery</span>
                        </div>
                    </div>
                </div>

            </div>

            {{-- Right Column: Product Details & Purchase Actions --}}
            <div class="lg:col-span-6 flex flex-col gap-5 md:gap-6">
                
                {{-- Seller Header & Title --}}
                <div class="flex flex-col gap-1.5">
                    <a href="{{ url('/catalog') }}" class="text-xs font-bold text-primary uppercase tracking-wider hover:underline self-start">
                        {{ $product['seller'] }}
                    </a>
                    <h1 class="font-headline-lg text-xl md:text-2xl lg:text-3xl font-bold text-on-surface leading-snug">
                        {{ $product['name'] }}
                    </h1>
                </div>

                {{-- Rating & Review Summary --}}
                <div class="flex items-center gap-3 text-xs">
                    <div class="flex items-center gap-1 text-tertiary font-bold">
                        <span class="material-symbols-outlined text-[16px]" style="font-variation-settings: 'FILL' 1">star</span>
                        <span>{{ number_format($product['rating'] ?: 5.0, 1) }}</span>
                    </div>
                    <span class="text-outline-variant">•</span>
                    <span class="text-on-surface-variant font-medium">
                        {{ $product['reviewCount'] }} customer reviews
                    </span>
                    <span class="text-outline-variant">•</span>
                    <span class="text-emerald-700 font-semibold">Verified Product</span>
                </div>

                {{-- Price & Savings Banner --}}
                <div class="p-4 bg-surface-container-low/70 rounded-2xl border border-outline-variant/60 flex flex-wrap items-baseline gap-3">
                    <span class="font-display-lg text-2xl md:text-3xl font-extrabold text-primary tracking-tight">
                        ₱{{ number_format($product['price'], 2) }}
                    </span>

                    @if(!empty($product['comparePrice']) && $product['comparePrice'] > $product['price'])
                        <span class="text-sm md:text-base text-outline line-through">
                            ₱{{ number_format($product['comparePrice'], 2) }}
                        </span>

                        @php
                            $savings = $product['comparePrice'] - $product['price'];
                            $percent = round(($savings / $product['comparePrice']) * 100);
                        @endphp
                        <span class="px-2 py-0.5 rounded-md bg-error/10 text-error font-extrabold text-xs">
                            Save ₱{{ number_format($savings, 2) }} (-{{ $percent }}%)
                        </span>
                    @endif
                </div>

                {{-- Description --}}
                <div class="text-xs md:text-sm text-on-surface-variant leading-relaxed">
                    <p>{{ $product['description'] }}</p>
                </div>

                <div class="border-t border-outline-variant/60"></div>

                {{-- Color Selector --}}
                <div x-show="colors.length > 0" class="flex flex-col gap-2.5">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-bold text-on-surface uppercase tracking-wider">Color</span>
                        <span class="text-primary font-semibold" x-text="selectedColor || 'Choose a color'"></span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="color in colors" :key="color">
                            <button
                                type="button"
                                @click="selectedColor = color"
                                class="px-4 py-2 rounded-xl border text-xs font-semibold transition-all cursor-pointer"
                                :class="selectedColor === color ? 'border-primary bg-primary/10 text-primary ring-2 ring-primary/20' : 'border-outline-variant bg-surface-container-lowest text-on-surface hover:border-outline'"
                                x-text="color">
                            </button>
                        </template>
                    </div>
                </div>

                {{-- Size Selector --}}
                <div x-show="sizes.length > 0" class="flex flex-col gap-2.5">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-bold text-on-surface uppercase tracking-wider">Size</span>
                        <span class="text-primary font-semibold" x-text="selectedSize || 'Choose a size'"></span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="size in sizes" :key="size">
                            <button
                                type="button"
                                @click="selectedSize = size"
                                class="min-w-[48px] px-3.5 py-2 rounded-xl border text-xs font-semibold transition-all cursor-pointer text-center"
                                :class="selectedSize === size ? 'border-primary bg-primary/10 text-primary ring-2 ring-primary/20' : 'border-outline-variant bg-surface-container-lowest text-on-surface hover:border-outline'"
                                x-text="size">
                            </button>
                        </template>
                    </div>
                </div>

                {{-- Stock Status Indicator --}}
                <div class="flex items-center gap-2 text-xs font-semibold">
                    <template x-if="stock > 10">
                        <span class="flex items-center gap-1.5 text-primary">
                            <span class="w-2 h-2 rounded-full bg-primary"></span>
                            In Stock
                        </span>
                    </template>
                    <template x-if="stock > 0 && stock <= 10">
                        <span class="flex items-center gap-1.5 text-tertiary">
                            <span class="w-2 h-2 rounded-full bg-tertiary"></span>
                            Only <span x-text="stock"></span> items left
                        </span>
                    </template>
                    <template x-if="stock === 0">
                        <span class="flex items-center gap-1.5 text-error">
                            <span class="w-2 h-2 rounded-full bg-error"></span>
                            Out of Stock
                        </span>
                    </template>
                </div>

                {{-- Quantity Stepper & Add to Cart --}}
                @if(auth()->check() && !auth()->user()->isBuyer())
                    <div class="mt-2 p-4 rounded-2xl bg-surface-container border border-outline-variant/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="flex items-start gap-3">
                            <span class="material-symbols-outlined text-primary text-[22px] mt-0.5">info</span>
                            <div>
                                <p class="text-xs font-bold text-on-surface">Purchasing is exclusive to Buyer accounts</p>
                                <p class="text-[11px] text-on-surface-variant">You are currently signed in as a {{ auth()->user()->roleLabel() }}.</p>
                            </div>
                        </div>
                        <a href="{{ auth()->user()->homeRoute() }}" class="px-4 py-2 rounded-full bg-primary text-on-primary font-label-md text-xs font-semibold hover:bg-primary/90 transition-colors text-center shrink-0">
                            Go to Dashboard
                        </a>
                    </div>
                @else
                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 pt-2">
                        {{-- Stepper --}}
                        <div class="flex items-center justify-between sm:justify-start border border-outline-variant rounded-full bg-surface-container-lowest p-1 shadow-xs">
                            <button
                                type="button"
                                @click="qty = Math.max(1, qty - 1)"
                                class="w-10 h-10 rounded-full flex items-center justify-center text-on-surface hover:bg-surface-container transition-colors cursor-pointer"
                                aria-label="Decrease quantity">
                                <span class="material-symbols-outlined text-[18px]">remove</span>
                            </button>
                            <span class="w-12 text-center text-xs font-bold text-on-surface" x-text="qty"></span>
                            <button
                                type="button"
                                @click="qty = Math.min(stock || 99, qty + 1)"
                                class="w-10 h-10 rounded-full flex items-center justify-center text-on-surface hover:bg-surface-container transition-colors cursor-pointer"
                                aria-label="Increase quantity">
                                <span class="material-symbols-outlined text-[18px]">add</span>
                            </button>
                        </div>

                        {{-- Add to Cart Action --}}
                        <button
                            type="button"
                            :disabled="stock === 0 || !canAddToCart || addingToCart"
                            @click="addToCart()"
                            class="flex-1 flex items-center justify-center gap-2 bg-primary text-on-primary font-label-md text-xs md:text-sm font-semibold px-8 py-3.5 rounded-full shadow-lg shadow-primary/25 hover:bg-primary/90 transition-all cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                            
                            <span x-show="addingToCart" x-cloak class="w-4 h-4 border-2 border-current border-t-transparent rounded-full animate-spin"></span>
                            <span x-show="!addingToCart && justAdded" x-cloak class="material-symbols-outlined text-[18px]">check</span>
                            <span x-text="justAdded ? 'Added to Cart' : 'Add to Cart'"></span>
                        </button>
                    </div>

                    {{-- Variant Selection Validation Notice --}}
                    <p x-show="!canAddToCart" x-cloak class="text-xs text-error font-medium">
                        Please select <span x-text="!selectedColor && colors.length ? 'a color' : 'a size'"></span> before adding to cart.
                    </p>
                @endif

                {{-- Mini Seller Card --}}
                <div class="mt-4 p-4 rounded-2xl border border-outline-variant/60 bg-surface-container-lowest flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-10 h-10 rounded-xl bg-primary/10 text-primary font-bold text-sm flex items-center justify-center flex-shrink-0">
                            {{ strtoupper(substr($product['seller'], 0, 1)) }}
                        </div>
                        <div class="flex flex-col min-w-0">
                            <h4 class="text-xs font-bold text-on-surface truncate">{{ $product['seller'] }}</h4>
                            <span class="text-[11px] text-on-surface-variant">Verified Merchant • Rapid Dispatch</span>
                        </div>
                    </div>
                    <a href="{{ url('/catalog') }}" class="px-3.5 py-1.5 rounded-xl border border-outline-variant text-xs font-semibold text-on-surface hover:bg-surface-container transition-colors flex-shrink-0">
                        Visit Store
                    </a>
                </div>

            </div>
        </div>

        {{-- Related Products Section --}}
        @if(count($related))
            <div class="mt-16 md:mt-20 pt-10 border-t border-outline-variant/60">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="font-headline-lg text-lg md:text-2xl font-bold text-on-surface">You Might Also Like</h2>
                    <a href="{{ url('/catalog') }}" class="text-primary text-xs font-semibold hover:underline">
                        View More
                    </a>
                </div>

                {{-- 2-column mobile, 4-column desktop grid --}}
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-5">
                    @foreach($related as $item)
                        <x-product-card :product="$item" />
                    @endforeach
                </div>
            </div>
        @endif

    </div>
</div>

@push('scripts')
<script>
    function productDetail({ product, gallery, colors, sizes, stock }) {
        return {
            product,
            gallery,
            colors,
            sizes,
            stock,
            activeImage: 0,
            selectedColor: colors.length === 1 ? colors[0] : null,
            selectedSize: sizes.length === 1 ? sizes[0] : null,
            qty: 1,
            justAdded: false,
            addingToCart: false,

            get canAddToCart() {
                const colorOk = this.colors.length === 0 || !!this.selectedColor;
                const sizeOk = this.sizes.length === 0 || !!this.selectedSize;
                return colorOk && sizeOk;
            },

            addToCart() {
                if (!this.canAddToCart || this.stock === 0 || this.addingToCart) return;
                const variant = {};
                if (this.selectedColor) variant.color = this.selectedColor;
                if (this.selectedSize) variant.size = this.selectedSize;

                this.addingToCart = true;
                api('/cart/items', {
                    method: 'POST',
                    body: {
                        product_id: this.product.id,
                        variant: Object.keys(variant).length ? variant : null,
                        quantity: this.qty,
                    },
                })
                    .then((data) => {
                        if (!data) return; // 401 -> redirected to /login
                        window.dispatchEvent(new CustomEvent('cart-updated', { detail: { count: data.cartCount } }));
                        this.justAdded = true;
                        setTimeout(() => (this.justAdded = false), 1500);
                    })
                    .catch((e) => alert(e.message))
                    .finally(() => (this.addingToCart = false));
            },
        };
    }
</script>
@endpush

@endsection
