<?php

namespace App\Http\Resources\Api\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $seller = $this->seller;
        $sellerName = $seller?->sellerProfile?->business_name ?? $seller?->full_name ?? 'Seller';

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

        $rawGallery = !empty($this->gallery) && is_array($this->gallery) ? $this->gallery : [];
        $gallery = array_map(fn ($img) => $toAbsoluteUrl($img, $this->name), $rawGallery);
        $mainImage = !empty($gallery[0]) ? $gallery[0] : $toAbsoluteUrl(null, $this->name);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'title' => $this->name,
            'category_id' => $this->category_id,
            'category_slug' => $this->category?->slug ?? '',
            'category_name' => $this->category?->name ?? '',
            'category' => $this->category?->name ?? '',
            'seller_id' => $this->seller_id,
            'seller_name' => $sellerName,
            'seller' => [
                'id' => $this->seller_id,
                'name' => $sellerName,
                'store_name' => $sellerName,
                'business_name' => $sellerName,
                'city' => 'Metro Manila',
            ],
            'price' => (float) $this->effective_price,
            'formatted_price' => '₱' . number_format($this->effective_price, 2),
            'regular_price' => (float) $this->price,
            'compare_price' => $this->compare_price ? (float) $this->compare_price : null,
            'is_flash_sale' => $this->isFlashSaleActive(),
            'stock' => (int) $this->stock,
            'weight' => (float) ($this->weight ?? 1.0),
            'rating' => $this->averageRating(),
            'reviews_count' => $this->reviews()->count(),
            'sold_count' => (int) ($this->orderItems()->sum('quantity') ?? 0),
            'image' => $mainImage,
            'primary_image' => $mainImage,
            'images' => $this->gallery ?? [$mainImage],
            'status' => $this->status,
            'is_active' => $this->status === 'active',
        ];
    }
}

