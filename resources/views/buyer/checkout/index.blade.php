@extends('layouts.app')

@section('title', 'Checkout — Nexora Market')

@section('content')
<div class="w-full bg-surface py-6 md:py-10 min-h-[75vh]">
    <div class="max-w-none px-4 md:px-8">

        {{-- Progress Header --}}
        <div class="flex items-center justify-center gap-2 md:gap-6 mb-8 text-xs font-semibold">
            <a href="{{ route('cart.index') }}" class="flex items-center gap-2 text-primary hover:underline">
                <span class="w-6 h-6 rounded-full bg-primary/10 text-primary flex items-center justify-center text-xs font-bold">1</span>
                <span>Cart</span>
            </a>
            <span class="text-outline-variant font-bold">—</span>
            <div class="flex items-center gap-2 text-primary font-bold">
                <span class="w-6 h-6 rounded-full bg-primary text-on-primary flex items-center justify-center text-xs">2</span>
                <span>Delivery & Split Orders</span>
            </div>
            <span class="text-outline-variant font-bold">—</span>
            <div class="flex items-center gap-2 text-outline">
                <span class="w-6 h-6 rounded-full bg-surface-container text-on-surface-variant flex items-center justify-center text-xs">3</span>
                <span>Confirmation</span>
            </div>
        </div>

        {{-- Validation Errors Banner --}}
        @if ($errors->any())
            <div class="mb-6 bg-rose-500/10 border border-rose-500/20 rounded-2xl p-4 max-w-5xl mx-auto">
                <p class="font-label-md text-xs font-bold text-rose-700">Please review the following:</p>
                <ul class="list-disc list-inside text-xs text-rose-600 mt-1 space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if($items->isEmpty())
            <div class="flex flex-col items-center justify-center py-20 px-4 text-center bg-surface-container-lowest rounded-3xl border border-outline-variant/60 max-w-md mx-auto">
                <div class="w-16 h-16 rounded-full bg-surface-container flex items-center justify-center text-outline mb-4">
                    <span class="material-symbols-outlined text-[32px]">remove_shopping_cart</span>
                </div>
                <h2 class="font-headline-sm text-lg font-bold text-on-surface">No items selected for checkout</h2>
                <p class="font-body-sm text-xs md:text-sm text-on-surface-variant max-w-sm mt-1 mb-6">
                    Return to your shopping cart and select the items you would like to purchase.
                </p>
                <a href="{{ route('cart.index') }}" class="px-8 py-3 rounded-full bg-primary text-on-primary font-label-md text-xs md:text-sm font-semibold hover:opacity-90 transition-all shadow-md">
                    Return to Cart
                </a>
            </div>
        @else
            <form
                x-data="checkoutApp({
                    addresses: {{ json_encode($addresses) }},
                    defaultAddressId: {{ $defaultAddress?->id ?? 'null' }},
                    storeShippingFees: {{ json_encode($storeShippingFees) }},
                    totalSubtotal: {{ (float) $totalSubtotal }},
                    totalShipping: {{ (float) $totalShipping }},
                    vouchers: {{ json_encode($userVouchers) }}
                })"
                @submit="handleSubmit($event)"
                method="POST" action="{{ route('checkout.store') }}"
                class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8 items-start max-w-7xl mx-auto">
                @csrf
                <input type="hidden" name="idempotency_token" value="{{ uniqid('chk_', true) }}">

                {{-- Left Side: Address, Split Order Packages, Vouchers & Payment Method --}}
                <div class="lg:col-span-7 xl:col-span-8 flex flex-col gap-6">

                    {{-- 1. Delivery Address Selection --}}
                    <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant/70 p-5 md:p-6 shadow-xs flex flex-col gap-4">
                        <div class="flex items-center justify-between border-b border-outline-variant/60 pb-3">
                            <h2 class="text-xs font-bold text-on-surface uppercase tracking-wider flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-[16px] text-primary">location_on</span>
                                <span>1. Delivery Address</span>
                            </h2>
                            <button
                                type="button"
                                @click="isNewAddress = !isNewAddress; selectedAddressId = null"
                                class="text-xs font-semibold text-primary hover:underline">
                                <span x-text="isNewAddress ? 'Choose from Address Book' : '+ Use a Different Address'"></span>
                            </button>
                        </div>

                        {{-- Saved Address Selector --}}
                        <div x-show="!isNewAddress && addresses.length > 0" class="flex flex-col gap-3">
                            <template x-for="addr in addresses" :key="addr.id">
                                <label class="p-4 rounded-2xl border transition-all cursor-pointer flex items-start gap-3"
                                    :class="selectedAddressId === addr.id ? 'border-primary bg-primary/5 ring-1 ring-primary/20' : 'border-outline-variant bg-surface hover:bg-surface-container-low/50'">
                                    <input
                                        type="radio"
                                        name="address_id"
                                        :value="addr.id"
                                        x-model="selectedAddressId"
                                        class="mt-1 text-primary focus:ring-primary">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-bold text-on-surface" x-text="addr.recipient_name || 'Recipient'"></span>
                                            <span class="text-xs text-on-surface-variant font-mono" x-text="addr.recipient_phone"></span>
                                            <template x-if="addr.is_default">
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-primary text-on-primary">
                                                    Default
                                                </span>
                                            </template>
                                        </div>
                                        <p class="text-xs text-on-surface leading-relaxed mt-1" x-text="addr.full_address"></p>
                                    </div>
                                </label>
                            </template>
                        </div>

                        {{-- New Address Form with Cascading Location Selector (Region -> Province -> City -> Barangay) --}}
                        <div x-show="isNewAddress || addresses.length === 0" x-cloak class="flex flex-col gap-4 pt-2">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div class="flex flex-col gap-1">
                                    <label class="text-xs font-semibold text-on-surface">Recipient Full Name</label>
                                    <input type="text" name="recipient_name" :required="isNewAddress" value="{{ old('recipient_name', auth()->user()->full_name) }}" class="rounded-xl border border-outline-variant px-3 py-2 text-xs bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                                </div>
                                <div class="flex flex-col gap-1">
                                    <label class="text-xs font-semibold text-on-surface">Mobile Contact Number</label>
                                    <input type="text" name="recipient_phone" :required="isNewAddress" value="{{ old('recipient_phone', auth()->user()->contact_number) }}" placeholder="09XXXXXXXXX" class="rounded-xl border border-outline-variant px-3 py-2 text-xs bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                {{-- Region --}}
                                <div class="flex flex-col gap-1">
                                    <label class="text-xs font-semibold text-on-surface">Region</label>
                                    <select x-model="selectedRegion" @change="onRegionChange()" class="rounded-xl border border-outline-variant px-3 py-2 text-xs bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                                        <option value="">Select Region</option>
                                        <template x-for="r in regions" :key="r.code">
                                            <option :value="r.code" x-text="r.name"></option>
                                        </template>
                                    </select>
                                    <input type="hidden" name="region_code" :value="selectedRegion">
                                    <input type="hidden" name="region_name" :value="selectedRegionName">
                                </div>

                                {{-- Province --}}
                                <div class="flex flex-col gap-1">
                                    <label class="text-xs font-semibold text-on-surface">Province</label>
                                    <select x-model="selectedProvince" @change="onProvinceChange()" :required="isNewAddress" class="rounded-xl border border-outline-variant px-3 py-2 text-xs bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                                        <option value="">Select Province</option>
                                        <template x-for="p in provinces" :key="p.code">
                                            <option :value="p.code" x-text="p.name"></option>
                                        </template>
                                    </select>
                                    <input type="hidden" name="province_code" :value="selectedProvince">
                                    <input type="hidden" name="province_name" :value="selectedProvinceName">
                                </div>

                                {{-- City/Municipality --}}
                                <div class="flex flex-col gap-1">
                                    <label class="text-xs font-semibold text-on-surface">City / Municipality</label>
                                    <select x-model="selectedMunicipality" @change="onMunicipalityChange()" :required="isNewAddress" class="rounded-xl border border-outline-variant px-3 py-2 text-xs bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                                        <option value="">Select City/Municipality</option>
                                        <template x-for="m in municipalities" :key="m.code">
                                            <option :value="m.code" x-text="m.name"></option>
                                        </template>
                                    </select>
                                    <input type="hidden" name="municipality_code" :value="selectedMunicipality">
                                    <input type="hidden" name="municipality_name" :value="selectedMunicipalityName">
                                </div>

                                {{-- Barangay --}}
                                <div class="flex flex-col gap-1">
                                    <label class="text-xs font-semibold text-on-surface">Barangay</label>
                                    <select x-model="selectedBarangay" :required="isNewAddress" class="rounded-xl border border-outline-variant px-3 py-2 text-xs bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                                        <option value="">Select Barangay</option>
                                        <template x-for="b in barangays" :key="b.code">
                                            <option :value="b.code" x-text="b.name"></option>
                                        </template>
                                    </select>
                                    <input type="hidden" name="barangay_code" :value="selectedBarangay">
                                    <input type="hidden" name="barangay_name" :value="selectedBarangayName">
                                </div>
                            </div>

                            <div class="grid grid-cols-3 gap-3">
                                <div class="col-span-2 flex flex-col gap-1">
                                    <label class="text-xs font-semibold text-on-surface">Street / Unit Details</label>
                                    <input type="text" name="street" placeholder="e.g. 45 Rizal Street" class="rounded-xl border border-outline-variant px-3 py-2 text-xs bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                                </div>
                                <div class="flex flex-col gap-1">
                                    <label class="text-xs font-semibold text-on-surface">Postal Code</label>
                                    <input type="text" name="postal_code" placeholder="1000" class="rounded-xl border border-outline-variant px-3 py-2 text-xs bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                                </div>
                            </div>
                        </div>

                    </div>

                    {{-- 2. Multi-Seller Split Order Packages (Rule B8) --}}
                    <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant/70 p-5 md:p-6 shadow-xs flex flex-col gap-5">
                        <div class="flex items-center justify-between border-b border-outline-variant/60 pb-3">
                            <div>
                                <h2 class="text-xs font-bold text-on-surface uppercase tracking-wider flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-[16px] text-primary">local_shipping</span>
                                    <span>2. Multi-Seller Split Order Packages</span>
                                </h2>
                                <p class="text-[11px] text-on-surface-variant mt-0.5">
                                    Orders from different sellers are split automatically. Shipping fees calculated per store rounded up to next kg ($\lceil \text{weight} \rceil$).
                                </p>
                            </div>
                            <span class="px-2.5 py-1 rounded-full bg-primary/10 text-primary text-xs font-bold">
                                {{ $grouped->count() }} Store Package{{ $grouped->count() > 1 ? 's' : '' }}
                            </span>
                        </div>

                        <div class="flex flex-col gap-4">
                            @foreach($grouped as $sellerId => $storeItems)
                                @php
                                    $seller = $storeItems->first()->product->seller;
                                    $storeName = $seller->sellerProfile->business_name ?? $seller->full_name ?? 'Merchant Store';
                                    $weightInfo = $storeWeights[$sellerId];
                                    $shippingFee = $storeShippingFees[$sellerId];
                                    $storeSubtotal = $storeItems->sum(fn ($i) => $i->unit_price * $i->quantity);
                                @endphp
                                <div class="p-4 rounded-2xl border border-outline-variant/80 bg-surface flex flex-col gap-3">
                                    {{-- Store Header --}}
                                    <div class="flex items-center justify-between pb-2 border-b border-outline-variant/40">
                                        <div class="flex items-center gap-2">
                                            <span class="material-symbols-outlined text-[18px] text-primary">storefront</span>
                                            <h3 class="text-xs font-bold text-on-surface">{{ $storeName }}</h3>
                                        </div>
                                        <span class="text-[11px] font-semibold text-on-surface-variant">
                                            {{ $storeItems->count() }} line item{{ $storeItems->count() > 1 ? 's' : '' }}
                                        </span>
                                    </div>

                                    {{-- Store Line Items --}}
                                    <div class="divide-y divide-outline-variant/40">
                                        @foreach($storeItems as $item)
                                            <div class="py-2.5 flex items-center justify-between gap-3 text-xs">
                                                <div class="flex items-center gap-3 min-w-0">
                                                    <img src="{{ $item->product->gallery[0] ?? '' }}" class="w-12 h-12 rounded-xl object-cover border border-outline-variant bg-surface-container flex-shrink-0">
                                                    <div class="min-w-0">
                                                        <h4 class="font-bold text-on-surface truncate">{{ $item->product->name }}</h4>
                                                        @if($item->variant)
                                                            <span class="text-[10px] text-on-surface-variant">
                                                                {{ collect($item->variant)->map(fn($v, $k) => "$k: $v")->implode(', ') }}
                                                            </span>
                                                        @endif
                                                        <div class="text-[10px] text-outline">
                                                            {{ $item->quantity }} × ₱{{ number_format($item->unit_price, 2) }}
                                                            • {{ (float) ($item->product->weight ?? 1.0) * $item->quantity }} kg
                                                        </div>
                                                    </div>
                                                </div>

                                                <span class="font-bold text-on-surface flex-shrink-0">
                                                    ₱{{ number_format($item->unit_price * $item->quantity, 2) }}
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>

                                    {{-- Weight & Shipping Breakdown --}}
                                    <div class="pt-2 border-t border-outline-variant/40 flex flex-wrap items-center justify-between text-xs bg-surface-container-low/40 p-2.5 rounded-xl">
                                        <div class="flex items-center gap-2">
                                            <span class="material-symbols-outlined text-[16px] text-outline">scale</span>
                                            <span class="text-on-surface-variant">
                                                Package Weight: <strong>{{ number_format($weightInfo['raw'], 2) }} kg</strong>
                                                → Billed: <strong>{{ $weightInfo['billed'] }} kg</strong> ($\lceil \text{weight} \rceil$)
                                            </span>
                                        </div>
                                        <div>
                                            <span class="text-on-surface-variant">Shipping Fee:</span>
                                            <strong class="text-on-surface ml-1">₱{{ number_format($shippingFee, 2) }}</strong>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- 3. Voucher Stacking Selection (Rule B7) --}}
                    <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant/70 p-5 md:p-6 shadow-xs flex flex-col gap-4">
                        <div class="flex items-center justify-between border-b border-outline-variant/60 pb-3">
                            <div>
                                <h2 class="text-xs font-bold text-on-surface uppercase tracking-wider flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-[16px] text-primary">confirmation_number</span>
                                    <span>3. Apply Vouchers & Discounts</span>
                                </h2>
                                <p class="text-[11px] text-on-surface-variant mt-0.5">
                                    Stacking rule: Maximum 1 Platform Voucher + 1 Shipping Voucher + 1 Store Voucher.
                                </p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            {{-- Platform Voucher Selector --}}
                            <div class="flex flex-col gap-1.5 p-3 rounded-xl border border-outline-variant bg-surface">
                                <label class="text-[11px] font-bold text-primary uppercase">Platform Voucher</label>
                                <select name="platform_voucher_id" x-model="selectedPlatformVoucher" @change="recalculate()" class="rounded-lg border border-outline-variant px-2.5 py-1.5 text-xs bg-surface-container-lowest text-on-surface">
                                    <option value="">None applied</option>
                                    @foreach($userVouchers->where('voucher_scope', 'platform') as $pv)
                                        <option value="{{ $pv->id }}" data-type="{{ $pv->type }}" data-value="{{ $pv->value }}" data-min="{{ $pv->min_spend }}" data-max="{{ $pv->max_discount }}">
                                            {{ $pv->code }} ({{ $pv->type === 'percent' ? (int)$pv->value . '%' : '₱' . $pv->value }} OFF)
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Shipping Voucher Selector --}}
                            <div class="flex flex-col gap-1.5 p-3 rounded-xl border border-outline-variant bg-surface">
                                <label class="text-[11px] font-bold text-emerald-700 uppercase">Shipping Voucher</label>
                                <select name="shipping_voucher_id" x-model="selectedShippingVoucher" @change="recalculate()" class="rounded-lg border border-outline-variant px-2.5 py-1.5 text-xs bg-surface-container-lowest text-on-surface">
                                    <option value="">None applied</option>
                                    @foreach($userVouchers->where('voucher_scope', 'shipping') as $sv)
                                        <option value="{{ $sv->id }}" data-type="{{ $sv->type }}" data-value="{{ $sv->value }}" data-min="{{ $sv->min_spend }}" data-max="{{ $sv->max_discount }}">
                                            {{ $sv->code }} ({{ $sv->type === 'percent' ? (int)$sv->value . '%' : '₱' . $sv->value }} OFF)
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Store Voucher Selector --}}
                            <div class="flex flex-col gap-1.5 p-3 rounded-xl border border-outline-variant bg-surface">
                                <label class="text-[11px] font-bold text-amber-700 uppercase">Store Voucher</label>
                                <select name="store_voucher_id" x-model="selectedStoreVoucher" @change="recalculate()" class="rounded-lg border border-outline-variant px-2.5 py-1.5 text-xs bg-surface-container-lowest text-on-surface">
                                    <option value="">None applied</option>
                                    @foreach($userVouchers->where('voucher_scope', 'store') as $stv)
                                        <option value="{{ $stv->id }}" data-type="{{ $stv->type }}" data-value="{{ $stv->value }}" data-min="{{ $stv->min_spend }}" data-max="{{ $stv->max_discount }}" data-seller="{{ $stv->seller_id }}">
                                            {{ $stv->code }} ({{ $stv->type === 'percent' ? (int)$stv->value . '%' : '₱' . $stv->value }} OFF)
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    {{-- 4. Payment Method Selection (Rule B9) --}}
                    <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant/70 p-5 md:p-6 shadow-xs flex flex-col gap-4">
                        <div class="flex items-center justify-between border-b border-outline-variant/60 pb-3">
                            <div>
                                <h2 class="text-xs font-bold text-on-surface uppercase tracking-wider flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-[16px] text-primary">payments</span>
                                    <span>4. Payment Method & Guardrails</span>
                                </h2>
                                <p class="text-[11px] text-on-surface-variant mt-0.5">
                                    Gateway orders (Card / e-Wallet) have a 60-minute payment countdown before auto-cancellation.
                                </p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <label class="p-4 rounded-2xl border transition-all cursor-pointer flex flex-col gap-2"
                                :class="paymentMethod === 'cod' ? 'border-primary bg-primary/5 ring-1 ring-primary/20' : 'border-outline-variant bg-surface'">
                                <div class="flex items-center justify-between">
                                    <input type="radio" name="payment_method" value="cod" x-model="paymentMethod" class="text-primary focus:ring-primary">
                                    <span class="material-symbols-outlined text-[20px] text-primary">local_atm</span>
                                </div>
                                <span class="text-xs font-bold text-on-surface">Cash on Delivery</span>
                                <span class="text-[10px] text-on-surface-variant">Pay in cash upon physical receipt</span>
                            </label>

                            <label class="p-4 rounded-2xl border transition-all cursor-pointer flex flex-col gap-2"
                                :class="paymentMethod === 'ewallet' ? 'border-primary bg-primary/5 ring-1 ring-primary/20' : 'border-outline-variant bg-surface'">
                                <div class="flex items-center justify-between">
                                    <input type="radio" name="payment_method" value="ewallet" x-model="paymentMethod" class="text-primary focus:ring-primary">
                                    <span class="material-symbols-outlined text-[20px] text-primary">account_balance_wallet</span>
                                </div>
                                <span class="text-xs font-bold text-on-surface">e-Wallet (GCash / Maya)</span>
                                <span class="text-[10px] text-on-surface-variant">60-minute payment window</span>
                            </label>

                            <label class="p-4 rounded-2xl border transition-all cursor-pointer flex flex-col gap-2"
                                :class="paymentMethod === 'card' ? 'border-primary bg-primary/5 ring-1 ring-primary/20' : 'border-outline-variant bg-surface'">
                                <div class="flex items-center justify-between">
                                    <input type="radio" name="payment_method" value="card" x-model="paymentMethod" class="text-primary focus:ring-primary">
                                    <span class="material-symbols-outlined text-[20px] text-primary">credit_card</span>
                                </div>
                                <span class="text-xs font-bold text-on-surface">Credit / Debit Card</span>
                                <span class="text-[10px] text-on-surface-variant">Visa, Mastercard, JCB</span>
                            </label>
                        </div>
                    </div>

                </div>

                {{-- Right Side: Summary Card & Guarded Submit Button --}}
                <div class="lg:col-span-5 xl:col-span-4 flex flex-col gap-4 sticky top-24">
                    <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 shadow-xs flex flex-col gap-4">
                        <h3 class="font-headline-sm text-sm font-bold text-on-surface pb-3 border-b border-outline-variant/60">
                            Checkout Summary
                        </h3>

                        <div class="flex flex-col gap-2.5 text-xs">
                            <div class="flex items-center justify-between text-on-surface-variant">
                                <span>Cart Subtotal ({{ $items->sum('quantity') }} items)</span>
                                <span class="font-semibold text-on-surface">₱<span x-text="totalSubtotal.toFixed(2)"></span></span>
                            </div>

                            <div class="flex items-center justify-between text-on-surface-variant">
                                <span>Total Shipping Fee ({{ $grouped->count() }} Stores)</span>
                                <span class="font-semibold text-on-surface">₱<span x-text="currentShipping.toFixed(2)"></span></span>
                            </div>

                            <div x-show="totalDiscount > 0" class="flex items-center justify-between text-emerald-700 font-bold">
                                <span>Total Voucher Discounts</span>
                                <span>-₱<span x-text="totalDiscount.toFixed(2)"></span></span>
                            </div>
                        </div>

                        <div class="pt-3 border-t border-outline-variant/60 flex items-baseline justify-between">
                            <span class="text-xs font-bold text-on-surface">Grand Total</span>
                            <span class="text-2xl font-black text-primary">
                                ₱<span x-text="grandTotal.toFixed(2)"></span>
                            </span>
                        </div>

                        {{-- Double-Click Submission Guardrails (Rule B8) --}}
                        <button
                            type="submit"
                            :disabled="submitting || (!selectedAddressId && !isNewAddress)"
                            class="w-full py-4 rounded-full bg-primary text-on-primary font-label-md text-xs font-bold flex items-center justify-center gap-2 shadow-lg shadow-primary/25 hover:opacity-90 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                            <span x-show="submitting" x-cloak class="w-4 h-4 border-2 border-current border-t-transparent rounded-full animate-spin"></span>
                            <span x-text="submitting ? 'Placing Orders...' : 'Place Order Now'"></span>
                            <span x-show="!submitting" class="material-symbols-outlined text-[18px]">verified</span>
                        </button>

                        <div class="text-[11px] text-on-surface-variant leading-relaxed text-center">
                            By placing this order, you confirm that your shipping address is accurate and agree to Nexora's Terms of Service and 7-day return policy.
                        </div>
                    </div>
                </div>

            </form>
        @endif

    </div>
