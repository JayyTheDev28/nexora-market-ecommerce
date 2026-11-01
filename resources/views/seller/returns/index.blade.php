@extends('layouts.app')

@section('title', 'Returns & Refund Requests — Seller Hub')

@section('content')
<div class="w-full min-h-screen bg-surface">
    <div class="flex flex-col lg:flex-row w-full">

            <x-seller-nav active="returns" />

            <div class="flex-1 min-w-0 p-4 sm:p-6 lg:p-8 bg-surface">
            <div class="w-full max-w-7xl mx-auto space-y-6">

        @if (session('status'))
            <div class="mb-6 bg-emerald-500/10 border border-emerald-500/30 rounded-2xl p-4 flex items-center gap-2 text-emerald-700 dark:text-emerald-300 font-label-md">
                <span class="material-symbols-outlined text-[20px]">check_circle</span>
                {{ session('status') }}
            </div>
        @endif

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="font-headline-lg text-headline-lg text-on-surface">Returns & Refund Requests</h1>
                <p class="font-body-md text-on-surface-variant">Review customer return claims, inspect uploaded proof photos/videos, and issue resolutions.</p>
            </div>
        </div>

        {{-- Status Filter Tabs --}}
        <div class="flex items-center gap-2 mb-6 overflow-x-auto pb-1 scrollbar-none">
            <a href="{{ route('seller.returns.index') }}" class="px-4 py-1.5 rounded-full text-xs font-bold transition-colors {{ !request('status') ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface-variant hover:bg-surface-container-high' }}">
                All Claims ({{ $counts['all'] }})
            </a>
            <a href="{{ route('seller.returns.index', ['status' => 'pending']) }}" class="px-4 py-1.5 rounded-full text-xs font-bold transition-colors {{ request('status') === 'pending' ? 'bg-error text-white' : 'bg-surface-container text-on-surface-variant hover:bg-surface-container-high' }}">
                Awaiting Review ({{ $counts['pending'] }})
            </a>
            <a href="{{ route('seller.returns.index', ['status' => 'approved']) }}" class="px-4 py-1.5 rounded-full text-xs font-bold transition-colors {{ request('status') === 'approved' ? 'bg-emerald-600 text-white' : 'bg-surface-container text-on-surface-variant hover:bg-surface-container-high' }}">
                Approved Returns ({{ $counts['approved'] }})
            </a>
            <a href="{{ route('seller.returns.index', ['status' => 'rejected']) }}" class="px-4 py-1.5 rounded-full text-xs font-bold transition-colors {{ request('status') === 'rejected' ? 'bg-surface-container-high text-on-surface font-semibold' : 'bg-surface-container text-on-surface-variant hover:bg-surface-container-high' }}">
                Rejected ({{ $counts['rejected'] }})
            </a>
        </div>

        {{-- Disputes Table --}}
        @if($disputes->isEmpty())
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-12 text-center flex flex-col items-center gap-2">
                <span class="material-symbols-outlined text-[48px] text-outline-variant mb-1">assignment_return</span>
                <p class="font-headline-sm text-headline-sm text-on-surface font-bold">No Return Requests</p>
                <p class="font-body-sm text-xs text-on-surface-variant">There are currently no customer return or refund requests under this filter.</p>
            </div>
        @else
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-outline-variant bg-surface-container-low font-bold text-on-surface-variant uppercase">
                                <th class="px-5 py-3">Order #</th>
                                <th class="px-5 py-3">Customer</th>
                                <th class="px-5 py-3">Claim Reason</th>
                                <th class="px-5 py-3">Proof Media</th>
                                <th class="px-5 py-3">Requested At</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="text-right px-5 py-3">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant">
                            @foreach($disputes as $dispute)
                                <tr class="hover:bg-surface-container-low/40 transition-colors">
                                    <td class="px-5 py-3.5 font-bold text-primary">
                                        <a href="{{ route('seller.returns.show', $dispute) }}" class="hover:underline">
                                            {{ $dispute->order->order_number }}
                                        </a>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <span class="font-bold text-on-surface">{{ $dispute->raisedBy->full_name }}</span>
                                    </td>
                                    <td class="px-5 py-3.5 max-w-xs">
                                        <p class="font-semibold text-on-surface line-clamp-1">{{ $dispute->reason }}</p>
                                        <p class="text-[11px] text-on-surface-variant line-clamp-1">{{ $dispute->details }}</p>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        @if(!empty($dispute->media_paths))
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-primary/10 text-primary flex items-center gap-1 w-fit">
                                                <span class="material-symbols-outlined text-[13px]">attach_file</span>
                                                {{ count($dispute->media_paths) }} files
                                            </span>
                                        @else
                                            <span class="text-[10px] text-on-surface-variant">None</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3.5 text-on-surface-variant">
                                        {{ $dispute->created_at->format('M j, Y H:i') }}
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase
                                            {{ $dispute->seller_status === 'pending' ? 'bg-amber-500/10 text-amber-600' : ($dispute->seller_status === 'approved' ? 'bg-emerald-500/10 text-emerald-600' : 'bg-error/10 text-error') }}">
                                            {{ $dispute->seller_status }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3.5 text-right">
                                        <a href="{{ route('seller.returns.show', $dispute) }}" class="px-3 py-1.5 rounded-full bg-primary text-on-primary text-xs font-bold hover:bg-primary/90 transition-colors inline-block">
                                            Inspect & Respond
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="px-5 py-4 border-t border-outline-variant bg-surface-container-low">
                    {{ $disputes->links() }}
                </div>
            </div>
        @endif

                </div>
        </div>
    </div>
</div>

@endsection
