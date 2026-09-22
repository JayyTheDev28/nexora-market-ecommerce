@extends('layouts.app')

@section('title', 'Seller Dashboard — Nexora Market')

@section('content')
    <x-dashboard-placeholder
        title="Seller Dashboard"
        icon="storefront"
        accent="bg-tertiary"
        :features="[
            'Dashboard overview (stats, charts)',
            'Manage inventory — add, update, archive products',
            'Set prices, discounts, and vouchers',
            'Order notifications and order details',
            'Prepare orders, print waybill / shipping label',
            'Hand over to courier, track shipment',
            'Handle customer feedback',
            'Generate financial and sales reports',
        ]" />

    @if(auth()->user()->sellerProfile)
        <div class="w-full bg-surface pb-12">
            <div class="max-w-none px-margin-mobile md:px-margin-desktop">
                <div class="bg-surface-container-low rounded-2xl p-6">
                    <h3 class="font-headline-sm text-headline-sm text-on-surface mb-3">Your Business</h3>
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 font-body-sm text-body-sm">
                        <div class="flex gap-2">
                            <dt class="text-on-surface-variant">Business Name:</dt>
                            <dd class="text-on-surface">{{ auth()->user()->sellerProfile->business_name }}</dd>
                        </div>
                        <div class="flex gap-2">
                            <dt class="text-on-surface-variant">Line of Business:</dt>
                            <dd class="text-on-surface capitalize">{{ str_replace('-', ' ', auth()->user()->sellerProfile->category) }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    @endif
@endsection
