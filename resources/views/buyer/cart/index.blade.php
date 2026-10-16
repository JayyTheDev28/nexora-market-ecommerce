@extends('layouts.app')

@section('title', 'Your Cart — Nexora Market')

@section('content')
<div
    x-data="cartPage({{ $items->map(fn ($item) => [
        'key' => (string) $item->id,
        'id' => $item->product_id,
        'name' => $item->product->name,
        'image' => $item->product->gallery[0] ?? 'https://placehold.co/200x200/e5eeff/0058be?text=' . urlencode($item->product->name),
        'price' => (float) $item->product->price,
        'seller' => $item->product->seller->sellerProfile->business_name ?? '',
        'variant' => $item->variant,
        'qty' => $item->quantity,
        'selected' => (bool) $item->selected,
    ])->values()->toJson() }})"
    class="w-full bg-surface py-6 md:py-10 min-h-[75vh] pb-24 md:pb-12">
    
    <div class="max-w-none px-4 md:px-8">

        {{-- Header Bar --}}
        <div class="flex items-center justify-between pb-4 border-b border-outline-variant/60 mb-6">
            <div>
                <h1 class="font-headline-lg text-2xl md:text-3xl font-bold text-on-surface">Shopping Cart</h1>
                <p class="font-body-md text-xs md:text-sm text-on-surface-variant mt-0.5" x-show="items.length > 0">
                    <span class="font-bold text-on-surface" x-text="items.length"></span> item<span x-show="items.length !== 1">s</span> in your cart
                </p>
            </div>
            <a href="{{ url('/catalog') }}" class="text-primary text-xs md:text-sm font-semibold hover:underline">
                Continue Shopping
            </a>
        </div>

        {{-- Empty Cart State --}}
        <div x-show="items.length === 0" x-cloak class="flex flex-col items-center justify-center py-20 px-4 text-center bg-surface-container-lowest rounded-3xl border border-outline-variant/60 max-w-lg mx-auto">
            <div class="w-16 h-16 rounded-full bg-surface-container flex items-center justify-center text-outline mb-4">
                <span class="material-symbols-outlined text-[32px]">shopping_cart</span>
            </div>
            <h2 class="font-headline-sm text-lg font-bold text-on-surface">Your cart is empty</h2>
            <p class="font-body-sm text-xs md:text-sm text-on-surface-variant max-w-sm mt-1 mb-6">
                Explore our catalog to discover verified products, deals, and daily discounts.
            </p>
            <a href="{{ url('/catalog') }}" class="px-8 py-3 rounded-full bg-primary text-on-primary font-label-md text-xs md:text-sm font-semibold hover:bg-primary/90 transition-all shadow-md">
                Browse Products
            </a>
        </div>

        {{-- Cart Contents --}}
        <div x-show="items.length > 0" x-cloak class="flex flex-col lg:flex-row gap-6 lg:gap-8 items-start">

            {{-- Left Side: Items & Free Shipping Tracker --}}
            <div class="flex-1 w-full flex flex-col gap-5">

                {{-- Free Shipping Progress Bar (₱2,000 threshold) --}}
                <div class="p-4 bg-surface-container-lowest rounded-2xl border border-outline-variant/60 flex flex-col gap-2.5 shadow-xs">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-semibold text-on-surface" x-show="subtotal < 2000">
                            Add <strong class="text-primary">₱<span x-text="(2000 - subtotal).toLocaleString('en-PH', {minimumFractionDigits: 2})"></span></strong> more for Free Delivery!
                        </span>
                        <span class="font-bold text-emerald-700" x-show="subtotal >= 2000">
                            You've qualified for Free Nationwide Delivery!
                        </span>
                        <span class="text-outline text-[11px] font-medium">Goal: ₱2,000.00</span>
                    </div>

                    {{-- Progress meter --}}
                    <div class="w-full h-2 rounded-full bg-surface-container overflow-hidden">
                        <div
                            class="h-full bg-primary transition-all duration-300 rounded-full"
                            :style="`width: ${Math.min(100, (subtotal / 2000) * 100)}%`">
                        </div>
                    </div>
                </div>

                {{-- Items Card --}}
                <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant/70 overflow-hidden shadow-xs">
                    
                    {{-- Select All Header Row --}}
                    <div class="flex items-center justify-between px-4 md:px-6 py-3.5 border-b border-outline-variant/60 bg-surface-container-low/60 text-xs font-semibold text-on-surface">
                        <label class="flex items-center gap-3 cursor-pointer select-none">
                            <input
                                type="checkbox"
                                :checked="selectAllChecked"
                                @change="toggleSelectAll($event.target.checked)"
                                class="w-4 h-4 rounded border-outline-variant text-primary focus:ring-primary cursor-pointer">
                            <span>Select All (<span x-text="items.length"></span>)</span>
                        </label>

                        <span class="text-on-surface-variant font-medium">
                            <span x-text="selectedItems.length"></span> selected
                        </span>
                    </div>

                    {{-- Items List --}}
                    <div class="divide-y divide-outline-variant/50">
                        <template x-for="item in items" :key="item.key">
                            <div class="p-4 md:p-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4 transition-colors hover:bg-surface-container-low/30">
                                
                                {{-- Item Details --}}
                                <div class="flex items-start gap-3.5 min-w-0 flex-1">
                                    <input
                                        type="checkbox"
                                        :checked="item.selected"
                                        @change="toggleItem(item)"
                                        class="w-4 h-4 rounded border-outline-variant text-primary focus:ring-primary mt-1.5 cursor-pointer flex-shrink-0">

                                    <img
                                        :src="item.image"
                                        :alt="item.name"
                                        class="w-18 h-18 md:w-20 md:h-20 rounded-2xl object-cover bg-surface-container border border-outline-variant/40 flex-shrink-0">

                                    <div class="flex flex-col min-w-0 flex-1 pr-2">
                                        <span class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wider truncate" x-text="item.seller"></span>
                                        <h3 class="font-headline-sm text-xs md:text-sm font-semibold text-on-surface line-clamp-2 mt-0.5" x-text="item.name"></h3>

                                        {{-- Variant Chips --}}
                                        <div x-show="item.variant" class="flex flex-wrap gap-1 mt-1">
                                            <template x-for="(val, key) in item.variant" :key="key">
                                                <span class="px-2 py-0.5 rounded-md bg-surface-container text-[11px] text-on-surface-variant font-medium">
                                                    <span class="capitalize" x-text="key"></span>: <strong class="text-on-surface" x-text="val"></strong>
                                                </span>
                                            </template>
                                        </div>

                                        <p class="font-headline-md text-sm md:text-base font-extrabold text-primary mt-2">
                                            ₱<span x-text="item.price.toLocaleString('en-PH', {minimumFractionDigits: 2})"></span>
                                        </p>
                                    </div>
                                </div>

                                {{-- Quantity Controls & Delete --}}
                                <div class="flex items-center justify-between sm:justify-end gap-4 pl-8 sm:pl-0 pt-2 sm:pt-0 border-t sm:border-t-0 border-outline-variant/40">
                                    {{-- Stepper --}}
                                    <div class="flex items-center border border-outline-variant rounded-full bg-surface-container-low p-0.5 shadow-xs">
                                        <button
                                            type="button"
                                            @click="setQty(item, item.qty - 1)"
                                            class="w-8 h-8 rounded-full flex items-center justify-center text-on-surface hover:bg-surface-container-lowest transition-colors cursor-pointer"
                                            aria-label="Decrease quantity">
                                            <span class="material-symbols-outlined text-[16px]">remove</span>
                                        </button>
                                        <span class="w-8 text-center text-xs font-bold text-on-surface" x-text="item.qty"></span>
                                        <button
                                            type="button"
                                            @click="setQty(item, item.qty + 1)"
                                            class="w-8 h-8 rounded-full flex items-center justify-center text-on-surface hover:bg-surface-container-lowest transition-colors cursor-pointer"
                                            aria-label="Increase quantity">
                                            <span class="material-symbols-outlined text-[16px]">add</span>
                                        </button>
                                    </div>

                                    {{-- Delete Button --}}
                                    <button
                                        type="button"
                                        @click="removeItem(item)"
                                        class="p-2 text-on-surface-variant hover:text-error hover:bg-error/10 rounded-full transition-colors cursor-pointer"
                                        aria-label="Remove item">
                                        <span class="material-symbols-outlined text-[20px]">delete</span>
                                    </button>
                                </div>

                            </div>
                        </template>
                    </div>

                </div>
            </div>

            {{-- Right Side: Order Summary & Voucher --}}
            <div class="w-full lg:w-88 flex-shrink-0 flex flex-col gap-5 lg:sticky lg:top-24">
                
                {{-- Voucher Card --}}
                <div class="p-5 bg-surface-container-lowest rounded-3xl border border-outline-variant/70 shadow-xs flex flex-col gap-3">
                    <span class="text-xs font-bold text-on-surface uppercase tracking-wider">Vouchers & Coupons</span>
                    
                    <div class="flex gap-2">
                        <input
                            type="text"
                            x-model="voucherInput"
                            placeholder="Enter voucher code"
                            class="flex-1 min-w-0 rounded-xl border border-outline-variant px-3 py-2 text-xs text-on-surface bg-surface-container-low focus:bg-surface-container-lowest focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary uppercase">
                        <button
                            type="button"
                            @click="applyVoucher()"
                            class="px-4 py-2 rounded-xl bg-primary text-on-primary font-label-md text-xs font-semibold hover:bg-primary/90 transition-colors flex-shrink-0 cursor-pointer">
                            Apply
                        </button>
                    </div>

                    {{-- Clickable Available Voucher Chips --}}
                    <div class="flex flex-wrap gap-1.5 pt-1">
                        <button
                            type="button"
                            @click="voucherInput = 'NEXORA10'; applyVoucher()"
                            class="px-2.5 py-1 rounded-lg border border-primary/30 bg-primary/5 text-primary text-[11px] font-semibold hover:bg-primary/10 transition-colors cursor-pointer">
                            NEXORA10 (10% Off)
                        </button>
                        <button
                            type="button"
                            @click="voucherInput = 'WELCOME50'; applyVoucher()"
                            class="px-2.5 py-1 rounded-lg border border-primary/30 bg-primary/5 text-primary text-[11px] font-semibold hover:bg-primary/10 transition-colors cursor-pointer">
                            WELCOME50 (₱50 Off)
                        </button>
                    </div>

                    <p x-show="voucherError" x-cloak class="text-xs text-error font-medium" x-text="voucherError"></p>

                    <div x-show="appliedVoucher" x-cloak class="p-2.5 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-800 text-xs flex items-center justify-between">
                        <span>Code <strong x-text="appliedVoucher?.code"></strong> applied!</span>
                        <button type="button" @click="appliedVoucher = null" class="text-on-surface-variant hover:text-error text-xs font-bold">
                            Remove
                        </button>
                    </div>
                </div>

                {{-- Summary Card --}}
                <div class="p-6 bg-surface-container-lowest rounded-3xl border border-outline-variant/70 shadow-xs flex flex-col gap-4">
                    <h2 class="font-headline-sm text-base font-bold text-on-surface">Order Summary</h2>

                    <div class="flex flex-col gap-2.5 text-xs">
                        <div class="flex justify-between text-on-surface-variant">
                            <span>Selected Items Subtotal</span>
                            <span class="font-semibold text-on-surface">₱<span x-text="subtotal.toLocaleString('en-PH', {minimumFractionDigits: 2})"></span></span>
                        </div>

                        <div class="flex justify-between text-error" x-show="discount > 0">
                            <span>Voucher Discount</span>
                            <span class="font-semibold">-₱<span x-text="discount.toLocaleString('en-PH', {minimumFractionDigits: 2})"></span></span>
                        </div>

                        <div class="flex justify-between text-on-surface-variant">
                            <span>Estimated Delivery Fee</span>
                            <span class="font-semibold" x-text="shipping === 0 ? 'Free' : '₱' + shipping.toLocaleString('en-PH', {minimumFractionDigits: 2})"></span>
                        </div>
                    </div>

                    <div class="border-t border-outline-variant/60 pt-3">
                        <div class="flex justify-between items-baseline mb-1">
                            <span class="text-xs font-bold text-on-surface uppercase tracking-wider">Total Amount</span>
                            <span class="font-headline-lg text-xl font-extrabold text-primary">
                                ₱<span x-text="total.toLocaleString('en-PH', {minimumFractionDigits: 2})"></span>
                            </span>
                        </div>
                        <p class="text-[11px] text-on-surface-variant">VAT and taxes included where applicable.</p>
                    </div>

                    {{-- Proceed Button --}}
                    <a
                        href="{{ url('/checkout') }}"
                        @click="if (selectedItems.length === 0) $event.preventDefault()"
                        class="w-full flex items-center justify-center gap-2 py-3.5 px-6 rounded-full font-label-md text-xs font-semibold transition-all shadow-md text-center"
                        :class="selectedItems.length > 0 ? 'bg-primary text-on-primary hover:bg-primary/90 hover:scale-[1.01]' : 'bg-surface-container text-outline cursor-not-allowed'">
                        <span>Proceed to Checkout</span>
                        <span x-show="selectedItems.length > 0" class="text-xs font-normal" x-text="`(${selectedItems.length})`"></span>
                    </a>
                </div>

            </div>

        </div>

    </div>

    {{-- Sticky Mobile Checkout Bar (Visible on mobile when items are in cart) --}}
    <div
        x-show="items.length > 0"
        x-cloak
        class="lg:hidden fixed bottom-0 left-0 right-0 z-40 bg-surface-container-lowest/95 backdrop-blur-md border-t border-outline-variant px-4 py-3 flex items-center justify-between shadow-xl">
        
        <label class="flex items-center gap-2 cursor-pointer select-none">
            <input
                type="checkbox"
                :checked="selectAllChecked"
                @change="toggleSelectAll($event.target.checked)"
                class="w-4 h-4 rounded border-outline-variant text-primary focus:ring-primary">
            <span class="text-xs font-semibold text-on-surface">All</span>
        </label>

        <div class="flex items-center gap-3">
            <div class="flex flex-col text-right">
                <span class="text-[10px] text-on-surface-variant font-medium">Total:</span>
                <span class="font-headline-md text-sm font-extrabold text-primary">
                    ₱<span x-text="total.toLocaleString('en-PH', {minimumFractionDigits: 2})"></span>
                </span>
            </div>

            <a
                href="{{ url('/checkout') }}"
                @click="if (selectedItems.length === 0) $event.preventDefault()"
                class="px-5 py-2.5 rounded-full font-label-md text-xs font-semibold shadow-xs"
                :class="selectedItems.length > 0 ? 'bg-primary text-on-primary' : 'bg-surface-container text-outline cursor-not-allowed'">
                Checkout (<span x-text="selectedItems.length"></span>)
            </a>
        </div>
    </div>