</div>
@endsection

@push('scripts')
<script>
    function checkoutApp(cfg) {
        return {
            addresses: cfg.addresses,
            selectedAddressId: cfg.defaultAddressId,
            isNewAddress: cfg.addresses.length === 0,
            paymentMethod: 'cod',
            submitting: false,

            totalSubtotal: cfg.totalSubtotal,
            baseShipping: cfg.totalShipping,
            currentShipping: cfg.totalShipping,
            totalDiscount: 0,
            grandTotal: cfg.totalSubtotal + cfg.totalShipping,

            selectedPlatformVoucher: '',
            selectedShippingVoucher: '',
            selectedStoreVoucher: '',

            // Cascading location selector data
            regions: [],
            provinces: [],
            municipalities: [],
            barangays: [],
            selectedRegion: '',
            selectedProvince: '',
            selectedMunicipality: '',
            selectedBarangay: '',

            get selectedRegionName() { return this.regions.find(r => r.code === this.selectedRegion)?.name ?? ''; },
            get selectedProvinceName() { return this.provinces.find(p => p.code === this.selectedProvince)?.name ?? ''; },
            get selectedMunicipalityName() { return this.municipalities.find(m => m.code === this.selectedMunicipality)?.name ?? ''; },
            get selectedBarangayName() { return this.barangays.find(b => b.code === this.selectedBarangay)?.name ?? ''; },

            async init() {
                try {
                    const res = await fetch('https://psgc.gitlab.io/api/regions/');
                    this.regions = (await res.json()).sort((a, b) => a.name.localeCompare(b.name));
                } catch (e) {
                    this.regions = [
                        { code: '040000000', name: 'Region IV-A (CALABARZON)' },
                        { code: '130000000', name: 'NCR (National Capital Region)' }
                    ];
                }
            },

            async onRegionChange() {
                this.provinces = [];
                this.municipalities = [];
                this.barangays = [];
                if (!this.selectedRegion) return;
                try {
                    const res = await fetch(`https://psgc.gitlab.io/api/regions/${this.selectedRegion}/provinces/`);
                    const data = await res.json();
                    if (data && data.length) {
                        this.provinces = data.sort((a, b) => a.name.localeCompare(b.name));
                    } else {
                        const cityRes = await fetch(`https://psgc.gitlab.io/api/regions/${this.selectedRegion}/cities-municipalities/`);
                        this.municipalities = (await cityRes.json()).sort((a, b) => a.name.localeCompare(b.name));
                        this.provinces = [{ code: this.selectedRegion, name: this.selectedRegionName }];
                        this.selectedProvince = this.selectedRegion;
                    }
                } catch (e) { console.error(e); }
            },

            async onProvinceChange() {
                this.municipalities = [];
                this.barangays = [];
                if (!this.selectedProvince) return;
                try {
                    const res = await fetch(`https://psgc.gitlab.io/api/provinces/${this.selectedProvince}/cities-municipalities/`);
                    this.municipalities = (await res.json()).sort((a, b) => a.name.localeCompare(b.name));
                } catch (e) { console.error(e); }
            },

            async onMunicipalityChange() {
                this.barangays = [];
                if (!this.selectedMunicipality) return;
                try {
                    const res = await fetch(`https://psgc.gitlab.io/api/cities-municipalities/${this.selectedMunicipality}/barangays/`);
                    this.barangays = (await res.json()).sort((a, b) => a.name.localeCompare(b.name));
                } catch (e) { console.error(e); }
            },

            recalculate() {
                let discount = 0;
                let shipping = this.baseShipping;

                // Platform Voucher calculation
                const pOpt = document.querySelector(`select[name="platform_voucher_id"] option[value="${this.selectedPlatformVoucher}"]`);
                if (pOpt && pOpt.dataset.value) {
                    const type = pOpt.dataset.type;
                    const val = parseFloat(pOpt.dataset.value);
                    const min = parseFloat(pOpt.dataset.min || 0);
                    const max = parseFloat(pOpt.dataset.max || 0);

                    if (this.totalSubtotal >= min) {
                        let d = type === 'percent' ? this.totalSubtotal * (val / 100) : val;
                        if (max > 0) d = Math.min(d, max);
                        discount += d;
                    }
                }

                // Shipping Voucher calculation
                const sOpt = document.querySelector(`select[name="shipping_voucher_id"] option[value="${this.selectedShippingVoucher}"]`);
                if (sOpt && sOpt.dataset.value) {
                    const type = sOpt.dataset.type;
                    const val = parseFloat(sOpt.dataset.value);
                    const min = parseFloat(sOpt.dataset.min || 0);
                    if (this.totalSubtotal >= min) {
                        let sd = type === 'percent' ? shipping * (val / 100) : val;
                        shipping = Math.max(0, shipping - sd);
                    }
                }

                // Store Voucher calculation
                const stOpt = document.querySelector(`select[name="store_voucher_id"] option[value="${this.selectedStoreVoucher}"]`);
                if (stOpt && stOpt.dataset.value) {
                    const type = stOpt.dataset.type;
                    const val = parseFloat(stOpt.dataset.value);
                    let std = type === 'percent' ? (this.totalSubtotal * 0.5) * (val / 100) : val;
                    discount += std;
                }

                this.currentShipping = shipping;
                this.totalDiscount = discount;
                this.grandTotal = Math.max(0, this.totalSubtotal + this.currentShipping - this.totalDiscount);
            },

            handleSubmit(e) {
                if (this.submitting) {
                    e.preventDefault();
                    return false;
                }
                this.submitting = true;
            }
        };
    }
</script>
@endpush
