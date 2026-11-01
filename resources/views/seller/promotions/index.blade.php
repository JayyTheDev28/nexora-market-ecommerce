@extends('layouts.app')

@section('title', 'Promotions & Flash Sales — Seller Hub')

@section('content')
<div class="w-full min-h-screen bg-surface">
    <div class="flex flex-col lg:flex-row w-full">

            <x-seller-nav active="promotions" />

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
                <h1 class="font-headline-lg text-headline-lg text-on-surface">Promotions & Flash Sales</h1>
                <p class="font-body-md text-on-surface-variant">Drive customer volume and conversions with limited-time deals, countdowns, and slashed pricing.</p>
            </div>
            <button
                type="button"
                onclick="document.getElementById('flashSaleModal').classList.remove('hidden')"
                class="inline-flex items-center gap-1.5 bg-primary text-on-primary font-label-md text-sm px-5 py-2.5 rounded-full hover:bg-primary/90 transition-colors shadow-sm w-fit">
                <span class="material-symbols-outlined text-[18px]">flash_on</span>
                Create Flash Sale
            </button>
        </div>

        {{-- Active Flash Sales Section --}}
        <div class="mb-8">
            <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-error text-[24px]">flash_on</span>
                Active Flash Sales
            </h2>

            @if($activeFlashSales->isEmpty())
                <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-8 text-center">
                    <span class="material-symbols-outlined text-[36px] text-outline-variant mb-1">timer_off</span>
                    <p class="font-label-md text-sm text-on-surface font-bold">No Active Flash Sales</p>
                    <p class="text-xs text-on-surface-variant mt-0.5">Launch a limited-time sale with promotional countdown pricing to boost visibility.</p>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($activeFlashSales as $product)
                        <div class="bg-surface-container-lowest rounded-2xl border-2 border-error/40 p-5 shadow-sm flex flex-col justify-between relative overflow-hidden">
                            <div class="flex items-start gap-4">
                                <div class="w-16 h-16 rounded-xl overflow-hidden border border-outline-variant bg-surface-container shrink-0">
                                    <img src="{{ $product->gallery[0] ?? 'https://placehold.co/100x100/e5eeff/0058be?text=' . urlencode($product->name) }}" class="w-full h-full object-cover">
                                </div>
                                <div class="flex-1">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-error text-on-error flex items-center gap-1 w-fit mb-1">
                                        <span class="material-symbols-outlined text-[12px]">bolt</span> Live Deal
                                    </span>
                                    <h3 class="font-bold text-xs text-on-surface line-clamp-1">{{ $product->name }}</h3>
                                    <div class="flex items-baseline gap-2 mt-1">
                                        <span class="text-base font-extrabold text-error">₱{{ number_format($product->flash_sale_price, 2) }}</span>
                                        <span class="text-xs text-on-surface-variant line-through">₱{{ number_format($product->price, 2) }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 pt-3 border-t border-outline-variant/60 flex items-center justify-between">
                                <div class="text-[11px] text-on-surface-variant flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[14px] text-error">schedule</span>
                                    <span>Ends: {{ $product->flash_sale_ends_at->diffForHumans() }}</span>
                                </div>
                                <form method="POST" action="{{ route('seller.promotions.flash-sale.end', $product) }}">
                                    @csrf
                                    <button type="submit" onclick="return confirm('End this flash sale early? Regular price will be restored immediately.');" class="px-3 py-1 rounded-full text-xs font-bold border border-outline-variant text-on-surface-variant hover:text-error hover:border-error transition-colors">
                                        End Deal
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Slashed / Compare-at Discounts Section --}}
        <div>
            <h2 class="font-headline-sm text-headline-sm text-on-surface font-bold mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-amber-500 text-[24px]">percent</span>
                Permanent / Strikethrough Discounts
            </h2>

            @if($discountedProducts->isEmpty())
                <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-8 text-center">
                    <p class="font-label-md text-sm text-on-surface font-bold">No Products with Compare-at Discounts</p>
                    <p class="text-xs text-on-surface-variant mt-0.5">Edit any product to set a compare-at price that shows as a slashed strikethrough bargain.</p>
                </div>
            @else
                <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant overflow-hidden shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="border-b border-outline-variant bg-surface-container-low font-bold text-on-surface-variant uppercase">
                                    <th class="px-5 py-3">Product</th>
                                    <th class="px-5 py-3">Sale Price</th>
                                    <th class="px-5 py-3">Original Compare Price</th>
                                    <th class="px-5 py-3">Buyer Savings</th>
                                    <th class="text-right px-5 py-3">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline-variant">
                                @foreach($discountedProducts as $p)
                                    @php
                                        $savingsPct = round((($p->compare_price - $p->price) / $p->compare_price) * 100);
                                    @endphp
                                    <tr class="hover:bg-surface-container-low/40 transition-colors">
                                        <td class="px-5 py-3.5">
                                            <div class="flex items-center gap-3">
                                                <img src="{{ $p->gallery[0] ?? 'https://placehold.co/60x60/e5eeff/0058be' }}" class="w-9 h-9 rounded-lg object-cover">
                                                <span class="font-bold text-on-surface">{{ $p->name }}</span>
                                            </div>
                                        </td>
                                        <td class="px-5 py-3.5 font-bold text-primary">₱{{ number_format($p->price, 2) }}</td>
                                        <td class="px-5 py-3.5 text-on-surface-variant line-through">₱{{ number_format($p->compare_price, 2) }}</td>
                                        <td class="px-5 py-3.5">
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-600">
                                                Save {{ $savingsPct }}%
                                            </span>
                                        </td>
                                        <td class="px-5 py-3.5 text-right">
                                            <a href="{{ route('seller.products.edit', $p) }}" class="text-primary hover:underline font-bold">Edit Price</a>
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
        </div>
    </div>
