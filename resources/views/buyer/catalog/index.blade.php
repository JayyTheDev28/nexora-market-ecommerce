@extends('layouts.app')

@section('title', 'Shop Catalog — Nexora Market')

@section('content')
<div
    x-data="catalogFilters({
        initialQuery: @js($initialQuery),
        initialCategory: @js($initialCategory),
        products: {{ collect($products)->map(fn($p) => ['id' => $p['id'], 'name' => $p['name'], 'category' => $p['category'], 'price' => (float) $p['price']])->values()->toJson() }},
        categories: {{ json_encode($categories) }}
    })"
    class="w-full bg-surface py-6 md:py-8 min-h-[75vh]">

    <div class="max-w-none px-4 md:px-8">

        {{-- Top Catalog Header & Controls Bar --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-outline-variant/60 mb-6">
            <div>
                <h1 class="font-headline-lg text-2xl md:text-3xl font-bold text-on-surface">Shop Catalog</h1>
                <p class="font-body-md text-xs md:text-sm text-on-surface-variant mt-0.5">
                    Showing <span class="font-bold text-on-surface" x-text="filteredIds.length"></span> product<span x-show="filteredIds.length !== 1">s</span>
                </p>
            </div>

            {{-- Sorting & Mobile Filter Trigger --}}
            <div class="flex items-center gap-3 self-stretch sm:self-auto">
                {{-- Mobile Filter Drawer Toggle (Hidden on md+) --}}
                <button
                    type="button"
                    @click="mobileFiltersOpen = true"
                    class="md:hidden flex-1 flex items-center justify-center gap-2 py-2 px-4 rounded-xl border border-outline-variant bg-surface-container-lowest font-label-md text-xs font-semibold text-on-surface hover:bg-surface-container transition-colors">
                    <span class="material-symbols-outlined text-[18px]">tune</span>
                    <span>Filters</span>
                    <span
                        x-show="activeFilterCount > 0"
                        x-text="activeFilterCount"
                        class="w-5 h-5 rounded-full bg-primary text-on-primary text-[10px] font-bold flex items-center justify-center">
                    </span>
                </button>

                {{-- Sort Dropdown --}}
                <div class="flex items-center gap-2 flex-1 sm:flex-none justify-end">
                    <span class="hidden sm:inline text-xs text-on-surface-variant font-medium">Sort by:</span>
                    <select
                        x-model="sortBy"
                        class="w-full sm:w-auto rounded-xl border border-outline-variant px-3 py-2 text-xs font-semibold text-on-surface bg-surface-container-lowest focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                        <option value="featured">Featured</option>
                        <option value="price_asc">Price: Low to High</option>
                        <option value="price_desc">Price: High to Low</option>
                        <option value="name_asc">Name: A to Z</option>
                    </select>
                </div>
            </div>
        </div>

        {{-- Active Filters Strip (Minimal tag chips) --}}
        <div
            x-show="activeFilterCount > 0"
            x-cloak
            class="flex flex-wrap items-center gap-2 mb-6 p-3 bg-surface-container-low/60 rounded-2xl border border-outline-variant/60">
            <span class="text-xs font-semibold text-on-surface-variant">Active Filters:</span>

            {{-- Search query chip --}}
            <span
                x-show="searchQuery.trim() !== ''"
                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-surface-container-lowest border border-outline-variant text-xs text-on-surface">
                Search: <strong class="truncate max-w-[120px]" x-text="searchQuery"></strong>
                <button type="button" @click="searchQuery = ''" class="hover:text-error text-on-surface-variant" aria-label="Clear search">
                    <span class="material-symbols-outlined text-[14px]">close</span>
                </button>
            </span>

            {{-- Selected categories chips --}}
            <template x-for="slug in selectedCategories" :key="slug">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-surface-container-lowest border border-outline-variant text-xs text-on-surface">
                    <span x-text="getCategoryName(slug)"></span>
                    <button type="button" @click="toggleCategory(slug)" class="hover:text-error text-on-surface-variant" aria-label="Remove filter">
                        <span class="material-symbols-outlined text-[14px]">close</span>
                    </button>
                </span>
            </template>

            {{-- Price range chip --}}
            <span
                x-show="minPrice || maxPrice"
                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-surface-container-lowest border border-outline-variant text-xs text-on-surface">
                Price: <span x-text="(minPrice ? '₱' + minPrice : '₱0') + ' - ' + (maxPrice ? '₱' + maxPrice : 'Any')"></span>
                <button type="button" @click="minPrice = ''; maxPrice = ''" class="hover:text-error text-on-surface-variant" aria-label="Clear price">
                    <span class="material-symbols-outlined text-[14px]">close</span>
                </button>
            </span>

            {{-- Clear all button --}}
            <button
                type="button"
                @click="clearAll()"
                class="ml-auto text-xs font-bold text-primary hover:underline cursor-pointer">
                Clear All
            </button>
        </div>

        {{-- Main Layout: Sidebar + Product Grid --}}
        <div class="flex flex-col md:flex-row gap-6 md:gap-8 items-start">

            {{-- Desktop Sidebar (Hidden on mobile) --}}
            <aside class="hidden md:block w-64 flex-shrink-0 sticky top-24">
                <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant/70 p-5 flex flex-col gap-6 shadow-xs">
                    
                    {{-- Search within catalog --}}
                    <div class="flex flex-col gap-2">
                        <label class="text-xs font-bold text-on-surface uppercase tracking-wider">Search</label>
                        <div class="relative">
                            <input
                                type="text"
                                x-model="searchQuery"
                                placeholder="Filter products…"
                                class="w-full rounded-xl border border-outline-variant px-3 py-2 text-xs text-on-surface bg-surface-container-low focus:bg-surface-container-lowest focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                            <button
                                type="button"
                                x-show="searchQuery"
                                @click="searchQuery = ''"
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-on-surface-variant hover:text-error">
                                <span class="material-symbols-outlined text-[14px]">close</span>
                            </button>
                        </div>
                    </div>

                    <div class="border-t border-outline-variant/60"></div>

                    {{-- Category Checkbox Filter --}}
                    <div class="flex flex-col gap-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-on-surface uppercase tracking-wider">Categories</span>
                            <button
                                type="button"
                                x-show="selectedCategories.length > 0"
                                @click="selectedCategories = []"
                                class="text-[11px] font-semibold text-primary hover:underline">
                                Reset
                            </button>
                        </div>

                        <div class="flex flex-col gap-2 max-h-64 overflow-y-auto pr-1">
                            @foreach($categories as $category)
                                <label class="flex items-center gap-2.5 cursor-pointer group py-0.5 select-none">
                                    <input
                                        type="checkbox"
                                        value="{{ $category['slug'] }}"
                                        x-model="selectedCategories"
                                        class="w-4 h-4 rounded border-outline-variant text-primary focus:ring-primary cursor-pointer">
                                    <span class="text-xs text-on-surface group-hover:text-primary transition-colors flex-1 truncate">
                                        {{ $category['name'] }}
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="border-t border-outline-variant/60"></div>

                    {{-- Price Range Filter --}}
                    <div class="flex flex-col gap-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-on-surface uppercase tracking-wider">Price Range</span>
                            <button
                                type="button"
                                x-show="minPrice || maxPrice"
                                @click="minPrice = ''; maxPrice = ''"
                                class="text-[11px] font-semibold text-primary hover:underline">
                                Reset
                            </button>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <input
                                type="number"
                                x-model.number="minPrice"
                                placeholder="₱ Min"
                                min="0"
                                class="w-full rounded-xl border border-outline-variant px-3 py-1.5 text-xs text-on-surface bg-surface-container-low focus:bg-surface-container-lowest focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                            <input
                                type="number"
                                x-model.number="maxPrice"
                                placeholder="₱ Max"
                                min="0"
                                class="w-full rounded-xl border border-outline-variant px-3 py-1.5 text-xs text-on-surface bg-surface-container-low focus:bg-surface-container-lowest focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                        </div>

                        <div class="flex flex-wrap gap-1.5 pt-1">
                            <button
                                type="button"
                                @click="minPrice = 0; maxPrice = 500"
                                class="px-2.5 py-1 rounded-lg bg-surface-container text-[11px] font-medium text-on-surface-variant hover:text-primary hover:bg-surface-container-high transition-colors">
                                Under ₱500
                            </button>
                            <button
                                type="button"
                                @click="minPrice = 500; maxPrice = 1500"
                                class="px-2.5 py-1 rounded-lg bg-surface-container text-[11px] font-medium text-on-surface-variant hover:text-primary hover:bg-surface-container-high transition-colors">
                                ₱500 - ₱1,500
                            </button>
                            <button
                                type="button"
                                @click="minPrice = 1500; maxPrice = ''"
                                class="px-2.5 py-1 rounded-lg bg-surface-container text-[11px] font-medium text-on-surface-variant hover:text-primary hover:bg-surface-container-high transition-colors">
                                ₱1,500+
                            </button>
                        </div>
                    </div>

                </div>
            </aside>

            {{-- Products Results Area --}}
            <div class="flex-1 w-full">

                {{-- Empty Results State --}}
                <div x-show="filteredIds.length === 0" x-cloak class="flex flex-col items-center justify-center py-20 px-4 text-center bg-surface-container-lowest rounded-3xl border border-outline-variant/60">
                    <div class="w-16 h-16 rounded-full bg-surface-container flex items-center justify-center text-outline mb-4">
                        <span class="material-symbols-outlined text-[32px]">search_off</span>
                    </div>
                    <h3 class="font-headline-sm text-base md:text-lg font-bold text-on-surface">No products found</h3>
                    <p class="font-body-sm text-xs md:text-sm text-on-surface-variant max-w-sm mt-1 mb-5">
                        Try loosening your filters, adjusting the search term, or clearing your category selections.
                    </p>
                    <button
                        type="button"
                        @click="clearAll()"
                        class="px-6 py-2.5 rounded-full bg-primary text-on-primary font-label-md text-xs font-semibold hover:bg-primary/90 transition-colors">
                        Clear All Filters
                    </button>
                </div>

                {{-- Responsive 2-column mobile, 3-column desktop grid --}}
                <div class="grid grid-cols-2 md:grid-cols-2 lg:grid-cols-3 gap-3 md:gap-5" x-show="filteredIds.length > 0">
                    @foreach($products as $product)
                        <div
                            x-show="filteredIds.includes({{ $product['id'] }})"
                            :style="'order: ' + filteredIds.indexOf({{ $product['id'] }})">
                            <x-product-card :product="$product" />
                        </div>
                    @endforeach
                </div>

            </div>
        </div>

    </div>

    {{-- Mobile Filter Drawer (Slide-Over Panel) --}}
    <div
        x-show="mobileFiltersOpen"
        x-cloak
        class="fixed inset-0 z-50 md:hidden">
        
        <div
            x-show="mobileFiltersOpen"
            x-transition:enter="transition-opacity ease-linear duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity ease-linear duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @click="mobileFiltersOpen = false"
            class="fixed inset-0 bg-inverse-surface/60 backdrop-blur-xs"></div>

        <div
            x-show="mobileFiltersOpen"
            x-transition:enter="transition ease-out duration-200 transform"
            x-transition:enter-start="translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in duration-200 transform"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="translate-x-full"
            class="fixed inset-y-0 right-0 w-full max-w-xs bg-surface-container-lowest shadow-2xl flex flex-col justify-between p-5 overflow-y-auto">
            
            <div class="flex flex-col gap-6">
                <div class="flex items-center justify-between pb-3 border-b border-outline-variant">
                    <h3 class="font-headline-sm text-base font-bold text-on-surface">Filter Catalog</h3>
                    <button type="button" @click="mobileFiltersOpen = false" class="p-1 rounded-full text-on-surface-variant hover:text-on-surface">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                {{-- Search --}}
                <div class="flex flex-col gap-2">
                    <label class="text-xs font-bold text-on-surface uppercase tracking-wider">Search</label>
                    <input
                        type="text"
                        x-model="searchQuery"
                        placeholder="Search products…"
                        class="w-full rounded-xl border border-outline-variant px-3 py-2 text-xs text-on-surface bg-surface-container-low focus:bg-surface-container-lowest focus:outline-none">
                </div>

                {{-- Categories --}}
                <div class="flex flex-col gap-2.5">
                    <span class="text-xs font-bold text-on-surface uppercase tracking-wider">Categories</span>
                    <div class="flex flex-col gap-2 max-h-48 overflow-y-auto">
                        @foreach($categories as $category)
                            <label class="flex items-center gap-2.5 cursor-pointer py-1">
                                <input
                                    type="checkbox"
                                    value="{{ $category['slug'] }}"
                                    x-model="selectedCategories"
                                    class="w-4 h-4 rounded border-outline-variant text-primary focus:ring-primary">
                                <span class="text-xs text-on-surface flex-1 truncate">{{ $category['name'] }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                {{-- Price Range --}}
                <div class="flex flex-col gap-2.5">
                    <span class="text-xs font-bold text-on-surface uppercase tracking-wider">Price Range</span>
                    <div class="grid grid-cols-2 gap-2">
                        <input
                            type="number"
                            x-model.number="minPrice"
                            placeholder="₱ Min"
                            class="rounded-xl border border-outline-variant px-3 py-2 text-xs text-on-surface bg-surface-container-low">
                        <input
                            type="number"
                            x-model.number="maxPrice"
                            placeholder="₱ Max"
                            class="rounded-xl border border-outline-variant px-3 py-2 text-xs text-on-surface bg-surface-container-low">
                    </div>
                </div>
            </div>

            <div class="pt-4 border-t border-outline-variant flex items-center gap-3">
                <button
                    type="button"
                    @click="clearAll()"
                    class="flex-1 py-2.5 rounded-full border border-outline-variant text-xs font-semibold text-on-surface hover:bg-surface-container">
                    Reset All
                </button>
                <button
                    type="button"
                    @click="mobileFiltersOpen = false"
                    class="flex-1 py-2.5 rounded-full bg-primary text-on-primary text-xs font-semibold hover:bg-primary/90">
                    Apply (<span x-text="filteredIds.length"></span>)
                </button>
            </div>

        </div>
    </div>

</div>

@push('scripts')
<script>
    function catalogFilters({ initialQuery, initialCategory, products, categories }) {
        return {
            searchQuery: initialQuery || '',
            selectedCategories: initialCategory ? [initialCategory] : [],
            minPrice: '',
            maxPrice: '',
            sortBy: 'featured',
            mobileFiltersOpen: false,
            products: products,
            categories: categories,

            get activeFilterCount() {
                let count = this.selectedCategories.length;
                if (this.searchQuery.trim() !== '') count++;
                if (this.minPrice !== '' || this.maxPrice !== '') count++;
                return count;
            },

            getCategoryName(slug) {
                const found = this.categories.find((c) => c.slug === slug);
                return found ? found.name : slug;
            },

            toggleCategory(slug) {
                const index = this.selectedCategories.indexOf(slug);
                if (index > -1) {
                    this.selectedCategories.splice(index, 1);
                } else {
                    this.selectedCategories.push(slug);
                }
            },

            clearAll() {
                this.searchQuery = '';
                this.selectedCategories = [];
                this.minPrice = '';
                this.maxPrice = '';
                this.sortBy = 'featured';
            },

            get filteredIds() {
                const q = this.searchQuery.trim().toLowerCase();
                const min = this.minPrice !== '' ? parseFloat(this.minPrice) : null;
                const max = this.maxPrice !== '' ? parseFloat(this.maxPrice) : null;

                let list = this.products.filter((p) => {
                    const matchesCategory = this.selectedCategories.length === 0 || this.selectedCategories.includes(p.category);
                    const matchesQuery = q === '' || p.name.toLowerCase().includes(q);
                    const matchesMin = min === null || p.price >= min;
                    const matchesMax = max === null || p.price <= max;
                    return matchesCategory && matchesQuery && matchesMin && matchesMax;
                });

                if (this.sortBy === 'price_asc') {
                    list.sort((a, b) => a.price - b.price);
                } else if (this.sortBy === 'price_desc') {
                    list.sort((a, b) => b.price - a.price);
                } else if (this.sortBy === 'name_asc') {
                    list.sort((a, b) => a.name.localeCompare(b.name));
                }

                return list.map((p) => p.id);
            },
        };
    }
</script>
@endpush

@endsection
