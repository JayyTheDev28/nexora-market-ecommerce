@extends('layouts.app')

@section('title', $order->order_number . ' — Seller Dashboard')

@section('content')
<div class="w-full bg-surface py-8 min-h-[70vh]">
    <div class="max-w-none px-margin-mobile md:px-margin-desktop">

        <x-seller-nav active="orders" />

        @if (session('status'))
            <div class="mb-6 bg-primary/10 border border-primary/30 rounded-2xl p-4">
                <p class="font-body-sm text-body-sm text-primary">{{ session('status') }}</p>
            </div>
        @endif

        <div class="flex items-center gap-3 mb-6">
            <a href="{{ route('seller.orders.index') }}" class="text-on-surface-variant hover:text-on-surface">
                <span class="material-symbols-outlined">arrow_back</span>
            </a>
            <h1 class="font-headline-lg text-headline-lg text-on-surface">{{ $order->order_number }}</h1>
            <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-primary/10 text-primary">{{ ucfirst(str_replace('_', ' ', $order->status)) }}</span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

            <div class="lg:col-span-2 flex flex-col gap-6">

                {{-- Items (only this seller's lines, per the controller query) --}}
                <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-6">
                    <h2 class="font-headline-sm text-headline-sm text-on-surface mb-4">Items</h2>
                    <div class="flex flex-col gap-3">
                        @foreach($order->items as $item)
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="font-body-sm text-body-sm text-on-surface">{{ $item->product_name }}</p>
                                    @if($item->variant)
                                        <p class="font-body-sm text-body-sm text-on-surface-variant">
                                            @foreach($item->variant as $k => $v)
                                                <span class="mr-2">{{ $k }}: {{ $v }}</span>
                                            @endforeach
                                        </p>
                                    @endif
                                    <p class="font-body-sm text-body-sm text-on-surface-variant">Qty {{ $item->quantity }}</p>
                                </div>
                                <span class="font-label-md text-label-md text-on-surface">₱{{ number_format($item->price * $item->quantity, 2) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Status history --}}
                <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-6">
                    <h2 class="font-headline-sm text-headline-sm text-on-surface mb-4">Status History</h2>
                    <div class="flex flex-col gap-4">
                        @forelse($order->statusHistory as $event)
                            <div class="flex items-start gap-3">
                                <span class="w-2 h-2 rounded-full bg-primary mt-1.5 flex-shrink-0"></span>
                                <div>
                                    <p class="font-body-sm text-body-sm text-on-surface">{{ ucfirst(str_replace('_', ' ', $event->status)) }}</p>
                                    <p class="font-body-sm text-body-sm text-on-surface-variant">
                                        {{ $event->changed_at->format('M j, Y g:i A') }}
                                        @if($event->changedBy)
                                            · {{ $event->changedBy->full_name }}
                                        @endif
                                    </p>
                                </div>
                            </div>
                        @empty
                            <p class="font-body-sm text-body-sm text-on-surface-variant">No status changes recorded yet.</p>
                        @endforelse
                    </div>
                </div>

            </div>

            <div class="flex flex-col gap-6">

                {{-- Buyer & delivery info --}}
                <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-6 flex flex-col gap-4">
                    <h2 class="font-headline-sm text-headline-sm text-on-surface">Delivery Details</h2>
                    <dl class="flex flex-col gap-2 font-body-sm text-body-sm">
                        <div>
                            <dt class="text-on-surface-variant">Buyer</dt>
                            <dd class="text-on-surface">{{ $order->buyer->full_name }}</dd>
                        </div>
                        <div>
                            <dt class="text-on-surface-variant">Address</dt>
                            <dd class="text-on-surface">{{ $order->address->full_address }}</dd>
                        </div>
                        <div>
                            <dt class="text-on-surface-variant">Payment Method</dt>
                            <dd class="text-on-surface">{{ match($order->payment_method) { 'cod' => 'Cash on Delivery', 'ewallet' => 'E-Wallet', 'card' => 'Credit / Debit Card', default => $order->payment_method } }}</dd>
                        </div>
                    </dl>
                </div>

                {{-- Advance status action --}}
                <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-6 flex flex-col gap-4">
                    <h2 class="font-headline-sm text-headline-sm text-on-surface">Order Actions</h2>
                    @if($nextStatus)
                        <form method="POST" action="{{ route('seller.orders.advance', $order) }}">
                            @csrf
                            <button type="submit" class="w-full flex items-center justify-center gap-2 bg-primary text-on-primary font-label-md text-label-md px-6 py-3 rounded-full hover:bg-primary/90 transition-colors">
                                <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                                {{ $actionLabel }}
                            </button>
                        </form>
                        @if($order->status === 'ready_for_pickup')
                            <p class="font-body-sm text-body-sm text-on-surface-variant">
                                This stands in for the rider pickup confirmation until the courier role is built — for now, mark it once the parcel actually leaves your hands.
                            </p>
                        @endif
                    @else
                        <p class="font-body-sm text-body-sm text-on-surface-variant">
                            This order has moved past what a seller controls — it's now with logistics/courier{{ $order->status === 'completed' ? ' and has been completed by the buyer' : '' }}.
                        </p>
                    @endif
                </div>

            </div>

        </div>
    </div>
</div>
@endsection
