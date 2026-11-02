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
            'first_item' => $this->items->first() ? [
                'name' => $this->items->first()->product_name,
                'price' => (float) $this->items->first()->price,
                'quantity' => $this->items->first()->quantity,
                'variant' => $this->items->first()->variant,
                'image' => $this->items->first()->product?->gallery[0] ?? null,
            ] : null,
            'has_dispute' => $this->dispute !== null,
            'has_review' => $this->review !== null,
        ];
    }
}

