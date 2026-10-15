@extends('layouts.app')

@section('title', 'Seller Dashboard — Nexora Market')

@section('content')
<div class="w-full bg-surface py-8 min-h-[70vh]">
    <div class="max-w-none px-margin-mobile md:px-margin-desktop">

        <x-seller-nav active="dashboard" />

        <div class="flex items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="font-headline-lg text-headline-lg text-on-surface">Welcome back, {{ auth()->user()->sellerProfile->business_name ?? auth()->user()->first_name }}</h1>
                <p class="font-body-md text-body-md text-on-surface-variant">Here's how your store is doing.</p>
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
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-5">
                <p class="font-body-sm text-body-sm text-on-surface-variant">Active Products</p>
                <p class="font-display-lg text-display-lg text-on-surface mt-1" style="font-size: 28px;">{{ $stats['activeProducts'] }}</p>
            </div>
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-5">
                <p class="font-body-sm text-body-sm text-on-surface-variant">Low Stock</p>
                <p class="font-display-lg text-display-lg mt-1 {{ $stats['lowStock'] > 0 ? 'text-tertiary' : 'text-on-surface' }}" style="font-size: 28px;">{{ $stats['lowStock'] }}</p>
            </div>
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-5">
                <p class="font-body-sm text-body-sm text-on-surface-variant">New Orders</p>
                <p class="font-display-lg text-display-lg mt-1 {{ $stats['newOrders'] > 0 ? 'text-primary' : 'text-on-surface' }}" style="font-size: 28px;">{{ $stats['newOrders'] }}</p>
            </div>
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-5">
                <p class="font-body-sm text-body-sm text-on-surface-variant">Revenue This Month</p>
                <p class="font-display-lg text-display-lg text-on-surface mt-1" style="font-size: 24px;">₱{{ number_format($stats['revenueThisMonth'], 2) }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Recent orders --}}
            <div class="lg:col-span-2 bg-surface-container-lowest rounded-2xl border border-outline-variant overflow-hidden">
                <div class="px-6 py-4 border-b border-outline-variant bg-surface-container-low flex items-center justify-between">
                    <h2 class="font-headline-sm text-headline-sm text-on-surface">Recent Orders</h2>
                    <a href="{{ route('seller.orders.index') }}" class="font-label-md text-label-md text-primary hover:underline">View All</a>
                </div>
                @if($recentOrders->isEmpty())
                    <div class="p-10 text-center">
                        <p class="font-body-sm text-body-sm text-on-surface-variant">No orders yet.</p>
                    </div>
                @else
                    <div class="divide-y divide-outline-variant">
                        @foreach($recentOrders as $order)
                            <a href="{{ route('seller.orders.show', $order) }}" class="flex items-center justify-between px-6 py-4 hover:bg-surface-container-low transition-colors">
                                <div>
                                    <p class="font-label-md text-label-md text-on-surface">{{ $order->order_number }}</p>
                                    <p class="font-body-sm text-body-sm text-on-surface-variant">{{ $order->buyer->full_name }} · {{ $order->placed_at->format('M j') }}</p>
                                </div>
                                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-primary/10 text-primary">{{ ucfirst(str_replace('_', ' ', $order->status)) }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Quick links --}}
            <div class="flex flex-col gap-4">
                <a href="{{ route('seller.products.create') }}" class="flex items-center gap-3 bg-primary text-on-primary rounded-2xl p-5 hover:bg-primary/90 transition-colors">
                    <span class="material-symbols-outlined text-[24px]">add_circle</span>
                    <div>
                        <p class="font-label-md text-label-md">Add Product</p>
                        <p class="font-body-sm text-body-sm text-on-primary/80">List something new</p>
                    </div>
                </a>
                <a href="{{ route('seller.products.index') }}" class="flex items-center gap-3 bg-surface-container-lowest border border-outline-variant rounded-2xl p-5 hover:bg-surface-container transition-colors">
                    <span class="material-symbols-outlined text-[24px] text-on-surface-variant">inventory_2</span>
                    <div>
                        <p class="font-label-md text-label-md text-on-surface">Manage Inventory</p>
                        <p class="font-body-sm text-body-sm text-on-surface-variant">Edit prices, stock, and more</p>
                    </div>
                </a>
                <a href="{{ route('seller.reports.index') }}" class="flex items-center gap-3 bg-surface-container-lowest border border-outline-variant rounded-2xl p-5 hover:bg-surface-container transition-colors">
                    <span class="material-symbols-outlined text-[24px] text-on-surface-variant">bar_chart</span>
                    <div>
                        <p class="font-label-md text-label-md text-on-surface">View Reports</p>
                        <p class="font-body-sm text-body-sm text-on-surface-variant">Sales and performance</p>
                    </div>
                </a>
            </div>

        </div>

    </div>
</div>
@endsection
