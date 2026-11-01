@extends('layouts.app')

@section('title', "Pickup Request {$order->order_number} — Nexora Logistics")

@section('content')
<div class="w-full min-h-screen bg-surface" x-data="{ scheduleModal: false, rejectModal: false }">
    <div class="flex flex-col lg:flex-row w-full">

            <x-logistics-nav active="pickups" />

            <div class="flex-1 min-w-0 p-4 sm:p-6 lg:p-8 bg-surface">
            <div class="w-full max-w-7xl mx-auto space-y-6">

        {{-- Breadcrumb / Back --}}
        <div class="mb-4">
            <a href="{{ route('logistics.pickups.index') }}" class="inline-flex items-center gap-1 font-label-md text-xs text-on-surface-variant hover:text-primary transition-colors">
                <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                <span>Back to Pickup Requests</span>
            </a>
        </div>

        {{-- Alerts --}}
        @if (session('status'))
            <div class="mb-6 bg-primary/10 border border-primary/30 rounded-2xl p-4 flex items-center gap-3">
                <span class="material-symbols-outlined text-primary text-[20px]">check_circle</span>
                <p class="font-body-sm text-xs md:text-sm text-primary font-medium">{{ session('status') }}</p>
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 bg-rose-500/10 border border-rose-500/20 rounded-2xl p-4">
                <p class="font-label-md text-xs font-bold text-rose-700">Please correct the following errors:</p>
                <ul class="list-disc list-inside text-xs text-rose-600 mt-1 space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Header Card --}}
        <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-6 mb-6">
            <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="font-headline-lg text-2xl font-bold text-on-surface">{{ $order->order_number }}</h1>
                        @if ($order->status === 'ready_for_pickup')
                            @if ($order->pickup_courier_id)
                                <span class="px-3 py-1 rounded-full bg-blue-500/10 text-blue-700 text-xs font-semibold">
                                    Rider Dispatched
                                </span>
                            @else
                                <span class="px-3 py-1 rounded-full bg-amber-500/10 text-amber-700 text-xs font-semibold">
                                    Ready for Pickup
                                </span>
                            @endif
                        @elseif ($order->status === 'picked_up')
                            <span class="px-3 py-1 rounded-full bg-emerald-500/10 text-emerald-700 text-xs font-semibold">
                                Picked Up
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
                        <div>Placed {{ $order->placed_at?->format('M d, Y h:i A') }}</div>
                        <div>Payment: <span class="uppercase font-semibold text-on-surface">{{ $order->payment_method }}</span></div>
                    </div>
                </div>

                {{-- Action Buttons --}}
                <div class="flex flex-wrap items-center gap-2">
                    @if ($order->status === 'ready_for_pickup')
                        <button
                            type="button"
                            @click="scheduleModal = true"
                            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-primary text-on-primary font-label-md text-xs font-semibold hover:bg-primary/90 transition-all cursor-pointer">
                            <span class="material-symbols-outlined text-[18px]">two_wheeler</span>
                            <span>{{ $order->pickup_courier_id ? 'Reassign Rider' : 'Assign Pickup Rider' }}</span>
                        </button>

                        <form method="POST" action="{{ route('logistics.pickups.confirm', $order) }}" onsubmit="return confirm('Confirm physical collection of this parcel from the seller?');">
                            @csrf
                            <button
                                type="submit"
                                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-emerald-600 text-white font-label-md text-xs font-semibold hover:bg-emerald-700 transition-all cursor-pointer">
                                <span class="material-symbols-outlined text-[18px]">check_circle</span>
                                <span>Confirm Pickup</span>
                            </button>
                        </form>

                        <button
                            type="button"
                            @click="rejectModal = true"
                            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-surface-container-high text-rose-600 font-label-md text-xs font-semibold hover:bg-rose-500/10 transition-all cursor-pointer">
                            <span class="material-symbols-outlined text-[18px]">cancel</span>
                            <span>Reject Request</span>
                        </button>
                    @elseif ($order->status === 'picked_up')
                        <a
                            href="{{ route('logistics.incoming.index', ['q' => $order->tracking_number ?? $order->order_number]) }}"
                            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-secondary text-on-secondary font-label-md text-xs font-semibold hover:bg-secondary/90 transition-all">
                            <span class="material-symbols-outlined text-[18px]">qr_code_scanner</span>
                            <span>Receive at Sorting Hub</span>
                        </a>
                    @endif
                </div>
            </div>
        </div>

        {{-- 3-Column Info Cards --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            {{-- Seller Origin --}}
            @php
                $seller = $order->primary_seller;
                $sellerAddress = $order->seller_address;
            @endphp
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-5">
                <div class="flex items-center gap-2 mb-3 text-secondary font-bold text-xs uppercase tracking-wider">
                    <span class="material-symbols-outlined text-[18px]">storefront</span>
                    <span>Origin / Seller</span>
                </div>
                <div class="font-headline-sm text-sm font-bold text-on-surface">
                    {{ $seller?->sellerProfile?->business_name ?? $seller?->full_name ?? 'Marketplace Seller' }}
                </div>
                <div class="font-body-sm text-xs text-on-surface-variant mt-1">
                    Contact Person: <span class="text-on-surface font-medium">{{ $seller?->full_name }}</span>
                </div>
                <div class="font-body-sm text-xs text-on-surface-variant mt-0.5">
                    Phone: <span class="text-on-surface font-medium">{{ $seller?->contact_number ?? 'N/A' }}</span>
                </div>
                <div class="mt-3 pt-3 border-t border-outline-variant font-body-sm text-xs">
                    <div class="font-semibold text-on-surface mb-0.5">Pickup Address:</div>
                    <p class="text-on-surface-variant">{{ $sellerAddress?->full_address ?? 'Address on file' }}</p>
                </div>
            </div>

            {{-- Buyer Destination --}}
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-5">
                <div class="flex items-center gap-2 mb-3 text-primary font-bold text-xs uppercase tracking-wider">
                    <span class="material-symbols-outlined text-[18px]">pin_drop</span>
                    <span>Destination / Buyer</span>
                </div>
                <div class="font-headline-sm text-sm font-bold text-on-surface">
                    {{ $order->buyer?->full_name }}
                </div>
                <div class="font-body-sm text-xs text-on-surface-variant mt-1">
                    Phone: <span class="text-on-surface font-medium">{{ $order->buyer?->contact_number ?? 'N/A' }}</span>
                </div>
                <div class="font-body-sm text-xs text-on-surface-variant mt-0.5">
                    Email: <span class="text-on-surface font-medium">{{ $order->buyer?->email }}</span>
                </div>
                <div class="mt-3 pt-3 border-t border-outline-variant font-body-sm text-xs">
                    <div class="font-semibold text-on-surface mb-0.5">Delivery Address:</div>
                    <p class="text-on-surface-variant">{{ $order->address?->full_address }}</p>
                </div>
            </div>

            {{-- Pickup Courier Status --}}
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-5">
                <div class="flex items-center gap-2 mb-3 text-on-surface-variant font-bold text-xs uppercase tracking-wider">
                    <span class="material-symbols-outlined text-[18px]">two_wheeler</span>
                    <span>Pickup Rider</span>
                </div>
                @if ($order->pickupCourier)
                    <div class="font-headline-sm text-sm font-bold text-on-surface">
                        {{ $order->pickupCourier->full_name }}
                    </div>
                    <div class="font-body-sm text-xs text-on-surface-variant mt-1">
                        Contact: <span class="text-on-surface font-medium">{{ $order->pickupCourier->contact_number }}</span>
                    </div>
                    <div class="font-body-sm text-xs text-on-surface-variant mt-0.5">
                        Vehicle: <span class="text-on-surface font-medium">{{ $order->pickupCourier->courierProfile?->vehicle_type ?? 'Motorcycle' }}</span>
                    </div>
                    <div class="font-body-sm text-xs text-on-surface-variant mt-0.5">
                        Plate No: <span class="font-mono text-on-surface font-bold">{{ $order->pickupCourier->courierProfile?->plate_number ?? 'N/A' }}</span>
                    </div>
                @else
                    <div class="text-on-surface-variant text-xs italic py-2">
                        No pickup courier assigned yet.
                    </div>
                    <button
                        type="button"
                        @click="scheduleModal = true"
                        class="mt-2 text-xs font-semibold text-primary hover:underline flex items-center gap-1 cursor-pointer">
                        <span class="material-symbols-outlined text-[16px]">add</span>
                        <span>Assign Rider Now</span>
                    </button>
                @endif
            </div>
        </div>

        {{-- Order Items Table --}}
        <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant overflow-hidden mb-8 shadow-xs">
            <div class="p-4 border-b border-outline-variant bg-surface-container-low/40">
                <h3 class="font-headline-sm text-sm font-bold text-on-surface">Parcel Contents & Summary</h3>
            </div>
            <table class="w-full text-left border-collapse font-body-sm text-xs">
                <thead>
                    <tr class="border-b border-outline-variant text-on-surface-variant font-semibold bg-surface-container-low/20">
                        <th class="px-5 py-3">Item Description</th>
                        <th class="px-5 py-3">Variant</th>
                        <th class="px-5 py-3 text-right">Unit Price</th>
                        <th class="px-5 py-3 text-center">Qty</th>
                        <th class="px-5 py-3 text-right">Line Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant text-on-surface">
                    @foreach ($order->items as $item)
                        <tr>
                            <td class="px-5 py-3.5">
                                <div class="font-semibold text-on-surface">{{ $item->product_name }}</div>
                            </td>
                            <td class="px-5 py-3.5 text-on-surface-variant">
                                @if (!empty($item->variant))
                                    {{ is_array($item->variant) ? collect($item->variant)->reject(fn($v, $k) => in_array(strtolower($k), ['image', 'image_url', 'img', 'row_id', 'has_file']) || str_starts_with((string)$v, '/storage/') || str_starts_with((string)$v, 'http://') || str_starts_with((string)$v, 'https://'))->map(fn($v, $k) => is_string($k) ? "$k: $v" : $v)->implode(', ') : $item->variant }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-right">₱{{ number_format($item->price, 2) }}</td>
                            <td class="px-5 py-3.5 text-center">{{ $item->quantity }}</td>
                            <td class="px-5 py-3.5 text-right font-medium">₱{{ number_format($item->price * $item->quantity, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="border-t border-outline-variant bg-surface-container-low/20 font-semibold text-xs">
                    <tr>
                        <td colspan="4" class="px-5 py-2 text-right text-on-surface-variant">Subtotal:</td>
                        <td class="px-5 py-2 text-right text-on-surface">₱{{ number_format($order->subtotal, 2) }}</td>
                    </tr>
                    <tr>
                        <td colspan="4" class="px-5 py-2 text-right text-on-surface-variant">Shipping Fee:</td>
                        <td class="px-5 py-2 text-right text-on-surface">₱{{ number_format($order->shipping_fee, 2) }}</td>
                    </tr>
                    <tr class="border-t border-outline-variant text-sm">
                        <td colspan="4" class="px-5 py-3 text-right font-bold text-on-surface">Order Total:</td>
                        <td class="px-5 py-3 text-right font-bold text-primary">₱{{ number_format($order->total, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- Order Status History Timeline --}}
        <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-6">
            <h3 class="font-headline-sm text-sm font-bold text-on-surface mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px] text-primary">history</span>
                <span>Audit & Status History</span>
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
    </div>
</div>


    {{-- Schedule Modal --}}
    <div
        x-show="scheduleModal"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
        @keydown.escape.window="scheduleModal = false">
        <div class="bg-surface-container-lowest rounded-2xl max-w-md w-full p-6 shadow-xl border border-outline-variant" @click.outside="scheduleModal = false">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary text-[22px]">two_wheeler</span>
                    <h3 class="font-headline-sm text-base font-bold text-on-surface">Assign Pickup Rider</h3>
                </div>
                <button @click="scheduleModal = false" class="text-on-surface-variant hover:text-on-surface">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>

            <form action="{{ route('logistics.pickups.schedule', $order) }}" method="POST">
                @csrf
                <div class="mb-4">
                    <label class="block font-label-md text-xs font-semibold text-on-surface mb-1">Select Rider</label>
                    <select name="courier_id" required class="w-full px-3 py-2 bg-surface-container-high border-none rounded-xl font-body-sm text-xs text-on-surface focus:ring-2 focus:ring-primary">
                        <option value="">-- Choose Courier --</option>
                        @foreach ($couriers as $courier)
                            <option value="{{ $courier->id }}" {{ $order->pickup_courier_id === $courier->id ? 'selected' : '' }}>
                                {{ $courier->full_name }} ({{ $courier->courierProfile?->vehicle_type ?? 'Motorcycle' }} - {{ $courier->courierProfile?->plate_number ?? 'No Plate' }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-5">
                    <label class="block font-label-md text-xs font-semibold text-on-surface mb-1">Instructions / Notes (Optional)</label>
                    <textarea name="scheduled_notes" rows="2" placeholder="e.g. Call seller upon arrival..." class="w-full px-3 py-2 bg-surface-container-high border-none rounded-xl font-body-sm text-xs text-on-surface focus:ring-2 focus:ring-primary"></textarea>
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" @click="scheduleModal = false" class="px-4 py-2 rounded-xl bg-surface-container-high text-on-surface font-label-md text-xs font-semibold hover:bg-surface-container-highest transition-colors">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-primary text-on-primary font-label-md text-xs font-semibold hover:bg-primary/90 transition-all">
                        Dispatch Rider
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Reject Modal --}}
    <div
        x-show="rejectModal"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
        @keydown.escape.window="rejectModal = false">
        <div class="bg-surface-container-lowest rounded-2xl max-w-md w-full p-6 shadow-xl border border-outline-variant" @click.outside="rejectModal = false">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2 text-rose-600">
                    <span class="material-symbols-outlined text-[22px]">cancel</span>
                    <h3 class="font-headline-sm text-base font-bold">Reject Pickup Request</h3>
                </div>
                <button @click="rejectModal = false" class="text-on-surface-variant hover:text-on-surface">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>

            <p class="font-body-sm text-xs text-on-surface-variant mb-4">
                Rejecting returns the order to <strong class="text-on-surface">Preparing</strong> status and notifies the seller to rectify the issue.
            </p>

            <form action="{{ route('logistics.pickups.reject', $order) }}" method="POST">
                @csrf
                <div class="mb-5">
                    <label class="block font-label-md text-xs font-semibold text-on-surface mb-1">Rejection Reason</label>
                    <textarea name="reason" rows="3" required placeholder="Specify why the parcel cannot be picked up (e.g. improper packaging, seller unavailable)..." class="w-full px-3 py-2 bg-surface-container-high border-none rounded-xl font-body-sm text-xs text-on-surface focus:ring-2 focus:ring-primary"></textarea>
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" @click="rejectModal = false" class="px-4 py-2 rounded-xl bg-surface-container-high text-on-surface font-label-md text-xs font-semibold hover:bg-surface-container-highest transition-colors">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-rose-600 text-white font-label-md text-xs font-semibold hover:bg-rose-700 transition-all">
                        Confirm Rejection
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
