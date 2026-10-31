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
    /**
     * B6: Persistent cart across sessions, grouping items by store
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $items = $user->cartItems()
            ->with(['product' => fn ($q) => $q->withTrashed()->with('seller.sellerProfile')])
            ->latest()
            ->get();

        // Group items by seller/store
        $grouped = $items->groupBy(function (CartItem $item) {
            return $item->product->seller_id ?? 0;
        });

        // User's available claimed vouchers for checkout preview
        $claimedVouchers = $user->vouchers()
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->wherePivotNull('used_at')
            ->get();

        return view('buyer.cart.index', compact('items', 'grouped', 'claimedVouchers'));
    }

    /**
     * B6: Variant line merging, per-buyer purchase limits, persistent cart
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'variant' => ['nullable', 'array'],
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $product = Product::with('seller.sellerProfile')->findOrFail($validated['product_id']);

        if ($product->seller?->sellerProfile?->is_vacation_mode) {
            $storeName = $product->seller->sellerProfile->business_name ?? 'The store';
            return response()->json(['error' => "{$storeName} is currently on vacation mode and not accepting new orders."], 422);
        }

        if ($product->stock <= 0) {
            return response()->json(['error' => 'This product is currently out of stock.'], 422);
        }

        $variant = $validated['variant'] ?? null;
        $quantity = $validated['quantity'] ?? 1;

        // B4: Check per-buyer purchase limits
        if ($product->max_purchase_limit !== null && $product->max_purchase_limit > 0) {
            $userTotalPurchased = $request->user()->orders()
                ->where('status', '!=', 'cancelled')
                ->whereHas('items', fn ($q) => $q->where('product_id', $product->id))
                ->with('items')
                ->get()
                ->flatMap->items
                ->where('product_id', $product->id)
                ->sum('quantity');

            $currentCartQty = $request->user()->cartItems()
                ->where('product_id', $product->id)
                ->sum('quantity');

            if (($userTotalPurchased + $currentCartQty + $quantity) > $product->max_purchase_limit) {
                $remaining = max(0, $product->max_purchase_limit - ($userTotalPurchased + $currentCartQty));
                return response()->json([
                    'error' => "Purchase limit reached! You can only purchase a maximum of {$product->max_purchase_limit} units of this item. You can add at most {$remaining} more.",
                ], 422);
            }
        }

        // B6: Variant line merging: same product + same variant = bump quantity
        $normalizeVariant = function ($v) {
            if (empty($v)) {
                return null;
            }
            if (is_string($v)) {
                $decoded = json_decode($v, true);
                if (is_array($decoded)) {
                    $v = $decoded;
                }
            }
            if (! is_array($v) || empty($v)) {
                return null;
            }
            ksort($v);
            return $v;
        };

        $targetVariant = $normalizeVariant($variant);

        $existing = $request->user()->cartItems()
            ->where('product_id', $product->id)
            ->get()
            ->first(function (CartItem $item) use ($normalizeVariant, $targetVariant) {
                $itemV = $normalizeVariant($item->variant);
                if ($itemV === null && $targetVariant === null) {
                    return true;
                }
                if ($itemV === null || $targetVariant === null) {
                    return false;
                }
                return $itemV == $targetVariant;
            });

        if ($existing) {
            $newQty = $existing->quantity + $quantity;
            if ($newQty > $product->stock) {
                return response()->json(['error' => "Cannot add more. Available stock: {$product->stock}."], 422);
            }
            $existing->update(['quantity' => $newQty]);
            $item = $existing->fresh(['product.seller.sellerProfile']);
        } else {
            if ($quantity > $product->stock) {
                return response()->json(['error' => "Available stock is only {$product->stock}."], 422);
            }
            $item = $request->user()->cartItems()->create([
                'product_id' => $product->id,
                'variant' => $variant,
                'quantity' => $quantity,
                'selected' => true,
            ])->load(['product.seller.sellerProfile']);
        }

        return response()->json([
            'success' => true,
            'item' => $this->formatItem($item),
            'cartCount' => $request->user()->cartItems()->sum('quantity'),
        ]);
    }

    /**
     * B6: Update quantity and selected state with IDOR protection
     */
    public function update(Request $request, CartItem $cartItem): JsonResponse
    {
        // IDOR protection
        abort_if($cartItem->user_id !== $request->user()->id, 403);

        $validated = $request->validate([
            'quantity' => ['sometimes', 'integer', 'min:1'],
            'selected' => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['quantity'])) {
            $product = $cartItem->product;
            if ($validated['quantity'] > $product->stock) {
                return response()->json(['error' => "Requested quantity exceeds available stock ({$product->stock})."], 422);
            }

            if ($product->max_purchase_limit !== null && $product->max_purchase_limit > 0) {
                if ($validated['quantity'] > $product->max_purchase_limit) {
                    return response()->json(['error' => "Exceeds per-buyer limit of {$product->max_purchase_limit}."], 422);
                }
            }
        }

        $cartItem->update($validated);

        return response()->json([
            'item' => $this->formatItem($cartItem->fresh(['product.seller.sellerProfile'])),
            'cartCount' => $request->user()->cartItems()->sum('quantity'),
        ]);
    }

    /**
     * B6: Destroy cart item with IDOR protection
     */
    public function destroy(Request $request, CartItem $cartItem): JsonResponse
    {
        // IDOR protection
        abort_if($cartItem->user_id !== $request->user()->id, 403);

        $cartItem->delete();

        return response()->json([
            'cartCount' => $request->user()->cartItems()->sum('quantity'),
        ]);
    }

    /**
     * B6: Line-item selection for checkout: Select or deselect all items in cart
     */
    public function selectAll(Request $request): JsonResponse
    {
        $validated = $request->validate(['selected' => ['required', 'boolean']]);

        $request->user()->cartItems()->update(['selected' => $validated['selected']]);

        return response()->json(['ok' => true]);
    }

    /**
     * B6: Store-level select all
     */
    public function selectStore(Request $request, int $sellerId): JsonResponse
    {
        $validated = $request->validate(['selected' => ['required', 'boolean']]);

        $request->user()->cartItems()
            ->whereHas('product', fn ($q) => $q->where('seller_id', $sellerId))
            ->update(['selected' => $validated['selected']]);

        return response()->json(['ok' => true]);
    }

    private function formatItem(CartItem $item): array
    {
        return [
            'key' => (string) $item->id,
            'id' => $item->product_id,
            'name' => $item->product->name,
            'image' => $item->product->gallery[0] ?? 'https://placehold.co/200x200/e5eeff/0058be?text=' . urlencode($item->product->name),
            'price' => $item->unit_price, // Dynamic price reflection
            'regularPrice' => (float) $item->product->price,
            'isFlashSale' => $item->product->isFlashSaleActive(),
            'sellerId' => $item->product->seller_id,
            'seller' => $item->product->seller->sellerProfile->business_name ?? $item->product->seller->full_name,
            'variant' => $item->variant,
            'qty' => $item->quantity,
            'selected' => $item->selected,
            'weight' => (float) ($item->product->weight ?? 1.0),
        ];
    }
}
