<?php

namespace App\Http\Controllers\Api\Mobile\Buyer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Mobile\ConversationResource;
use App\Http\Resources\Api\Mobile\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    /**
     * B14: List conversations
     * GET /api/mobile/v1/buyer/chat
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $conversations = Conversation::whereHas('participants', fn ($q) => $q->where('users.id', $user->id))
            ->with(['participants.sellerProfile', 'messages' => fn ($q) => $q->latest()->limit(1)])
            ->orderByDesc('last_message_at')
            ->get();

        return response()->json([
            'data' => ConversationResource::collection($conversations),
            'message' => 'Conversations retrieved successfully.',
        ]);
    }

    /**
     * B14: Conversation details with messages
     * GET /api/mobile/v1/buyer/chat/{conversation}
     */
    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        if (! $conversation->participants()->where('users.id', $user->id)->exists()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $messages = $conversation->messages()->with('sender.sellerProfile')->oldest()->get();
        $otherParticipant = $conversation->participants()->where('users.id', '!=', $user->id)->first();

        // Mark as read
        $conversation->participants()->updateExistingPivot($user->id, ['last_read_at' => now()]);

        return response()->json([
            'data' => [
                'conversation' => new ConversationResource($conversation),
                'other_participant' => $otherParticipant ? [
                    'id' => $otherParticipant->id,
                    'name' => $otherParticipant->sellerProfile?->business_name ?? $otherParticipant->full_name,
                    'avatar_url' => $otherParticipant->avatar_url,
                    'role' => $otherParticipant->role,
                ] : null,
                'messages' => MessageResource::collection($messages),
            ],
            'message' => 'Messages retrieved successfully.',
        ]);
    }

    /**
     * B14: Start chat with seller with optional product card
     * POST /api/mobile/v1/buyer/chat/start
     */
    public function startWithSeller(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'seller_id' => ['required', 'integer', 'exists:users,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
        ]);

        $seller = User::findOrFail($validated['seller_id']);

        $conversation = Conversation::whereHas('participants', fn ($q) => $q->where('users.id', $user->id))
            ->whereHas('participants', fn ($q) => $q->where('users.id', $seller->id))
            ->first();

        if (! $conversation) {
            $sellerName = $seller->sellerProfile?->business_name ?? $seller->full_name;
            $conversation = Conversation::create([
                'subject' => "Inquiry with {$sellerName}",
                'last_message_at' => now(),
            ]);

            $conversation->participants()->attach([$user->id, $seller->id]);
        }

        if (! empty($validated['product_id'])) {
            $product = Product::find($validated['product_id']);
            if ($product) {
                $productCard = "Hi! I'm interested in this product: [{$product->name}](" . url("/products/{$product->id}") . ") — Price: ₱" . number_format($product->price, 2);

                Message::create([
                    'conversation_id' => $conversation->id,
                    'sender_id' => $user->id,
                    'body' => $productCard,
                ]);

                $conversation->update(['last_message_at' => now()]);
            }
        }

        $conversation->load(['participants.sellerProfile', 'messages']);

        return response()->json([
            'data' => new ConversationResource($conversation),
            'message' => 'Chat initiated.',
        ]);
    }

    /**
     * B14: Send message
     * POST /api/mobile/v1/buyer/chat/{conversation}/message
     */
    public function sendMessage(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        if (! $conversation->participants()->where('users.id', $user->id)->exists()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($request->has('message') && ! $request->has('body')) {
            $request->merge(['body' => $request->input('message')]);
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $message = $conversation->messages()->create([
            'sender_id' => $user->id,
            'body' => $validated['body'],
        ]);

        $conversation->update(['last_message_at' => now()]);

        return response()->json([
            'data' => new MessageResource($message->load('sender.sellerProfile')),
            'message' => 'Message sent.',
        ], 201);
    }
}

