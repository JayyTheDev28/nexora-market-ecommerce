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
        flashSaleSeconds: {{ (int) $product['flashSaleSeconds'] }},
        maxPurchaseLimit: {{ $product['maxPurchaseLimit'] ? (int) $product['maxPurchaseLimit'] : 'null' }},
        buyerRemainingLimit: {{ $product['buyerRemainingLimit'] !== null ? (int) $product['buyerRemainingLimit'] : 'null' }},
        inWishlist: {{ $product['inWishlist'] ? 'true' : 'false' }},
        isFollowingStore: {{ $product['isFollowingStore'] ? 'true' : 'false' }}
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

        {{-- Flash Sale Countdown Ribbon --}}
        <div
            x-show="isFlashSale && flashSaleSeconds > 0"
            x-cloak
            class="mb-6 p-4 rounded-2xl bg-gradient-to-r from-amber-500 via-orange-500 to-rose-600 text-white flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-lg shadow-orange-500/20">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-white/20 backdrop-blur-xs flex items-center justify-center">
                    <span class="material-symbols-outlined text-[24px]">bolt</span>
                </div>
                <div>
                    <h3 class="text-sm font-black uppercase tracking-wider">Flash Sale Active</h3>
                    <p class="text-xs text-white/90">Exclusive limited-time discount. Prices revert when countdown reaches zero.</p>
                </div>
            </div>

            {{-- Timer Blocks --}}
            <div class="flex items-center gap-2 self-start sm:self-auto font-mono text-xs font-black">
                <span class="text-xs font-sans font-bold uppercase tracking-wider text-white/80 mr-1">Ends in:</span>
                <div class="px-2.5 py-1.5 rounded-lg bg-black/30 backdrop-blur-xs text-center min-w-[36px]" x-text="formatTime(hours)">00</div>
                <span>:</span>
                <div class="px-2.5 py-1.5 rounded-lg bg-black/30 backdrop-blur-xs text-center min-w-[36px]" x-text="formatTime(minutes)">00</div>
                <span>:</span>
                <div class="px-2.5 py-1.5 rounded-lg bg-black/30 backdrop-blur-xs text-center min-w-[36px]" x-text="formatTime(seconds)">00</div>
            </div>
        </div>

        {{-- Main Product Layout (2 Columns on Desktop) --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12 items-start">

            {{-- Left Column: Gallery & Assurances --}}
            <div class="lg:col-span-6 flex flex-col gap-4 md:gap-6 sticky top-24">
                
                {{-- Main Gallery Image with Wishlist Toggle Overlay --}}
                <div class="relative w-full aspect-square rounded-3xl overflow-hidden bg-surface-container border border-outline-variant/60 shadow-xs">
                    <template x-for="(img, i) in gallery" :key="i">
                        <img
                            :src="img"
                            x-show="activeImage === i"
                            x-cloak
                            class="w-full h-full object-cover transition-all duration-300">
                    </template>

                    {{-- Wishlist Button --}}
                    @if(auth()->check() && auth()->user()->isBuyer())
                        <button
                            type="button"
                            @click="toggleWishlist()"
                            class="absolute top-4 right-4 w-11 h-11 rounded-full bg-white/90 backdrop-blur-xs text-on-surface shadow-md flex items-center justify-center hover:scale-110 transition-transform">
                            <span class="material-symbols-outlined text-[24px]" :class="inWishlist ? 'text-rose-600' : 'text-on-surface-variant'" :style="inWishlist ? 'font-variation-settings: \'FILL\' 1' : ''">
                                favorite
                            </span>
                        </button>
                    @endif
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

                {{-- Buyer Protection & Assurances Card --}}
                <div class="p-5 bg-surface-container-lowest rounded-2xl border border-outline-variant/60 flex flex-col gap-3 shadow-xs">
                    <h4 class="text-xs font-bold text-on-surface uppercase tracking-wider">Nexora Buyer Assurance</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs text-on-surface-variant">
                        <div class="flex items-center gap-2">
                            <span class="w-1.5 h-1.5 rounded-full bg-primary flex-shrink-0"></span>
                            <span>100% Authentic</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="w-1.5 h-1.5 rounded-full bg-primary flex-shrink-0"></span>
                            <span>7-Day Return Window</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="w-1.5 h-1.5 rounded-full bg-primary flex-shrink-0"></span>
                            <span>Cash on Delivery (COD)</span>
                        </div>
                    </div>
                </div>

            </div>

            {{-- Right Column: Product Details & Purchase Actions --}}
            <div class="lg:col-span-6 flex flex-col gap-5 md:gap-6">
                
                {{-- Seller Header & Title --}}
                <div class="flex flex-col gap-1.5">
                    <div class="flex items-center justify-between">
                        <a href="{{ route('stores.show', $product['sellerId']) }}" class="text-xs font-bold text-primary uppercase tracking-wider hover:underline">
                            {{ $product['seller'] }}
                        </a>

                        @if(auth()->check() && auth()->user()->isBuyer())
                            <button
                                type="button"
                                @click="toggleStoreFollow()"
                                class="text-xs font-semibold px-3 py-1 rounded-full border border-outline-variant hover:bg-surface-container transition-colors"
                                :class="isFollowingStore ? 'bg-primary/10 text-primary border-primary/30' : 'text-on-surface-variant'">
                                <span x-text="isFollowingStore ? 'Following Store' : '+ Follow Store'"></span>
                            </button>
                        @endif
                    </div>

                    <h1 class="font-headline-lg text-xl md:text-2xl lg:text-3xl font-bold text-on-surface leading-snug">
                        {{ $product['name'] }}
                    </h1>
                </div>

                {{-- Rating & Review Summary --}}
                <div class="flex items-center gap-3 text-xs">
                    <div class="flex items-center gap-1 text-amber-500 font-bold">
                        <span class="material-symbols-outlined text-[16px]" style="font-variation-settings: 'FILL' 1">star</span>
                        <span>{{ number_format($product['rating'], 1) }}</span>
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
                        ₱<span x-text="currentPrice.toFixed(2)">{{ number_format($product['effectivePrice'], 2) }}</span>
                    </span>

                    @if(!empty($product['comparePrice']) && $product['comparePrice'] > $product['effectivePrice'])
                        <span class="text-sm md:text-base text-outline line-through">
                            ₱{{ number_format($product['comparePrice'], 2) }}
                        </span>

                        @php
                            $savings = $product['comparePrice'] - $product['effectivePrice'];
                            $percent = round(($savings / $product['comparePrice']) * 100);
                        @endphp
                        <span class="px-2 py-0.5 rounded-md bg-error/10 text-error font-extrabold text-xs">
                            Save ₱{{ number_format($savings, 2) }} (-{{ $percent }}%)
                        </span>
                    @endif

                    @if($product['isFlashSale'])
                        <span class="px-2 py-0.5 rounded-md bg-amber-500 text-white font-extrabold text-xs flex items-center gap-1">
                            <span class="material-symbols-outlined text-[14px]">bolt</span>
                            Flash Deal
                        </span>
                    @endif
                </div>

                {{-- Per-Buyer Purchase Limit Notice (Rule B4) --}}
                @if($product['maxPurchaseLimit'])
                    <div class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/20 text-xs text-amber-800 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px]">gavel</span>
                        <span>
                            <strong>Purchase Limit:</strong> Maximum <strong>{{ $product['maxPurchaseLimit'] }}</strong> units per buyer.
                            @if($product['buyerRemainingLimit'] !== null)
                                (You can purchase at most <strong>{{ $product['buyerRemainingLimit'] }}</strong> more).
                            @endif
                        </span>
                    </div>
                @endif

                {{-- Description --}}
                <div class="text-xs md:text-sm text-on-surface-variant leading-relaxed">
                    <p>{{ $product['description'] }}</p>
                </div>

                <div class="border-t border-outline-variant/60"></div>

                {{-- Product Variations Selector (from seller variants table) --}}
                <div x-show="variantsData && variantsData.length > 0" class="flex flex-col gap-2.5">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-bold text-on-surface uppercase tracking-wider">Variation</span>
                        <span class="text-primary font-semibold" x-text="selectedVariant || 'Choose an option'"></span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="v in (variantsData || [])" :key="v.name || v.sku">
                            <button
                                type="button"
                                @click="selectVariant(v)"
                                class="px-4 py-2 rounded-xl border text-xs font-semibold transition-all cursor-pointer flex items-center gap-2"
                                :class="selectedVariant === (v.name || v.sku) ? 'border-primary bg-primary/10 text-primary ring-2 ring-primary/20 font-bold' : 'border-outline-variant bg-surface-container-lowest text-on-surface hover:border-outline'"
                                :disabled="v.stock === 0">
                                <span x-text="v.name || v.sku"></span>
                                <template x-if="v.price && parseFloat(v.price) > 0">
                                    <span class="text-[11px] opacity-75 font-normal">
                                        ₱<span x-text="parseFloat(v.price).toFixed(2)"></span>
                                    </span>
                                </template>
                                <template x-if="v.stock === 0">
                                    <span class="text-[10px] text-rose-500 font-normal italic">(Out of stock)</span>
                                </template>
                            </button>
                        </template>
                    </div>
                </div>

                {{-- Variant 1: Color Selector --}}
                <div x-show="colors && colors.length > 0 && (!variantsData || variantsData.length === 0)" class="flex flex-col gap-2.5">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-bold text-on-surface uppercase tracking-wider">Color</span>
                        <span class="text-primary font-semibold" x-text="selectedColor || 'Choose a color'"></span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="color in (colors || [])" :key="color">
                            <button
                                type="button"
                                @click="selectColor(color)"
                                class="px-4 py-2 rounded-xl border text-xs font-semibold transition-all cursor-pointer"
                                :class="selectedColor === color ? 'border-primary bg-primary/10 text-primary ring-2 ring-primary/20' : 'border-outline-variant bg-surface-container-lowest text-on-surface hover:border-outline'"
                                x-text="color">
                            </button>
                        </template>
                    </div>
                </div>

                {{-- Variant 2: Size Selector --}}
                <div x-show="sizes && sizes.length > 0 && (!variantsData || variantsData.length === 0)" class="flex flex-col gap-2.5">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-bold text-on-surface uppercase tracking-wider">Size</span>
                        <span class="text-primary font-semibold" x-text="selectedSize || 'Choose a size'"></span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="size in (sizes || [])" :key="size">
                            <button
                                type="button"
                                @click="selectSize(size)"
                                class="min-w-[48px] px-3.5 py-2 rounded-xl border text-xs font-semibold transition-all cursor-pointer text-center"
                                :class="selectedSize === size ? 'border-primary bg-primary/10 text-primary ring-2 ring-primary/20' : 'border-outline-variant bg-surface-container-lowest text-on-surface hover:border-outline'"
                                x-text="size">
                            </button>
                        </template>
                    </div>
                </div>

                {{-- Stock Status Indicator --}}
                <div class="flex items-center gap-2 text-xs font-semibold">
                    <template x-if="currentStock > 10">
                        <span class="flex items-center gap-1.5 text-primary">
                            <span class="w-2 h-2 rounded-full bg-primary"></span>
                            In Stock (<span x-text="currentStock"></span> available)
                        </span>
                    </template>
                    <template x-if="currentStock > 0 && currentStock <= 10">
                        <span class="flex items-center gap-1.5 text-tertiary">
                            <span class="w-2 h-2 rounded-full bg-tertiary"></span>
                            Only <span x-text="currentStock"></span> items left!
                        </span>
                    </template>
                    <template x-if="currentStock === 0">
                        <span class="flex items-center gap-1.5 text-rose-600 font-bold">
                            <span class="w-2 h-2 rounded-full bg-rose-600"></span>
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
                        {{-- Stepper with typeable input --}}
                        <div class="flex items-center justify-between sm:justify-start border border-outline-variant rounded-full bg-surface-container-lowest p-1 shadow-xs">
                            <button
                                type="button"
                                @click="qty = Math.max(1, qty - 1)"
                                :disabled="currentStock === 0"
                                class="w-10 h-10 rounded-full flex items-center justify-center text-on-surface hover:bg-surface-container transition-colors cursor-pointer disabled:opacity-40">
                                <span class="material-symbols-outlined text-[18px]">remove</span>
                            </button>
                            <input
                                type="number"
                                min="1"
                                :max="maxAllowedQty"
                                :disabled="currentStock === 0"
                                x-model.number="qty"
                                @input="if (qty < 1 || isNaN(qty)) qty = 1; if (qty > maxAllowedQty) qty = maxAllowedQty;"
                                @blur="if (!qty || qty < 1) qty = 1; if (qty > maxAllowedQty) qty = maxAllowedQty;"
                                class="w-14 text-center text-xs font-bold text-on-surface bg-transparent border-0 focus:ring-0 p-0 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                            <button
                                type="button"
                                @click="qty = Math.min(maxAllowedQty, qty + 1)"
                                :disabled="currentStock === 0 || qty >= maxAllowedQty"
                                class="w-10 h-10 rounded-full flex items-center justify-center text-on-surface hover:bg-surface-container transition-colors cursor-pointer disabled:opacity-40">
                                <span class="material-symbols-outlined text-[18px]">add</span>
                            </button>
                        </div>

                        {{-- Add to Cart Action (Disabled when Out of Stock) --}}
                        <button
                            type="button"
                            :disabled="stock === 0 || !canAddToCart || addingToCart"
                            @click="addToCart()"
                            class="flex-1 flex items-center justify-center gap-2 bg-primary text-on-primary font-label-md text-xs md:text-sm font-semibold px-8 py-3.5 rounded-full shadow-lg shadow-primary/25 hover:bg-primary/90 transition-all cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                            
                            <span x-show="addingToCart" x-cloak class="w-4 h-4 border-2 border-current border-t-transparent rounded-full animate-spin"></span>
                            <span x-show="!addingToCart && justAdded" x-cloak class="material-symbols-outlined text-[18px]">check</span>
                            <span x-text="buttonLabel">Add to Cart</span>
                        </button>

                        {{-- Chat with Seller Action --}}
                        @if(auth()->check() && auth()->user()->isBuyer())
                            <form method="POST" action="{{ route('buyer.chat.start') }}" class="sm:flex-shrink-0">
                                @csrf
                                <input type="hidden" name="seller_id" value="{{ $product['sellerId'] }}">
                                <input type="hidden" name="product_id" value="{{ $product['id'] }}">
                                <button
                                    type="submit"
                                    class="w-full sm:w-auto px-5 py-3.5 rounded-full border border-primary text-primary text-xs font-bold hover:bg-primary/10 transition-colors flex items-center justify-center gap-1.5">
                                    <span class="material-symbols-outlined text-[18px]">chat</span>
                                    <span>Chat Seller</span>
                                </button>
                            </form>
                        @endif
                    </div>

                    {{-- Variant Selection Validation Notice --}}
                    <p x-show="!canAddToCart && currentStock > 0" x-cloak class="text-xs text-rose-600 font-medium">
                        Please select <span x-text="variantsData && variantsData.length > 0 && !selectedVariant ? 'a variation' : (!selectedColor && colors && colors.length ? 'a color' : 'a size')"></span> before adding to cart.
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
                            <span class="text-[11px] text-on-surface-variant">
                                {{ $product['sellerProductsCount'] }} products • {{ $product['sellerFollowersCount'] }} followers
                            </span>
                        </div>
                    </div>
                    <a href="{{ route('stores.show', $product['sellerId']) }}" class="px-3.5 py-1.5 rounded-xl border border-outline-variant text-xs font-semibold text-on-surface hover:bg-surface-container transition-colors flex-shrink-0">
                        Visit Store
                    </a>
                </div>

            </div>

        </div>

        {{-- Customer Ratings & Reviews Section --}}
        <div class="mt-12 pt-8 border-t border-outline-variant/60">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-lg font-bold text-on-surface">Customer Reviews & Ratings</h2>
                    <p class="text-xs text-on-surface-variant">Verified buyer feedback on delivered orders.</p>
                </div>
                <div class="flex items-center gap-2 text-sm font-bold text-on-surface">
                    <span class="material-symbols-outlined text-amber-500 text-[20px]" style="font-variation-settings: 'FILL' 1">star</span>
                    <span>{{ number_format($product['rating'], 1) }} / 5.0</span>
                    <span class="text-outline-variant font-normal">({{ $product['reviewCount'] }} reviews)</span>
                </div>
            </div>

            @if($reviews->isEmpty())
                <div class="p-8 text-center bg-surface-container-lowest rounded-2xl border border-dashed border-outline-variant">
                    <p class="text-xs text-on-surface-variant">No reviews yet for this product. Be the first to order and share your feedback!</p>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($reviews as $rev)
                        <div class="p-4 rounded-2xl bg-surface-container-lowest border border-outline-variant flex flex-col gap-3">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <img src="{{ $rev->buyer->avatar_url }}" class="w-8 h-8 rounded-full object-cover">
                                    <div>
                                        <span class="text-xs font-bold text-on-surface">{{ $rev->buyer->full_name }}</span>
                                        <span class="text-[10px] text-emerald-700 block font-semibold">Verified Purchase</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-0.5 text-amber-500">
                                    @for($i = 1; $i <= 5; $i++)
                                        <span class="material-symbols-outlined text-[14px]" style="font-variation-settings: 'FILL' {{ $i <= $rev->rating ? 1 : 0 }}">star</span>
                                    @endfor
                                </div>
                            </div>

                            <p class="text-xs text-on-surface leading-relaxed">
                                {{ $rev->comment ?? 'Rated ' . $rev->rating . ' out of 5 stars.' }}
                            </p>

                            @if(!empty($rev->photos))
                                <div class="flex gap-2 overflow-x-auto pt-1">
                                    @foreach($rev->photos as $photo)
                                        <img src="{{ asset('storage/' . $photo) }}" class="w-14 h-14 rounded-xl object-cover border border-outline-variant">
                                    @endforeach
                                </div>
                            @endif

                            @if($rev->seller_reply)
                                <div class="p-3 rounded-xl bg-surface-container-low border border-outline-variant/60 text-xs">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="font-bold text-primary flex items-center gap-1">
                                            <span class="material-symbols-outlined text-[13px]">storefront</span>
                                            Seller Response
                                        </span>
                                        <span class="text-[10px] text-on-surface-variant">{{ $rev->seller_replied_at?->format('M j, Y') }}</span>
                                    </div>
                                    <p class="text-on-surface leading-relaxed">{{ $rev->seller_reply }}</p>
                                </div>
                            @endif

                            <div class="flex items-center justify-between text-[10px] text-outline pt-2 border-t border-outline-variant/40">
                                <span>{{ $rev->created_at->format('M d, Y') }}</span>
                                @if($rev->edited_count > 0)
                                    <span class="italic">Edited (1 edit used)</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
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
            selectedVariant: '',
            selectedColor: '',
            selectedSize: '',
            qty: 1,
            addingToCart: false,
            justAdded: false,

            // Timer calculation
            hours: 0,
            minutes: 0,
            seconds: 0,

            init() {
                if (Array.isArray(this.variantsData) && this.variantsData.length === 1) {
                    this.selectVariant(this.variantsData[0]);
                }
                if (this.colors.length === 1) this.selectedColor = this.colors[0];
                if (this.sizes.length === 1) this.selectedSize = this.sizes[0];
                this.startTimer();
            },

            get buttonLabel() {
                if (this.currentStock === 0 || this.stock === 0) {
                    return 'Out of Stock';
                }
                if (this.justAdded) {
                    return 'Added to Cart';
                }
                if (Array.isArray(this.variantsData) && this.variantsData.length > 0 && !this.selectedVariant && !this.selectedColor && !this.selectedSize) {
                    return 'Select a Variation';
                }
                return 'Add to Cart';
            },

            get maxAllowedQty() {
                let limit = this.currentStock;
                if (this.buyerRemainingLimit !== null) {
                    limit = Math.min(limit, this.buyerRemainingLimit);
                }
                return Math.max(1, limit);
            },

            get canAddToCart() {
                if (Array.isArray(this.variantsData) && this.variantsData.length > 0 && !this.selectedVariant && !this.selectedColor && !this.selectedSize) return false;
                if (Array.isArray(this.colors) && this.colors.length && !this.selectedColor) return false;
                if (Array.isArray(this.sizes) && this.sizes.length && !this.selectedSize) return false;
                if (this.currentStock === 0) return false;
                return true;
            },

            selectVariant(v) {
                this.selectedVariant = v.name || v.sku;
                if (v.price && parseFloat(v.price) > 0) {
                    this.currentPrice = parseFloat(v.price);
                }
                if (v.stock !== undefined && v.stock !== null) {
                    this.currentStock = parseInt(v.stock);
                }
                if (this.qty > this.maxAllowedQty) {
                    this.qty = this.maxAllowedQty;
                }
                if (v.image) {
                    const imgIdx = this.gallery.indexOf(v.image);
                    if (imgIdx !== -1) {
                        this.activeImage = imgIdx;
                    }
                }
            },

            // Dynamic Variant selection driving real-time price, stock, and gallery images
            selectColor(color) {
                this.selectedColor = color;
                this.updateVariantMatrix();
            },

            selectSize(size) {
                this.selectedSize = size;
                this.updateVariantMatrix();
            },

            updateVariantMatrix() {
                // If product has variantsData matrix:
                if (Array.isArray(this.variantsData) && this.variantsData.length > 0) {
                    const match = this.variantsData.find(v => {
                        const colorMatch = !this.selectedColor || v.color === this.selectedColor;
                        const sizeMatch = !this.selectedSize || v.size === this.selectedSize;
                        return colorMatch && sizeMatch;
                    });

                    if (match) {
                        if (match.price) this.currentPrice = parseFloat(match.price);
                        if (match.stock !== undefined) this.currentStock = parseInt(match.stock);
                        if (match.image) {
                            const imgIdx = this.gallery.indexOf(match.image);
                            if (imgIdx !== -1) {
                                this.activeImage = imgIdx;
                            } else {
                                this.gallery.unshift(match.image);
                                this.activeImage = 0;
                            }
                        }
                    }
                }
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
                if (this.selectedColor) variant.color = this.selectedColor;
                if (this.selectedSize) variant.size = this.selectedSize;

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
