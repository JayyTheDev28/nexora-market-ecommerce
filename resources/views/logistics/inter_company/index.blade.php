@extends('layouts.app')

@section('title', 'Inter-Company Shipping Settlement — Logistics Operations')

@section('content')
<div class="w-full min-h-screen bg-surface">
    <div class="flex flex-col lg:flex-row w-full">
        <x-logistics-nav active="inter-company" />

        <div class="flex-1 min-w-0 p-4 sm:p-6 lg:p-8 bg-surface">
            <div class="w-full max-w-7xl mx-auto space-y-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2 text-xs">
                        <a href="{{ route('logistics.dashboard') }}" class="text-primary font-bold hover:underline">Logistics Dashboard</a>
                        <span class="text-outline-variant">/</span>
                        <span class="text-on-surface-variant">Inter-Company Shipping Ledger</span>
                    </div>
                    <a href="{{ route('logistics.shipping-rates.index') }}" class="text-xs text-primary font-bold hover:underline">Configure Rate Splits →</a>
                </div>

                @if (session('status'))
                    <div class="bg-primary/10 border border-primary/30 rounded-2xl p-4 text-xs text-primary font-semibold">{{ session('status') }}</div>
                @endif

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4">
                        <span class="text-[10px] text-on-surface-variant uppercase font-semibold block">Origin Pickup Share Receivable</span>
                        <span class="text-2xl font-extrabold text-emerald-800 mt-1 block">₱{{ number_format($stats['pickupReceivable'], 2) }}</span>
                    </div>
                    <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4">
                        <span class="text-[10px] text-on-surface-variant uppercase font-semibold block">Line-Haul Transfer Receivable</span>
                        <span class="text-2xl font-extrabold text-indigo-800 mt-1 block">₱{{ number_format($stats['transferReceivable'], 2) }}</span>
                    </div>
                    <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4">
                        <span class="text-[10px] text-on-surface-variant uppercase font-semibold block">Partner Shares Payable (Collected COD)</span>
                        <span class="text-2xl font-extrabold text-amber-800 mt-1 block">₱{{ number_format($stats['payableToPartners'], 2) }}</span>
                    </div>
                </div>

                <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant overflow-hidden shadow-xs">
                    <div class="p-5 overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="border-b border-outline-variant text-[11px] uppercase text-on-surface-variant">
                                    <th class="pb-3">Order #</th>
                                    <th class="pb-3 text-right">Total Shipping</th>
                                    <th class="pb-3 text-right">Origin Share</th>
                                    <th class="pb-3 text-right">Transfer Share</th>
                                    <th class="pb-3 text-right">Delivery Share</th>
                                    <th class="pb-3">Status</th>
                                    <th class="pb-3 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline-variant/40">
                                @forelse($allocations as $a)
                                    <tr>
                                        <td class="py-3 font-mono font-bold text-primary">{{ $a->order?->order_number }}</td>
                                        <td class="py-3 text-right font-bold">₱{{ number_format($a->total_shipping_fee, 2) }}</td>
                                        <td class="py-3 text-right">₱{{ number_format($a->pickup_share_amount, 2) }}</td>
                                        <td class="py-3 text-right">₱{{ number_format($a->transfer_share_amount, 2) }}</td>
                                        <td class="py-3 text-right font-semibold">₱{{ number_format($a->delivery_share_amount, 2) }}</td>
                                        <td class="py-3">
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $a->settlement_status === 'settled' ? 'bg-emerald-500/10 text-emerald-800' : ($a->settlement_status === 'self_retained' ? 'bg-surface-container text-on-surface-variant' : 'bg-amber-500/10 text-amber-800') }}">{{ str_replace('_', ' ', $a->settlement_status) }}</span>
                                            @if($a->allocation_mode)
                                                <div class="text-[10px] text-outline font-mono mt-0.5">{{ str_replace('_', ' ', $a->allocation_mode) }}</div>
                                            @endif
                                        </td>
                                        <td class="py-3 text-right">
                                            @if($a->settlement_status === 'allocated')
                                                <form method="POST" action="{{ route('logistics.inter-company.settle', $a) }}" class="inline">
                                                    @csrf
                                                    <button type="submit" class="px-3 py-1 rounded-xl bg-primary/10 text-primary font-bold cursor-pointer">Mark Settled</button>
                                                </form>
                                            @elseif($a->settlement_status === 'self_retained')
                                                <span class="text-[11px] text-outline">Self-Retained (No Payable)</span>
                                            @elseif($a->settlement_status === 'pending_policy_approval')
                                                <span class="text-[11px] text-amber-800 font-semibold">Pending Admin Contract</span>
                                            @else
                                                <span class="text-[11px] text-outline">Settled</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="py-8 text-center text-on-surface-variant">No inter-company shipping allocations recorded yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                        <div class="mt-4">{{ $allocations->links() }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