</div>

@push('scripts')
<script>
    function cartPage(initialItems) {
        return {
            items: initialItems,
            voucherInput: '',
            appliedVoucher: null,
            voucherError: '',

            vouchers: {
                'NEXORA10': { code: 'NEXORA10', type: 'percent', value: 0.10 },
                'WELCOME50': { code: 'WELCOME50', type: 'flat', value: 50 },
            },

            get selectedItems() {
                return this.items.filter((i) => i.selected);
            },

            get selectAllChecked() {
                return this.items.length > 0 && this.items.every((i) => i.selected);
            },

            toggleItem(item) {
                item.selected = !item.selected;
                api(`/cart/items/${item.key}`, { method: 'PATCH', body: { selected: item.selected } })
                    .catch((e) => { alert(e.message); item.selected = !item.selected; });
            },

            toggleSelectAll(state) {
                this.items.forEach((i) => (i.selected = state));
                api('/cart/select-all', { method: 'PATCH', body: { selected: state } })
                    .catch((e) => alert(e.message));
            },

            setQty(item, qty) {
                qty = Math.max(1, qty);
                const previous = item.qty;
                item.qty = qty;
                api(`/cart/items/${item.key}`, { method: 'PATCH', body: { quantity: qty } })
                    .catch((e) => { alert(e.message); item.qty = previous; });
            },

            removeItem(item) {
                this.items = this.items.filter((i) => i.key !== item.key);
                api(`/cart/items/${item.key}`, { method: 'DELETE' })
                    .then((data) => {
                        if (data) window.dispatchEvent(new CustomEvent('cart-updated', { detail: { count: data.cartCount } }));
                    })
                    .catch((e) => alert(e.message));
            },

            applyVoucher() {
                const code = this.voucherInput.trim().toUpperCase();
                if (!code) return;
                const voucher = this.vouchers[code];
                if (!voucher) {
                    this.voucherError = 'Invalid or expired voucher code.';
                    this.appliedVoucher = null;
                    return;
                }
                this.voucherError = '';
                this.appliedVoucher = voucher;
                this.voucherInput = '';
            },

            get subtotal() {
                return this.selectedItems.reduce((sum, i) => sum + i.price * i.qty, 0);
            },

            get discount() {
                if (!this.appliedVoucher) return 0;
                if (this.appliedVoucher.type === 'percent') {
                    return Math.round(this.subtotal * this.appliedVoucher.value * 100) / 100;
                }
                return Math.min(this.appliedVoucher.value, this.subtotal);
            },

            get shipping() {
                if (this.subtotal === 0) return 0;
                return this.subtotal >= 2000 ? 0 : 60;
            },

            get total() {
                return Math.max(0, this.subtotal - this.discount + this.shipping);
            },
        };
    }
</script>
@endpush

@endsection
