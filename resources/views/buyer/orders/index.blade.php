@extends('layouts.app')

@section('title', 'My Orders — Nexora Market')

@section('content')
<div
    x-data="ordersPage({{ $orders->map(fn ($order) => [
        'id' => $order->id,
        'orderNumber' => $order->order_number,
        'placedAt' => $order->placed_at->timestamp * 1000,
        'status' => $order->status,
        'total' => (float) $order->total,
        'paymentMethod' => match ($order->payment_method) {
            'cod' => 'Cash on Delivery',
            'ewallet' => 'E-Wallet',
            'card' => 'Credit / Debit Card',
            default => $order->payment_method,
        },
        'hasFeedback' => (bool) $order->review,
        'hasDispute' => (bool) $order->dispute,
        'lines' => $order->items->map(fn ($item) => [
            'name' => $item->product_name,
            'qty' => $item->quantity,
            'price' => (float) $item->price,
        ])->values(),
        'confirmReceiptUrl' => route('buyer.orders.confirm-receipt', $order),
        'feedbackUrl' => route('buyer.orders.feedback', $order),
        'disputeUrl' => route('buyer.orders.dispute', $order),
    ])->values()->toJson() }})"
    class="w-full bg-surface py-6 md:py-8 min-h-[75vh]">
    <div class="max-w-none px-4 md:px-8">

        {{-- Sub-Navigation --}}
        <x-buyer-nav active="orders" />

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="font-headline-lg text-2xl md:text-3xl font-bold tracking-tight text-on-surface">My Orders</h1>
                <p class="font-body-md text-xs md:text-sm text-on-surface-variant mt-1">Track delivery statuses, confirm received packages, and manage order reviews.</p>
            </div>
            <a href="{{ url('/catalog') }}" class="self-start sm:self-auto inline-flex items-center justify-center px-4 py-2 rounded-full bg-primary text-on-primary font-label-md text-xs font-semibold shadow-xs hover:bg-primary/90 transition-all">
                Continue Shopping
            </a>
        </div>

        @if (session('status'))
            <div class="mb-6 bg-primary/10 border border-primary/20 rounded-2xl p-4 flex items-center justify-between">
                <p class="font-body-sm text-xs md:text-sm font-medium text-primary">{{ session('status') }}</p>
            </div>
        @endif

        {{-- Status Filter Tabs (Scrollable Segmented Control) --}}
        <div class="flex items-center gap-1.5 p-1 bg-surface-container-low rounded-2xl border border-outline-variant/60 w-full sm:w-fit mb-6 overflow-x-auto">
            <template x-for="tab in tabs" :key="tab.key">
                <button
                    type="button"
                    @click="activeTab = tab.key"
                    class="flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold whitespace-nowrap transition-all duration-200 cursor-pointer"
                    :class="activeTab === tab.key ? 'bg-surface-container-lowest text-primary shadow-xs font-bold' : 'text-on-surface-variant hover:text-on-surface hover:bg-surface-container/50'">
                    <span x-text="tab.label"></span>
                    <span
                        class="px-2 py-0.5 rounded-full text-[10px] font-bold transition-colors"
                        :class="activeTab === tab.key ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface-variant'"
                        x-text="ordersInTab(tab.key).length">
                    </span>
                </button>
            </template>
        </div>

        {{-- Empty State --}}
        <div x-show="ordersInTab(activeTab).length === 0" x-cloak class="flex flex-col items-center justify-center py-16 px-4 text-center bg-surface-container-lowest rounded-3xl border border-outline-variant">
            <div class="w-14 h-14 rounded-2xl bg-surface-container flex items-center justify-center text-outline mb-3">
                <span class="material-symbols-outlined text-[28px]">inventory_2</span>
            </div>
            <p class="font-headline-sm text-base font-bold text-on-surface">No orders found</p>
            <p class="font-body-sm text-xs text-on-surface-variant max-w-sm mt-1">There are currently no orders in this status category.</p>
            <a href="{{ url('/catalog') }}" class="mt-4 inline-flex items-center justify-center bg-primary text-on-primary font-label-md text-xs font-semibold px-5 py-2.5 rounded-full hover:bg-primary/90 transition-colors">
                Explore Marketplace
            </a>
        </div>

        {{-- Orders List --}}
        <div class="flex flex-col gap-5">
            <template x-for="order in ordersInTab(activeTab)" :key="order.id">
                <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant overflow-hidden shadow-xs">

                    {{-- Order Header --}}
                    <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 bg-surface-container-low/60 border-b border-outline-variant">
                        <div class="flex items-center gap-3">
                            <span class="font-mono text-xs md:text-sm font-bold text-on-surface" x-text="order.orderNumber"></span>
                            <span class="text-outline-variant">•</span>
                            <span class="text-xs text-on-surface-variant" x-text="formatDate(order.placedAt)"></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-surface-container text-on-surface-variant" x-text="order.paymentMethod"></span>
                        </div>
                    </div>

                    <div class="p-5 flex flex-col gap-6">

                        {{-- Responsive Timeline --}}
                        <div x-show="order.status !== 'delivery_failed' && order.status !== 'returned'" class="w-full">
                            {{-- Desktop Tracker (md and up) --}}
                            <div class="hidden md:flex items-center justify-between">
                                <template x-for="(step, i) in timelineSteps" :key="step.key">
                                    <div class="flex items-center flex-1 last:flex-none">
                                        <div class="flex flex-col items-center gap-1.5 flex-shrink-0" style="width: 100px;">
                                            <div
                                                class="w-7 h-7 rounded-full flex items-center justify-center text-[11px] font-bold transition-colors"
                                                :class="stepIndex(order.status) >= i ? 'bg-primary text-on-primary ring-4 ring-primary/10' : 'bg-surface-container text-outline'">
                                                <span x-show="stepIndex(order.status) > i">✓</span>
                                                <span x-show="stepIndex(order.status) <= i" x-text="i + 1"></span>
                                            </div>
                                            <span
                                                class="text-[11px] font-medium text-center leading-tight whitespace-nowrap"
                                                :class="stepIndex(order.status) >= i ? 'text-on-surface font-semibold' : 'text-on-surface-variant'"
                                                x-text="step.label">
                                            </span>
                                        </div>
                                        <div
                                            class="flex-1 h-0.5 mx-2 rounded-full transition-colors"
                                            x-show="i < timelineSteps.length - 1"
                                            :class="stepIndex(order.status) > i ? 'bg-primary' : 'bg-surface-container'">
                                        </div>
                                    </div>
                                </template>
                            </div>

                            {{-- Mobile Tracker (horizontal scrolling with snap) --}}
                            <div class="flex md:hidden overflow-x-auto pb-2 -mx-2 px-2">
                                <div class="flex items-center min-w-max">
                                    <template x-for="(step, i) in timelineSteps" :key="'mob-' + step.key">
                                        <div class="flex items-center">
                                            <div class="flex flex-col items-center gap-1" style="width: 80px;">
                                                <div
                                                    class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold"
                                                    :class="stepIndex(order.status) >= i ? 'bg-primary text-on-primary' : 'bg-surface-container text-outline'">
                                                    <span x-show="stepIndex(order.status) > i">✓</span>
                                                    <span x-show="stepIndex(order.status) <= i" x-text="i + 1"></span>
                                                </div>
                                                <span
                                                    class="text-[10px] text-center leading-tight truncate max-w-[76px]"
                                                    :class="stepIndex(order.status) >= i ? 'text-on-surface font-bold' : 'text-on-surface-variant'"
                                                    x-text="step.label">
                                                </span>
                                            </div>
                                            <div
                                                class="w-6 h-0.5"
                                                x-show="i < timelineSteps.length - 1"
                                                :class="stepIndex(order.status) > i ? 'bg-primary' : 'bg-surface-container'">
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>

                        {{-- Delivery Failed / Returned Notice --}}
                        <div x-show="order.status === 'delivery_failed' || order.status === 'returned'" class="p-3.5 bg-rose-500/10 border border-rose-500/20 rounded-xl text-xs text-rose-700 leading-relaxed font-medium">
                            <span x-text="order.status === 'delivery_failed' ? 'The delivery attempt was unsuccessful. A re-delivery attempt is being scheduled by the courier.' : 'This order has been returned to the vendor.'"></span>
                        </div>

                        {{-- Order Line Items --}}
                        <div class="flex flex-col gap-2.5 pt-2 border-t border-outline-variant/60">
                            <template x-for="(line, i) in order.lines" :key="i">
                                <div class="flex items-center justify-between py-1 text-xs">
                                    <div class="flex-1 min-w-0 pr-4">
                                        <p class="font-medium text-on-surface truncate" x-text="line.name"></p>
                                        <p class="text-[11px] text-on-surface-variant mt-0.5">Quantity: <span class="font-semibold text-on-surface" x-text="line.qty"></span></p>
                                    </div>
                                    <span class="font-bold text-on-surface whitespace-nowrap">
                                        ₱<span x-text="(line.price * line.qty).toLocaleString('en-PH', {minimumFractionDigits: 2})"></span>
                                    </span>
                                </div>
                            </template>
                        </div>

                        {{-- Total and Actions Row --}}
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pt-4 border-t border-outline-variant">
                            <div>
                                <span class="text-xs text-on-surface-variant">Order Total</span>
                                <p class="text-lg md:text-xl font-bold text-primary">
                                    ₱<span x-text="order.total.toLocaleString('en-PH', {minimumFractionDigits: 2})"></span>
                                </p>
                            </div>

                            {{-- Action Buttons --}}
                            <div class="flex flex-wrap items-center gap-2.5" x-show="order.status === 'out_for_delivery' || order.status === 'delivered' || order.status === 'completed'">
                                {{-- Confirm Receipt --}}
                                <form method="POST" :action="order.confirmReceiptUrl" x-show="order.status === 'out_for_delivery' || order.status === 'delivered'">
                                    @csrf
                                    <button
                                        type="submit"
                                        class="px-5 py-2 rounded-full bg-emerald-600 text-white font-label-md text-xs font-semibold shadow-xs hover:bg-emerald-700 transition-colors">
                                        Confirm Receipt
                                    </button>
                                </form>

                                {{-- Completed Actions: Feedback & Dispute --}}
                                <template x-if="order.status === 'completed'">
                                    <div class="flex flex-wrap items-center gap-2.5">
                                        {{-- Feedback Button / Status --}}
                                        <button
                                            type="button"
                                            @click="openFeedback(order)"
                                            x-show="!order.hasFeedback"
                                            class="px-4 py-2 rounded-full border border-outline-variant text-on-surface font-label-md text-xs font-semibold hover:border-primary hover:text-primary transition-colors">
                                            Leave Feedback
                                        </button>
                                        <span x-show="order.hasFeedback" class="px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-600">
                                            Feedback Submitted
                                        </span>

                                        {{-- Dispute Button / Status --}}
                                        <button
                                            type="button"
                                            @click="openDispute(order)"
                                            x-show="!order.hasDispute"
                                            class="px-4 py-2 rounded-full border border-outline-variant text-on-surface-variant font-label-md text-xs font-semibold hover:text-rose-600 hover:border-rose-400 transition-colors">
                                            Raise Dispute
                                        </button>
                                        <span x-show="order.hasDispute" class="px-3 py-1 rounded-full text-xs font-semibold bg-rose-500/10 text-rose-600">
                                            Dispute Raised
                                        </span>
                                    </div>
                                </template>
                            </div>

                        </div>

                    </div>
                </div>
            </template>
        </div>

    </div>

    {{-- Feedback Modal --}}
    <div
        x-show="feedbackModal.open"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-inverse-surface/60 backdrop-blur-sm">

        <div
            @click.outside="feedbackModal.open = false"
            x-show="feedbackModal.open"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            class="relative max-w-sm w-full bg-surface-container-lowest rounded-3xl shadow-2xl p-6 flex flex-col gap-4 border border-outline-variant">

            <div>
                <h3 class="font-headline-sm text-base font-bold text-on-surface">Leave Order Feedback</h3>
                <p class="text-xs text-on-surface-variant mt-0.5">Rate your overall experience with this purchase.</p>
            </div>

            <form method="POST" :action="feedbackModal.url" class="flex flex-col gap-4">
                @csrf

                {{-- Interactive Star Rating --}}
                <div class="flex items-center justify-center gap-2 py-2">
                    <template x-for="star in [1,2,3,4,5]" :key="star">
                        <button
                            type="button"
                            @click="feedbackModal.rating = star"
                            class="p-1 hover:scale-110 transition-transform">
                            <span
                                class="material-symbols-outlined text-[28px]"
                                :class="star <= feedbackModal.rating ? 'text-amber-500' : 'text-outline'"
                                :style="star <= feedbackModal.rating ? `font-variation-settings: 'FILL' 1` : ''">
                                star
                            </span>
                        </button>
                    </template>
                </div>
                <input type="hidden" name="rating" :value="feedbackModal.rating">

                <textarea
                    name="comment"
                    rows="3"
                    placeholder="Write a brief comment about product quality or delivery..."
                    class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary"></textarea>

                <div class="flex gap-2.5 pt-1">
                    <button
                        type="button"
                        @click="feedbackModal.open = false"
                        class="flex-1 text-xs font-semibold text-on-surface-variant px-4 py-2.5 rounded-full hover:bg-surface-container transition-colors">
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="feedbackModal.rating === 0"
                        class="flex-1 bg-primary text-on-primary text-xs font-semibold px-4 py-2.5 rounded-full hover:bg-primary/90 disabled:opacity-40 disabled:cursor-not-allowed transition-all">
                        Submit Review
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Dispute Modal --}}
    <div
        x-show="disputeModal.open"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-inverse-surface/60 backdrop-blur-sm">

        <div
            @click.outside="disputeModal.open = false"
            x-show="disputeModal.open"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            class="relative max-w-sm w-full bg-surface-container-lowest rounded-3xl shadow-2xl p-6 flex flex-col gap-4 border border-outline-variant">

            <div>
                <h3 class="font-headline-sm text-base font-bold text-on-surface">Raise a Dispute</h3>
                <p class="text-xs text-on-surface-variant mt-0.5">Submit an issue for review by Nexora marketplace administrators.</p>
            </div>

            <form method="POST" :action="disputeModal.url" class="flex flex-col gap-4">
                @csrf
                <div class="flex flex-col gap-1.5">
                    <label class="text-[11px] font-semibold text-on-surface-variant">Reason for dispute</label>
                    <select
                        name="reason"
                        required
                        class="rounded-xl border border-outline-variant px-3 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                        <option value="" disabled selected>Select a reason</option>
                        <option>Item not as described</option>
                        <option>Item damaged or defective</option>
                        <option>Wrong item received</option>
                        <option>Item never arrived</option>
                        <option>Other</option>
                    </select>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label class="text-[11px] font-semibold text-on-surface-variant">Details</label>
                    <textarea
                        name="details"
                        rows="3"
                        placeholder="Please describe the issue clearly..."
                        class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary"></textarea>
                </div>

                <div class="flex gap-2.5 pt-1">
                    <button
                        type="button"
                        @click="disputeModal.open = false"
                        class="flex-1 text-xs font-semibold text-on-surface-variant px-4 py-2.5 rounded-full hover:bg-surface-container transition-colors">
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="flex-1 bg-rose-600 text-white text-xs font-semibold px-4 py-2.5 rounded-full hover:bg-rose-700 transition-colors">
                        Submit Dispute
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

