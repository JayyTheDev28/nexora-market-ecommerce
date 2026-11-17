@extends('layouts.app')

@section('title', $product['name'] . ' — Nexora Market')

@section('content')
<div
    x-data="productDetail({
        product: {{ json_encode($product) }},
        gallery: {{ json_encode($product['gallery'] ?: [$product['image']]) }},
        colors: {{ json_encode($product['colors']) }},
        sizes: {{ json_encode($product['sizes']) }},
        variantsData: {{ json_encode($product['variantsData']) }},
        stock: {{ (int) $product['stock'] }},
        price: {{ (float) $product['effectivePrice'] }},
        regularPrice: {{ (float) $product['price'] }},
        isFlashSale: {{ $product['isFlashSale'] ? 'true' : 'false' }},
        flashSaleEndsAt: {{ json_encode($product['flashSaleEndsAt'] ?? null) }},
        flashSaleSeconds: {{ (int) $product['flashSaleSeconds'] }},
        maxPurchaseLimit: {{ $product['maxPurchaseLimit'] ? (int) $product['maxPurchaseLimit'] : 'null' }},
        buyerRemainingLimit: {{ $product['buyerRemainingLimit'] !== null ? (int) $product['buyerRemainingLimit'] : 'null' }},
        inWishlist: {{ $product['inWishlist'] ? 'true' : 'false' }},
        isFollowingStore: {{ $product['isFollowingStore'] ? 'true' : 'false' }}
    })"
    class="w-full bg-white py-4 sm:py-8">

    {{-- Main Centered Container with Balanced Left and Right Margins --}}
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- Minimalist Breadcrumb Navigation --}}
        <nav class="flex items-center gap-2 text-xs text-gray-500 mb-5 overflow-x-auto whitespace-nowrap">
            <a href="{{ auth()->check() ? url(auth()->user()->homeRoute()) : url('/') }}" class="hover:text-gray-900 transition-colors">Home</a>
            <span class="text-gray-300">/</span>
            <a href="{{ url('/catalog') }}" class="hover:text-gray-900 transition-colors">Shop offers</a>
            <span class="text-gray-300">/</span>
            <span class="text-gray-800 font-medium truncate max-w-xs sm:max-w-md">{{ $product['name'] }}</span>
        </nav>

        {{-- Flash Sale Active Banner --}}
        @if($product['isFlashSale'])
            <div x-show="isFlashSale && flashSaleSeconds > 0" class="mb-5 p-3.5 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-xs text-amber-900 flex items-center justify-between shadow-xs">
                <div class="flex items-center gap-2 font-bold">
                    <span class="material-symbols-outlined text-[18px] text-amber-600">bolt</span>
                    <span>Flash Sale Active</span>
                </div>
                <div class="flex items-center gap-1.5 font-mono text-xs font-bold text-amber-800">
                    <span>Ends in:</span>
                    <span x-text="formatTime(hours) + ':' + formatTime(minutes) + ':' + formatTime(seconds)"></span>
                </div>
            </div>
        @endif

        {{-- Main Product Card Layout (2 Balanced Columns) --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12 items-start">

            {{-- LEFT COLUMN: Vertical Thumbnails + Main Preview Card --}}
            <div class="lg:col-span-6 flex flex-col gap-3">
                <div class="flex flex-col-reverse sm:flex-row gap-4 items-start">
                    
                    {{-- Vertical Thumbnails Strip (Left of Main Image) --}}
                    <div class="flex sm:flex-col gap-2.5 overflow-x-auto sm:overflow-y-auto max-h-[480px] w-full sm:w-20 shrink-0 pb-1 sm:pb-0" x-show="gallery.length > 1">
                        <template x-for="(img, i) in gallery" :key="i">
                            <button
                                type="button"
                                @click="activeImage = i"
                                class="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl overflow-hidden border-2 p-1 bg-[#f8f8f9] transition-all duration-200 cursor-pointer shrink-0"
                                :class="activeImage === i ? 'border-[#584554] ring-2 ring-[#584554]/20 scale-102' : 'border-gray-200 hover:border-gray-300 opacity-80 hover:opacity-100'">
                                <img
                                    :src="img"
                                    :alt="'Thumbnail ' + (i + 1)"
                                    x-on:error="$el.src = 'https://placehold.co/200x200/f4f4f5/584554?text=' + encodeURIComponent(product.name)"
                                    class="w-full h-full object-contain rounded-xl">
                            </button>
                        </template>
                    </div>

                    {{-- Main Product Image Preview Container --}}
                    <div class="relative flex-1 w-full aspect-square rounded-3xl bg-[#f4f4f5] border border-gray-200/70 p-6 sm:p-8 flex items-center justify-center overflow-hidden group select-none shadow-xs">
                        
                        {{-- Top-Left Badge: "Special offer" / Flash Sale --}}
                        <div class="absolute top-4 left-4 z-10">
                            <span class="inline-flex items-center px-3 py-1 rounded-lg text-[11px] font-semibold text-white bg-[#78384b] shadow-xs">
                                <span x-show="isFlashSale && flashSaleSeconds > 0" class="flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[13px]">bolt</span>
                                    <span>Flash deal</span>
                                </span>
                                <span x-show="!isFlashSale || flashSaleSeconds <= 0">Special offer</span>
                            </span>
                        </div>

                        {{-- Top-Right Action: Fullscreen Zoom Icon Button --}}
                        <button
                            type="button"
                            @click="showZoomModal = true"
                            class="absolute top-4 right-4 z-10 w-9 h-9 rounded-full bg-white text-gray-700 shadow-sm border border-gray-200/80 flex items-center justify-center hover:bg-gray-50 hover:scale-105 transition-all"
                            title="Click to view full screen">
                            <span class="material-symbols-outlined text-[18px]">search</span>
                        </button>

                        {{-- Active Main Image with Smooth Hover Zoom --}}
                        <div class="w-full h-full flex items-center justify-center cursor-zoom-in overflow-hidden" @click="showZoomModal = true">
                            <template x-for="(img, i) in gallery" :key="i">
                                <img
                                    :src="img"
                                    :alt="product.name"
                                    x-show="activeImage === i"
                                    x-cloak
                                    x-on:error="$el.src = 'https://placehold.co/800x800/f4f4f5/584554?text=' + encodeURIComponent(product.name)"
                                    class="max-w-full max-h-full object-contain transition-transform duration-300 ease-out group-hover:scale-105">
                            </template>
                        </div>

                        {{-- Bottom-Right Navigation Arrow Controls (< and >) --}}
                        <div class="absolute bottom-4 right-4 z-10 flex items-center gap-2" x-show="gallery.length > 1">
                            <button
                                type="button"
                                @click.stop="prevImage()"
                                class="w-8 h-8 rounded-full bg-white text-gray-700 shadow-sm border border-gray-200/80 flex items-center justify-center hover:bg-gray-50 transition-colors"
                                title="Previous image">
                                <span class="material-symbols-outlined text-[18px]">chevron_left</span>
                            </button>
                            <button
                                type="button"
                                @click.stop="nextImage()"
                                class="w-8 h-8 rounded-full bg-white text-gray-700 shadow-sm border border-gray-200/80 flex items-center justify-center hover:bg-gray-50 transition-colors"
                                title="Next image">
                                <span class="material-symbols-outlined text-[18px]">chevron_right</span>
                            </button>
                        </div>

                    </div>

                </div>

                {{-- Hint Caption under Gallery --}}
                <p class="text-[11px] text-gray-400 text-center tracking-normal">
                    Hover over the photo to zoom · Click the photo to view it full screen
                </p>

            </div>

            {{-- RIGHT COLUMN: Category, Title, Pricing, Variations & CTAs --}}
            <div class="lg:col-span-6 flex flex-col gap-3.5">
                
                {{-- Category Tag --}}
                <div class="text-[11px] font-semibold tracking-wider uppercase text-primary">
                    {{ strtoupper($product['category'] ?? 'ELECTRONICS') }}
                </div>

                {{-- Product Title with Standard Typography --}}
                <h1 class="font-headline-lg text-xl sm:text-2xl md:text-3xl font-bold text-on-surface leading-snug">
                    {{ $product['name'] }}
                </h1>

                {{-- Social Proof & Ratings Row --}}
                <div class="flex items-center gap-4 text-xs text-on-surface-variant">
                    @if($product['reviewCount'] > 0)
                        <div class="flex items-center gap-1 text-amber-500 font-medium">
                            <span class="material-symbols-outlined text-[15px]" style="font-variation-settings: 'FILL' 1">star</span>
                            <span class="text-on-surface font-semibold">{{ number_format($product['rating'], 1) }}</span>
                        </div>
                        <span>{{ $product['reviewCount'] }} reviews</span>
                    @else
                        <span>No ratings yet</span>
                        <span>0 reviews</span>
                    @endif
                    <span>{{ (int) ($product['soldCount'] ?? 0) }} sold</span>
                </div>

                {{-- Pricing & Savings Block --}}
                <div class="flex flex-col gap-1 pt-1">
                    <div class="flex items-baseline gap-3 flex-wrap">
                        {{-- Effective Price --}}
                        <span class="text-2xl sm:text-3xl font-extrabold text-primary tracking-tight">
                            ₱<span x-text="currentPrice.toFixed(2)">{{ number_format($product['effectivePrice'], 2) }}</span>
                        </span>

                        {{-- Strikethrough Regular Price --}}
                        @if(!empty($product['comparePrice']) && $product['comparePrice'] > $product['effectivePrice'])
                            <span class="text-sm sm:text-base text-outline line-through">
                                ₱{{ number_format($product['comparePrice'], 2) }}
                            </span>

                            @php
                                $savings = $product['comparePrice'] - $product['effectivePrice'];
                                $percent = round(($savings / $product['comparePrice']) * 100);
                            @endphp
                            {{-- Discount Badge --}}
                            <span class="px-2 py-0.5 rounded-md bg-primary text-on-primary text-[11px] font-bold tracking-tight">
                                -{{ $percent }}%
                            </span>
                        @endif
                    </div>

                    {{-- You Save Subtext --}}
                    @if(!empty($product['comparePrice']) && $product['comparePrice'] > $product['effectivePrice'])
                        <div class="text-xs font-semibold text-primary">
                            You save ₱{{ number_format($savings, 2) }}
                        </div>
                    @endif

                    {{-- Flash Sale Countdown (Only when Flash Sale is Active) --}}
                    <template x-if="isFlashSale && flashSaleSeconds > 0">
                        <div class="text-[11px] text-on-surface-variant">
                            Offer ends in
                            <strong class="text-on-surface font-mono" x-text="formatTime(hours) + ':' + formatTime(minutes) + ':' + formatTime(seconds)"></strong>
                        </div>
                    </template>
                </div>

                {{-- Purchase Limit Notice (Rule B4) --}}
                @if($product['maxPurchaseLimit'])
                    <div class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/20 text-xs text-amber-800 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[17px] text-amber-700">info</span>
                        <span>
                            <strong>Purchase Limit:</strong> Maximum <strong>{{ $product['maxPurchaseLimit'] }}</strong> units per buyer.
                            @if($product['buyerRemainingLimit'] !== null)
                                (You can order at most <strong>{{ $product['buyerRemainingLimit'] }}</strong> more).
                            @endif
                        </span>
                    </div>
                @endif

                {{-- Selected Variation & Stock Header --}}
                <div class="flex items-center justify-between text-xs pt-1">
                    <div class="text-gray-700">
                        Selected: <span class="font-semibold text-gray-900" x-text="selectedVariant || selectedColor || 'Standard Edition'"></span>
                    </div>
                    <div class="font-medium text-emerald-600" x-show="currentStock > 0">
                        <span x-text="currentStock"></span> in stock
                    </div>
                    <div class="font-medium text-rose-600" x-show="currentStock === 0" x-cloak>
                        Out of Stock
                    </div>
                    @if($product['stock'] === 0)
                        <span class="sr-only">Out of Stock</span>
                    @endif
                </div>

                {{-- Variation Options (from variantsData or colors) --}}
                <div x-show="(variantsData && variantsData.length > 0) || (colors && colors.length > 0)" class="flex flex-wrap gap-2 pt-0.5">
                    <template x-if="variantsData && variantsData.length > 0">
                        <template x-for="v in variantsData" :key="v.name || v.sku">
                            <button
                                type="button"
                                @click="selectVariant(v)"
                                class="px-3 py-1.5 rounded-xl border text-xs transition-all cursor-pointer flex items-center gap-2"
                                :class="selectedVariant === (v.name || v.sku) ? 'border-[#584554] bg-[#584554]/10 text-[#584554] ring-1 ring-[#584554] font-semibold' : 'border-gray-200 bg-white text-gray-700 hover:border-gray-300'"
                                :disabled="v.stock === 0">
                                <template x-if="v.image">
                                    <img :src="v.image" :alt="v.name || v.sku" class="w-5 h-5 rounded-md object-contain border border-gray-200 shrink-0">
                                </template>
                                <span x-text="v.name || v.sku"></span>
                                <template x-if="v.price && parseFloat(v.price) > 0">
                                    <span class="text-[10px] opacity-75">₱<span x-text="parseFloat(v.price).toFixed(2)"></span></span>
                                </template>
                            </button>
                        </template>
                    </template>

                    <template x-if="(!variantsData || variantsData.length === 0) && (colors && colors.length > 0)">
                        <template x-for="c in colors" :key="c">
                            <button
                                type="button"
                                @click="selectVariant({ name: c })"
                                class="px-3 py-1.5 rounded-xl border text-xs transition-all cursor-pointer"
                                :class="selectedVariant === c ? 'border-[#584554] bg-[#584554]/10 text-[#584554] ring-1 ring-[#584554] font-semibold' : 'border-gray-200 bg-white text-gray-700 hover:border-gray-300'"
                                x-text="c">
                            </button>
                        </template>
                    </template>
                </div>

                {{-- Sizes Options (if any) --}}
                <div x-show="sizes && sizes.length > 0" class="flex flex-col gap-1.5 pt-0.5">
                    <span class="text-xs font-semibold text-gray-700">Size:</span>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="s in sizes" :key="s">
                            <button
                                type="button"
                                @click="selectSize(s)"
                                class="px-3.5 py-1.5 rounded-xl border text-xs transition-all cursor-pointer text-center"
                                :class="selectedSize === s ? 'border-[#584554] bg-[#584554]/10 text-[#584554] ring-1 ring-[#584554] font-semibold' : 'border-gray-200 bg-white text-gray-700 hover:border-gray-300'"
                                x-text="s">
                            </button>
                        </template>
                    </div>
                </div>

                {{-- Action Row: Stepper, Wishlist & Add to Cart CTA --}}
                @if(auth()->check() && !auth()->user()->isBuyer())
                    <div class="p-3.5 rounded-2xl bg-gray-50 border border-gray-200 text-xs text-gray-700 flex items-center justify-between gap-3 mt-1">
                        <div>
                            <span class="font-bold text-gray-900 block">Signed in as {{ auth()->user()->roleLabel() }}</span>
                            <span>Purchasing is exclusive to Buyer accounts.</span>
                        </div>
                        <a href="{{ auth()->user()->homeRoute() }}" class="px-4 py-2 rounded-xl bg-gray-900 text-white font-semibold text-xs hover:bg-gray-800 transition-colors shrink-0">
                            Dashboard
                        </a>
                    </div>
                @else
                    <div class="flex items-center gap-3 pt-2">
                        {{-- Quantity Stepper --}}
                        <div class="flex items-center border border-gray-300 rounded-xl bg-white px-1 py-0.5 shadow-xs shrink-0">
                            <button
                                type="button"
                                @click="qty = Math.max(1, qty - 1)"
                                :disabled="currentStock === 0"
                                class="w-8 h-8 rounded-lg flex items-center justify-center text-gray-600 hover:bg-gray-100 transition-colors cursor-pointer disabled:opacity-30"
                                title="Decrease quantity">
                                <span class="material-symbols-outlined text-[16px]">remove</span>
                            </button>
                            <input
                                type="number"
                                min="1"
                                :max="maxAllowedQty"
                                :disabled="currentStock === 0"
                                x-model.number="qty"
                                @input="if (qty < 1 || isNaN(qty)) qty = 1; if (qty > maxAllowedQty) qty = maxAllowedQty;"
                                @blur="if (!qty || qty < 1) qty = 1; if (qty > maxAllowedQty) qty = maxAllowedQty;"
                                class="w-10 text-center text-xs font-semibold text-gray-900 bg-transparent border-0 focus:ring-0 p-0 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                            <button
                                type="button"
                                @click="qty = Math.min(maxAllowedQty, qty + 1)"
                                :disabled="currentStock === 0 || qty >= maxAllowedQty"
                                class="w-8 h-8 rounded-lg flex items-center justify-center text-gray-600 hover:bg-gray-100 transition-colors cursor-pointer disabled:opacity-30"
                                title="Increase quantity">
                                <span class="material-symbols-outlined text-[16px]">add</span>
                            </button>
                        </div>

                        {{-- Wishlist Button --}}
                        @if(auth()->check() && auth()->user()->isBuyer())
                            <button
                                type="button"
                                @click="toggleWishlist()"
                                class="w-10 h-10 rounded-xl border border-gray-300 bg-white flex items-center justify-center text-gray-600 hover:bg-gray-50 transition-colors shrink-0 shadow-xs"
                                title="Save to wishlist">
                                <span class="material-symbols-outlined text-[20px]" :class="inWishlist ? 'text-rose-600' : 'text-gray-500'" :style="inWishlist ? 'font-variation-settings: \'FILL\' 1' : ''">
                                    favorite
                                </span>
                            </button>
                        @endif

                        {{-- Primary "Add to cart" CTA Button --}}
                        <button
                            type="button"
                            :disabled="stock === 0 || !canAddToCart || addingToCart"
                            @click="addToCart()"
                            class="flex-1 py-3 px-6 rounded-xl bg-[#543b4b] hover:bg-[#432d3b] text-white font-medium text-xs sm:text-sm shadow-xs transition-all cursor-pointer flex items-center justify-center gap-2 disabled:opacity-40 disabled:cursor-not-allowed">
                            <span x-show="addingToCart" x-cloak class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                            <span x-show="!addingToCart && justAdded" x-cloak class="material-symbols-outlined text-[18px]">check</span>
                            <span x-text="buttonLabel">Add to cart</span>
                        </button>

                        {{-- Chat with Seller Button --}}
                        @if(auth()->check() && auth()->user()->isBuyer())
                            <form method="POST" action="{{ route('buyer.chat.start') }}" class="shrink-0">
                                @csrf
                                <input type="hidden" name="seller_id" value="{{ $product['sellerId'] }}">
                                <input type="hidden" name="product_id" value="{{ $product['id'] }}">
                                <button
                                    type="submit"
                                    class="w-10 h-10 rounded-xl border border-gray-300 bg-white text-gray-700 flex items-center justify-center hover:bg-gray-50 transition-colors shadow-xs"
                                    title="Chat with seller">
                                    <span class="material-symbols-outlined text-[19px]">chat</span>
                                </button>
                            </form>
                        @endif
                    </div>

                    {{-- Selection Validation Notice --}}
                    <p x-show="!canAddToCart && currentStock > 0" x-cloak class="text-xs text-rose-600 font-medium">
                        Please select <span x-text="hasVariations && !selectedVariant ? 'a variation' : 'a size'"></span> before adding to cart.
                    </p>
                @endif

                {{-- Estimated Shipping Calculation Banner --}}
                @if(isset($shippingQuote))
                    <div class="mt-3 p-3.5 rounded-2xl border border-gray-200/80 bg-[#fafafa] space-y-1 text-xs">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-semibold text-gray-800 flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-[17px] text-[#543b4b]">local_shipping</span>
                                <span>Estimated Shipping ({{ ucwords(str_replace('_', ' ', $shippingQuote['route_type'])) }})</span>
                            </span>
                            <strong class="text-sm font-bold text-[#543b4b]">₱{{ number_format($shippingQuote['shipping_fee'], 2) }}</strong>
                        </div>
                        <p class="text-[11px] text-gray-500">
                            {{ $shippingQuote['billed_weight_kg'] }} kg billed ·
                            @if(($shippingQuote['pricing_mode'] ?? 'weight_tiered') === 'smart_cost_margin')
                                Est. Cost ₱{{ number_format($shippingQuote['breakdown']['total_estimated_cost'] ?? 0, 2) }} ÷ (1 − {{ number_format($shippingQuote['breakdown']['target_profit_margin_pct'] ?? 20, 0) }}% margin)
                            @else
                                Active Tariff: Base ₱{{ number_format($shippingQuote['policy']->base_fee ?? 50, 2) }} + ₱{{ number_format($shippingQuote['policy']->per_additional_kg_fee ?? 20, 2) }}/add'l kg
                            @endif
                        </p>
                    </div>
                @endif

                {{-- Store Banner / Verification --}}
                <div class="mt-3 p-3.5 rounded-2xl border border-gray-200/80 bg-[#fafafa] flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <div class="w-8 h-8 rounded-lg bg-[#543b4b]/10 text-[#543b4b] font-bold text-xs flex items-center justify-center shrink-0">
                            {{ strtoupper(substr($product['seller'], 0, 1)) }}
                        </div>
                        <div class="min-w-0">
                            <a href="{{ route('stores.show', $product['sellerId']) }}" class="text-xs font-bold text-gray-900 hover:underline truncate block">
                                {{ $product['seller'] }}
                            </a>
                            <span class="text-[10px] text-gray-500 block">
                                {{ $product['sellerProductsCount'] }} products · {{ $product['sellerFollowersCount'] }} followers
                            </span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <a href="{{ route('stores.show', $product['sellerId']) }}" class="text-xs font-semibold px-3 py-1.5 rounded-xl border border-gray-300 text-gray-700 bg-white hover:bg-gray-50 transition-colors shrink-0">
                            Visit Store
                        </a>
                        @if(auth()->check() && auth()->user()->isBuyer())
                            <a href="{{ route('safety-reports.index', ['category' => 'fake_misleading_product', 'title' => 'Report product: ' . $product['name'], 'product_id' => $product['id'], 'reported_user_id' => $product['sellerId'], 'subject_type' => 'product', 'subject_id' => $product['id']]) }}" class="text-xs font-semibold px-3 py-1.5 rounded-xl border border-rose-200 text-rose-700 bg-rose-50/70 hover:bg-rose-100 transition-colors shrink-0 flex items-center gap-1" title="Report this product">
                                <span class="material-symbols-outlined text-[15px]">flag</span>
                                <span>Report Product</span>
                            </a>
                        @endif
                    </div>
                </div>

            </div>

        </div>

        {{-- Product Details & Description Section --}}
        <div class="mt-12 pt-8 border-t border-gray-200">
            <h2 class="text-base font-bold text-gray-900 mb-3">Product Description</h2>
            <div class="text-xs sm:text-sm text-gray-600 leading-relaxed max-w-4xl space-y-3 whitespace-pre-line">
                {{ $product['description'] }}
            </div>
        </div>

        {{-- Customer Ratings & Reviews Section --}}
        <div class="mt-10 pt-8 border-t border-gray-200">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-base font-bold text-gray-900">Customer Reviews & Ratings</h2>
                    <p class="text-xs text-gray-500">Verified buyer feedback from delivered orders.</p>
                </div>
                <div class="flex items-center gap-1.5 text-xs font-semibold text-gray-800">
                    <span class="material-symbols-outlined text-amber-500 text-[18px]" style="font-variation-settings: 'FILL' 1">star</span>
                    <span>{{ number_format($product['rating'], 1) }} / 5.0</span>
                    <span class="text-gray-400 font-normal">({{ $product['reviewCount'] }} reviews)</span>
                </div>
            </div>

            @if($reviews->isEmpty())
                <div class="p-8 text-center bg-gray-50 rounded-2xl border border-dashed border-gray-200">
                    <p class="text-xs text-gray-500">No reviews yet for this product. Be the first to order and share your feedback!</p>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($reviews as $rev)
                        <div class="p-4 rounded-2xl bg-[#fafafa] border border-gray-200/80 flex flex-col gap-2.5">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-full bg-[#543b4b]/10 text-[#543b4b] text-[11px] font-bold flex items-center justify-center">
                                        {{ strtoupper(substr($rev->buyer->first_name ?? 'B', 0, 1)) }}
                                    </div>
                                    <div>
                                        <span class="text-xs font-bold text-gray-900">{{ $rev->buyer->full_name }}</span>
                                        <span class="text-[10px] text-emerald-600 block font-medium">Verified Purchase</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-0.5 text-amber-500">
                                    @for($i = 1; $i <= 5; $i++)
                                        <span class="material-symbols-outlined text-[13px]" style="font-variation-settings: 'FILL' {{ $i <= $rev->rating ? 1 : 0 }}">star</span>
                                    @endfor
                                </div>
                            </div>

                            <p class="text-xs text-gray-700 leading-relaxed">
                                {{ $rev->comment ?? 'Rated ' . $rev->rating . ' out of 5 stars.' }}
                            </p>

                            @if(!empty($rev->photos))
                                <div class="flex gap-2 overflow-x-auto pt-1">
                                    @foreach($rev->photos as $photo)
                                        <img src="{{ asset('storage/' . $photo) }}" class="w-14 h-14 rounded-xl object-cover border border-gray-200">
                                    @endforeach
                                </div>
                            @endif

                            @if($rev->seller_reply)
                                <div class="mt-2 p-3 rounded-xl bg-gray-100/70 border border-gray-200 text-xs">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="font-bold text-[#543b4b] flex items-center gap-1">
                                            <span class="material-symbols-outlined text-[14px]">storefront</span>
                                            Seller Response
                                        </span>
                                        <span class="text-[10px] text-gray-400">{{ $rev->seller_replied_at?->format('M j, Y') }}</span>
                                    </div>
                                    <p class="text-gray-700 leading-relaxed">{{ $rev->seller_reply }}</p>
                                </div>
                            @endif

                            <div class="text-[10px] text-gray-400 pt-1 border-t border-gray-200/50">
                                {{ $rev->created_at->format('M d, Y') }}
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

    </div>

    {{-- Full-Screen Lightbox Zoom Modal --}}
    <div
        x-show="showZoomModal"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4"
        @keydown.escape.window="showZoomModal = false">
        
        <div class="relative max-w-4xl w-full max-h-[90vh] flex flex-col items-center" @click.outside="showZoomModal = false">
            <button
                type="button"
                @click="showZoomModal = false"
                class="absolute -top-12 right-0 w-9 h-9 rounded-full bg-white/20 text-white hover:bg-white/30 flex items-center justify-center transition-colors">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>

            <div class="relative bg-white rounded-3xl p-6 sm:p-8 max-w-3xl w-full flex items-center justify-center overflow-hidden">
                <img
                    :src="gallery[activeImage]"
                    :alt="product.name"
                    class="max-w-full max-h-[75vh] object-contain">
            </div>

            <div class="flex items-center gap-3 mt-4 text-white text-xs">
                <button
                    type="button"
                    @click="prevImage()"
                    class="px-3 py-1.5 rounded-full bg-white/20 hover:bg-white/30 transition-colors flex items-center gap-1">
                    <span class="material-symbols-outlined text-[16px]">chevron_left</span>
                    <span>Previous</span>
                </button>
                <span x-text="(activeImage + 1) + ' / ' + gallery.length"></span>
                <button
                    type="button"
                    @click="nextImage()"
                    class="px-3 py-1.5 rounded-full bg-white/20 hover:bg-white/30 transition-colors flex items-center gap-1">
                    <span>Next</span>
                    <span class="material-symbols-outlined text-[16px]">chevron_right</span>
                </button>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
    function productDetail(cfg) {
        return {
            product: cfg.product,
            gallery: cfg.gallery,
            colors: cfg.colors || [],
            sizes: cfg.sizes || [],
            variantsData: Array.isArray(cfg.variantsData) ? cfg.variantsData : [],
            stock: cfg.stock,
            currentStock: cfg.stock,
            currentPrice: cfg.price,
            regularPrice: cfg.regularPrice,
            isFlashSale: cfg.isFlashSale,
            flashSaleSeconds: cfg.flashSaleSeconds,
            maxPurchaseLimit: cfg.maxPurchaseLimit,
            buyerRemainingLimit: cfg.buyerRemainingLimit,
            inWishlist: cfg.inWishlist,
            isFollowingStore: cfg.isFollowingStore,

            activeImage: 0,
            showZoomModal: false,
            selectedVariant: '',
            selectedColor: '',
            selectedSize: '',
            qty: 1,
            addingToCart: false,
            justAdded: false,

            hours: 0,
            minutes: 0,
            seconds: 0,

            init() {
                if (this.stock === 0 && Array.isArray(this.variantsData) && this.variantsData.length > 0) {
                    const totalVStock = this.variantsData.reduce((sum, v) => sum + (parseInt(v.stock) || 0), 0);
                    if (totalVStock > 0) {
                        this.stock = totalVStock;
                        this.currentStock = totalVStock;
                    }
                }
                if (Array.isArray(this.variantsData) && this.variantsData.length === 1) {
                    this.selectVariant(this.variantsData[0]);
                } else if ((!this.variantsData || this.variantsData.length === 0) && Array.isArray(this.colors) && this.colors.length === 1) {
                    this.selectVariant({ name: this.colors[0] });
                }
                if (Array.isArray(this.sizes) && this.sizes.length === 1) {
                    this.selectSize(this.sizes[0]);
                }
                this.startTimer();
            },

            nextImage() {
                if (this.gallery.length > 0) {
                    this.activeImage = (this.activeImage + 1) % this.gallery.length;
                }
            },

            prevImage() {
                if (this.gallery.length > 0) {
                    this.activeImage = (this.activeImage - 1 + this.gallery.length) % this.gallery.length;
                }
            },

            get hasVariations() {
                return (Array.isArray(this.variantsData) && this.variantsData.length > 0) || (Array.isArray(this.colors) && this.colors.length > 0);
            },

            get hasSizes() {
                return Array.isArray(this.sizes) && this.sizes.length > 0;
            },

            get buttonLabel() {
                if (this.currentStock === 0) return 'Out of Stock';
                if (this.justAdded) return 'Added to cart';
                if (this.hasVariations && !this.selectedVariant) return 'Select variation';
                if (this.hasSizes && !this.selectedSize) return 'Select size';
                return 'Add to cart';
            },

            get maxAllowedQty() {
                let limit = this.currentStock;
                if (this.buyerRemainingLimit !== null) {
                    limit = Math.min(limit, this.buyerRemainingLimit);
                }
                return Math.max(1, limit);
            },

            get canAddToCart() {
                if (this.currentStock === 0) return false;
                if (this.hasVariations && !this.selectedVariant) return false;
                if (this.hasSizes && !this.selectedSize) return false;
                return true;
            },

            selectVariant(v) {
                this.selectedVariant = v.name || v.sku;
                this.selectedColor = this.selectedVariant;
                if (v.price && parseFloat(v.price) > 0) {
                    this.currentPrice = parseFloat(v.price);
                }
                if (v.stock !== undefined && v.stock !== null) {
                    this.currentStock = parseInt(v.stock);
                    this.stock = this.currentStock;
                }
                if (this.qty > this.maxAllowedQty) {
                    this.qty = this.maxAllowedQty;
                }
                if (v.image) {
                    const imgIdx = this.gallery.indexOf(v.image);
                    if (imgIdx !== -1) {
                        this.activeImage = imgIdx;
                    } else {
                        this.gallery.unshift(v.image);
                        this.activeImage = 0;
                    }
                }
            },

            selectSize(size) {
                this.selectedSize = size;
            },

            startTimer() {
                if (!this.isFlashSale || this.flashSaleSeconds <= 0) return;

                const updateTime = () => {
                    if (this.flashSaleSeconds <= 0) {
                        this.hours = 0;
                        this.minutes = 0;
                        this.seconds = 0;
                        this.isFlashSale = false;
                        this.currentPrice = this.regularPrice;
                        return;
                    }
                    this.hours = Math.floor(this.flashSaleSeconds / 3600);
                    this.minutes = Math.floor((this.flashSaleSeconds % 3600) / 60);
                    this.seconds = this.flashSaleSeconds % 60;
                    this.flashSaleSeconds--;
                };

                updateTime();
                setInterval(updateTime, 1000);
            },

            formatTime(num) {
                return num.toString().padStart(2, '0');
            },

            async toggleWishlist() {
                try {
                    const res = await fetch(`/buyer/wishlist/${this.product.id}/toggle`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        }
                    });
                    const data = await res.json();
                    if (data.success) {
                        this.inWishlist = data.inWishlist;
                    }
                } catch (e) {
                    console.error('Failed to toggle wishlist', e);
                }
            },

            async toggleStoreFollow() {
                try {
                    const res = await fetch(`/buyer/stores/${this.product.sellerId}/follow-toggle`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        }
                    });
                    const data = await res.json();
                    if (data.success) {
                        this.isFollowingStore = data.following;
                    }
                } catch (e) {
                    console.error('Failed to toggle store follow', e);
                }
            },

            async addToCart() {
                if (!this.canAddToCart || this.currentStock === 0) return;
                this.addingToCart = true;

                const variant = {};
                if (this.selectedVariant) variant.variation = this.selectedVariant;
                if (this.selectedSize) variant.size = this.selectedSize;

                if (this.selectedVariant && Array.isArray(this.variantsData)) {
                    const matched = this.variantsData.find(v => (v.name || v.sku) === this.selectedVariant);
                    if (matched && matched.image) {
                        variant.image = matched.image;
                    }
                }

                try {
                    const res = await fetch('/cart/items', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            product_id: this.product.id,
                            variant: Object.keys(variant).length ? variant : null,
                            quantity: this.qty,
                        }),
                    });

                    const data = await res.json();

                    if (res.ok && data.success) {
                        this.justAdded = true;
                        setTimeout(() => this.justAdded = false, 2500);

                        // Update global cart badge
                        if (data.cartCount !== undefined) {
                            window.dispatchEvent(new CustomEvent('cart-updated', { detail: { count: data.cartCount } }));
                        }
                        const badge = document.getElementById('navbar-cart-badge');
                        if (badge) {
                            badge.innerText = data.cartCount;
                            badge.classList.remove('hidden');
                        }
                    } else if (data.error) {
                        alert(data.error);
                    }
                } catch (e) {
                    console.error('Failed to add to cart', e);
                } finally {
                    this.addingToCart = false;
                }
            }
        };
    }
</script>
@endpush
