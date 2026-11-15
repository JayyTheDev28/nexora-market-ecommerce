@extends('layouts.app')

@section('title', 'Courier Compensation Policies — Logistics Operations')

@section('content')
<div class="w-full min-h-screen bg-surface" x-data="{ roleTab: 'rider' }">
    <div class="flex flex-col lg:flex-row w-full">
        <x-logistics-nav active="compensation" />

        <div class="flex-1 min-w-0 p-4 sm:p-6 lg:p-8 bg-surface">
            <div class="w-full max-w-7xl mx-auto space-y-6">

                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2 text-xs">
                        <a href="{{ route('logistics.dashboard') }}" class="text-primary font-bold hover:underline">Logistics Dashboard</a>
                        <span class="text-outline-variant">/</span>
                        <span class="text-on-surface-variant">Compensation Policies</span>
                    </div>
                    <a href="{{ route('logistics.payouts.index') }}" class="text-xs text-primary font-bold hover:underline">Manage Courier Payouts →</a>
                </div>

                @if (session('status'))
                    <div class="bg-primary/10 border border-primary/30 rounded-2xl p-4 text-xs md:text-sm text-primary font-semibold">
                        {{ session('status') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="bg-rose-500/10 border border-rose-500/20 rounded-2xl p-4 text-xs text-rose-700">
                        <ul class="list-disc list-inside space-y-0.5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Active Schedules Overview --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 shadow-xs">
                        <div class="flex items-center justify-between">
                            <span class="px-2.5 py-1 rounded-full bg-emerald-500/10 text-emerald-800 text-[10px] font-bold uppercase">Active Rider Policy</span>
                            <span class="text-xs font-mono text-on-surface-variant">v{{ $activeRiderPolicy->version }}</span>
                        </div>
                        <h2 class="text-base font-bold text-on-surface mt-2">{{ $activeRiderPolicy->name }}</h2>
                        <div class="grid grid-cols-3 gap-3 mt-4 text-xs">
                            <div class="bg-surface-container/50 p-3 rounded-2xl">
                                <span class="text-[10px] text-on-surface-variant block">Pickup Rate</span>
                                <strong class="text-lg font-extrabold text-primary">₱{{ number_format($activeRiderPolicy->pickup_rate, 2) }}</strong>
                            </div>
                            <div class="bg-surface-container/50 p-3 rounded-2xl">
                                <span class="text-[10px] text-on-surface-variant block">Delivery Rate</span>
                                <strong class="text-lg font-extrabold text-primary">₱{{ number_format($activeRiderPolicy->delivery_rate, 2) }}</strong>
                            </div>
                            <div class="bg-surface-container/50 p-3 rounded-2xl">
                                <span class="text-[10px] text-on-surface-variant block">Failed Attempt</span>
                                <strong class="text-lg font-extrabold text-on-surface">₱{{ number_format($activeRiderPolicy->failed_attempt_rate, 2) }}</strong>
                            </div>
                        </div>
                    </div>

                    <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 shadow-xs">
                        <div class="flex items-center justify-between">
                            <span class="px-2.5 py-1 rounded-full bg-indigo-500/10 text-indigo-800 text-[10px] font-bold uppercase">Active Transfer Courier Policy</span>
                            <span class="text-xs font-mono text-on-surface-variant">v{{ $activeCourierPolicy->version }}</span>
                        </div>
                        <h2 class="text-base font-bold text-on-surface mt-2">{{ $activeCourierPolicy->name }}</h2>
                        <div class="grid grid-cols-4 gap-2.5 mt-4 text-xs">
                            <div class="bg-surface-container/50 p-3 rounded-2xl">
                                <span class="text-[10px] text-on-surface-variant block">Trip Base</span>
                                <strong class="text-sm font-extrabold text-indigo-800">₱{{ number_format($activeCourierPolicy->transfer_trip_base_rate, 2) }}</strong>
                            </div>
                            <div class="bg-surface-container/50 p-3 rounded-2xl">
                                <span class="text-[10px] text-on-surface-variant block">Per Km</span>
                                <strong class="text-sm font-extrabold text-on-surface">₱{{ number_format($activeCourierPolicy->transfer_per_km_rate, 2) }}</strong>
                            </div>
                            <div class="bg-surface-container/50 p-3 rounded-2xl">
                                <span class="text-[10px] text-on-surface-variant block">Per Parcel</span>
                                <strong class="text-sm font-extrabold text-on-surface">₱{{ number_format($activeCourierPolicy->transfer_per_parcel_rate, 2) }}</strong>
                            </div>
                            <div class="bg-surface-container/50 p-3 rounded-2xl">
                                <span class="text-[10px] text-on-surface-variant block">Fuel Allow.</span>
                                <strong class="text-sm font-extrabold text-on-surface">₱{{ number_format($activeCourierPolicy->transfer_fuel_allowance, 2) }}</strong>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Propose New Rate Version Form --}}
                <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 shadow-xs">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                        <div>
                            <h3 class="text-sm font-bold text-on-surface">Create New Compensation Policy Version</h3>
                            <p class="text-[11px] text-on-surface-variant">New versions start as Draft or Proposed and do NOT alter existing active rates or historical earnings until explicitly activated.</p>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" @click="roleTab = 'rider'" :class="roleTab === 'rider' ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface'" class="px-3.5 py-1.5 rounded-xl text-xs font-bold cursor-pointer">Rider Schedule</button>
                            <button type="button" @click="roleTab = 'courier'" :class="roleTab === 'courier' ? 'bg-indigo-600 text-white' : 'bg-surface-container text-on-surface'" class="px-3.5 py-1.5 rounded-xl text-xs font-bold cursor-pointer">Transfer Courier Schedule</button>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('logistics.compensation.store') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-4 text-xs">
                        @csrf
                        <input type="hidden" name="role" :value="roleTab">
                        <div class="sm:col-span-2">
                            <label class="block font-semibold mb-1">Policy Name</label>
                            <input type="text" name="name" required placeholder="e.g. Q4 Peak Season Rider Schedule" class="w-full px-3.5 py-2 rounded-xl border border-outline-variant bg-surface">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Initial Status</label>
                            <select name="status" class="w-full px-3.5 py-2 rounded-xl border border-outline-variant bg-surface">
                                <option value="draft">Draft</option>
                                <option value="proposed">Proposed (Pending Activation)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Effective From</label>
                            <input type="date" name="effective_from" class="w-full px-3.5 py-2 rounded-xl border border-outline-variant bg-surface">
                        </div>

                        <template x-if="roleTab === 'rider'">
                            <div class="sm:col-span-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <label class="block font-semibold mb-1">Pickup Fee (₱)</label>
                                    <input type="number" step="0.01" min="0" name="pickup_rate" value="30.00" class="w-full px-3.5 py-2 rounded-xl border border-outline-variant bg-surface">
                                </div>
                                <div>
                                    <label class="block font-semibold mb-1">Delivery Fee (₱)</label>
                                    <input type="number" step="0.01" min="0" name="delivery_rate" value="50.00" class="w-full px-3.5 py-2 rounded-xl border border-outline-variant bg-surface">
                                </div>
                                <div>
                                    <label class="block font-semibold mb-1">Failed Attempt Fee (₱)</label>
                                    <input type="number" step="0.01" min="0" name="failed_attempt_rate" value="0.00" class="w-full px-3.5 py-2 rounded-xl border border-outline-variant bg-surface">
                                </div>
                            </div>
                        </template>

                        <template x-if="roleTab === 'courier'">
                            <div class="sm:col-span-4 grid grid-cols-2 sm:grid-cols-5 gap-3">
                                <div>
                                    <label class="block font-semibold mb-1">Trip Base (₱)</label>
                                    <input type="number" step="0.01" min="0" name="transfer_trip_base_rate" value="150.00" class="w-full px-3 py-2 rounded-xl border border-outline-variant bg-surface">
                                </div>
                                <div>
                                    <label class="block font-semibold mb-1">Per Km (₱)</label>
                                    <input type="number" step="0.01" min="0" name="transfer_per_km_rate" value="2.50" class="w-full px-3 py-2 rounded-xl border border-outline-variant bg-surface">
                                </div>
                                <div>
                                    <label class="block font-semibold mb-1">Per Parcel (₱)</label>
                                    <input type="number" step="0.01" min="0" name="transfer_per_parcel_rate" value="5.00" class="w-full px-3 py-2 rounded-xl border border-outline-variant bg-surface">
                                </div>
                                <div>
                                    <label class="block font-semibold mb-1">Fuel Allowance (₱)</label>
                                    <input type="number" step="0.01" min="0" name="transfer_fuel_allowance" value="50.00" class="w-full px-3 py-2 rounded-xl border border-outline-variant bg-surface">
                                </div>
                                <div>
                                    <label class="block font-semibold mb-1">Exception Fee (₱)</label>
                                    <input type="number" step="0.01" min="0" name="transfer_exception_rate" value="75.00" class="w-full px-3 py-2 rounded-xl border border-outline-variant bg-surface">
                                </div>
                            </div>
                        </template>

                        <div class="sm:col-span-3">
                            <input type="text" name="notes" placeholder="Reason or policy notes..." class="w-full px-3.5 py-2 rounded-xl border border-outline-variant bg-surface">
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="px-5 py-2 rounded-xl bg-primary text-on-primary font-bold cursor-pointer">Save Policy Draft</button>
                        </div>
                    </form>
                </div>

                {{-- All Policy Versions Table --}}
                <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant overflow-hidden shadow-xs">
                    <div class="p-5 bg-surface-container-low/60 border-b border-outline-variant/60">
                        <h3 class="text-sm font-bold text-on-surface">Versioned Policy Registry</h3>
                    </div>
                    <div class="p-5 overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="border-b border-outline-variant text-[11px] uppercase text-on-surface-variant">
                                    <th class="pb-3">Name</th>
                                    <th class="pb-3">Role / Scope</th>
                                    <th class="pb-3">Rates</th>
                                    <th class="pb-3">Status</th>
                                    <th class="pb-3 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline-variant/40">
                                @foreach($policies as $p)
                                    <tr>
                                        <td class="py-3 font-bold text-on-surface">
                                            {{ $p->name }} <span class="text-outline font-mono font-normal">v{{ $p->version }}</span>
                                            @if($p->notes)
                                                <span class="block text-[10px] text-on-surface-variant font-normal">{{ $p->notes }}</span>
                                            @endif
                                        </td>
                                        <td class="py-3">
                                            <span class="uppercase font-bold text-[10px] px-2 py-0.5 rounded bg-surface-container">{{ $p->role }}</span>
                                            <span class="block text-[10px] text-outline mt-0.5">{{ $p->logistics_company_id ? 'Company Policy' : 'Platform Default' }}</span>
                                        </td>
                                        <td class="py-3 font-mono text-[11px]">
                                            @if($p->role === 'rider')
                                                Pickup ₱{{ number_format($p->pickup_rate, 2) }} • Delivery ₱{{ number_format($p->delivery_rate, 2) }} • Fail ₱{{ number_format($p->failed_attempt_rate, 2) }}
                                            @else
                                                Base ₱{{ number_format($p->transfer_trip_base_rate, 2) }} • ₱{{ number_format($p->transfer_per_km_rate, 2) }}/km • ₱{{ number_format($p->transfer_per_parcel_rate, 2) }}/pcl • Fuel ₱{{ number_format($p->transfer_fuel_allowance, 2) }}
                                            @endif
                                        </td>
                                        <td class="py-3">
                                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase
                                                {{ $p->status === 'active' ? 'bg-emerald-500/10 text-emerald-800' : ($p->status === 'draft' || $p->status === 'proposed' ? 'bg-amber-500/10 text-amber-800' : 'bg-surface-container text-outline') }}">
                                                {{ $p->status }}
                                            </span>
                                        </td>
                                        <td class="py-3 text-right">
                                            @if($p->status !== 'active' || !$p->logistics_company_id)
                                                <form method="POST" action="{{ route('logistics.compensation.activate', $p) }}" class="inline">
                                                    @csrf
                                                    <button type="submit" class="px-3 py-1 rounded-xl bg-primary/10 text-primary font-bold hover:bg-primary hover:text-on-primary transition-colors cursor-pointer">
                                                        Approve & Activate
                                                    </button>
                                                </form>
                                            @else
                                                <span class="text-[11px] text-emerald-700 font-bold">Currently Active</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
@endsection
