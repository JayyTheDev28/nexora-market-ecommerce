@extends('layouts.app')

@section('title', 'Orders — Seller Dashboard')

@section('content')
<div class="w-full bg-surface py-8 min-h-[70vh]">
    <div class="max-w-none px-margin-mobile md:px-margin-desktop">

        <x-seller-nav active="orders" />

        @if (session('status'))
            <div class="mb-6 bg-primary/10 border border-primary/30 rounded-2xl p-4">
                <p class="font-body-sm text-body-sm text-primary">{{ session('status') }}</p>
            </div>
        @endif

        <h1 class="font-headline-lg text-headline-lg text-on-surface mb-6">Orders</h1>

        {{-- Status filter chips --}}
        <div class="flex flex-wrap gap-2 mb-5">
            <a href="{{ route('seller.orders.index') }}" class="px-4 py-2 rounded-full font-label-md text-label-md transition-colors {{ !request('status') ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface-variant hover:bg-surface-variant' }}">
                All
            </a>
            <a href="{{ route('seller.orders.index', ['status' => 'placed']) }}" class="px-4 py-2 rounded-full font-label-md text-label-md flex items-center gap-1.5 transition-colors {{ request('status') === 'placed' ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface-variant hover:bg-surface-variant' }}">
                New Orders
                @if($counts['new'] > 0)
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold {{ request('status') === 'placed' ? 'bg-on-primary text-primary' : 'bg-primary text-on-primary' }}">{{ $counts['new'] }}</span>
                @endif
            </a>
            <span class="px-4 py-2 rounded-full font-label-md text-label-md bg-surface-container text-on-surface-variant">
                In Progress ({{ $counts['active'] }})
            </span>
            <span class="px-4 py-2 rounded-full font-label-md text-label-md bg-surface-container text-on-surface-variant">
                Shipped+ ({{ $counts['shipped'] }})
            </span>
        </div>

        @if($orders->isEmpty())
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-10 text-center flex flex-col items-center gap-3">
                <span class="material-symbols-outlined text-[36px] text-outline">receipt_long</span>
                <p class="font-headline-sm text-headline-sm text-on-surface">No orders here yet</p>
                <p class="font-body-sm text-body-sm text-on-surface-variant">Orders containing your products will show up here.</p>
            </div>
        @else
            <div class="flex flex-col gap-4">
                @foreach($orders as $order)
                    <a href="{{ route('seller.orders.show', $order) }}" class="block bg-surface-container-lowest rounded-2xl border border-outline-variant p-5 hover:border-primary transition-colors">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <span class="font-label-md text-label-md text-on-surface">{{ $order->order_number }}</span>
                                <span class="font-body-sm text-body-sm text-on-surface-variant">{{ $order->placed_at->format('M j, Y') }}</span>
                                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-primary/10 text-primary">{{ ucfirst(str_replace('_', ' ', $order->status)) }}</span>
                            </div>
                            <span class="font-body-sm text-body-sm text-on-surface-variant">{{ $order->buyer->full_name }}</span>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach($order->items as $item)
                                <span class="px-3 py-1 rounded-lg bg-surface-container font-body-sm text-body-sm text-on-surface-variant">{{ $item->product_name }} ×{{ $item->quantity }}</span>
                            @endforeach
                        </div>
                    </a>
                @endforeach
            </div>
        @endif

    </div>
</div>
@endsection
