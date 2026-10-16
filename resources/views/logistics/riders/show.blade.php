@extends('layouts.app')

@section('title', 'Rider Details — ' . $rider->full_name)

@section('content')
<div class="w-full bg-surface py-8 min-h-[75vh]" x-data="{ disapproveModal: false, deactivateModal: false }">
    <div class="max-w-none px-margin-mobile md:px-margin-desktop">

        <x-logistics-nav active="riders" />

        <div class="mb-6 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('logistics.riders.index') }}" class="w-9 h-9 rounded-full border border-outline-variant flex items-center justify-center text-on-surface hover:bg-surface-container transition-colors">
                    <span class="material-symbols-outlined text-[20px]">arrow_back</span>
                </a>
                <div>
                    <h1 class="font-headline-lg text-2xl font-bold text-on-surface">{{ $rider->full_name }}</h1>
                    <p class="font-body-sm text-xs text-on-surface-variant">Courier Registration & Workload Profile</p>
                </div>
            </div>

            {{-- Action buttons --}}
            <div class="flex items-center gap-2">
                @if($rider->isPending())
                    <form method="POST" action="{{ route('logistics.riders.approve', $rider) }}">
                        @csrf
                        <button type="submit" class="px-5 py-2 rounded-full bg-primary text-on-primary font-semibold text-xs hover:bg-primary/90 transition-all cursor-pointer flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-[16px]">check</span>
                            Approve Rider
                        </button>
                    </form>
                    <button
                        type="button"
                        @click="disapproveModal = true"
                        class="px-4 py-2 rounded-full border border-rose-300 text-rose-700 hover:bg-rose-50 font-semibold text-xs transition-all cursor-pointer">
                        Disapprove
                    </button>
                @elseif($rider->isApproved())
                    @if($rider->isActive())
                        <button
                            type="button"
                            @click="deactivateModal = true"
                            class="px-4 py-2 rounded-full border border-rose-300 text-rose-700 hover:bg-rose-50 font-semibold text-xs transition-all cursor-pointer flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-[16px]">block</span>
                            Deactivate Account
                        </button>
                    @else
                        <form method="POST" action="{{ route('logistics.riders.toggle-status', $rider) }}">
                            @csrf
                            <input type="hidden" name="action" value="activate">
                            <button type="submit" class="px-5 py-2 rounded-full bg-emerald-600 text-white font-semibold text-xs hover:bg-emerald-700 transition-all cursor-pointer flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-[16px]">check_circle</span>
                                Activate Rider
                            </button>
                        </form>
                    @endif
                @endif
            </div>
        </div>

        {{-- Flash Messages --}}
        @if (session('status'))
            <div class="mb-6 bg-primary/10 border border-primary/30 rounded-2xl p-4 flex items-center gap-3">
                <span class="material-symbols-outlined text-primary text-[20px]">check_circle</span>
                <p class="font-body-sm text-xs text-primary font-medium">{{ session('status') }}</p>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Left Column: Personal, Vehicle & Document Info --}}
            <div class="flex flex-col gap-6">

                {{-- Rider Card --}}
                <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-6">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-14 h-14 rounded-2xl bg-primary/10 text-primary font-bold text-xl flex items-center justify-center flex-shrink-0">
                            {{ strtoupper(substr($rider->first_name, 0, 1)) }}
                        </div>
                        <div>
                            <h2 class="font-headline-sm text-base font-bold text-on-surface">{{ $rider->full_name }}</h2>
                            <p class="text-xs text-on-surface-variant">{{ $rider->email }}</p>
                            <div class="flex items-center gap-2 mt-1.5">
                                @if($rider->isPending())
                                    <span class="px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-800 text-[10px] font-bold">Pending Review</span>
                                @elseif($rider->isDisapproved())
                                    <span class="px-2 py-0.5 rounded-full bg-rose-500/10 text-rose-800 text-[10px] font-bold">Disapproved</span>
                                @elseif($rider->isActive())
                                    <span class="px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-800 text-[10px] font-bold">Active & Approved</span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full bg-slate-500/10 text-slate-800 text-[10px] font-bold">Deactivated</span>
                                @endif
                                <span class="text-[11px] text-on-surface-variant font-medium">Joined {{ $rider->created_at->format('M j, Y') }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-outline-variant pt-4 flex flex-col gap-2.5 text-xs">
                        <div class="flex justify-between">
                            <span class="text-on-surface-variant">Contact Number</span>
                            <span class="font-semibold text-on-surface">{{ $rider->contact_number }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-on-surface-variant">Sex / Age</span>
                            <span class="font-semibold text-on-surface">{{ ucfirst($rider->sex) }} ({{ $rider->age }} yrs old)</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-on-surface-variant">Birthday</span>
                            <span class="font-semibold text-on-surface">{{ $rider->birthday?->format('F d, Y') ?? 'N/A' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-on-surface-variant">Email Verified</span>
                            <span class="font-semibold {{ $rider->hasVerifiedEmail() ? 'text-emerald-700' : 'text-amber-700' }}">
                                {{ $rider->hasVerifiedEmail() ? 'Verified' : 'Pending Verification' }}
                            </span>
                        </div>
                        @if($rider->disapproval_reason)
                            <div class="mt-2 p-3 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-800">
                                <p class="font-bold text-[11px]">Disapproval Reason:</p>
                                <p class="mt-0.5 text-xs">{{ $rider->disapproval_reason }}</p>
                            </div>
                        @endif
                        @if($rider->status_reason)
                            <div class="mt-2 p-3 rounded-xl bg-slate-500/10 border border-slate-500/20 text-slate-800">
                                <p class="font-bold text-[11px]">Deactivation Note:</p>
                                <p class="mt-0.5 text-xs">{{ $rider->status_reason }}</p>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Vehicle Information --}}
                <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-6">
                    <h3 class="font-headline-sm text-sm font-bold text-on-surface mb-3 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary text-[18px]">two_wheeler</span>
                        Vehicle Registration
                    </h3>
                    @if($rider->courierProfile)
                        <div class="flex flex-col gap-2.5 text-xs">
                            <div class="flex justify-between items-center">
                                <span class="text-on-surface-variant">Vehicle Type</span>
                                <span class="font-bold text-on-surface">{{ $rider->courierProfile->vehicle_type }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-on-surface-variant">Plate Number</span>
                                <span class="px-2.5 py-1 rounded bg-surface-container font-mono text-xs font-bold text-on-surface border border-outline-variant">
                                    {{ $rider->courierProfile->plate_number }}
                                </span>
                            </div>
                        </div>
                    @else
                        <p class="text-xs text-on-surface-variant italic">No courier vehicle profile registered.</p>
                    @endif
                </div>

                {{-- Registered Address --}}
                <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-6">
                    <h3 class="font-headline-sm text-sm font-bold text-on-surface mb-3 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary text-[18px]">home_pin</span>
                        Registered Address
                    </h3>
                    @if($rider->address)
                        <p class="text-xs text-on-surface font-medium leading-relaxed">
                            {{ $rider->address->house_number ? $rider->address->house_number . ' ' : '' }}
                            {{ $rider->address->street ? $rider->address->street . ', ' : '' }}
                            {{ $rider->address->barangay_name }},<br>
                            {{ $rider->address->municipality_name }}, {{ $rider->address->province_name }}
                        </p>
                    @else
                        <p class="text-xs text-on-surface-variant italic">No address on file.</p>
                    @endif
                </div>

                {{-- Submitted Documents --}}
                <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-6">
                    <h3 class="font-headline-sm text-sm font-bold text-on-surface mb-3 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary text-[18px]">folder_shared</span>
                        Verification Documents
                    </h3>
                    <div class="flex flex-col gap-2 text-xs">
                        @if($rider->valid_id_path || $rider->courierProfile?->license_path)
                            <a href="{{ Storage::url($rider->valid_id_path ?? $rider->courierProfile?->license_path) }}" target="_blank"
                               class="flex items-center justify-between p-3 rounded-xl bg-surface-container-low hover:bg-surface-container border border-outline-variant transition-colors">
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-outlined text-primary text-[18px]">badge</span>
                                    <span class="font-medium text-on-surface">Driver's License / Valid ID</span>
                                </div>
                                <span class="material-symbols-outlined text-on-surface-variant text-[16px]">open_in_new</span>
                            </a>
                        @else
                            <p class="text-xs text-on-surface-variant italic">No ID uploaded.</p>
                        @endif

                        @if($rider->courierProfile?->or_cr_path)
                            <a href="{{ Storage::url($rider->courierProfile->or_cr_path) }}" target="_blank"
                               class="flex items-center justify-between p-3 rounded-xl bg-surface-container-low hover:bg-surface-container border border-outline-variant transition-colors">
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-outlined text-primary text-[18px]">receipt_long</span>
                                    <span class="font-medium text-on-surface">Vehicle OR / CR Document</span>
                                </div>
                                <span class="material-symbols-outlined text-on-surface-variant text-[16px]">open_in_new</span>
                            </a>
                        @else
                            <p class="text-xs text-on-surface-variant italic">No OR/CR uploaded.</p>
                        @endif
                    </div>
                </div>

            </div>

            {{-- Right Column: Delivery Area Assignment & Active Workload --}}
            <div class="lg:col-span-2 flex flex-col gap-6">

                {{-- Delivery Area Assignment Editor --}}
                <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="font-headline-sm text-base font-bold text-on-surface flex items-center gap-2">
                                <span class="material-symbols-outlined text-secondary text-[20px]">explore</span>
                                Assigned Delivery Coverage Areas
                            </h3>
                            <p class="text-xs text-on-surface-variant mt-0.5">Select the destination areas this rider is authorized to deliver to.</p>
                        </div>
                    </div>

                    @if(! $rider->isApproved() || ! $rider->isActive())
                        <div class="p-4 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-800 text-xs mb-4">
                            <p class="font-bold">Area Assignment Locked</p>
                            <p class="mt-0.5">Rider must be both approved and active before delivery areas can be assigned.</p>
                        </div>
                    @else
                        <form method="POST" action="{{ route('logistics.riders.assign-areas', $rider) }}" class="flex flex-col gap-4">
                            @csrf
                            @if($availableAreas->isEmpty())
                                <p class="text-xs text-on-surface-variant italic">No delivery areas configured for this sorting hub yet.</p>
                            @else
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    @foreach($availableAreas as $area)
                                        @php
                                            $isAssignedToThisRider = $area->courier_id === $rider->id;
                                            $isAssignedToOther = $area->courier_id && $area->courier_id !== $rider->id;
                                        @endphp
                                        <label class="flex items-start gap-3 p-3.5 rounded-xl border cursor-pointer transition-all {{ $isAssignedToThisRider ? 'bg-secondary/10 border-secondary' : 'bg-surface-container-low border-outline-variant hover:border-outline' }}">
                                            <input
                                                type="checkbox" name="area_ids[]" value="{{ $area->id }}"
                                                {{ $isAssignedToThisRider ? 'checked' : '' }}
                                                class="mt-0.5 rounded border-outline-variant text-secondary focus:ring-secondary">
                                            <div class="flex-1 text-xs">
                                                <p class="font-bold text-on-surface">{{ $area->name }}</p>
                                                @if($isAssignedToOther)
                                                    <p class="text-[10px] text-amber-700 mt-0.5">Currently assigned to {{ $area->courier?->full_name }}</p>
                                                @elseif($isAssignedToThisRider)
                                                    <p class="text-[10px] text-secondary font-semibold mt-0.5">Assigned to this rider</p>
                                                @else
                                                    <p class="text-[10px] text-on-surface-variant mt-0.5">Available for assignment</p>
                                                @endif
                                            </div>
                                        </label>
                                    @endforeach
                                </div>

                                <div class="flex justify-end pt-2">
                                    <button type="submit" class="px-5 py-2.5 rounded-full bg-secondary text-on-secondary font-semibold text-xs shadow-xs hover:bg-secondary/90 transition-all cursor-pointer">
                                        Save Delivery Area Assignments
                                    </button>
                                </div>
                            @endif
                        </form>
                    @endif
                </div>

                {{-- Active Workload & Assigned Deliveries --}}
                <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant overflow-hidden shadow-xs">
                    <div class="px-6 py-4 bg-surface-container-low border-b border-outline-variant flex items-center justify-between">
                        <div>
                            <h3 class="font-headline-sm text-sm font-bold text-on-surface flex items-center gap-2">
                                <span class="material-symbols-outlined text-primary text-[18px]">local_shipping</span>
                                Current Assigned Workload
                            </h3>
                            <p class="text-xs text-on-surface-variant">Parcels currently in progress with this courier</p>
                        </div>
                        <span class="px-3 py-1 rounded-full text-xs font-bold {{ $rider->activeDeliveries->count() > 0 ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface-variant' }}">
                            {{ $rider->activeDeliveries->count() }} active parcels
                        </span>
                    </div>

                    @if($rider->activeDeliveries->isEmpty())
                        <div class="p-8 text-center text-xs text-on-surface-variant">
                            <span class="material-symbols-outlined text-[32px] text-outline block mb-1">inventory_2</span>
                            No active deliveries currently assigned to this rider. Rider is available for dispatch.
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-surface-container-low text-on-surface-variant border-b border-outline-variant font-label-md">
                                    <tr>
                                        <th class="px-5 py-3">Order / Tracking</th>
                                        <th class="px-5 py-3">Buyer & Address</th>
                                        <th class="px-5 py-3">Status</th>
                                        <th class="px-5 py-3">Total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-outline-variant">
                                    @foreach($rider->activeDeliveries as $order)
                                        <tr class="hover:bg-surface-container-low/50">
                                            <td class="px-5 py-3.5">
                                                <p class="font-bold text-on-surface">{{ $order->order_number }}</p>
                                                @if($order->tracking_number)
                                                    <span class="text-[10px] text-on-surface-variant font-mono">{{ $order->tracking_number }}</span>
                                                @endif
                                            </td>
                                            <td class="px-5 py-3.5">
                                                <p class="font-semibold text-on-surface">{{ $order->buyer?->full_name }}</p>
                                                @if($order->address)
                                                    <p class="text-[11px] text-on-surface-variant">{{ $order->address->barangay_name }}, {{ $order->address->municipality_name }}</p>
                                                @endif
                                            </td>
                                            <td class="px-5 py-3.5">
                                                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-primary/10 text-primary">
                                                    {{ ucfirst(str_replace('_', ' ', $order->status)) }}
                                                </span>
                                            </td>
                                            <td class="px-5 py-3.5 font-semibold text-on-surface">
                                                ₱{{ number_format($order->total, 2) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                {{-- Delivery History (Recent 10) --}}
                <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant overflow-hidden shadow-xs">
                    <div class="px-6 py-4 bg-surface-container-low border-b border-outline-variant flex items-center justify-between">
                        <h3 class="font-headline-sm text-sm font-bold text-on-surface flex items-center gap-2">
                            <span class="material-symbols-outlined text-emerald-600 text-[18px]">verified</span>
                            Completed Delivery History
                        </h3>
                        <span class="text-xs text-on-surface-variant">{{ $rider->completedDeliveries->count() }} total completed</span>
                    </div>

                    @if($rider->completedDeliveries->isEmpty())
                        <div class="p-6 text-center text-xs text-on-surface-variant">
                            No completed deliveries on record yet.
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead class="bg-surface-container-low text-on-surface-variant border-b border-outline-variant font-label-md">
                                    <tr>
                                        <th class="px-5 py-3">Order Number</th>
                                        <th class="px-5 py-3">Delivered To</th>
                                        <th class="px-5 py-3">Date</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-outline-variant">
                                    @foreach($rider->completedDeliveries as $comp)
                                        <tr class="hover:bg-surface-container-low/50">
                                            <td class="px-5 py-3 font-semibold text-on-surface">{{ $comp->order_number }}</td>
                                            <td class="px-5 py-3 text-on-surface-variant">{{ $comp->buyer?->full_name }}</td>
                                            <td class="px-5 py-3 text-on-surface-variant">{{ $comp->updated_at->format('M j, Y h:i A') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

            </div>

        </div>

    </div>

    {{-- Modal: Disapprove Application --}}
    <div x-show="disapproveModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
        <div @click.outside="disapproveModal = false" class="bg-surface-container-lowest rounded-3xl border border-outline-variant shadow-xl w-full max-w-md p-6">
            <h3 class="font-headline-sm text-base font-bold text-on-surface mb-2">Disapprove Courier Application</h3>
            <p class="font-body-sm text-xs text-on-surface-variant mb-4">
                Please provide a reason for declining {{ $rider->full_name }}. This will be emailed to the applicant.
            </p>

            <form action="{{ route('logistics.riders.disapprove', $rider) }}" method="POST" class="flex flex-col gap-4">
                @csrf
                <div class="flex flex-col gap-1.5">
                    <label for="disapprove_reason" class="font-label-md text-xs font-semibold text-on-surface-variant">Reason for Disapproval</label>
                    <textarea
                        id="disapprove_reason" name="reason" rows="3" required
                        placeholder="e.g. Incomplete or expired driver's license submitted..."
                        class="rounded-xl border border-outline-variant p-3 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary"></textarea>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <button type="button" @click="disapproveModal = false" class="px-4 py-2 rounded-full border border-outline-variant text-xs font-semibold text-on-surface hover:bg-surface-container">
                        Cancel
                    </button>
                    <button type="submit" class="px-5 py-2 rounded-full bg-rose-600 text-white text-xs font-semibold hover:bg-rose-700">
                        Confirm Disapproval
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal: Deactivate Rider --}}
    <div x-show="deactivateModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
        <div @click.outside="deactivateModal = false" class="bg-surface-container-lowest rounded-3xl border border-outline-variant shadow-xl w-full max-w-md p-6">
            <h3 class="font-headline-sm text-base font-bold text-on-surface mb-2">Deactivate Rider Account</h3>
            <p class="font-body-sm text-xs text-on-surface-variant mb-4">
                Deactivating {{ $rider->full_name }} will immediately prevent them from receiving new delivery assignments.
            </p>

            <form action="{{ route('logistics.riders.toggle-status', $rider) }}" method="POST" class="flex flex-col gap-4">
                @csrf
                <input type="hidden" name="action" value="deactivate">
                <div class="flex flex-col gap-1.5">
                    <label for="deactivate_reason" class="font-label-md text-xs font-semibold text-on-surface-variant">Reason for Deactivation</label>
                    <textarea
                        id="deactivate_reason" name="reason" rows="3"
                        placeholder="e.g. Temporary leave of absence / performance review..."
                        class="rounded-xl border border-outline-variant p-3 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary"></textarea>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <button type="button" @click="deactivateModal = false" class="px-4 py-2 rounded-full border border-outline-variant text-xs font-semibold text-on-surface hover:bg-surface-container">
                        Cancel
                    </button>
                    <button type="submit" class="px-5 py-2 rounded-full bg-rose-600 text-white text-xs font-semibold hover:bg-rose-700">
                        Confirm Deactivation
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
