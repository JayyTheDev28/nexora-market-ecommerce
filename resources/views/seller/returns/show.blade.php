@extends('layouts.app')

@section('title', 'Return Inspection — Order ' . $dispute->order->order_number)

@section('content')
<div class="w-full bg-surface py-8 min-h-[70vh]">
    <div class="max-w-none px-margin-mobile md:px-margin-desktop">

        <x-seller-nav active="returns" />

        @if (session('status'))
            <div class="mb-6 bg-emerald-500/10 border border-emerald-500/30 rounded-2xl p-4 flex items-center gap-2 text-emerald-700 dark:text-emerald-300 font-label-md">
                <span class="material-symbols-outlined text-[20px]">check_circle</span>
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 bg-error/10 border border-error/30 rounded-2xl p-4 text-error font-body-sm">
                <ul class="list-disc pl-5 space-y-1">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="flex items-center gap-3 mb-6">
            <a href="{{ route('seller.returns.index') }}" class="p-2 rounded-full border border-outline-variant text-on-surface-variant hover:text-on-surface hover:bg-surface-container transition-colors">
                <span class="material-symbols-outlined text-[20px]">arrow_back</span>
            </a>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="font-headline-lg text-headline-lg text-on-surface font-black">
                        Return Request for Order {{ $dispute->order->order_number }}
                    </h1>
                    <span class="px-3 py-0.5 rounded-full text-xs font-bold uppercase
                        {{ $dispute->seller_status === 'pending' ? 'bg-amber-500/10 text-amber-600 border border-amber-500/20' : ($dispute->seller_status === 'approved' ? 'bg-emerald-500/10 text-emerald-600' : 'bg-error/10 text-error') }}">
                        {{ $dispute->seller_status }}
                    </span>
                </div>
                <p class="font-body-sm text-xs text-on-surface-variant mt-0.5">
                    Submitted by {{ $dispute->raisedBy->full_name }} on {{ $dispute->created_at->format('M j, Y \a\t g:i A') }}
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

            <div class="lg:col-span-2 space-y-6">

                {{-- Buyer's Claim & Description --}}
                <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-6 shadow-sm">
                    <h2 class="font-headline-sm text-sm font-bold text-on-surface border-b border-outline-variant pb-3 mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary text-[20px]">report_problem</span>
                        Customer Reason & Statement
                    </h2>

                    <div class="space-y-3 text-xs">
                        <div>
                            <span class="text-on-surface-variant font-semibold">Claimed Reason:</span>
                            <p class="font-bold text-sm text-on-surface mt-0.5">{{ $dispute->reason }}</p>
                        </div>
                        <div>
                            <span class="text-on-surface-variant font-semibold">Customer Explanation:</span>
                            <div class="p-3.5 rounded-xl bg-surface-container-low border border-outline-variant text-on-surface leading-relaxed mt-1">
                                {{ $dispute->details }}
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Uploaded Photo / Video Evidence Gallery --}}
                <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-6 shadow-sm">
                    <h2 class="font-headline-sm text-sm font-bold text-on-surface border-b border-outline-variant pb-3 mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary text-[20px]">photo_library</span>
                        Photo & Video Evidence Proof
                    </h2>

                    @if(empty($dispute->media_paths))
                        <p class="text-xs text-on-surface-variant">No media proof files attached.</p>
                    @else
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                            @foreach($dispute->media_paths as $media)
                                @php
                                    $isVid = preg_match('/\.(mp4|mov)$/i', $media);
                                    $url = Storage::url($media);
                                @endphp
                                <div class="rounded-xl overflow-hidden border border-outline-variant aspect-square bg-black relative group">
                                    @if($isVid)
                                        <video src="{{ $url }}" controls class="w-full h-full object-cover"></video>
                                    @else
                                        <a href="{{ $url }}" target="_blank">
                                            <img src="{{ $url }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-200">
                                        </a>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Order Items Affected --}}
                <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-6 shadow-sm">
                    <h2 class="font-headline-sm text-sm font-bold text-on-surface border-b border-outline-variant pb-3 mb-4">
                        Items in This Order
                    </h2>
                    <div class="divide-y divide-outline-variant text-xs">
                        @foreach($dispute->order->items as $item)
                            <div class="py-2.5 flex items-center justify-between">
                                <div>
                                    <p class="font-bold text-on-surface">{{ $item->product_name }}</p>
                                    @if($item->variant)
                                        <p class="text-[11px] text-on-surface-variant">{{ is_array($item->variant) ? implode(', ', $item->variant) : $item->variant }}</p>
                                    @endif
                                </div>
                                <span class="font-mono text-on-surface font-semibold">₱{{ number_format($item->price, 2) }} × {{ $item->quantity }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

            </div>

            {{-- Decision & Resolution Panel --}}
            <div class="space-y-6">

                <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-6 shadow-sm space-y-4">
                    <h2 class="font-headline-sm text-sm font-bold text-on-surface border-b border-outline-variant pb-2">
                        Merchant Decision
                    </h2>

                    @if($dispute->seller_status === 'pending')
                        <div class="space-y-4">
                            {{-- Approve Action --}}
                            <form method="POST" action="{{ route('seller.returns.approve', $dispute) }}" class="space-y-3 p-4 rounded-xl border border-emerald-500/30 bg-emerald-500/5">
                                @csrf
                                <h3 class="font-bold text-xs text-emerald-700 dark:text-emerald-300 flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[16px]">check_circle</span>
                                    Approve Return / Refund
                                </h3>
                                <div>
                                    <label class="block text-[11px] font-semibold text-on-surface mb-1">Return Instructions for Buyer</label>
                                    <textarea name="return_instructions" rows="2" placeholder="e.g. Please ship back using any courier to our warehouse at: ..." class="w-full rounded-lg border border-outline-variant bg-surface px-2.5 py-1.5 text-xs text-on-surface focus:outline-none"></textarea>
                                </div>
                                <label class="flex items-center gap-2 cursor-pointer text-[11px] font-semibold text-on-surface">
                                    <input type="checkbox" name="restore_stock" value="1" checked class="rounded text-primary focus:ring-primary w-4 h-4">
                                    Restore product inventory stock
                                </label>
                                <button type="submit" onclick="return confirm('Approve this return request?');" class="w-full py-2 px-3 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition-colors">
                                    Approve & Send Instructions
                                </button>
                            </form>

                            {{-- Reject Action --}}
                            <form method="POST" action="{{ route('seller.returns.reject', $dispute) }}" class="space-y-3 p-4 rounded-xl border border-error/30 bg-error/5">
                                @csrf
                                <h3 class="font-bold text-xs text-error flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[16px]">cancel</span>
                                    Reject Return Request
                                </h3>
                                <div>
                                    <label class="block text-[11px] font-semibold text-on-surface mb-1">Reason for Rejection <span class="text-error">*</span></label>
                                    <textarea name="rejection_reason" rows="2" required placeholder="State why the evidence does not warrant a refund..." class="w-full rounded-lg border border-outline-variant bg-surface px-2.5 py-1.5 text-xs text-on-surface focus:outline-none"></textarea>
                                </div>
                                <button type="submit" onclick="return confirm('Reject this return request?');" class="w-full py-2 px-3 rounded-lg bg-error hover:bg-error/90 text-white font-bold text-xs transition-colors">
                                    Reject Claim
                                </button>
                            </form>
                        </div>
                    @else
                        <div class="p-4 rounded-xl bg-surface-container-low text-xs space-y-2">
                            <p class="font-bold text-on-surface">Recorded Decision:</p>
                            <p class="capitalize"><strong class="text-on-surface">Status:</strong> {{ $dispute->seller_status }}</p>
                            @if($dispute->seller_response)
                                <p><strong class="text-on-surface">Note:</strong> {{ $dispute->seller_response }}</p>
                            @endif
                            @if($dispute->return_instructions)
                                <p><strong class="text-on-surface">Instructions:</strong> {{ $dispute->return_instructions }}</p>
                            @endif
                            @if($dispute->seller_response_at)
                                <p class="text-[10px] text-on-surface-variant">Recorded on {{ $dispute->seller_response_at->format('M j, Y H:i') }}</p>
                            @endif
                        </div>
                    @endif
                </div>

            </div>

        </div>

    </div>
</div>
@endsection
