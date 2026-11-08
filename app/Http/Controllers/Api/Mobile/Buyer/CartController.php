<?php

namespace App\Http\Controllers\Api\Mobile\Buyer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Mobile\CartItemRequest;
use App\Http\Resources\Api\Mobile\CartItemResource;
use App\Http\Resources\Api\Mobile\VoucherResource;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    private function normalizeVariantPayload(mixed $v): ?array
    {
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
    }

    /**
     * Resolve variation stock, server-verified price, and variation image from product.variants_data.
     *
     * @return array{stock: int, price: ?float, image: ?string}
     */
    private function resolveVariantDetails(Product $product, ?array $variant): array
    {
        $availableStock = (int) $product->stock;
        $variantPrice = null;
        $variantImage = null;

        if ($availableStock === 0 && ! empty($product->variants_data) && is_array($product->variants_data)) {
            $availableStock = (int) array_sum(array_map(fn ($v) => (int) ($v['stock'] ?? 0), $product->variants_data));
        }

        $variation = is_array($variant) ? ($variant['variation'] ?? $variant['name'] ?? null) : null;
        if (! empty($variation) && is_array($product->variants_data)) {
            foreach ($product->variants_data as $key => $v) {
                if (! is_array($v)) {
                    continue;
                }
                $vName = $v['name'] ?? (is_string($key) ? str_replace('_', ' / ', $key) : null);
                $vSku = $v['sku'] ?? null;
                if ($vName === $variation || $vSku === $variation || (string) $key === $variation) {
                    if (isset($v['stock'])) {
                        $availableStock = (int) $v['stock'];
                    }
                    if (! $product->isFlashSaleActive() && isset($v['price']) && is_numeric($v['price']) && (float) $v['price'] > 0) {
                        $variantPrice = (float) $v['price'];
                    }
                    if (! empty($v['image'])) {
                        $variantImage = (string) $v['image'];
                    }
                    break;
                }
            }
        }

        return [
            'stock' => $availableStock,
            'price' => $variantPrice,
            'image' => $variantImage,
        ];
    }

    private function buildCartPayload(\App\Models\User $user): array
    {
        $items = $user->cartItems()
            ->with(['product' => fn ($q) => $q->withTrashed()->with('seller.sellerProfile')])
            ->latest()
            ->get();

        $grouped = $items->groupBy(fn (CartItem $i) => $i->product->seller_id ?? 0);

        $claimedVouchers = $user->vouchers()
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->wherePivotNull('used_at')
            ->get();

        $formattedStores = [];
        foreach ($grouped as $sellerId => $storeItems) {
            $seller = $storeItems->first()?->product?->seller;
            $storeName = $seller?->sellerProfile?->business_name ?? $seller?->full_name ?? 'Seller';

            $formattedStores[] = [
                'seller_id' => $sellerId,
                'store_name' => $storeName,
                'is_vacation_mode' => (bool) ($seller?->sellerProfile?->is_vacation_mode ?? false),
                'items' => CartItemResource::collection($storeItems),
            ];
        }

        return [
            'items' => CartItemResource::collection($items),
            'stores' => $formattedStores,
            'claimed_vouchers' => VoucherResource::collection($claimedVouchers),
            'total_items' => $items->count(),
            'cart_count' => $items->count(),
        ];
    }

    /**
     * B6: List cart items grouped by store
     * GET /api/mobile/v1/buyer/cart
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->buildCartPayload($request->user()),
            'message' => 'Cart retrieved successfully.',
        ]);
    }

    /**
     * B6: Add item to cart with line merging, vacation mode, and purchase limit checks
     * POST /api/mobile/v1/buyer/cart/items
     */
    public function store(CartItemRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $product = Product::with('seller.sellerProfile')->findOrFail($validated['product_id']);

        if ($product->status !== 'active') {
            return response()->json([
                'message' => 'This product is no longer available.',
                'errors' => ['product' => ['Product is not active.']],
            ], 422);
        }

        if ($product->seller?->sellerProfile?->is_vacation_mode) {
            $storeName = $product->seller->sellerProfile->business_name ?? 'The store';
            return response()->json([
                'message' => "{$storeName} is currently on vacation mode and not accepting new orders.",
                'errors' => ['vacation' => ["Store is on vacation mode."]],
            ], 422);
        }

        $variant = $validated['variant'] ?? null;
        if (is_array($variant)) {
            // Never trust client-supplied prices; always resolve from server product/variants_data
            unset($variant['price']);
            if (empty($variant)) {
                $variant = null;
            }
        }
        $quantity = $validated['quantity'] ?? 1;

        $variantDetails = $this->resolveVariantDetails($product, $variant);
        $availableStock = $variantDetails['stock'];

        if (is_array($variant)) {
            if ($variantDetails['price'] !== null) {
                $variant['price'] = $variantDetails['price'];
            }
            if (empty($variant['image']) && $variantDetails['image'] !== null) {
                $variant['image'] = $variantDetails['image'];
            }
        }

        if ($availableStock <= 0) {
            return response()->json([
                'message' => 'This product or variation is currently out of stock.',
                'errors' => ['stock' => ['Out of stock.']],
            ], 422);
        }

        // B4: Check per-buyer purchase limits
        if ($product->max_purchase_limit !== null && $product->max_purchase_limit > 0) {
            $userTotalPurchased = $user->orders()
                ->where('status', '!=', 'cancelled')
                ->whereHas('items', fn ($q) => $q->where('product_id', $product->id))
                ->with('items')
                ->get()
                ->flatMap->items
                ->where('product_id', $product->id)
                ->sum('quantity');

            $currentCartQty = $user->cartItems()
                ->where('product_id', $product->id)
                ->sum('quantity');

            if (($userTotalPurchased + $currentCartQty + $quantity) > $product->max_purchase_limit) {
                $remaining = max(0, $product->max_purchase_limit - ($userTotalPurchased + $currentCartQty));
                return response()->json([
                    'message' => "Purchase limit reached! You can only purchase a maximum of {$product->max_purchase_limit} units of this item. You can add at most {$remaining} more.",
                    'errors' => ['purchase_limit' => ["Purchase limit exceeded."]],
                ], 422);
            }
        }

        // B6: Variant line merging
        $targetVariant = $this->normalizeVariantPayload($variant);

        $existing = $user->cartItems()
            ->where('product_id', $product->id)
            ->get()
            ->first(function (CartItem $item) use ($targetVariant) {
                $itemV = $this->normalizeVariantPayload($item->variant);
                if ($itemV === null && $targetVariant === null) return true;
                if ($itemV === null || $targetVariant === null) return false;
                return $itemV == $targetVariant;
            });

        if ($existing) {
            $newQty = $existing->quantity + $quantity;
            if ($newQty > $availableStock) {
                return response()->json([
                    'message' => "Cannot add more. Available stock: {$availableStock}.",
                    'errors' => ['stock' => ["Available stock: {$availableStock}."]],
                ], 422);
            }
            $existing->update(['quantity' => $newQty]);
            $item = $existing->fresh(['product.seller.sellerProfile']);
        } else {
            if ($quantity > $availableStock) {
                return response()->json([
                    'message' => "Available stock is only {$availableStock}.",
                    'errors' => ['stock' => ["Available stock: {$availableStock}."]],
                ], 422);
            }
            $item = $user->cartItems()->create([
                'product_id' => $product->id,
                'variant' => $targetVariant,
                'quantity' => $quantity,
                'selected' => true,
            ])->load(['product.seller.sellerProfile']);
        }

        $payload = $this->buildCartPayload($user);
        $payload['id'] = $item->id;
        $payload['cart_item_id'] = $item->id;
        $payload['item'] = new CartItemResource($item);

        return response()->json([
            'data' => $payload,
            'message' => 'Item added to cart.',
        ], 201);
    }

    /**
     * B6: Update quantity or selected state with IDOR protection
     * PATCH /api/mobile/v1/buyer/cart/items/{cartItem}
     */
    public function update(Request $request, CartItem $cartItem): JsonResponse
    {
        if ($cartItem->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'quantity' => ['sometimes', 'integer', 'min:1'],
            'selected' => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['quantity'])) {
            $product = $cartItem->product;
            $variantDetails = $this->resolveVariantDetails($product, is_array($cartItem->variant) ? $cartItem->variant : null);
            $availableStock = $variantDetails['stock'];

            if ($validated['quantity'] > $availableStock) {
                return response()->json([
                    'message' => "Requested quantity exceeds available stock ({$availableStock}).",
                    'errors' => ['stock' => ["Stock: {$availableStock}"]],
                ], 422);
            }

            if ($product->max_purchase_limit !== null && $product->max_purchase_limit > 0 && $validated['quantity'] > $cartItem->quantity) {
                $userTotalPurchased = $request->user()->orders()
                    ->where('status', '!=', 'cancelled')
                    ->whereHas('items', fn ($q) => $q->where('product_id', $product->id))
                    ->with('items')
                    ->get()
                    ->flatMap->items
                    ->where('product_id', $product->id)
                    ->sum('quantity');

                $otherCartQty = $request->user()->cartItems()
                    ->where('product_id', $product->id)
                    ->where('id', '!=', $cartItem->id)
                    ->sum('quantity');

                if (($userTotalPurchased + $otherCartQty + $validated['quantity']) > $product->max_purchase_limit) {
                    return response()->json([
                        'message' => "Exceeds per-buyer limit of {$product->max_purchase_limit}.",
                        'errors' => ['purchase_limit' => ["Limit: {$product->max_purchase_limit}"]],
                    ], 422);
                }
            }
        }

        $cartItem->update($validated);

        $payload = $this->buildCartPayload($request->user());
        $payload['item'] = new CartItemResource($cartItem->fresh(['product.seller.sellerProfile']));

        return response()->json([
            'data' => $payload,
            'message' => 'Cart item updated.',
        ]);
    }

    /**
     * B6: Remove item from cart
     * DELETE /api/mobile/v1/buyer/cart/items/{cartItem}
     */
    public function destroy(Request $request, CartItem $cartItem): JsonResponse
    {
        if ($cartItem->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $cartItem->delete();

        return response()->json([
            'data' => $this->buildCartPayload($request->user()),
            'message' => 'Item removed from cart.',
        ]);
    }

    /**
     * B6: Select/deselect all
     * PATCH /api/mobile/v1/buyer/cart/select-all
     */
    public function selectAll(Request $request): JsonResponse
    {
        $validated = $request->validate(['selected' => ['required', 'boolean']]);
        $request->user()->cartItems()->update(['selected' => $validated['selected']]);

        return response()->json([
            'data' => $this->buildCartPayload($request->user()),
            'message' => 'Selection updated.',
        ]);
    }

    /**
     * B6: Store-level select all
     * PATCH /api/mobile/v1/buyer/cart/select-store/{sellerId}
     */
    public function selectStore(Request $request, int $sellerId): JsonResponse
    {
        $validated = $request->validate(['selected' => ['required', 'boolean']]);

        $request->user()->cartItems()
            ->whereHas('product', fn ($q) => $q->where('seller_id', $sellerId))
            ->update(['selected' => $validated['selected']]);

        return response()->json([
            'data' => $this->buildCartPayload($request->user()),
            'message' => 'Store selection updated.',
        ]);
    }
}

