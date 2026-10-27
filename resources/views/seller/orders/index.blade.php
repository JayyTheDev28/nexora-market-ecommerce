@extends('layouts.app')

@section('title', 'Order Management & Fulfillment — Seller Hub')

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

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="font-headline-lg text-headline-lg text-on-surface">Order Management</h1>
                <p class="font-body-md text-on-surface-variant">Review incoming purchases, pack items, print shipping waybills, and dispatch to courier.</p>
            </div>
        </div>

        {{-- Status Filter Tabs --}}
        <div class="flex flex-wrap items-center gap-2 mb-5">
            @foreach([
                ['key' => 'all', 'label' => 'All Orders', 'count' => $counts['all']],
                ['key' => 'to_confirm', 'label' => 'To Confirm', 'count' => $counts['to_confirm']],
                ['key' => 'processing', 'label' => 'To Pack & Prepare', 'count' => $counts['processing']],
                ['key' => 'ready_for_pickup', 'label' => 'Ready for Pickup', 'count' => $counts['ready_for_pickup']],
                ['key' => 'shipped', 'label' => 'In Transit', 'count' => $counts['shipped']],
                ['key' => 'completed', 'label' => 'Completed', 'count' => $counts['completed']],
                ['key' => 'cancelled', 'label' => 'Cancelled', 'count' => $counts['cancelled']],
                ['key' => 'returned', 'label' => 'Returned', 'count' => $counts['returned']],
            ] as $tab)
                <a
                    href="{{ route('seller.orders.index', array_filter(array_merge(request()->query(), ['status' => $tab['key']]))) }}"
                    class="px-3.5 py-1.5 rounded-full text-xs font-bold transition-colors {{ ($statusTab === $tab['key']) ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface-variant hover:bg-surface-container-high' }}">
                    {{ $tab['label'] }} ({{ $tab['count'] }})
                </a>
            @endforeach
        </div>

        {{-- Search and Date Filters --}}
        <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4 mb-6 shadow-sm">
            <form method="GET" action="{{ route('seller.orders.index') }}" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
                <input type="hidden" name="status" value="{{ $statusTab }}">

                <div>
                    <label class="block text-[11px] font-semibold text-on-surface-variant mb-1">Search Keywords</label>
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="Order #, buyer, product..."
                        class="w-full rounded-xl border border-outline-variant px-3 py-1.5 text-xs text-on-surface bg-surface focus:outline-none focus:border-primary">
                </div>

                <div>
                    <label class="block text-[11px] font-semibold text-on-surface-variant mb-1">From Date</label>
                    <input type="date" name="from" value="{{ request('from') }}"
                        class="w-full rounded-xl border border-outline-variant px-3 py-1.5 text-xs text-on-surface bg-surface focus:outline-none focus:border-primary">
                </div>

                <div>
                    <label class="block text-[11px] font-semibold text-on-surface-variant mb-1">To Date</label>
                    <input type="date" name="to" value="{{ request('to') }}"
                        class="w-full rounded-xl border border-outline-variant px-3 py-1.5 text-xs text-on-surface bg-surface focus:outline-none focus:border-primary">
                </div>

                <div class="flex items-end gap-2">
                    <button type="submit" class="flex-1 py-1.5 px-4 rounded-xl bg-primary text-on-primary text-xs font-bold hover:bg-primary/90 transition-colors">
                        Filter Orders
                    </button>
                    <a href="{{ route('seller.orders.index', ['status' => $statusTab]) }}" class="py-1.5 px-3 rounded-xl border border-outline-variant text-xs text-on-surface-variant hover:bg-surface-container">
                        Reset
                    </a>
                </div>
            </form>
        </div>

        {{-- Orders List --}}
        @if($orders->isEmpty())
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-12 text-center flex flex-col items-center gap-2">
                <span class="material-symbols-outlined text-[48px] text-outline-variant mb-1">receipt_long</span>
                <p class="font-headline-sm text-headline-sm text-on-surface font-bold">No orders found</p>
                <p class="font-body-sm text-xs text-on-surface-variant">There are no orders matching the selected filter or search terms.</p>
            </div>
        @else
            <div class="space-y-4">
                @foreach($orders as $order)
                    <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-5 hover:border-primary/50 transition-all shadow-sm">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-outline-variant pb-3 mb-3">
                            <div class="flex items-center gap-3 flex-wrap">
                                <a href="{{ route('seller.orders.show', $order) }}" class="font-headline-sm text-sm font-bold text-primary hover:underline">
                                    {{ $order->order_number }}
                                </a>
                                <span class="text-xs text-on-surface-variant">•</span>
                                <span class="text-xs text-on-surface-variant font-medium">{{ $order->placed_at->format('M j, Y H:i') }}</span>
                                <span class="text-xs text-on-surface-variant">•</span>
                                <span class="text-xs text-on-surface font-semibold">{{ $order->buyer->full_name }}</span>
                                @if($order->payment_method === 'cod')
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/10 text-amber-600 border border-amber-500/20">COD</span>
                                @else
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/10 text-emerald-600 border border-emerald-500/20">Prepaid ({{ strtoupper($order->payment_method) }})</span>
                                @endif
                            </div>
                            <div>
                                <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider
                                    {{ $order->status === 'placed' ? 'bg-primary/10 text-primary border border-primary/20' : ($order->status === 'cancelled' ? 'bg-error/10 text-error' : ($order->status === 'completed' || $order->status === 'delivered' ? 'bg-emerald-500/10 text-emerald-600' : 'bg-blue-500/10 text-blue-600')) }}">
                                    {{ str_replace('_', ' ', $order->status) }}
                                </span>
                            </div>
                        </div>

                        {{-- Order Items Row --}}
                        <div class="space-y-2 mb-4">
                            @foreach($order->items as $item)
                                <div class="flex items-center justify-between text-xs py-1">
                                    <div class="flex items-center gap-2">
                                        <span class="material-symbols-outlined text-[16px] text-outline-variant">package_2</span>
                                        <span class="font-semibold text-on-surface">{{ $item->product_name }}</span>
                                        @if(!empty($item->variant))
                                            <span class="text-on-surface-variant">({{ is_array($item->variant) ? implode(', ', $item->variant) : $item->variant }})</span>
                                        @endif
                                        <span class="text-on-surface-variant font-medium">× {{ $item->quantity }}</span>
                                    </div>
                                    <span class="font-mono text-on-surface">₱{{ number_format($item->price * $item->quantity, 2) }}</span>
                                </div>
                            @endforeach
                        </div>

                        {{-- Footer Summary & Actions --}}
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pt-3 border-t border-outline-variant/60">
                            <div class="text-xs text-on-surface-variant">
                                <span>Ship to: <strong class="text-on-surface">{{ $order->address->municipality_name ?? '—' }}, {{ $order->address->province_name ?? '—' }}</strong></span>
                                <span class="ml-3">Total: <strong class="text-primary font-bold text-sm">₱{{ number_format($order->total, 2) }}</strong></span>
                            </div>

                            <div class="flex items-center gap-2">
                                <a href="{{ route('seller.orders.waybill', $order) }}" target="_blank" class="px-3.5 py-1.5 rounded-full border border-outline-variant text-xs font-semibold text-on-surface hover:bg-surface-container transition-colors flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[15px]">print</span>
                                    Print Waybill
                                </a>
                                <a href="{{ route('seller.orders.show', $order) }}" class="px-4 py-1.5 rounded-full bg-primary text-on-primary text-xs font-bold hover:bg-primary/90 transition-colors">
                                    Manage Order
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-6">
                {{ $orders->links() }}
            </div>
        @endif

    </div>
</div>
@endsection
