@extends('layouts.app')

@section('title', 'Inventory — Seller Dashboard')

@section('content')
<div class="w-full bg-surface py-8 min-h-[70vh]">
    <div class="max-w-none px-margin-mobile md:px-margin-desktop">

        <x-seller-nav active="products" />

        @if (session('status'))
            <div class="mb-6 bg-primary/10 border border-primary/30 rounded-2xl p-4">
                <p class="font-body-sm text-body-sm text-primary">{{ session('status') }}</p>
            </div>
        @endif

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <h1 class="font-headline-lg text-headline-lg text-on-surface">Inventory</h1>
            <a href="{{ route('seller.products.create') }}" class="inline-flex items-center gap-2 bg-primary text-on-primary font-label-md text-label-md px-5 py-2.5 rounded-full hover:bg-primary/90 transition-colors">
                <span class="material-symbols-outlined text-[18px]">add</span>
                Add Product
            </a>
        </div>

        {{-- Status filter chips --}}
        <div class="flex flex-wrap gap-2 mb-5">
            @foreach([
                ['key' => null, 'label' => 'All', 'count' => $counts['all']],
                ['key' => 'active', 'label' => 'Active', 'count' => $counts['active']],
                ['key' => 'draft', 'label' => 'Draft', 'count' => $counts['draft']],
                ['key' => 'archived', 'label' => 'Archived', 'count' => $counts['archived']],
            ] as $chip)
                <a
                    href="{{ route('seller.products.index', $chip['key'] ? ['status' => $chip['key']] : []) }}"
                    class="px-4 py-2 rounded-full font-label-md text-label-md transition-colors {{ request('status') === $chip['key'] || (!$chip['key'] && !request('status')) ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface-variant hover:bg-surface-variant' }}">
                    {{ $chip['label'] }} ({{ $chip['count'] }})
                </a>
            @endforeach
            @if($counts['low_stock'] > 0)
                <span class="px-4 py-2 rounded-full font-label-md text-label-md bg-tertiary-fixed text-on-tertiary-fixed flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[16px]">warning</span>
                    {{ $counts['low_stock'] }} low stock
                </span>
            @endif
        </div>

        {{-- Search --}}
        <form method="GET" class="mb-5">
            @if(request('status'))
                <input type="hidden" name="status" value="{{ request('status') }}">
            @endif
            <div class="relative max-w-sm">
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-[18px]">search</span>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Search your products…"
                    class="w-full rounded-xl border border-outline-variant pl-9 pr-3 py-2 font-body-sm text-body-sm text-on-surface bg-surface-container-lowest focus:outline-none focus:ring-2 focus:ring-primary">
            </div>
        </form>

        {{-- Products table --}}
        @if($products->isEmpty())
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-10 text-center flex flex-col items-center gap-3">
                <span class="material-symbols-outlined text-[36px] text-outline">inventory_2</span>
                <p class="font-headline-sm text-headline-sm text-on-surface">No products yet</p>
                <p class="font-body-sm text-body-sm text-on-surface-variant">Add your first product to start selling on Nexora.</p>
                <a href="{{ route('seller.products.create') }}" class="mt-2 inline-flex items-center gap-2 bg-primary text-on-primary font-label-md text-label-md px-5 py-2.5 rounded-full hover:bg-primary/90 transition-colors">
                    Add Product
                </a>
            </div>
        @else
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-outline-variant bg-surface-container-low">
                                <th class="text-left px-5 py-3 font-label-md text-label-md text-on-surface-variant">Product</th>
                                <th class="text-left px-5 py-3 font-label-md text-label-md text-on-surface-variant">Category</th>
                                <th class="text-left px-5 py-3 font-label-md text-label-md text-on-surface-variant">Price</th>
                                <th class="text-left px-5 py-3 font-label-md text-label-md text-on-surface-variant">Stock</th>
                                <th class="text-left px-5 py-3 font-label-md text-label-md text-on-surface-variant">Status</th>
                                <th class="text-right px-5 py-3 font-label-md text-label-md text-on-surface-variant">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($products as $product)
                                <tr class="border-b border-outline-variant last:border-b-0">
                                    <td class="px-5 py-4">
                                        <div class="flex items-center gap-3">
                                            <img src="{{ $product->gallery[0] ?? 'https://placehold.co/80x80/e5eeff/0058be?text=' . urlencode($product->name) }}" class="w-10 h-10 rounded-lg object-cover bg-surface-container flex-shrink-0">
                                            <span class="font-body-sm text-body-sm text-on-surface">{{ $product->name }}</span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 font-body-sm text-body-sm text-on-surface-variant">{{ $product->category->name }}</td>
                                    <td class="px-5 py-4 font-body-sm text-body-sm text-on-surface">₱{{ number_format($product->price, 2) }}</td>
                                    <td class="px-5 py-4 font-body-sm text-body-sm">
                                        <span class="{{ $product->stock <= 10 ? 'text-tertiary font-semibold' : 'text-on-surface' }}">{{ $product->stock }}</span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="px-2.5 py-1 rounded-full text-[11px] font-bold
                                            {{ $product->status === 'active' ? 'bg-primary/10 text-primary' : ($product->status === 'draft' ? 'bg-surface-container text-on-surface-variant' : 'bg-error-container text-on-error-container') }}">
                                            {{ ucfirst($product->status) }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ route('seller.products.edit', $product) }}" class="text-primary hover:underline font-label-md text-label-md">Edit</a>
                                            @if($product->status === 'archived')
                                                <form method="POST" action="{{ route('seller.products.activate', $product) }}">
                                                    @csrf
                                                    <button type="submit" class="text-primary hover:underline font-label-md text-label-md">Activate</button>
                                                </form>
                                            @else
                                                <form method="POST" action="{{ route('seller.products.archive', $product) }}" onsubmit="return confirm('Archive this product? It will no longer be visible to buyers.');">
                                                    @csrf
                                                    <button type="submit" class="text-on-surface-variant hover:text-error font-label-md text-label-md">Archive</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

    </div>
</div>
@endsection
