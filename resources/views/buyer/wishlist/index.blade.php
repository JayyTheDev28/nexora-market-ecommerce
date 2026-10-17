@extends('layouts.app')

@section('title', 'My Wishlist & Followed Stores — Nexora Market')

@section('content')
<div x-data="wishlistApp()" class="w-full bg-surface py-6 md:py-8 min-h-[75vh]">
    <div class="max-w-none px-4 md:px-8">

        <x-buyer-nav active="wishlist" />

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="font-headline-lg text-2xl md:text-3xl font-bold tracking-tight text-on-surface">My Wishlist & Followed Stores (B5)</h1>
                <p class="font-body-md text-xs md:text-sm text-on-surface-variant mt-1">Keep track of your favorite products and stay updated with your preferred sellers.</p>
            </div>

            <div class="flex items-center gap-2 p-1 bg-surface-container-low rounded-2xl border border-outline-variant/60">
                <button
                    type="button"
                    @click="activeTab = 'items'"
                    :class="activeTab === 'items' ? 'bg-surface-container-lowest text-primary font-bold shadow-xs' : 'text-on-surface-variant hover:text-on-surface'"
                    class="px-4 py-1.5 rounded-xl text-xs font-semibold transition-all">
                    Wishlist ({{ $wishlists->count() }})
                </button>
                <button
                    type="button"
                    @click="activeTab = 'stores'"
                    :class="activeTab === 'stores' ? 'bg-surface-container-lowest text-primary font-bold shadow-xs' : 'text-on-surface-variant hover:text-on-surface'"
                    class="px-4 py-1.5 rounded-xl text-xs font-semibold transition-all">
                    Followed Stores ({{ $followedStores->count() }})
                </button>
            </div>
        </div>

        @if (session('status'))
            <div class="mb-6 bg-primary/10 border border-primary/20 rounded-2xl p-4 flex items-center justify-between">
                <p class="font-body-sm text-xs md:text-sm font-medium text-primary">{{ session('status') }}</p>
            </div>
        @endif

        {{-- Tab 1: Wishlist Items --}}
        <div x-show="activeTab === 'items'">
            @if($wishlists->isEmpty())
                <div class="p-12 text-center bg-surface-container-lowest rounded-3xl border border-outline-variant max-w-lg mx-auto flex flex-col items-center gap-3">
                    <span class="material-symbols-outlined text-outline text-[48px]">favorite_border</span>
                    <h3 class="text-sm font-bold text-on-surface">Your wishlist is currently empty</h3>
                    <p class="text-xs text-on-surface-variant">Browse our catalog and save items you want to keep an eye on.</p>
                    <a href="{{ route('catalog.index') }}" class="mt-2 px-5 py-2.5 rounded-xl bg-primary text-on-primary text-xs font-semibold hover:opacity-90 transition-opacity">
                        Explore Catalog
                    </a>
                </div>
            @else
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                    @foreach($wishlists as $wish)
                        @php
                            $product = $wish->product;
                            $isDeleted = ! $product || $product->trashed();
                            $isOutOfStock = ! $isDeleted && $product->stock <= 0;
                        @endphp
                        <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant overflow-hidden flex flex-col justify-between group hover:shadow-md transition-shadow relative">
                            {{-- Product Image & Status Badges --}}
                            <div class="relative aspect-square w-full bg-surface-container overflow-hidden">
                                @if($isDeleted)
                                    <div class="w-full h-full flex flex-col items-center justify-center p-4 bg-surface-container text-center text-outline">
                                        <span class="material-symbols-outlined text-[32px] mb-1">remove_shopping_cart</span>
                                        <span class="text-xs font-bold">Item Unavailable</span>
                                        <span class="text-[10px] text-on-surface-variant">Product was removed</span>
                                    </div>
                                @else
                                    <img
                                        src="{{ $product->gallery[0] ?? 'https://placehold.co/400x400/e5eeff/0058be?text=' . urlencode($product->name) }}"
                                        alt="{{ $product->name }}"
                                        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300 {{ $isOutOfStock ? 'opacity-60 grayscale' : '' }}">

                                    @if($isOutOfStock)
                                        <span class="absolute top-2 left-2 px-2 py-0.5 rounded-md text-[10px] font-extrabold bg-rose-600 text-white">
                                            Out of Stock
                                        </span>
                                    @elseif($product->isFlashSaleActive())
                                        <span class="absolute top-2 left-2 px-2 py-0.5 rounded-md text-[10px] font-extrabold bg-amber-500 text-white flex items-center gap-1">
                                            <span class="material-symbols-outlined text-[12px]">bolt</span>
                                            <span>Flash Sale</span>
                                        </span>
                                    @endif
                                @endif

                                {{-- Remove from Wishlist Button --}}
                                <button
                                    type="button"
                                    @click="toggleWishlist({{ $product->id ?? $wish->product_id }})"
                                    title="Remove from wishlist"
                                    class="absolute top-2 right-2 w-8 h-8 rounded-full bg-white/90 backdrop-blur-xs text-rose-600 flex items-center justify-center shadow-xs hover:scale-110 transition-transform">
                                    <span class="material-symbols-outlined text-[18px]" style="font-variation-settings: 'FILL' 1">favorite</span>
                                </button>
                            </div>

                            {{-- Product Details --}}
                            <div class="p-3.5 flex flex-col gap-1.5 flex-1 justify-between">
                                <div>
                                    <span class="text-[10px] font-semibold text-primary uppercase tracking-wider block truncate">
                                        {{ $product->seller->sellerProfile->business_name ?? 'Store' }}
                                    </span>
                                    <h4 class="text-xs font-bold text-on-surface line-clamp-2">
                                        {{ $product->name ?? 'Unavailable Product' }}
                                    </h4>
                                </div>

                                <div class="pt-2 border-t border-outline-variant/60 flex items-center justify-between">
                                    <div>
                                        @if(!$isDeleted)
                                            <span class="text-xs font-extrabold text-primary">
                                                ₱{{ number_format($product->effective_price, 2) }}
                                            </span>
                                            @if($product->compare_price && $product->compare_price > $product->effective_price)
                                                <span class="text-[10px] text-outline line-through ml-1">
                                                    ₱{{ number_format($product->compare_price, 2) }}
                                                </span>
                                            @endif
                                        @else
                                            <span class="text-[11px] text-outline italic">No longer available</span>
                                        @endif
                                    </div>

                                    @if(!$isDeleted && !$isOutOfStock)
                                        <a href="{{ url('/products/' . $product->id) }}" class="px-2.5 py-1 rounded-lg bg-primary text-on-primary text-[11px] font-semibold hover:opacity-90">
                                            View
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Tab 2: Followed Stores --}}
        <div x-show="activeTab === 'stores'">
            @if($followedStores->isEmpty())
                <div class="p-12 text-center bg-surface-container-lowest rounded-3xl border border-outline-variant max-w-lg mx-auto flex flex-col items-center gap-3">
                    <span class="material-symbols-outlined text-outline text-[48px]">storefront</span>
                    <h3 class="text-sm font-bold text-on-surface">You are not following any stores yet</h3>
                    <p class="text-xs text-on-surface-variant">Follow your favorite merchants to discover their new arrivals and exclusive store vouchers.</p>
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($followedStores as $seller)
                        <div class="p-4 rounded-2xl bg-surface-container-lowest border border-outline-variant flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <img src="{{ $seller->avatar_url }}" alt="{{ $seller->full_name }}" class="w-12 h-12 rounded-full object-cover border border-outline-variant">
                                <div>
                                    <h4 class="text-xs font-bold text-on-surface">
                                        {{ $seller->sellerProfile->business_name ?? $seller->full_name }}
                                    </h4>
                                    <p class="text-[11px] text-on-surface-variant">Merchant Partner</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <a href="{{ route('catalog.index', ['seller_id' => $seller->id]) }}" class="px-3 py-1.5 rounded-xl border border-outline-variant text-xs font-semibold text-on-surface hover:bg-surface-container">
                                    Visit
                                </a>
                                <button
                                    type="button"
                                    @click="toggleFollow({{ $seller->id }})"
                                    class="px-3 py-1.5 rounded-xl bg-surface-container text-xs font-semibold text-on-surface-variant hover:text-rose-600">
                                    Following
                                </button>
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
    function wishlistApp() {
        return {
            activeTab: 'items',
            async toggleWishlist(productId) {
                try {
                    const res = await fetch(`/buyer/wishlist/${productId}/toggle`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        }
                    });
                    const data = await res.json();
                    if (data.success) {
                        window.location.reload();
                    }
                } catch (e) {
                    console.error('Failed to toggle wishlist', e);
                }
            },
            async toggleFollow(sellerId) {
                try {
                    const res = await fetch(`/buyer/stores/${sellerId}/follow-toggle`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        }
                    });
                    const data = await res.json();
                    if (data.success) {
                        window.location.reload();
                    }
                } catch (e) {
                    console.error('Failed to toggle store follow', e);
                }
            }
        };
    }
</script>
@endpush
