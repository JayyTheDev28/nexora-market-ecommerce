@extends('layouts.app')

@section('title', 'Your Cart — Nexora Market')

@section('content')
<div
    x-data="cartPage({{ $items->map(fn ($item) => [
        'key' => (string) $item->id,
        'id' => $item->product_id,
        'name' => $item->product->name,
        'image' => $item->product->gallery[0] ?? 'https://placehold.co/200x200/e5eeff/0058be?text=' . urlencode($item->product->name),
        'price' => (float) $item->unit_price,
        'regularPrice' => (float) $item->product->price,
        'isFlashSale' => $item->product->isFlashSaleActive(),
        'sellerId' => (int) ($item->product->seller_id ?? 0),
        'seller' => $item->product->seller->sellerProfile->business_name ?? $item->product->seller->full_name ?? 'Merchant',
        'variant' => $item->variant,
        'qty' => $item->quantity,
        'selected' => (bool) $item->selected,
        'stock' => (int) $item->product->stock,
        'weight' => (float) ($item->product->weight ?? 1.0),
    ])->values()->toJson() }})"
    class="w-full bg-surface py-6 md:py-10 min-h-[75vh] pb-24 md:pb-12">
    
    <div class="max-w-none px-4 md:px-8">

        {{-- Header Bar --}}
        <div class="flex items-center justify-between pb-4 border-b border-outline-variant/60 mb-6">
            <div>
                <h1 class="font-headline-lg text-2xl md:text-3xl font-bold text-on-surface">Shopping Cart (B6)</h1>
                <p class="font-body-md text-xs md:text-sm text-on-surface-variant mt-0.5" x-show="items.length > 0">
                    Persistent database cart • Grouped by Merchant Store
                </p>
            </div>
            <a href="{{ route('catalog.index') }}" class="text-primary text-xs md:text-sm font-semibold hover:underline flex items-center gap-1">
                <span>Continue Shopping</span>
                <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
            </a>
        </div>

        {{-- Empty Cart State --}}
        <div x-show="items.length === 0" x-cloak class="flex flex-col items-center justify-center py-20 px-4 text-center bg-surface-container-lowest rounded-3xl border border-outline-variant/60 max-w-lg mx-auto">
            <div class="w-16 h-16 rounded-full bg-surface-container flex items-center justify-center text-outline mb-4">
                <span class="material-symbols-outlined text-[32px]">shopping_cart</span>
            </div>
            <h2 class="font-headline-sm text-lg font-bold text-on-surface">Your cart is empty</h2>
            <p class="font-body-sm text-xs md:text-sm text-on-surface-variant max-w-sm mt-1 mb-6">
                Explore our catalog to discover authentic products and add items to your cart.
            </p>
            <a href="{{ route('catalog.index') }}" class="px-8 py-3 rounded-full bg-primary text-on-primary font-label-md text-xs md:text-sm font-semibold hover:opacity-90 transition-all shadow-md">
                Browse Products
            </a>
        </div>

        {{-- Cart Contents --}}
        <div x-show="items.length > 0" x-cloak class="flex flex-col lg:flex-row gap-6 lg:gap-8 items-start">

            {{-- Left Side: Store-Grouped Items (Rule B6) --}}
            <div class="flex-1 w-full flex flex-col gap-5">

                {{-- Select All Header Bar --}}
                <div class="flex items-center justify-between p-4 bg-surface-container-lowest rounded-2xl border border-outline-variant/60 shadow-xs">
                    <label class="flex items-center gap-3 cursor-pointer select-none">
                        <input
                            type="checkbox"
                            :checked="selectAllChecked"
                            @change="toggleSelectAll($event.target.checked)"
                            class="w-4 h-4 rounded border-outline-variant text-primary focus:ring-primary cursor-pointer">
                        <span class="text-xs font-bold text-on-surface">Select All Items (<span x-text="items.length"></span>)</span>
                    </label>

                    <button
                        type="button"
                        @click="deleteSelected()"
                        :disabled="selectedItems.length === 0"
                        class="text-xs font-semibold text-rose-600 hover:text-rose-800 disabled:opacity-40 disabled:cursor-not-allowed">
                        Delete Selected (<span x-text="selectedItems.length"></span>)
                    </button>
                </div>

                {{-- Stores Groups --}}
                <template x-for="sellerId in uniqueSellers" :key="sellerId">
                    <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant/70 overflow-hidden shadow-xs">
                        
                        {{-- Store Header with Store Select All --}}
                        <div class="flex items-center justify-between px-5 py-3.5 border-b border-outline-variant/60 bg-surface-container-low/60 text-xs font-semibold text-on-surface">
                            <label class="flex items-center gap-2.5 cursor-pointer select-none">
                                <input
                                    type="checkbox"
                                    :checked="isStoreAllSelected(sellerId)"
                                    @change="toggleStoreSelect(sellerId, $event.target.checked)"
                                    class="w-4 h-4 rounded border-outline-variant text-primary focus:ring-primary cursor-pointer">
                                <span class="material-symbols-outlined text-[16px] text-primary">storefront</span>
                                <span class="font-bold text-on-surface" x-text="getStoreName(sellerId)"></span>
                            </label>

                            <a :href="`/catalog?seller_id=${sellerId}`" class="text-[11px] font-semibold text-primary hover:underline">
                                Visit Store →
                            </a>
                        </div>

                        {{-- Line Items for this Store --}}
                        <div class="divide-y divide-outline-variant/50">
                            <template x-for="item in itemsForSeller(sellerId)" :key="item.key">
                                <div class="p-4 md:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4 transition-colors hover:bg-surface-container-low/30">
                                    
                                    {{-- Item Checkbox, Image & Info --}}
                                    <div class="flex items-start gap-3.5 min-w-0 flex-1">
                                        <input
                                            type="checkbox"
                                            :checked="item.selected"
                                            @change="toggleItemSelect(item, $event.target.checked)"
                                            class="w-4 h-4 mt-1 rounded border-outline-variant text-primary focus:ring-primary cursor-pointer flex-shrink-0">

                                        <img
                                            :src="item.image"
                                            :alt="item.name"
                                            class="w-16 h-16 md:w-20 md:h-20 rounded-2xl object-cover border border-outline-variant/60 flex-shrink-0 bg-surface-container">

                                        <div class="min-w-0 flex-1 flex flex-col gap-1">
                                            <a :href="`/products/${item.id}`" class="text-xs font-bold text-on-surface hover:text-primary transition-colors line-clamp-2">
                                                <span x-text="item.name"></span>
                                            </a>

                                            {{-- Variant Badges (Rule B6 Variant Merging) --}}
                                            <template x-if="item.variant">
                                                <div class="flex flex-wrap gap-1 mt-0.5">
                                                    <template x-for="(val, key) in item.variant" :key="key">
                                                        <span class="px-2 py-0.5 rounded-md bg-surface-container text-on-surface-variant text-[10px] font-medium border border-outline-variant/50">
                                                            <span x-text="key"></span>: <strong x-text="val"></strong>
                                                        </span>
                                                    </template>
                                                </div>
                                            </template>

                                            {{-- Dynamic Price Display (Flash Sale or Regular) (Rule B6) --}}
                                            <div class="flex items-baseline gap-2 mt-1">
                                                <span class="text-xs md:text-sm font-extrabold text-primary">
                                                    ₱<span x-text="item.price.toFixed(2)"></span>
                                                </span>
                                                <template x-if="item.isFlashSale && item.regularPrice > item.price">
                                                    <span class="text-[10px] text-outline line-through">
                                                        ₱<span x-text="item.regularPrice.toFixed(2)"></span>
                                                    </span>
                                                </template>
                                                <span class="text-[10px] text-outline font-medium">
                                                    (<span x-text="item.weight"></span> kg/unit)
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Quantity Stepper & Subtotal --}}
                                    <div class="flex items-center justify-between sm:justify-end gap-5 pl-7 sm:pl-0">
                                        <div class="flex items-center border border-outline-variant rounded-full bg-surface-container-lowest p-0.5 shadow-xs">
                                            <button
                                                type="button"
                                                @click="updateQuantity(item, item.qty - 1)"
                                                class="w-7 h-7 rounded-full flex items-center justify-center text-on-surface hover:bg-surface-container transition-colors cursor-pointer">
                                                <span class="material-symbols-outlined text-[15px]">remove</span>
                                            </button>
                                            <span class="w-8 text-center text-xs font-bold text-on-surface" x-text="item.qty"></span>
                                            <button
                                                type="button"
                                                @click="updateQuantity(item, item.qty + 1)"
                                                class="w-7 h-7 rounded-full flex items-center justify-center text-on-surface hover:bg-surface-container transition-colors cursor-pointer">
                                                <span class="material-symbols-outlined text-[15px]">add</span>
                                            </button>
                                        </div>

                                        <span class="text-xs font-bold text-on-surface w-24 text-right">
                                            ₱<span x-text="(item.price * item.qty).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                                        </span>

                                        <button
                                            type="button"
                                            @click="removeItem(item)"
                                            class="text-outline hover:text-rose-600 transition-colors p-1"
                                            title="Remove item">
                                            <span class="material-symbols-outlined text-[18px]">delete</span>
                                        </button>
                                    </div>

                                </div>
                            </template>
                        </div>

                    </div>
                </template>

            </div>

            {{-- Right Side: Order Summary Card --}}
            <div class="w-full lg:w-96 flex flex-col gap-4 sticky top-24">
                <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 shadow-xs flex flex-col gap-4">
                    <h3 class="font-headline-sm text-sm font-bold text-on-surface pb-3 border-b border-outline-variant/60">
                        Order Summary
                    </h3>

                    <div class="flex flex-col gap-2.5 text-xs">
                        <div class="flex items-center justify-between text-on-surface-variant">
                            <span>Selected Items (<span x-text="selectedItemsCount"></span> units)</span>
                            <span class="font-semibold text-on-surface">₱<span x-text="subtotal.toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span></span>
                        </div>
                        <div class="flex items-center justify-between text-on-surface-variant">
                            <span>Participating Stores</span>
                            <span class="font-semibold text-on-surface" x-text="selectedStoresCount"></span>
                        </div>
                        <div class="flex items-center justify-between text-on-surface-variant">
                            <span>Shipping Fees (Calculated at Checkout)</span>
                            <span class="text-[11px] text-outline">Weight-based: ₱50/1st kg + ₱20/kg</span>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-outline-variant/60 flex items-baseline justify-between">
                        <span class="text-xs font-bold text-on-surface">Estimated Subtotal</span>
                        <span class="text-xl font-extrabold text-primary">
                            ₱<span x-text="subtotal.toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                        </span>
                    </div>

                    {{-- Proceed to Checkout Button --}}
                    <a
                        href="{{ route('checkout.index') }}"
                        :class="selectedItems.length === 0 ? 'pointer-events-none opacity-50 bg-outline' : 'bg-primary hover:opacity-90'"
                        class="w-full py-3.5 rounded-full text-on-primary font-label-md text-xs font-semibold flex items-center justify-center gap-2 shadow-md transition-all">
                        <span>Check Out (<span x-text="selectedItems.length"></span>)</span>
                        <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                    </a>

                    <p class="text-[11px] text-center text-on-surface-variant">
                        Multi-seller orders will be automatically split by store at checkout (B8).
                    </p>
                </div>
            </div>

        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
    function cartPage(initialItems) {
        return {
            items: initialItems,

            get uniqueSellers() {
                return [...new Set(this.items.map(i => i.sellerId))];
            },

            get selectedItems() {
                return this.items.filter(i => i.selected);
            },

            get selectedItemsCount() {
                return this.selectedItems.reduce((acc, i) => acc + i.qty, 0);
            },

            get selectedStoresCount() {
                return [...new Set(this.selectedItems.map(i => i.sellerId))].length;
            },

            get selectAllChecked() {
                return this.items.length > 0 && this.items.every(i => i.selected);
            },

            get subtotal() {
                return this.selectedItems.reduce((sum, item) => sum + (item.price * item.qty), 0);
            },

            itemsForSeller(sellerId) {
                return this.items.filter(i => i.sellerId === sellerId);
            },

            getStoreName(sellerId) {
                const item = this.items.find(i => i.sellerId === sellerId);
                return item ? item.seller : 'Store';
            },

            isStoreAllSelected(sellerId) {
                const storeItems = this.itemsForSeller(sellerId);
                return storeItems.length > 0 && storeItems.every(i => i.selected);
            },

            async toggleSelectAll(checked) {
                this.items.forEach(i => i.selected = checked);
                try {
                    await fetch('/cart/select-all', {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ selected: checked }),
                    });
                } catch (e) {
                    console.error('Failed to select all', e);
                }
            },

            async toggleStoreSelect(sellerId, checked) {
                this.itemsForSeller(sellerId).forEach(i => i.selected = checked);
                try {
                    await fetch(`/cart/select-store/${sellerId}`, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ selected: checked }),
                    });
                } catch (e) {
                    console.error('Failed to select store', e);
                }
            },

            async toggleItemSelect(item, checked) {
                item.selected = checked;
                try {
                    await fetch(`/cart/items/${item.key}`, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ selected: checked }),
                    });
                } catch (e) {
                    console.error('Failed to update item select', e);
                }
            },

            async updateQuantity(item, newQty) {
                if (newQty < 1) return;
                const oldQty = item.qty;
                item.qty = newQty;

                try {
                    const res = await fetch(`/cart/items/${item.key}`, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ quantity: newQty }),
                    });
                    const data = await res.json();
                    if (data.error) {
                        alert(data.error);
                        item.qty = oldQty;
                    }
                } catch (e) {
                    console.error('Failed to update quantity', e);
                    item.qty = oldQty;
                }
            },

            async removeItem(item) {
                if (!confirm(`Remove ${item.name} from your cart?`)) return;

                try {
                    const res = await fetch(`/cart/items/${item.key}`, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        },
                    });
                    if (res.ok) {
                        this.items = this.items.filter(i => i.key !== item.key);
                    }
                } catch (e) {
                    console.error('Failed to delete item', e);
                }
            },

            async deleteSelected() {
                if (!confirm('Remove selected items from your cart?')) return;
                const toDelete = [...this.selectedItems];
                for (const item of toDelete) {
                    await this.removeItem(item);
                }
            }
        };
    }
</script>
@endpush
