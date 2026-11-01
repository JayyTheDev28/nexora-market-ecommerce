@extends('layouts.app')

@section('title', 'Store Vouchers — Seller Hub')

@section('content')
<div class="w-full min-h-screen bg-surface">
    <div class="flex flex-col lg:flex-row w-full">

            <x-seller-nav active="vouchers" />

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
                <h1 class="font-headline-lg text-headline-lg text-on-surface">Store Vouchers</h1>
                <p class="font-body-md text-on-surface-variant">Create and issue promotional discount coupons specifically for your store merchandise.</p>
            </div>
            <button
                type="button"
                onclick="document.getElementById('createVoucherModal').classList.remove('hidden')"
                class="inline-flex items-center gap-1.5 bg-primary text-on-primary font-label-md text-sm px-5 py-2.5 rounded-full hover:bg-primary/90 transition-colors shadow-sm w-fit">
                <span class="material-symbols-outlined text-[18px]">add</span>
                Create Voucher
            </button>
        </div>

        {{-- Voucher Stat Cards --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-5">
                <p class="text-xs font-semibold text-on-surface-variant">Total Vouchers</p>
                <p class="font-headline-lg text-2xl font-bold text-on-surface mt-1">{{ $stats['total_vouchers'] }}</p>
            </div>
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-5">
                <p class="text-xs font-semibold text-on-surface-variant">Active & Usable</p>
                <p class="font-headline-lg text-2xl font-bold text-emerald-600 mt-1">{{ $stats['active_vouchers'] }}</p>
            </div>
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-5">
                <p class="text-xs font-semibold text-on-surface-variant">Claimed by Buyers</p>
                <p class="font-headline-lg text-2xl font-bold text-primary mt-1">{{ $stats['total_claims'] }}</p>
            </div>
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-5">
                <p class="text-xs font-semibold text-on-surface-variant">Redeemed in Orders</p>
                <p class="font-headline-lg text-2xl font-bold text-amber-500 mt-1">{{ $stats['total_used'] }}</p>
            </div>
        </div>

        {{-- Vouchers Table --}}
        @if($vouchers->isEmpty())
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-12 text-center">
                <span class="material-symbols-outlined text-[48px] text-outline-variant mb-2">confirmation_number</span>
                <h3 class="font-headline-sm text-headline-sm text-on-surface font-bold">No Store Vouchers Created</h3>
                <p class="font-body-sm text-xs text-on-surface-variant mt-1 max-w-sm mx-auto">
                    Attract first-time shoppers and reward repeat customers with fixed-peso or percentage discount vouchers.
                </p>
                <button
                    type="button"
                    onclick="document.getElementById('createVoucherModal').classList.remove('hidden')"
                    class="mt-4 px-5 py-2.5 rounded-full bg-primary text-on-primary text-xs font-bold hover:bg-primary/90 transition-colors">
                    Create First Voucher
                </button>
            </div>
        @else
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-outline-variant bg-surface-container-low font-bold text-on-surface-variant uppercase">
                                <th class="px-5 py-3">Voucher Code</th>
                                <th class="px-5 py-3">Discount Value</th>
                                <th class="px-5 py-3">Conditions</th>
                                <th class="px-5 py-3">Claims / Usage</th>
                                <th class="px-5 py-3">Expiration</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="text-right px-5 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant">
                            @foreach($vouchers as $voucher)
                                @php
                                    $isExpired = $voucher->isExpired();
                                @endphp
                                <tr class="hover:bg-surface-container-low/40 transition-colors">
                                    <td class="px-5 py-3.5">
                                        <div class="flex items-center gap-2">
                                            <span class="px-2.5 py-1 rounded-lg font-mono font-bold text-xs bg-amber-500/10 text-amber-600 border border-amber-500/20">
                                                {{ $voucher->code }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <span class="font-extrabold text-sm text-on-surface">
                                            @if($voucher->type === 'flat')
                                                ₱{{ number_format($voucher->value, 2) }} OFF
                                            @else
                                                {{ (int) $voucher->value }}% OFF
                                            @endif
                                        </span>
                                    </td>
                                    <td class="px-5 py-3.5 text-on-surface-variant">
                                        <p>Min spend: <strong class="text-on-surface">₱{{ number_format($voucher->min_spend, 2) }}</strong></p>
                                        @if($voucher->max_discount)
                                            <p class="text-[10px]">Cap: ₱{{ number_format($voucher->max_discount, 2) }}</p>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <p class="font-medium text-on-surface">{{ $voucher->claims_count }} claimed</p>
                                        <p class="text-[10px] text-on-surface-variant">{{ $voucher->used_count }} redeemed</p>
                                    </td>
                                    <td class="px-5 py-3.5 text-on-surface-variant">
                                        {{ $voucher->expires_at ? $voucher->expires_at->format('M j, Y H:i') : 'No Expiry' }}
                                    </td>
                                    <td class="px-5 py-3.5">
                                        @if($isExpired)
                                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-outline-variant/30 text-on-surface-variant">
                                                Expired
                                            </span>
                                        @elseif(!$voucher->is_active)
                                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-error/10 text-error">
                                                Inactive
                                            </span>
                                        @else
                                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-600">
                                                Active
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3.5 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ route('seller.vouchers.claims', $voucher) }}" class="p-1 rounded text-primary hover:underline font-bold" title="View Claims History">
                                                Claims
                                            </a>
                                            <form method="POST" action="{{ route('seller.vouchers.toggle', $voucher) }}">
                                                @csrf
                                                <button type="submit" class="px-2.5 py-1 rounded-full border border-outline-variant text-[11px] font-bold text-on-surface hover:bg-surface-container transition-colors">
                                                    {{ $voucher->is_active ? 'Disable' : 'Enable' }}
                                                </button>
                                            </form>
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
    </div>
</div>


{{-- Create Voucher Modal --}}
<div id="createVoucherModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 max-w-lg w-full shadow-xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-headline-sm text-headline-sm text-on-surface font-bold flex items-center gap-2">
                <span class="material-symbols-outlined text-amber-500 text-[22px]">confirmation_number</span>
                Create Store Voucher
            </h3>
            <button type="button" onclick="document.getElementById('createVoucherModal').classList.add('hidden')" class="p-1 rounded-full text-on-surface-variant hover:bg-surface-container">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
        </div>

        <form method="POST" action="{{ route('seller.vouchers.store') }}" class="space-y-4">
            @csrf

            <div>
                <label for="code" class="block font-label-md text-xs font-semibold text-on-surface mb-1">
                    Voucher Code <span class="text-error">*</span>
                </label>
                <input type="text" id="code" name="code" required placeholder="e.g. SAVE50, MARCHFEST" class="w-full rounded-xl border border-outline-variant bg-surface px-3 py-2 text-sm font-mono uppercase text-on-surface focus:border-primary focus:outline-none">
                <span class="text-[10px] text-on-surface-variant">Letters and numbers only, automatically converted to uppercase.</span>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="type" class="block font-label-md text-xs font-semibold text-on-surface mb-1">Discount Type <span class="text-error">*</span></label>
                    <select id="type" name="type" required class="w-full rounded-xl border border-outline-variant bg-surface px-3 py-2 text-xs text-on-surface focus:border-primary focus:outline-none">
                        <option value="flat">Fixed Amount (₱)</option>
                        <option value="percent">Percentage (%)</option>
                    </select>
                </div>
                <div>
                    <label for="value" class="block font-label-md text-xs font-semibold text-on-surface mb-1">Discount Value <span class="text-error">*</span></label>
                    <input type="number" step="0.01" min="1" id="value" name="value" required placeholder="50.00" class="w-full rounded-xl border border-outline-variant bg-surface px-3 py-2 text-xs text-on-surface focus:border-primary focus:outline-none">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="min_spend" class="block font-label-md text-xs font-semibold text-on-surface mb-1">Minimum Spend (₱) <span class="text-error">*</span></label>
                    <input type="number" step="0.01" min="0" id="min_spend" name="min_spend" required value="0" class="w-full rounded-xl border border-outline-variant bg-surface px-3 py-2 text-xs text-on-surface focus:border-primary focus:outline-none">
                </div>
                <div>
                    <label for="max_discount" class="block font-label-md text-xs font-semibold text-on-surface mb-1">Max Discount Cap (₱)</label>
                    <input type="number" step="0.01" min="1" id="max_discount" name="max_discount" placeholder="Optional for %" class="w-full rounded-xl border border-outline-variant bg-surface px-3 py-2 text-xs text-on-surface focus:border-primary focus:outline-none">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="usage_limit" class="block font-label-md text-xs font-semibold text-on-surface mb-1">Total Usage Limit</label>
                    <input type="number" min="1" id="usage_limit" name="usage_limit" placeholder="e.g. 100 uses" class="w-full rounded-xl border border-outline-variant bg-surface px-3 py-2 text-xs text-on-surface focus:border-primary focus:outline-none">
                </div>
                <div>
                    <label for="expires_at" class="block font-label-md text-xs font-semibold text-on-surface mb-1">Expiration Date</label>
                    <input type="datetime-local" id="expires_at" name="expires_at" min="{{ now()->addHours(1)->format('Y-m-d\TH:i') }}" class="w-full rounded-xl border border-outline-variant bg-surface px-3 py-2 text-xs text-on-surface focus:border-primary focus:outline-none">
                </div>
            </div>

            <div class="pt-3 border-t border-outline-variant flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('createVoucherModal').classList.add('hidden')" class="px-4 py-2 rounded-full border border-outline-variant text-xs font-semibold text-on-surface hover:bg-surface-container">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2 rounded-full bg-primary text-on-primary text-xs font-bold hover:bg-primary/90">
                    Publish Voucher
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
