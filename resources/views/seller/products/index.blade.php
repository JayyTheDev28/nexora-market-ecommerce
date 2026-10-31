@extends('layouts.app')

@section('title', 'Product Catalog — Seller Dashboard')

@section('content')
<div class="w-full bg-surface py-8 min-h-[70vh]">
    <div class="max-w-none px-margin-mobile md:px-margin-desktop">

        <x-seller-nav active="products" />

        @if (session('status'))
            <div class="mb-6 bg-emerald-500/10 border border-emerald-500/30 rounded-2xl p-4 flex items-center gap-2 text-emerald-700 dark:text-emerald-300 font-label-md">
                <span class="material-symbols-outlined text-[20px]">check_circle</span>
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 bg-error/10 border border-error/30 rounded-2xl p-4 text-error font-body-sm">
                <ul class="list-disc pl-5 space-y-1">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="font-headline-lg text-headline-lg text-on-surface">Product Catalog</h1>
                <p class="font-body-md text-on-surface-variant">Create and maintain your product listings, variants, pricing, and marketplace visibility.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('seller.inventory.index') }}" class="inline-flex items-center gap-1.5 border border-outline-variant text-on-surface font-label-md text-sm px-4 py-2.5 rounded-full hover:bg-surface-container transition-colors">
                    <span class="material-symbols-outlined text-[18px]">warehouse</span>
                    Stock Overview
                </a>
                <a href="{{ route('seller.products.create') }}" class="inline-flex items-center gap-1.5 bg-primary text-on-primary font-label-md text-sm px-5 py-2.5 rounded-full hover:bg-primary/90 transition-colors shadow-sm">
                    <span class="material-symbols-outlined text-[18px]">add</span>
                    Add Product
                </a>
            </div>
        </div>

        {{-- Status Filter Chips --}}
        <div class="flex flex-wrap items-center gap-2 mb-5">
            @foreach([
                ['key' => null, 'label' => 'All', 'count' => $counts['all']],
                ['key' => 'active', 'label' => 'Active', 'count' => $counts['active']],
                ['key' => 'draft', 'label' => 'Drafts', 'count' => $counts['draft']],
                ['key' => 'archived', 'label' => 'Archived', 'count' => $counts['archived']],
                ['key' => 'low_stock', 'label' => 'Low Stock', 'count' => $counts['low_stock']],
                ['key' => 'out_of_stock', 'label' => 'Out of Stock', 'count' => $counts['out_of_stock']],
            ] as $chip)
                <a
                    href="{{ route('seller.products.index', array_filter(array_merge(request()->query(), ['status' => $chip['key']]))) }}"
                    class="px-3.5 py-1.5 rounded-full text-xs font-bold transition-colors {{ (request('status') === $chip['key'] || (!$chip['key'] && !request('status'))) ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface-variant hover:bg-surface-container-high' }}">
                    {{ $chip['label'] }} ({{ $chip['count'] }})
                </a>
            @endforeach
        </div>

        {{-- Search & Store Category Filters --}}
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-5">
            <form method="GET" class="flex flex-wrap items-center gap-3">
                @if(request('status'))
                    <input type="hidden" name="status" value="{{ request('status') }}">
                @endif
                <div class="relative w-64">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-[18px]">search</span>
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="Search name, SKU, bio…"
                        class="w-full rounded-full border border-outline-variant pl-9 pr-3 py-1.5 font-body-sm text-xs text-on-surface bg-surface-container-lowest focus:outline-none focus:ring-1 focus:ring-primary">
                </div>

                @if($storeCategories->isNotEmpty())
                    <select name="store_category" onchange="this.form.submit()" class="rounded-full border border-outline-variant px-3 py-1.5 text-xs text-on-surface bg-surface-container-lowest focus:outline-none focus:ring-1 focus:ring-primary">
                        <option value="">All Store Categories</option>
                        @foreach($storeCategories as $sc)
                            <option value="{{ $sc->id }}" {{ request('store_category') == $sc->id ? 'selected' : '' }}>{{ $sc->name }}</option>
                        @endforeach
                    </select>
                @endif

                <button type="submit" class="px-4 py-1.5 rounded-full bg-surface-container border border-outline-variant text-xs font-semibold hover:bg-surface-container-high">
                    Filter
                </button>
            </form>

            {{-- Bulk Actions Bar --}}
            <form id="bulkForm" method="POST" action="{{ route('seller.products.bulk') }}" class="flex items-center gap-2">
                @csrf
                <select name="action" required class="rounded-full border border-outline-variant px-3 py-1.5 text-xs text-on-surface bg-surface-container-lowest focus:outline-none">
                    <option value="">Bulk Actions</option>
                    <option value="activate">Set Active</option>
                    <option value="archive">Archive Selected</option>
                    <option value="delete">Delete Safe</option>
                </select>
                <button type="submit" onclick="return confirm('Apply bulk action to selected products?');" class="px-4 py-1.5 rounded-full bg-surface-container border border-outline-variant text-xs font-bold hover:bg-surface-container-high">
                    Apply
                </button>
            </form>
        </div>

        {{-- Products table --}}
        @if($products->isEmpty())
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-12 text-center flex flex-col items-center gap-3">
                <span class="material-symbols-outlined text-[48px] text-outline-variant">inventory_2</span>
                <p class="font-headline-sm text-headline-sm text-on-surface font-bold">No products match your criteria</p>
                <p class="font-body-sm text-xs text-on-surface-variant max-w-sm">Try adjusting your status filter or search keywords, or publish a new product.</p>
                <a href="{{ route('seller.products.create') }}" class="mt-2 inline-flex items-center gap-1.5 bg-primary text-on-primary font-label-md text-xs px-5 py-2.5 rounded-full hover:bg-primary/90 transition-colors">
                    Add Product
                </a>
            </div>
        @else
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="border-b border-outline-variant bg-surface-container-low text-xs font-bold text-on-surface-variant uppercase tracking-wider">
                                <th class="w-10 px-4 py-3">
                                    <input type="checkbox" id="selectAll" class="rounded text-primary focus:ring-primary w-4 h-4 cursor-pointer">
                                </th>
                                <th class="px-4 py-3">Product</th>
                                <th class="px-4 py-3">SKU</th>
                                <th class="px-4 py-3">Categories</th>
                                <th class="px-4 py-3">Price</th>
                                <th class="px-4 py-3">Stock Level</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="text-right px-4 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant text-xs">
                            @foreach($products as $product)
                                <tr class="hover:bg-surface-container-low/40 transition-colors">
                                    <td class="px-4 py-3.5">
                                        <input type="checkbox" name="product_ids[]" value="{{ $product->id }}" form="bulkForm" class="product-checkbox rounded text-primary focus:ring-primary w-4 h-4 cursor-pointer">
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <div class="flex items-center gap-3">
                                            <div class="w-11 h-11 rounded-lg overflow-hidden border border-outline-variant bg-surface-container shrink-0">
                                                <img src="{{ $product->gallery[0] ?? 'https://placehold.co/80x80/e5eeff/0058be?text=' . urlencode($product->name) }}" class="w-full h-full object-cover">
                                            </div>
                                            <div>
                                                <a href="{{ route('seller.products.edit', $product) }}" class="font-bold text-on-surface hover:text-primary transition-colors line-clamp-1">
                                                    {{ $product->name }}
                                                </a>
                                                <div class="flex items-center gap-1.5 text-[11px] text-on-surface-variant mt-0.5">
                                                    <span>₱{{ number_format($product->price, 2) }}</span>
                                                    @if(!empty($product->variants_data))
                                                        <span>•</span>
                                                        <span class="text-primary font-semibold">{{ count($product->variants_data) }} variants</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3.5 font-mono text-[11px] text-on-surface-variant">
                                        {{ $product->sku ?: '—' }}
                                    </td>
                                    <td class="px-4 py-3.5 text-on-surface-variant">
                                        <p class="font-medium text-on-surface">{{ $product->category->name }}</p>
                                        @if($product->storeCategory)
                                            <p class="text-[10px] text-primary">{{ $product->storeCategory->name }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <span class="font-bold text-on-surface">₱{{ number_format($product->effective_price, 2) }}</span>
                                        @if($product->compare_price && $product->compare_price > $product->price)
                                            <span class="text-[10px] text-on-surface-variant line-through block">₱{{ number_format($product->compare_price, 2) }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3.5">
                                        @if($product->stock <= 0)
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-error/10 text-error">Out of Stock</span>
                                        @elseif($product->isLowStock())
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-600 flex items-center gap-1 w-fit">
                                                <span class="material-symbols-outlined text-[12px]">warning</span> {{ $product->stock }} left
                                            </span>
                                        @else
                                            <span class="font-semibold text-emerald-600">{{ $product->stock }} units</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase
                                            {{ $product->status === 'active' ? 'bg-emerald-500/10 text-emerald-600' : ($product->status === 'draft' ? 'bg-surface-container text-on-surface-variant' : 'bg-outline-variant/30 text-on-surface-variant') }}">
                                            {{ $product->status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3.5 text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <a href="{{ route('seller.products.edit', $product) }}" class="p-1 rounded-md text-on-surface-variant hover:text-primary hover:bg-surface-container" title="Edit Product">
                                                <span class="material-symbols-outlined text-[18px]">edit</span>
                                            </a>
                                            <form method="POST" action="{{ route('seller.products.duplicate', $product) }}">
                                                @csrf
                                                <button type="submit" class="p-1 rounded-md text-on-surface-variant hover:text-on-surface hover:bg-surface-container" title="Duplicate as Draft">
                                                    <span class="material-symbols-outlined text-[18px]">content_copy</span>
                                                </button>
                                            </form>
                                            @if($product->status === 'archived')
                                                <form method="POST" action="{{ route('seller.products.activate', $product) }}">
                                                    @csrf
                                                    <button type="submit" class="p-1 rounded-md text-emerald-600 hover:bg-emerald-500/10" title="Reactivate Product">
                                                        <span class="material-symbols-outlined text-[18px]">visibility</span>
                                                    </button>
                                                </form>
                                            @else
                                                <form method="POST" action="{{ route('seller.products.archive', $product) }}" onsubmit="return confirm('Archive \'{{ $product->name }}\'? It will be hidden from buyers.');">
                                                    @csrf
                                                    <button type="submit" class="p-1 rounded-md text-on-surface-variant hover:text-amber-600 hover:bg-amber-500/10" title="Archive Product">
                                                        <span class="material-symbols-outlined text-[18px]">archive</span>
                                                    </button>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('seller.products.destroy', $product) }}" onsubmit="return confirm('Permanently remove \'{{ $product->name }}\'? (Only possible if no active orders depend on it).');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="p-1 rounded-md text-error hover:bg-error/10" title="Delete Product">
                                                    <span class="material-symbols-outlined text-[18px]">delete</span>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="px-5 py-4 border-t border-outline-variant bg-surface-container-low">
                    {{ $products->links() }}
                </div>
            </div>
        @endif

    </div>
</div>

<script>
document.getElementById('selectAll')?.addEventListener('change', function() {
    document.querySelectorAll('.product-checkbox').forEach(cb => cb.checked = this.checked);
});
</script>
@endsection
