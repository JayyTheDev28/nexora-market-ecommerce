@extends('layouts.app')

@section('title', 'Logistics Reports & Financial Analytics — Nexora Logistics')

@section('content')
<div class="w-full min-h-screen bg-surface" x-data="{ tab: '{{ $activeTab }}' }">
    <div class="flex flex-col lg:flex-row w-full">
        <x-logistics-nav active="reports" />

        <div class="flex-1 min-w-0 p-4 sm:p-6 lg:p-8 bg-surface">
            <div class="w-full max-w-7xl mx-auto space-y-6">

                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div>
                        <h1 class="font-headline-lg text-2xl md:text-3xl font-bold text-on-surface">Logistics Operational Reports & Financial Analytics</h1>
                        <p class="font-body-md text-xs md:text-sm text-on-surface-variant mt-1">
                            Authoritative reports using event timestamps, verified COD collections, courier compensation, operating expenses, and balance reconciliation.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a :href="'{{ route('logistics.reports.export') }}?from={{ $fromInput }}&to={{ $toInput }}&type=' + tab"
                           class="inline-flex items-center gap-1.5 bg-primary text-on-primary px-4 py-2 rounded-full text-xs font-bold shadow-xs">
                            <span class="material-symbols-outlined text-[16px]">download</span>
                            <span>Export CSV</span>
                        </a>
                        <button onclick="window.print()" class="inline-flex items-center gap-1.5 bg-surface-container-high text-on-surface px-4 py-2 rounded-full text-xs font-semibold cursor-pointer">
                            <span class="material-symbols-outlined text-[16px]">print</span>
                            <span>Print</span>
                        </button>
                    </div>
                </div>

                {{-- Filter Bar --}}
                <form method="GET" action="{{ route('logistics.reports.index') }}" class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4 flex flex-wrap items-center gap-3 text-xs">
                    <input type="hidden" name="tab" :value="tab">
                    <div class="flex items-center gap-2">
                        <label class="font-bold">From:</label>
                        <input type="date" name="from" value="{{ $fromInput }}" class="px-3 py-1.5 rounded-xl border border-outline-variant bg-surface">
                    </div>
                    <div class="flex items-center gap-2">
                        <label class="font-bold">To:</label>
                        <input type="date" name="to" value="{{ $toInput }}" class="px-3 py-1.5 rounded-xl border border-outline-variant bg-surface">
                    </div>
                    <select name="hub_id" class="px-3 py-1.5 rounded-xl border border-outline-variant bg-surface">
                        <option value="">All Hubs</option>
                        @foreach($hubs as $h)
                            <option value="{{ $h->id }}" @selected($hubId === $h->id)>{{ $h->name }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="px-4 py-1.5 rounded-xl bg-primary text-on-primary font-bold cursor-pointer">Apply Filters</button>
                </form>

                {{-- Balance Reconciliation Banner --}}
                <div class="rounded-2xl p-4 border {{ $reconciliation['is_balanced'] ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-900' : 'bg-rose-500/10 border-rose-500/20 text-rose-900' }} text-xs flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-[20px]">{{ $reconciliation['is_balanced'] ? 'verified_user' : 'error' }}</span>
                        <span>
                            @if($reconciliation['is_balanced'])
                                <strong>Financial Ledger Integrity Check Passed:</strong> All COD collections, rider balances, remittance batches, and courier earnings reconcile with zero discrepancies.
                            @else
                                <strong>Financial Reconciliation Alert:</strong> {{ $reconciliation['critical_count'] }} critical and {{ $reconciliation['warning_count'] }} warning ledger discrepancy item(s) detected.
                            @endif
                        </span>
                    </div>
                </div>

                {{-- Report Domain Tabs --}}
                <div class="flex flex-wrap gap-2 border-b border-outline-variant pb-3 text-xs font-bold">
                    <button type="button" @click="tab = 'operations'" :class="tab === 'operations' ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface'" class="px-4 py-2 rounded-full cursor-pointer">Operations & Areas</button>
                    <button type="button" @click="tab = 'cod_reconciliation'" :class="tab === 'cod_reconciliation' ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface'" class="px-4 py-2 rounded-full cursor-pointer">COD Reconciliation</button>
                    <button type="button" @click="tab = 'admin_settlements'" :class="tab === 'admin_settlements' ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface'" class="px-4 py-2 rounded-full cursor-pointer">Admin Settlements</button>
                    <button type="button" @click="tab = 'rider_performance'" :class="tab === 'rider_performance' ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface'" class="px-4 py-2 rounded-full cursor-pointer">Rider Performance</button>
                    <button type="button" @click="tab = 'courier_performance'" :class="tab === 'courier_performance' ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface'" class="px-4 py-2 rounded-full cursor-pointer">Transfer Couriers</button>
                    <button type="button" @click="tab = 'profitability'" :class="tab === 'profitability' ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface'" class="px-4 py-2 rounded-full cursor-pointer">Profitability & Routes</button>
                    <button type="button" @click="tab = 'exceptions'" :class="tab === 'exceptions' ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface'" class="px-4 py-2 rounded-full cursor-pointer">
                        Damage &amp; Missing Incidents ({{ $incidentReport['unresolved_total'] }})
                    </button>
                </div>

                {{-- TAB 1: Operations --}}
                <div x-show="tab === 'operations'" class="space-y-6">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <div class="bg-surface-container-lowest p-4 rounded-2xl border border-outline-variant"><span class="text-[10px] uppercase text-on-surface-variant block">Parcels Processed</span><strong class="text-2xl">{{ $metrics['total_processed'] }}</strong></div>
                        <div class="bg-surface-container-lowest p-4 rounded-2xl border border-outline-variant"><span class="text-[10px] uppercase text-on-surface-variant block">Delivered</span><strong class="text-2xl text-emerald-700">{{ $metrics['delivered'] }}</strong></div>
                        <div class="bg-surface-container-lowest p-4 rounded-2xl border border-outline-variant"><span class="text-[10px] uppercase text-on-surface-variant block">Failed Attempts</span><strong class="text-2xl text-rose-700">{{ $metrics['failed'] }}</strong></div>
                        <div class="bg-surface-container-lowest p-4 rounded-2xl border border-outline-variant"><span class="text-[10px] uppercase text-on-surface-variant block">Returned / RTS</span><strong class="text-2xl">{{ $metrics['returned'] }}</strong></div>
                    </div>

                    <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-5 overflow-x-auto">
                        <h3 class="text-sm font-bold mb-3">Delivery Areas Breakdown</h3>
                        <table class="w-full text-left text-xs">
                            <thead><tr class="border-b border-outline-variant text-[11px] uppercase text-on-surface-variant"><th class="pb-2">Area</th><th class="pb-2 text-right">Total</th><th class="pb-2 text-right">Delivered</th><th class="pb-2 text-right">Failed</th></tr></thead>
                            <tbody class="divide-y divide-outline-variant/40">
                                @forelse($areaBreakdown as $area)
                                    <tr><td class="py-2.5 font-semibold">{{ $area->barangay_name ?? $area->municipality_name }}</td><td class="py-2.5 text-right">{{ $area->total_orders }}</td><td class="py-2.5 text-right text-emerald-700 font-bold">{{ $area->delivered_orders }}</td><td class="py-2.5 text-right text-rose-700">{{ $area->failed_orders }}</td></tr>
                                @empty
                                    <tr><td colspan="4" class="py-4 text-center text-outline">No area records in range.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- TAB 2: COD Reconciliation --}}
                <div x-show="tab === 'cod_reconciliation'" class="space-y-6">
                    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
                        <div class="bg-surface-container-lowest p-4 rounded-2xl border border-outline-variant"><span class="text-[10px] uppercase text-on-surface-variant block">Collected COD Cash</span><strong class="text-xl">₱{{ number_format($codReport['collected_in_period'], 2) }}</strong></div>
                        <div class="bg-surface-container-lowest p-4 rounded-2xl border border-outline-variant"><span class="text-[10px] uppercase text-on-surface-variant block">Verified Remitted</span><strong class="text-xl text-emerald-700">₱{{ number_format($codReport['verified_remitted_total'], 2) }}</strong></div>
                        <div class="bg-surface-container-lowest p-4 rounded-2xl border border-outline-variant"><span class="text-[10px] uppercase text-on-surface-variant block">Pending Verification</span><strong class="text-xl text-amber-700">₱{{ number_format($codReport['pending_verification_total'], 2) }}</strong></div>
                        <div class="bg-surface-container-lowest p-4 rounded-2xl border border-outline-variant"><span class="text-[10px] uppercase text-on-surface-variant block">Shortages</span><strong class="text-xl text-rose-700">₱{{ number_format($codReport['shortages_total'], 2) }}</strong></div>
                        <div class="bg-surface-container-lowest p-4 rounded-2xl border border-outline-variant"><span class="text-[10px] uppercase text-on-surface-variant block">Overpayments</span><strong class="text-xl text-indigo-700">₱{{ number_format($codReport['overpayments_total'], 2) }}</strong></div>
                    </div>
                </div>

                {{-- TAB 3: Admin Settlements --}}
                <div x-show="tab === 'admin_settlements'" class="space-y-6">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="bg-surface-container-lowest p-4 rounded-2xl border border-outline-variant"><span class="text-[10px] uppercase text-on-surface-variant block">Merchandise Remitted (Verified)</span><strong class="text-2xl text-primary">₱{{ number_format($adminReport['total_merchandise_verified'], 2) }}</strong></div>
                        <div class="bg-surface-container-lowest p-4 rounded-2xl border border-outline-variant"><span class="text-[10px] uppercase text-on-surface-variant block">Shipping Retained (Verified)</span><strong class="text-2xl text-emerald-700">₱{{ number_format($adminReport['total_shipping_retained'], 2) }}</strong></div>
                        <div class="bg-surface-container-lowest p-4 rounded-2xl border border-outline-variant"><span class="text-[10px] uppercase text-on-surface-variant block">Pending Admin Verification</span><strong class="text-2xl text-amber-700">₱{{ number_format($adminReport['pending_admin_verification'], 2) }}</strong></div>
                    </div>
                </div>

                {{-- TAB 4: Rider Performance --}}
                <div x-show="tab === 'rider_performance'" class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-5 overflow-x-auto">
                    <h3 class="text-sm font-bold mb-3">Rider Delivery & COD Accountability Report</h3>
                    <table class="w-full text-left text-xs">
                        <thead><tr class="border-b border-outline-variant text-[11px] uppercase text-on-surface-variant"><th class="pb-2">Rider</th><th class="pb-2 text-right">Delivered</th><th class="pb-2 text-right">COD Collected</th><th class="pb-2 text-right">Verified Remitted</th><th class="pb-2 text-right">Held Unremitted</th><th class="pb-2 text-right">Period Earnings</th></tr></thead>
                        <tbody class="divide-y divide-outline-variant/40">
                            @forelse($riderPerformance as $r)
                                <tr>
                                    <td class="py-2.5 font-bold">{{ $r->full_name }}</td>
                                    <td class="py-2.5 text-right">{{ $r->delivered_count }}</td>
                                    <td class="py-2.5 text-right">₱{{ number_format($r->cod_collected_total, 2) }}</td>
                                    <td class="py-2.5 text-right text-emerald-700">₱{{ number_format($r->cod_verified_total, 2) }}</td>
                                    <td class="py-2.5 text-right text-amber-800">₱{{ number_format($r->courierProfile?->unremitted_cod_balance ?? 0, 2) }}</td>
                                    <td class="py-2.5 text-right font-bold text-primary">₱{{ number_format($r->period_earnings_total, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="py-6 text-center text-outline">No riders found for this logistics company.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- TAB 5: Transfer Courier Performance --}}
                <div x-show="tab === 'courier_performance'" class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-5 overflow-x-auto">
                    <h3 class="text-sm font-bold mb-3">Transfer Courier Performance & Trip Earnings</h3>
                    <table class="w-full text-left text-xs">
                        <thead><tr class="border-b border-outline-variant text-[11px] uppercase text-on-surface-variant"><th class="pb-2">Transfer Courier</th><th class="pb-2 text-right">Completed Manifests</th><th class="pb-2 text-right">Exceptions</th><th class="pb-2 text-right">Parcels Moved</th><th class="pb-2 text-right">Trip Compensation</th><th class="pb-2 text-right">Paid Out</th></tr></thead>
                        <tbody class="divide-y divide-outline-variant/40">
                            @forelse($transferCourierPerformance as $tc)
                                <tr>
                                    <td class="py-2.5 font-bold">{{ $tc['name'] }}</td>
                                    <td class="py-2.5 text-right">{{ $tc['completed_manifests'] }}</td>
                                    <td class="py-2.5 text-right {{ $tc['exception_manifests'] > 0 ? 'text-rose-700 font-bold' : '' }}">{{ $tc['exception_manifests'] }}</td>
                                    <td class="py-2.5 text-right">{{ $tc['parcels_moved'] }}</td>
                                    <td class="py-2.5 text-right font-bold text-primary">₱{{ number_format($tc['period_earnings'], 2) }}</td>
                                    <td class="py-2.5 text-right text-emerald-700">₱{{ number_format($tc['paid_out'], 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="py-6 text-center text-outline">No transfer couriers found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- TAB 6: Profitability & Routes --}}
                <div x-show="tab === 'profitability'" class="space-y-6">
                    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
                        <div class="bg-surface-container-lowest p-4 rounded-2xl border border-outline-variant"><span class="text-[10px] uppercase text-on-surface-variant block">Shipping Revenue</span><strong class="text-xl text-primary">₱{{ number_format($profitability['recognized_shipping_revenue'], 2) }}</strong></div>
                        <div class="bg-surface-container-lowest p-4 rounded-2xl border border-outline-variant"><span class="text-[10px] uppercase text-on-surface-variant block">Courier Comp Total</span><strong class="text-xl text-amber-800">₱{{ number_format($profitability['total_courier_comp'], 2) }}</strong></div>
                        <div class="bg-surface-container-lowest p-4 rounded-2xl border border-outline-variant"><span class="text-[10px] uppercase text-on-surface-variant block">Operating Expenses</span><strong class="text-xl text-rose-700">₱{{ number_format($profitability['operating_expenses_total'], 2) }}</strong></div>
                        <div class="bg-surface-container-lowest p-4 rounded-2xl border border-outline-variant">
                            <span class="text-[10px] uppercase text-on-surface-variant block">Net Operating Contribution</span>
                            <strong class="text-xl {{ $profitability['net_operating_contribution'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">₱{{ number_format($profitability['net_operating_contribution'], 2) }}</strong>
                            @if(!empty($profitability['contribution_note']))
                                <span class="text-[10px] text-outline block mt-0.5">{{ $profitability['contribution_note'] }}</span>
                            @endif
                        </div>
                        <div class="bg-surface-container-lowest p-4 rounded-2xl border border-outline-variant"><span class="text-[10px] uppercase text-on-surface-variant block">Operating Margin</span><strong class="text-xl">{{ $profitability['operating_margin_pct'] }}%</strong></div>
                    </div>

                    <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-5 overflow-x-auto">
                        <h3 class="text-sm font-bold mb-3">Route & Hub Pair Profitability</h3>
                        <table class="w-full text-left text-xs">
                            <thead><tr class="border-b border-outline-variant text-[11px] uppercase text-on-surface-variant"><th class="pb-2">Route</th><th class="pb-2 text-right">Orders</th><th class="pb-2 text-right">Shipping Revenue</th><th class="pb-2 text-right">Courier Cost</th><th class="pb-2 text-right">Net Contribution</th><th class="pb-2 text-right">Flag</th></tr></thead>
                            <tbody class="divide-y divide-outline-variant/40">
                                @forelse($profitability['route_profitability'] as $route)
                                    <tr>
                                        <td class="py-2.5 font-semibold">{{ $route['route'] }}</td>
                                        <td class="py-2.5 text-right">{{ $route['orders_count'] }}</td>
                                        <td class="py-2.5 text-right">₱{{ number_format($route['shipping_revenue'], 2) }}</td>
                                        <td class="py-2.5 text-right">₱{{ number_format($route['direct_courier_cost'], 2) }}</td>
                                        <td class="py-2.5 text-right font-bold {{ $route['is_unprofitable'] ? 'text-rose-700' : 'text-emerald-700' }}">₱{{ number_format($route['net_contribution'], 2) }}</td>
                                        <td class="py-2.5 text-right">
                                            @if($route['is_unprofitable'])
                                                <span class="px-2 py-0.5 rounded-full bg-rose-500/10 text-rose-700 text-[10px] font-bold">UNPROFITABLE</span>
                                            @else
                                                <span class="px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-700 text-[10px] font-bold">HEALTHY</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="py-6 text-center text-outline">No completed deliveries in range to analyze routes.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- TAB 7: Damage, Missing & Discrepancy Incidents --}}
                <div x-show="tab === 'exceptions'" class="space-y-6">
                    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
                        <div class="bg-surface-container-lowest p-4 rounded-2xl border border-outline-variant"><span class="text-[10px] uppercase text-on-surface-variant block">Unresolved Damage</span><strong class="text-2xl text-rose-700">{{ $incidentReport['open_damage'] }}</strong></div>
                        <div class="bg-surface-container-lowest p-4 rounded-2xl border border-outline-variant"><span class="text-[10px] uppercase text-on-surface-variant block">Missing / Lost</span><strong class="text-2xl text-rose-800">{{ $incidentReport['open_missing'] }}</strong></div>
                        <div class="bg-surface-container-lowest p-4 rounded-2xl border border-outline-variant"><span class="text-[10px] uppercase text-on-surface-variant block">Under Investigation</span><strong class="text-2xl text-amber-700">{{ $incidentReport['under_investigation'] }}</strong></div>
                        <div class="bg-surface-container-lowest p-4 rounded-2xl border border-outline-variant"><span class="text-[10px] uppercase text-on-surface-variant block">Found</span><strong class="text-2xl text-indigo-700">{{ $incidentReport['found'] }}</strong></div>
                        <div class="bg-surface-container-lowest p-4 rounded-2xl border border-outline-variant"><span class="text-[10px] uppercase text-on-surface-variant block">Resolved</span><strong class="text-2xl text-emerald-700">{{ $incidentReport['resolved'] }}</strong></div>
                    </div>

                    <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-5 overflow-x-auto">
                        <h3 class="text-sm font-bold mb-3">Parcel Damage, Missing &amp; Discrepancy Exception Report</h3>
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="border-b border-outline-variant text-[11px] uppercase text-on-surface-variant">
                                    <th class="pb-2">Tracking #</th>
                                    <th class="pb-2">Manifest</th>
                                    <th class="pb-2">Type &amp; Stage</th>
                                    <th class="pb-2">Status</th>
                                    <th class="pb-2">Hub</th>
                                    <th class="pb-2">Details &amp; Evidence</th>
                                    <th class="pb-2">Reported</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline-variant/40">
                                @forelse($incidentReport['incidents'] as $inc)
                                    <tr>
                                        <td class="py-2.5 font-mono font-bold">{{ $inc->tracking_number }}</td>
                                        <td class="py-2.5">
                                            @if ($inc->manifest)
                                                <a href="{{ route('logistics.transfers.manifests.show', $inc->manifest) }}" class="text-primary font-semibold hover:underline">{{ $inc->manifest->manifest_number }}</a>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="py-2.5">
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $inc->incident_type === 'missing' ? 'bg-rose-600 text-white' : ($inc->incident_type === 'damage' ? 'bg-rose-500/15 text-rose-700' : 'bg-amber-500/15 text-amber-800') }}">
                                                {{ ucfirst($inc->incident_type) }}
                                            </span>
                                            <span class="block text-[10px] text-on-surface-variant mt-0.5">{{ str_replace('_', ' ', ucfirst($inc->source_stage)) }}</span>
                                        </td>
                                        <td class="py-2.5 font-semibold">{{ $inc->status_label }}</td>
                                        <td class="py-2.5">{{ $inc->hub?->name ?? '—' }}</td>
                                        <td class="py-2.5 max-w-xs">
                                            @if ($inc->damage_type_label)
                                                <span class="font-semibold text-on-surface">[{{ $inc->damage_type_label }}]</span>
                                            @endif
                                            <span>{{ $inc->description }}</span>
                                            @if ($inc->evidence_url)
                                                <a href="{{ $inc->evidence_url }}" target="_blank" class="ml-1 text-primary underline text-[11px]">Evidence</a>
                                            @endif
                                        </td>
                                        <td class="py-2.5 text-[11px] text-on-surface-variant">
                                            {{ $inc->reporter?->full_name ?? 'Operator' }}<br>
                                            {{ $inc->reported_at?->format('M d, Y H:i') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="py-6 text-center text-outline">No parcel damage or missing incidents in this date range.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @if(!$reconciliation['is_balanced'])
                    <div class="bg-rose-500/5 border border-rose-500/20 rounded-3xl p-5">
                        <h3 class="text-sm font-bold text-rose-800 mb-2">Detected Reconciliation Discrepancies</h3>
                        <ul class="list-disc list-inside text-xs text-rose-800 space-y-1">
                            @foreach($reconciliation['issues'] as $iss)
                                <li><strong class="uppercase">[{{ $iss['severity'] }}]</strong> {{ $iss['message'] }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

            </div>
        </div>
    </div>
</div>
@endsection
