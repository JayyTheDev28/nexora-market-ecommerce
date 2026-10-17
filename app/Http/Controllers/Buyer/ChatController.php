<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChatController extends Controller
{
    /**
     * B14: Real-time buyer-seller chat index
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $conversations = Conversation::whereHas('participants', fn ($q) => $q->where('users.id', $user->id))
            ->with(['participants', 'messages' => fn ($q) => $q->latest()->limit(1)])
            ->orderByDesc('last_message_at')
            ->get();

        return view('buyer.chat.index', compact('conversations'));
    }

    /**
     * B14: View a single conversation
     */
    public function show(Request $request, Conversation $conversation): View
    {
        $user = $request->user();

        abort_unless($conversation->participants()->where('users.id', $user->id)->exists(), 403);

        $messages = $conversation->messages()->with('sender')->oldest()->get();
        $otherParticipant = $conversation->participants()->where('users.id', '!=', $user->id)->first();

        // Mark as read
        $conversation->participants()->updateExistingPivot($user->id, ['last_read_at' => now()]);

        return view('buyer.chat.show', compact('conversation', 'messages', 'otherParticipant'));
    }

    /**
     * B14: Initiate chat with seller with product card attachment
     */
    public function startWithSeller(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'seller_id' => ['required', 'integer', 'exists:users,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
        ]);

        $seller = User::findOrFail($validated['seller_id']);

        // Find existing conversation between buyer and seller
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

        // If product was provided, attach product card inquiry message
        if (! empty($validated['product_id'])) {
            $product = Product::find($validated['product_id']);
            if ($product) {
                $img = $product->gallery[0] ?? '';
                $productCard = "Hi! I'm interested in this product: [{$product->name}](" . url("/products/{$product->id}") . ") — Price: ₱" . number_format($product->price, 2);

                Message::create([
                    'conversation_id' => $conversation->id,
                    'sender_id' => $user->id,
                    'body' => $productCard,
                ]);

                $conversation->update(['last_message_at' => now()]);
            }
        }

        return redirect()->route('buyer.chat.show', $conversation->id);
    }

    /**
     * B14: Send message in conversation
     */
    public function sendMessage(Request $request, Conversation $conversation): RedirectResponse
    {
        $user = $request->user();

        abort_unless($conversation->participants()->where('users.id', $user->id)->exists(), 403);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $conversation->messages()->create([
            'sender_id' => $user->id,
            'body' => $validated['body'],
        ]);

        $conversation->update(['last_message_at' => now()]);

        return back()->with('status', 'Message sent.');
    }
}
