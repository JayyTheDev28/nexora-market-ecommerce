@extends('layouts.app')

@section('title', 'Chat with ' . ($otherParticipant?->sellerProfile?->business_name ?? $otherParticipant?->full_name ?? 'Seller') . ' — Nexora Market')

@section('content')
<div class="w-full bg-surface py-6 md:py-8 min-h-[75vh]">
    <div class="max-w-4xl mx-auto px-4">

        {{-- Header Bar --}}
        <div class="p-4 bg-surface-container-lowest rounded-2xl border border-outline-variant shadow-xs flex items-center justify-between gap-4 mb-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('buyer.chat.index') }}" class="w-8 h-8 rounded-xl bg-surface-container flex items-center justify-center text-on-surface hover:bg-surface-container-high transition-colors">
                    <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                </a>
                <img src="{{ $otherParticipant?->avatar_url }}" class="w-10 h-10 rounded-full object-cover border border-outline-variant">
                <div>
                    <h2 class="text-xs font-bold text-on-surface">
                        {{ $otherParticipant?->sellerProfile?->business_name ?? $otherParticipant?->full_name }}
                    </h2>
                    <span class="text-[10px] text-emerald-600 font-semibold flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        Active Seller
                    </span>
                </div>
            </div>
            @if($otherParticipant?->isSeller())
                <a href="{{ route('catalog.index', ['seller_id' => $otherParticipant->id]) }}" class="px-3 py-1.5 rounded-xl border border-outline-variant text-xs font-semibold hover:bg-surface-container">
                    Visit Store
                </a>
            @endif
        </div>

        {{-- Messages Thread Box --}}
        <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-5 shadow-xs flex flex-col h-[520px]">
            <div class="flex-1 overflow-y-auto space-y-3 pr-2 flex flex-col" id="message-container">
                @forelse($messages as $msg)
                    @php
                        $isMe = $msg->sender_id === auth()->id();
                    @endphp
                    <div class="flex flex-col {{ $isMe ? 'items-end' : 'items-start' }}">
                        <div class="max-w-[75%] rounded-2xl p-3.5 text-xs leading-relaxed {{ $isMe ? 'bg-primary text-on-primary rounded-br-xs' : 'bg-surface-container-low text-on-surface rounded-bl-xs border border-outline-variant/60' }}">
                            {{-- Check if message has a product card link --}}
                            {!! nl2br(e($msg->body)) !!}
                        </div>
                        <span class="text-[10px] text-outline mt-1 px-1">
                            {{ $msg->created_at->format('h:i A') }}
                        </span>
                    </div>
                @empty
                    <div class="m-auto text-center text-xs text-on-surface-variant">
                        No messages yet. Send a greeting to start the conversation!
                    </div>
                @endforelse
            </div>

            {{-- Message Input Form --}}
            <form method="POST" action="{{ route('buyer.chat.message', $conversation->id) }}" class="mt-4 pt-3 border-t border-outline-variant/60 flex items-center gap-2">
                @csrf
                <input
                    type="text"
                    name="body"
                    required
                    placeholder="Type your message or inquiry here..."
                    autocomplete="off"
                    class="flex-1 rounded-xl border border-outline-variant px-4 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                <button
                    type="submit"
                    class="px-5 py-2.5 rounded-xl bg-primary text-on-primary text-xs font-semibold flex items-center gap-1.5 hover:opacity-90 transition-opacity">
                    <span class="material-symbols-outlined text-[16px]">send</span>
                    <span>Send</span>
                </button>
            </form>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const box = document.getElementById('message-container');
        if (box) box.scrollTop = box.scrollHeight;
    });
</script>
@endpush
