@extends('layouts.app')

@section('title', 'Checkout — Nexora Market')

@section('content')
<div x-data="checkoutPage()" x-init="loadProvinces()" class="w-full bg-surface py-8 min-h-[70vh]">
    <div class="max-w-none px-margin-mobile md:px-margin-desktop">

        <h1 class="font-headline-lg text-headline-lg text-on-surface mb-6">Checkout</h1>

        {{-- Nothing selected in cart — nowhere to check out from --}}
        <div x-show="$store.cart.selectedItems.length === 0" x-cloak class="flex flex-col items-center justify-center py-20 text-center gap-4">
            <span class="material-symbols-outlined text-[48px] text-outline">remove_shopping_cart</span>
            <p class="font-headline-sm text-headline-sm text-on-surface">No items selected for checkout</p>
            <p class="font-body-sm text-body-sm text-on-surface-variant max-w-sm">Go back to your cart and select at least one item to check out.</p>
            <a href="{{ url('/cart') }}" class="mt-2 inline-flex items-center gap-2 bg-primary text-on-primary font-label-md text-label-md px-6 py-3 rounded-full hover:bg-primary/90 transition-colors">
                Back to Cart
            </a>
        </div>

        <div x-show="$store.cart.selectedItems.length > 0" x-cloak class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

            {{-- Left: address + payment --}}
            <div class="lg:col-span-2 flex flex-col gap-6">

                {{-- Delivery Address --}}
                <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-6 flex flex-col gap-5">
                    <h2 class="flex items-center gap-2 font-headline-sm text-headline-sm text-on-surface">
                        <span class="material-symbols-outlined text-primary text-[20px]">location_on</span>
                        Delivery Address
                    </h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="flex flex-col gap-2">
                            <label class="font-label-md text-label-md text-on-surface-variant">Recipient Name</label>
                            <input type="text" x-model="address.name" placeholder="e.g. Jane Doe"
                                class="rounded-xl border border-outline-variant px-3.5 py-2.5 font-body-md text-body-md text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="font-label-md text-label-md text-on-surface-variant">Phone Number</label>
                            <input type="tel" x-model="address.phone" placeholder="+63 9XX XXX XXXX"
                                class="rounded-xl border border-outline-variant px-3.5 py-2.5 font-body-md text-body-md text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="flex flex-col gap-2">
                            <label class="font-label-md text-label-md text-on-surface-variant">Province</label>
                            <select
                                x-model="selectedProvince" @change="onProvinceChange()"
                                class="rounded-xl border border-outline-variant px-3.5 py-2.5 font-body-md text-body-md text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary disabled:bg-surface-container">
                                <option value="" disabled selected x-text="loadingProvinces ? 'Loading…' : 'Select Province'"></option>
                                <template x-for="p in provinces" :key="p.code">
                                    <option :value="p.code" x-text="p.name"></option>
                                </template>
                            </select>
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="font-label-md text-label-md text-on-surface-variant">Municipality / City</label>
                            <select
                                x-model="selectedMunicipality" @change="onMunicipalityChange()"
                                :disabled="!selectedProvince || loadingMunicipalities"
                                class="rounded-xl border border-outline-variant px-3.5 py-2.5 font-body-md text-body-md text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary disabled:bg-surface-container">
                                <option value="" disabled selected x-text="loadingMunicipalities ? 'Loading…' : 'Select City'"></option>
                                <template x-for="m in municipalities" :key="m.code">
                                    <option :value="m.code" x-text="m.name"></option>
                                </template>
                            </select>
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="font-label-md text-label-md text-on-surface-variant">Barangay</label>
                            <select
                                x-model="selectedBarangay"
                                :disabled="!selectedMunicipality || loadingBarangays"
                                class="rounded-xl border border-outline-variant px-3.5 py-2.5 font-body-md text-body-md text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary disabled:bg-surface-container">
                                <option value="" disabled selected x-text="loadingBarangays ? 'Loading…' : 'Select Barangay'"></option>
                                <template x-for="b in barangays" :key="b.code">
                                    <option :value="b.code" x-text="b.name"></option>
                                </template>
                            </select>
                        </div>
                    </div>

                    <div class="flex flex-col gap-2">
                        <label class="font-label-md text-label-md text-on-surface-variant">Street Address</label>
                        <input type="text" x-model="address.street" placeholder="House/Unit No., Street, Subdivision"
                            class="rounded-xl border border-outline-variant px-3.5 py-2.5 font-body-md text-body-md text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                    </div>
                </div>

                {{-- Payment Method --}}
                <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-6 flex flex-col gap-4">
                    <h2 class="flex items-center gap-2 font-headline-sm text-headline-sm text-on-surface">
                        <span class="material-symbols-outlined text-primary text-[20px]">payments</span>
                        Payment Method
                    </h2>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <template x-for="method in paymentMethods" :key="method.id">
                            <button
                                type="button"
                                @click="selectedPayment = method.id"
                                class="flex flex-col items-start gap-2 p-4 rounded-xl border-2 text-left transition-colors"
                                :class="selectedPayment === method.id ? 'border-primary bg-primary/5' : 'border-outline-variant hover:border-outline'">
                                <span class="material-symbols-outlined text-[22px]" :class="selectedPayment === method.id ? 'text-primary' : 'text-on-surface-variant'" x-text="method.icon"></span>
                                <span class="font-label-md text-label-md text-on-surface" x-text="method.label"></span>
                                <span class="font-body-sm text-body-sm text-on-surface-variant" x-text="method.desc"></span>
                            </button>
                        </template>
                    </div>
                </div>

            </div>

            {{-- Right: order summary --}}
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-6 flex flex-col gap-4 lg:sticky lg:top-20">
                <h2 class="font-headline-sm text-headline-sm text-on-surface">Order Summary</h2>

                <div class="flex flex-col gap-3 max-h-64 overflow-y-auto pr-1">
                    <template x-for="item in $store.cart.selectedItems" :key="item.key">
                        <div class="flex items-center gap-3">
                            <img :src="item.image" :alt="item.name" class="w-12 h-12 rounded-lg object-cover bg-surface-container flex-shrink-0">
                            <div class="flex-1 min-w-0">
                                <p class="font-body-sm text-body-sm text-on-surface truncate" x-text="item.name"></p>
                                <p class="font-body-sm text-body-sm text-on-surface-variant">Qty <span x-text="item.qty"></span></p>
                            </div>
                            <span class="font-label-md text-label-md text-on-surface flex-shrink-0">₱<span x-text="(item.price * item.qty).toLocaleString('en-PH', {minimumFractionDigits: 2})"></span></span>
                        </div>
                    </template>
                </div>

                <div class="border-t border-outline-variant"></div>

                <div class="flex flex-col gap-2 font-body-sm text-body-sm">
                    <div class="flex justify-between text-on-surface-variant">
                        <span>Subtotal</span>
                        <span>₱<span x-text="$store.cart.subtotal.toLocaleString('en-PH', {minimumFractionDigits: 2})"></span></span>
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

                <button
                    type="button"
                    :disabled="!canPlaceOrder"
                    @click="placeOrder()"
                    class="w-full flex items-center justify-center gap-2 font-label-md text-label-md px-6 py-3 rounded-full transition-all"
                    :class="canPlaceOrder ? 'bg-primary text-on-primary hover:bg-primary/90 shadow-lg shadow-primary/20' : 'bg-surface-container text-outline cursor-not-allowed'">
                    Place Order
                    <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                </button>

                <p x-show="!canPlaceOrder" x-cloak class="font-body-sm text-body-sm text-on-surface-variant text-center">
                    Complete the delivery address and select a payment method to continue.
                </p>
            </div>

        </div>
    </div>

    {{-- Order placed modal — no backend yet; this is the frontend confirmation state --}}
    <div
        x-show="orderPlaced"
        x-cloak
        x-transition.opacity
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-inverse-surface/60 backdrop-blur-sm">
        <div
            x-show="orderPlaced"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            class="relative max-w-md w-full bg-surface-container-lowest rounded-3xl shadow-2xl p-8 text-center flex flex-col items-center gap-4">

            <div class="w-16 h-16 rounded-full bg-primary/10 flex items-center justify-center">
                <span class="material-symbols-outlined text-[30px] text-primary" style="font-variation-settings: 'FILL' 1">check_circle</span>
            </div>

            <h2 class="font-headline-lg text-headline-lg text-on-surface">Order Placed!</h2>
            <p class="font-body-md text-body-md text-on-surface-variant">
                Your order <span class="font-semibold text-on-surface" x-text="placedOrderId"></span> has been placed successfully.
            </p>
            <p class="font-body-sm text-body-sm text-on-surface-variant">
                You can track its progress anytime from My Orders.
            </p>

            <div class="w-full flex flex-col gap-2 mt-2">
                <a href="{{ url('/buyer/orders') }}" class="w-full inline-flex items-center justify-center gap-2 bg-primary text-on-primary font-label-md text-label-md px-6 py-3 rounded-full hover:bg-primary/90 transition-all">
                    Track My Order
                </a>
                <a href="{{ url('/catalog') }}" class="w-full inline-flex items-center justify-center gap-2 text-on-surface-variant font-label-md text-label-md px-6 py-3 rounded-full hover:bg-surface-container transition-all">
                    Continue Shopping
                </a>
            </div>
        </div>
    </div>

