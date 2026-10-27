@extends('layouts.app')

@section('title', "Dispute #{$dispute->id} — Order #{$dispute->order->order_number} — Admin Portal")

@section('content')
<div class="w-full bg-surface py-8 min-h-[75vh]">
    <div class="max-w-none px-margin-mobile md:px-margin-desktop">

        <x-admin-nav active="disputes" />

        {{-- Flash messages --}}
        @if (session('status'))
            <div class="mb-6 bg-primary/10 border border-primary/30 rounded-2xl p-4 flex items-center justify-between">
                <div class="flex items-center gap-2 text-primary font-body-sm text-sm">
                    <span class="material-symbols-outlined text-[20px]">check_circle</span>
                    <span>{{ session('status') }}</span>
                </div>
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 bg-error/10 border border-error/30 rounded-2xl p-4">
                <ul class="list-disc list-inside text-xs text-error font-medium">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Back & Header --}}
        <div class="mb-6">
            <a href="{{ route('admin.disputes.index') }}" class="inline-flex items-center gap-1.5 text-xs text-on-surface-variant hover:text-primary transition-colors mb-3">
                <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                <span>Back to Disputes List</span>
            </a>

            <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 shadow-xs flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="font-headline-lg text-2xl font-bold text-on-surface">
                            Dispute for Order #{{ $dispute->order->order_number }}
                        </h1>
                        @if($dispute->status === 'open')
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-500/10 text-amber-700">Open Claim</span>
                        @elseif($dispute->status === 'under_review')
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-500/10 text-blue-700">Under Investigation</span>
                        @elseif($dispute->status === 'resolved')
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-500/10 text-emerald-700">Resolved</span>
                        @else
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-500/10 text-rose-700">Rejected</span>
                        @endif
                    </div>
                    <p class="font-body-sm text-xs text-on-surface-variant mt-1">
                        Filed by {{ $dispute->raisedBy?->full_name }} on {{ $dispute->created_at->format('F d, Y h:i A') }} ({{ $dispute->created_at->diffForHumans() }})
                    </p>
                </div>

                <div class="text-right">
                    <span class="text-xs text-on-surface-variant block">Order Total</span>
                    <span class="font-headline-md text-2xl font-bold text-primary">₱{{ number_format($dispute->order->total_amount, 2) }}</span>
                    <span class="text-[11px] text-on-surface-variant block capitalize">Status: {{ str_replace('_', ' ', $dispute->order->status) }}</span>
                </div>
            </div>
        </div>

        {{-- Dispute Arbitration Layout --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">

            {{-- Left 2 Columns: Evidence & Order Details --}}
            <div class="lg:col-span-2 space-y-6">

                {{-- Dispute Claim Details --}}
                <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 shadow-xs">
                    <h2 class="font-headline-sm text-sm font-bold text-on-surface mb-3 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px] text-rose-600">report_problem</span>
                        <span>Customer Dispute Claim</span>
                    </h2>

                    <div class="bg-surface-container-low rounded-2xl p-4 border border-outline-variant mb-4">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs text-on-surface-variant">Reason Category:</span>
                            <span class="font-bold text-xs text-rose-700">{{ $dispute->reason }}</span>
                        </div>
                        <p class="text-xs text-on-surface mt-2 whitespace-pre-line leading-relaxed">
                            {{ $dispute->details }}
                        </p>
                    </div>

                    {{-- Uploaded Photo/Video Evidence Gallery --}}
                    <div>
                        <h3 class="text-xs font-bold text-on-surface mb-2 flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-[16px] text-primary">photo_library</span>
                            <span>Uploaded Evidence ({{ count($dispute->media_paths ?? []) }})</span>
                        </h3>

                        @if(!empty($dispute->media_paths))
                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                                @foreach($dispute->media_paths as $media)
                                    @php
                                        $ext = strtolower(pathinfo($media, PATHINFO_EXTENSION));
                                        $isVideo = in_array($ext, ['mp4', 'mov', 'webm']);
                                    @endphp
                                    <div class="group relative rounded-2xl border border-outline-variant overflow-hidden bg-surface-container aspect-square">
                                        @if($isVideo)
                                            <video src="{{ Storage::url($media) }}" controls class="w-full h-full object-cover"></video>
                                        @else
                                            <a href="{{ Storage::url($media) }}" target="_blank" class="block w-full h-full">
                                                <img src="{{ Storage::url($media) }}" alt="Evidence file" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-200">
                                            </a>
                                        @endif
                                        <div class="absolute bottom-1 right-1">
                                            <a href="{{ Storage::url($media) }}" target="_blank" class="p-1 rounded-lg bg-black/60 text-white text-[10px] flex items-center gap-0.5">
                                                <span class="material-symbols-outlined text-[13px]">open_in_new</span>
                                            </a>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-xs text-outline">No media evidence uploaded with this claim.</p>
                        @endif
                    </div>
                </div>

                {{-- Ordered Items Table --}}
                <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 shadow-xs">
                    <h2 class="font-headline-sm text-sm font-bold text-on-surface mb-3 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px] text-primary">shopping_bag</span>
                        <span>Ordered Items in Order #{{ $dispute->order->order_number }}</span>
                    </h2>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-surface-container-low border-b border-outline-variant text-on-surface-variant font-semibold">
                                <tr>
                                    <th class="px-4 py-3">Product</th>
                                    <th class="px-4 py-3">Variant</th>
                                    <th class="px-4 py-3 text-right">Qty</th>
                                    <th class="px-4 py-3 text-right">Unit Price</th>
                                    <th class="px-4 py-3 text-right">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline-variant">
                                @foreach($dispute->order->items as $item)
                                    <tr>
                                        <td class="px-4 py-3 font-semibold text-on-surface">
                                            {{ $item->product->name ?? 'Product' }}
                                        </td>
                                        <td class="px-4 py-3 text-on-surface-variant">
                                            {{ $item->variant ?? 'Standard' }}
                                        </td>
                                        <td class="px-4 py-3 text-right font-medium">
                                            {{ $item->quantity }}
                                        </td>
                                        <td class="px-4 py-3 text-right font-medium">
                                            ₱{{ number_format($item->price, 2) }}
                                        </td>
                                        <td class="px-4 py-3 text-right font-bold text-on-surface">
                                            ₱{{ number_format($item->price * $item->quantity, 2) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

            {{-- Right Column: Parties & Official Arbitration Decision Form --}}
            <div class="space-y-6">

                {{-- Parties Summary --}}
                <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 shadow-xs">
                    <h2 class="font-headline-sm text-sm font-bold text-on-surface mb-3 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px] text-primary">group</span>
                        <span>Dispute Parties</span>
                    </h2>

                    <div class="space-y-3 text-xs">
                        <div class="p-3 rounded-2xl bg-surface-container-low border border-outline-variant">
                            <span class="text-[10px] text-primary font-bold uppercase tracking-wider block">Buyer (Complainant)</span>
                            <span class="font-bold text-on-surface block mt-1">{{ $dispute->order->buyer->full_name ?? 'Buyer' }}</span>
                            <span class="text-on-surface-variant block">{{ $dispute->order->buyer->email }}</span>
                            <span class="text-on-surface-variant block">{{ $dispute->order->buyer->contact_number }}</span>
                        </div>

                        <div class="p-3 rounded-2xl bg-surface-container-low border border-outline-variant">
                            <span class="text-[10px] text-secondary font-bold uppercase tracking-wider block">Seller (Merchant)</span>
                            <span class="font-bold text-on-surface block mt-1">{{ $dispute->order->seller->sellerProfile->business_name ?? $dispute->order->seller->full_name }}</span>
                            <span class="text-on-surface-variant block">{{ $dispute->order->seller->email }}</span>
                            <span class="text-on-surface-variant block">{{ $dispute->order->seller->contact_number }}</span>
                        </div>
                    </div>
                </div>

                {{-- Official Resolution Form --}}
                <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 shadow-xs">
                    <h2 class="font-headline-sm text-sm font-bold text-on-surface mb-3 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px] text-primary">gavel</span>
                        <span>Official Arbitration Decision</span>
                    </h2>

                    <form action="{{ route('admin.disputes.resolve', $dispute) }}" method="POST" class="space-y-4">
                        @csrf

                        <div>
                            <label class="block text-xs font-semibold text-on-surface mb-1">Dispute Status <span class="text-error">*</span></label>
                            <select name="status" required class="w-full px-3 py-2 bg-surface-container rounded-xl text-xs text-on-surface border border-outline-variant focus:outline-none focus:border-primary">
                                <option value="under_review" {{ $dispute->status === 'under_review' ? 'selected' : '' }}>Under Investigation</option>
                                <option value="resolved" {{ $dispute->status === 'resolved' ? 'selected' : '' }}>Resolve (Approve Claim)</option>
                                <option value="rejected" {{ $dispute->status === 'rejected' ? 'selected' : '' }}>Reject (Decline Claim)</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-on-surface mb-1">Order Outcome Action</label>
                            <select name="action_type" class="w-full px-3 py-2 bg-surface-container rounded-xl text-xs text-on-surface border border-outline-variant focus:outline-none focus:border-primary">
                                <option value="maintain_status">Maintain Current Order Status ({{ ucfirst(str_replace('_', ' ', $dispute->order->status)) }})</option>
                                <option value="mark_returned">Mark Order as Returned / Refund Approved</option>
                            </select>
                            <span class="text-[10px] text-outline block mt-0.5">Selecting "Mark as Returned" records the return in order history.</span>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-on-surface mb-1">Arbitration Note / Verdict <span class="text-error">*</span></label>
                            <textarea name="resolution_note" rows="4" required
                                      placeholder="State the findings, evidence evaluation, and verdict communicated to buyer and seller..."
                                      class="w-full px-3 py-2 bg-surface-container rounded-xl text-xs text-on-surface border border-outline-variant focus:outline-none focus:border-primary">{{ old('resolution_note', $dispute->resolution_note) }}</textarea>
                        </div>

                        <button type="submit" class="w-full py-2.5 rounded-xl bg-primary text-on-primary font-label-md text-xs font-semibold shadow-xs hover:bg-primary/90 transition-all flex items-center justify-center gap-1.5">
                            <span class="material-symbols-outlined text-[16px]">balance</span>
                            <span>Record Official Verdict</span>
                        </button>
                    </form>
                </div>

            </div>
        </div>

    </div>
</div>
@endsection
