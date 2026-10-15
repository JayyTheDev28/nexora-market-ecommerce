@extends('layouts.app')

@section('title', 'Reports — Seller Dashboard')

@section('content')
<div class="w-full bg-surface py-8 min-h-[70vh]">
    <div class="max-w-none px-margin-mobile md:px-margin-desktop">

        <x-seller-nav active="reports" />

        <h1 class="font-headline-lg text-headline-lg text-on-surface mb-6">Sales &amp; Performance Report</h1>

        {{-- Date range picker --}}
        <form method="GET" class="flex flex-wrap items-end gap-4 mb-8 bg-surface-container-lowest rounded-2xl border border-outline-variant p-5">
            <div class="flex flex-col gap-2">
                <label class="font-label-md text-label-md text-on-surface-variant">From</label>
                <input type="date" name="from" value="{{ $from->toDateString() }}" class="rounded-xl border border-outline-variant px-3.5 py-2.5 font-body-md text-body-md text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
            </div>
            <div class="flex flex-col gap-2">
                <label class="font-label-md text-label-md text-on-surface-variant">To</label>
                <input type="date" name="to" value="{{ $to->toDateString() }}" class="rounded-xl border border-outline-variant px-3.5 py-2.5 font-body-md text-body-md text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
            </div>
            <button type="submit" class="bg-primary text-on-primary font-label-md text-label-md px-6 py-2.5 rounded-full hover:bg-primary/90 transition-colors">
                Update
            </button>
        </form>

        <p class="font-body-sm text-body-sm text-on-surface-variant mb-4">
            Showing results from {{ $from->format('M j, Y') }} to {{ $to->format('M j, Y') }}
        </p>

        {{-- Summary cards --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-5">
                <p class="font-body-sm text-body-sm text-on-surface-variant">Orders</p>
                <p class="font-display-lg text-display-lg text-on-surface mt-1" style="font-size: 26px;">{{ $orderCount }}</p>
            </div>
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-5">
                <p class="font-body-sm text-body-sm text-on-surface-variant">Gross Sales</p>
                <p class="font-display-lg text-display-lg text-on-surface mt-1" style="font-size: 22px;">₱{{ number_format($grossSales, 2) }}</p>
            </div>
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-5">
                <p class="font-body-sm text-body-sm text-on-surface-variant">Platform Commission (10%)</p>
                <p class="font-display-lg text-display-lg text-error mt-1" style="font-size: 22px;">-₱{{ number_format($commission, 2) }}</p>
            </div>
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-5">
                <p class="font-body-sm text-body-sm text-on-surface-variant">Net Payout</p>
                <p class="font-display-lg text-display-lg text-primary mt-1" style="font-size: 22px;">₱{{ number_format($netPayout, 2) }}</p>
            </div>
        </div>

        {{-- Orders by status --}}
        <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-6">
            <h2 class="font-headline-sm text-headline-sm text-on-surface mb-4">Orders by Status</h2>
            @if($byStatus->isEmpty())
                <p class="font-body-sm text-body-sm text-on-surface-variant">No orders in this date range.</p>
            @else
                <div class="flex flex-col gap-3">
                    @foreach($byStatus as $status => $count)
                        <div class="flex items-center justify-between">
                            <span class="font-body-sm text-body-sm text-on-surface">{{ ucfirst(str_replace('_', ' ', $status)) }}</span>
                            <span class="font-label-md text-label-md text-on-surface">{{ $count }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

    </div>
</div>
@endsection
