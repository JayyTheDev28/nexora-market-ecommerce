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

        return [
            'id' => $this->id,
            'name' => $this->name,
            'category_id' => $this->category_id,
            'category_slug' => $this->category?->slug ?? '',
            'category_name' => $this->category?->name ?? '',
            'seller_id' => $this->seller_id,
            'seller_name' => $sellerName,
            'price' => (float) $this->effective_price,
            'regular_price' => (float) $this->price,
            'compare_price' => $this->compare_price ? (float) $this->compare_price : null,
            'is_flash_sale' => $this->isFlashSaleActive(),
            'stock' => (int) $this->stock,
            'weight' => (float) ($this->weight ?? 1.0),
            'rating' => $this->averageRating(),
            'reviews_count' => $this->reviews()->count(),
            'image' => $this->gallery[0] ?? 'https://placehold.co/600x600/e5eeff/0058be?text=' . urlencode($this->name),
        ];
    }
}

