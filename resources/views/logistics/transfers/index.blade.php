@extends('layouts.app')

@section('title', 'Hub Transfers & Cross-Docking — Nexora Logistics')

@section('content')
<div class="w-full min-h-screen bg-surface">
    <div class="flex flex-col lg:flex-row w-full">
        <x-logistics-nav active="transfers" />

        <div class="flex-1 min-w-0 p-4 sm:p-6 lg:p-8 bg-surface">
            <div class="w-full max-w-7xl mx-auto space-y-6">

                {{-- Header --}}
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-2">
                    <div>
                        <h1 class="font-headline-lg text-2xl md:text-3xl font-bold text-on-surface">Hub Transfers & Cross-Docking</h1>
                        <p class="font-body-md text-sm text-on-surface-variant mt-1">
                            Track inter-region parcel movements between logistics companies. Custody shifts only when the receiving hub confirms delivery.
                        </p>
                    </div>
                </div>

                {{-- Alerts --}}
                @if (session('status'))
                    <div class="bg-primary/10 border border-primary/30 rounded-2xl p-4 flex items-center gap-3">
                        <span class="material-symbols-outlined text-primary text-[20px]">check_circle</span>
                        <p class="font-body-sm text-xs md:text-sm text-primary font-medium">{{ session('status') }}</p>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="bg-rose-500/10 border border-rose-500/20 rounded-2xl p-4">
                        <p class="font-label-md text-xs font-bold text-rose-700">Please correct the following errors:</p>
                        <ul class="list-disc list-inside text-xs text-rose-600 mt-1 space-y-0.5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Parcels Awaiting Cross-Region Hub Dispatch --}}
                @if ($awaitingDispatch->isNotEmpty())
                    <div class="bg-surface-container-lowest border border-amber-500/30 rounded-2xl p-5 shadow-xs">
                        <div class="flex items-center justify-between gap-3 mb-3">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-amber-600 text-[22px]">swap_horizontal_circle</span>
                                <h2 class="text-sm font-bold text-on-surface uppercase tracking-wide">
                                    Cross-Region Parcels Awaiting Hub Dispatch ({{ $awaitingDispatch->count() }})
                                </h2>
                            </div>
                            <span class="text-xs text-on-surface-variant">Parcels received at your hub destined for another logistics company's territory.</span>
                        </div>

                        <div class="divide-y divide-outline-variant/50">
                            @foreach ($awaitingDispatch as $order)
                                <div class="py-3.5 flex flex-col md:flex-row md:items-center justify-between gap-4">
                                    <div>
                                        <div class="flex items-center gap-2.5">
                                            <span class="font-mono text-xs font-bold text-primary">#{{ $order->order_number }}</span>
                                            <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-surface-container text-on-surface-variant uppercase">
                                                {{ str_replace('_', ' ', $order->status) }}
                                            </span>
                                        </div>
                                        <p class="text-xs text-on-surface mt-1">
                                            <strong>Destination:</strong> {{ $order->shipping_barangay }}, {{ $order->shipping_city }}, {{ $order->shipping_province }}
                                        </p>
                                        <p class="text-[11px] text-on-surface-variant mt-0.5">
                                            Destination Carrier: <strong>{{ $order->deliveryLogistics?->full_name ?? 'Assigned Logistics' }}</strong>
                                            @if ($order->destinationHub)
                                                &bull; Hub: <strong>{{ $order->destinationHub->name }}</strong>
                                            @endif
                                        </p>
                                    </div>

                                    {{-- Dispatch Form --}}
                                    <form method="POST" action="{{ route('logistics.transfers.dispatch', $order) }}" class="flex flex-wrap items-center gap-2">
                                        @csrf
                                        <div>
                                            <select name="sender_hub_id" required class="px-2.5 py-1.5 rounded-xl border border-outline-variant bg-surface text-xs text-on-surface">
                                                <option value="">Dispatch From Hub...</option>
                                                @foreach ($myHubs as $mHub)
                                                    <option value="{{ $mHub->id }}" {{ $order->origin_hub_id == $mHub->id ? 'selected' : '' }}>
                                                        {{ $mHub->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div>
                                            <select name="receiver_hub_id" required class="px-2.5 py-1.5 rounded-xl border border-outline-variant bg-surface text-xs text-on-surface">
                                                <option value="">Destination Hub...</option>
                                                @foreach ($otherHubs as $oHub)
                                                    <option value="{{ $oHub->id }}" {{ $order->destination_hub_id == $oHub->id ? 'selected' : '' }}>
                                                        {{ $oHub->name }} ({{ $oHub->logisticsCompany?->full_name }})
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <button type="submit" class="px-3.5 py-1.5 rounded-xl bg-primary text-on-primary text-xs font-semibold hover:bg-primary/90 shadow-xs flex items-center gap-1.5">
                                            <span class="material-symbols-outlined text-[16px]">local_shipping</span>
                                            <span>Dispatch to Hub</span>
                                        </button>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Tabs & Search --}}
                <div class="bg-surface-container-lowest border border-outline-variant/80 rounded-2xl p-5 shadow-xs">
                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4 border-b border-outline-variant/60 pb-4">
                        {{-- Tabs --}}
                        <div class="flex items-center gap-2 overflow-x-auto">
                            <a href="{{ route('logistics.transfers.index', ['tab' => 'incoming']) }}"
                               class="px-4 py-2 rounded-xl text-xs font-semibold transition-colors flex items-center gap-2 shrink-0 {{ $tab === 'incoming' ? 'bg-primary text-on-primary' : 'bg-surface-container hover:bg-surface-container-high text-on-surface' }}">
                                <span>Incoming Transfers</span>
                                <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $tab === 'incoming' ? 'bg-white/20 text-white' : 'bg-surface text-primary' }}">
                                    {{ $counts['incoming'] }}
                                </span>
                            </a>

                            <a href="{{ route('logistics.transfers.index', ['tab' => 'in_transit']) }}"
                               class="px-4 py-2 rounded-xl text-xs font-semibold transition-colors flex items-center gap-2 shrink-0 {{ $tab === 'in_transit' ? 'bg-primary text-on-primary' : 'bg-surface-container hover:bg-surface-container-high text-on-surface' }}">
                                <span>Outgoing in Transit</span>
                                <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $tab === 'in_transit' ? 'bg-white/20 text-white' : 'bg-surface text-on-surface-variant' }}">
                                    {{ $counts['in_transit'] }}
                                </span>
                            </a>

                            <a href="{{ route('logistics.transfers.index', ['tab' => 'completed']) }}"
                               class="px-4 py-2 rounded-xl text-xs font-semibold transition-colors flex items-center gap-2 shrink-0 {{ $tab === 'completed' ? 'bg-primary text-on-primary' : 'bg-surface-container hover:bg-surface-container-high text-on-surface' }}">
                                <span>Transfer History</span>
                                <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $tab === 'completed' ? 'bg-white/20 text-white' : 'bg-surface text-on-surface-variant' }}">
                                    {{ $counts['completed'] }}
                                </span>
                            </a>
                        </div>

                        {{-- Search --}}
                        <form method="GET" action="{{ route('logistics.transfers.index') }}" class="flex items-center gap-2">
                            <input type="hidden" name="tab" value="{{ $tab }}">
                            <div class="relative">
                                <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-[18px]">search</span>
                                <input type="text" name="q" value="{{ $search }}" placeholder="Search tracking #, order, carrier..."
                                       class="pl-9 pr-3 py-1.5 rounded-xl border border-outline-variant bg-surface text-xs text-on-surface focus:outline-none focus:border-primary w-64">
                            </div>
                            @if ($search)
                                <a href="{{ route('logistics.transfers.index', ['tab' => $tab]) }}" class="text-xs text-on-surface-variant hover:text-on-surface">Clear</a>
                            @endif
                        </form>
                    </div>

                    {{-- Custody Rule Alert Banner --}}
                    <div class="mt-4 p-3 bg-surface-container-low rounded-xl text-[11px] text-on-surface-variant flex items-center gap-2 border border-outline-variant/40">
                        <span class="material-symbols-outlined text-primary text-[18px]">shield</span>
                        <span>
                            <strong>Custody Invariant:</strong> While a parcel travels between hubs, legal custody remains with the sending logistics company. Custody transfers only when the receiving company confirms receipt at its hub.
                        </span>
                    </div>

                    {{-- Transfers Table --}}
                    <div class="mt-4 overflow-x-auto">
                        @if ($transfers->isEmpty())
                            <div class="p-10 text-center text-xs text-on-surface-variant">
                                <span class="material-symbols-outlined text-3xl text-on-surface-variant/60 mb-2">move_item</span>
                                <p>No transfers found in this view.</p>
                            </div>
                        @else
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="border-b border-outline-variant text-[11px] font-bold text-on-surface-variant uppercase tracking-wider">
                                        <th class="py-3 px-3">Transfer / Order</th>
                                        <th class="py-3 px-3">Sending Carrier & Hub</th>
                                        <th class="py-3 px-3">Receiving Carrier & Hub</th>
                                        <th class="py-3 px-3">Dispatched / Received</th>
                                        <th class="py-3 px-3">Custody Status</th>
                                        <th class="py-3 px-3 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-outline-variant/60">
                                    @foreach ($transfers as $t)
                                        <tr class="hover:bg-surface-container/50 transition-colors">
                                            <td class="py-3.5 px-3">
                                                <p class="font-mono font-bold text-primary">{{ $t->tracking_number }}</p>
                                                <p class="text-[11px] text-on-surface-variant mt-0.5">Order #{{ $t->order?->order_number }}</p>
                                            </td>

                                            <td class="py-3.5 px-3">
                                                <p class="font-semibold text-on-surface">{{ $t->senderLogistics?->full_name ?? 'Sender' }}</p>
                                                <p class="text-[11px] text-on-surface-variant flex items-center gap-1 mt-0.5">
                                                    <span class="material-symbols-outlined text-[14px]">warehouse</span>
                                                    <span>{{ $t->senderHub?->name }}</span>
                                                </p>
                                            </td>

                                            <td class="py-3.5 px-3">
                                                <p class="font-semibold text-on-surface">{{ $t->receiverLogistics?->full_name ?? 'Receiver' }}</p>
                                                <p class="text-[11px] text-on-surface-variant flex items-center gap-1 mt-0.5">
                                                    <span class="material-symbols-outlined text-[14px]">warehouse</span>
                                                    <span>{{ $t->receiverHub?->name }}</span>
                                                </p>
                                            </td>

                                            <td class="py-3.5 px-3">
                                                <p class="text-on-surface font-medium">{{ $t->dispatched_at?->format('M d, Y h:i A') }}</p>
                                                @if ($t->received_at)
                                                    <p class="text-[11px] text-emerald-700 font-semibold mt-0.5">Received: {{ $t->received_at->format('M d, Y h:i A') }}</p>
                                                @else
                                                    <p class="text-[11px] text-amber-600 font-medium mt-0.5">In Transit</p>
                                                @endif
                                            </td>

                                            <td class="py-3.5 px-3">
                                                @if ($t->status === 'received')
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/10 text-emerald-700">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                        <span>Custody with {{ $t->receiverLogistics?->full_name }}</span>
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-500/10 text-amber-700">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                                        <span>Custody with {{ $t->senderLogistics?->full_name }} (Sender)</span>
                                                    </span>
                                                @endif
                                            </td>

                                            <td class="py-3.5 px-3 text-right">
                                                @if ($tab === 'incoming' && $t->status === 'in_transit')
                                                    <form method="POST" action="{{ route('logistics.transfers.confirm', $t) }}"
                                                          onsubmit="return confirm('Confirm receipt of parcel #{{ $t->order?->order_number }} at {{ $t->receiverHub?->name }}? Legal custody will shift to your company.');">
                                                        @csrf
                                                        <button type="submit" class="px-3 py-1.5 rounded-xl bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-700 shadow-xs inline-flex items-center gap-1">
                                                            <span class="material-symbols-outlined text-[16px]">how_to_reg</span>
                                                            <span>Confirm Receipt at Hub</span>
                                                        </button>
                                                    </form>
                                                @else
                                                    <span class="text-[11px] text-on-surface-variant italic">
                                                        {{ ucfirst($t->status) }}
                                                    </span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>

                            <div class="mt-4">
                                {{ $transfers->links() }}
                            </div>
                        @endif
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>
@endsection

