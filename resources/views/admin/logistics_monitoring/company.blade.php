@extends('layouts.app')
@section('title', 'Logistics Company Details — Admin Portal')
@section('content')
<div class="w-full min-h-screen bg-surface flex flex-col lg:flex-row">
    <x-admin-nav active="logistics-monitoring" />
    <main class="flex-1 p-6 lg:p-8 max-w-6xl mx-auto w-full space-y-6">
            <div>
                <a href="{{ route('admin.logistics-monitoring.index') }}" class="text-xs font-semibold text-primary hover:underline">&larr; Back to Logistics Monitoring</a>
                <h1 class="text-2xl font-bold text-on-surface mt-1">{{ $company->logisticsProfile?->company_name ?? $company->full_name }}</h1>
                <p class="text-sm text-on-surface-variant">{{ $company->email }} &bull; {{ $company->contact_number }}</p>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-surface-container-lowest p-4 rounded-2xl border border-outline-variant/60"><div class="text-xs text-on-surface-variant">Delivered / Failed</div><div class="text-lg font-bold">{{ $deliveredCount }} / {{ $failedCount }}</div></div>
                <div class="bg-surface-container-lowest p-4 rounded-2xl border border-outline-variant/60"><div class="text-xs text-on-surface-variant">Completed Transfers</div><div class="text-lg font-bold">{{ $completedTransfersCount }}</div></div>
                <div class="bg-surface-container-lowest p-4 rounded-2xl border border-outline-variant/60"><div class="text-xs text-on-surface-variant">Shipping Allocations</div><div class="text-lg font-bold">₱{{ number_format($shippingAllocations, 2) }}</div></div>
                <div class="bg-surface-container-lowest p-4 rounded-2xl border border-outline-variant/60"><div class="text-xs text-on-surface-variant">Courier Expenses</div><div class="text-lg font-bold">₱{{ number_format($courierExpenses, 2) }}</div></div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-surface-container-lowest p-5 rounded-2xl border border-outline-variant/60">
                    <h3 class="font-bold text-sm mb-3">Registered Hubs ({{ $hubs->count() }})</h3>
                    <ul class="space-y-2 text-xs">
                        @foreach ($hubs as $h)
                            <li><strong>{{ $h->name }}</strong> ({{ $h->code }}) — {{ $h->city_name }}</li>
                        @endforeach
                    </ul>
                </div>
                <div class="bg-surface-container-lowest p-5 rounded-2xl border border-outline-variant/60">
                    <h3 class="font-bold text-sm mb-3">Assigned Riders ({{ $riders->count() }})</h3>
                    <ul class="space-y-2 text-xs">
                        @foreach ($riders as $r)
                            <li>{{ $r->full_name }} ({{ $r->email }})</li>
                        @endforeach
                    </ul>
                </div>
                <div class="bg-surface-container-lowest p-5 rounded-2xl border border-outline-variant/60">
                    <h3 class="font-bold text-sm mb-3">Transfer Couriers ({{ $couriers->count() }})</h3>
                    <ul class="space-y-2 text-xs">
                        @foreach ($couriers as $c)
                            <li>{{ $c->full_name }} ({{ $c->email }})</li>
                        @endforeach
                    </ul>
                </div>
            </div>
    </main>
</div>
@endsection
