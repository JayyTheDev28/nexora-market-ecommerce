@extends('layouts.app')

@section('title', 'Inventory & Stock Management — Seller Hub')

@section('content')
<div class="w-full min-h-screen bg-surface">
    <div class="flex flex-col lg:flex-row w-full">

            <x-seller-nav active="inventory" />

            <div class="flex-1 min-w-0 p-4 sm:p-6 lg:p-8 bg-surface">
            <div class="w-full max-w-7xl mx-auto space-y-6">

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
                <h1 class="font-headline-lg text-headline-lg text-on-surface">Inventory Management</h1>
                <p class="font-body-md text-on-surface-variant">Real-time stock monitoring, replenishment adjustments, and low-inventory alerts.</p>
            </div>
            <a href="{{ route('seller.products.create') }}" class="inline-flex items-center gap-1.5 bg-primary text-on-primary font-label-md text-sm px-5 py-2.5 rounded-full hover:bg-primary/90 transition-colors shadow-sm w-fit">
                <span class="material-symbols-outlined text-[18px]">add</span>
                Add Product
            </a>
        </div>

        {{-- Metrics Cards --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-5">
                <p class="text-xs font-semibold text-on-surface-variant">Catalog Products</p>
                <p class="font-headline-lg text-2xl font-bold text-on-surface mt-1">{{ $metrics['total_items'] }}</p>
            </div>
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-5">
                <p class="text-xs font-semibold text-on-surface-variant">Total Units Available</p>
                <p class="font-headline-lg text-2xl font-bold text-primary mt-1">{{ number_format($metrics['total_stock_units']) }}</p>
            </div>
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-5">
                <p class="text-xs font-semibold text-on-surface-variant">Low Stock Warnings</p>
                <p class="font-headline-lg text-2xl font-bold mt-1 {{ $metrics['low_stock_count'] > 0 ? 'text-amber-500' : 'text-on-surface' }}">
                    {{ $metrics['low_stock_count'] }}
                </p>
            </div>
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-5">
                <p class="text-xs font-semibold text-on-surface-variant">Out of Stock</p>
                <p class="font-headline-lg text-2xl font-bold mt-1 {{ $metrics['out_of_stock_count'] > 0 ? 'text-error' : 'text-on-surface' }}">
                    {{ $metrics['out_of_stock_count'] }}
                </p>
            </div>
        </div>

        {{-- Filters & Search --}}
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div class="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-none">
                <a href="{{ route('seller.inventory.index') }}" class="px-4 py-1.5 rounded-full text-xs font-bold transition-colors {{ !request('filter') ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface-variant hover:bg-surface-container-high' }}">
                    All Stock
                </a>
                <a href="{{ route('seller.inventory.index', ['filter' => 'low_stock']) }}" class="px-4 py-1.5 rounded-full text-xs font-bold transition-colors {{ request('filter') === 'low_stock' ? 'bg-amber-500 text-white' : 'bg-surface-container text-on-surface-variant hover:bg-surface-container-high' }}">
                    Low Stock ({{ $metrics['low_stock_count'] }})
                </a>
                <a href="{{ route('seller.inventory.index', ['filter' => 'out_of_stock']) }}" class="px-4 py-1.5 rounded-full text-xs font-bold transition-colors {{ request('filter') === 'out_of_stock' ? 'bg-error text-white' : 'bg-surface-container text-on-surface-variant hover:bg-surface-container-high' }}">
                    Out of Stock ({{ $metrics['out_of_stock_count'] }})
                </a>
                <a href="{{ route('seller.inventory.index', ['filter' => 'in_stock']) }}" class="px-4 py-1.5 rounded-full text-xs font-bold transition-colors {{ request('filter') === 'in_stock' ? 'bg-emerald-600 text-white' : 'bg-surface-container text-on-surface-variant hover:bg-surface-container-high' }}">
                    Healthy Stock
                </a>
            </div>

            <form method="GET" class="relative w-64">
                @if(request('filter'))
                    <input type="hidden" name="filter" value="{{ request('filter') }}">
                @endif
                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-[18px]">search</span>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Search by name or SKU…"
                    class="w-full rounded-full border border-outline-variant pl-9 pr-3 py-1.5 font-body-sm text-xs text-on-surface bg-surface-container-lowest focus:outline-none focus:ring-1 focus:ring-primary">
            </form>
        </div>

        {{-- Inventory Table --}}
        @if($products->isEmpty())
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-12 text-center">
                <span class="material-symbols-outlined text-[48px] text-outline-variant mb-2">warehouse</span>
                <h3 class="font-headline-sm text-headline-sm text-on-surface font-bold">No Products Found</h3>
                <p class="font-body-sm text-xs text-on-surface-variant mt-1">Try another search term or stock filter.</p>
            </div>
        @else
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="border-b border-outline-variant bg-surface-container-low text-xs font-bold text-on-surface-variant uppercase tracking-wider">
                                <th class="px-4 py-3">Product</th>
                                <th class="px-4 py-3">SKU</th>
                                <th class="px-4 py-3">Current Stock</th>
                                <th class="px-4 py-3">Alert Threshold</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="text-right px-4 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant text-xs">
                            @foreach($products as $product)
                                <tr class="hover:bg-surface-container-low/40 transition-colors">
                                    <td class="px-4 py-3.5">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-lg overflow-hidden border border-outline-variant bg-surface-container shrink-0">
                                                <img src="{{ $product->gallery[0] ?? 'https://placehold.co/80x80/e5eeff/0058be?text=' . urlencode($product->name) }}" class="w-full h-full object-cover">
                                            </div>
                                            <div>
                                                <a href="{{ route('seller.products.edit', $product) }}" class="font-bold text-on-surface hover:text-primary">
                                                    {{ $product->name }}
                                                </a>
                                                <div class="text-[11px] text-on-surface-variant mt-0.5">
                                                    {{ $product->category->name }}
                                                    @if($product->storeCategory)
                                                        · <span class="text-primary">{{ $product->storeCategory->name }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3.5 font-mono text-[11px] text-on-surface-variant">
                                        {{ $product->sku ?: '—' }}
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <span class="font-bold text-sm {{ $product->stock <= 0 ? 'text-error' : ($product->isLowStock() ? 'text-amber-500' : 'text-on-surface') }}">
                                            {{ $product->stock }}
                                        </span>
                                        @if(!empty($product->variants_data))
                                            <span class="block text-[10px] text-on-surface-variant mt-0.5">
                                                across {{ count($product->variants_data) }} variants
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3.5 text-on-surface-variant">
                                        {{ $product->low_stock_threshold ?? 10 }} units
                                    </td>
                                    <td class="px-4 py-3.5">
                                        @if($product->stock <= 0)
                                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-error/10 text-error">
                                                Out of Stock
                                            </span>
                                        @elseif($product->isLowStock())
                                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-600 flex items-center gap-1 w-fit">
                                                <span class="material-symbols-outlined text-[12px]">warning</span> Low Stock
                                            </span>
                                        @else
                                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-600">
                                                Available
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3.5 text-right">
                                        <button
                                            type="button"
                                            onclick="openStockModal({{ json_encode($product) }})"
                                            class="px-3.5 py-1.5 rounded-full bg-primary/10 text-primary hover:bg-primary hover:text-on-primary text-xs font-bold transition-colors">
                                            Adjust Stock
                                        </button>
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
    </div>
</div>


{{-- Stock Adjustment Modal --}}
<div id="stockModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 max-w-md w-full shadow-xl">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-headline-sm text-headline-sm text-on-surface font-bold">Adjust Inventory Stock</h3>
            <button type="button" onclick="document.getElementById('stockModal').classList.add('hidden')" class="p-1 rounded-full text-on-surface-variant hover:bg-surface-container">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
        </div>

        <form id="stockForm" method="POST" action="" class="space-y-4">
            @csrf

            <div class="p-3 rounded-xl bg-surface-container-low border border-outline-variant">
                <p id="modalProductName" class="font-bold text-xs text-on-surface line-clamp-1"></p>
                <p id="modalCurrentStock" class="text-xs text-on-surface-variant mt-0.5"></p>
            </div>

            <div>
                <label class="block font-label-md text-xs font-semibold text-on-surface mb-1">Adjustment Action</label>
                <div class="grid grid-cols-3 gap-2">
                    <label class="flex items-center justify-center p-2 rounded-xl border border-outline-variant text-xs font-bold cursor-pointer text-center bg-surface hover:bg-surface-container">
                        <input type="radio" name="adjustment_type" value="set" checked class="hidden peer">
                        <span class="peer-checked:text-primary">Set Exact</span>
                    </label>
                    <label class="flex items-center justify-center p-2 rounded-xl border border-outline-variant text-xs font-bold cursor-pointer text-center bg-surface hover:bg-surface-container">
                        <input type="radio" name="adjustment_type" value="add" class="hidden peer">
                        <span class="peer-checked:text-primary">+ Add Units</span>
                    </label>
                    <label class="flex items-center justify-center p-2 rounded-xl border border-outline-variant text-xs font-bold cursor-pointer text-center bg-surface hover:bg-surface-container">
                        <input type="radio" name="adjustment_type" value="subtract" class="hidden peer">
                        <span class="peer-checked:text-primary">- Reduce Units</span>
                    </label>
                </div>
            </div>

            <div>
                <label for="modalQty" class="block font-label-md text-xs font-semibold text-on-surface mb-1">Quantity</label>
                <input type="number" id="modalQty" name="quantity" min="0" required class="w-full rounded-xl border border-outline-variant bg-surface px-3 py-2 text-sm text-on-surface focus:border-primary focus:outline-none">
            </div>

            <div>
                <label for="modalThreshold" class="block font-label-md text-xs font-semibold text-on-surface mb-1">Low Stock Alert Threshold</label>
                <input type="number" id="modalThreshold" name="threshold" min="0" class="w-full rounded-xl border border-outline-variant bg-surface px-3 py-2 text-sm text-on-surface focus:border-primary focus:outline-none">
            </div>

            <div>
                <label for="modalReason" class="block font-label-md text-xs font-semibold text-on-surface mb-1">Adjustment Reason / Audit Note</label>
                <input type="text" id="modalReason" name="reason" placeholder="e.g. Restock shipment received, damaged item, inventory audit" class="w-full rounded-xl border border-outline-variant bg-surface px-3 py-2 text-xs text-on-surface focus:border-primary focus:outline-none">
            </div>

            <div class="pt-3 border-t border-outline-variant flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('stockModal').classList.add('hidden')" class="px-4 py-2 rounded-full border border-outline-variant text-xs font-semibold text-on-surface hover:bg-surface-container">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2 rounded-full bg-primary text-on-primary text-xs font-bold hover:bg-primary/90">
                    Apply Adjustment
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openStockModal(p) {
    document.getElementById('stockForm').action = `/seller/inventory/${p.id}/adjust`;
    document.getElementById('modalProductName').innerText = p.name;
    document.getElementById('modalCurrentStock').innerText = `Current available inventory: ${p.stock} units`;
    document.getElementById('modalQty').value = p.stock;
    document.getElementById('modalThreshold').value = p.low_stock_threshold || 10;
    document.getElementById('modalReason').value = '';
    document.getElementById('stockModal').classList.remove('hidden');
}
</script>
@endsection
