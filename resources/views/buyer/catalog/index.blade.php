@extends('layouts.app')

@section('title', 'Shop Catalog — Nexora Market')

@section('content')
<div
    x-data="catalogFilters({
        initialQuery: @js($initialQuery),
        initialCategory: @js($initialCategory),
        products: {{ collect($products)->map(fn($p) => ['id' => $p['id'], 'name' => $p['name'], 'category' => $p['category']])->values()->toJson() }}
    })"
    class="w-full bg-surface py-8">

    <div class="max-w-none px-margin-mobile md:px-margin-desktop">

        <div class="mb-6">
            <h1 class="font-headline-lg text-headline-lg text-on-surface">Shop Catalog</h1>
            <p class="font-body-md text-body-md text-on-surface-variant mt-1">
                <span x-text="filteredIds.length"></span> product<span x-show="filteredIds.length !== 1">s</span> found
            </p>
        </div>

        <div class="flex flex-col md:flex-row gap-6">

            {{-- Sidebar --}}
            <aside class="md:w-64 flex-shrink-0">
                <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-5 sticky top-20 flex flex-col gap-5">

                    {{-- Search --}}
                    <div class="flex flex-col gap-2">
                        <label class="font-label-md text-label-md text-on-surface-variant">Search</label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-[18px]">search</span>
                            <input
                                type="text"
                                x-model="searchQuery"
                                placeholder="Search products…"
                                class="w-full rounded-xl border border-outline-variant pl-9 pr-3 py-2 font-body-sm text-body-sm text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                    </div>

                    <div class="border-t border-outline-variant"></div>

                    {{-- Category filter --}}
                    <div class="flex flex-col gap-3">
                        <div class="flex items-center justify-between">
                            <span class="font-label-md text-label-md text-on-surface-variant">Categories</span>
                            <button
                                type="button"
                                x-show="selectedCategories.length > 0"
                                @click="selectedCategories = []"
                                class="font-label-sm text-label-sm text-primary hover:underline">
                                Clear
                            </button>
                        </div>
                        <div class="flex flex-col gap-2.5">
                            @foreach($categories as $category)
                                <label class="flex items-center gap-2.5 cursor-pointer group">
                                    <input
                                        type="checkbox"
                                        value="{{ $category['slug'] }}"
                                        x-model="selectedCategories"
                                        class="w-4 h-4 rounded border-outline-variant text-primary focus:ring-primary">
                                    <span class="material-symbols-outlined text-[16px] text-on-surface-variant">{{ $category['icon'] }}</span>
                                    <span class="font-body-sm text-body-sm text-on-surface group-hover:text-primary transition-colors">{{ $category['name'] }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                </div>
            </aside>

            {{-- Results --}}
            <div class="flex-1">

                <div x-show="filteredIds.length === 0" x-cloak class="flex flex-col items-center justify-center py-20 text-center gap-3">
                    <span class="material-symbols-outlined text-[40px] text-outline">search_off</span>
                    <p class="font-headline-sm text-headline-sm text-on-surface">No products found</p>
                    <p class="font-body-sm text-body-sm text-on-surface-variant">Try a different search term or clear your category filters.</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-gutter">
                    @foreach($products as $product)
                        <div x-show="filteredIds.includes({{ $product['id'] }})">
                            <x-product-card :product="$product" />
                        </div>
                    @endforeach
                </div>

            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function catalogFilters({ initialQuery, initialCategory, products }) {
        return {
            searchQuery: initialQuery || '',
            selectedCategories: initialCategory ? [initialCategory] : [],
            products: products,

            get filteredIds() {
                const q = this.searchQuery.trim().toLowerCase();
                return this.products
                    .filter((p) => {
                        const matchesCategory = this.selectedCategories.length === 0 || this.selectedCategories.includes(p.category);
                        const matchesQuery = q === '' || p.name.toLowerCase().includes(q);
                        return matchesCategory && matchesQuery;
                    })
                    .map((p) => p.id);
            },
        };
    }
</script>
@endpush

@endsection
