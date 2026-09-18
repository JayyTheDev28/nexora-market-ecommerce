@extends('layouts.app')

@section('title', 'Your Cart — Nexora Market')

@section('content')
<div x-data="cartPage()" class="w-full bg-surface py-8 min-h-[70vh]">
    <div class="max-w-none px-margin-mobile md:px-margin-desktop">

        <h1 class="font-headline-lg text-headline-lg text-on-surface mb-6">Shopping Cart</h1>

        {{-- Empty state --}}
        <div x-show="$store.cart.items.length === 0" x-cloak class="flex flex-col items-center justify-center py-20 text-center gap-4">
            <span class="material-symbols-outlined text-[48px] text-outline">shopping_cart</span>
            <p class="font-headline-sm text-headline-sm text-on-surface">Your cart is empty</p>
            <p class="font-body-sm text-body-sm text-on-surface-variant max-w-sm">Looks like you haven't added anything yet. Explore the catalog to find something you'll love.</p>
            <a href="{{ url('/catalog') }}" class="mt-2 inline-flex items-center gap-2 bg-primary text-on-primary font-label-md text-label-md px-6 py-3 rounded-full hover:bg-primary/90 transition-colors">
                Browse Catalog
            </a>
        </div>

        {{-- Cart contents --}}
        <div x-show="$store.cart.items.length > 0" x-cloak class="flex flex-col lg:flex-row gap-6 items-start">

            {{-- Item list --}}
            <div class="flex-1 w-full bg-surface-container-lowest rounded-2xl border border-outline-variant overflow-hidden">

                <div class="flex items-center gap-3 px-5 py-3 border-b border-outline-variant bg-surface-container-low">
                    <input
                        type="checkbox"
                        :checked="selectAllChecked"
                        @change="$store.cart.selectAll($event.target.checked)"
                        class="w-4 h-4 rounded border-outline-variant text-primary focus:ring-primary">
                    <span class="font-label-md text-label-md text-on-surface-variant">Select All</span>
                    <span class="ml-auto font-body-sm text-body-sm text-on-surface-variant">
                        <span x-text="$store.cart.selectedItems.length"></span> of <span x-text="$store.cart.items.length"></span> selected
                    </span>
                </div>

                <template x-for="item in $store.cart.items" :key="item.key">
                    <div class="flex items-center gap-4 px-5 py-4 border-b border-outline-variant last:border-b-0">
                        <input
                            type="checkbox"
                            :checked="item.selected"
                            @change="$store.cart.toggle(item.key)"
                            class="w-4 h-4 rounded border-outline-variant text-primary focus:ring-primary flex-shrink-0">

                        <img :src="item.image" :alt="item.name" class="w-16 h-16 rounded-xl object-cover flex-shrink-0 bg-surface-container">

                        <div class="flex-1 min-w-0">
                            <p class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider" x-text="item.seller"></p>
                            <p class="font-headline-sm text-headline-sm text-on-surface truncate" x-text="item.name"></p>
                            <p x-show="item.variant" class="font-body-sm text-body-sm text-on-surface-variant">
                                <template x-for="(val, key) in item.variant" :key="key">
                                    <span class="mr-2" x-text="key + ': ' + val"></span>
                                </template>
                            </p>
                            <p class="font-headline-md text-headline-md text-primary mt-1">₱<span x-text="item.price.toLocaleString('en-PH', {minimumFractionDigits: 2})"></span></p>
                        </div>

                        <div class="flex items-center border border-outline-variant rounded-full flex-shrink-0">
                            <button type="button" @click="$store.cart.setQty(item.key, item.qty - 1)" class="w-8 h-8 flex items-center justify-center text-on-surface hover:text-primary" aria-label="Decrease quantity">
                                <span class="material-symbols-outlined text-[16px]">remove</span>
                            </button>
                            <span class="w-8 text-center font-label-md text-label-md" x-text="item.qty"></span>
                            <button type="button" @click="$store.cart.setQty(item.key, item.qty + 1)" class="w-8 h-8 flex items-center justify-center text-on-surface hover:text-primary" aria-label="Increase quantity">
                                <span class="material-symbols-outlined text-[16px]">add</span>
                            </button>
                        </div>

                        <button type="button" @click="$store.cart.remove(item.key)" class="text-on-surface-variant hover:text-error flex-shrink-0" aria-label="Remove item">
                            <span class="material-symbols-outlined text-[20px]">delete</span>
                        </button>
                    </div>
                </template>
            </div>

            {{-- Order summary --}}
            <div class="w-full lg:w-80 flex-shrink-0 bg-surface-container-lowest rounded-2xl border border-outline-variant p-5 flex flex-col gap-4 lg:sticky lg:top-20">
                <h2 class="font-headline-sm text-headline-sm text-on-surface">Order Summary</h2>

                {{-- Voucher --}}
                <div class="flex flex-col gap-2">
                    <label class="font-label-md text-label-md text-on-surface-variant">Discount Voucher</label>
                    <div class="flex gap-2">
                        <input
                            type="text"
                            x-model="voucherInput"
                            placeholder="Enter code"
                            class="flex-1 min-w-0 rounded-xl border border-outline-variant px-3 py-2 font-body-sm text-body-sm text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                        <button type="button" @click="applyVoucher()" class="px-4 py-2 rounded-xl bg-surface-container text-on-surface font-label-md text-label-md hover:bg-surface-variant transition-colors flex-shrink-0">
                            Apply
                        </button>
                    </div>
                    <p x-show="voucherError" x-cloak class="font-body-sm text-body-sm text-error" x-text="voucherError"></p>
                    <p x-show="appliedVoucher" x-cloak class="font-body-sm text-body-sm text-primary flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px]">check_circle</span>
                        <span x-text="appliedVoucher?.code"></span> applied
                        <button type="button" class="ml-auto text-on-surface-variant hover:text-error" @click="removeVoucher()">
                            <span class="material-symbols-outlined text-[14px]">close</span>
                        </button>
                    </p>
                </div>

                <div class="border-t border-outline-variant"></div>

                <div class="flex flex-col gap-2 font-body-sm text-body-sm">
                    <div class="flex justify-between text-on-surface-variant">
                        <span>Subtotal</span>
                        <span>₱<span x-text="$store.cart.subtotal.toLocaleString('en-PH', {minimumFractionDigits: 2})"></span></span>
                    </div>
                    <div class="flex justify-between text-on-surface-variant" x-show="discount > 0">
                        <span>Discount</span>
                        <span class="text-error">-₱<span x-text="discount.toLocaleString('en-PH', {minimumFractionDigits: 2})"></span></span>
                    </div>
                    <div class="flex justify-between text-on-surface-variant">
                        <span>Shipping</span>
                        <span x-text="shipping === 0 ? 'Free' : '₱' + shipping.toLocaleString('en-PH', {minimumFractionDigits: 2})"></span>
                    </div>
                </div>

                <div class="border-t border-outline-variant"></div>

                <div class="flex justify-between items-center">
                    <span class="font-headline-sm text-headline-sm text-on-surface">Total</span>
                    <span class="font-headline-lg text-headline-lg text-primary">₱<span x-text="total.toLocaleString('en-PH', {minimumFractionDigits: 2})"></span></span>
                </div>

                <a
                    href="{{ url('/checkout') }}"
                    @click="if ($store.cart.selectedItems.length === 0) $event.preventDefault()"
                    class="w-full flex items-center justify-center gap-2 font-label-md text-label-md px-6 py-3 rounded-full transition-all"
                    :class="$store.cart.selectedItems.length > 0 ? 'bg-primary text-on-primary hover:bg-primary/90 shadow-lg shadow-primary/20' : 'bg-surface-container text-outline cursor-not-allowed'">
                    Proceed to Checkout
                    <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                </a>
            </div>

        </div>
    </div>
