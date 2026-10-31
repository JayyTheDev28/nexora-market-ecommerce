@extends('layouts.app')

@section('title', "Delivery Inspection {$order->order_number} — Nexora Logistics")

@section('content')
<div class="w-full bg-surface py-8 min-h-[75vh]" x-data="{ resolveModal: false }">
    <div class="max-w-none px-margin-mobile md:px-margin-desktop">

        <x-logistics-nav active="deliveries" />

        {{-- Breadcrumb --}}
        <div class="mb-4">
            <a href="{{ route('logistics.deliveries.index') }}" class="inline-flex items-center gap-1 font-label-md text-xs text-on-surface-variant hover:text-primary transition-colors">
                <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                <span>Back to Delivery Monitoring</span>
            </a>
        </div>

        {{-- Alerts --}}
        @if (session('status'))
            <div class="mb-6 bg-primary/10 border border-primary/30 rounded-2xl p-4 flex items-center gap-3">
                <span class="material-symbols-outlined text-primary text-[20px]">check_circle</span>
                <p class="font-body-sm text-xs md:text-sm text-primary font-medium">{{ session('status') }}</p>
            </div>
        @endif

        {{-- Header Card --}}
        <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-6 mb-6">
            <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="font-headline-lg text-2xl font-bold text-on-surface">{{ $order->order_number }}</h1>
                        @if ($order->status === 'assigned_to_rider')
                            <span class="px-3 py-1 rounded-full bg-blue-500/10 text-blue-700 text-xs font-semibold">
                                Assigned to Rider
                            </span>
                        @elseif ($order->status === 'out_for_delivery')
                            <span class="px-3 py-1 rounded-full bg-amber-500/10 text-amber-700 text-xs font-semibold">
                                Out for Delivery
                            </span>
                        @elseif (in_array($order->status, ['delivered', 'completed']))
                            <span class="px-3 py-1 rounded-full bg-emerald-500/10 text-emerald-700 text-xs font-semibold">
                                Delivered
                            </span>
                        @elseif ($order->status === 'delivery_failed')
                            <span class="px-3 py-1 rounded-full bg-rose-500/10 text-rose-700 text-xs font-semibold">
                                Delivery Failed
                            </span>
                        @elseif ($order->status === 'returned')
                            <span class="px-3 py-1 rounded-full bg-surface-container-high text-on-surface text-xs font-semibold">
                                Returned to Seller
                            </span>
                        @else
                            <span class="px-3 py-1 rounded-full bg-surface-container-high text-on-surface text-xs font-semibold">
                                {{ ucwords(str_replace('_', ' ', $order->status)) }}
                            </span>
                        @endif
                    </div>
                    <div class="flex flex-wrap items-center gap-4 mt-2 font-body-sm text-xs text-on-surface-variant">
                        @if ($order->tracking_number)
                            <div class="flex items-center gap-1 font-mono font-medium text-primary">
                                <span class="material-symbols-outlined text-[16px]">barcode</span>
                                <span>{{ $order->tracking_number }}</span>
                            </div>
                        @endif
                        <div>Zone: <strong class="text-on-surface">{{ $order->deliveryArea?->name ?? 'Unassigned' }}</strong></div>
                        <div>Total: <strong class="text-on-surface">₱{{ number_format($order->total, 2) }}</strong> ({{ strtoupper($order->payment_method) }})</div>
                    </div>
                </div>

                {{-- Action Buttons --}}
                <div class="flex items-center gap-2">
                    @if ($order->status === 'delivery_failed')
                        <button
                            type="button"
                            @click="resolveModal = true"
                            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-rose-600 text-white font-label-md text-xs font-semibold hover:bg-rose-700 transition-all cursor-pointer">
                            <span class="material-symbols-outlined text-[18px]">healing</span>
                            <span>Resolve Delivery Exception</span>
                        </button>
                    @endif
                </div>
            </div>
        </div>

        {{-- 3-Column Info Cards --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            {{-- Delivery Rider Details --}}
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-5">
                <div class="flex items-center gap-2 mb-3 text-primary font-bold text-xs uppercase tracking-wider">
                    <span class="material-symbols-outlined text-[18px]">two_wheeler</span>
                    <span>Assigned Courier</span>
                </div>
                @if ($order->deliveryCourier)
                    <div class="font-headline-sm text-sm font-bold text-on-surface">
                        {{ $order->deliveryCourier->full_name }}
                    </div>
                    <div class="font-body-sm text-xs text-on-surface-variant mt-1">
                        Contact: <span class="text-on-surface font-medium">{{ $order->deliveryCourier->contact_number }}</span>
                    </div>
                    <div class="font-body-sm text-xs text-on-surface-variant mt-0.5">
                        Vehicle: <span class="text-on-surface font-medium">{{ $order->deliveryCourier->courierProfile?->vehicle_type ?? 'Motorcycle' }}</span>
                    </div>
                    <div class="font-body-sm text-xs text-on-surface-variant mt-0.5">
                        Plate No: <span class="font-mono text-on-surface font-bold">{{ $order->deliveryCourier->courierProfile?->plate_number ?? 'N/A' }}</span>
                    </div>
                @else
                    <div class="text-xs text-on-surface-variant italic py-2">No delivery courier assigned.</div>
                @endif
            </div>

            {{-- Buyer Destination --}}
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-5">
                <div class="flex items-center gap-2 mb-3 text-secondary font-bold text-xs uppercase tracking-wider">
                    <span class="material-symbols-outlined text-[18px]">pin_drop</span>
                    <span>Buyer Destination</span>
                </div>
                <div class="font-headline-sm text-sm font-bold text-on-surface">
                    {{ $order->buyer?->full_name }}
                </div>
                <div class="font-body-sm text-xs text-on-surface-variant mt-1">
                    Phone: <span class="text-on-surface font-medium">{{ $order->buyer?->contact_number ?? 'N/A' }}</span>
                </div>
                <div class="mt-3 pt-3 border-t border-outline-variant font-body-sm text-xs">
                    <div class="font-semibold text-on-surface mb-0.5">Address:</div>
                    <p class="text-on-surface-variant">{{ $order->address?->full_address }}</p>
                </div>
            </div>

            {{-- Seller Origin --}}
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-5">
                <div class="flex items-center gap-2 mb-3 text-on-surface-variant font-bold text-xs uppercase tracking-wider">
                    <span class="material-symbols-outlined text-[18px]">storefront</span>
                    <span>Merchant Origin</span>
                </div>
                <div class="font-headline-sm text-sm font-bold text-on-surface">
                    {{ $order->primary_seller?->sellerProfile?->business_name ?? $order->primary_seller?->full_name ?? 'Seller' }}
                </div>
                <div class="font-body-sm text-xs text-on-surface-variant mt-1">
                    Contact: <span class="text-on-surface font-medium">{{ $order->primary_seller?->contact_number }}</span>
                </div>
                <div class="mt-3 pt-3 border-t border-outline-variant font-body-sm text-xs">
                    <div class="font-semibold text-on-surface mb-0.5">Seller Address:</div>
                    <p class="text-on-surface-variant">{{ $order->seller_address?->full_address ?? 'Origin Hub' }}</p>
                </div>
            </div>
        </div>

        {{-- Delivery Attempts Section (if any recorded) --}}
        @if ($order->deliveryAttempts->count() > 0)
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-6 mb-8">
                <h3 class="font-headline-sm text-sm font-bold text-on-surface mb-4 flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px] text-rose-600">report</span>
                    <span>Courier Delivery Attempt History</span>
                </h3>

                <div class="space-y-3">
                    @foreach ($order->deliveryAttempts as $attempt)
                        <div class="p-3.5 rounded-xl border border-outline-variant/80 bg-surface-container-low/40 flex items-start justify-between gap-4">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $attempt->result === 'delivered' ? 'bg-emerald-500/10 text-emerald-700' : 'bg-rose-500/10 text-rose-700' }}">
                                        {{ $attempt->result }}
                                    </span>
                                    <span class="font-body-xs text-xs text-on-surface-variant">
                                        {{ $attempt->attempted_at?->format('M d, Y h:i A') }}
                                    </span>
                                    <span class="text-xs text-on-surface-variant">· by {{ $attempt->courier?->full_name }}</span>
                                </div>
                                @if ($attempt->failure_reason)
                                    <div class="font-body-sm text-xs text-rose-700 font-semibold mt-1">
                                        Reason: {{ $attempt->failure_reason }}
                                    </div>
                                @endif
                                @if ($attempt->notes)
                                    <p class="font-body-sm text-xs text-on-surface-variant mt-1">
                                        {{ $attempt->notes }}
                                    </p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Lifecycle History Timeline --}}
        <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-6 mb-8">
            <h3 class="font-headline-sm text-sm font-bold text-on-surface mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px] text-primary">timeline</span>
                <span>Full Order Lifecycle & Tracking Events</span>
            </h3>

            <div class="relative pl-6 border-l-2 border-outline-variant space-y-6">
                @forelse ($order->statusHistory as $history)
                    <div class="relative group">
                        <span class="absolute -left-[31px] top-0.5 w-3.5 h-3.5 rounded-full bg-primary ring-4 ring-surface"></span>
                        <div class="font-label-md text-xs font-bold text-on-surface">
                            {{ ucwords(str_replace('_', ' ', $history->status)) }}
                        </div>
                        <div class="font-body-xs text-[11px] text-on-surface-variant mt-0.5">
                            {{ $history->changed_at->format('M d, Y h:i:s A') }}
                            @if ($history->changedBy)
                                · by <span class="text-on-surface font-medium">{{ $history->changedBy->full_name }} ({{ $history->changedBy->role }})</span>
                            @endif
                        </div>
                        @if ($history->note)
                            <p class="font-body-sm text-xs text-on-surface-variant bg-surface-container-high/60 rounded-xl p-2.5 mt-2">
                                {{ $history->note }}
                            </p>
                        @endif
                    </div>
                @empty
                    <p class="text-xs text-on-surface-variant italic">No status history recorded yet.</p>
                @endforelse
            </div>
        </div>

    </div>

    {{-- Resolve Exception Modal --}}
    <div
        x-show="resolveModal"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
        @keydown.escape.window="resolveModal = false">
        <div class="bg-surface-container-lowest rounded-2xl max-w-md w-full p-6 shadow-xl border border-outline-variant" @click.outside="resolveModal = false">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2 text-rose-600">
                    <span class="material-symbols-outlined text-[22px]">healing</span>
                    <h3 class="font-headline-sm text-base font-bold">Resolve Delivery Exception</h3>
                </div>
                <button @click="resolveModal = false" class="text-on-surface-variant hover:text-on-surface">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>

            <form action="{{ route('logistics.deliveries.resolve', $order) }}" method="POST">
                @csrf
                <div class="space-y-3 mb-4">
                    <label class="flex items-start gap-3 p-3 rounded-xl border border-outline-variant cursor-pointer hover:bg-surface-container-low transition-colors">
                        <input type="radio" name="action" value="reschedule" checked class="mt-1 text-primary focus:ring-primary">
                        <div>
                            <div class="font-label-md text-xs font-bold text-on-surface">Reschedule Delivery Attempt</div>
                            <div class="font-body-xs text-[11px] text-on-surface-variant mt-0.5">Reset order to 'Assigned to Rider' for re-attempt.</div>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 p-3 rounded-xl border border-outline-variant cursor-pointer hover:bg-surface-container-low transition-colors">
                        <input type="radio" name="action" value="return_to_seller" class="mt-1 text-rose-600 focus:ring-rose-500">
                        <div>
                            <div class="font-label-md text-xs font-bold text-rose-600">Return to Seller (RTS)</div>
                            <div class="font-body-xs text-[11px] text-on-surface-variant mt-0.5">Close delivery run and return package to the seller.</div>
                        </div>
                    </label>
                </div>

                <div class="mb-5">
                    <label class="block font-label-md text-xs font-semibold text-on-surface mb-1">Resolution Reason / Notes <span class="text-rose-600">*</span></label>
                    <textarea name="notes" rows="3" required placeholder="State reason for resolution..." class="w-full px-3 py-2 bg-surface-container-high border-none rounded-xl font-body-sm text-xs text-on-surface focus:ring-2 focus:ring-primary"></textarea>
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" @click="resolveModal = false" class="px-4 py-2 rounded-xl bg-surface-container-high text-on-surface font-label-md text-xs font-semibold hover:bg-surface-container-highest transition-colors">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-primary text-on-primary font-label-md text-xs font-semibold hover:bg-primary/90 transition-all">
                        Apply Resolution
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
