@extends('layouts.app')

@section('title', $order->order_number . ' — Order Details')

@section('content')
<div class="w-full bg-surface py-8 min-h-[70vh]">
    <div class="max-w-none px-margin-mobile md:px-margin-desktop">

        <x-seller-nav active="orders" />

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
            <div class="flex items-center gap-3">
                <a href="{{ route('seller.orders.index') }}" class="p-2 rounded-full border border-outline-variant text-on-surface-variant hover:text-on-surface hover:bg-surface-container transition-colors">
                    <span class="material-symbols-outlined text-[20px]">arrow_back</span>
                </a>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="font-headline-lg text-headline-lg text-on-surface font-black">{{ $order->order_number }}</h1>
                        <span class="px-3 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider
                            {{ $order->status === 'placed' ? 'bg-primary/10 text-primary border border-primary/20' : ($order->status === 'cancelled' ? 'bg-error/10 text-error' : ($order->status === 'completed' || $order->status === 'delivered' ? 'bg-emerald-500/10 text-emerald-600' : 'bg-blue-500/10 text-blue-600')) }}">
                            {{ str_replace('_', ' ', $order->status) }}
                        </span>
                    </div>
                    <p class="font-body-sm text-xs text-on-surface-variant mt-0.5">
                        Placed on {{ $order->placed_at->format('M j, Y \a\t g:i A') }}
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('seller.orders.waybill', $order) }}" target="_blank" class="px-4 py-2 rounded-full border border-outline-variant font-label-md text-xs font-bold text-on-surface hover:bg-surface-container transition-colors flex items-center gap-1.5 shadow-xs">
                    <span class="material-symbols-outlined text-[18px]">print</span>
                    Print Shipping Waybill
                </a>
            </div>
        </div>

        {{-- Dispute / Return Alert if open --}}
        @if($order->dispute)
            <div class="mb-6 p-4 rounded-2xl bg-error/10 border border-error/30 flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-error text-[24px]">assignment_return</span>
                    <div>
                        <h3 class="font-label-md text-sm font-bold text-on-surface">Return / Refund Request Active</h3>
                        <p class="text-xs text-on-surface-variant">Buyer reason: {{ $order->dispute->reason }} (Status: {{ ucfirst($order->dispute->seller_status) }})</p>
                    </div>
                </div>
                <a href="{{ route('seller.returns.show', $order->dispute) }}" class="px-4 py-1.5 rounded-full bg-error text-on-error text-xs font-bold hover:bg-error/90 transition-colors">
                    Review Claim
                </a>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

            <div class="lg:col-span-2 flex flex-col gap-6">

                {{-- Order Items Table --}}
                <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-6 shadow-sm">
                    <h2 class="font-headline-sm text-sm font-bold text-on-surface border-b border-outline-variant pb-3 mb-4">
                        Order Manifest
                    </h2>
                    <div class="divide-y divide-outline-variant">
                        @foreach($order->items as $item)
                            <div class="py-3.5 flex items-center justify-between gap-4 first:pt-0 last:pb-0">
                                <div class="flex items-center gap-3">
                                    <div class="w-12 h-12 rounded-xl bg-surface-container-low border border-outline-variant overflow-hidden shrink-0">
                                        <img src="{{ $item->product?->gallery[0] ?? 'https://placehold.co/100x100/e5eeff/0058be' }}" class="w-full h-full object-cover">
                                    </div>
                                    <div>
                                        <h3 class="font-label-md text-xs font-bold text-on-surface">{{ $item->product_name }}</h3>
                                        @if($item->variant)
                                            <p class="text-[11px] text-on-surface-variant">
                                                Variant: {{ is_array($item->variant) ? implode(', ', $item->variant) : $item->variant }}
                                            </p>
                                        @endif
                                        <p class="text-[11px] text-on-surface-variant font-medium">₱{{ number_format($item->price, 2) }} × {{ $item->quantity }} units</p>
                                    </div>
                                </div>
                                <span class="font-label-md text-sm font-extrabold text-on-surface">
                                    ₱{{ number_format($item->price * $item->quantity, 2) }}
                                </span>
                            </div>
                        @endforeach
                    </div>

                    {{-- Financial Breakdown --}}
                    <div class="mt-6 pt-4 border-t border-outline-variant space-y-1.5 text-xs text-on-surface-variant">
                        <div class="flex justify-between">
                            <span>Merchandise Subtotal:</span>
                            <span class="font-mono text-on-surface">₱{{ number_format($order->subtotal, 2) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Shipping Fee:</span>
                            <span class="font-mono text-on-surface">₱{{ number_format($order->shipping_fee, 2) }}</span>
                        </div>
                        @if($order->discount > 0)
                            <div class="flex justify-between text-emerald-600">
                                <span>Total Discounts Applied:</span>
                                <span class="font-mono">-₱{{ number_format($order->discount, 2) }}</span>
                            </div>
                        @endif
                        <div class="flex justify-between font-bold text-sm text-on-surface pt-2 border-t border-outline-variant/60">
                            <span>Order Total:</span>
                            <span class="text-primary font-black">₱{{ number_format($order->total, 2) }}</span>
                        </div>
                        <div class="flex justify-between pt-2 border-t border-dashed border-outline-variant text-[11px] text-on-surface-variant">
                            <span>Platform Commission (10%):</span>
                            <span>₱{{ number_format($order->subtotal * 0.10, 2) }}</span>
                        </div>
                        <div class="flex justify-between font-semibold text-emerald-600 text-xs">
                            <span>Expected Net Payout:</span>
                            <span>₱{{ number_format($order->subtotal * 0.90, 2) }}</span>
                        </div>
                    </div>
                </div>

                {{-- Status History Timeline --}}
                <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-6 shadow-sm">
                    <h2 class="font-headline-sm text-sm font-bold text-on-surface border-b border-outline-variant pb-3 mb-4">
                        Lifecycle Audit Timeline
                    </h2>
                    <div class="relative pl-6 space-y-6 before:absolute before:left-2 before:top-2 before:bottom-2 before:w-0.5 before:bg-outline-variant">
                        @forelse($order->statusHistory as $event)
                            <div class="relative">
                                <span class="absolute -left-6 top-1 w-2.5 h-2.5 rounded-full bg-primary ring-4 ring-surface"></span>
                                <p class="font-label-md text-xs font-bold text-on-surface capitalize">
                                    {{ str_replace('_', ' ', $event->status) }}
                                </p>
                                <p class="text-[11px] text-on-surface-variant mt-0.5">
                                    {{ $event->changed_at->format('M j, Y · g:i A') }}
                                    @if($event->changedBy)
                                        — by <span class="text-on-surface font-medium">{{ $event->changedBy->full_name }}</span>
                                    @endif
                                </p>
                            </div>
                        @empty
                            <p class="text-xs text-on-surface-variant">No tracking events logged yet.</p>
                        @endforelse
                    </div>
                </div>

            </div>

            <div class="flex flex-col gap-6">

                {{-- Action Panel --}}
                <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-6 shadow-sm space-y-4">
                    <h2 class="font-headline-sm text-sm font-bold text-on-surface border-b border-outline-variant pb-2">
                        Fulfillment Actions
                    </h2>

                    @if($nextStatus)
                        <form method="POST" action="{{ route('seller.orders.advance', $order) }}">
                            @csrf
                            <button type="submit" class="w-full flex items-center justify-center gap-2 bg-primary text-on-primary font-label-md text-xs font-bold py-3 px-4 rounded-xl hover:bg-primary/90 transition-colors shadow-sm">
                                <span class="material-symbols-outlined text-[18px]">check_circle</span>
                                {{ $actionLabel }}
                            </button>
                        </form>
                    @else
                        <div class="p-3.5 rounded-xl bg-surface-container-low text-xs text-on-surface-variant">
                            @if(in_array($order->status, ['picked_up', 'sorting_hub', 'in_transit', 'out_for_delivery']))
                                Parcel handed over to logistics sorting center and courier dispatch.
                            @elseif($order->status === 'delivered' || $order->status === 'completed')
                                Order has been successfully delivered and completed.
                            @elseif($order->status === 'cancelled')
                                Order is cancelled. Inventory stock was restored.
                            @endif
                        </div>
                    @endif

                    {{-- Cancellation Trigger --}}
                    @if($order->canBeCancelledBySeller())
                        <div class="pt-2 border-t border-outline-variant">
                            <button
                                type="button"
                                onclick="document.getElementById('cancelOrderModal').classList.remove('hidden')"
                                class="w-full py-2 px-3 rounded-xl border border-error/40 text-error hover:bg-error/10 text-xs font-bold transition-colors">
                                Cancel Order
                            </button>
                        </div>
                    @endif
                </div>

                {{-- Buyer & Shipping Address Card --}}
                <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-6 shadow-sm space-y-3">
                    <h2 class="font-headline-sm text-sm font-bold text-on-surface border-b border-outline-variant pb-2">
                        Customer & Delivery Address
                    </h2>
                    <div class="text-xs space-y-2">
                        <div>
                            <span class="text-on-surface-variant font-medium">Buyer:</span>
                            <p class="font-bold text-on-surface">{{ $order->buyer->full_name }}</p>
                        </div>
                        <div>
                            <span class="text-on-surface-variant font-medium">Phone Contact:</span>
                            <p class="font-mono text-on-surface">{{ $order->address->recipient_phone ?? $order->buyer->contact_number }}</p>
                        </div>
                        <div>
                            <span class="text-on-surface-variant font-medium">Delivery Address:</span>
                            <p class="text-on-surface">{{ $order->address->full_address }}</p>
                        </div>
                        <div>
                            <span class="text-on-surface-variant font-medium">Payment Mode:</span>
                            <p class="font-bold text-on-surface uppercase">{{ match($order->payment_method) { 'cod' => 'Cash on Delivery (Collect on delivery)', 'ewallet' => 'GCash / Maya (Prepaid)', 'card' => 'Debit / Credit Card (Prepaid)', default => $order->payment_method } }}</p>
                        </div>
                    </div>
                </div>

            </div>

        </div>

    </div>
</div>

{{-- Seller Cancellation Modal --}}
@if($order->canBeCancelledBySeller())
    <div id="cancelOrderModal" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4 hidden">
        <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 max-w-md w-full shadow-xl">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-headline-sm text-headline-sm text-on-surface font-bold text-error">Cancel Order</h3>
                <button type="button" onclick="document.getElementById('cancelOrderModal').classList.add('hidden')" class="p-1 rounded-full text-on-surface-variant hover:bg-surface-container">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>
            <form method="POST" action="{{ route('seller.orders.cancel', $order) }}" class="space-y-4">
                @csrf
                <p class="text-xs text-on-surface-variant">
                    Cancelling will restore all product inventory stock back to your store catalog and notify the buyer.
                </p>
                <div>
                    <label for="cancel_reason" class="block text-xs font-semibold text-on-surface mb-1">
                        Reason for Cancellation <span class="text-error">*</span>
                    </label>
                    <textarea id="cancel_reason" name="reason" rows="3" required placeholder="e.g. Out of stock due to factory defect, buyer requested cancellation" class="w-full rounded-xl border border-outline-variant bg-surface px-3 py-2 text-xs text-on-surface focus:border-primary focus:outline-none"></textarea>
                </div>
                <div class="pt-3 border-t border-outline-variant flex justify-end gap-2">
                    <button type="button" onclick="document.getElementById('cancelOrderModal').classList.add('hidden')" class="px-4 py-2 rounded-full border border-outline-variant text-xs font-semibold text-on-surface hover:bg-surface-container">
                        Keep Order
                    </button>
                    <button type="submit" class="px-5 py-2 rounded-full bg-error text-on-error text-xs font-bold hover:bg-error/90">
                        Confirm Cancellation
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif
@endsection
