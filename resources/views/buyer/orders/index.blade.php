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
    class="w-full bg-surface py-8 min-h-[70vh]">
    <div class="max-w-none px-margin-mobile md:px-margin-desktop">

        <h1 class="font-headline-lg text-headline-lg text-on-surface mb-6">My Orders</h1>

        @if (session('status'))
            <div class="mb-6 bg-primary/10 border border-primary/30 rounded-2xl p-4">
                <p class="font-body-sm text-body-sm text-primary">{{ session('status') }}</p>
            </div>
        @endif

        {{-- Status tabs --}}
        <div class="flex gap-2 overflow-x-auto pb-1 mb-6 border-b border-outline-variant">
            <template x-for="tab in tabs" :key="tab.key">
                <button
                    type="button"
                    @click="activeTab = tab.key"
                    class="flex items-center gap-2 px-4 py-3 font-label-md text-label-md whitespace-nowrap border-b-2 -mb-px transition-colors"
                    :class="activeTab === tab.key ? 'border-primary text-primary' : 'border-transparent text-on-surface-variant hover:text-on-surface'">
                    <span x-text="tab.label"></span>
                    <span
                        class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                        :class="activeTab === tab.key ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface-variant'"
                        x-text="ordersInTab(tab.key).length">
                    </span>
                </button>
            </template>
        </div>

        {{-- Empty state for this tab --}}
        <div x-show="ordersInTab(activeTab).length === 0" x-cloak class="flex flex-col items-center justify-center py-20 text-center gap-3">
            <span class="material-symbols-outlined text-[40px] text-outline">inventory_2</span>
            <p class="font-headline-sm text-headline-sm text-on-surface">No orders here yet</p>
            <p class="font-body-sm text-body-sm text-on-surface-variant">Orders you place will show up here once they reach this stage.</p>
        </div>

        {{-- Orders list --}}
        <div class="flex flex-col gap-5">
            <template x-for="order in ordersInTab(activeTab)" :key="order.id">
                <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant overflow-hidden">

                    {{-- Header --}}
                    <div class="flex flex-wrap items-center justify-between gap-2 px-5 py-4 bg-surface-container-low border-b border-outline-variant">
                        <div class="flex items-center gap-3">
                            <span class="font-label-md text-label-md text-on-surface" x-text="order.orderNumber"></span>
                            <span class="font-body-sm text-body-sm text-on-surface-variant" x-text="formatDate(order.placedAt)"></span>
                        </div>
                        <span class="font-body-sm text-body-sm text-on-surface-variant" x-text="order.paymentMethod"></span>
                    </div>

                    <div class="p-5 flex flex-col gap-5">

                        {{-- Timeline --}}
                        <div class="flex items-center" x-show="order.status !== 'delivery_failed' && order.status !== 'returned'">
                            <template x-for="(step, i) in timelineSteps" :key="step.key">
                                <div class="flex items-center flex-1 last:flex-none">
                                    <div class="flex flex-col items-center gap-1 flex-shrink-0" style="width: 88px;">
                                        <div
                                            class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0"
                                            :class="stepIndex(order.status) >= i ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface-variant'">
                                            <span class="material-symbols-outlined text-[16px]" x-text="step.icon"></span>
                                        </div>
                                        <span class="font-label-sm text-label-sm text-center leading-tight" :class="stepIndex(order.status) >= i ? 'text-on-surface' : 'text-on-surface-variant'" x-text="step.label"></span>
                                    </div>
                                    <div
                                        class="flex-1 h-0.5 mx-1"
                                        x-show="i < timelineSteps.length - 1"
                                        :class="stepIndex(order.status) > i ? 'bg-primary' : 'bg-surface-container'">
                                    </div>
                                </div>
                            </template>
                        </div>

                        {{-- Failed / returned notice --}}
                        <div x-show="order.status === 'delivery_failed' || order.status === 'returned'" class="flex items-center gap-2 bg-error-container rounded-xl p-3">
                            <span class="material-symbols-outlined text-[18px] text-on-error-container">error</span>
                            <span class="font-body-sm text-body-sm text-on-error-container" x-text="order.status === 'delivery_failed' ? 'The last delivery attempt failed. A redelivery will be scheduled.' : 'This order was returned to the seller.'"></span>
                        </div>

                        {{-- Line items --}}
                        <div class="flex flex-col gap-3">
                            <template x-for="(line, i) in order.lines" :key="i">
                                <div class="flex items-center justify-between">
                                    <div class="flex-1 min-w-0">
                                        <p class="font-body-sm text-body-sm text-on-surface truncate" x-text="line.name"></p>
                                        <p class="font-body-sm text-body-sm text-on-surface-variant">Qty <span x-text="line.qty"></span></p>
                                    </div>
                                    <span class="font-label-md text-label-md text-on-surface flex-shrink-0">₱<span x-text="(line.price * line.qty).toLocaleString('en-PH', {minimumFractionDigits: 2})"></span></span>
                                </div>
                            </template>
                        </div>

                        <div class="flex items-center justify-between pt-3 border-t border-outline-variant">
                            <span class="font-headline-sm text-headline-sm text-on-surface">Total</span>
                            <span class="font-headline-md text-headline-md text-primary">₱<span x-text="order.total.toLocaleString('en-PH', {minimumFractionDigits: 2})"></span></span>
                        </div>

                        {{-- Actions --}}
                        <div class="flex flex-wrap gap-3" x-show="order.status === 'out_for_delivery' || order.status === 'delivered' || order.status === 'completed'">
                            <form method="POST" :action="order.confirmReceiptUrl" x-show="order.status === 'out_for_delivery' || order.status === 'delivered'">
                                @csrf
                                <button type="submit" class="flex items-center gap-2 bg-primary text-on-primary font-label-md text-label-md px-5 py-2.5 rounded-full hover:bg-primary/90 transition-colors">
                                    <span class="material-symbols-outlined text-[18px]">check_circle</span>
                                    Confirm Receipt
                                </button>
                            </form>

                            <template x-if="order.status === 'completed'">
                                <div class="flex flex-wrap gap-3">
                                    <button
                                        type="button"
                                        @click="openFeedback(order)"
                                        x-show="!order.hasFeedback"
                                        class="flex items-center gap-2 border border-outline-variant text-on-surface font-label-md text-label-md px-5 py-2.5 rounded-full hover:bg-surface-container transition-colors">
                                        <span class="material-symbols-outlined text-[18px]">rate_review</span>
                                        Leave Feedback
                                    </button>
                                    <span x-show="order.hasFeedback" class="flex items-center gap-1 font-label-md text-label-md text-primary px-2">
                                        <span class="material-symbols-outlined text-[18px]" style="font-variation-settings: 'FILL' 1">star</span>
                                        Feedback submitted
                                    </span>

                                    <button
                                        type="button"
                                        @click="openDispute(order)"
                                        x-show="!order.hasDispute"
                                        class="flex items-center gap-2 border border-outline-variant text-on-surface-variant font-label-md text-label-md px-5 py-2.5 rounded-full hover:bg-surface-container transition-colors">
                                        <span class="material-symbols-outlined text-[18px]">report</span>
                                        Raise Dispute
                                    </button>
                                    <span x-show="order.hasDispute" class="flex items-center gap-1 font-label-md text-label-md text-error px-2">
                                        <span class="material-symbols-outlined text-[18px]">report</span>
                                        Dispute raised
                                    </span>
                                </div>
                            </template>
                        </div>

                    </div>
                </div>
            </template>
        </div>

    </div>

    {{-- Feedback modal — real form, posts to the order's feedback route --}}
    <div x-show="feedbackModal.open" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-inverse-surface/60 backdrop-blur-sm">
        <div @click.outside="feedbackModal.open = false" class="relative max-w-sm w-full bg-surface-container-lowest rounded-3xl shadow-2xl p-6 flex flex-col gap-4">
            <h3 class="font-headline-sm text-headline-sm text-on-surface">Leave Feedback</h3>
            <form method="POST" :action="feedbackModal.url" class="flex flex-col gap-4">
                @csrf
                <div class="flex gap-1">
                    <template x-for="star in [1,2,3,4,5]" :key="star">
                        <button type="button" @click="feedbackModal.rating = star">
                            <span class="material-symbols-outlined text-[26px] text-tertiary" :style="star <= feedbackModal.rating ? `font-variation-settings: 'FILL' 1` : ''">star</span>
                        </button>
                    </template>
                </div>
                <input type="hidden" name="rating" :value="feedbackModal.rating">
                <textarea name="comment" rows="3" placeholder="How was your experience?" class="rounded-xl border border-outline-variant px-3.5 py-2.5 font-body-md text-body-md text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary"></textarea>
                <div class="flex gap-3">
                    <button type="button" @click="feedbackModal.open = false" class="flex-1 font-label-md text-label-md text-on-surface-variant px-4 py-2.5 rounded-full hover:bg-surface-container">Cancel</button>
                    <button type="submit" :disabled="feedbackModal.rating === 0" class="flex-1 bg-primary text-on-primary font-label-md text-label-md px-4 py-2.5 rounded-full hover:bg-primary/90 disabled:opacity-50">Submit</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Dispute modal — real form, posts to the order's dispute route --}}
    <div x-show="disputeModal.open" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-inverse-surface/60 backdrop-blur-sm">
        <div @click.outside="disputeModal.open = false" class="relative max-w-sm w-full bg-surface-container-lowest rounded-3xl shadow-2xl p-6 flex flex-col gap-4">
            <h3 class="font-headline-sm text-headline-sm text-on-surface">Raise a Dispute</h3>
            <form method="POST" :action="disputeModal.url" class="flex flex-col gap-4">
                @csrf
                <select name="reason" required class="rounded-xl border border-outline-variant px-3.5 py-2.5 font-body-md text-body-md text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                    <option value="" disabled selected>Select a reason</option>
                    <option>Item not as described</option>
                    <option>Item damaged or defective</option>
                    <option>Wrong item received</option>
                    <option>Item never arrived</option>
                    <option>Other</option>
                </select>
                <textarea name="details" rows="3" placeholder="Add any details that will help us look into this" class="rounded-xl border border-outline-variant px-3.5 py-2.5 font-body-md text-body-md text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary"></textarea>
                <div class="flex gap-3">
                    <button type="button" @click="disputeModal.open = false" class="flex-1 font-label-md text-label-md text-on-surface-variant px-4 py-2.5 rounded-full hover:bg-surface-container">Cancel</button>
                    <button type="submit" class="flex-1 bg-error text-on-error font-label-md text-label-md px-4 py-2.5 rounded-full hover:bg-error/90">Submit Dispute</button>
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

            // Buyer-facing tabs group the full ERP status lifecycle:
            //   To Ship          — placed, confirmed, preparing, ready_for_pickup, picked_up
            //   In Transit       — at_sorting_center, sorted, assigned_to_rider
            //   Out for Delivery — out_for_delivery, delivered
            //   Completed        — completed
            // delivery_failed / returned show up under "To Ship" with a notice banner.
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
                { key: 'placed', label: 'Order Placed', icon: 'receipt_long' },
                { key: 'preparing', label: 'Preparing', icon: 'inventory_2' },
                { key: 'in_transit', label: 'In Transit', icon: 'local_shipping' },
                { key: 'out_for_delivery', label: 'Out for Delivery', icon: 'moped' },
                { key: 'delivered', label: 'Delivered', icon: 'home' },
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
