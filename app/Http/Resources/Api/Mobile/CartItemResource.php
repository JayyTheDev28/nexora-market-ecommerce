<?php

namespace App\Http\Resources\Api\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $product = $this->product;
        $seller = $product?->seller;
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

        $rawImg = $product ? $product->getVariantImageUrl($this->variant) : null;
        $imageUrl = $product ? $toAbsoluteUrl($rawImg, $product->name) : null;

        $availableStock = (int) ($product?->stock ?? 0);
        if ($product) {
            if ($availableStock === 0 && ! empty($product->variants_data) && is_array($product->variants_data)) {
                $availableStock = (int) array_sum(array_map(fn ($v) => (int) ($v['stock'] ?? 0), $product->variants_data));
            }
            $variation = is_array($this->variant) ? ($this->variant['variation'] ?? $this->variant['name'] ?? null) : null;
            if ($variation && ! empty($product->variants_data) && is_array($product->variants_data)) {
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
                        break;
                    }
                }
            }
        }

        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'name' => $product?->name ?? 'Unavailable Product',
            'image' => $imageUrl,
            'price' => (float) $this->unit_price,
            'regular_price' => (float) ($product?->price ?? 0),
            'quantity' => (int) $this->quantity,
            'selected' => (bool) $this->selected,
            'variant' => $this->variant,
            'weight' => (float) ($product?->weight ?? 1.0),
            'stock' => $availableStock,
            'is_vacation_mode' => (bool) ($seller?->sellerProfile?->is_vacation_mode ?? false),
            'seller_id' => $product?->seller_id,
            'seller_name' => $sellerName,
        ];
    }
}

