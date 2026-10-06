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
    class="w-full bg-surface py-8">

    <div class="max-w-none px-margin-mobile md:px-margin-desktop">

        {{-- Breadcrumb --}}
        <div class="flex items-center gap-2 font-body-sm text-body-sm text-on-surface-variant mb-6">
            <a href="{{ url('/') }}" class="hover:text-primary">Home</a>
            <span class="material-symbols-outlined text-[14px]">chevron_right</span>
            <a href="{{ url('/catalog') }}" class="hover:text-primary">Catalog</a>
            <span class="material-symbols-outlined text-[14px]">chevron_right</span>
            <span class="text-on-surface">{{ $product['name'] }}</span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10">

            {{-- Gallery --}}
            <div class="flex flex-col gap-3">
                <div class="relative w-full aspect-square rounded-2xl overflow-hidden bg-surface-container">
                    <template x-for="(img, i) in gallery" :key="i">
                        <img :src="img" x-show="activeImage === i" x-cloak class="w-full h-full object-cover">
                    </template>
                </div>
                <div class="flex gap-3" x-show="gallery.length > 1">
                    <template x-for="(img, i) in gallery" :key="i">
                        <button
                            type="button"
                            @click="activeImage = i"
                            class="w-16 h-16 rounded-xl overflow-hidden border-2 flex-shrink-0"
                            :class="activeImage === i ? 'border-primary' : 'border-transparent'">
                            <img :src="img" class="w-full h-full object-cover">
                        </button>
                    </template>
                </div>
            </div>

            {{-- Details --}}
            <div class="flex flex-col gap-4">
                <div>
                    <span class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider">{{ $product['seller'] }}</span>
                    <h1 class="font-headline-lg text-headline-lg text-on-surface mt-1">{{ $product['name'] }}</h1>
                </div>

                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-1 text-tertiary">
                        <span class="material-symbols-outlined text-[16px]" style="font-variation-settings: 'FILL' 1">star</span>
                        <span class="font-label-md text-label-md text-on-surface">{{ number_format($product['rating'], 1) }}</span>
                    </div>
                    <span class="font-body-sm text-body-sm text-on-surface-variant">({{ $product['reviewCount'] }} reviews)</span>
                </div>

                <div class="flex items-center gap-3">
                    <span class="font-display-lg text-display-lg text-primary" style="font-size: 28px;">₱{{ number_format($product['price'], 2) }}</span>
                    @if(!empty($product['comparePrice']))
                        <span class="font-body-md text-body-md text-outline line-through">₱{{ number_format($product['comparePrice'], 2) }}</span>
                    @endif
                </div>

                <p class="font-body-md text-body-md text-on-surface-variant leading-relaxed">{{ $product['description'] }}</p>

                <div class="border-t border-outline-variant my-2"></div>

                {{-- Color selector --}}
                <div x-show="colors.length > 0" class="flex flex-col gap-2">
                    <span class="font-label-md text-label-md text-on-surface-variant">
                        Color: <span class="text-on-surface font-semibold" x-text="selectedColor || 'Select a color'"></span>
                    </span>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="color in colors" :key="color">
                            <button
                                type="button"
                                @click="selectedColor = color"
                                class="px-4 py-2 rounded-full border text-body-sm font-body-sm transition-colors"
                                :class="selectedColor === color ? 'border-primary bg-primary/10 text-primary' : 'border-outline-variant text-on-surface hover:border-outline'"
                                x-text="color">
                            </button>
                        </template>
                    </div>
                </div>

                {{-- Size selector --}}
                <div x-show="sizes.length > 0" class="flex flex-col gap-2">
                    <span class="font-label-md text-label-md text-on-surface-variant">
                        Size: <span class="text-on-surface font-semibold" x-text="selectedSize || 'Select a size'"></span>
                    </span>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="size in sizes" :key="size">
                            <button
                                type="button"
                                @click="selectedSize = size"
                                class="px-4 py-2 rounded-lg border text-body-sm font-body-sm transition-colors"
                                :class="selectedSize === size ? 'border-primary bg-primary/10 text-primary' : 'border-outline-variant text-on-surface hover:border-outline'"
                                x-text="size">
                            </button>
                        </template>
                    </div>
                </div>

                {{-- Stock indicator --}}
                <div class="flex items-center gap-2">
                    <template x-if="stock > 10">
                        <span class="flex items-center gap-1.5 font-label-md text-label-md text-primary">
                            <span class="w-2 h-2 rounded-full bg-primary"></span> In Stock
                        </span>
                    </template>
                    <template x-if="stock > 0 && stock <= 10">
                        <span class="flex items-center gap-1.5 font-label-md text-label-md text-tertiary">
                            <span class="w-2 h-2 rounded-full bg-tertiary"></span> Only <span x-text="stock"></span> left in stock
                        </span>
                    </template>
                    <template x-if="stock === 0">
                        <span class="flex items-center gap-1.5 font-label-md text-label-md text-error">
                            <span class="w-2 h-2 rounded-full bg-error"></span> Out of Stock
                        </span>
                    </template>
                </div>

                <div class="border-t border-outline-variant my-2"></div>

                {{-- Quantity + Add to cart --}}
                <div class="flex items-center gap-4">
                    <div class="flex items-center border border-outline-variant rounded-full">
                        <button type="button" @click="qty = Math.max(1, qty - 1)" class="w-10 h-10 flex items-center justify-center text-on-surface hover:text-primary" aria-label="Decrease quantity">
                            <span class="material-symbols-outlined text-[18px]">remove</span>
                        </button>
                        <span class="w-10 text-center font-label-md text-label-md" x-text="qty"></span>
                        <button type="button" @click="qty = Math.min(stock || 99, qty + 1)" class="w-10 h-10 flex items-center justify-center text-on-surface hover:text-primary" aria-label="Increase quantity">
                            <span class="material-symbols-outlined text-[18px]">add</span>
                        </button>
                    </div>

                    <button
                        type="button"
                        :disabled="stock === 0 || !canAddToCart"
                        @click="addToCart()"
                        class="flex-1 flex items-center justify-center gap-2 bg-primary text-on-primary font-label-md text-label-md px-6 py-3 rounded-full shadow-lg shadow-primary/20 hover:bg-primary/90 transition-all disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-primary">
                        <span class="material-symbols-outlined text-[20px]" x-text="justAdded ? 'check' : 'shopping_cart'"></span>
                        <span x-text="justAdded ? 'Added to Cart' : 'Add to Cart'"></span>
                    </button>
                </div>

                <p x-show="!canAddToCart" x-cloak class="font-body-sm text-body-sm text-error">
                    Please select <span x-text="!selectedColor && colors.length ? 'a color' : 'a size'"></span> before adding to cart.
                </p>
            </div>
        </div>

        {{-- Related products --}}
        @if(count($related))
            <div class="mt-16">
                <h2 class="font-headline-lg text-headline-lg text-on-surface mb-6">You Might Also Like</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-gutter">
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
