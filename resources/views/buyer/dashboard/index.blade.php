@extends('layouts.app')

@section('title', 'My Dashboard — Nexora Market')

@section('content')
<div class="w-full bg-surface py-8 min-h-[70vh]">
    <div class="max-w-none px-margin-mobile md:px-margin-desktop">

        <x-buyer-nav active="dashboard" />

        <div class="flex items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="font-headline-lg text-headline-lg text-on-surface">Welcome back, {{ auth()->user()->first_name }}</h1>
                <p class="font-body-md text-body-md text-on-surface-variant">Here's what's happening with your account.</p>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="flex items-center gap-2 rounded-full border border-outline-variant px-5 py-2.5 font-label-md text-label-md text-on-surface hover:bg-surface-container transition-colors">
                    <span class="material-symbols-outlined text-[18px]">logout</span>
                    Log Out
                </button>
            </form>
        </div>

        {{-- Stat cards --}}
        <div class="grid grid-cols-3 gap-4 mb-8">
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-5">
                <p class="font-body-sm text-body-sm text-on-surface-variant">In Your Cart</p>
                <p class="font-display-lg text-display-lg text-on-surface mt-1" style="font-size: 28px;">{{ $stats['cartItems'] }}</p>
            </div>
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-5">
                <p class="font-body-sm text-body-sm text-on-surface-variant">Active Orders</p>
                <p class="font-display-lg text-display-lg mt-1 {{ $stats['activeOrders'] > 0 ? 'text-primary' : 'text-on-surface' }}" style="font-size: 28px;">{{ $stats['activeOrders'] }}</p>
            </div>
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-5">
                <p class="font-body-sm text-body-sm text-on-surface-variant">Completed Orders</p>
                <p class="font-display-lg text-display-lg text-on-surface mt-1" style="font-size: 28px;">{{ $stats['completedOrders'] }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Recent orders --}}
            <div class="lg:col-span-2 bg-surface-container-lowest rounded-2xl border border-outline-variant overflow-hidden">
                <div class="px-6 py-4 border-b border-outline-variant bg-surface-container-low flex items-center justify-between">
                    <h2 class="font-headline-sm text-headline-sm text-on-surface">Recent Orders</h2>
                    <a href="{{ route('buyer.orders') }}" class="font-label-md text-label-md text-primary hover:underline">View All</a>
                </div>
                @if($recentOrders->isEmpty())
                    <div class="p-10 text-center flex flex-col items-center gap-3">
                        <span class="material-symbols-outlined text-[32px] text-outline">shopping_bag</span>
                        <p class="font-body-sm text-body-sm text-on-surface-variant">You haven't placed any orders yet.</p>
                        <a href="{{ url('/catalog') }}" class="inline-flex items-center gap-2 bg-primary text-on-primary font-label-md text-label-md px-5 py-2.5 rounded-full hover:bg-primary/90 transition-colors">
                            Browse Catalog
                        </a>
                    </div>
                @else
                    <div class="divide-y divide-outline-variant">
                        @foreach($recentOrders as $order)
                            <div class="flex items-center justify-between px-6 py-4">
                                <div>
                                    <p class="font-label-md text-label-md text-on-surface">{{ $order->order_number }}</p>
                                    <p class="font-body-sm text-body-sm text-on-surface-variant">{{ $order->items->count() }} item{{ $order->items->count() === 1 ? '' : 's' }} · {{ $order->placed_at->format('M j') }}</p>
                                </div>
                                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-primary/10 text-primary">{{ ucfirst(str_replace('_', ' ', $order->status)) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Quick links --}}
            <div class="flex flex-col gap-4">
                <a href="{{ url('/catalog') }}" class="flex items-center gap-3 bg-primary text-on-primary rounded-2xl p-5 hover:bg-primary/90 transition-colors">
                    <span class="material-symbols-outlined text-[24px]">storefront</span>
                    <div>
                        <p class="font-label-md text-label-md">Browse Catalog</p>
                        <p class="font-body-sm text-body-sm text-on-primary/80">Find something new</p>
                    </div>
                </a>
                <a href="{{ url('/cart') }}" class="flex items-center gap-3 bg-surface-container-lowest border border-outline-variant rounded-2xl p-5 hover:bg-surface-container transition-colors">
                    <span class="material-symbols-outlined text-[24px] text-on-surface-variant">shopping_cart</span>
                    <div>
                        <p class="font-label-md text-label-md text-on-surface">View Cart</p>
                        <p class="font-body-sm text-body-sm text-on-surface-variant">{{ $stats['cartItems'] }} item{{ $stats['cartItems'] === 1 ? '' : 's' }} waiting</p>
                    </div>
                </a>
                <a href="{{ route('buyer.account') }}" class="flex items-center gap-3 bg-surface-container-lowest border border-outline-variant rounded-2xl p-5 hover:bg-surface-container transition-colors">
                    <span class="material-symbols-outlined text-[24px] text-on-surface-variant">account_circle</span>
                    <div>
                        <p class="font-label-md text-label-md text-on-surface">My Account</p>
                        <p class="font-body-sm text-body-sm text-on-surface-variant">Update your info</p>
                    </div>
                </a>
            </div>

        </div>

    </div>
</div>
@endsection