</div>


{{-- Create Flash Sale Modal --}}
<div id="flashSaleModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 max-w-md w-full shadow-xl">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-headline-sm text-headline-sm text-on-surface font-bold flex items-center gap-1.5">
                <span class="material-symbols-outlined text-error text-[22px]">flash_on</span>
                Schedule Flash Sale
            </h3>
            <button type="button" onclick="document.getElementById('flashSaleModal').classList.add('hidden')" class="p-1 rounded-full text-on-surface-variant hover:bg-surface-container">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
        </div>

        <form method="POST" action="{{ route('seller.promotions.flash-sale.store') }}" class="space-y-4">
            @csrf

            <div>
                <label for="modal_product_id" class="block font-label-md text-xs font-semibold text-on-surface mb-1">Select Product <span class="text-error">*</span></label>
                <select id="modal_product_id" name="product_id" required class="w-full rounded-xl border border-outline-variant bg-surface px-3 py-2 text-xs text-on-surface focus:border-primary focus:outline-none">
                    <option value="" disabled selected>-- Choose an active product --</option>
                    @foreach($allProducts as $p)
                        <option value="{{ $p->id }}" data-price="{{ $p->price }}">{{ $p->name }} (Regular: ₱{{ number_format($p->price, 2) }})</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="modal_flash_price" class="block font-label-md text-xs font-semibold text-on-surface mb-1">
                    Promotional Flash Sale Price (₱) <span class="text-error">*</span>
                </label>
                <input type="number" step="0.01" min="1" id="modal_flash_price" name="flash_sale_price" required placeholder="0.00" class="w-full rounded-xl border border-outline-variant bg-surface px-3 py-2 text-sm text-on-surface focus:border-primary focus:outline-none">
                <span class="text-[10px] text-on-surface-variant">Must be strictly lower than regular price.</span>
            </div>

            <div>
                <label for="modal_ends_at" class="block font-label-md text-xs font-semibold text-on-surface mb-1">
                    Campaign End Date & Time <span class="text-error">*</span>
                </label>
                <input type="datetime-local" id="modal_ends_at" name="ends_at" required min="{{ now()->addMinutes(15)->format('Y-m-d\TH:i') }}" class="w-full rounded-xl border border-outline-variant bg-surface px-3 py-2 text-xs text-on-surface focus:border-primary focus:outline-none">
                <span class="text-[10px] text-on-surface-variant">When the deal expires, the product automatically reverts to normal pricing.</span>
            </div>

            <div class="pt-3 border-t border-outline-variant flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('flashSaleModal').classList.add('hidden')" class="px-4 py-2 rounded-full border border-outline-variant text-xs font-semibold text-on-surface hover:bg-surface-container">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2 rounded-full bg-error text-on-error text-xs font-bold hover:bg-error/90">
                    Launch Flash Sale
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
