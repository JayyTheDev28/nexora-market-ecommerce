<?php

namespace App\Http\Resources\Api\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $seller = $this->seller;
        $sellerName = $seller?->sellerProfile?->business_name ?? $seller?->full_name ?? 'Seller';

        $toAbsoluteUrl = function (?string $path): ?string {
            if (empty($path)) return null;
            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) return $path;
            if (str_starts_with($path, '/storage/') || str_starts_with($path, 'storage/')) {
                return url('/' . ltrim($path, '/'));
            }
            return url(\Illuminate\Support\Facades\Storage::url($path));
        };

        $firstItem = $this->items->first();
        $firstItemRawImg = $firstItem?->product?->gallery[0] ?? null;

        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'checkout_session_id' => $this->checkout_session_id,
            'status' => $this->status,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'total' => (float) $this->total,
            'subtotal' => (float) $this->subtotal,
            'shipping_fee' => (float) $this->shipping_fee,
            'discount' => (float) $this->discount,
            'placed_at' => $this->placed_at?->format('Y-m-d H:i:s'),
            'delivered_at' => $this->delivered_at?->format('Y-m-d H:i:s'),
            'completed_at' => $this->completed_at?->format('Y-m-d H:i:s'),
            'seller_id' => $this->seller_id,
            'seller_name' => $sellerName,
            'items_count' => $this->items()->count(),
            'items' => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'name' => $item->product_name,
                'title' => $item->product_name,
                'price' => (float) $item->price,
                'unit_price' => (float) $item->price,
                'quantity' => (int) $item->quantity,
                'variant' => $item->variant,
                'image' => $toAbsoluteUrl($item->product?->gallery[0] ?? null),
                'image_url' => $toAbsoluteUrl($item->product?->gallery[0] ?? null),
            ]),
            'first_item' => $firstItem ? [
                'name' => $firstItem->product_name,
                'price' => (float) $firstItem->price,
                'quantity' => $firstItem->quantity,
                'variant' => $firstItem->variant,
                'image' => $toAbsoluteUrl($firstItemRawImg),
            ] : null,
            'has_dispute' => $this->dispute !== null,
            'has_review' => $this->review !== null,
        ];
    }
}

