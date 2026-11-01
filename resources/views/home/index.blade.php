@extends('layouts.app')

@section('title', 'Nexora Market — Modern Philippine Multi-Vendor Marketplace')

@section('content')
<div class="flex flex-col w-full">

    {{-- Hero Section (Product-First Modern E-Commerce Layout) --}}
    <section class="relative w-full bg-gradient-to-b from-surface-container-low via-surface to-background py-6 md:py-10 border-b border-outline-variant/40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8 items-center">

            {{-- Left Content Column --}}
            <div class="lg:col-span-7 flex flex-col gap-3 md:gap-4">
                
                <span class="inline-flex self-start rounded-full bg-primary/10 border border-primary/20 px-3 py-0.5 text-xs font-semibold text-primary">
                    Curated Multi-Vendor Marketplace
                </span>

                <h1 class="font-display-lg text-2xl sm:text-3xl lg:text-4xl font-extrabold text-on-surface tracking-tight leading-tight">
                    Find Something <br class="hidden sm:inline">
                    <span class="text-primary">You'll Love.</span>
                </h1>

                <p class="font-body-md text-xs sm:text-sm text-on-surface-variant max-w-xl leading-relaxed">
                    Discover authentic fashion, electronics, and lifestyle goods from verified merchants across the Philippines. Enjoy nationwide delivery, Cash on Delivery, and hassle-free buyer protection.
                </p>

                {{-- Action Buttons --}}
                <div class="flex flex-wrap items-center gap-2.5 pt-1">
                    <a href="{{ url('/catalog') }}" class="flex items-center justify-center gap-2 rounded-full bg-primary px-5 py-2.5 font-label-md text-xs sm:text-sm font-semibold text-on-primary shadow-sm hover:bg-primary/90 transition-all">
                        <span>Shop All Deals</span>
                        <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                    </a>
                    <a href="#categories" class="flex items-center justify-center gap-2 rounded-full bg-surface-container-lowest border border-outline-variant/80 px-5 py-2.5 font-label-md text-xs sm:text-sm font-semibold text-on-surface hover:bg-surface-container hover:border-outline transition-all">
                        <span>Explore Categories</span>
                    </a>
                </div>

                {{-- Social Proof Strip --}}
                <div class="mt-1 flex items-center gap-3 pt-3 border-t border-outline-variant/50 max-w-md">
                    <div class="flex -space-x-2">
                        @foreach($heroReviewerAvatars as $avatar)
                            <img class="w-7 h-7 rounded-full object-cover ring-2 ring-surface-container-lowest shadow-xs" src="{{ $avatar }}" alt="Verified Buyer">
                        @endforeach
                    </div>
                    <div class="flex flex-col">
                        <div class="flex text-tertiary">
                            @for($i = 0; $i < 5; $i++)
                                <span class="material-symbols-outlined text-[14px]" style="font-variation-settings: 'FILL' 1">star</span>
                            @endfor
                        </div>
                        <span class="font-label-sm text-[11px] text-on-surface-variant font-medium">4.9/5 from 10k+ verified Filipino shoppers</span>
                    </div>
                </div>

            </div>

            {{-- Right Spotlight Graphic Column --}}
            <div class="lg:col-span-5 relative w-full">
                <div class="relative w-full aspect-[4/3] sm:aspect-[16/10] lg:aspect-square rounded-2xl overflow-hidden shadow-md border border-outline-variant/60 bg-surface-container">
                    <img class="w-full h-full object-cover" src="{{ $heroImage }}" alt="Featured products on Nexora Market">
                    <div class="absolute inset-0 bg-gradient-to-t from-inverse-surface/60 via-transparent to-transparent"></div>

                    {{-- Featured Product Showcase Pill (Clean & minimal) --}}
                    <div class="absolute bottom-3 left-3 right-3 bg-surface-container-lowest/95 backdrop-blur-md rounded-xl p-3 shadow-md border border-outline-variant/60 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <img class="w-10 h-10 rounded-lg object-cover bg-surface-container flex-shrink-0" src="{{ $heroFeaturedProduct['image'] }}" alt="{{ $heroFeaturedProduct['name'] }}">
                            <div class="flex flex-col min-w-0">
                                <span class="text-[9px] font-bold text-primary uppercase tracking-wider">
                                    {{ $heroFeaturedProduct['seller'] }}
                                </span>
                                <h4 class="font-headline-sm text-xs font-bold text-on-surface truncate">{{ $heroFeaturedProduct['name'] }}</h4>
                                <span class="font-headline-md text-xs sm:text-sm font-extrabold text-primary">₱{{ number_format($heroFeaturedProduct['price'], 2) }}</span>
                            </div>
                        </div>

                        <a href="{{ url('/catalog') }}" class="px-3 py-1 rounded-full bg-primary text-on-primary text-xs font-semibold hover:bg-primary/90 transition-colors flex-shrink-0">
                            View Deal
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </section>

    {{-- Shop by Category Section --}}
    <section id="categories" class="py-8 md:py-10 bg-surface w-full">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3 mb-5 md:mb-6">
                <div>
                    <p class="text-primary font-bold text-xs uppercase tracking-wider mb-0.5">Browse Collections</p>
                    <h2 class="font-headline-lg text-lg md:text-xl font-bold text-on-surface">Shop by Category</h2>
                </div>
                <a href="{{ url('/catalog') }}" class="text-primary font-label-md text-xs font-semibold hover:underline flex items-center gap-1 self-start sm:self-auto">
                    <span>View All Categories</span>
                    <span class="material-symbols-outlined text-[16px]">chevron_right</span>
                </a>
            </div>

            {{-- 2 cols on small mobile, 4 on tablet, 8 on desktop --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-2.5 md:gap-3">
                @foreach($categories as $category)
                    <x-category-card :category="$category" />
                @endforeach
            </div>
        </div>
    </section>

    {{-- Trending Deals Section (Shopee/Lazada style with 2-column mobile grid) --}}
    <section class="py-8 md:py-10 bg-surface-container-low/50 w-full border-t border-b border-outline-variant/40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3 mb-5 md:mb-6">
                <div>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full bg-error/10 text-error font-bold text-[10px] uppercase tracking-wider mb-1">
                        Top Picks This Week
                    </span>
                    <h2 class="font-headline-lg text-lg md:text-xl font-bold text-on-surface">Trending Deals</h2>
                    <p class="font-body-sm text-xs text-on-surface-variant mt-0.5">Top-rated items loved by the Nexora community.</p>
                </div>
                <a href="{{ url('/catalog') }}" class="text-primary font-label-md text-xs font-semibold hover:underline flex items-center gap-1 self-start sm:self-auto">
                    <span>See All Trending</span>
                    <span class="material-symbols-outlined text-[16px]">chevron_right</span>
                </a>
            </div>

            {{-- 2-column mobile grid, 4-column desktop grid --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-4">
                @foreach($trendingProducts as $product)
                    <x-product-card :product="$product" />
                @endforeach
            </div>
        </div>
    </section>

    {{-- Promotional Highlight Banner --}}
    <section class="py-6 md:py-8 bg-surface">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="relative w-full rounded-2xl overflow-hidden shadow-md flex flex-col md:flex-row bg-inverse-surface items-center">
                <div class="p-5 md:p-8 lg:p-10 flex-1 flex flex-col gap-3 md:gap-4 relative z-10">
                    <span class="inline-flex items-center px-2.5 py-0.5 bg-surface-container-lowest/15 text-on-primary rounded-full font-label-sm text-[10px] uppercase tracking-wider self-start backdrop-blur-md border border-white/20">
                        {{ $promo['tag'] }}
                    </span>
                    <h2 class="font-display-lg text-xl md:text-2xl lg:text-3xl font-extrabold text-on-primary leading-tight">
                        {!! $promo['headline'] !!}
                    </h2>
                    <p class="font-body-md text-xs sm:text-sm text-on-primary/80 max-w-md leading-relaxed">
                        {{ $promo['description'] }}
                    </p>
                    <a href="{{ $promo['url'] ?? url('/catalog') }}" class="mt-1 bg-primary text-on-primary font-label-md text-xs sm:text-sm font-semibold px-5 py-2.5 rounded-full self-start hover:bg-primary/90 transition-all shadow-sm">
                        {{ $promo['cta'] }}
                    </a>
                </div>
                <div class="flex-1 w-full h-[200px] md:h-full relative min-h-[200px] md:min-h-[260px]">
                    <img class="absolute inset-0 w-full h-full object-cover" src="{{ $promo['image'] }}" alt="{{ $promo['headline'] }}">
                    <div class="absolute inset-0 bg-gradient-to-t md:bg-gradient-to-r from-inverse-surface via-inverse-surface/60 to-transparent"></div>
                </div>
            </div>
        </div>
    </section>

    {{-- New Arrivals Section --}}
    <section class="py-8 md:py-10 bg-surface w-full">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3 mb-5 md:mb-6">
                <div>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full bg-primary/10 text-primary font-bold text-[10px] uppercase tracking-wider mb-1">
                        Just Landed
                    </span>
                    <h2 class="font-headline-lg text-lg md:text-xl font-bold text-on-surface">New Arrivals</h2>
                    <p class="font-body-sm text-xs text-on-surface-variant mt-0.5">Freshly listed products from verified independent stores.</p>
                </div>
                <a href="{{ url('/catalog') }}" class="text-primary font-label-md text-xs font-semibold hover:underline flex items-center gap-1 self-start sm:self-auto">
                    <span>View More New Arrivals</span>
                    <span class="material-symbols-outlined text-[16px]">chevron_right</span>
                </a>
            </div>

            {{-- 2-column mobile grid, 4-column desktop grid --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-4">
                @foreach($newArrivals as $product)
                    <x-product-card :product="$product" />
                @endforeach
            </div>
        </div>
    </section>

    {{-- Featured Verified Sellers Section --}}
    <section class="py-8 md:py-10 bg-surface-container-low/50 w-full border-t border-outline-variant/40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3 mb-5 md:mb-6">
                <div>
                    <p class="text-primary font-bold text-xs uppercase tracking-wider mb-0.5">Multi-Vendor Showcase</p>
                    <h2 class="font-headline-lg text-lg md:text-xl font-bold text-on-surface">Featured Stores</h2>
                    <p class="font-body-sm text-xs text-on-surface-variant mt-0.5">Discover top-rated sellers with verified ratings and rapid dispatch.</p>
                </div>
                <a href="{{ url('/catalog') }}" class="text-primary font-label-md text-xs font-semibold hover:underline flex items-center gap-1 self-start sm:self-auto">
                    <span>Browse All Sellers</span>
                    <span class="material-symbols-outlined text-[16px]">chevron_right</span>
                </a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-5">
                @foreach($featuredSellers as $store)
                    <x-store-card :store="$store" />
                @endforeach
            </div>
        </div>
    </section>

    {{-- Customer Reviews & Testimonials --}}
    <section class="py-8 md:py-10 bg-surface w-full border-t border-outline-variant/40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-lg mx-auto mb-6 md:mb-8">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full bg-primary/10 text-primary font-bold text-[10px] uppercase tracking-wider mb-1.5">
                    Buyer Experiences
                </span>
                <h2 class="font-headline-lg text-lg md:text-xl font-bold text-on-surface">What Our Customers Say</h2>
                <p class="font-body-sm text-xs text-on-surface-variant mt-0.5">Real feedback from verified shoppers across the country.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-5">
                @foreach($reviews as $review)
                    <div class="bg-surface-container-lowest p-4 sm:p-5 rounded-2xl border border-outline-variant/60 flex flex-col justify-between gap-3 shadow-xs">
                        <div class="flex flex-col gap-2.5">
                            <div class="flex text-tertiary">
                                @for($i = 0; $i < floor($review['rating']); $i++)
                                    <span class="material-symbols-outlined text-[16px]" style="font-variation-settings: 'FILL' 1">star</span>
                                @endfor
                                @if($review['rating'] - floor($review['rating']) >= 0.5)
                                    <span class="material-symbols-outlined text-[16px]" style="font-variation-settings: 'FILL' 1">star_half</span>
                                @endif
                            </div>
                            <p class="font-body-md text-xs text-on-surface italic leading-relaxed">&quot;{{ $review['quote'] }}&quot;</p>
                        </div>

                        <div class="flex items-center gap-2.5 pt-2.5 border-t border-outline-variant/40">
                            <img class="w-8 h-8 rounded-full object-cover ring-2 ring-primary/20" src="{{ $review['avatar'] }}" alt="{{ $review['name'] }}">
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

    {{-- Final Shopping Call to Action --}}
    <section class="py-8 md:py-12 bg-surface-container-low w-full border-t border-outline-variant/40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center flex flex-col items-center gap-3 max-w-xl mx-auto">
            <h2 class="font-display-lg text-xl md:text-2xl font-bold text-on-surface">Ready to start shopping?</h2>
            <p class="font-body-md text-xs sm:text-sm text-on-surface-variant max-w-md">
                Find authentic deals, follow verified stores, and enjoy convenient Cash on Delivery nationwide.
            </p>
            <a href="{{ url('/catalog') }}" class="mt-1 inline-flex items-center gap-2 bg-primary text-on-primary font-label-md text-xs sm:text-sm font-semibold px-6 py-2.5 rounded-full hover:bg-primary/90 shadow-sm transition-all">
                <span>Start Shopping Now</span>
                <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
            </a>
        </div>
    </section>

</div>
@endsection
