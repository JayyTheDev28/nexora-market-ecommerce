@extends('layouts.app')

@section('title', 'My Orders — Nexora Market')

@section('content')
<div x-data="ordersPage()" x-init="$store.orders.seedIfEmpty()" class="w-full bg-surface py-8 min-h-[70vh]">
    <div class="max-w-none px-margin-mobile md:px-margin-desktop">

        <h1 class="font-headline-lg text-headline-lg text-on-surface mb-6">My Orders</h1>

        {{-- Status tabs --}}
        <div class="flex gap-2 overflow-x-auto pb-1 mb-6 border-b border-outline-variant">
            <template x-for="tab in tabs" :key="tab.status">
                <button
                    type="button"
                    @click="activeTab = tab.status"
                    class="flex items-center gap-2 px-4 py-3 font-label-md text-label-md whitespace-nowrap border-b-2 -mb-px transition-colors"
                    :class="activeTab === tab.status ? 'border-primary text-primary' : 'border-transparent text-on-surface-variant hover:text-on-surface'">
                    <span x-text="tab.label"></span>
                    <span
                        class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                        :class="activeTab === tab.status ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface-variant'"
                        x-text="$store.orders.byStatus(tab.status).length">
                    </span>
                </button>
            </template>
        </div>

        {{-- Empty state for this tab --}}
        <div x-show="$store.orders.byStatus(activeTab).length === 0" x-cloak class="flex flex-col items-center justify-center py-20 text-center gap-3">
            <span class="material-symbols-outlined text-[40px] text-outline">inventory_2</span>
            <p class="font-headline-sm text-headline-sm text-on-surface">No orders here yet</p>
            <p class="font-body-sm text-body-sm text-on-surface-variant">Orders you place will show up here once they reach this stage.</p>
        </div>

        {{-- Orders list --}}
        <div class="flex flex-col gap-5">
            <template x-for="order in $store.orders.byStatus(activeTab)" :key="order.id">
                <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant overflow-hidden">

                    {{-- Header --}}
                    <div class="flex flex-wrap items-center justify-between gap-2 px-5 py-4 bg-surface-container-low border-b border-outline-variant">
                        <div class="flex items-center gap-3">
                            <span class="font-label-md text-label-md text-on-surface" x-text="order.id"></span>
                            <span class="font-body-sm text-body-sm text-on-surface-variant" x-text="formatDate(order.placedAt)"></span>
                        </div>
                        <span class="font-body-sm text-body-sm text-on-surface-variant" x-text="order.paymentMethod"></span>
                    </div>

                    <div class="p-5 flex flex-col gap-5">

                        {{-- Timeline --}}
                        <div class="flex items-center">
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

                        {{-- Line items --}}
                        <div class="flex flex-col gap-3">
                            <template x-for="(line, i) in order.lines" :key="i">
                                <div class="flex items-center gap-3">
                                    <img :src="line.image" :alt="line.name" class="w-12 h-12 rounded-lg object-cover bg-surface-container flex-shrink-0">
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
                        <div class="flex flex-wrap gap-3" x-show="order.status === 'out_for_delivery' || order.status === 'completed'">
                            <button
                                type="button"
                                x-show="order.status === 'out_for_delivery'"
                                @click="$store.orders.confirmReceipt(order.id)"
                                class="flex items-center gap-2 bg-primary text-on-primary font-label-md text-label-md px-5 py-2.5 rounded-full hover:bg-primary/90 transition-colors">
                                <span class="material-symbols-outlined text-[18px]">check_circle</span>
                                Confirm Receipt
                            </button>

                            <template x-if="order.status === 'completed'">
                                <div class="flex flex-wrap gap-3">
                                    <button
                                        type="button"
                                        @click="openFeedback(order)"
                                        x-show="!order.feedback"
                                        class="flex items-center gap-2 border border-outline-variant text-on-surface font-label-md text-label-md px-5 py-2.5 rounded-full hover:bg-surface-container transition-colors">
                                        <span class="material-symbols-outlined text-[18px]">rate_review</span>
                                        Leave Feedback
                                    </button>
                                    <span x-show="order.feedback" class="flex items-center gap-1 font-label-md text-label-md text-primary px-2">
                                        <span class="material-symbols-outlined text-[18px]" style="font-variation-settings: 'FILL' 1">star</span>
                                        Feedback submitted
                                    </span>

                                    <button
                                        type="button"
                                        @click="openDispute(order)"
                                        x-show="!order.disputed"
                                        class="flex items-center gap-2 border border-outline-variant text-on-surface-variant font-label-md text-label-md px-5 py-2.5 rounded-full hover:bg-surface-container transition-colors">
                                        <span class="material-symbols-outlined text-[18px]">report</span>
                                        Raise Dispute
                                    </button>
                                    <span x-show="order.disputed" class="flex items-center gap-1 font-label-md text-label-md text-error px-2">
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

    {{-- Feedback modal --}}
    <div x-show="feedbackModal.open" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-inverse-surface/60 backdrop-blur-sm">
        <div @click.outside="feedbackModal.open = false" class="relative max-w-sm w-full bg-surface-container-lowest rounded-3xl shadow-2xl p-6 flex flex-col gap-4">
            <h3 class="font-headline-sm text-headline-sm text-on-surface">Leave Feedback</h3>
            <div class="flex gap-1">
                <template x-for="star in [1,2,3,4,5]" :key="star">
                    <button type="button" @click="feedbackModal.rating = star">
                        <span class="material-symbols-outlined text-[26px] text-tertiary" :style="star <= feedbackModal.rating ? `font-variation-settings: 'FILL' 1` : ''" x-text="'star'"></span>
                    </button>
                </template>
            </div>
            <textarea x-model="feedbackModal.comment" rows="3" placeholder="How was your experience?" class="rounded-xl border border-outline-variant px-3.5 py-2.5 font-body-md text-body-md text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary"></textarea>
            <div class="flex gap-3">
                <button type="button" @click="feedbackModal.open = false" class="flex-1 font-label-md text-label-md text-on-surface-variant px-4 py-2.5 rounded-full hover:bg-surface-container">Cancel</button>
                <button type="button" @click="submitFeedback()" class="flex-1 bg-primary text-on-primary font-label-md text-label-md px-4 py-2.5 rounded-full hover:bg-primary/90">Submit</button>
            </div>
        </div>
    </div>

    {{-- Dispute modal --}}
    <div x-show="disputeModal.open" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-inverse-surface/60 backdrop-blur-sm">
        <div @click.outside="disputeModal.open = false" class="relative max-w-sm w-full bg-surface-container-lowest rounded-3xl shadow-2xl p-6 flex flex-col gap-4">
            <h3 class="font-headline-sm text-headline-sm text-on-surface">Raise a Dispute</h3>
            <select x-model="disputeModal.reason" class="rounded-xl border border-outline-variant px-3.5 py-2.5 font-body-md text-body-md text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                <option value="" disabled selected>Select a reason</option>
                <option>Item not as described</option>
                <option>Item damaged or defective</option>
                <option>Wrong item received</option>
                <option>Item never arrived</option>
                <option>Other</option>
            </select>
            <textarea x-model="disputeModal.details" rows="3" placeholder="Add any details that will help us look into this" class="rounded-xl border border-outline-variant px-3.5 py-2.5 font-body-md text-body-md text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary"></textarea>
            <div class="flex gap-3">
                <button type="button" @click="disputeModal.open = false" class="flex-1 font-label-md text-label-md text-on-surface-variant px-4 py-2.5 rounded-full hover:bg-surface-container">Cancel</button>
                <button type="button" @click="submitDispute()" class="flex-1 bg-error text-on-error font-label-md text-label-md px-4 py-2.5 rounded-full hover:bg-error/90">Submit Dispute</button>
            </div>
        </div>
    </div>

