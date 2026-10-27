@extends('layouts.app')

@section('title', 'Buyer Dashboard — Nexora Market')

@section('content')
<div class="w-full bg-surface py-6 md:py-8 min-h-[75vh]">
    <div class="max-w-none px-4 md:px-8">

        {{-- Sub-Navigation --}}
        <x-buyer-nav active="dashboard" />

        <x-platform-announcements />

        {{-- Welcome Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
            <div>
                <h1 class="font-headline-lg text-2xl md:text-3xl font-bold tracking-tight text-on-surface">Welcome back, {{ auth()->user()->first_name }}</h1>
                <p class="font-body-md text-xs md:text-sm text-on-surface-variant mt-1">Manage your active orders, delivery preferences, and shopping cart.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ url('/catalog') }}" class="inline-flex items-center justify-center px-4 py-2 rounded-full bg-primary text-on-primary font-label-md text-xs font-semibold shadow-xs hover:bg-primary/90 transition-all">
                    Browse Products
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center justify-center px-4 py-2 rounded-full border border-outline-variant text-on-surface-variant font-label-md text-xs font-semibold hover:text-error hover:border-error/40 hover:bg-error-container/20 transition-all">
                        Sign Out
                    </button>
                </form>
            </div>
        </div>

        {{-- Metric Stat Cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 md:gap-6 mb-8">
            {{-- Cart Items --}}
            <a href="{{ url('/cart') }}" class="group bg-surface-container-lowest rounded-2xl border border-outline-variant p-5 hover:border-primary/50 hover:shadow-md transition-all duration-200">
                <div class="flex items-center justify-between">
                    <p class="font-label-md text-xs font-medium text-on-surface-variant">Shopping Cart</p>
                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-surface-container text-on-surface-variant group-hover:bg-primary/10 group-hover:text-primary transition-colors">View Cart &rarr;</span>
                </div>
                <div class="mt-3 flex items-baseline gap-2">
                    <p class="font-headline-lg text-2xl md:text-3xl font-bold text-on-surface">{{ $stats['cartItems'] }}</p>
                    <span class="text-xs text-on-surface-variant">item{{ $stats['cartItems'] === 1 ? '' : 's' }}</span>
                </div>
            </a>

            {{-- Active Orders --}}
            <a href="{{ route('buyer.orders') }}" class="group bg-surface-container-lowest rounded-2xl border border-outline-variant p-5 hover:border-primary/50 hover:shadow-md transition-all duration-200">
                <div class="flex items-center justify-between">
                    <p class="font-label-md text-xs font-medium text-on-surface-variant">Active Orders</p>
                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full {{ $stats['activeOrders'] > 0 ? 'bg-primary/10 text-primary' : 'bg-surface-container text-on-surface-variant' }} group-hover:bg-primary/10 group-hover:text-primary transition-colors">Track &rarr;</span>
                </div>
                <div class="mt-3 flex items-baseline gap-2">
                    <p class="font-headline-lg text-2xl md:text-3xl font-bold {{ $stats['activeOrders'] > 0 ? 'text-primary' : 'text-on-surface' }}">{{ $stats['activeOrders'] }}</p>
                    <span class="text-xs text-on-surface-variant">in progress</span>
                </div>
            </a>

            {{-- Completed Orders --}}
            <a href="{{ route('buyer.orders') }}" class="group bg-surface-container-lowest rounded-2xl border border-outline-variant p-5 hover:border-primary/50 hover:shadow-md transition-all duration-200">
                <div class="flex items-center justify-between">
                    <p class="font-label-md text-xs font-medium text-on-surface-variant">Completed Orders</p>
                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-surface-container text-on-surface-variant group-hover:bg-primary/10 group-hover:text-primary transition-colors">History &rarr;</span>
                </div>
                <div class="mt-3 flex items-baseline gap-2">
                    <p class="font-headline-lg text-2xl md:text-3xl font-bold text-on-surface">{{ $stats['completedOrders'] }}</p>
                    <span class="text-xs text-on-surface-variant">delivered</span>
                </div>
            </a>
        </div>

        {{-- Main Dashboard Content --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

            {{-- Recent Orders (2 cols on desktop) --}}
            <div class="lg:col-span-2 bg-surface-container-lowest rounded-2xl border border-outline-variant overflow-hidden shadow-xs">
                <div class="px-5 py-4 border-b border-outline-variant bg-surface-container-low/60 flex items-center justify-between">
                    <h2 class="font-headline-sm text-sm font-bold text-on-surface">Recent Orders</h2>
                    <a href="{{ route('buyer.orders') }}" class="font-label-md text-xs font-semibold text-primary hover:underline">View All Orders &rarr;</a>
                </div>

                @if($recentOrders->isEmpty())
                    <div class="p-10 text-center flex flex-col items-center gap-3">
                        <div class="w-12 h-12 rounded-2xl bg-surface-container flex items-center justify-center text-outline">
                            <span class="material-symbols-outlined text-[24px]">shopping_bag</span>
                        </div>
                        <p class="font-headline-sm text-sm font-bold text-on-surface">No recent orders found</p>
                        <p class="font-body-sm text-xs text-on-surface-variant max-w-sm">Explore verified products from trusted Philippine merchants in our catalog.</p>
                        <a href="{{ url('/catalog') }}" class="mt-2 inline-flex items-center justify-center bg-primary text-on-primary font-label-md text-xs font-semibold px-5 py-2.5 rounded-full hover:bg-primary/90 transition-colors">
                            Explore Catalog
                        </a>
                    </div>
                @else
                    <div class="divide-y divide-outline-variant/60">
                        @foreach($recentOrders as $order)
                            @php
                                $statusSlug = $order->status;
                                $statusClass = match($statusSlug) {
                                    'completed', 'delivered' => 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20',
                                    'out_for_delivery' => 'bg-primary/10 text-primary border-primary/20',
                                    'at_sorting_center', 'sorted', 'assigned_to_rider' => 'bg-indigo-500/10 text-indigo-600 border-indigo-500/20',
                                    'delivery_failed', 'returned' => 'bg-rose-500/10 text-rose-600 border-rose-500/20',
                                    default => 'bg-amber-500/10 text-amber-700 border-amber-500/20',
                                };
                            @endphp
                            <div class="p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-surface-container-low/40 transition-colors">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono text-xs font-bold text-on-surface">{{ $order->order_number }}</span>
                                        <span class="text-outline-variant">•</span>
                                        <span class="text-xs text-on-surface-variant">{{ $order->placed_at->format('M j, Y') }}</span>
                                    </div>
                                    <p class="text-xs text-on-surface-variant mt-1">
                                        {{ $order->items->count() }} item{{ $order->items->count() === 1 ? '' : 's' }}
                                        @if($order->items->isNotEmpty())
                                            — <span class="text-on-surface font-medium">{{ $order->items->first()->product_name }}</span>
                                            @if($order->items->count() > 1)
                                                <span class="text-on-surface-variant text-[11px]">(+{{ $order->items->count() - 1 }} more)</span>
                                            @endif
                                        @endif
                                    </p>
                                </div>

                                <div class="flex items-center justify-between sm:justify-end gap-4">
                                    <span class="px-2.5 py-1 rounded-full text-[11px] font-bold border {{ $statusClass }}">
                                        {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                                    </span>
                                    <a href="{{ route('buyer.orders') }}" class="text-xs font-semibold text-primary hover:underline whitespace-nowrap">
                                        Details
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Quick Shortcuts Sidebar (1 col on desktop) --}}
            <div class="flex flex-col gap-4">
                {{-- Account Profile Card --}}
                <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-5 shadow-xs">
                    <p class="font-label-sm text-[10px] uppercase tracking-wider text-outline font-bold mb-3">Account Profile</p>
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-full bg-primary text-on-primary font-bold text-sm flex items-center justify-center flex-shrink-0">
                            {{ strtoupper(substr(auth()->user()->first_name, 0, 1)) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-bold text-on-surface truncate">{{ auth()->user()->full_name }}</p>
                            <p class="text-[11px] text-on-surface-variant truncate">{{ auth()->user()->email }}</p>
                        </div>
                    </div>
                    @if(auth()->user()->address)
                        <div class="p-3 bg-surface-container rounded-xl text-[11px] text-on-surface-variant leading-relaxed mb-3">
                            <p class="font-semibold text-on-surface mb-0.5">Primary Delivery Address:</p>
                            <p class="truncate">{{ auth()->user()->address->full_address }}</p>
                        </div>
                    @endif
                    <a href="{{ route('buyer.account') }}" class="w-full flex items-center justify-center py-2 px-3 rounded-xl border border-outline-variant font-label-md text-xs font-semibold text-on-surface hover:bg-surface-container transition-colors">
                        Edit Profile & Addresses
                    </a>
                </div>

                {{-- Shopping Shortcuts --}}
                <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-5 shadow-xs flex flex-col gap-3">
                    <p class="font-label-sm text-[10px] uppercase tracking-wider text-outline font-bold">Shopping Hub</p>

                    <a href="{{ url('/catalog') }}" class="flex items-center justify-between p-3 rounded-xl bg-surface-container-low hover:bg-surface-container transition-colors group">
                        <div class="flex flex-col">
                            <span class="text-xs font-bold text-on-surface group-hover:text-primary transition-colors">Browse Marketplace</span>
                            <span class="text-[11px] text-on-surface-variant">Discover newly listed products</span>
                        </div>
                        <span class="text-xs text-primary font-bold">&rarr;</span>
                    </a>

                    <a href="{{ url('/cart') }}" class="flex items-center justify-between p-3 rounded-xl bg-surface-container-low hover:bg-surface-container transition-colors group">
                        <div class="flex flex-col">
                            <span class="text-xs font-bold text-on-surface group-hover:text-primary transition-colors">Review Cart</span>
                            <span class="text-[11px] text-on-surface-variant">{{ $stats['cartItems'] }} item{{ $stats['cartItems'] === 1 ? '' : 's' }} awaiting checkout</span>
                        </div>
                        <span class="text-xs text-primary font-bold">&rarr;</span>
                    </a>

                    <a href="{{ route('buyer.orders') }}" class="flex items-center justify-between p-3 rounded-xl bg-surface-container-low hover:bg-surface-container transition-colors group">
                        <div class="flex flex-col">
                            <span class="text-xs font-bold text-on-surface group-hover:text-primary transition-colors">Track Deliveries</span>
                            <span class="text-[11px] text-on-surface-variant">Status milestones and history</span>
                        </div>
                        <span class="text-xs text-primary font-bold">&rarr;</span>
                    </a>
                </div>
            </div>

        </div>

    </div>
</div>
@endsection
