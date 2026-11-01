@extends('layouts.app')

@section('title', 'Messages & Seller Chat — Nexora Market')

@section('content')
<div class="w-full bg-surface py-5 md:py-6 min-h-[75vh]">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <x-buyer-nav active="chat" />

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="font-headline-lg text-xl md:text-2xl font-bold tracking-tight text-on-surface">Messages & Seller Chat</h1>
                <p class="font-body-md text-xs md:text-sm text-on-surface-variant mt-1">Direct inquiries with merchant sellers and delivery coordination.</p>
            </div>
        </div>

        @if($conversations->isEmpty())
            <div class="p-12 text-center bg-surface-container-lowest rounded-3xl border border-outline-variant max-w-lg mx-auto flex flex-col items-center gap-3">
                <span class="material-symbols-outlined text-outline text-[48px]">chat_bubble_outline</span>
                <h3 class="text-sm font-bold text-on-surface">No conversations yet</h3>
                <p class="text-xs text-on-surface-variant">You can start a chat directly from any product page or order tracking view to ask sellers questions.</p>
                <a href="{{ route('catalog.index') }}" class="mt-2 px-5 py-2.5 rounded-xl bg-primary text-on-primary text-xs font-semibold hover:opacity-90 transition-opacity">
                    Explore Catalog
                </a>
            </div>
        @else
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant divide-y divide-outline-variant/60 max-w-4xl shadow-xs overflow-hidden">
                @foreach($conversations as $conv)
                    @php
                        $other = $conv->participants->firstWhere('id', '!=', auth()->id());
                        $lastMsg = $conv->messages->first();
                    @endphp
                    <a
                        href="{{ route('buyer.chat.show', $conv->id) }}"
                        class="p-4 flex items-center justify-between gap-4 hover:bg-surface-container-low transition-colors">
                        <div class="flex items-center gap-3 min-w-0">
                            <img
                                src="{{ $other?->avatar_url }}"
                                alt="{{ $other?->full_name }}"
                                class="w-12 h-12 rounded-full object-cover border border-outline-variant flex-shrink-0">
                            <div class="min-w-0">
                                <h4 class="text-xs font-bold text-on-surface truncate">
                                    {{ $other?->sellerProfile?->business_name ?? $other?->full_name ?? 'Seller' }}
                                </h4>
                                <p class="text-xs text-on-surface-variant truncate mt-0.5">
                                    {{ $lastMsg?->body ?? 'No messages yet' }}
                                </p>
                            </div>
                        </div>

                        <div class="text-right flex-shrink-0">
                            <span class="text-[10px] text-outline font-medium">
                                {{ $conv->last_message_at ? $conv->last_message_at->diffForHumans() : '' }}
                            </span>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif

    </div>
</div>
@endsection
