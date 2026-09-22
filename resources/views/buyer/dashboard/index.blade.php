@extends('layouts.app')

@section('title', 'Buyer Dashboard — Nexora Market')

@section('content')
    <x-dashboard-placeholder
        title="Buyer Dashboard"
        icon="shopping_bag"
        accent="bg-primary"
        :features="[
            'Browse categories and search products',
            'View cart and place orders',
            'Track order status through delivery',
            'Chat with sellers',
            'Account management',
        ]" />

    {{-- The buyer storefront already exists, so link straight into it --}}
    <div class="w-full bg-surface pb-12">
        <div class="max-w-none px-margin-mobile md:px-margin-desktop">
            <div class="bg-surface-container-low rounded-2xl p-6 flex flex-col sm:flex-row sm:items-center gap-4">
                <div class="flex-1">
                    <h3 class="font-headline-sm text-headline-sm text-on-surface">The storefront is already live</h3>
                    <p class="font-body-sm text-body-sm text-on-surface-variant mt-1">
                        Catalog, product pages, cart, checkout, and order tracking are all built. Jump in from here.
                    </p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <a href="{{ url('/catalog') }}" class="inline-flex items-center gap-2 bg-primary text-on-primary font-label-md text-label-md px-5 py-2.5 rounded-full hover:bg-primary/90 transition-colors">
                        Browse Catalog
                    </a>
                    <a href="{{ url('/buyer/orders') }}" class="inline-flex items-center gap-2 border border-outline-variant text-on-surface font-label-md text-label-md px-5 py-2.5 rounded-full hover:bg-surface-container transition-colors">
                        My Orders
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection
