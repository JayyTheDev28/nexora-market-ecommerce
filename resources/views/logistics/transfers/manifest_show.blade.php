@extends('layouts.app')

@section('title', "Manifest {$manifest->manifest_number} — Nexora Logistics")

@section('content')
<div class="w-full min-h-screen bg-surface">
    <div class="flex flex-col lg:flex-row w-full">
        <x-logistics-nav active="transfers" />

        <div class="flex-1 min-w-0 p-4 sm:p-6 lg:p-8 bg-surface">
            <div class="w-full max-w-7xl mx-auto space-y-6">

                {{-- Breadcrumb & Header --}}
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2 text-xs text-on-surface-variant mb-1">
                            <a href="{{ route('logistics.transfers.index') }}" class="hover:text-primary flex items-center gap-1">
                                <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                                <span>Hub Transfers & Manifests</span>
                            </a>
                            <span>/</span>
                            <span class="font-semibold text-on-surface">{{ $manifest->manifest_number }}</span>
                        </div>
                        <h1 class="font-headline-lg text-2xl font-bold text-on-surface flex items-center gap-3">
                            <span>{{ $manifest->manifest_number }}</span>
                            <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wide
                                {{ match($manifest->status) {
                                    'completed' => 'bg-emerald-500/15 text-emerald-700',
                                    'exception' => 'bg-rose-500/15 text-rose-700',
                                    'in_transit' => 'bg-indigo-500/15 text-indigo-700',
                                    'arrived', 'receiving' => 'bg-amber-500/15 text-amber-700',
                                    'cancelled' => 'bg-slate-500/15 text-slate-600',
                                    default => 'bg-primary/15 text-primary',
                                } }}">
                                {{ str_replace('_', ' ', $manifest->status) }}
                            </span>
                        </h1>
                        <p class="text-xs text-on-surface-variant mt-1">
                            {{ $manifest->originHub?->name }} ({{ $manifest->originHub?->code }}) → {{ $manifest->destinationHub?->name }} ({{ $manifest->destinationHub?->code }})
                            · {{ $manifest->is_internal ? 'Internal Hub Transfer' : 'Inter-Company Custody Transfer' }}
                        </p>
                    </div>

                    @if ((int) $manifest->sender_logistics_id === (int) auth()->id() && in_array($manifest->status, ['draft', 'loading', 'ready_to_dispatch']))
                        <form action="{{ route('logistics.transfers.manifests.cancel', $manifest) }}" method="POST" onsubmit="return confirm('Cancel this transfer manifest?');">
                            @csrf
                            <button type="submit" class="px-4 py-2 rounded-full border border-rose-500/40 text-rose-700 text-xs font-semibold hover:bg-rose-500/10 cursor-pointer">
                                Cancel Manifest
                            </button>
                        </form>
                    @endif
                </div>

                @if (session('status'))
                    <div class="bg-primary/10 border border-primary/30 rounded-2xl p-4 flex items-center gap-3">
                        <span class="material-symbols-outlined text-primary text-[20px]">check_circle</span>
                        <p class="text-xs sm:text-sm text-primary font-medium">{{ session('status') }}</p>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="bg-rose-500/10 border border-rose-500/20 rounded-2xl p-4">
                        <ul class="list-disc list-inside text-xs text-rose-600 space-y-0.5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Summary Cards --}}
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-4">
                    <div class="bg-surface-container-lowest border border-outline-variant rounded-2xl p-4">
                        <span class="text-[11px] text-on-surface-variant">Expected</span>
                        <p class="text-xl font-bold text-on-surface mt-1">{{ $receivingCounts['expected'] }}</p>
                    </div>
                    <div class="bg-surface-container-lowest border border-outline-variant rounded-2xl p-4">
                        <span class="text-[11px] text-emerald-700">Received</span>
                        <p class="text-xl font-bold text-emerald-700 mt-1">{{ $receivingCounts['received'] }}</p>
                    </div>
                    <div class="bg-surface-container-lowest border border-outline-variant rounded-2xl p-4">
                        <span class="text-[11px] text-amber-700">Outstanding</span>
                        <p class="text-xl font-bold text-amber-700 mt-1">{{ $receivingCounts['outstanding'] }}</p>
                    </div>
                    <div class="bg-surface-container-lowest border border-outline-variant rounded-2xl p-4">
                        <span class="text-[11px] text-rose-700">Missing</span>
                        <p class="text-xl font-bold text-rose-700 mt-1">{{ $receivingCounts['missing'] }}</p>
                    </div>
                    <div class="bg-surface-container-lowest border border-outline-variant rounded-2xl p-4">
                        <span class="text-[11px] text-rose-700">Damaged / Discrepancy</span>
                        <p class="text-xl font-bold text-rose-700 mt-1">{{ $receivingCounts['damaged'] + $receivingCounts['discrepancy'] }}</p>
                    </div>
                </div>

                {{-- Assign Courier (Sender before dispatch) --}}
                @if ((int) $manifest->sender_logistics_id === (int) auth()->id() && in_array($manifest->status, ['draft', 'loading', 'ready_to_dispatch']))
                    <div class="bg-surface-container-lowest border border-outline-variant rounded-2xl p-5">
                        <h2 class="text-sm font-bold text-on-surface mb-3">Assign / Update Transfer Courier</h2>
                        <form action="{{ route('logistics.transfers.manifests.assign-courier', $manifest) }}" method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
                            @csrf
                            <div>
                                <label class="block text-xs font-semibold text-on-surface mb-1">Transfer Courier</label>
                                <select name="assigned_courier_id" required class="w-full rounded-xl border border-outline-variant bg-surface px-3 py-2 text-xs">
                                    <option value="">Select approved Transfer Courier...</option>
                                    @foreach ($transferCouriers as $tc)
                                        @php $busy = $tc->assignedManifests->where('id', '!=', $manifest->id)->isNotEmpty(); @endphp
                                        <option value="{{ $tc->id }}" {{ (int) $manifest->assigned_courier_id === (int) $tc->id ? 'selected' : '' }} {{ $busy ? 'disabled' : '' }}>
                                            {{ $tc->full_name }} {{ $busy ? '(Active Trip)' : '(Available)' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-on-surface mb-1">Vehicle Plate Number (Optional)</label>
                                <input type="text" name="vehicle_plate_number" value="{{ $manifest->vehicle_plate_number }}" placeholder="e.g. ABC 1234" class="w-full uppercase rounded-xl border border-outline-variant bg-surface px-3 py-2 text-xs">
                            </div>
                            <button type="submit" class="px-4 py-2.5 rounded-xl bg-primary text-on-primary text-xs font-semibold hover:bg-primary/90 cursor-pointer">
                                Save Courier Assignment
                            </button>
                        </form>
                    </div>
                @endif

                {{-- Receiving Hub Verification Form (Receiver after arrival) --}}
                @if ((int) $manifest->receiver_logistics_id === (int) auth()->id() && in_array($manifest->status, ['arrived', 'receiving', 'exception']))
                    <div class="bg-surface-container-lowest border-2 border-primary/30 rounded-2xl p-5">
                        <div class="flex flex-wrap items-center justify-between gap-3 mb-1">
                            <h2 class="text-sm font-bold text-on-surface">Receiving Hub Parcel Verification</h2>
                            <div class="flex items-center gap-2">
                                <button type="button" onclick="window.toggleManifestCamera()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-900 text-white text-xs font-semibold hover:bg-slate-800 cursor-pointer">
                                    <span class="material-symbols-outlined text-[16px]">photo_camera</span>
                                    <span>Scan with Camera</span>
                                </button>
                                <button type="button" onclick="window.lookupManifestParcel()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-surface-container-high text-on-surface text-xs font-semibold hover:bg-surface-container-highest cursor-pointer">
                                    <span class="material-symbols-outlined text-[16px]">search</span>
                                    <span>Lookup Parcel</span>
                                </button>
                            </div>
                        </div>
                        <p class="text-xs text-on-surface-variant mb-4">Scan using camera or USB/Bluetooth barcode scanner, inspect physical condition, and confirm classification. Custody transfers only for accepted parcels.</p>

                        <form id="manifest-verify-form" action="{{ route('logistics.transfers.manifests.verify-parcel', $manifest) }}" method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                            @csrf
                            <div>
                                <label class="block text-xs font-semibold text-on-surface mb-1">Tracking Number</label>
                                <input id="manifest-tracking-input" type="text" name="tracking_number" required autofocus placeholder="Scan or type tracking #" class="w-full font-mono rounded-xl border border-outline-variant bg-surface px-3 py-2 text-xs">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-on-surface mb-1">Classification</label>
                                <select id="manifest-classification-select" name="classification" required class="w-full rounded-xl border border-outline-variant bg-surface px-3 py-2 text-xs">
                                    <option value="received">Received (Accept & Transfer Custody)</option>
                                    <option value="missing">Missing</option>
                                    <option value="damaged">Damaged</option>
                                    <option value="discrepancy">Discrepancy / Needs Review</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-on-surface mb-1">Notes (Optional)</label>
                                <input id="manifest-notes-input" type="text" name="notes" placeholder="Condition or discrepancy details" class="w-full rounded-xl border border-outline-variant bg-surface px-3 py-2 text-xs">
                            </div>
                            <button type="submit" class="px-4 py-2.5 rounded-xl bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-700 cursor-pointer">
                                Verify Parcel
                            </button>
                        </form>

                        @include('logistics.components.camera_scanner_modal', ['scannerId' => 'manifest-camera'])

                        {{-- Scanned Parcel Verification Preview Card --}}
                        <div id="manifest-lookup-preview" class="hidden mt-4 rounded-xl border p-3.5 text-xs"></div>
                    </div>
                @endif

                {{-- Manifest Parcels Table --}}
                <div class="bg-surface-container-lowest border border-outline-variant rounded-2xl overflow-hidden">
                    <div class="px-5 py-4 border-b border-outline-variant flex items-center justify-between">
                        <h2 class="text-sm font-bold text-on-surface">Manifest Parcels ({{ $manifest->items->count() }})</h2>
                        <span class="text-xs text-on-surface-variant">
                            Courier: <strong>{{ $manifest->assignedCourier?->full_name ?? 'Unassigned' }}</strong>
                            @if ($manifest->vehicle_plate_number)
                                · Plate: <strong>{{ $manifest->vehicle_plate_number }}</strong>
                            @endif
                        </span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-surface-container-low text-on-surface-variant uppercase text-[11px]">
                                <tr>
                                    <th class="px-4 py-3">Tracking #</th>
                                    <th class="px-4 py-3">Order #</th>
                                    <th class="px-4 py-3">Loading Status</th>
                                    <th class="px-4 py-3">Receiving Status</th>
                                    <th class="px-4 py-3">Notes</th>
                                    @if ((int) $manifest->receiver_logistics_id === (int) auth()->id() && in_array($manifest->status, ['arrived', 'receiving', 'exception']))
                                        <th class="px-4 py-3 text-right">Quick Verify</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline-variant/60">
                                @foreach ($manifest->items as $item)
                                    <tr>
                                        <td class="px-4 py-3 font-mono font-semibold text-on-surface">{{ $item->tracking_number }}</td>
                                        <td class="px-4 py-3 text-on-surface-variant">#{{ $item->order?->order_number }}</td>
                                        <td class="px-4 py-3">
                                            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold {{ $item->loading_status === 'loaded' ? 'bg-emerald-500/15 text-emerald-700' : 'bg-slate-500/15 text-slate-600' }}">
                                                {{ ucfirst($item->loading_status) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold {{ match($item->receiving_status) {
                                                'received' => 'bg-emerald-500/15 text-emerald-700',
                                                'missing', 'damaged', 'discrepancy' => 'bg-rose-500/15 text-rose-700',
                                                default => 'bg-amber-500/15 text-amber-700',
                                            } }}">
                                                {{ ucfirst($item->receiving_status) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-on-surface-variant">{{ $item->issue_notes ?? '—' }}</td>
                                        @if ((int) $manifest->receiver_logistics_id === (int) auth()->id() && in_array($manifest->status, ['arrived', 'receiving', 'exception']))
                                            <td class="px-4 py-3 text-right">
                                                @if ($item->receiving_status !== 'received')
                                                    <form action="{{ route('logistics.transfers.manifests.verify-parcel', $manifest) }}" method="POST" class="inline">
                                                        @csrf
                                                        <input type="hidden" name="tracking_number" value="{{ $item->tracking_number }}">
                                                        <input type="hidden" name="classification" value="received">
                                                        <button type="submit" class="px-3 py-1 rounded-full bg-emerald-600 text-white text-[11px] font-semibold hover:bg-emerald-700 cursor-pointer">
                                                            Accept
                                                        </button>
                                                    </form>
                                                @else
                                                    <span class="text-emerald-700 font-semibold text-[11px]">Verified</span>
                                                @endif
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Audit Trail --}}
                @if ($auditLogs->isNotEmpty())
                    <div class="bg-surface-container-lowest border border-outline-variant rounded-2xl p-5">
                        <h2 class="text-sm font-bold text-on-surface mb-3">Manifest Audit Trail</h2>
                        <div class="space-y-2 text-xs">
                            @foreach ($auditLogs as $log)
                                <div class="flex items-center justify-between py-1.5 border-b border-outline-variant/40 last:border-0">
                                    <div>
                                        <span class="font-semibold text-on-surface">{{ str_replace('_', ' ', ucfirst($log->action)) }}</span>
                                        <span class="text-on-surface-variant">by {{ $log->operator?->full_name ?? 'System' }}</span>
                                        @if ($log->reason)
                                            <span class="text-rose-700">— {{ $log->reason }}</span>
                                        @endif
                                    </div>
                                    <span class="text-on-surface-variant text-[11px]">{{ $log->created_at?->format('M d, Y h:i A') }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    window.toggleManifestCamera = function () {
        if (!window.NexoraCameraScanner) return;
        window.NexoraCameraScanner.toggle('manifest-camera', function (code) {
            const input = document.getElementById('manifest-tracking-input');
            if (input) {
                input.value = code;
            }
            window.lookupManifestParcel(code);
        });
    };

    window.lookupManifestParcel = async function (overrideCode) {
        const input = document.getElementById('manifest-tracking-input');
        const box = document.getElementById('manifest-lookup-preview');
        const trackingNumber = (overrideCode || input?.value || '').trim();
        if (!trackingNumber || !box) return;

        box.classList.remove('hidden');
        box.className = 'mt-4 rounded-xl border border-outline-variant bg-surface p-3.5 text-xs';
        box.innerHTML = '<div class="text-on-surface-variant">Resolving <span class="font-mono font-bold">' + trackingNumber + '</span> on manifest...</div>';

        try {
            const response = await fetch('{{ route('logistics.transfers.manifests.lookup-parcel', $manifest) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                },
                body: JSON.stringify({ tracking_number: trackingNumber }),
            });

            const payload = await response.json();
            if (!response.ok) {
                box.className = 'mt-4 rounded-xl border border-rose-500/40 bg-rose-500/10 p-3.5 text-xs text-rose-700';
                box.innerHTML = '<div class="font-bold">Barcode Rejected</div><div class="mt-1">' + (payload.message || 'Tracking number not found on this manifest.') + '</div>';
                return;
            }

            const d = payload.data;
            box.className = 'mt-4 rounded-xl border border-primary/40 bg-primary/5 p-3.5 text-xs';
            box.innerHTML = `
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div class="space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-mono font-bold text-sm text-on-surface">${d.tracking_number}</span>
                            <span class="text-on-surface-variant">Order #${d.order_number || '—'}</span>
                            <span class="px-2 py-0.5 rounded-full bg-amber-500/15 text-amber-800 font-semibold text-[11px]">Receiving: ${d.receiving_status.toUpperCase()}</span>
                        </div>
                        <div class="text-on-surface-variant">
                            <strong>Sender:</strong> ${d.sender_name} · <strong>Recipient:</strong> ${d.recipient_name} · <strong>Destination:</strong> ${d.destination}
                        </div>
                        <div class="text-on-surface-variant">
                            <strong>Weight:</strong> ${d.weight_kg.toFixed(2)} kg · <strong>${d.payment_method}:</strong> ₱${d.cod_amount.toFixed(2)} · <strong>Hub:</strong> ${d.destination_hub}
                        </div>
                    </div>
                    <div class="text-primary font-semibold shrink-0">Inspect physical parcel & click Verify Parcel</div>
                </div>
            `;
        } catch (err) {
            box.className = 'mt-4 rounded-xl border border-rose-500/40 bg-rose-500/10 p-3.5 text-xs text-rose-700';
            box.innerHTML = '<div class="font-bold">Lookup Failed</div><div class="mt-1">Unable to verify barcode online. You can still submit the form manually.</div>';
        }
    };

    // Trigger parcel preview on USB scanner Enter key without immediately blind-accepting damaged/missing items
    document.getElementById('manifest-tracking-input')?.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            window.lookupManifestParcel(this.value);
        }
    });
</script>
@endpush
