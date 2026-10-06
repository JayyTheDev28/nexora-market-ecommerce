<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function index(Request $request): View
    {
        $items = $request->user()->cartItems()->with('product')->latest()->get();

        return view('buyer.cart.index', compact('items'));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'variant' => ['nullable', 'array'],
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $product = Product::findOrFail($validated['product_id']);
        $variant = $validated['variant'] ?? null;
        $quantity = $validated['quantity'] ?? 1;

        // Same product + same variant combination = one line, quantity bumped.
        // Different variant (e.g. different color) = a separate line.
        $existing = $request->user()->cartItems()
            ->where('product_id', $product->id)
            ->get()
            ->first(fn (CartItem $item) => $item->variant === $variant);

        if ($existing) {
            $existing->increment('quantity', $quantity);
            $item = $existing->fresh('product');
        } else {
            $item = $request->user()->cartItems()->create([
                'product_id' => $product->id,
                'variant' => $variant,
                'quantity' => $quantity,
                'selected' => true,
            ])->load('product');
        }

        return response()->json([
            'item' => $this->formatItem($item),
            'cartCount' => $request->user()->cartItems()->sum('quantity'),
        ]);
    }

    public function update(Request $request, CartItem $cartItem): JsonResponse
    {
        abort_if($cartItem->user_id !== $request->user()->id, 403);

        $validated = $request->validate([
            'quantity' => ['sometimes', 'integer', 'min:1'],
            'selected' => ['sometimes', 'boolean'],
        ]);

        $cartItem->update($validated);

        return response()->json([
            'item' => $this->formatItem($cartItem->fresh('product')),
        ]);
    }

    public function destroy(Request $request, CartItem $cartItem): JsonResponse
    {
        abort_if($cartItem->user_id !== $request->user()->id, 403);

        $cartItem->delete();

        return response()->json([
            'cartCount' => $request->user()->cartItems()->sum('quantity'),
        ]);
    }

    public function selectAll(Request $request): JsonResponse
    {
        $validated = $request->validate(['selected' => ['required', 'boolean']]);

        $request->user()->cartItems()->update(['selected' => $validated['selected']]);

        return response()->json(['ok' => true]);
    }

    private function formatItem(CartItem $item): array
    {
        return [
            'key' => (string) $item->id,
            'id' => $item->product_id,
            'name' => $item->product->name,
            'image' => $item->product->gallery[0] ?? 'https://placehold.co/200x200/e5eeff/0058be?text=' . urlencode($item->product->name),
            'price' => (float) $item->product->price,
            'seller' => $item->product->seller->sellerProfile->business_name ?? '',
            'variant' => $item->variant,
            'qty' => $item->quantity,
            'selected' => $item->selected,
        ];
    }
}