@push('scripts')
<script>
    function ordersPage(orders) {
        return {
            orders,

            tabs: [
                { key: 'to_ship', label: 'To Ship' },
                { key: 'in_transit', label: 'In Transit' },
                { key: 'out_for_delivery', label: 'Out for Delivery' },
                { key: 'completed', label: 'Completed' },
            ],
            activeTab: 'to_ship',

            statusGroups: {
                to_ship: ['placed', 'confirmed', 'preparing', 'ready_for_pickup', 'picked_up', 'delivery_failed', 'returned'],
                in_transit: ['at_sorting_center', 'sorted', 'assigned_to_rider'],
                out_for_delivery: ['out_for_delivery', 'delivered'],
                completed: ['completed'],
            },

            ordersInTab(tabKey) {
                const statuses = this.statusGroups[tabKey] || [];
                return this.orders.filter((o) => statuses.includes(o.status));
            },

            timelineSteps: [
                { key: 'placed', label: 'Order Placed' },
                { key: 'preparing', label: 'Preparing' },
                { key: 'in_transit', label: 'In Transit' },
                { key: 'out_for_delivery', label: 'Out for Delivery' },
                { key: 'delivered', label: 'Delivered' },
            ],

            stepIndex(status) {
                const map = {
                    placed: 0,
                    confirmed: 1, preparing: 1, ready_for_pickup: 1, picked_up: 1,
                    at_sorting_center: 2, sorted: 2, assigned_to_rider: 2,
                    out_for_delivery: 3,
                    delivered: 4, completed: 4,
                };
                return map[status] ?? 0;
            },

            formatDate(timestamp) {
                return new Date(timestamp).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
            },

            feedbackModal: { open: false, url: '', rating: 0 },
            openFeedback(order) {
                this.feedbackModal = { open: true, url: order.feedbackUrl, rating: 0 };
            },

            disputeModal: { open: false, url: '' },
            openDispute(order) {
                this.disputeModal = { open: true, url: order.disputeUrl };
            },
        };
    }
</script>
@endpush

@endsection
