@extends('layouts.app')

@section('title', 'Disputes & Return Complaints — Admin Portal')

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

        {{-- Page Header --}}
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="font-headline-lg text-2xl font-bold text-on-surface">Complaints & Dispute Arbitration</h1>
                <p class="font-body-md text-sm text-on-surface-variant">Review customer claims, inspect photographic/video evidence, arbitrate returns and refunds fairly.</p>
            </div>

            {{-- Status Tabs --}}
            <div class="flex items-center gap-1 bg-surface-container-low p-1 rounded-2xl border border-outline-variant">
                <a href="{{ route('admin.disputes.index', ['status' => 'open', 'q' => $search]) }}"
                   class="px-3 py-1.5 rounded-xl font-label-md text-xs font-semibold transition-all {{ $status === 'open' ? 'bg-primary text-on-primary shadow-xs' : 'text-on-surface-variant hover:text-on-surface' }}">
                    Open ({{ $counts['open'] }})
                </a>
                <a href="{{ route('admin.disputes.index', ['status' => 'under_review', 'q' => $search]) }}"
                   class="px-3 py-1.5 rounded-xl font-label-md text-xs font-semibold transition-all {{ $status === 'under_review' ? 'bg-primary text-on-primary shadow-xs' : 'text-on-surface-variant hover:text-on-surface' }}">
                    In Review ({{ $counts['under_review'] }})
                </a>
                <a href="{{ route('admin.disputes.index', ['status' => 'resolved', 'q' => $search]) }}"
                   class="px-3 py-1.5 rounded-xl font-label-md text-xs font-semibold transition-all {{ $status === 'resolved' ? 'bg-primary text-on-primary shadow-xs' : 'text-on-surface-variant hover:text-on-surface' }}">
                    Resolved ({{ $counts['resolved'] }})
                </a>
                <a href="{{ route('admin.disputes.index', ['status' => 'rejected', 'q' => $search]) }}"
                   class="px-3 py-1.5 rounded-xl font-label-md text-xs font-semibold transition-all {{ $status === 'rejected' ? 'bg-primary text-on-primary shadow-xs' : 'text-on-surface-variant hover:text-on-surface' }}">
                    Rejected ({{ $counts['rejected'] }})
                </a>
                <a href="{{ route('admin.disputes.index', ['status' => 'all', 'q' => $search]) }}"
                   class="px-3 py-1.5 rounded-xl font-label-md text-xs font-semibold transition-all {{ $status === 'all' ? 'bg-primary text-on-primary shadow-xs' : 'text-on-surface-variant hover:text-on-surface' }}">
                    All
                </a>
            </div>
        </div>

        {{-- Search Row --}}
        <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-4 mb-6 shadow-xs">
            <form method="GET" action="{{ route('admin.disputes.index') }}" class="flex items-center gap-3">
                <input type="hidden" name="status" value="{{ $status }}">

                <div class="relative flex-1">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline text-[18px]">search</span>
                    <input type="text" name="q" value="{{ $search }}"
                           placeholder="Search by order number, buyer name, or dispute reason..."
                           class="w-full pl-9 pr-4 py-2 bg-surface-container rounded-xl text-xs text-on-surface placeholder:text-outline border border-outline-variant focus:outline-none focus:border-primary">
                </div>

                <button type="submit" class="px-4 py-2 rounded-xl bg-primary text-on-primary font-label-md text-xs font-semibold shadow-xs hover:bg-primary/90 transition-all">
                    Search
                </button>
                @if($search || $status !== 'all')
                    <a href="{{ route('admin.disputes.index') }}" class="px-3 py-2 rounded-xl border border-outline-variant text-on-surface-variant text-xs font-semibold hover:bg-surface-container transition-all">
                        Reset
                    </a>
                @endif
            </form>
        </div>

        {{-- Disputes Table --}}
        <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant overflow-hidden shadow-xs">
            @if($disputes->isEmpty())
                <div class="p-12 text-center flex flex-col items-center justify-center">
                    <span class="material-symbols-outlined text-[48px] text-outline mb-3">task_alt</span>
                    <h3 class="font-headline-sm text-base font-bold text-on-surface">No disputes found</h3>
                    <p class="font-body-sm text-xs text-on-surface-variant mt-1">There are no customer complaints in this view.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-surface-container-low border-b border-outline-variant text-on-surface-variant font-semibold">
                            <tr>
                                <th class="px-5 py-3.5">Order Info</th>
                                <th class="px-5 py-3.5">Customer / Complainant</th>
                                <th class="px-5 py-3.5">Merchant</th>
                                <th class="px-5 py-3.5">Reason & Claim</th>
                                <th class="px-5 py-3.5">Dispute Status</th>
                                <th class="px-5 py-3.5">Date Filed</th>
                                <th class="px-5 py-3.5 text-right">Arbitration</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant">
                            @foreach($disputes as $dispute)
                                <tr class="hover:bg-surface-container-low/50 transition-colors">
                                    <td class="px-5 py-4">
                                        <span class="font-bold text-on-surface block">#{{ $dispute->order->order_number }}</span>
                                        <span class="text-xs text-primary font-semibold">₱{{ number_format($dispute->order->total_amount, 2) }}</span>
                                        <span class="text-[10px] text-on-surface-variant block capitalize">{{ str_replace('_', ' ', $dispute->order->status) }}</span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="font-semibold text-on-surface block">{{ $dispute->raisedBy?->full_name ?? 'Buyer' }}</span>
                                        <span class="text-[10px] text-on-surface-variant">{{ $dispute->raisedBy?->email }}</span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="font-semibold text-on-surface block">{{ $dispute->order->seller->sellerProfile->business_name ?? $dispute->order->seller->full_name }}</span>
                                        <span class="text-[10px] text-on-surface-variant">{{ $dispute->order->seller->email }}</span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="font-bold text-on-surface block">{{ $dispute->reason }}</span>
                                        <span class="text-xs text-on-surface-variant truncate block max-w-xs" title="{{ $dispute->details }}">
                                            {{ $dispute->details }}
                                        </span>
                                        @if(!empty($dispute->media_paths))
                                            <span class="inline-flex items-center gap-1 text-[10px] text-primary font-semibold mt-1">
                                                <span class="material-symbols-outlined text-[13px]">image</span>
                                                {{ count($dispute->media_paths) }} Evidence File(s)
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4">
                                        @if($dispute->status === 'open')
                                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-700">Open</span>
                                        @elseif($dispute->status === 'under_review')
                                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-500/10 text-blue-700">Under Review</span>
                                        @elseif($dispute->status === 'resolved')
                                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-700">Resolved</span>
                                        @else
                                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-500/10 text-rose-700">Rejected</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 text-on-surface-variant text-[11px]">
                                        {{ $dispute->created_at->format('M d, Y h:i A') }}
                                    </td>
                                    <td class="px-5 py-4 text-right">
                                        <a href="{{ route('admin.disputes.show', $dispute) }}"
                                           class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full bg-secondary text-on-secondary font-label-md text-xs font-semibold shadow-xs hover:bg-secondary/90 transition-all">
                                            <span>Arbitrate</span>
                                            <span class="material-symbols-outlined text-[15px]">arrow_forward</span>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                @if($disputes->hasPages())
                    <div class="px-6 py-4 border-t border-outline-variant bg-surface-container-low">
                        {{ $disputes->links() }}
                    </div>
                @endif
            @endif
        </div>

    </div>
</div>
@endsection
