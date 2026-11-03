<?php

namespace App\Http\Resources\Api\Mobile;

use App\Models\StoreFollow;
use App\Models\Wishlist;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $seller = $this->seller;
        $profile = $seller?->sellerProfile;
        $sellerName = $profile?->business_name ?? $seller?->full_name ?? 'Seller';

        // Buyer purchase limit calculation
        $buyerRemainingLimit = null;
        if ($this->max_purchase_limit !== null && $this->max_purchase_limit > 0) {
            $userTotalPurchased = 0;
            $currentCartQty = 0;

            if ($user) {
                $userTotalPurchased = $user->orders()
                    ->where('status', '!=', 'cancelled')
                    ->whereHas('items', fn ($q) => $q->where('product_id', $this->id))
                    ->with('items')
                    ->get()
                    ->flatMap->items
                    ->where('product_id', $this->id)
                    ->sum('quantity');

                $currentCartQty = $user->cartItems()
                    ->where('product_id', $this->id)
                    ->sum('quantity');
            }

            $buyerRemainingLimit = max(0, $this->max_purchase_limit - ($userTotalPurchased + $currentCartQty));
        }

        // Flash sale calculation
        $isFlashSale = $this->isFlashSaleActive();
        $flashSaleRemainingSeconds = $isFlashSale && $this->flash_sale_ends_at
            ? max(0, now()->diffInSeconds($this->flash_sale_ends_at, false))
            : 0;

        $inWishlist = false;
        $isFollowingStore = false;
        if ($user) {
            $inWishlist = Wishlist::where('user_id', $user->id)->where('product_id', $this->id)->exists();
            $isFollowingStore = StoreFollow::where('user_id', $user->id)->where('seller_id', $this->seller_id)->exists();
        }

        $toAbsoluteUrl = function (?string $path, string $fallbackName): string {
            if (empty($path)) {
                return 'https://placehold.co/600x600/e5eeff/0058be?text=' . urlencode($fallbackName);
            }
            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                return $path;
            }
            if (str_starts_with($path, '/storage/') || str_starts_with($path, 'storage/')) {
                return url('/' . ltrim($path, '/'));
            }
            return url(\Illuminate\Support\Facades\Storage::url($path));
        };

        $rawGallery = $this->gallery ?: [];
        $normalizedVariants = [];
        if (! empty($this->variants_data) && is_array($this->variants_data)) {
            foreach ($this->variants_data as $key => $v) {
                if (! is_array($v)) continue;
                if (! empty($v['image']) && ! in_array($v['image'], $rawGallery, true)) {
                    $rawGallery[] = $v['image'];
                }
                $vName = $v['name'] ?? (is_string($key) ? str_replace('_', ' / ', $key) : ($v['sku'] ?? 'Option'));
                $vColor = $v['color'] ?? null;
                $vSize = $v['size'] ?? null;
                if (! $vColor && ! $vSize && str_contains($vName, ' / ')) {
                    $parts = explode(' / ', $vName, 2);
                    $vColor = trim($parts[0]);
                    $vSize = trim($parts[1]);
                }
                $vImage = ! empty($v['image']) ? $toAbsoluteUrl($v['image'], $vName) : null;
                $normalizedVariants[] = [
                    'name' => $vName,
                    'sku' => $v['sku'] ?? '',
                    'color' => $vColor,
                    'size' => $vSize,
                    'price' => isset($v['price']) ? (float) $v['price'] : (float) $this->effective_price,
                    'stock' => isset($v['stock']) ? (int) $v['stock'] : (int) $this->stock,
                    'image' => $vImage,
                ];
            }
        }
        $gallery = array_map(fn ($img) => $toAbsoluteUrl($img, $this->name), $rawGallery);
        $mainImage = !empty($gallery[0]) ? $gallery[0] : $toAbsoluteUrl(null, $this->name);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'title' => $this->name,
            'description' => $this->description,
            'category_id' => $this->category_id,
            'category_name' => $this->category?->name ?? '',
            'category' => $this->category?->name ?? '',
            'image' => $mainImage,
            'primary_image' => $mainImage,
            'images' => $gallery,
            'seller' => [
                'id' => $this->seller_id,
                'name' => $sellerName,
                'store_name' => $sellerName,
                'business_name' => $profile?->business_name ?? $sellerName,
                'city' => 'Metro Manila',
                'avatar_url' => $seller?->avatar_url,
                'is_vacation_mode' => (bool) ($profile?->is_vacation_mode ?? false),
                'followers_count' => StoreFollow::where('seller_id', $this->seller_id)->count(),
                'products_count' => $seller?->products()->where('status', 'active')->count() ?? 0,
                'is_following' => $isFollowingStore,
            ],
            'price' => (float) $this->effective_price,
            'formatted_price' => '₱' . number_format($this->effective_price, 2),
            'regular_price' => (float) $this->price,
            'compare_price' => $this->compare_price ? (float) $this->compare_price : null,
            'stock' => (int) $this->stock,
            'is_active' => $this->status === 'active',
            'weight' => (float) ($this->weight ?? 1.0),
            'max_purchase_limit' => $this->max_purchase_limit,
            'buyer_remaining_limit' => $buyerRemainingLimit,
            'is_flash_sale' => $isFlashSale,
            'flash_sale_remaining_seconds' => $flashSaleRemainingSeconds,
            'in_wishlist' => $inWishlist,
            'rating' => $this->averageRating(),
            'reviews_count' => $this->reviews()->count(),
            'gallery' => $gallery,
            'colors' => is_array($this->colors) ? array_values($this->colors) : [],
            'sizes' => is_array($this->sizes) ? array_values($this->sizes) : [],
            'variants_data' => $normalizedVariants,
            'reviews' => $this->reviews()->with('buyer')->latest()->take(10)->get()->map(fn ($r) => [
                'id' => $r->id,
                'buyer_name' => $r->buyer?->full_name ?? 'Shopper',
                'rating' => $r->rating,
                'comment' => $r->comment,
                'photos' => $r->photos ?? [],
                'created_at' => $r->created_at?->diffForHumans(),
            ]),
        ];
    }
}

