@extends('layouts.app')

@section('title', 'Customer Feedback — Seller Dashboard')

@section('content')
<div class="w-full bg-surface py-8 min-h-[70vh]">
    <div class="max-w-none px-margin-mobile md:px-margin-desktop">

        <x-seller-nav active="feedback" />

        <div class="flex items-center justify-between gap-4 mb-6">
            <h1 class="font-headline-lg text-headline-lg text-on-surface">Customer Feedback</h1>
            @if($averageRating)
                <div class="flex items-center gap-2 bg-surface-container-lowest border border-outline-variant rounded-full px-4 py-2">
                    <span class="material-symbols-outlined text-[18px] text-tertiary" style="font-variation-settings: 'FILL' 1">star</span>
                    <span class="font-label-md text-label-md text-on-surface">{{ $averageRating }} average</span>
                    <span class="font-body-sm text-body-sm text-on-surface-variant">({{ $reviews->count() }} review{{ $reviews->count() === 1 ? '' : 's' }})</span>
                </div>
            @endif
        </div>

        @if($reviews->isEmpty())
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-10 text-center flex flex-col items-center gap-3">
                <span class="material-symbols-outlined text-[36px] text-outline">rate_review</span>
                <p class="font-headline-sm text-headline-sm text-on-surface">No feedback yet</p>
                <p class="font-body-sm text-body-sm text-on-surface-variant">Reviews from buyers on completed orders will show up here.</p>
            </div>
        @else
            <div class="flex flex-col gap-4">
                @foreach($reviews as $review)
                    <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-5">
                        <div class="flex items-center justify-between gap-3 mb-2">
                            <div class="flex items-center gap-2">
                                <span class="font-label-md text-label-md text-on-surface">{{ $review->buyer->full_name }}</span>
                                <span class="font-body-sm text-body-sm text-on-surface-variant">· {{ $review->order->order_number }}</span>
                            </div>
                            <div class="flex text-tertiary">
                                @for($i = 0; $i < 5; $i++)
                                    <span class="material-symbols-outlined text-[16px]" @if($i < $review->rating) style="font-variation-settings: 'FILL' 1" @endif>star</span>
                                @endfor
                            </div>
                        </div>
                        @if($review->comment)
                            <p class="font-body-sm text-body-sm text-on-surface-variant">{{ $review->comment }}</p>
                        @endif
                        <p class="font-body-sm text-body-sm text-on-surface-variant mt-2">{{ $review->created_at->format('M j, Y') }}</p>
                    </div>
                @endforeach
            </div>
        @endif

    </div>
</div>
@endsection
