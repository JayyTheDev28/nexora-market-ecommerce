@extends('layouts.app')

@section('title', 'Customer Reviews & Feedback — Seller Hub')

@section('content')
<div class="w-full min-h-screen bg-surface">
    <div class="flex flex-col lg:flex-row w-full">

            <x-seller-nav active="feedback" />

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
                <h1 class="font-headline-lg text-headline-lg text-on-surface">Customer Reviews & Ratings</h1>
                <p class="font-body-md text-on-surface-variant">Monitor buyer satisfaction, review feedback photos, and respond to customer reviews.</p>
            </div>
            @if($averageRating)
                <div class="flex items-center gap-3 bg-surface-container-lowest border border-outline-variant rounded-2xl px-5 py-3 shadow-xs">
                    <div class="flex text-amber-500">
                        @for($i = 1; $i <= 5; $i++)
                            <span class="material-symbols-outlined text-[20px] {{ $i <= round($averageRating) ? 'fill-1' : '' }}">star</span>
                        @endfor
                    </div>
                    <div>
                        <span class="font-headline-sm text-base font-bold text-on-surface">{{ $averageRating }} / 5.0</span>
                        <span class="text-xs text-on-surface-variant block">({{ $ratingCounts['total'] }} total reviews)</span>
                    </div>
                </div>
            @endif
        </div>

        {{-- Filter Chips --}}
        <div class="flex flex-wrap items-center gap-2 mb-6">
            <a href="{{ route('seller.feedback.index') }}" class="px-3.5 py-1.5 rounded-full text-xs font-bold transition-colors {{ (!request('rating') && !request('reply_status')) ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface-variant hover:bg-surface-container-high' }}">
                All ({{ $ratingCounts['total'] }})
            </a>
            <a href="{{ route('seller.feedback.index', ['reply_status' => 'pending']) }}" class="px-3.5 py-1.5 rounded-full text-xs font-bold transition-colors {{ request('reply_status') === 'pending' ? 'bg-amber-500 text-white' : 'bg-surface-container text-on-surface-variant hover:bg-surface-container-high' }}">
                Awaiting Reply ({{ $ratingCounts['pending_reply'] }})
            </a>
            <a href="{{ route('seller.feedback.index', ['reply_status' => 'replied']) }}" class="px-3.5 py-1.5 rounded-full text-xs font-bold transition-colors {{ request('reply_status') === 'replied' ? 'bg-emerald-600 text-white' : 'bg-surface-container text-on-surface-variant hover:bg-surface-container-high' }}">
                Replied
            </a>
            <span class="text-outline-variant">|</span>
            @for($r = 5; $r >= 1; $r--)
                <a href="{{ route('seller.feedback.index', ['rating' => $r]) }}" class="px-3 py-1 rounded-full text-xs font-bold transition-colors flex items-center gap-1 {{ request('rating') == $r ? 'bg-primary text-on-primary' : 'bg-surface-container text-on-surface-variant hover:bg-surface-container-high' }}">
                    <span>{{ $r }}</span>
                    <span class="material-symbols-outlined text-[13px] text-amber-500 fill-1">star</span>
                    <span>({{ $ratingCounts[$r . '_star'] }})</span>
                </a>
            @endfor
        </div>

        {{-- Reviews List --}}
        @if($reviews->isEmpty())
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-12 text-center flex flex-col items-center gap-2">
                <span class="material-symbols-outlined text-[48px] text-outline-variant mb-1">rate_review</span>
                <p class="font-headline-sm text-headline-sm text-on-surface font-bold">No Reviews Found</p>
                <p class="font-body-sm text-xs text-on-surface-variant">There are no reviews matching this filter.</p>
            </div>
        @else
            <div class="space-y-4">
                @foreach($reviews as $review)
                    <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-5 shadow-xs">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-outline-variant/60 pb-3 mb-3">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-primary/10 text-primary font-bold text-xs flex items-center justify-center">
                                    {{ strtoupper(substr($review->buyer->full_name, 0, 1)) }}
                                </div>
                                <div>
                                    <span class="font-label-md text-xs font-bold text-on-surface">{{ $review->buyer->full_name }}</span>
                                    <span class="text-[11px] text-on-surface-variant ml-2">Order #{{ $review->order?->order_number }}</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="flex text-amber-500">
                                    @for($i = 1; $i <= 5; $i++)
                                        <span class="material-symbols-outlined text-[16px] {{ $i <= $review->rating ? 'fill-1' : '' }}">star</span>
                                    @endfor
                                </div>
                                <span class="text-[11px] text-on-surface-variant">{{ $review->created_at->format('M j, Y') }}</span>
                            </div>
                        </div>

                        {{-- Product info tag --}}
                        @if($review->product)
                            <div class="mb-2 flex items-center gap-2 text-xs text-on-surface-variant bg-surface-container-low px-3 py-1.5 rounded-xl w-fit">
                                <span class="material-symbols-outlined text-[15px]">inventory_2</span>
                                <span>{{ $review->product->name }}</span>
                            </div>
                        @endif

                        {{-- Review comment --}}
                        @if($review->comment)
                            <p class="text-xs text-on-surface leading-relaxed mt-2">{{ $review->comment }}</p>
                        @endif

                        {{-- Review photos --}}
                        @if(!empty($review->photos))
                            <div class="flex items-center gap-2 mt-3 overflow-x-auto pb-1">
                                @foreach($review->photos as $photo)
                                    <a href="{{ Storage::url($photo) }}" target="_blank" class="w-16 h-16 rounded-xl overflow-hidden border border-outline-variant shrink-0 bg-surface-container">
                                        <img src="{{ Storage::url($photo) }}" class="w-full h-full object-cover">
                                    </a>
                                @endforeach
                            </div>
                        @endif

                        {{-- Seller Reply Section --}}
                        <div class="mt-4 pt-3 border-t border-outline-variant/50">
                            @if($review->seller_reply)
                                <div class="p-3.5 rounded-xl bg-surface-container-low border border-outline-variant text-xs">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="font-bold text-primary flex items-center gap-1">
                                            <span class="material-symbols-outlined text-[14px]">reply</span>
                                            Store Response
                                        </span>
                                        <span class="text-[10px] text-on-surface-variant">
                                            {{ $review->seller_replied_at ? $review->seller_replied_at->format('M j, Y') : '' }}
                                        </span>
                                    </div>
                                    <p class="text-on-surface leading-relaxed">{{ $review->seller_reply }}</p>
                                    <button
                                        type="button"
                                        onclick="toggleReplyForm({{ $review->id }})"
                                        class="mt-2 text-[11px] text-primary hover:underline font-semibold block">
                                        Edit Response
                                    </button>
                                </div>
                            @else
                                <button
                                    type="button"
                                    onclick="toggleReplyForm({{ $review->id }})"
                                    class="px-3.5 py-1.5 rounded-full border border-primary text-primary hover:bg-primary/10 text-xs font-bold transition-colors flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[15px]">reply</span>
                                    Reply to Customer
                                </button>
                            @endif

                            {{-- Inline Reply Form --}}
                            <div id="replyForm_{{ $review->id }}" class="mt-3 hidden">
                                <form method="POST" action="{{ route('seller.feedback.reply', $review) }}" class="space-y-2">
                                    @csrf
                                    <textarea name="reply" rows="2" required placeholder="Write a courteous reply to thank the buyer or address their feedback..." class="w-full rounded-xl border border-outline-variant bg-surface px-3 py-2 text-xs text-on-surface focus:outline-none focus:border-primary">{{ $review->seller_reply }}</textarea>
                                    <div class="flex items-center justify-end gap-2">
                                        <button type="button" onclick="toggleReplyForm({{ $review->id }})" class="px-3 py-1 rounded-full border border-outline-variant text-xs text-on-surface-variant hover:bg-surface-container">
                                            Cancel
                                        </button>
                                        <button type="submit" class="px-4 py-1 rounded-full bg-primary text-on-primary text-xs font-bold hover:bg-primary/90">
                                            Post Reply
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-6">
                {{ $reviews->links() }}
            </div>
        @endif

                </div>
        </div>
    </div>
</div>


<script>
function toggleReplyForm(id) {
    const f = document.getElementById(`replyForm_${id}`);
    if (f) {
        f.classList.toggle('hidden');
    }
}
</script>
@endsection
