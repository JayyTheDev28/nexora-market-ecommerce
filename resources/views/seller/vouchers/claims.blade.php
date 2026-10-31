@extends('layouts.app')

@section('title', 'Voucher Claims History — ' . $voucher->code)

@section('content')
<div class="w-full bg-surface py-8 min-h-[70vh]">
    <div class="max-w-none px-margin-mobile md:px-margin-desktop">

        <x-seller-nav active="vouchers" />

        <div class="flex items-center justify-between gap-4 mb-6">
            <div>
                <a href="{{ route('seller.vouchers.index') }}" class="inline-flex items-center gap-1 text-xs text-primary font-semibold hover:underline mb-1">
                    <span class="material-symbols-outlined text-[16px]">arrow_back</span> Back to Vouchers
                </a>
                <h1 class="font-headline-lg text-headline-lg text-on-surface flex items-center gap-2">
                    Claims & Redemption:
                    <span class="font-mono text-primary font-bold">{{ $voucher->code }}</span>
                </h1>
            </div>
        </div>

        <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant overflow-hidden shadow-sm">
            <div class="px-6 py-4 bg-surface-container-low border-b border-outline-variant flex items-center justify-between text-xs text-on-surface-variant font-bold uppercase">
                <span>Buyer / Claimed At</span>
                <span>Redemption Status</span>
            </div>

            @if($claims->isEmpty())
                <div class="p-12 text-center">
                    <p class="font-label-md text-sm text-on-surface">No Claims Yet</p>
                    <p class="text-xs text-on-surface-variant mt-1">This voucher has not been claimed by any buyers yet.</p>
                </div>
            @else
                <div class="divide-y divide-outline-variant text-xs">
                    @foreach($claims as $claim)
                        <div class="px-6 py-4 flex items-center justify-between hover:bg-surface-container-low/40 transition-colors">
                            <div>
                                <p class="font-bold text-on-surface">{{ $claim->user->full_name }}</p>
                                <p class="text-[11px] text-on-surface-variant">{{ $claim->user->email }} · Claimed {{ $claim->claimed_at->format('M j, Y H:i') }}</p>
                            </div>
                            <div>
                                @if($claim->used_at)
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-600 flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[12px]">check</span>
                                        Used in Order {{ $claim->order?->order_number }} ({{ $claim->used_at->format('M j, Y') }})
                                    </span>
                                @else
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-600">
                                        In Buyer Wallet (Unused)
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="px-6 py-4 border-t border-outline-variant bg-surface-container-low">
                    {{ $claims->links() }}
                </div>
            @endif
        </div>

    </div>
</div>
@endsection