</div>

@push('scripts')
<script>
    function ordersPage() {
        return {
            tabs: [
                { status: 'to_ship', label: 'To Ship' },
                { status: 'in_transit', label: 'In Transit' },
                { status: 'out_for_delivery', label: 'Out for Delivery' },
                { status: 'completed', label: 'Completed' },
            ],
            activeTab: 'to_ship',

            timelineSteps: [
                { key: 'placed', label: 'Order Placed', icon: 'receipt_long' },
                { key: 'to_ship', label: 'Preparing', icon: 'inventory_2' },
                { key: 'in_transit', label: 'In Transit', icon: 'local_shipping' },
                { key: 'out_for_delivery', label: 'Out for Delivery', icon: 'moped' },
                { key: 'completed', label: 'Delivered', icon: 'home' },
            ],

            stepIndex(status) {
                const order = ['placed', 'to_ship', 'in_transit', 'out_for_delivery', 'completed'];
                // Every order has at least reached "placed"; map its current
                // status onto the timeline's step index.
                const idx = order.indexOf(status);
                return idx === -1 ? 0 : idx;
            },

            formatDate(timestamp) {
                return new Date(timestamp).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
            },

            feedbackModal: { open: false, orderId: null, rating: 0, comment: '' },
            openFeedback(order) {
                this.feedbackModal = { open: true, orderId: order.id, rating: 0, comment: '' };
            },
            submitFeedback() {
                const order = this.$store.orders.items.find((o) => o.id === this.feedbackModal.orderId);
                if (order) {
                    order.feedback = { rating: this.feedbackModal.rating, comment: this.feedbackModal.comment };
                    this.$store.orders.persist();
                }
                this.feedbackModal.open = false;
            },

            disputeModal: { open: false, orderId: null, reason: '', details: '' },
            openDispute(order) {
                this.disputeModal = { open: true, orderId: order.id, reason: '', details: '' };
            },
            submitDispute() {
                const order = this.$store.orders.items.find((o) => o.id === this.disputeModal.orderId);
                if (order) {
                    order.disputed = true;
                    order.disputeReason = this.disputeModal.reason;
                    order.disputeDetails = this.disputeModal.details;
                    this.$store.orders.persist();
                }
                this.disputeModal.open = false;
            },
        };
    }
</script>
@endpush

@endsection
