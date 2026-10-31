@extends('layouts.app')

@section('title', 'Seller Operations Hub — Nexora Market')

@section('content')
<div class="w-full bg-surface py-8 min-h-[70vh]">
    <div class="max-w-none px-margin-mobile md:px-margin-desktop">

        <x-seller-nav active="dashboard" />

        <x-platform-announcements />

        @if(!empty($stats['vacationMode']))
            <div class="mb-6 p-4 rounded-2xl bg-amber-500/10 border border-amber-500/30 flex items-start justify-between gap-4">
                <div class="flex items-start gap-3">
                    <span class="material-symbols-outlined text-amber-500 text-[24px]">beach_access</span>
                    <div>
                        <h2 class="font-label-lg font-bold text-on-surface">Store is Currently in Vacation Mode</h2>
                        <p class="font-body-sm text-on-surface-variant mt-0.5">
                            {{ $stats['vacationNotice'] ?: 'New order placement is paused while you are away. Your existing orders can still be fulfilled.' }}
                        </p>
                    </div>
                </div>
                <a href="{{ route('seller.store.edit') }}" class="px-3.5 py-1.5 rounded-full text-xs font-bold border border-amber-500 text-amber-600 dark:text-amber-400 hover:bg-amber-500/20 transition-colors">
                    Manage Vacation
                </a>
            </div>
        @endif

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="font-headline-lg text-headline-lg text-on-surface">
                        {{ auth()->user()->sellerProfile->business_name ?? auth()->user()->first_name }}
                    </h1>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-600 border border-emerald-500/20 flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px]">verified</span> Active Merchant
                    </span>
                </div>
                <p class="font-body-md text-body-md text-on-surface-variant mt-1">Operational command center, inventory, sales, and buyer fulfillment.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('stores.show', auth()->id()) }}" target="_blank" class="flex items-center gap-1.5 rounded-full border border-outline-variant px-4 py-2 font-label-md text-label-md text-on-surface hover:bg-surface-container transition-colors">
                    <span class="material-symbols-outlined text-[18px]">open_in_new</span>
                    View My Store
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex items-center gap-1.5 rounded-full border border-outline-variant px-4 py-2 font-label-md text-label-md text-on-surface hover:bg-surface-container transition-colors">
                        <span class="material-symbols-outlined text-[18px]">logout</span>
                        Log Out
                    </button>
                </form>
            </div>
        </div>

        {{-- Stat cards --}}
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-8">
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4">
                <p class="font-body-sm text-xs text-on-surface-variant font-medium">Active Products</p>
                <p class="font-headline-md text-headline-md text-on-surface mt-1 font-bold">{{ $stats['activeProducts'] }}</p>
                <a href="{{ route('seller.products.index') }}" class="text-[11px] text-primary hover:underline mt-2 inline-block">Manage catalog →</a>
            </div>
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4">
                <p class="font-body-sm text-xs text-on-surface-variant font-medium">Low Stock Alerts</p>
                <p class="font-headline-md text-headline-md mt-1 font-bold {{ $stats['lowStock'] > 0 ? 'text-amber-500' : 'text-on-surface' }}">{{ $stats['lowStock'] }}</p>
                <a href="{{ route('seller.inventory.index', ['filter' => 'low_stock']) }}" class="text-[11px] text-primary hover:underline mt-2 inline-block">Restock now →</a>
            </div>
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4">
                <p class="font-body-sm text-xs text-on-surface-variant font-medium">New Orders</p>
                <p class="font-headline-md text-headline-md mt-1 font-bold {{ $stats['newOrders'] > 0 ? 'text-primary' : 'text-on-surface' }}">{{ $stats['newOrders'] }}</p>
                <a href="{{ route('seller.orders.index', ['status' => 'to_confirm']) }}" class="text-[11px] text-primary hover:underline mt-2 inline-block">Confirm orders →</a>
            </div>
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4">
                <p class="font-body-sm text-xs text-on-surface-variant font-medium">To Pack & Ship</p>
                <p class="font-headline-md text-headline-md mt-1 font-bold {{ $stats['ordersToPack'] > 0 ? 'text-blue-500' : 'text-on-surface' }}">{{ $stats['ordersToPack'] }}</p>
                <a href="{{ route('seller.orders.index', ['status' => 'processing']) }}" class="text-[11px] text-primary hover:underline mt-2 inline-block">Prepare parcels →</a>
            </div>
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4">
                <p class="font-body-sm text-xs text-on-surface-variant font-medium">Returns Requiring Review</p>
                <p class="font-headline-md text-headline-md mt-1 font-bold {{ $stats['openReturns'] > 0 ? 'text-error' : 'text-on-surface' }}">{{ $stats['openReturns'] }}</p>
                <a href="{{ route('seller.returns.index', ['status' => 'pending']) }}" class="text-[11px] text-primary hover:underline mt-2 inline-block">Review claims →</a>
            </div>
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4">
                <p class="font-body-sm text-xs text-on-surface-variant font-medium">Revenue This Month</p>
                <p class="font-headline-md text-headline-md text-on-surface mt-1 font-bold text-[20px]">₱{{ number_format($stats['revenueThisMonth'], 2) }}</p>
                <a href="{{ route('seller.reports.index') }}" class="text-[11px] text-primary hover:underline mt-2 inline-block">Financial report →</a>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">

            {{-- Actionable orders needing immediate fulfillment --}}
            <div class="lg:col-span-2 bg-surface-container-lowest rounded-2xl border border-outline-variant overflow-hidden">
                <div class="px-6 py-4 border-b border-outline-variant bg-surface-container-low flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary text-[20px]">bolt</span>
                        <h2 class="font-headline-sm text-headline-sm text-on-surface">Orders Requiring Action</h2>
                    </div>
                    <a href="{{ route('seller.orders.index') }}" class="font-label-md text-label-md text-primary hover:underline">View All Orders</a>
                </div>
                @if($actionableOrders->isEmpty())
                    <div class="p-10 text-center">
                        <span class="material-symbols-outlined text-[36px] text-outline-variant mb-2">task_alt</span>
                        <p class="font-body-sm text-body-sm text-on-surface-variant">All orders are up to date! No orders waiting to be packed or confirmed.</p>
                    </div>
                @else
                    <div class="divide-y divide-outline-variant">
                        @foreach($actionableOrders as $order)
                            <div class="flex items-center justify-between px-6 py-4 hover:bg-surface-container-low transition-colors">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('seller.orders.show', $order) }}" class="font-label-md text-label-md font-bold text-primary hover:underline">
                                            {{ $order->order_number }}
                                        </a>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $order->status === 'placed' ? 'bg-primary/10 text-primary' : 'bg-blue-500/10 text-blue-600' }}">
                                            {{ str_replace('_', ' ', $order->status) }}
                                        </span>
                                    </div>
                                    <p class="font-body-sm text-xs text-on-surface-variant mt-1">
                                        Buyer: {{ $order->buyer->full_name }} · {{ $order->items->count() }} items · ₱{{ number_format($order->total, 2) }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('seller.orders.waybill', $order) }}" target="_blank" class="px-3 py-1.5 rounded-lg border border-outline-variant text-xs font-semibold text-on-surface hover:bg-surface-container transition-colors flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[14px]">print</span> Waybill
                                    </a>
                                    <a href="{{ route('seller.orders.show', $order) }}" class="px-3 py-1.5 rounded-lg bg-primary text-on-primary text-xs font-semibold hover:bg-primary/90 transition-colors">
                                        Process Order
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Quick Launchpad --}}
            <div class="flex flex-col gap-3">
                <a href="{{ route('seller.products.create') }}" class="flex items-center gap-3 bg-primary text-on-primary rounded-2xl p-4 hover:bg-primary/90 transition-colors shadow-sm">
                    <span class="material-symbols-outlined text-[26px]">add_circle</span>
                    <div>
                        <p class="font-label-md text-label-md font-bold">Add New Product</p>
                        <p class="font-body-sm text-xs text-on-primary/80">Publish with variants & gallery</p>
                    </div>
                </a>
                <a href="{{ route('seller.vouchers.index') }}" class="flex items-center gap-3 bg-surface-container-lowest border border-outline-variant rounded-2xl p-4 hover:bg-surface-container transition-colors">
                    <span class="material-symbols-outlined text-[26px] text-amber-500">confirmation_number</span>
                    <div>
                        <p class="font-label-md text-label-md text-on-surface font-bold">Store Vouchers</p>
                        <p class="font-body-sm text-xs text-on-surface-variant">Create discount codes for buyers</p>
                    </div>
                </a>
                <a href="{{ route('seller.promotions.index') }}" class="flex items-center gap-3 bg-surface-container-lowest border border-outline-variant rounded-2xl p-4 hover:bg-surface-container transition-colors">
                    <span class="material-symbols-outlined text-[26px] text-emerald-500">percent</span>
                    <div>
                        <p class="font-label-md text-label-md text-on-surface font-bold">Flash Sales & Deals</p>
                        <p class="font-body-sm text-xs text-on-surface-variant">Schedule countdown promotional sales</p>
                    </div>
                </a>
                <a href="{{ route('seller.chat.index') }}" class="flex items-center gap-3 bg-surface-container-lowest border border-outline-variant rounded-2xl p-4 hover:bg-surface-container transition-colors">
                    <span class="material-symbols-outlined text-[26px] text-blue-500">chat</span>
                    <div>
                        <p class="font-label-md text-label-md text-on-surface font-bold">Customer Conversations</p>
                        <p class="font-body-sm text-xs text-on-surface-variant">Inquire with buyers and provide support</p>
                    </div>
                </a>
                <a href="{{ route('seller.store.edit') }}" class="flex items-center gap-3 bg-surface-container-lowest border border-outline-variant rounded-2xl p-4 hover:bg-surface-container transition-colors">
                    <span class="material-symbols-outlined text-[26px] text-purple-500">storefront</span>
                    <div>
                        <p class="font-label-md text-label-md text-on-surface font-bold">Store Profile & Vacation Mode</p>
                        <p class="font-body-sm text-xs text-on-surface-variant">Logo, banner, contacts & vacation</p>
                    </div>
                </a>
            </div>

        </div>

        {{-- Pending returns section if any exist --}}
        @if($recentDisputes->isNotEmpty())
            <div class="bg-surface-container-lowest rounded-2xl border border-error/30 mb-8 overflow-hidden">
                <div class="px-6 py-4 bg-error/5 border-b border-error/20 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-error text-[20px]">warning</span>
                        <h2 class="font-headline-sm text-headline-sm text-on-surface">Return & Refund Requests Awaiting Your Review</h2>
                    </div>
                    <a href="{{ route('seller.returns.index') }}" class="font-label-md text-label-md text-error hover:underline">View All Returns</a>
                </div>
                <div class="divide-y divide-outline-variant">
                    @foreach($recentDisputes as $dispute)
                        <div class="flex items-center justify-between px-6 py-4 hover:bg-surface-container-low transition-colors">
                            <div>
                                <p class="font-label-md text-label-md font-bold text-on-surface">Order {{ $dispute->order->order_number }}</p>
                                <p class="font-body-sm text-xs text-on-surface-variant mt-0.5">
                                    Reason: <span class="font-medium text-on-surface">{{ $dispute->reason }}</span> · Raised by {{ $dispute->raisedBy->full_name }} ({{ $dispute->created_at->diffForHumans() }})
                                </p>
                            </div>
                            <a href="{{ route('seller.returns.show', $dispute) }}" class="px-3.5 py-1.5 rounded-lg border border-error/40 text-error hover:bg-error/10 text-xs font-bold transition-colors">
                                Inspect & Respond
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Recent Order History --}}
        <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant overflow-hidden">
            <div class="px-6 py-4 border-b border-outline-variant bg-surface-container-low flex items-center justify-between">
                <h2 class="font-headline-sm text-headline-sm text-on-surface">Recent Order Stream</h2>
                <a href="{{ route('seller.orders.index') }}" class="font-label-md text-label-md text-primary hover:underline">Full Order History</a>
            </div>
            @if($recentOrders->isEmpty())
                <div class="p-10 text-center">
                    <p class="font-body-sm text-body-sm text-on-surface-variant">No orders placed yet.</p>
                </div>
            @else
                <div class="divide-y divide-outline-variant">
                    @foreach($recentOrders as $order)
                        <div class="flex items-center justify-between px-6 py-3.5 hover:bg-surface-container-low transition-colors">
                            <div class="flex items-center gap-3">
                                <span class="material-symbols-outlined text-outline-variant text-[22px]">shopping_bag</span>
                                <div>
                                    <a href="{{ route('seller.orders.show', $order) }}" class="font-label-md text-label-md font-bold text-on-surface hover:text-primary">
                                        {{ $order->order_number }}
                                    </a>
                                    <p class="font-body-sm text-xs text-on-surface-variant">{{ $order->buyer->full_name }} · {{ $order->placed_at->format('M j, Y H:i') }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-4">
                                <span class="font-label-md text-label-md text-on-surface font-bold">₱{{ number_format($order->total, 2) }}</span>
                                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-primary/10 text-primary capitalize">
                                    {{ str_replace('_', ' ', $order->status) }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

    </div>
</div>
@endsection
