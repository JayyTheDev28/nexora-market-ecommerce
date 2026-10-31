@extends('layouts.app')

@section('title', 'Nexora Market — Modern Philippine Multi-Vendor Marketplace')

@section('content')
<div class="flex flex-col w-full">

    {{-- Top Hero Command Section (Modern 3-Column Marketplace Architecture) --}}
    <section class="relative w-full bg-gradient-to-b from-surface-container-low via-surface to-background py-6 md:py-8 border-b border-outline-variant/40">
        <div class="max-w-none px-4 md:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-stretch">

                {{-- Column 1: Department Sidebar Rail (Desktop Only) --}}
                <div class="hidden lg:flex lg:col-span-3 flex-col bg-surface-container-lowest rounded-3xl border border-outline-variant/60 p-4 shadow-xs justify-between">
                    <div>
                        <div class="flex items-center justify-between pb-3 mb-2 border-b border-outline-variant/50">
                            <h2 class="text-xs font-bold text-on-surface uppercase tracking-wider flex items-center gap-2">
                                <span class="material-symbols-outlined text-[18px] text-primary">category</span>
                                <span>Departments</span>
                            </h2>
                            <a href="{{ url('/catalog') }}" class="text-[11px] font-semibold text-primary hover:underline">
                                All Categories
                            </a>
                        </div>
                        <nav class="flex flex-col gap-1">
                            @foreach(array_slice($categories, 0, 7) as $cat)
                                <a href="{{ $cat['url'] }}" class="flex items-center justify-between px-3 py-2 rounded-xl text-xs font-medium text-on-surface-variant hover:text-primary hover:bg-surface-container-low transition-all group">
                                    <div class="flex items-center gap-2.5">
                                        <span class="material-symbols-outlined text-[17px] text-outline group-hover:text-primary transition-colors">{{ $cat['icon'] }}</span>
                                        <span>{{ $cat['name'] }}</span>
                                    </div>
                                    <span class="material-symbols-outlined text-[14px] text-outline-variant group-hover:translate-x-0.5 transition-transform">chevron_right</span>
                                </a>
                            @endforeach
                        </nav>
                    </div>

                    <a href="{{ url('/catalog') }}" class="mt-4 pt-3 border-t border-outline-variant/50 flex items-center justify-between px-3 py-2 text-xs font-bold text-primary hover:underline">
                        <span>Browse Full Catalog</span>
                        <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                    </a>
                </div>

                {{-- Column 2: Center Main Promotional Showcase --}}
                <div class="lg:col-span-6 flex flex-col justify-between bg-surface-container-lowest rounded-3xl border border-outline-variant/60 p-6 sm:p-8 lg:p-10 shadow-sm relative overflow-hidden">
                    <div class="relative z-10 flex flex-col gap-4 max-w-xl">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="inline-flex rounded-full bg-primary/10 border border-primary/20 px-3 py-1 text-xs font-semibold text-primary">
                                Curated Multi-Vendor Marketplace
                            </span>
                            <span class="inline-flex rounded-full bg-error/10 border border-error/20 px-3 py-1 text-xs font-bold text-error">
                                Special Offers Active
                            </span>
                        </div>

                        <h1 class="font-display-lg text-2xl sm:text-4xl lg:text-[40px] font-extrabold text-on-surface tracking-tight leading-[1.15]">
                            Find Something <br class="hidden sm:inline">
                            <span class="text-primary bg-gradient-to-r from-primary to-primary-container bg-clip-text text-transparent">You'll Love.</span>
                        </h1>

                        <p class="font-body-md text-xs sm:text-sm text-on-surface-variant leading-relaxed">
                            Discover authentic fashion, electronics, and lifestyle goods from verified merchants across the Philippines. Enjoy nationwide express delivery, Cash on Delivery, and 7-day buyer protection.
                        </p>

                        {{-- Action Buttons --}}
                        <div class="flex flex-wrap items-center gap-3 pt-2">
                            <a href="{{ url('/catalog') }}" class="flex items-center justify-center gap-2 rounded-full bg-primary px-7 py-3 font-label-md text-xs sm:text-sm font-semibold text-on-primary shadow-md shadow-primary/25 hover:bg-primary/90 hover:scale-[1.02] active:scale-95 transition-all">
                                <span>Shop All Deals</span>
                                <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                            </a>
                            <a href="#special-offers" class="flex items-center justify-center gap-2 rounded-full bg-surface-container-low border border-outline-variant/80 px-5 py-3 font-label-md text-xs sm:text-sm font-semibold text-on-surface hover:bg-surface-container hover:border-outline transition-all">
                                <span>Special Offers</span>
                            </a>
                        </div>
                    </div>

                    {{-- Social Proof Strip --}}
                    <div class="relative z-10 mt-6 pt-4 border-t border-outline-variant/50 flex items-center gap-4">
                        <div class="flex -space-x-2">
                            @foreach($heroReviewerAvatars as $avatar)
                                <img class="w-7 h-7 rounded-full object-cover ring-2 ring-surface-container-lowest shadow-xs" src="{{ $avatar }}" alt="Verified Buyer">
                            @endforeach
                        </div>
                        <div class="flex flex-col">
                            <div class="flex text-amber-500">
                                @for($i = 0; $i < 5; $i++)
                                    <span class="material-symbols-outlined text-[14px]" style="font-variation-settings: 'FILL' 1">star</span>
                                @endfor
                            </div>
                            <span class="text-[11px] text-on-surface-variant font-medium">4.9/5 from 10k+ verified Filipino shoppers</span>
                        </div>
                    </div>

                    {{-- Subtle background geometric accent --}}
                    <div class="absolute -right-20 -bottom-20 w-80 h-80 rounded-full bg-primary/5 blur-3xl pointer-events-none"></div>
                </div>

                {{-- Column 3: Shopper Quick Command Hub (Desktop Only) --}}
                <div class="hidden lg:flex lg:col-span-3 flex-col gap-4">
                    {{-- User Quick Action Card --}}
                    <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant/60 p-5 shadow-xs flex flex-col justify-between flex-1">
                        <div>
                            <div class="flex items-center gap-3 mb-3">
                                <div class="w-10 h-10 rounded-2xl bg-primary/10 text-primary flex items-center justify-center font-bold text-base">
                                    @auth
                                        {{ strtoupper(substr(auth()->user()->first_name, 0, 1)) }}
                                    @else
                                        <span class="material-symbols-outlined text-[20px]">person</span>
                                    @endauth
                                </div>
                                <div class="flex flex-col min-w-0">
                                    <span class="text-xs font-bold text-on-surface truncate">
                                        @auth
                                            Hi, {{ auth()->user()->first_name }}!
                                        @else
                                            Welcome to Nexora!
                                        @endauth
                                    </span>
                                    <span class="text-[11px] text-on-surface-variant">Member Benefits & Perks</span>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-2 text-center text-xs">
                                <a href="{{ url('/buyer/orders') }}" class="p-2.5 rounded-xl bg-surface-container-low hover:bg-surface-container transition-colors flex flex-col items-center gap-1">
                                    <span class="material-symbols-outlined text-[18px] text-primary">local_shipping</span>
                                    <span class="text-[11px] font-semibold text-on-surface">Track Orders</span>
                                </a>
                                <a href="{{ url('/buyer/vouchers') }}" class="p-2.5 rounded-xl bg-surface-container-low hover:bg-surface-container transition-colors flex flex-col items-center gap-1">
                                    <span class="material-symbols-outlined text-[18px] text-primary">confirmation_number</span>
                                    <span class="text-[11px] font-semibold text-on-surface">Vouchers</span>
                                </a>
                            </div>
                        </div>

                        <div class="mt-4 pt-3 border-t border-outline-variant/50">
                            @auth
                                @if(auth()->user()->isSeller())
                                    <a href="{{ route('seller.dashboard') }}" class="w-full block py-2 px-3 rounded-xl bg-primary/10 hover:bg-primary/20 text-primary text-xs font-bold text-center transition-colors">
                                        Seller Management Hub →
                                    </a>
                                @else
                                    <a href="{{ route('buyer.account') }}" class="w-full block py-2 px-3 rounded-xl bg-surface-container-low hover:bg-surface-container text-on-surface text-xs font-semibold text-center transition-colors">
                                        Manage Account →
                                    </a>
                                @endif
                            @else
                                <a href="{{ route('login') }}" class="w-full block py-2 px-3 rounded-xl bg-primary text-on-primary text-xs font-bold text-center hover:bg-primary/90 transition-colors shadow-xs">
                                    Sign In / Register
                                </a>
                            @endauth
                        </div>
                    </div>

                    {{-- Daily Spotlight Deal Card --}}
                    <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant/60 p-4 shadow-xs flex items-center gap-3">
                        <img class="w-16 h-16 rounded-2xl object-cover bg-surface-container flex-shrink-0" src="{{ $heroFeaturedProduct['image'] }}" alt="{{ $heroFeaturedProduct['name'] }}">
                        <div class="flex flex-col min-w-0 flex-1">
                            <span class="text-[10px] font-bold text-primary uppercase tracking-wider truncate">Daily Pick</span>
                            <h4 class="text-xs font-bold text-on-surface truncate">{{ $heroFeaturedProduct['name'] }}</h4>
                            <span class="text-xs font-extrabold text-primary">₱{{ number_format($heroFeaturedProduct['price'], 2) }}</span>
                        </div>
                        <a href="{{ url('/catalog') }}" class="p-2 rounded-xl bg-primary/10 hover:bg-primary/20 text-primary flex items-center justify-center flex-shrink-0" aria-label="View deal">
                            <span class="material-symbols-outlined text-[18px]">chevron_right</span>
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </section>

    {{-- Value Propositions & Trust Strip --}}
    <section class="w-full py-4 bg-surface-container-lowest border-b border-outline-variant/40">
        <div class="max-w-none px-4 md:px-8">
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="flex items-center gap-3 p-2">
                    <span class="w-10 h-10 rounded-2xl bg-primary/10 text-primary flex items-center justify-center flex-shrink-0">
                        <span class="material-symbols-outlined text-[20px]">verified</span>
                    </span>
                    <div class="flex flex-col">
                        <span class="text-xs font-bold text-on-surface">100% Authentic</span>
                        <span class="text-[11px] text-on-surface-variant">Verified brand merchants</span>
                    </div>
                </div>

                <div class="flex items-center gap-3 p-2">
                    <span class="w-10 h-10 rounded-2xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center flex-shrink-0">
                        <span class="material-symbols-outlined text-[20px]">local_shipping</span>
                    </span>
                    <div class="flex flex-col">
                        <span class="text-xs font-bold text-on-surface">Nationwide Delivery</span>
                        <span class="text-[11px] text-on-surface-variant">Fast fulfillment hubs</span>
                    </div>
                </div>

                <div class="flex items-center gap-3 p-2">
                    <span class="w-10 h-10 rounded-2xl bg-amber-500/10 text-amber-600 flex items-center justify-center flex-shrink-0">
                        <span class="material-symbols-outlined text-[20px]">payments</span>
                    </span>
                    <div class="flex flex-col">
                        <span class="text-xs font-bold text-on-surface">Cash on Delivery</span>
                        <span class="text-[11px] text-on-surface-variant">Safe & secure checkout</span>
                    </div>
                </div>

                <div class="flex items-center gap-3 p-2">
                    <span class="w-10 h-10 rounded-2xl bg-purple-500/10 text-purple-600 flex items-center justify-center flex-shrink-0">
                        <span class="material-symbols-outlined text-[20px]">published_with_changes</span>
                    </span>
                    <div class="flex flex-col">
                        <span class="text-xs font-bold text-on-surface">7-Day Return Window</span>
                        <span class="text-[11px] text-on-surface-variant">Hassle-free buyer guarantee</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Shop by Category Section --}}
    <section id="categories" class="py-10 md:py-12 bg-surface w-full">
        <div class="max-w-none px-4 md:px-8">
            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-6">
                <div>
                    <p class="text-primary font-bold text-xs uppercase tracking-wider mb-1">Browse Collections</p>
                    <h2 class="font-headline-lg text-xl md:text-2xl font-bold text-on-surface">Shop by Category</h2>
                </div>
                <a href="{{ url('/catalog') }}" class="text-primary font-label-md text-xs font-semibold hover:underline flex items-center gap-1 self-start sm:self-auto">
                    <span>All Categories</span>
                    <span class="material-symbols-outlined text-[18px]">chevron_right</span>
                </a>
            </div>

            {{-- 2 cols on mobile, 4 on tablet, 8 on desktop --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3 md:gap-4">
                @foreach($categories as $category)
                    <x-category-card :category="$category" />
                @endforeach
            </div>
        </div>
    </section>

    {{-- Special Offers & Trending Deals Section --}}
    <section id="special-offers" class="py-10 md:py-12 bg-surface-container-low/50 w-full border-t border-b border-outline-variant/40">
        <div class="max-w-none px-4 md:px-8">
            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-6 md:mb-8">
                <div>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full bg-error/10 text-error font-bold text-[11px] uppercase tracking-wider mb-1">
                        Special Offers This Week
                    </span>
                    <h2 class="font-headline-lg text-xl md:text-2xl font-bold text-on-surface">Trending Deals</h2>
                    <p class="font-body-sm text-xs md:text-sm text-on-surface-variant mt-0.5">Top-rated items loved by the Nexora community.</p>
                </div>
                <a href="{{ url('/catalog') }}" class="text-primary font-label-md text-xs font-semibold hover:underline flex items-center gap-1 self-start sm:self-auto">
                    <span>See All Special Offers</span>
                    <span class="material-symbols-outlined text-[18px]">chevron_right</span>
                </a>
            </div>

            {{-- 2-column mobile grid, 4-column desktop grid --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-5">
                @foreach($trendingProducts as $product)
                    <x-product-card :product="$product" />
                @endforeach
            </div>
        </div>
    </section>

    {{-- Promotional Highlight Banner --}}
    <section class="py-8 md:py-10 bg-surface">
        <div class="max-w-none px-4 md:px-8">
            <div class="relative w-full rounded-3xl overflow-hidden shadow-xl flex flex-col md:flex-row bg-inverse-surface items-center">
                <div class="p-6 md:p-10 lg:p-12 flex-1 flex flex-col gap-4 md:gap-5 relative z-10">
                    <span class="inline-flex items-center px-3 py-1 bg-surface-container-lowest/15 text-on-primary rounded-full font-label-sm text-[11px] uppercase tracking-wider self-start backdrop-blur-md border border-white/20">
                        {{ $promo['tag'] }}
                    </span>
                    <h2 class="font-display-lg text-2xl md:text-3xl lg:text-4xl font-extrabold text-on-primary leading-tight">
                        {!! $promo['headline'] !!}
                    </h2>
                    <p class="font-body-md text-xs md:text-sm text-on-primary/80 max-w-md leading-relaxed">
                        {{ $promo['description'] }}
                    </p>
                    <a href="{{ $promo['url'] ?? url('/catalog') }}" class="mt-2 bg-primary text-on-primary font-label-md text-xs md:text-sm font-semibold px-6 py-3 rounded-full self-start hover:bg-primary/90 transition-all shadow-md hover:scale-105 active:scale-95">
                        {{ $promo['cta'] }}
                    </a>
                </div>
                <div class="flex-1 w-full h-[220px] md:h-full relative min-h-[220px] md:min-h-[300px]">
                    <img class="absolute inset-0 w-full h-full object-cover" src="{{ $promo['image'] }}" alt="{{ $promo['headline'] }}">
                    <div class="absolute inset-0 bg-gradient-to-t md:bg-gradient-to-r from-inverse-surface via-inverse-surface/60 to-transparent"></div>
                </div>
            </div>
        </div>
    </section>

    {{-- New Arrivals & All Products Section --}}
    <section class="py-10 md:py-12 bg-surface w-full">
        <div class="max-w-none px-4 md:px-8">
            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-6 md:mb-8">
                <div>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full bg-primary/10 text-primary font-bold text-[11px] uppercase tracking-wider mb-1">
                        Fresh Releases
                    </span>
                    <h2 class="font-headline-lg text-xl md:text-2xl font-bold text-on-surface">New Arrivals</h2>
                    <p class="font-body-sm text-xs md:text-sm text-on-surface-variant mt-0.5">Explore the latest listings from verified shops across All Products.</p>
                </div>
                <a href="{{ url('/catalog') }}" class="text-primary font-label-md text-xs font-semibold hover:underline flex items-center gap-1 self-start sm:self-auto">
                    <span>View All Products</span>
                    <span class="material-symbols-outlined text-[18px]">chevron_right</span>
                </a>
            </div>

            {{-- 2-column mobile grid, 4-column desktop grid --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-5">
                @foreach($newArrivals as $product)
                    <x-product-card :product="$product" />
                @endforeach
            </div>
        </div>
    </section>

    {{-- Featured Verified Sellers Section --}}
    <section class="py-10 md:py-12 bg-surface-container-low/50 w-full border-t border-outline-variant/40">
        <div class="max-w-none px-4 md:px-8">
            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-6 md:mb-8">
                <div>
                    <p class="text-primary font-bold text-xs uppercase tracking-wider mb-1">Official Flagships</p>
                    <h2 class="font-headline-lg text-xl md:text-2xl font-bold text-on-surface">Featured Stores</h2>
                    <p class="font-body-sm text-xs md:text-sm text-on-surface-variant mt-0.5">Shop directly from verified brand distributors with highest customer ratings.</p>
                </div>
                <a href="{{ url('/catalog') }}" class="text-primary font-label-md text-xs font-semibold hover:underline flex items-center gap-1 self-start sm:self-auto">
                    <span>Explore Stores</span>
                    <span class="material-symbols-outlined text-[18px]">chevron_right</span>
                </a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-5 md:gap-6">
                @foreach($featuredSellers as $store)
                    <x-store-card :store="$store" />
                @endforeach
            </div>
        </div>
    </section>

    {{-- Customer Reviews & Testimonials --}}
    <section class="py-10 md:py-12 bg-surface w-full border-t border-outline-variant/40">
        <div class="max-w-none px-4 md:px-8">
            <div class="text-center max-w-xl mx-auto mb-8">
                <span class="inline-flex items-center px-3 py-1 rounded-full bg-primary/10 text-primary font-bold text-[11px] uppercase tracking-wider mb-2">
                    Verified Feedback
                </span>
                <h2 class="font-headline-lg text-xl md:text-2xl font-bold text-on-surface">What Shoppers Are Saying</h2>
                <p class="font-body-sm text-xs md:text-sm text-on-surface-variant mt-1">Real reviews from buyers across the Philippines.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-5 md:gap-6">
                @foreach($reviews as $review)
                    <div class="bg-surface-container-lowest p-6 rounded-3xl border border-outline-variant/60 flex flex-col justify-between gap-4 shadow-xs">
                        <div class="flex flex-col gap-3">
                            <div class="flex text-amber-500">
                                @for($i = 0; $i < floor($review['rating']); $i++)
                                    <span class="material-symbols-outlined text-[18px]" style="font-variation-settings: 'FILL' 1">star</span>
                                @endfor
                                @if($review['rating'] - floor($review['rating']) >= 0.5)
                                    <span class="material-symbols-outlined text-[18px]" style="font-variation-settings: 'FILL' 1">star_half</span>
                                @endif
                            </div>
                            <p class="font-body-md text-xs md:text-sm text-on-surface italic leading-relaxed">&quot;{{ $review['quote'] }}&quot;</p>
                        </div>

                        <div class="flex items-center gap-3 pt-3 border-t border-outline-variant/40">
                            <img class="w-10 h-10 rounded-full object-cover ring-2 ring-primary/20" src="{{ $review['avatar'] }}" alt="{{ $review['name'] }}">
                            <div class="flex flex-col">
                                <span class="font-label-md text-xs font-bold text-on-surface">{{ $review['name'] }}</span>
                                <span class="text-[10px] text-primary font-semibold">Verified Buyer</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

</div>
@endsection
