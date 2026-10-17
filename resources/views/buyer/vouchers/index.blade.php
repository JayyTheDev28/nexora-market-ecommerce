@extends('layouts.app')

@section('title', 'Vouchers & Discounts — Nexora Market')

@section('content')
<div x-data="voucherWallet()" class="w-full bg-surface py-6 md:py-8 min-h-[75vh]">
    <div class="max-w-none px-4 md:px-8">

        <x-buyer-nav active="vouchers" />

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="font-headline-lg text-2xl md:text-3xl font-bold tracking-tight text-on-surface">Vouchers & Discounts (B7)</h1>
                <p class="font-body-md text-xs md:text-sm text-on-surface-variant mt-1">
                    Claim vouchers to save on your orders. Stacking rule: Max 1 Store + 1 Platform + 1 Shipping Voucher per checkout.
                </p>
            </div>

            {{-- Tabs --}}
            <div class="flex items-center gap-2 p-1 bg-surface-container-low rounded-2xl border border-outline-variant/60">
                <button
                    type="button"
                    @click="activeScope = 'all'"
                    :class="activeScope === 'all' ? 'bg-surface-container-lowest text-primary font-bold shadow-xs' : 'text-on-surface-variant hover:text-on-surface'"
                    class="px-4 py-1.5 rounded-xl text-xs font-semibold transition-all">
                    All Vouchers
                </button>
                <button
                    type="button"
                    @click="activeScope = 'platform'"
                    :class="activeScope === 'platform' ? 'bg-surface-container-lowest text-primary font-bold shadow-xs' : 'text-on-surface-variant hover:text-on-surface'"
                    class="px-4 py-1.5 rounded-xl text-xs font-semibold transition-all">
                    Platform
                </button>
                <button
                    type="button"
                    @click="activeScope = 'shipping'"
                    :class="activeScope === 'shipping' ? 'bg-surface-container-lowest text-primary font-bold shadow-xs' : 'text-on-surface-variant hover:text-on-surface'"
                    class="px-4 py-1.5 rounded-xl text-xs font-semibold transition-all">
                    Free Shipping
                </button>
                <button
                    type="button"
                    @click="activeScope = 'store'"
                    :class="activeScope === 'store' ? 'bg-surface-container-lowest text-primary font-bold shadow-xs' : 'text-on-surface-variant hover:text-on-surface'"
                    class="px-4 py-1.5 rounded-xl text-xs font-semibold transition-all">
                    Store
                </button>
            </div>
        </div>

        @if (session('status'))
            <div class="mb-6 bg-primary/10 border border-primary/20 rounded-2xl p-4 flex items-center justify-between">
                <p class="font-body-sm text-xs md:text-sm font-medium text-primary">{{ session('status') }}</p>
            </div>
        @endif

        {{-- Voucher Cards Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @forelse($availableVouchers as $voucher)
                @php
                    $isClaimed = in_array($voucher->id, $claimedIds, true);
                    $scopeBadge = match($voucher->voucher_scope) {
                        'platform' => ['label' => 'Platform Voucher', 'class' => 'bg-primary/10 text-primary'],
                        'shipping' => ['label' => 'Shipping Discount', 'class' => 'bg-emerald-500/10 text-emerald-700'],
                        default => ['label' => ($voucher->seller->sellerProfile->business_name ?? 'Store Voucher'), 'class' => 'bg-amber-500/10 text-amber-700'],
                    };
                @endphp
                <div
                    x-show="activeScope === 'all' || activeScope === '{{ $voucher->voucher_scope }}'"
                    class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-5 flex flex-col justify-between gap-4 relative overflow-hidden shadow-xs hover:shadow-md transition-shadow">
                    
                    {{-- Decorative Ticket Cutouts --}}
                    <div class="absolute -left-3 top-1/2 -translate-y-1/2 w-6 h-6 rounded-full bg-surface border-r border-outline-variant"></div>
                    <div class="absolute -right-3 top-1/2 -translate-y-1/2 w-6 h-6 rounded-full bg-surface border-l border-outline-variant"></div>

                    <div class="flex flex-col gap-2">
                        <div class="flex items-center justify-between gap-2">
                            <span class="px-2 py-0.5 rounded-md text-[10px] font-extrabold {{ $scopeBadge['class'] }}">
                                {{ $scopeBadge['label'] }}
                            </span>
                            <span class="font-mono text-xs font-bold text-on-surface bg-surface-container px-2 py-0.5 rounded-md border border-outline-variant">
                                {{ $voucher->code }}
                            </span>
                        </div>

                        {{-- Value / Discount Details --}}
                        <div class="pt-1">
                            <h3 class="text-xl font-extrabold text-primary">
                                @if($voucher->type === 'percent')
                                    {{ (int) $voucher->value }}% OFF
                                @else
                                    ₱{{ number_format($voucher->value, 2) }} OFF
                                @endif
                            </h3>
                            <p class="text-xs text-on-surface-variant font-medium mt-0.5">
                                Min. Spend: <span class="font-bold text-on-surface">₱{{ number_format($voucher->min_spend, 2) }}</span>
                                @if($voucher->max_discount)
                                    • Cap: <span class="font-bold text-on-surface">₱{{ number_format($voucher->max_discount, 2) }}</span>
                                @endif
                            </p>
                        </div>

                        @if($voucher->expires_at)
                            <p class="text-[11px] text-outline flex items-center gap-1 mt-1">
                                <span class="material-symbols-outlined text-[13px]">schedule</span>
                                <span>Expires {{ $voucher->expires_at->diffForHumans() }}</span>
                            </p>
                        @endif
                    </div>

                    {{-- Action Button --}}
                    <div class="pt-3 border-t border-dashed border-outline-variant flex items-center justify-between">
                        <span class="text-[11px] text-on-surface-variant">
                            {{ $isClaimed ? 'In your voucher wallet' : 'Available to claim' }}
                        </span>

                        <button
                            type="button"
                            @click="claimVoucher({{ $voucher->id }})"
                            id="claim-btn-{{ $voucher->id }}"
                            class="px-4 py-1.5 rounded-xl text-xs font-semibold transition-all {{ $isClaimed ? 'bg-surface-container text-outline cursor-default' : 'bg-primary text-on-primary hover:opacity-90' }}"
                            :disabled="{{ $isClaimed ? 'true' : 'false' }}">
                            {{ $isClaimed ? 'Claimed' : 'Claim Voucher' }}
                        </button>
                    </div>

                </div>
            @empty
                <div class="col-span-full p-12 text-center bg-surface-container-lowest rounded-3xl border border-dashed border-outline-variant">
                    <span class="material-symbols-outlined text-outline text-[48px] mb-2">confirmation_number</span>
                    <h3 class="text-sm font-bold text-on-surface">No vouchers currently active</h3>
                    <p class="text-xs text-on-surface-variant mt-1">Check back later for seasonal promotions and promotional discount codes.</p>
                </div>
            @endforelse
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
    function voucherWallet() {
        return {
            activeScope: 'all',
            async claimVoucher(voucherId) {
                const btn = document.getElementById(`claim-btn-${voucherId}`);
                if (btn) btn.disabled = true;

                try {
                    const res = await fetch(`/buyer/vouchers/${voucherId}/claim`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        }
                    });
                    const data = await res.json();
                    if (data.success || res.ok) {
                        if (btn) {
                            btn.innerText = 'Claimed';
                            btn.className = 'px-4 py-1.5 rounded-xl text-xs font-semibold bg-surface-container text-outline cursor-default';
                        }
                    } else if (data.error) {
                        alert(data.error);
                        if (btn) btn.disabled = false;
                    }
                } catch (e) {
                    console.error('Failed to claim voucher', e);
                    if (btn) btn.disabled = false;
                }
            }
        };
    }
</script>
@endpush
