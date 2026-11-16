@extends('layouts.app')
@section('title', 'Logistics Monitoring — Admin Portal')
@section('content')
<div class="w-full min-h-screen bg-surface flex flex-col lg:flex-row">
    <x-admin-nav active="logistics-monitoring" />
    <main class="flex-1 p-6 lg:p-8 max-w-7xl mx-auto w-full space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-on-surface">Platform-Wide Logistics Monitoring</h1>
                <p class="text-sm text-on-surface-variant">Read-only operational oversight across all Logistics Companies, hubs, manifests, and parcel incidents.</p>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                @foreach ([
                    ['Active Companies', $stats['activeCompanies']],
                    ['Active Hubs', $stats['activeHubs']],
                    ['Active Transfers', $stats['activeTransfers']],
                    ['In-Transit Parcels', $stats['inTransitParcels']],
                    ['Delivered Parcels', $stats['deliveredParcels']],
                    ['Delayed (>48h)', $stats['delayedParcels']],
                    ['Missing Parcels', $stats['missingParcels']],
                    ['Damaged Parcels', $stats['damagedParcels']],
                    ['Quarantined', $stats['quarantinedParcels']],
                    ['Wrong-Hub', $stats['wrongHubIncidents']],
                    ['Open Investigations', $stats['openInvestigations']],
                ] as [$label, $val])
                    <div class="bg-surface-container-lowest p-4 rounded-2xl border border-outline-variant/60">
                        <div class="text-xs text-on-surface-variant">{{ $label }}</div>
                        <div class="text-xl font-bold text-on-surface mt-1">{{ number_format($val) }}</div>
                    </div>
                @endforeach
            </div>

            {{-- Parcel Tracking Oversight --}}
            <div class="bg-surface-container-lowest p-5 rounded-2xl border border-outline-variant/60 space-y-4">
                <h2 class="text-base font-bold">Parcel Tracking &amp; Custody Lookup</h2>
                <form method="GET" action="{{ route('admin.logistics-monitoring.index') }}" class="flex gap-3">
                    <input type="text" name="tracking" value="{{ $trackingSearch }}" placeholder="Enter Tracking Number or Order Number (e.g. NX-TRK-...)" class="flex-1 px-3 py-2 rounded-xl border border-outline-variant text-sm bg-surface">
                    <button type="submit" class="px-5 py-2 rounded-xl bg-primary text-on-primary text-sm font-semibold">Track Parcel</button>
                </form>

                @if ($trackingSearch !== '' && ! $trackedOrder)
                    <p class="text-sm text-red-600">No parcel found matching "{{ $trackingSearch }}".</p>
                @elseif ($trackedOrder)
                    <div class="p-4 rounded-xl bg-surface-container-low border border-outline-variant/60 space-y-3 text-sm">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                            <div><span class="text-xs text-on-surface-variant block">Order / Tracking</span><strong>{{ $trackedOrder->order_number }} / {{ $trackedOrder->tracking_number }}</strong></div>
                            <div><span class="text-xs text-on-surface-variant block">Status</span><strong>{{ ucwords(str_replace('_', ' ', $trackedOrder->status)) }}</strong></div>
                            <div><span class="text-xs text-on-surface-variant block">Current Hub</span><strong>{{ $trackedOrder->currentHub?->name ?? 'Unassigned' }}</strong></div>
                            <div><span class="text-xs text-on-surface-variant block">Current Custodian</span><strong>{{ $trackedOrder->currentCustodyLogistics?->logisticsProfile?->company_name ?? $trackedOrder->currentCustodyLogistics?->full_name ?? 'N/A' }}</strong></div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs pt-2 border-t border-outline-variant/40">
                            <div>Origin Hub: <strong>{{ $trackedOrder->originHub?->name ?? 'N/A' }}</strong> &bull; Pickup Rider: <strong>{{ $trackedOrder->pickupCourier?->full_name ?? 'Unassigned' }}</strong></div>
                            <div>Destination Hub: <strong>{{ $trackedOrder->destinationHub?->name ?? 'N/A' }}</strong> &bull; Delivery Rider: <strong>{{ $trackedOrder->deliveryCourier?->full_name ?? 'Unassigned' }}</strong></div>
                        </div>
                        @if ($trackedOrder->parcelIncidents->isNotEmpty())
                            <div class="pt-2 border-t border-outline-variant/40 text-xs">
                                <strong>Incidents:</strong>
                                @foreach ($trackedOrder->parcelIncidents as $inc)
                                    <span class="inline-block px-2 py-0.5 rounded bg-red-100 text-red-800 mr-1">{{ $inc->incident_number }} ({{ $inc->incident_type }} - {{ $inc->investigation_status }})</span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Logistics Companies --}}
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant/60 overflow-x-auto">
                <div class="p-4 border-b border-outline-variant/40 font-bold text-base">Logistics Companies</div>
                <table class="w-full text-sm">
                    <thead class="bg-surface-container-low text-xs uppercase text-on-surface-variant">
                        <tr>
                            <th class="text-left px-4 py-3">Company</th>
                            <th class="text-left px-4 py-3">Active Hubs</th>
                            <th class="text-left px-4 py-3">Active Manifests</th>
                            <th class="text-left px-4 py-3">Status</th>
                            <th class="text-right px-4 py-3">Details</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/40">
                        @foreach ($companies as $comp)
                            <tr>
                                <td class="px-4 py-3 font-medium">{{ $comp->logisticsProfile?->company_name ?? $comp->sortingCenterProfile?->business_name ?? $comp->full_name }}</td>
                                <td class="px-4 py-3">{{ $comp->active_hubs_count }}</td>
                                <td class="px-4 py-3">{{ $comp->active_manifests_count }}</td>
                                <td class="px-4 py-3">{{ ucfirst($comp->account_status) }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.logistics-monitoring.company', $comp) }}" class="text-primary font-semibold hover:underline">Inspect</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Recent Parcel Incidents --}}
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant/60 overflow-x-auto">
                <div class="p-4 border-b border-outline-variant/40 font-bold text-base">Parcel Incidents &amp; Investigations</div>
                <table class="w-full text-sm">
                    <thead class="bg-surface-container-low text-xs uppercase text-on-surface-variant">
                        <tr>
                            <th class="text-left px-4 py-3">Incident #</th>
                            <th class="text-left px-4 py-3">Tracking</th>
                            <th class="text-left px-4 py-3">Type</th>
                            <th class="text-left px-4 py-3">Hub</th>
                            <th class="text-left px-4 py-3">Investigation Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/40">
                        @forelse ($recentIncidents as $inc)
                            <tr>
                                <td class="px-4 py-3 font-mono text-xs">{{ $inc->incident_number ?? ('INC-' . $inc->id) }}</td>
                                <td class="px-4 py-3">{{ $inc->order?->tracking_number ?? 'N/A' }}</td>
                                <td class="px-4 py-3">{{ ucwords(str_replace('_', ' ', $inc->incident_type)) }}</td>
                                <td class="px-4 py-3">{{ $inc->hub?->name ?? 'N/A' }}</td>
                                <td class="px-4 py-3">{{ ucwords(str_replace('_', ' ', $inc->investigation_status)) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-6 text-center text-on-surface-variant">No parcel incidents recorded.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
    </main>
</div>
@endsection
