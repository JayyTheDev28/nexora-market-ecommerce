@extends('layouts.app')

@section('title', 'Rider Dashboard — Nexora Logistics')

@section('content')
<div class="w-full bg-surface py-6 min-h-[85vh]" x-data="{
    completeModalOpen: false,
    failModalOpen: false,
    vehicleModalOpen: false,
    activeOrderId: null,
    activeOrderNumber: '',
    activeOrderTotal: 0,
    isCod: false,
    submitting: false
}">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- Alerts --}}
        @if (session('status'))
            <div class="mb-6 bg-primary/10 border border-primary/30 rounded-2xl p-4 flex items-center gap-3 shadow-2xs">
                <span class="material-symbols-outlined text-primary text-[22px]">check_circle</span>
                <p class="font-body-sm text-xs md:text-sm text-primary font-semibold">{{ session('status') }}</p>
            </div>
        @endif

        <x-platform-announcements />

        @if (session('info'))
            <div class="mb-6 bg-amber-500/10 border border-amber-500/30 rounded-2xl p-4 flex items-center gap-3 shadow-2xs">
                <span class="material-symbols-outlined text-amber-700 text-[22px]">info</span>
                <p class="font-body-sm text-xs md:text-sm text-amber-900 font-semibold">{{ session('info') }}</p>
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 bg-rose-500/10 border border-rose-500/20 rounded-2xl p-4">
                <div class="flex items-center gap-2 mb-2 text-rose-700 font-bold text-xs">
                    <span class="material-symbols-outlined text-[18px]">error</span>
                    <span>Action could not be completed:</span>
                </div>
                <ul class="list-disc list-inside text-xs text-rose-700 space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- R1 & R2: Rider Profile & Availability Header Card --}}
        <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 md:p-8 shadow-xs mb-8">
            <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-6">
                
                {{-- Profile Info --}}
                <div class="flex items-start md:items-center gap-4">
                    <div class="w-16 h-16 rounded-2xl bg-primary/10 text-primary font-bold text-2xl flex items-center justify-center flex-shrink-0 shadow-2xs">
                        {{ strtoupper(substr($rider->first_name, 0, 1)) }}
                    </div>
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <h1 class="font-headline-lg text-xl md:text-2xl font-bold text-on-surface">{{ $rider->full_name }}</h1>
                            <span class="px-2.5 py-0.5 rounded-full {{ $rider->isActive() ? 'bg-emerald-500/10 text-emerald-800' : 'bg-rose-500/10 text-rose-800' }} text-[11px] font-semibold flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full {{ $rider->isActive() ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>
                                {{ $rider->isActive() ? 'Active Courier' : ucfirst($rider->account_status) }}
                            </span>

                            {{-- Online / Offline Badge --}}
                            <span class="px-2.5 py-0.5 rounded-full {{ $stats['is_online'] ? 'bg-primary/10 text-primary' : 'bg-surface-container text-on-surface-variant' }} text-[11px] font-semibold flex items-center gap-1 border border-outline-variant/60">
                                <span class="w-2 h-2 rounded-full {{ $stats['is_online'] ? 'bg-primary animate-pulse' : 'bg-outline' }}"></span>
                                {{ $stats['is_online'] ? 'ONLINE (Accepting Jobs)' : 'OFFLINE' }}
                            </span>
                        </div>
                        <p class="text-xs text-on-surface-variant mt-1">
                            {{ $rider->email }} · {{ $rider->contact_number }}
                        </p>
                        @if($profile?->sortingCenter)
                            <p class="text-[11px] text-primary font-medium mt-0.5 flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">warehouse</span>
                                <span>Hub: {{ $profile->sortingCenter->sortingCenterProfile?->business_name ?? $profile->sortingCenter->full_name }}</span>
                            </p>
                        @endif
                    </div>
                </div>

                {{-- Availability Toggle & Vehicle Actions --}}
                <div class="flex flex-wrap items-center gap-3 w-full lg:w-auto">
                    {{-- Toggle Online Form --}}
                    <form method="POST" action="{{ route('courier.toggle-online') }}" class="inline">
                        @csrf
                        <input type="hidden" name="is_online" value="{{ $stats['is_online'] ? 0 : 1 }}">
                        <button type="submit"
                            class="px-4 py-2.5 rounded-2xl text-xs font-bold transition-all shadow-xs flex items-center gap-2 {{ $stats['is_online'] ? 'bg-surface-container-high hover:bg-surface-container-highest text-on-surface border border-outline-variant' : 'bg-primary hover:bg-primary-hover text-on-primary' }}">
                            <span class="material-symbols-outlined text-[18px]">{{ $stats['is_online'] ? 'power_settings_new' : 'wifi' }}</span>
                            <span>{{ $stats['is_online'] ? 'Go Offline' : 'Go Online' }}</span>
                        </button>
                    </form>

                    {{-- Vehicle Update Button --}}
                    <button type="button" @click="vehicleModalOpen = true"
                        class="px-4 py-2.5 rounded-2xl text-xs font-semibold bg-surface-container hover:bg-surface-container-high text-on-surface border border-outline-variant transition-all flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px]">two_wheeler</span>
                        <span>Vehicle Info</span>
                        @if($profile?->vehicle_verification_status === 'pending')
                            <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse" title="Update Pending Logistics Review"></span>
                        @endif
                    </button>

                    {{-- Earnings Hub Link --}}
                    <a href="{{ route('courier.earnings') }}"
                        class="px-4 py-2.5 rounded-2xl text-xs font-semibold bg-primary/10 hover:bg-primary/20 text-primary border border-primary/20 transition-all flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px]">payments</span>
                        <span>Earnings & COD</span>
                    </a>
                </div>
            </div>

            {{-- Vehicle Details Strip --}}
            @if($profile)
                <div class="mt-6 pt-6 border-t border-outline-variant/60 grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                    <div class="bg-surface-container/60 rounded-2xl p-3 border border-outline-variant/50">
                        <span class="text-[10px] text-on-surface-variant uppercase font-medium block">Vehicle Type</span>
                        <span class="font-bold text-on-surface">{{ $profile->vehicle_type }}</span>
                    </div>
                    <div class="bg-surface-container/60 rounded-2xl p-3 border border-outline-variant/50">
                        <span class="text-[10px] text-on-surface-variant uppercase font-medium block">Plate Number</span>
                        <span class="font-mono font-bold text-on-surface">{{ $profile->plate_number }}</span>
                    </div>
                    <div class="bg-surface-container/60 rounded-2xl p-3 border border-outline-variant/50">
                        <span class="text-[10px] text-on-surface-variant uppercase font-medium block">Driver's License</span>
                        <span class="font-mono font-bold text-on-surface">{{ $profile->license_number ?: 'N/A' }}</span>
                    </div>
                    <div class="bg-surface-container/60 rounded-2xl p-3 border border-outline-variant/50">
                        <span class="text-[10px] text-on-surface-variant uppercase font-medium block">Vehicle Status</span>
                        <span class="font-bold {{ $profile->vehicle_verification_status === 'verified' ? 'text-emerald-700' : ($profile->vehicle_verification_status === 'pending' ? 'text-amber-700' : 'text-rose-700') }}">
                            {{ ucfirst($profile->vehicle_verification_status) }}
                        </span>
                    </div>
                </div>

                @if($profile->vehicle_verification_status === 'pending')
                    <div class="mt-3 bg-amber-500/10 border border-amber-500/30 rounded-2xl p-3 text-xs text-amber-900 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px] text-amber-700 flex-shrink-0">hourglass_top</span>
                        <span>You submitted a vehicle update (Plate: <strong>{{ $profile->pending_plate_number }}</strong>, Type: <strong>{{ $profile->pending_vehicle_type }}</strong>). It is currently pending review by your logistics company.</span>
                    </div>
                @endif
            @endif
        </div>

        {{-- Stat Cards --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4 shadow-2xs">
                <span class="text-[11px] text-on-surface-variant font-medium block">Active Jobs Held</span>
                <div class="flex items-baseline gap-1 mt-1">
                    <span class="text-2xl font-bold {{ $stats['active_jobs'] >= 3 ? 'text-rose-600' : 'text-on-surface' }}">{{ $stats['active_jobs'] }}</span>
                    <span class="text-xs text-on-surface-variant font-medium">/ 3 max</span>
                </div>
                <span class="text-[10px] text-outline mt-1 block">Maximum 3 concurrent jobs</span>
            </div>

            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4 shadow-2xs">
                <span class="text-[11px] text-on-surface-variant font-medium block">Today's Earnings</span>
                <span class="text-2xl font-bold text-primary mt-1 block">₱{{ number_format($stats['today_earnings'], 2) }}</span>
                <span class="text-[10px] text-outline mt-1 block">{{ $stats['today_pickups'] }} pickups · {{ $stats['today_deliveries'] }} deliveries</span>
            </div>

            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4 shadow-2xs">
                <span class="text-[11px] text-on-surface-variant font-medium block">Unremitted COD Balance</span>
                <span class="text-2xl font-bold {{ $stats['unremitted_cod'] > 0 ? 'text-amber-800' : 'text-on-surface' }} mt-1 block">₱{{ number_format($stats['unremitted_cod'], 2) }}</span>
                <span class="text-[10px] text-outline mt-1 block">Must remit EOD to Hub</span>
            </div>

            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4 shadow-2xs">
                <span class="text-[11px] text-on-surface-variant font-medium block">Weekly Total Pay</span>
                <span class="text-2xl font-bold text-emerald-800 mt-1 block">₱{{ number_format($stats['weekly_earnings'], 2) }}</span>
                <span class="text-[10px] text-outline mt-1 block">₱30 pickup / ₱50 delivery</span>
            </div>
        </div>

        {{-- Module R3: 60-Second Job Offer Engine Section --}}
        @if($pendingOffers->count() > 0)
            <div class="mb-8">
                <div class="flex items-center gap-2 mb-3">
                    <span class="material-symbols-outlined text-amber-800 text-[22px] animate-bounce">notifications_active</span>
                    <h2 class="text-base font-bold text-on-surface">Incoming Job Offers (60-Second SLA Engine)</h2>
                </div>

                <div class="space-y-4">
                    @foreach($pendingOffers as $offer)
                        @php
                            $remainingSeconds = max(0, now()->diffInSeconds($offer->expires_at, false));
                        @endphp
                        <div class="bg-surface-container-lowest rounded-3xl border-2 border-primary/40 p-5 md:p-6 shadow-md relative overflow-hidden"
                            x-data="{ timeLeft: {{ $remainingSeconds }}, timer: null }"
                            x-init="timer = setInterval(() => { if (timeLeft > 0) { timeLeft-- } else { clearInterval(timer); location.reload(); } }, 1000)">
                            
                            {{-- Timer Progress Banner --}}
                            <div class="bg-primary/10 border-b border-primary/20 -mx-5 -mt-5 md:-mx-6 md:-mt-6 p-3 px-6 flex items-center justify-between text-xs mb-4">
                                <div class="flex items-center gap-2 text-primary font-bold">
                                    <span class="material-symbols-outlined text-[18px]">timer</span>
                                    <span>60s Response Window:</span>
                                    <span class="font-mono text-sm" x-text="timeLeft + 's remaining'"></span>
                                </div>
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] uppercase font-extrabold tracking-wider bg-primary text-on-primary">
                                    {{ strtoupper($offer->type) }} JOB OFFER
                                </span>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                                <div>
                                    <span class="text-[10px] text-on-surface-variant uppercase font-semibold block">Pickup Location</span>
                                    <p class="text-xs font-bold text-on-surface mt-0.5">{{ $offer->pickup_location }}</p>
                                    <span class="text-[10px] text-on-surface-variant uppercase font-semibold block mt-2">Drop-off Destination</span>
                                    <p class="text-xs text-on-surface mt-0.5">{{ $offer->dropoff_location }}</p>
                                </div>

                                <div class="border-y md:border-y-0 md:border-x border-outline-variant/60 py-3 md:py-0 md:px-4">
                                    <div class="flex justify-between items-center text-xs mb-1">
                                        <span class="text-on-surface-variant font-medium">Expected Pay:</span>
                                        <span class="font-bold text-emerald-800 text-sm">₱{{ number_format($offer->expected_pay, 2) }}</span>
                                    </div>
                                    <div class="flex justify-between items-center text-xs">
                                        <span class="text-on-surface-variant font-medium">COD Collection:</span>
                                        <span class="font-bold text-on-surface">₱{{ number_format($offer->cod_amount, 2) }}</span>
                                    </div>
                                    <div class="flex justify-between items-center text-xs mt-1">
                                        <span class="text-on-surface-variant font-medium">Order Number:</span>
                                        <span class="font-mono font-bold text-primary">{{ $offer->order->order_number }}</span>
                                    </div>
                                </div>

                                {{-- Action Buttons --}}
                                <div class="flex items-center gap-3 justify-end">
                                    <form method="POST" action="{{ route('courier.offers.decline', $offer) }}">
                                        @csrf
                                        <input type="hidden" name="idempotency_key" value="{{ uniqid('dec_', true) }}">
                                        <button type="submit"
                                            class="px-4 py-2.5 rounded-2xl text-xs font-semibold bg-surface-container hover:bg-surface-container-high text-on-surface-variant border border-outline-variant transition-all">
                                            Decline
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('courier.offers.accept', $offer) }}" @submit="submitting = true">
                                        @csrf
                                        <input type="hidden" name="idempotency_key" value="{{ uniqid('acc_', true) }}">
                                        <button type="submit" :disabled="submitting || timeLeft <= 0"
                                            class="px-5 py-2.5 rounded-2xl text-xs font-bold bg-primary hover:bg-primary-hover text-on-primary transition-all shadow-xs flex items-center gap-1.5">
                                            <span class="material-symbols-outlined text-[18px]">check</span>
                                            <span>Accept Job (₱{{ number_format($offer->expected_pay, 0) }})</span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Module R4: Barcode Scanner / Manual Tracking Entry Tool --}}
        <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 shadow-xs mb-8">
            <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                <div>
                    <h2 class="text-sm font-bold text-on-surface flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary text-[20px]">qr_code_scanner</span>
                        <span>Confirm Pickup (Barcode Scan / Manual Waybill Entry)</span>
                    </h2>
                    <p class="text-xs text-on-surface-variant mt-0.5">
                        Scan waybill barcode or enter tracking number to confirm merchant pickup and earn <strong>₱30.00</strong>.
                    </p>
                </div>

                <form method="POST" action="{{ route('courier.pickup.confirm') }}" class="w-full md:w-auto flex items-center gap-2" @submit="submitting = true">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ uniqid('pick_', true) }}">
                    <input type="text" name="tracking_number" required placeholder="e.g. TRK-20261017-XXXX"
                        class="px-4 py-2.5 rounded-2xl text-xs bg-surface-container-lowest border border-outline-variant text-on-surface placeholder:text-outline focus:outline-hidden focus:border-primary w-full md:w-64 font-mono">
                    <button type="submit" :disabled="submitting"
                        class="px-5 py-2.5 rounded-2xl text-xs font-bold bg-primary hover:bg-primary-hover text-on-primary transition-all shadow-xs flex-shrink-0 flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[18px]">done</span>
                        <span>Confirm Pickup</span>
                    </button>
                </form>
            </div>
        </div>

        {{-- Active Workload Tabs --}}
        <div class="space-y-8">
            
            {{-- 1. Assigned Deliveries (Out for Delivery / Ready to Depart) --}}
            <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant overflow-hidden shadow-xs">
                <div class="p-5 md:px-6 bg-surface-container-low/60 border-b border-outline-variant/60 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary text-[20px]">local_shipping</span>
                        <h2 class="text-sm font-bold text-on-surface">Active Delivery Tasks ({{ $activeDeliveries->count() }})</h2>
                    </div>
                    <span class="text-[11px] text-on-surface-variant">Requires 6-Digit OTP + POD Photo</span>
                </div>

                <div class="p-5 md:p-6">
                    @if($activeDeliveries->isEmpty())
                        <div class="py-8 text-center text-xs text-on-surface-variant">
                            <span class="material-symbols-outlined text-3xl text-outline mb-1 block">inventory_2</span>
                            No parcels currently in your delivery queue.
                        </div>
                    @else
                        <div class="space-y-4">
                            @foreach($activeDeliveries as $order)
                                @php
                                    $failedCount = $order->deliveryAttempts->where('result', 'failed')->count();
                                    $isCod = strtolower($order->payment_method) === 'cod';
                                @endphp
                                <div class="p-4 rounded-2xl border border-outline-variant/80 bg-surface-container/30 flex flex-col md:flex-row md:items-center justify-between gap-4">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap mb-1">
                                            <span class="font-mono text-xs font-bold text-primary">{{ $order->order_number }}</span>
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $order->status === 'out_for_delivery' ? 'bg-primary/10 text-primary' : 'bg-surface-container text-on-surface-variant' }}">
                                                {{ strtoupper(str_replace('_', ' ', $order->status)) }}
                                            </span>
                                            @if($isCod)
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-900 border border-amber-500/30">
                                                    COD: ₱{{ number_format($order->total, 2) }}
                                                </span>
                                            @else
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-800">
                                                    PREPAID
                                                </span>
                                            @endif
                                            @if($failedCount > 0)
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-500/10 text-rose-800">
                                                    Attempt {{ $failedCount }}/3 Failed
                                                </span>
                                            @endif
                                        </div>

                                        <p class="text-xs text-on-surface font-semibold">
                                            Recipient: {{ $order->buyer->full_name ?? 'Buyer' }} ({{ $order->buyer->contact_number ?? 'No contact' }})
                                        </p>
                                        <p class="text-[11px] text-on-surface-variant mt-0.5">
                                            Address: {{ $order->address ? "{$order->address->street}, {$order->address->city}, {$order->address->province}" : 'Default Address' }}
                                        </p>
                                    </div>

                                    {{-- Delivery Execution Buttons --}}
                                    <div class="flex items-center gap-2 flex-wrap">
                                        @if($order->status === 'assigned_to_rider')
                                            {{-- Depart for delivery --}}
                                            <form method="POST" action="{{ route('courier.deliveries.update-status', $order) }}">
                                                @csrf
                                                <input type="hidden" name="action" value="out_for_delivery">
                                                <button type="submit"
                                                    class="px-4 py-2 rounded-xl text-xs font-bold bg-primary hover:bg-primary-hover text-on-primary transition-all shadow-xs flex items-center gap-1.5">
                                                    <span class="material-symbols-outlined text-[16px]">navigation</span>
                                                    <span>Depart (Out for Delivery)</span>
                                                </button>
                                            </form>
                                        @elseif($order->status === 'out_for_delivery')
                                            {{-- Complete Delivery Button --}}
                                            <button type="button"
                                                @click="activeOrderId = {{ $order->id }}; activeOrderNumber = '{{ $order->order_number }}'; activeOrderTotal = '{{ number_format($order->total, 2) }}'; isCod = {{ $isCod ? 'true' : 'false' }}; completeModalOpen = true"
                                                class="px-4 py-2 rounded-xl text-xs font-bold bg-emerald-700 hover:bg-emerald-800 text-white transition-all shadow-xs flex items-center gap-1.5">
                                                <span class="material-symbols-outlined text-[16px]">verified</span>
                                                <span>Complete Delivery</span>
                                            </button>

                                            {{-- Failed Delivery Button --}}
                                            <button type="button"
                                                @click="activeOrderId = {{ $order->id }}; activeOrderNumber = '{{ $order->order_number }}'; failModalOpen = true"
                                                class="px-3.5 py-2 rounded-xl text-xs font-semibold bg-rose-500/10 hover:bg-rose-500/20 text-rose-700 border border-rose-500/30 transition-all flex items-center gap-1">
                                                <span class="material-symbols-outlined text-[16px]">warning</span>
                                                <span>Report Failure</span>
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- 2. Pending Pickups --}}
            <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant overflow-hidden shadow-xs">
                <div class="p-5 md:px-6 bg-surface-container-low/60 border-b border-outline-variant/60 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-amber-800 text-[20px]">storefront</span>
                        <h2 class="text-sm font-bold text-on-surface">Assigned Merchant Pickups ({{ $pickupTasks->count() }})</h2>
                    </div>
                    <span class="text-[11px] text-on-surface-variant font-medium">Earn ₱30.00 upon confirmation</span>
                </div>

                <div class="p-5 md:p-6">
                    @if($pickupTasks->isEmpty())
                        <div class="py-8 text-center text-xs text-on-surface-variant">
                            <span class="material-symbols-outlined text-3xl text-outline mb-1 block">check_circle</span>
                            No pending seller pickups assigned.
                        </div>
                    @else
                        <div class="space-y-3">
                            @foreach($pickupTasks as $order)
                                <div class="p-4 rounded-2xl border border-outline-variant bg-surface-container/20 flex flex-col md:flex-row md:items-center justify-between gap-4">
                                    <div>
                                        <span class="font-mono text-xs font-bold text-primary">{{ $order->order_number }}</span>
                                        <p class="text-xs font-semibold text-on-surface mt-1">
                                            Merchant: {{ $order->seller->sellerProfile?->store_name ?? $order->seller->full_name }}
                                        </p>
                                        <p class="text-[11px] text-on-surface-variant">
                                            Location: {{ $order->seller->address ? "{$order->seller->address->street}, {$order->seller->address->city}" : 'Seller Warehouse' }}
                                        </p>
                                    </div>

                                    <form method="POST" action="{{ route('courier.pickup.confirm') }}">
                                        @csrf
                                        <input type="hidden" name="tracking_number" value="{{ $order->tracking_number ?: $order->order_number }}">
                                        <input type="hidden" name="idempotency_key" value="{{ uniqid('pk_', true) }}">
                                        <button type="submit"
                                            class="px-4 py-2 rounded-xl text-xs font-bold bg-primary hover:bg-primary-hover text-on-primary transition-all shadow-xs flex items-center gap-1.5">
                                            <span class="material-symbols-outlined text-[16px]">qr_code_scanner</span>
                                            <span>Confirm Pickup (₱30)</span>
                                        </button>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- 3. Completed Deliveries Log --}}
            <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant overflow-hidden shadow-xs">
                <div class="p-5 md:px-6 bg-surface-container-low/60 border-b border-outline-variant/60 flex items-center justify-between">
                    <h2 class="text-sm font-bold text-on-surface">Recent Delivery History</h2>
                    <a href="{{ route('courier.earnings') }}" class="text-xs text-primary font-bold hover:underline">View Full Ledger →</a>
                </div>

                <div class="p-5 md:p-6">
                    @if($completedDeliveries->isEmpty())
                        <p class="text-center text-xs text-on-surface-variant py-4">No completed deliveries recorded yet.</p>
                    @else
                        <div class="divide-y divide-outline-variant/40">
                            @foreach($completedDeliveries as $order)
                                <div class="py-3 flex items-center justify-between text-xs">
                                    <div>
                                        <span class="font-mono font-bold text-on-surface">{{ $order->order_number }}</span>
                                        <span class="text-on-surface-variant text-[11px] block">{{ $order->buyer->full_name ?? 'Buyer' }} · {{ $order->delivered_at?->format('M d, Y h:i A') }}</span>
                                    </div>
                                    <div class="text-right">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-800">
                                            Delivered (₱50 earned)
                                        </span>
                                        @if(strtolower($order->payment_method) === 'cod')
                                            <span class="text-[10px] text-amber-900 block font-bold">COD: ₱{{ number_format($order->total, 2) }}</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- MODAL 1: Complete Delivery (Module R5: POD + 6-digit OTP) --}}
    <div x-show="completeModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
        <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant max-w-lg w-full p-6 shadow-xl" @click.away="completeModalOpen = false">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-emerald-700 text-[24px]">verified</span>
                    <h3 class="text-base font-bold text-on-surface">Complete Delivery Verification</h3>
                </div>
                <button type="button" @click="completeModalOpen = false" class="text-outline hover:text-on-surface">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <p class="text-xs text-on-surface-variant mb-4">
                Order <strong class="font-mono text-primary" x-text="activeOrderNumber"></strong>. You must enter the 6-digit OTP shown on the buyer's order screen and upload a clear POD photo.
            </p>

            <form method="POST" :action="'/courier/deliveries/' + activeOrderId + '/complete'" enctype="multipart/form-data" @submit="submitting = true">
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ uniqid('del_', true) }}">

                <div class="space-y-4">
                    {{-- 6-Digit Delivery OTP --}}
                    <div>
                        <label class="block text-xs font-bold text-on-surface mb-1">
                            Buyer's 6-Digit Delivery OTP <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="delivery_otp" required maxlength="6" pattern="[0-9]{6}" placeholder="Enter 6-digit numeric OTP"
                            class="w-full px-4 py-2.5 rounded-2xl text-base font-mono font-bold tracking-widest text-center bg-surface-container-lowest border border-outline-variant text-primary focus:outline-hidden focus:border-primary">
                        <span class="text-[10px] text-outline mt-1 block">Brute-force protection: 5 consecutive failed attempts lock verification for 15 minutes.</span>
                    </div>

                    {{-- POD Image Upload --}}
                    <div>
                        <label class="block text-xs font-bold text-on-surface mb-1">
                            Proof of Delivery Photo (POD) <span class="text-rose-500">*</span>
                        </label>
                        <input type="file" name="pod_image" required accept="image/jpeg,image/png"
                            class="w-full text-xs text-on-surface file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-primary file:text-on-primary hover:file:bg-primary-hover">
                        <span class="text-[10px] text-outline mt-1 block">Supported formats: JPG, PNG. Maximum file size: 5 MB.</span>
                    </div>

                    {{-- COD Notice --}}
                    <template x-if="isCod">
                        <div class="bg-amber-500/10 border border-amber-500/30 rounded-2xl p-3 text-xs text-amber-900">
                            <strong>COD Collection Notice:</strong> Please collect <strong>₱<span x-text="activeOrderTotal"></span></strong> cash from the buyer. This will be added to your unremitted COD ledger.
                        </div>
                    </template>

                    {{-- Notes --}}
                    <div>
                        <label class="block text-xs font-semibold text-on-surface mb-1">Delivery Notes (Optional)</label>
                        <input type="text" name="notes" placeholder="e.g. Received by security guard, gate drop-off"
                            class="w-full px-4 py-2 rounded-xl text-xs bg-surface-container-lowest border border-outline-variant text-on-surface">
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 mt-6 pt-4 border-t border-outline-variant">
                    <button type="button" @click="completeModalOpen = false"
                        class="px-4 py-2 rounded-xl text-xs font-semibold bg-surface-container text-on-surface">
                        Cancel
                    </button>
                    <button type="submit" :disabled="submitting"
                        class="px-5 py-2.5 rounded-2xl text-xs font-bold bg-emerald-700 hover:bg-emerald-800 text-white shadow-xs">
                        Confirm Delivery (Earn ₱50)
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- MODAL 2: Report Failed Delivery (Module R6) --}}
    <div x-show="failModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
        <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant max-w-md w-full p-6 shadow-xl" @click.away="failModalOpen = false">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2 text-rose-600">
                    <span class="material-symbols-outlined text-[24px]">warning</span>
                    <h3 class="text-base font-bold text-on-surface">Report Failed Delivery</h3>
                </div>
                <button type="button" @click="failModalOpen = false" class="text-outline hover:text-on-surface">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <p class="text-xs text-on-surface-variant mb-4">
                Order <strong class="font-mono text-primary" x-text="activeOrderNumber"></strong>. Please select a valid failure reason. Up to 3 attempts allowed; 3rd failure or 'Customer Refused' automatically returns parcel to sender.
            </p>

            <form method="POST" :action="'/courier/deliveries/' + activeOrderId + '/fail'" @submit="submitting = true">
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ uniqid('fail_', true) }}">

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-on-surface mb-1">
                            Failure Reason <span class="text-rose-500">*</span>
                        </label>
                        <select name="reason" required class="w-full px-4 py-2.5 rounded-2xl text-xs bg-surface-container-lowest border border-outline-variant text-on-surface">
                            <option value="">-- Select Valid Reason --</option>
                            <option value="buyer_not_home">Buyer Not Home / Unavailable</option>
                            <option value="wrong_address">Wrong / Incomplete Address</option>
                            <option value="refused">Customer Refused Delivery (Auto Return-to-Sender)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-on-surface mb-1">Attempt Notes / Details</label>
                        <textarea name="notes" rows="2" placeholder="Details about call attempt, guardhouse notice, etc."
                            class="w-full px-4 py-2 rounded-xl text-xs bg-surface-container-lowest border border-outline-variant text-on-surface"></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 mt-6 pt-4 border-t border-outline-variant">
                    <button type="button" @click="failModalOpen = false" class="px-4 py-2 rounded-xl text-xs font-semibold bg-surface-container text-on-surface">
                        Cancel
                    </button>
                    <button type="submit" :disabled="submitting" class="px-5 py-2.5 rounded-2xl text-xs font-bold bg-rose-600 hover:bg-rose-700 text-white shadow-xs">
                        Record Failure
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- MODAL 3: Update Vehicle Info (Module R1) --}}
    <div x-show="vehicleModalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
        <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant max-w-lg w-full p-6 shadow-xl" @click.away="vehicleModalOpen = false">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary text-[24px]">two_wheeler</span>
                    <h3 class="text-base font-bold text-on-surface">Vehicle Details & Update Workflow</h3>
                </div>
                <button type="button" @click="vehicleModalOpen = false" class="text-outline hover:text-on-surface">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>

            <p class="text-xs text-on-surface-variant mb-4">
                Updating your plate number or OR/CR document will submit a re-verification request to your logistics center. Plates must be unique across the fleet.
            </p>

            <form method="POST" action="{{ route('courier.vehicle.update') }}" enctype="multipart/form-data" @submit="submitting = true">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-on-surface mb-1">Vehicle Type <span class="text-rose-500">*</span></label>
                        <select name="vehicle_type" required class="w-full px-4 py-2.5 rounded-2xl text-xs bg-surface-container-lowest border border-outline-variant text-on-surface">
                            @foreach(['Motorcycle', 'Van', 'Sedan', 'Truck', 'Bicycle'] as $vType)
                                <option value="{{ $vType }}" {{ ($profile?->vehicle_type === $vType) ? 'selected' : '' }}>{{ $vType }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-on-surface mb-1">Plate Number <span class="text-rose-500">*</span></label>
                        <input type="text" name="plate_number" required value="{{ old('plate_number', $profile?->plate_number) }}"
                            placeholder="e.g. MC-1234 or ABC-1234"
                            class="w-full px-4 py-2.5 rounded-2xl text-xs font-mono font-bold bg-surface-container-lowest border border-outline-variant text-on-surface">
                        <span class="text-[10px] text-outline mt-1 block">Case-insensitive and space-trimmed uniqueness check applied.</span>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-on-surface mb-1">New Vehicle OR/CR Document (Optional)</label>
                        <input type="file" name="or_cr_file" accept="image/jpeg,image/png,application/pdf"
                            class="w-full text-xs text-on-surface file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-surface-container file:text-on-surface hover:file:bg-surface-container-high">
                        <span class="text-[10px] text-outline mt-1 block">Supported: JPG, PNG, PDF (Max 5 MB).</span>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 mt-6 pt-4 border-t border-outline-variant">
                    <button type="button" @click="vehicleModalOpen = false" class="px-4 py-2 rounded-xl text-xs font-semibold bg-surface-container text-on-surface">
                        Cancel
                    </button>
                    <button type="submit" :disabled="submitting" class="px-5 py-2.5 rounded-2xl text-xs font-bold bg-primary hover:bg-primary-hover text-on-primary shadow-xs">
                        Submit for Logistics Review
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
