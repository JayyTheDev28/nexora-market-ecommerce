@extends('layouts.app')

@section('title', 'Buyer Messages & Inquiries — Seller Hub')

@section('content')
<div class="w-full bg-surface py-8 min-h-[70vh]">
    <div class="max-w-none px-margin-mobile md:px-margin-desktop">

        <x-seller-nav active="chat" />

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="font-headline-lg text-headline-lg text-on-surface">Customer Conversations</h1>
                <p class="font-body-md text-on-surface-variant">Real-time messaging with buyers regarding orders, product inquiries, and assistance.</p>
            </div>
        </div>

        @if($conversations->isEmpty())
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-12 text-center flex flex-col items-center gap-2">
                <span class="material-symbols-outlined text-[48px] text-outline-variant mb-1">chat</span>
                <p class="font-headline-sm text-headline-sm text-on-surface font-bold">No Conversations Yet</p>
                <p class="font-body-sm text-xs text-on-surface-variant max-w-sm">
                    When shoppers message you from your public storefront or product pages, their inquiries will appear here.
                </p>
            </div>
        @else
            <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant overflow-hidden shadow-sm grid grid-cols-1 md:grid-cols-3 min-h-[550px]">

                {{-- Conversations List (Left Column) --}}
                <div class="md:col-span-1 border-r border-outline-variant bg-surface-container-low/40 flex flex-col">
                    <div class="p-4 border-b border-outline-variant bg-surface-container-low font-bold text-xs text-on-surface-variant uppercase tracking-wider">
                        Active Inquiries ({{ $conversations->count() }})
                    </div>
                    <div class="divide-y divide-outline-variant overflow-y-auto flex-1">
                        @foreach($conversations as $conv)
                            @php
                                $other = $conv->participants->firstWhere('id', '!=', auth()->id());
                                $lastMsg = $conv->messages->first();
                                $isActive = $activeConversation && $activeConversation->id === $conv->id;
                            @endphp
                            <a href="{{ route('seller.chat.index', ['conversation' => $conv->id]) }}" class="block p-4 hover:bg-surface-container-low transition-colors {{ $isActive ? 'bg-primary/5 border-l-4 border-primary' : '' }}">
                                <div class="flex items-center justify-between gap-2 mb-1">
                                    <span class="font-bold text-xs text-on-surface line-clamp-1">
                                        {{ $other ? $other->full_name : 'Customer' }}
                                    </span>
                                    <span class="text-[10px] text-on-surface-variant shrink-0">
                                        {{ $conv->last_message_at ? $conv->last_message_at->diffForHumans(null, true) : '' }}
                                    </span>
                                </div>
                                @if($lastMsg)
                                    <p class="text-xs text-on-surface-variant line-clamp-1">
                                        {{ $lastMsg->sender_id === auth()->id() ? 'You: ' : '' }}{{ $lastMsg->body ?: '[Attachment]' }}
                                    </p>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>

                {{-- Active Chat Thread (Right Column) --}}
                <div class="md:col-span-2 flex flex-col h-[550px] bg-surface-container-lowest">
                    @if($activeConversation)
                        {{-- Thread Header --}}
                        <div class="px-6 py-3.5 border-b border-outline-variant bg-surface-container-low/60 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full bg-primary/10 text-primary font-bold text-xs flex items-center justify-center">
                                    {{ strtoupper(substr($otherParticipant->full_name ?? 'C', 0, 1)) }}
                                </div>
                                <div>
                                    <h3 class="font-bold text-xs text-on-surface">{{ $otherParticipant->full_name ?? 'Customer' }}</h3>
                                    <span class="text-[10px] text-on-surface-variant">{{ $otherParticipant->email ?? '' }}</span>
                                </div>
                            </div>
                            @if($activeConversation->order)
                                <a href="{{ route('seller.orders.show', $activeConversation->order) }}" class="px-3 py-1 rounded-full border border-outline-variant text-[11px] font-bold text-primary hover:underline">
                                    View Order {{ $activeConversation->order->order_number }}
                                </a>
                            @endif
                        </div>

                        {{-- Messages Stream --}}
                        <div class="p-6 overflow-y-auto flex-1 space-y-4" id="chatStream">
                            @forelse($messages as $msg)
                                @php
                                    $isMine = ($msg->sender_id === auth()->id());
                                @endphp
                                <div class="flex flex-col {{ $isMine ? 'items-end' : 'items-start' }}">
                                    <div class="max-w-[75%] rounded-2xl px-4 py-2.5 text-xs shadow-xs {{ $isMine ? 'bg-primary text-on-primary rounded-br-none' : 'bg-surface-container text-on-surface rounded-bl-none' }}">
                                        @if($msg->body)
                                            <p class="leading-relaxed">{{ $msg->body }}</p>
                                        @endif
                                        @if($msg->attachment_path)
                                            @php
                                                $attUrl = Storage::url($msg->attachment_path);
                                                $isImg = preg_match('/\.(jpg|jpeg|png|webp)$/i', $msg->attachment_path);
                                            @endphp
                                            <div class="mt-2">
                                                @if($isImg)
                                                    <a href="{{ $attUrl }}" target="_blank">
                                                        <img src="{{ $attUrl }}" class="rounded-xl max-h-48 object-cover">
                                                    </a>
                                                @else
                                                    <a href="{{ $attUrl }}" target="_blank" class="flex items-center gap-1.5 underline font-semibold">
                                                        <span class="material-symbols-outlined text-[16px]">description</span>
                                                        Download Attachment
                                                    </a>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                    <span class="text-[10px] text-on-surface-variant mt-1 px-1">
                                        {{ $msg->created_at->format('g:i A') }}
                                    </span>
                                </div>
                            @empty
                                <div class="text-center text-xs text-on-surface-variant py-12">
                                    No messages yet. Send a greeting to start the conversation.
                                </div>
                            @endforelse
                        </div>

                        {{-- Message Input Bar --}}
                        <form method="POST" action="{{ route('seller.chat.message', $activeConversation) }}" enctype="multipart/form-data" class="p-4 border-t border-outline-variant bg-surface-container-low/30">
                            @csrf
                            <div class="flex items-center gap-2">
                                <label class="p-2 rounded-full text-on-surface-variant hover:bg-surface-container cursor-pointer shrink-0" title="Attach file or photo">
                                    <span class="material-symbols-outlined text-[20px]">attach_file</span>
                                    <input type="file" name="attachment" class="hidden" onchange="document.getElementById('attBadge').classList.remove('hidden')">
                                </label>
                                <span id="attBadge" class="hidden px-2 py-0.5 rounded text-[10px] font-bold bg-primary text-on-primary">File selected</span>
                                <input
                                    type="text"
                                    name="message"
                                    placeholder="Type your response to the buyer..."
                                    class="flex-1 rounded-full border border-outline-variant px-4 py-2 text-xs text-on-surface bg-surface focus:outline-none focus:border-primary">
                                <button type="submit" class="p-2 rounded-full bg-primary text-on-primary hover:bg-primary/90 shrink-0">
                                    <span class="material-symbols-outlined text-[20px]">send</span>
                                </button>
                            </div>
                        </form>
                    @else
                        <div class="flex items-center justify-center h-full text-xs text-on-surface-variant">
                            Select a conversation to read and reply.
                        </div>
                    @endif
                </div>

            </div>
        @endif

    </div>
</div>

<script>
window.addEventListener('DOMContentLoaded', () => {
    const stream = document.getElementById('chatStream');
    if (stream) {
        stream.scrollTop = stream.scrollHeight;
    }
});
</script>
@endsection
