@extends('layouts.app')

@section('title', 'Nexora Market')

@section('content')
<div class="flex flex-col w-full">

    {{-- Hero --}}
    <section class="relative w-full overflow-hidden bg-surface-container pb-24 pt-12 md:pb-32 lg:pb-48">
        <div class="max-w-none px-margin-mobile md:px-margin-desktop relative z-10 grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">

            <div class="flex flex-col gap-6 md:gap-8 max-w-2xl pt-16 md:pt-24 lg:pt-32">
                <span class="inline-flex items-center self-start gap-2 rounded-full bg-primary/10 px-4 py-2 font-label-md text-label-md text-primary">
                    <span class="material-symbols-outlined text-[16px] font-bold">local_mall</span>
                    Curated Shopping Experience
                </span>

                <h1 class="font-display-lg text-display-lg text-on-surface leading-tight text-4xl md:text-5xl lg:text-7xl">
                    Find Something <br>
                    <span class="text-primary italic">You'll Love.</span>
                </h1>

                <p class="font-body-lg text-body-lg text-on-surface-variant max-w-xl">
                    Discover products, explore new arrivals, and shop from a wide selection—all in one place.
                </p>

                <div class="flex flex-col sm:flex-row gap-4 pt-4">
                    <a href="{{ url('/products') }}" class="flex items-center justify-center gap-2 rounded-full bg-primary px-8 py-4 font-label-md text-label-md text-on-primary shadow-lg shadow-primary/20 transition-transform hover:scale-105">
                        Shop Now
                        <span class="material-symbols-outlined text-[20px]">arrow_forward</span>
                    </a>
                    <a href="{{ url('/categories') }}" class="flex items-center justify-center gap-2 rounded-full bg-surface px-8 py-4 font-label-md text-label-md text-on-surface shadow-md shadow-surface-variant transition-transform hover:scale-105">
                        Explore Categories
                    </a>
                </div>

                <div class="mt-8 flex items-center gap-6 pt-4 border-t border-outline-variant/50 max-w-md">
                    <div class="flex -space-x-3">
                        @foreach($heroReviewerAvatars as $avatar)
                            <img class="w-10 h-10 rounded-full object-cover shadow-sm ring-2 ring-surface" src="{{ $avatar }}" alt="">
                        @endforeach
                    </div>
                    <div class="flex flex-col">
                        <div class="flex text-tertiary">
                            @for($i = 0; $i < 5; $i++)
                                <span class="material-symbols-outlined text-[16px]" style="font-variation-settings: 'FILL' 1">star</span>
                            @endfor
                        </div>
                        <span class="font-label-sm text-label-sm text-on-surface-variant">4.9/5 from 10k+ reviews</span>
                    </div>
                </div>
            </div>

            <div class="relative h-[400px] md:h-[600px] w-full mt-12 lg:mt-0">
                <div class="absolute inset-0 rounded-3xl overflow-hidden shadow-2xl">
                    <img class="w-full h-full object-cover" src="{{ $heroImage }}" alt="Featured product setup">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/40 to-transparent"></div>
                </div>

                <div class="absolute -left-12 bottom-24 bg-surface rounded-2xl p-4 shadow-xl flex items-center gap-4 animate-[bounce_6s_ease-in-out_infinite]">
                    <img class="w-16 h-16 rounded-xl object-cover" src="{{ $heroFeaturedProduct['image'] }}" alt="">
                    <div class="flex flex-col">
                        <span class="font-label-md text-label-md text-on-surface">{{ $heroFeaturedProduct['name'] }}</span>
                        <span class="font-headline-sm text-headline-sm text-primary">₱{{ number_format($heroFeaturedProduct['price'], 2) }}</span>
                    </div>
                </div>

                <div class="absolute -right-8 top-32 bg-surface rounded-2xl p-4 shadow-xl flex items-center gap-3 animate-[bounce_5s_ease-in-out_infinite_reverse]">
                    <div class="w-12 h-12 rounded-full bg-secondary-container flex items-center justify-center text-on-secondary-container">
                        <span class="material-symbols-outlined text-[24px]">verified</span>
                    </div>
                    <div class="flex flex-col pr-4">
                        <span class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider">Top Rated Seller</span>
                        <span class="font-label-md text-label-md text-on-surface">{{ $heroFeaturedProduct['seller'] }}</span>
                    </div>
                </div>
            </div>

        </div>

        <div class="absolute top-0 right-0 -translate-y-1/4 translate-x-1/4 w-[800px] h-[800px] bg-primary/5 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute bottom-0 left-0 translate-y-1/3 -translate-x-1/4 w-[600px] h-[600px] bg-tertiary/5 rounded-full blur-3xl pointer-events-none"></div>
    </section>

    {{-- Featured Sellers --}}
    <section class="py-24 bg-surface w-full">
        <div class="max-w-none px-margin-mobile md:px-margin-desktop">
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-12">
                <div>
                    <h2 class="font-headline-lg text-headline-lg text-on-surface mb-2">Featured Sellers</h2>
                    <p class="font-body-md text-body-md text-on-surface-variant">Discover top-rated stores with exceptional products.</p>
                </div>
                <a href="{{ url('/sellers') }}" class="text-primary font-label-md text-label-md hover:underline flex items-center gap-1">
                    View All Sellers <span class="material-symbols-outlined text-[18px]">chevron_right</span>
                </a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                @foreach($featuredSellers as $store)
                    <x-store-card :store="$store" />
                @endforeach
            </div>
        </div>
    </section>

    {{-- Shop by Category --}}
    <section class="py-24 bg-surface w-full">
        <div class="max-w-none px-margin-mobile md:px-margin-desktop">
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-12">
                <div>
                    <h2 class="font-headline-lg text-headline-lg text-on-surface mb-2">Shop by Category</h2>
                    <p class="font-body-md text-body-md text-on-surface-variant">Explore thousands of products across trending categories.</p>
                </div>
                <a href="{{ url('/categories') }}" class="text-primary font-label-md text-label-md hover:underline flex items-center gap-1">
                    View All Categories <span class="material-symbols-outlined text-[18px]">chevron_right</span>
                </a>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-gutter">
                @foreach($categories as $category)
                    <x-category-card :category="$category" />
                @endforeach
            </div>
        </div>
    </section>

    {{-- Promotional Banner --}}
    <section class="py-12 bg-surface">
        <div class="max-w-none px-margin-mobile md:px-margin-desktop">
            <div class="relative w-full rounded-[32px] overflow-hidden shadow-2xl flex flex-col md:flex-row bg-inverse-surface items-center">
                <div class="p-12 md:p-16 lg:p-24 flex-1 flex flex-col gap-6 relative z-10">
                    <div class="inline-flex px-3 py-1 bg-surface-container-lowest/10 text-on-secondary rounded-full font-label-sm text-label-sm uppercase tracking-widest self-start backdrop-blur-md">
                        {{ $promo['tag'] }}
                    </div>
                    <h2 class="font-display-lg text-display-lg text-on-primary">{!! $promo['headline'] !!}</h2>
                    <p class="font-body-lg text-body-lg text-on-primary/80 max-w-md">{{ $promo['description'] }}</p>
                    <a href="{{ $promo['url'] ?? '#' }}" class="mt-4 bg-primary text-on-primary font-label-md text-label-md px-8 py-4 rounded-full self-start hover:bg-primary/90 transition-colors shadow-lg">
                        {{ $promo['cta'] }}
                    </a>
                </div>
                <div class="flex-1 w-full h-[400px] md:h-full relative">
                    <img class="absolute inset-0 w-full h-full object-cover" src="{{ $promo['image'] }}" alt="{{ $promo['headline'] }}">
                    <div class="absolute inset-0 bg-gradient-to-r from-inverse-surface via-inverse-surface/80 to-transparent"></div>
                </div>
            </div>
        </div>
    </section>

    {{-- New Arrivals --}}
    <section class="py-24 bg-surface w-full">
        <div class="max-w-none px-margin-mobile md:px-margin-desktop">
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-12">
                <div>
                    <h2 class="font-headline-lg text-headline-lg text-on-surface mb-2">New Arrivals</h2>
                    <p class="font-body-md text-body-md text-on-surface-variant">Freshly added items you won't want to miss.</p>
                </div>
                <div class="flex gap-2">
                    <button type="button" class="w-12 h-12 rounded-full bg-surface-container flex items-center justify-center text-on-surface hover:bg-surface-variant transition-colors">
                        <span class="material-symbols-outlined">arrow_back</span>
                    </button>
                    <button type="button" class="w-12 h-12 rounded-full bg-primary flex items-center justify-center text-on-primary shadow-md hover:scale-105 transition-transform">
                        <span class="material-symbols-outlined">arrow_forward</span>
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-gutter">
                @foreach($newArrivals as $product)
                    <x-product-card :product="$product" />
                @endforeach
            </div>
        </div>
    </section>

    {{-- Trending Now --}}
    <section class="py-24 bg-surface-container-low w-full">
        <div class="max-w-none px-margin-mobile md:px-margin-desktop">
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-12">
                <div>
                    <h2 class="font-headline-lg text-headline-lg text-on-surface mb-2">Trending Now</h2>
                    <p class="font-body-md text-body-md text-on-surface-variant">Top-rated items loved by the Nexora community.</p>
                </div>
                <div class="flex gap-2">
                    <button type="button" class="w-12 h-12 rounded-full bg-surface-container flex items-center justify-center text-on-surface hover:bg-surface-variant transition-colors">
                        <span class="material-symbols-outlined">arrow_back</span>
                    </button>
                    <button type="button" class="w-12 h-12 rounded-full bg-primary flex items-center justify-center text-on-primary shadow-md hover:scale-105 transition-transform">
                        <span class="material-symbols-outlined">arrow_forward</span>
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-gutter">
                @foreach($trendingProducts as $product)
                    <x-product-card :product="$product" />
                @endforeach
            </div>
        </div>
    </section>

    {{-- Customer Reviews --}}
    <section class="py-24 bg-surface w-full border-t border-outline-variant/30">
        <div class="max-w-none px-margin-mobile md:px-margin-desktop">
            <div class="text-center mb-12">
                <h2 class="font-headline-lg text-headline-lg text-on-surface mb-2">What Our Customers Say</h2>
                <p class="font-body-md text-body-md text-on-surface-variant">Join thousands of happy shoppers.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                @foreach($reviews as $review)
                    <div class="bg-surface-container-lowest p-8 rounded-3xl border border-outline-variant flex flex-col gap-4 shadow-sm">
                        <div class="flex text-tertiary">
                            @for($i = 0; $i < floor($review['rating']); $i++)
                                <span class="material-symbols-outlined text-[20px]" style="font-variation-settings: 'FILL' 1">star</span>
                            @endfor
                            @if($review['rating'] - floor($review['rating']) >= 0.5)
                                <span class="material-symbols-outlined text-[20px]" style="font-variation-settings: 'FILL' 1">star_half</span>
                            @endif
                        </div>
                        <p class="font-body-md text-body-md text-on-surface italic">&quot;{{ $review['quote'] }}&quot;</p>
                        <div class="flex items-center gap-4 mt-4">
                            <img class="w-12 h-12 rounded-full object-cover" src="{{ $review['avatar'] }}" alt="{{ $review['name'] }}">
                            <div class="flex flex-col">
                                <span class="font-label-md text-label-md text-on-surface">{{ $review['name'] }}</span>
                                <span class="font-body-sm text-body-sm text-on-surface-variant">Verified Buyer</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Final CTA --}}
    <section class="py-24 bg-surface-container-high w-full">
        <div class="max-w-none px-margin-mobile md:px-margin-desktop text-center">
            <h2 class="font-display-lg text-display-lg text-on-surface mb-6">Ready to discover something new?</h2>
            <a href="{{ url('/products') }}" class="inline-block bg-primary text-on-primary font-label-md text-label-md px-10 py-5 rounded-full hover:bg-primary/90 transition-colors shadow-lg shadow-primary/20 hover:scale-105 transition-transform text-lg">
                Start Shopping
            </a>
        </div>
    </section>

</div>
@endsection