</div>

@push('scripts')
<script>
    function cartPage() {
        return {
            voucherInput: '',
            appliedVoucher: null,
            voucherError: '',

            // Demo voucher codes — no backend, just illustrating the UI.
            vouchers: {
                'NEXORA10': { code: 'NEXORA10', type: 'percent', value: 0.10 },
                'WELCOME50': { code: 'WELCOME50', type: 'flat', value: 50 },
            },

            get selectAllChecked() {
                return this.$store.cart.items.length > 0 && this.$store.cart.items.every((i) => i.selected);
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

            removeVoucher() {
                this.appliedVoucher = null;
            },

            get discount() {
                if (!this.appliedVoucher) return 0;
                const subtotal = this.$store.cart.subtotal;
                if (this.appliedVoucher.type === 'percent') {
                    return Math.round(subtotal * this.appliedVoucher.value * 100) / 100;
                }
                return Math.min(this.appliedVoucher.value, subtotal);
            },

            get shipping() {
                const subtotal = this.$store.cart.subtotal;
                if (subtotal === 0) return 0;
                return subtotal >= 2000 ? 0 : 60;
            },

            get total() {
                return Math.max(0, this.$store.cart.subtotal - this.discount + this.shipping);
            },
        };
    }
</script>
@endpush

@endsection
