@extends('layouts.app')

@section('title', 'My Orders — Nexora Market')

@section('content')
<div
    x-data="ordersPage()"
    class="w-full bg-surface py-6 md:py-8 min-h-[75vh]">
    <div class="max-w-none px-4 md:px-8">

        {{-- Sub-Navigation --}}
        <x-buyer-nav active="orders" />

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="font-headline-lg text-2xl md:text-3xl font-bold tracking-tight text-on-surface">Order Tracking & History (B10)</h1>
                <p class="font-body-md text-xs md:text-sm text-on-surface-variant mt-1">Track orders, manage cancellations, file return requests, and rate your purchases.</p>
            </div>
            <a href="{{ route('catalog.index') }}" class="self-start sm:self-auto inline-flex items-center justify-center px-4 py-2 rounded-full bg-primary text-on-primary font-label-md text-xs font-semibold shadow-xs hover:bg-primary/90 transition-all">
                Continue Shopping
            </a>
        </div>

        @if (session('status'))
            <div class="mb-6 bg-primary/10 border border-primary/20 rounded-2xl p-4 flex items-center justify-between">
                <p class="font-body-sm text-xs md:text-sm font-medium text-primary">{{ session('status') }}</p>
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 bg-rose-500/10 border border-rose-500/20 rounded-2xl p-4">
                <p class="font-label-md text-xs md:text-sm font-bold text-rose-700">Please review the following:</p>
                <ul class="list-disc list-inside text-xs text-rose-600 mt-1 space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Status Filter Tabs (Rule B10) --}}
        <div class="flex items-center gap-1.5 p-1 bg-surface-container-low rounded-2xl border border-outline-variant/60 w-full mb-6 overflow-x-auto">
            @php
                $tabItems = [
                    ['key' => 'all', 'label' => 'All Orders', 'count' => $counts['all']],
                    ['key' => 'to_pay', 'label' => 'To Pay', 'count' => $counts['to_pay']],
                    ['key' => 'to_ship', 'label' => 'To Ship', 'count' => $counts['to_ship']],
                    ['key' => 'to_receive', 'label' => 'To Receive', 'count' => $counts['to_receive']],
                    ['key' => 'completed', 'label' => 'Completed', 'count' => $counts['completed']],
                    ['key' => 'cancelled', 'label' => 'Cancelled', 'count' => $counts['cancelled']],
                    ['key' => 'return_refund', 'label' => 'Return / Refund', 'count' => $counts['return_refund']],
                ];
            @endphp

            @foreach($tabItems as $tab)
                <a
                    href="{{ route('buyer.orders', ['tab' => $tab['key']]) }}"
                    class="flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold whitespace-nowrap transition-all {{ $activeTab === $tab['key'] ? 'bg-surface-container-lowest text-primary shadow-xs font-bold' : 'text-on-surface-variant hover:text-on-surface hover:bg-surface-container/50' }}">
                    <span>{{ $tab['label'] }}</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $activeTab === $tab['key'] ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface-variant' }}">
                        {{ $tab['count'] }}
                    </span>
                </a>
            @endforeach
        </div>

        {{-- Empty Orders State --}}
        @if($orders->isEmpty())
            <div class="flex flex-col items-center justify-center py-16 px-4 text-center bg-surface-container-lowest rounded-3xl border border-outline-variant max-w-lg mx-auto">
                <div class="w-14 h-14 rounded-2xl bg-surface-container flex items-center justify-center text-outline mb-3">
                    <span class="material-symbols-outlined text-[28px]">inventory_2</span>
                </div>
                <p class="font-headline-sm text-base font-bold text-on-surface">No orders in this tab</p>
                <p class="font-body-sm text-xs text-on-surface-variant max-w-sm mt-1">There are currently no orders under the selected status filter.</p>
                <a href="{{ route('catalog.index') }}" class="mt-4 inline-flex items-center justify-center bg-primary text-on-primary font-label-md text-xs font-semibold px-5 py-2.5 rounded-full hover:bg-primary/90 transition-colors">
                    Explore Marketplace
                </a>
            </div>
        @else
            {{-- Orders List --}}
            <div class="flex flex-col gap-5 max-w-5xl">
                @foreach($orders as $order)
                    @php
                        $storeName = $order->seller->sellerProfile->business_name ?? $order->seller->full_name ?? 'Store';
                        $statusBadge = match($order->status) {
                            'placed' => ['label' => 'Order Placed', 'class' => 'bg-primary/10 text-primary'],
                            'confirmed' => ['label' => 'Confirmed by Seller', 'class' => 'bg-primary/10 text-primary'],
                            'preparing' => ['label' => 'Preparing for Dispatch', 'class' => 'bg-amber-500/10 text-amber-800'],
                            'ready_for_pickup' => ['label' => 'Ready for Pickup', 'class' => 'bg-amber-500/10 text-amber-800'],
                            'picked_up', 'at_sorting_center', 'sorted', 'assigned_to_rider' => ['label' => 'In Logistics Transit', 'class' => 'bg-indigo-500/10 text-indigo-700'],
                            'out_for_delivery' => ['label' => 'Out for Delivery', 'class' => 'bg-blue-600/10 text-blue-700 animate-pulse'],
                            'delivered' => ['label' => 'Delivered', 'class' => 'bg-emerald-600/10 text-emerald-800 font-bold'],
                            'completed' => ['label' => 'Order Completed', 'class' => 'bg-emerald-600/10 text-emerald-800 font-bold'],
                            'cancelled' => ['label' => 'Cancelled', 'class' => 'bg-rose-500/10 text-rose-700 font-bold'],
                            'returned' => ['label' => 'Returned / Refunded', 'class' => 'bg-purple-500/10 text-purple-700 font-bold'],
                            default => ['label' => ucfirst($order->status), 'class' => 'bg-surface-container text-on-surface-variant'],
                        };
                        $canCancel = $order->canBeCancelledByBuyer();
                        $canReturn = $order->canBeReturned();
                        $canReview = $order->canBeReviewed();
                        $review = $order->review;
                        $dispute = $order->dispute;
                    @endphp

                    <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant overflow-hidden shadow-xs flex flex-col justify-between">
                        
                        {{-- Order Card Header --}}
                        <div class="p-4 md:px-5 bg-surface-container-low/60 border-b border-outline-variant/60 flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <span class="material-symbols-outlined text-primary text-[20px]">storefront</span>
                                <h3 class="text-xs font-bold text-on-surface">{{ $storeName }}</h3>
                                <span class="text-outline-variant">•</span>
                                <span class="font-mono text-xs font-bold text-primary">{{ $order->order_number }}</span>
                                <span class="text-outline-variant">•</span>
                                <span class="text-[11px] text-on-surface-variant">{{ $order->placed_at->format('M d, Y h:i A') }}</span>
                            </div>

                            <div class="flex items-center gap-2">
                                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold {{ $statusBadge['class'] }}">
                                    {{ $statusBadge['label'] }}
                                </span>
                            </div>
                        </div>

                        {{-- 60-Minute Payment Countdown Banner (Rule B9) --}}
                        @if($order->payment_status === 'unpaid' && in_array($order->payment_method, ['card', 'ewallet']) && $order->status !== 'cancelled' && $order->payment_expires_at)
                            <div class="bg-amber-500/10 border-b border-amber-500/20 px-5 py-2.5 flex items-center justify-between text-xs text-amber-800">
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-outlined text-[18px]">timer</span>
                                    <span><strong>Payment Window (B9):</strong> Please complete your gateway payment. Orders unpaid within 60 minutes are automatically cancelled.</span>
                                </div>
                                <span class="font-mono font-bold text-amber-900">
                                    Expires {{ $order->payment_expires_at->diffForHumans() }}
                                </span>
                            </div>
                        @endif

                        {{-- Delivery OTP Security Banner (Rule R5) --}}
                        @if($order->status === 'out_for_delivery')
                            @php
                                if (empty($order->delivery_otp)) {
                                    $order->generateDeliveryOtp();
                                }
                            @endphp
                            <div class="bg-primary/10 border-b border-primary/20 px-5 py-3 flex flex-wrap items-center justify-between gap-3 text-xs text-primary">
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-outlined text-[20px]">pin</span>
                                    <span><strong>Delivery Verification OTP:</strong> Provide this 6-digit code to your rider upon parcel arrival.</span>
                                </div>
                                <div class="flex items-center gap-2 bg-surface-container-lowest px-3 py-1 rounded-xl border border-primary/30 shadow-xs">
                                    <span class="text-[10px] text-on-surface-variant uppercase tracking-wider font-semibold">Your OTP:</span>
                                    <span class="font-mono text-base font-extrabold tracking-widest text-primary">{{ $order->delivery_otp }}</span>
                                </div>
                            </div>
                        @endif

                        {{-- Order Items List --}}
                        <div class="p-4 md:p-5 flex flex-col gap-3">
                            @foreach($order->items as $item)
                                <div class="flex items-center justify-between gap-4 py-2 border-b border-outline-variant/30 last:border-b-0 text-xs">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <img src="{{ $item->product?->gallery[0] ?? '' }}" class="w-12 h-12 rounded-xl object-cover border border-outline-variant bg-surface-container flex-shrink-0">
                                        <div class="min-w-0">
                                            <h4 class="font-bold text-on-surface truncate">{{ $item->product_name }}</h4>
                                            @if($item->variant)
                                                <span class="text-[10px] text-on-surface-variant block">
                                                    {{ collect($item->variant)->map(fn($v, $k) => "$k: $v")->implode(', ') }}
                                                </span>
                                            @endif
                                            <span class="text-[10px] text-outline">Qty: {{ $item->quantity }} × ₱{{ number_format($item->price, 2) }}</span>
                                        </div>
                                    </div>
                                    <span class="font-bold text-on-surface flex-shrink-0">
                                        ₱{{ number_format($item->price * $item->quantity, 2) }}
                                    </span>
                                </div>
                            @endforeach

                            {{-- Immutable Address Snapshot Display (Rule B2) --}}
                            @if($order->address_snapshot)
                                <div class="mt-2 p-3 rounded-xl bg-surface-container-low/40 border border-outline-variant/40 text-[11px] text-on-surface-variant flex items-start gap-2">
                                    <span class="material-symbols-outlined text-[15px] text-outline flex-shrink-0 mt-0.5">pin_drop</span>
                                    <div>
                                        <strong>Delivery Snapshot:</strong>
                                        {{ $order->address_snapshot['recipient_name'] ?? '' }} ({{ $order->address_snapshot['recipient_phone'] ?? '' }})
                                        — {{ $order->address_snapshot['full_address'] ?? '' }}
                                    </div>
                                </div>
                            @endif

                            {{-- Cancellation Reason Notice --}}
                            @if($order->status === 'cancelled')
                                <div class="mt-2 p-3 rounded-xl bg-rose-500/10 border border-rose-500/20 text-xs text-rose-800 flex items-center justify-between">
                                    <div>
                                        <strong>Order Cancelled (B11):</strong> {{ $order->cancellation_reason ?? 'Cancelled by buyer' }}
                                        <span class="block text-[11px] text-rose-700 mt-0.5">Item quantities have been restored back to inventory.</span>
                                    </div>
                                    <span class="text-[10px] text-rose-600">{{ $order->cancelled_at ? $order->cancelled_at->format('M d, Y') : '' }}</span>
                                </div>
                            @endif

                            {{-- Return/Dispute Notice (Rule B12) --}}
                            @if($dispute)
                                <div class="mt-2 p-3.5 rounded-xl bg-purple-500/10 border border-purple-500/20 text-xs text-purple-900 flex flex-col gap-1.5">
                                    <div class="flex items-center justify-between">
                                        <span class="font-bold flex items-center gap-1.5">
                                            <span class="material-symbols-outlined text-[16px]">assignment_return</span>
                                            Return / Refund Request: {{ ucfirst(str_replace('_', ' ', $dispute->status)) }}
                                        </span>
                                        <span class="text-[10px] font-mono text-purple-700">{{ $dispute->created_at->format('M d, Y') }}</span>
                                    </div>
                                    <p class="text-[11px] text-purple-800 leading-relaxed">
                                        <strong>Reason:</strong> {{ $dispute->reason }} — {{ $dispute->details }}
                                    </p>
                                    @if(!empty($dispute->media_paths))
                                        <div class="flex gap-2 pt-1 overflow-x-auto">
                                            @foreach($dispute->media_paths as $proof)
                                                <a href="{{ asset('storage/' . $proof) }}" target="_blank" class="w-12 h-12 rounded-lg border border-purple-300 overflow-hidden flex-shrink-0">
                                                    <img src="{{ asset('storage/' . $proof) }}" class="w-full h-full object-cover">
                                                </a>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endif

                            {{-- Review Display (Rule B13) --}}
                            @if($review)
                                <div class="mt-2 p-3 rounded-xl bg-amber-500/10 border border-amber-500/20 text-xs text-amber-900 flex flex-col gap-1">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-1 text-amber-600">
                                            <span class="font-bold text-amber-900">Your Rating:</span>
                                            @for($i = 1; $i <= 5; $i++)
                                                <span class="material-symbols-outlined text-[14px]" style="font-variation-settings: 'FILL' {{ $i <= $review->rating ? 1 : 0 }}">star</span>
                                            @endfor
                                        </div>
                                        <span class="text-[10px] text-amber-700">
                                            {{ $review->edited_count >= 1 ? 'Edited (1/1 edit used)' : '1 edit remaining' }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-amber-950 italic mt-0.5">"{{ $review->comment ?? 'No written comment' }}"</p>
                                </div>
                            @endif
                        </div>

                        {{-- Order Card Footer & Actions --}}
                        <div class="p-4 md:px-5 bg-surface-container-low/40 border-t border-outline-variant/60 flex flex-wrap items-center justify-between gap-4">
                            {{-- Pricing Breakdown --}}
                            <div class="text-xs text-on-surface-variant flex items-center gap-3">
                                <span>Subtotal: <strong>₱{{ number_format($order->subtotal, 2) }}</strong></span>
                                <span>Shipping: <strong>₱{{ number_format($order->shipping_fee, 2) }}</strong></span>
                                @if($order->discount > 0)
                                    <span class="text-emerald-700 font-bold">Discount: -₱{{ number_format($order->discount, 2) }}</span>
                                @endif
                                <span class="text-sm font-black text-on-surface ml-2">Total: ₱{{ number_format($order->total, 2) }}</span>
                            </div>

                            {{-- Action Buttons --}}
                            <div class="flex items-center gap-2">
                                {{-- Chat Seller Action (B14) --}}
                                @if($order->seller_id)
                                    <form method="POST" action="{{ route('buyer.chat.start') }}">
                                        @csrf
                                        <input type="hidden" name="seller_id" value="{{ $order->seller_id }}">
                                        <button
                                            type="submit"
                                            class="px-3.5 py-1.5 rounded-xl border border-outline-variant text-xs font-semibold text-on-surface hover:bg-surface-container flex items-center gap-1">
                                            <span class="material-symbols-outlined text-[16px]">chat</span>
                                            <span>Chat Seller</span>
                                        </button>
                                    </form>
                                @endif

                                {{-- B11: Pre-Shipping Cancellation --}}
                                @if($canCancel)
                                    <button
                                        type="button"
                                        @click="openCancelModal({{ $order->id }}, '{{ $order->order_number }}')"
                                        class="px-3.5 py-1.5 rounded-xl border border-rose-300 text-rose-700 text-xs font-semibold hover:bg-rose-50 transition-colors">
                                        Cancel Order (B11)
                                    </button>
                                @endif

                                {{-- Confirm Delivery Receipt --}}
                                @if(in_array($order->status, ['out_for_delivery', 'delivered']))
                                    <form method="POST" action="{{ route('buyer.orders.confirm-receipt', $order->id) }}">
                                        @csrf
                                        <button
                                            type="submit"
                                            class="px-4 py-1.5 rounded-xl bg-primary text-on-primary text-xs font-semibold hover:opacity-90">
                                            Confirm Received
                                        </button>
                                    </form>
                                @endif

                                {{-- B12: 7-Day Return / Refund Window --}}
                                @if($canReturn)
                                    <button
                                        type="button"
                                        @click="openReturnModal({{ $order->id }}, '{{ $order->order_number }}')"
                                        class="px-3.5 py-1.5 rounded-xl border border-purple-400 text-purple-700 text-xs font-semibold hover:bg-purple-50 transition-colors">
                                        Return / Refund (B12)
                                    </button>
                                @endif

                                {{-- B13: 30-Day Product Rating & Review --}}
                                @if($canReview)
                                    @if(!$review)
                                        <button
                                            type="button"
                                            @click="openReviewModal({{ $order->id }}, '{{ $order->order_number }}', null)"
                                            class="px-4 py-1.5 rounded-xl bg-amber-500 text-white text-xs font-bold hover:bg-amber-600 transition-colors">
                                            Rate & Review (B13)
                                        </button>
                                    @elseif($review->canBeEdited())
                                        <button
                                            type="button"
                                            @click="openReviewModal({{ $order->id }}, '{{ $order->order_number }}', {{ json_encode(['rating' => $review->rating, 'comment' => $review->comment]) }})"
                                            class="px-3.5 py-1.5 rounded-xl border border-amber-400 text-amber-800 text-xs font-semibold hover:bg-amber-50 transition-colors">
                                            Edit Review (1 edit left)
                                        </button>
                                    @endif
                                @endif
                            </div>
                        </div>

                    </div>
                @endforeach
            </div>
        @endif

        {{-- Cancellation Modal (B11) --}}
        <div x-show="cancelModal.open" x-cloak class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div @click.away="cancelModal.open = false" class="w-full max-w-md bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 shadow-xl flex flex-col gap-4">
                <div>
                    <h3 class="text-base font-bold text-on-surface">Cancel Order (B11)</h3>
                    <p class="text-xs text-on-surface-variant mt-0.5">Pre-shipping cancellation is permitted before logistics preparation. Product quantities will be immediately restored.</p>
                </div>
                <form :action="`/buyer/orders/${cancelModal.orderId}/cancel`" method="POST" class="flex flex-col gap-4">
                    @csrf
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-semibold text-on-surface">Reason for cancellation</label>
                        <select name="reason" required class="rounded-xl border border-outline-variant px-3 py-2 text-xs bg-surface text-on-surface focus:outline-none focus:ring-2 focus:ring-primary">
                            <option value="">Select a reason</option>
                            <option value="Changed mind / No longer needed">Changed mind / No longer needed</option>
                            <option value="Need to change shipping address">Need to change shipping address</option>
                            <option value="Found cheaper alternative">Found cheaper alternative</option>
                            <option value="Duplicate order placed">Duplicate order placed</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="flex items-center justify-end gap-3 pt-2 border-t border-outline-variant/60">
                        <button type="button" @click="cancelModal.open = false" class="px-4 py-2 rounded-xl text-xs font-semibold text-on-surface-variant hover:text-on-surface">
                            Back
                        </button>
                        <button type="submit" class="px-5 py-2 rounded-xl bg-rose-600 text-white text-xs font-bold hover:bg-rose-700">
                            Confirm Cancellation
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- 7-Day Return / Refund Request Modal (B12) --}}
        <div x-show="returnModal.open" x-cloak class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div @click.away="returnModal.open = false" class="w-full max-w-lg bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 shadow-xl flex flex-col gap-4 max-h-[90vh] overflow-y-auto">
                <div>
                    <h3 class="text-base font-bold text-on-surface">File Return / Refund Request (B12)</h3>
                    <p class="text-xs text-on-surface-variant mt-0.5">Permitted within 7 days of delivery. Requires 1–5 proof photos or videos.</p>
                </div>
                <form :action="`/buyer/orders/${returnModal.orderId}/return`" method="POST" enctype="multipart/form-data" class="flex flex-col gap-4">
                    @csrf
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-semibold text-on-surface">Return Reason</label>
                        <select name="reason" required class="rounded-xl border border-outline-variant px-3 py-2 text-xs bg-surface text-on-surface focus:outline-none focus:ring-2 focus:ring-primary">
                            <option value="">Select return reason</option>
                            <option value="Item damaged or defective upon arrival">Item damaged or defective upon arrival</option>
                            <option value="Wrong item received">Wrong item received</option>
                            <option value="Item not as described / Missing parts">Item not as described / Missing parts</option>
                            <option value="Counterfeit or unauthentic item">Counterfeit or unauthentic item</option>
                        </select>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-semibold text-on-surface">Description & Details</label>
                        <textarea name="details" required rows="3" placeholder="Describe the issue in detail (min 10 characters)..." class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs bg-surface text-on-surface focus:outline-none focus:ring-2 focus:ring-primary"></textarea>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-semibold text-on-surface">Upload Photo / Video Evidence (1 to 5 files required)</label>
                        <input type="file" name="media[]" required multiple accept="image/*,video/*" class="text-xs text-on-surface-variant file:mr-2 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-primary file:text-on-primary">
                        <span class="text-[11px] text-outline">Supported: JPG, PNG, WEBP, MP4, MOV (max 10MB each)</span>
                    </div>
                    <div class="flex items-center justify-end gap-3 pt-2 border-t border-outline-variant/60">
                        <button type="button" @click="returnModal.open = false" class="px-4 py-2 rounded-xl text-xs font-semibold text-on-surface-variant hover:text-on-surface">
                            Cancel
                        </button>
                        <button type="submit" class="px-5 py-2 rounded-xl bg-purple-700 text-white text-xs font-bold hover:bg-purple-800">
                            Submit Return Request
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Item Rating & Review Modal (B13) --}}
        <div x-show="reviewModal.open" x-cloak class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div @click.away="reviewModal.open = false" class="w-full max-w-md bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 shadow-xl flex flex-col gap-4">
                <div>
                    <h3 class="text-base font-bold text-on-surface">Item Rating & Review (B13)</h3>
                    <p class="text-xs text-on-surface-variant mt-0.5">Rate this completed order within 30 days. Maximum 1 edit allowed.</p>
                </div>
                <form :action="`/buyer/orders/${reviewModal.orderId}/review`" method="POST" enctype="multipart/form-data" class="flex flex-col gap-4">
                    @csrf
                    {{-- Star Rating --}}
                    <div class="flex items-center justify-center gap-2 py-2">
                        <template x-for="s in [1,2,3,4,5]" :key="s">
                            <button type="button" @click="reviewModal.rating = s" class="p-1 hover:scale-110 transition-transform">
                                <span class="material-symbols-outlined text-[32px]" :class="s <= reviewModal.rating ? 'text-amber-500' : 'text-outline'" :style="s <= reviewModal.rating ? `font-variation-settings: 'FILL' 1` : ''">
                                    star
                                </span>
                            </button>
                        </template>
                    </div>
                    <input type="hidden" name="rating" :value="reviewModal.rating">

                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-semibold text-on-surface">Review Comment</label>
                        <textarea name="comment" x-model="reviewModal.comment" rows="3" placeholder="Share your experience with product quality, packaging, and dispatch..." class="rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs bg-surface text-on-surface focus:outline-none focus:ring-2 focus:ring-primary"></textarea>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-semibold text-on-surface">Add Photos (Optional, up to 5 photos)</label>
                        <input type="file" name="photos[]" multiple accept="image/*" class="text-xs text-on-surface-variant file:mr-2 file:py-1 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-primary file:text-on-primary">
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2 border-t border-outline-variant/60">
                        <button type="button" @click="reviewModal.open = false" class="px-4 py-2 rounded-xl text-xs font-semibold text-on-surface-variant hover:text-on-surface">
                            Cancel
                        </button>
                        <button type="submit" :disabled="reviewModal.rating === 0" class="px-5 py-2 rounded-xl bg-amber-500 text-white text-xs font-bold hover:bg-amber-600 disabled:opacity-40">
                            Submit Review
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
    function ordersPage() {
        return {
            cancelModal: {
                open: false,
                orderId: null,
                orderNumber: '',
            },
            returnModal: {
                open: false,
                orderId: null,
                orderNumber: '',
            },
            reviewModal: {
                open: false,
                orderId: null,
                orderNumber: '',
                rating: 5,
                comment: '',
            },

            openCancelModal(id, num) {
                this.cancelModal.orderId = id;
                this.cancelModal.orderNumber = num;
                this.cancelModal.open = true;
            },

            openReturnModal(id, num) {
                this.returnModal.orderId = id;
                this.returnModal.orderNumber = num;
                this.returnModal.open = true;
            },

            openReviewModal(id, num, existing) {
                this.reviewModal.orderId = id;
                this.reviewModal.orderNumber = num;
                this.reviewModal.rating = existing ? existing.rating : 5;
                this.reviewModal.comment = existing ? (existing.comment || '') : '';
                this.reviewModal.open = true;
            }
        };
    }
</script>
@endpush
