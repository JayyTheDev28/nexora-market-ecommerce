<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ChatController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $conversations = Conversation::whereHas('participants', fn ($q) => $q->where('users.id', $user->id))
            ->with([
                'participants' => fn ($q) => $q->where('users.id', '!=', $user->id),
                'messages' => fn ($q) => $q->latest()->limit(1),
            ])
            ->orderByDesc('last_message_at')
            ->get();

        $activeConversation = null;
        $messages = collect();
        $otherParticipant = null;

        if ($convId = $request->query('conversation')) {
            $activeConversation = $conversations->firstWhere('id', (int) $convId);
        }

        if (! $activeConversation && $conversations->isNotEmpty()) {
            $activeConversation = $conversations->first();
        }

        if ($activeConversation) {
            $messages = $activeConversation->messages()->with('sender')->oldest()->get();
            $otherParticipant = $activeConversation->participants->firstWhere('id', '!=', $user->id);

            // Mark as read
            $activeConversation->participants()->updateExistingPivot($user->id, ['last_read_at' => now()]);
        }

        return view('seller.chat.index', compact('conversations', 'activeConversation', 'messages', 'otherParticipant'));
    }

    public function sendMessage(Request $request, Conversation $conversation): RedirectResponse
    {
        $user = $request->user();

        abort_unless($conversation->participants()->where('users.id', $user->id)->exists(), 403);

        $validated = $request->validate([
            'message' => ['nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx', 'max:5120'],
        ]);

        if (empty($validated['message']) && ! $request->hasFile('attachment')) {
            return back()->withErrors(['message' => 'Please enter a message or attach a file.']);
        }

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('chat_attachments', 'public');
        }

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'body' => $validated['message'] ?? ($attachmentPath ? '[Attachment]' : ''),
            'attachment_path' => $attachmentPath,
        ]);

        $conversation->update(['last_message_at' => now()]);

        return redirect()->route('seller.chat.index', ['conversation' => $conversation->id]);
    }
}
