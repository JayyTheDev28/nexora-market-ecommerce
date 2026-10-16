@extends('layouts.app')

@section('title', 'Checkout — Nexora Market')

@section('content')
<div class="w-full bg-surface py-6 md:py-10 min-h-[75vh]">
    <div class="max-w-none px-4 md:px-8">

        {{-- 3-Step Checkout Progress Header --}}
        <div class="flex items-center justify-center gap-2 md:gap-6 mb-8 text-xs font-semibold">
            <a href="{{ url('/cart') }}" class="flex items-center gap-2 text-primary hover:underline">
                <span class="w-6 h-6 rounded-full bg-primary/10 text-primary flex items-center justify-center text-xs font-bold">1</span>
                <span>Cart</span>
            </a>
            <span class="text-outline-variant font-bold">—</span>
            <div class="flex items-center gap-2 text-primary font-bold">
                <span class="w-6 h-6 rounded-full bg-primary text-on-primary flex items-center justify-center text-xs">2</span>
                <span>Delivery & Payment</span>
            </div>
            <span class="text-outline-variant font-bold">—</span>
            <div class="flex items-center gap-2 text-outline">
                <span class="w-6 h-6 rounded-full bg-surface-container text-on-surface-variant flex items-center justify-center text-xs">3</span>
                <span>Confirmation</span>
            </div>
        </div>

        {{-- Validation Errors Banner --}}
        @if ($errors->any())
            <div class="mb-6 bg-error-container/60 border border-error/40 rounded-2xl p-4 max-w-4xl mx-auto">
                <p class="font-label-md text-xs font-bold text-on-error-container">Please fix the following issues:</p>
                <ul class="list-disc list-inside text-xs text-on-error-container mt-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Empty Cart Check --}}
        @if($items->isEmpty())
            <div class="flex flex-col items-center justify-center py-20 px-4 text-center bg-surface-container-lowest rounded-3xl border border-outline-variant/60 max-w-md mx-auto">
                <div class="w-16 h-16 rounded-full bg-surface-container flex items-center justify-center text-outline mb-4">
                    <span class="material-symbols-outlined text-[32px]">remove_shopping_cart</span>
                </div>
                <h2 class="font-headline-sm text-lg font-bold text-on-surface">No items selected for checkout</h2>
                <p class="font-body-sm text-xs md:text-sm text-on-surface-variant max-w-sm mt-1 mb-6">
                    Return to your shopping cart and select the items you would like to purchase.
                </p>
                <a href="{{ url('/cart') }}" class="px-8 py-3 rounded-full bg-primary text-on-primary font-label-md text-xs md:text-sm font-semibold hover:bg-primary/90 transition-all shadow-md">
                    Return to Cart
                </a>
            </div>
        @else
            <form
                x-data="checkoutForm({{ json_encode([
                    'provinceCode'  => $lastAddress?->province_code ?? '',
                    'muniCode'      => $lastAddress?->municipality_code ?? '',
                    'barangayCode'  => $lastAddress?->barangay_code ?? '',
                ]) }})"
                x-init="init()"
                method="POST" action="{{ route('checkout.store') }}"
                class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8 items-start">
                @csrf

                {{-- Left Side: Address & Payment Method --}}
                <div class="lg:col-span-7 xl:col-span-8 flex flex-col gap-6">

                    {{-- Delivery Address Card --}}
                    <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant/70 p-5 md:p-7 flex flex-col gap-5 shadow-xs">
                        <div class="flex items-center justify-between border-b border-outline-variant/60 pb-3">
                            <h2 class="text-xs font-bold text-on-surface uppercase tracking-wider">
                                Delivery Address
                            </h2>
                            <span class="text-[11px] text-on-surface-variant">Philippine PSGC Standard</span>
                        </div>

                        {{-- Recipient & Contact --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="flex flex-col gap-1.5">
                                <label class="text-xs font-semibold text-on-surface">Recipient Full Name</label>
                                <input
                                    type="text"
                                    name="name"
                                    required
                                    value="{{ old('name', auth()->user()->full_name) }}"
                                    placeholder="e.g. Maria Santos"
                                    class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface-container-low focus:bg-surface-container-lowest focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                            </div>

                            <div class="flex flex-col gap-1.5">
                                <label class="text-xs font-semibold text-on-surface">Mobile Contact Number</label>
                                <input
                                    type="tel"
                                    name="phone"
                                    required
                                    value="{{ old('phone', auth()->user()->contact_number) }}"
                                    placeholder="+63 9XX XXX XXXX"
                                    class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface-container-low focus:bg-surface-container-lowest focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                            </div>
                        </div>

                        {{-- PSGC Location Selectors --}}
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            {{-- Province --}}
                            <div class="flex flex-col gap-1.5">
                                <label class="text-xs font-semibold text-on-surface">Province</label>
                                <select
                                    name="province_code"
                                    required
                                    x-model="selectedProvince"
                                    @change="onProvinceChange()"
                                    class="rounded-xl border border-outline-variant px-3 py-2 text-xs text-on-surface bg-surface-container-low focus:bg-surface-container-lowest focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary disabled:opacity-60">
                                    <option value="" disabled selected x-text="loadingProvinces ? 'Loading…' : 'Select Province'"></option>
                                    <template x-for="p in provinces" :key="p.code">
                                        <option :value="p.code" x-text="p.name"></option>
                                    </template>
                                </select>
                            </div>

                            {{-- City / Municipality --}}
                            <div class="flex flex-col gap-1.5">
                                <label class="text-xs font-semibold text-on-surface">City / Municipality</label>
                                <select
                                    name="municipality_code"
                                    required
                                    x-model="selectedMunicipality"
                                    @change="onMunicipalityChange()"
                                    :disabled="!selectedProvince || loadingMunicipalities"
                                    class="rounded-xl border border-outline-variant px-3 py-2 text-xs text-on-surface bg-surface-container-low focus:bg-surface-container-lowest focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary disabled:opacity-60">
                                    <option value="" disabled selected x-text="loadingMunicipalities ? 'Loading…' : 'Select City'"></option>
                                    <template x-for="m in municipalities" :key="m.code">
                                        <option :value="m.code" x-text="m.name"></option>
                                    </template>
                                </select>
                            </div>

                            {{-- Barangay --}}
                            <div class="flex flex-col gap-1.5">
                                <label class="text-xs font-semibold text-on-surface">Barangay</label>
                                <select
                                    name="barangay_code"
                                    required
                                    x-model="selectedBarangay"
                                    :disabled="!selectedMunicipality || loadingBarangays"
                                    class="rounded-xl border border-outline-variant px-3 py-2 text-xs text-on-surface bg-surface-container-low focus:bg-surface-container-lowest focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary disabled:opacity-60">
                                    <option value="" disabled selected x-text="loadingBarangays ? 'Loading…' : 'Select Barangay'"></option>
                                    <template x-for="b in barangays" :key="b.code">
                                        <option :value="b.code" x-text="b.name"></option>
                                    </template>
                                </select>
                            </div>
                        </div>

                        {{-- Hidden inputs for resolved names (Backend contract preservation) --}}
                        <input type="hidden" name="province_name" :value="selectedProvinceName">
                        <input type="hidden" name="municipality_name" :value="selectedMunicipalityName">
                        <input type="hidden" name="barangay_name" :value="selectedBarangayName">

                        {{-- Street Address --}}
                        <div class="flex flex-col gap-1.5">
                            <label class="text-xs font-semibold text-on-surface">House / Unit No., Building, Street Address</label>
                            <input
                                type="text"
                                name="street"
                                value="{{ old('street') }}"
                                placeholder="e.g. Unit 4B, Emerald Tower, 123 Rizal St."
                                class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface-container-low focus:bg-surface-container-lowest focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary">
                        </div>
                    </div>

                    {{-- Payment Method Selection Card --}}
                    <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant/70 p-5 md:p-7 flex flex-col gap-4 shadow-xs">
                        <div class="flex items-center justify-between border-b border-outline-variant/60 pb-3">
                            <h2 class="text-xs font-bold text-on-surface uppercase tracking-wider">
                                Payment Method
                            </h2>
                            <span class="text-[11px] text-emerald-700 font-semibold">256-Bit SSL Protected</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <template x-for="method in paymentMethods" :key="method.id">
                                <label
                                    class="flex flex-col items-start gap-1.5 p-4 rounded-2xl border-2 transition-all cursor-pointer select-none"
                                    :class="selectedPayment === method.id ? 'border-primary bg-primary/5 shadow-xs ring-2 ring-primary/20' : 'border-outline-variant/70 hover:border-outline bg-surface-container-low/40'">
                                    
                                    <input type="radio" name="payment_method" :value="method.id" x-model="selectedPayment" class="sr-only" required>

                                    <div class="flex items-center justify-between w-full">
                                        <span class="text-xs font-bold text-on-surface" x-text="method.label"></span>
                                        <span
                                            class="w-4 h-4 rounded-full border-2 flex items-center justify-center transition-colors"
                                            :class="selectedPayment === method.id ? 'border-primary bg-primary' : 'border-outline-variant'">
                                            <span class="w-1.5 h-1.5 rounded-full bg-white" x-show="selectedPayment === method.id"></span>
                                        </span>
                                    </div>

                                    <span class="text-[11px] text-on-surface-variant leading-tight" x-text="method.desc"></span>
                                </label>
                            </template>
                        </div>
                    </div>

                </div>

                {{-- Right Side: Sticky Order Summary & Submit --}}
                <div class="lg:col-span-5 xl:col-span-4 bg-surface-container-lowest rounded-3xl border border-outline-variant/70 p-5 md:p-7 flex flex-col gap-5 lg:sticky lg:top-24 shadow-xs">
                    
                    <h2 class="text-xs font-bold text-on-surface uppercase tracking-wider">
                        Order Review (<span x-text="{{ $items->count() }}"></span>)
                    </h2>

                    {{-- Items Thumbnails List --}}
                    <div class="flex flex-col gap-3 max-h-64 overflow-y-auto pr-1 divide-y divide-outline-variant/40">
                        @foreach($items as $item)
                            <div class="flex items-center gap-3 pt-2.5 first:pt-0">
                                <img
                                    src="{{ $item->product->gallery[0] ?? 'https://placehold.co/100x100/e5eeff/0058be?text=' . urlencode($item->product->name) }}"
                                    alt="{{ $item->product->name }}"
                                    class="w-12 h-12 rounded-xl object-cover bg-surface-container border border-outline-variant/40 flex-shrink-0">
                                
                                <div class="flex-1 min-w-0 pr-1">
                                    <h4 class="text-xs font-semibold text-on-surface truncate">{{ $item->product->name }}</h4>
                                    <p class="text-[11px] text-on-surface-variant">Qty {{ $item->quantity }}</p>
                                </div>

                                <span class="text-xs font-bold text-on-surface flex-shrink-0">
                                    ₱{{ number_format($item->product->price * $item->quantity, 2) }}
                                </span>
                            </div>
                        @endforeach
                    </div>

                    <div class="border-t border-outline-variant/60 pt-3">
                        @php
                            $subtotal = $items->sum(fn ($i) => $i->product->price * $i->quantity);
                            $shipping = $subtotal >= 2000 ? 0 : 60;
                            $total = $subtotal + $shipping;
                        @endphp

                        <div class="flex flex-col gap-2 text-xs">
                            <div class="flex justify-between text-on-surface-variant">
                                <span>Subtotal</span>
                                <span class="font-semibold text-on-surface">₱{{ number_format($subtotal, 2) }}</span>
                            </div>

                            <div class="flex justify-between text-on-surface-variant">
                                <span>Estimated Delivery</span>
                                <span class="font-semibold {{ $shipping === 0 ? 'text-emerald-700' : 'text-on-surface' }}">
                                    {{ $shipping === 0 ? 'Free' : '₱' . number_format($shipping, 2) }}
                                </span>
                            </div>
                        </div>

                        <div class="border-t border-outline-variant/60 my-3"></div>

                        <div class="flex justify-between items-baseline mb-4">
                            <span class="text-xs font-bold text-on-surface uppercase tracking-wider">Total Amount</span>
                            <span class="font-headline-lg text-2xl font-extrabold text-primary">
                                ₱{{ number_format($total, 2) }}
                            </span>
                        </div>

                        {{-- Place Order Action --}}
                        <button
                            type="submit"
                            :disabled="!selectedPayment || loadingProvinces || loadingMunicipalities || loadingBarangays"
                            class="w-full flex items-center justify-center gap-2 py-3.5 px-6 rounded-full bg-primary text-on-primary font-label-md text-xs md:text-sm font-semibold hover:bg-primary/90 transition-all shadow-md cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                            <span x-show="loadingProvinces || loadingMunicipalities || loadingBarangays" x-cloak class="w-4 h-4 border-2 border-current border-t-transparent rounded-full animate-spin"></span>
                            <span x-text="loadingProvinces || loadingMunicipalities || loadingBarangays ? 'Loading address data…' : 'Place Order Now'"></span>
                        </button>
                    </div>

                    <div class="p-3 bg-surface-container-low rounded-xl text-[11px] text-on-surface-variant text-center">
                        By placing your order, you agree to Nexora Market's terms and customer return policies.
                    </div>

                </div>

            </form>
        @endif

    </div>
</div>

@push('scripts')
<script>
    function checkoutForm(savedAddress) {
        return {
            paymentMethods: [
                { id: 'cod', label: 'Cash on Delivery', desc: 'Pay with cash upon arrival' },
                { id: 'ewallet', label: 'E-Wallet', desc: 'GCash, Maya & more' },
                { id: 'card', label: 'Credit / Debit Card', desc: 'Visa, Mastercard & JCB' },
            ],
            selectedPayment: null,
            isSubmitting: false,

            provinces: [],
            municipalities: [],
            barangays: [],
            selectedProvince: savedAddress?.provinceCode  ?? '',
            selectedMunicipality: savedAddress?.muniCode  ?? '',
            selectedBarangay: savedAddress?.barangayCode  ?? '',
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

            async init() {
                await this.loadProvinces();
                // Auto-cascade if user has a saved address
                if (this.selectedProvince) {
                    await this.loadMunicipalities(this.selectedProvince);
                }
                if (this.selectedMunicipality) {
                    await this.loadBarangays(this.selectedMunicipality);
                }
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

            async loadMunicipalities(provinceCode) {
                this.loadingMunicipalities = true;
                try {
                    const res = await fetch(`https://psgc.gitlab.io/api/provinces/${provinceCode}/cities-municipalities/`);
                    this.municipalities = (await res.json()).sort((a, b) => a.name.localeCompare(b.name));
                } catch (e) {
                    console.error('Failed to load municipalities', e);
                } finally {
                    this.loadingMunicipalities = false;
                }
            },

            async loadBarangays(muniCode) {
                this.loadingBarangays = true;
                try {
                    const res = await fetch(`https://psgc.gitlab.io/api/cities-municipalities/${muniCode}/barangays/`);
                    this.barangays = (await res.json()).sort((a, b) => a.name.localeCompare(b.name));
                } catch (e) {
                    console.error('Failed to load barangays', e);
                } finally {
                    this.loadingBarangays = false;
                }
            },

            async onProvinceChange() {
                this.municipalities = [];
                this.barangays = [];
                this.selectedMunicipality = '';
                this.selectedBarangay = '';
                if (!this.selectedProvince) return;
                await this.loadMunicipalities(this.selectedProvince);
            },

            async onMunicipalityChange() {
                this.barangays = [];
                this.selectedBarangay = '';
                if (!this.selectedMunicipality) return;
                await this.loadBarangays(this.selectedMunicipality);
            },
        };
    }
</script>
@endpush

@endsection
