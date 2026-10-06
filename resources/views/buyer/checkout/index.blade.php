@extends('layouts.app')

@section('title', 'Checkout — Nexora Market')

@section('content')
<div class="w-full bg-surface py-8 min-h-[70vh]">
    <div class="max-w-none px-margin-mobile md:px-margin-desktop">

        <h1 class="font-headline-lg text-headline-lg text-on-surface mb-6">Checkout</h1>

        @if ($errors->any())
            <div class="mb-6 bg-error-container border border-error/30 rounded-2xl p-4">
                <p class="font-label-md text-label-md text-on-error-container">Please fix the following:</p>
                <ul class="list-disc list-inside font-body-sm text-body-sm text-on-error-container">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Nothing selected in cart — nowhere to check out from --}}
        @if($items->isEmpty())
            <div class="flex flex-col items-center justify-center py-20 text-center gap-4">
                <span class="material-symbols-outlined text-[48px] text-outline">remove_shopping_cart</span>
                <p class="font-headline-sm text-headline-sm text-on-surface">No items selected for checkout</p>
                <p class="font-body-sm text-body-sm text-on-surface-variant max-w-sm">Go back to your cart and select at least one item to check out.</p>
                <a href="{{ url('/cart') }}" class="mt-2 inline-flex items-center gap-2 bg-primary text-on-primary font-label-md text-label-md px-6 py-3 rounded-full hover:bg-primary/90 transition-colors">
                    Back to Cart
                </a>
            </div>
        @else
            <form
                x-data="checkoutForm()"
                x-init="loadProvinces()"
                method="POST" action="{{ route('checkout.store') }}"
                class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
                @csrf

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
                                <input type="text" name="name" required value="{{ old('name', auth()->user()->full_name) }}" placeholder="e.g. Jane Doe"
                                    class="rounded-xl border border-outline-variant px-3.5 py-2.5 font-body-md text-body-md text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="font-label-md text-label-md text-on-surface-variant">Phone Number</label>
                                <input type="tel" name="phone" required value="{{ old('phone', auth()->user()->contact_number) }}" placeholder="+63 9XX XXX XXXX"
                                    class="rounded-xl border border-outline-variant px-3.5 py-2.5 font-body-md text-body-md text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="flex flex-col gap-2">
                                <label class="font-label-md text-label-md text-on-surface-variant">Province</label>
                                <select
                                    name="province_code" required
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
                                    name="municipality_code" required
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
                                    name="barangay_code" required
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

                        {{-- Resolved names travel alongside the codes, same pattern as registration --}}
                        <input type="hidden" name="province_name" :value="selectedProvinceName">
                        <input type="hidden" name="municipality_name" :value="selectedMunicipalityName">
                        <input type="hidden" name="barangay_name" :value="selectedBarangayName">

                        <div class="flex flex-col gap-2">
                            <label class="font-label-md text-label-md text-on-surface-variant">Street Address</label>
                            <input type="text" name="street" value="{{ old('street') }}" placeholder="House/Unit No., Street, Subdivision"
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
                                <label
                                    class="flex flex-col items-start gap-2 p-4 rounded-xl border-2 text-left transition-colors cursor-pointer"
                                    :class="selectedPayment === method.id ? 'border-primary bg-primary/5' : 'border-outline-variant hover:border-outline'">
                                    <input type="radio" name="payment_method" :value="method.id" x-model="selectedPayment" class="sr-only" required>
                                    <span class="material-symbols-outlined text-[22px]" :class="selectedPayment === method.id ? 'text-primary' : 'text-on-surface-variant'" x-text="method.icon"></span>
                                    <span class="font-label-md text-label-md text-on-surface" x-text="method.label"></span>
                                    <span class="font-body-sm text-body-sm text-on-surface-variant" x-text="method.desc"></span>
                                </label>
                            </template>
                        </div>
                    </div>

                </div>

                {{-- Right: order summary --}}
                <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-6 flex flex-col gap-4 lg:sticky lg:top-20">
                    <h2 class="font-headline-sm text-headline-sm text-on-surface">Order Summary</h2>

                    <div class="flex flex-col gap-3 max-h-64 overflow-y-auto pr-1">
                        @foreach($items as $item)
                            <div class="flex items-center gap-3">
                                <img src="{{ $item->product->gallery[0] ?? 'https://placehold.co/100x100/e5eeff/0058be?text=' . urlencode($item->product->name) }}" alt="{{ $item->product->name }}" class="w-12 h-12 rounded-lg object-cover bg-surface-container flex-shrink-0">
                                <div class="flex-1 min-w-0">
                                    <p class="font-body-sm text-body-sm text-on-surface truncate">{{ $item->product->name }}</p>
                                    <p class="font-body-sm text-body-sm text-on-surface-variant">Qty {{ $item->quantity }}</p>
                                </div>
                                <span class="font-label-md text-label-md text-on-surface flex-shrink-0">₱{{ number_format($item->product->price * $item->quantity, 2) }}</span>
                            </div>
                        @endforeach
                    </div>

                    <div class="border-t border-outline-variant"></div>

                    @php
                        $subtotal = $items->sum(fn ($i) => $i->product->price * $i->quantity);
                        $shipping = $subtotal >= 2000 ? 0 : 60;
                        $total = $subtotal + $shipping;
                    @endphp

                    <div class="flex flex-col gap-2 font-body-sm text-body-sm">
                        <div class="flex justify-between text-on-surface-variant">
                            <span>Subtotal</span>
                            <span>₱{{ number_format($subtotal, 2) }}</span>
                        </div>
                        <div class="flex justify-between text-on-surface-variant">
                            <span>Shipping</span>
                            <span>{{ $shipping === 0 ? 'Free' : '₱' . number_format($shipping, 2) }}</span>
                        </div>
                    </div>

                    <div class="border-t border-outline-variant"></div>

                    <div class="flex justify-between items-center">
                        <span class="font-headline-sm text-headline-sm text-on-surface">Total</span>
                        <span class="font-headline-lg text-headline-lg text-primary">₱{{ number_format($total, 2) }}</span>
                    </div>

                    <button
                        type="submit"
                        class="w-full flex items-center justify-center gap-2 bg-primary text-on-primary font-label-md text-label-md px-6 py-3 rounded-full shadow-lg shadow-primary/20 hover:bg-primary/90 transition-all">
                        Place Order
                        <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                    </button>
                </div>

            </form>
        @endif
    </div>
</div>

@push('scripts')
<script>
    function checkoutForm() {
        return {
            paymentMethods: [
                { id: 'cod', label: 'Cash on Delivery', icon: 'payments', desc: 'Pay when your order arrives' },
                { id: 'ewallet', label: 'E-Wallet', icon: 'account_balance_wallet', desc: 'GCash, Maya, and more' },
                { id: 'card', label: 'Credit / Debit Card', icon: 'credit_card', desc: 'Visa, Mastercard, and more' },
            ],
            selectedPayment: null,

            provinces: [],
            municipalities: [],
            barangays: [],
            selectedProvince: '',
            selectedMunicipality: '',
            selectedBarangay: '',
            loadingProvinces: false,
            loadingMunicipalities: false,
            loadingBarangays: false,

            get selectedProvinceName() {
                return this.provinces.find((p) => p.code === this.selectedProvince)?.name ?? '';
            },
            get selectedMunicipalityName() {
                return this.municipalities.find((m) => m.code === this.selectedMunicipality)?.name ?? '';
            },
            get selectedBarangayName() {
                return this.barangays.find((b) => b.code === this.selectedBarangay)?.name ?? '';
            },

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
        };
    }
</script>
@endpush

@endsection