</div>

@push('scripts')
<script>
    function checkoutPage() {
        return {
            address: { name: '', phone: '', street: '' },

            paymentMethods: [
                { id: 'cod', label: 'Cash on Delivery', icon: 'payments', desc: 'Pay when your order arrives' },
                { id: 'ewallet', label: 'E-Wallet', icon: 'account_balance_wallet', desc: 'GCash, Maya, and more' },
                { id: 'card', label: 'Credit / Debit Card', icon: 'credit_card', desc: 'Visa, Mastercard, and more' },
            ],
            selectedPayment: null,

            // Address — PSGC public API (province → municipality/city → barangay), same as /register
            provinces: [],
            municipalities: [],
            barangays: [],
            selectedProvince: '',
            selectedMunicipality: '',
            selectedBarangay: '',
            loadingProvinces: false,
            loadingMunicipalities: false,
            loadingBarangays: false,

            async loadProvinces() {
                this.loadingProvinces = true;
                try {
                    const res = await fetch('https://psgc.gitlab.io/api/provinces/');
                    this.provinces = (await res.json()).sort((a, b) => a.name.localeCompare(b.name));
                } catch (e) {
                    console.error('Failed to load provinces', e);
                } finally {
                    this.loadingProvinces = false;
                }
            },

            async onProvinceChange() {
                this.municipalities = [];
                this.barangays = [];
                this.selectedMunicipality = '';
                this.selectedBarangay = '';
                if (!this.selectedProvince) return;
                this.loadingMunicipalities = true;
                try {
                    const res = await fetch(`https://psgc.gitlab.io/api/provinces/${this.selectedProvince}/cities-municipalities/`);
                    this.municipalities = (await res.json()).sort((a, b) => a.name.localeCompare(b.name));
                } catch (e) {
                    console.error('Failed to load municipalities', e);
                } finally {
                    this.loadingMunicipalities = false;
                }
            },

            async onMunicipalityChange() {
                this.barangays = [];
                this.selectedBarangay = '';
                if (!this.selectedMunicipality) return;
                this.loadingBarangays = true;
                try {
                    const res = await fetch(`https://psgc.gitlab.io/api/cities-municipalities/${this.selectedMunicipality}/barangays/`);
                    this.barangays = (await res.json()).sort((a, b) => a.name.localeCompare(b.name));
                } catch (e) {
                    console.error('Failed to load barangays', e);
                } finally {
                    this.loadingBarangays = false;
                }
            },

            get shipping() {
                const subtotal = this.$store.cart.subtotal;
                if (subtotal === 0) return 0;
                return subtotal >= 2000 ? 0 : 60;
            },

            get total() {
                return this.$store.cart.subtotal + this.shipping;
            },

            get canPlaceOrder() {
                return this.address.name.trim() !== ''
                    && this.address.phone.trim() !== ''
                    && this.address.street.trim() !== ''
                    && !!this.selectedProvince
                    && !!this.selectedMunicipality
                    && !!this.selectedBarangay
                    && !!this.selectedPayment
                    && this.$store.cart.selectedItems.length > 0;
            },

            orderPlaced: false,
            placedOrderId: '',

            placeOrder() {
                if (!this.canPlaceOrder) return;

                const lines = this.$store.cart.selectedItems.map((i) => ({
                    name: i.name,
                    image: i.image,
                    qty: i.qty,
                    price: i.price,
                }));

                const paymentLabel = this.paymentMethods.find((m) => m.id === this.selectedPayment)?.label ?? this.selectedPayment;

                const order = this.$store.orders.place({
                    lines,
                    total: this.total,
                    address: { ...this.address },
                    paymentMethod: paymentLabel,
                });

                this.$store.cart.clearSelected();
                this.placedOrderId = order.id;
                this.orderPlaced = true;
            },
        };
    }
</script>
@endpush

@endsection
