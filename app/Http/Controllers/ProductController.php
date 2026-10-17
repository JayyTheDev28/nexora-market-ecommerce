<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StoreFollow;
use App\Models\Wishlist;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * B4: Product Detail Page
     * - Variant selection (up to 2 variation types, e.g., Color/Size) driving real-time price, stock, image
     * - Out-of-stock disabling
     * - Per-buyer purchase limits
     * - Flash sale price/countdown integration
     * - Wishlist & store follow integrations
     */
    public function show(Request $request, int $id)
    {
        $product = Product::with([
            'category',
            'seller.sellerProfile',
            'reviews.buyer',
        ])->findOrFail($id);

        $user = $request->user();

        // Calculate buyer's purchase limit & remaining allowance
        $buyerRemainingLimit = null;
        if ($product->max_purchase_limit !== null && $product->max_purchase_limit > 0) {
            $userTotalPurchased = 0;
            $currentCartQty = 0;

            if ($user) {
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
            }

            $buyerRemainingLimit = max(0, $product->max_purchase_limit - ($userTotalPurchased + $currentCartQty));
        }

        // Flash sale calculation
        $isFlashSale = $product->isFlashSaleActive();
        $flashSaleRemainingSeconds = $isFlashSale && $product->flash_sale_ends_at
            ? max(0, now()->diffInSeconds($product->flash_sale_ends_at, false))
            : 0;

        // Wishlist & Store Follow status
        $inWishlist = false;
        $isFollowingStore = false;
        if ($user) {
            $inWishlist = Wishlist::where('user_id', $user->id)->where('product_id', $product->id)->exists();
            $isFollowingStore = StoreFollow::where('user_id', $user->id)->where('seller_id', $product->seller_id)->exists();
        }

        // Store details
        $sellerFollowersCount = StoreFollow::where('seller_id', $product->seller_id)->count();
        $sellerProductsCount = Product::where('seller_id', $product->seller_id)->where('status', 'active')->count();

        // Related Products
        $related = Product::where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where('status', 'active')
            ->with('seller.sellerProfile')
            ->limit(4)
            ->get()
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'seller' => $p->seller->sellerProfile->business_name ?? $p->seller->full_name,
                'price' => (float) $p->effective_price,
                'comparePrice' => $p->compare_price ? (float) $p->compare_price : null,
                'image' => $p->gallery[0] ?? 'https://placehold.co/600x600/e5eeff/0058be?text=' . urlencode($p->name),
                'url' => url("/products/{$p->id}"),
            ])
            ->values()
            ->all();

        // Reviews list
        $reviews = $product->reviews()->with('buyer')->latest()->take(10)->get();

        $productArray = [
            'id' => $product->id,
            'name' => $product->name,
            'seller' => $product->seller->sellerProfile->business_name ?? $product->seller->full_name,
            'sellerId' => $product->seller_id,
            'price' => (float) $product->price,
            'effectivePrice' => (float) $product->effective_price,
            'comparePrice' => $product->compare_price ? (float) $product->compare_price : null,
            'isFlashSale' => $isFlashSale,
            'flashSalePrice' => $product->flash_sale_price ? (float) $product->flash_sale_price : null,
            'flashSaleEndsAt' => $product->flash_sale_ends_at?->toIso8601String(),
            'flashSaleSeconds' => $flashSaleRemainingSeconds,
            'rating' => $product->averageRating(),
            'reviewCount' => $product->reviews()->count(),
            'stock' => (int) $product->stock,
            'weight' => (float) ($product->weight ?? 1.0),
            'maxPurchaseLimit' => $product->max_purchase_limit,
            'buyerRemainingLimit' => $buyerRemainingLimit,
            'image' => $product->gallery[0] ?? 'https://placehold.co/600x600/e5eeff/0058be?text=' . urlencode($product->name),
            'gallery' => $product->gallery ?: [$product->gallery[0] ?? 'https://placehold.co/600x600/e5eeff/0058be?text=' . urlencode($product->name)],
            'colors' => $product->colors ?? [],
            'sizes' => $product->sizes ?? [],
            'variantsData' => $product->variants_data ?? [],
            'description' => $product->description,
            'inWishlist' => $inWishlist,
            'isFollowingStore' => $isFollowingStore,
            'sellerFollowersCount' => $sellerFollowersCount,
            'sellerProductsCount' => $sellerProductsCount,
        ];

        return view('buyer.products.show', [
            'product' => $productArray,
            'related' => $related,
            'reviews' => $reviews,
        ]);
    }
}
