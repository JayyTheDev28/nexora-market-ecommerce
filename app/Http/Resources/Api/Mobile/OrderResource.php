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
        $firstItemRawImg = $firstItem?->product?->getVariantImageUrl(is_array($firstItem->variant) ? $firstItem->variant : null);

        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'checkout_session_id' => $this->checkout_session_id,
            'tracking_number' => $this->tracking_number,
            'delivery_otp' => $this->status === 'out_for_delivery' ? $this->delivery_otp : null,
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
            'cancelled_at' => $this->cancelled_at?->format('Y-m-d H:i:s'),
            'cancellation_reason' => $this->cancellation_reason,
            'can_be_cancelled' => $this->canBeCancelledByBuyer(),
            'can_be_returned' => $this->canBeReturned(),
            'can_be_reviewed' => $this->canBeReviewed(),
            'address_snapshot' => $this->address_snapshot,
            'current_hub_id' => $this->current_hub_id,
            'current_hub_name' => $this->currentHub?->name,
            'origin_hub_name' => $this->originHub?->name,
            'destination_hub_name' => $this->destinationHub?->name,
            'seller_id' => $this->seller_id,
            'seller_name' => $sellerName,
            'courier' => $this->deliveryCourier ? [
                'name' => $this->deliveryCourier->full_name,
                'contact_number' => $this->deliveryCourier->contact_number,
                'vehicle_type' => $this->deliveryCourier->courierProfile?->vehicle_type,
                'plate_number' => $this->deliveryCourier->courierProfile?->plate_number,
            ] : null,
            'items_count' => $this->items->count(),
            'items' => $this->items->map(function ($item) use ($toAbsoluteUrl) {
                $rawImg = $item->product?->getVariantImageUrl(is_array($item->variant) ? $item->variant : null);
                $absImg = $toAbsoluteUrl($rawImg);
                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name,
                    'name' => $item->product_name,
                    'title' => $item->product_name,
                    'price' => (float) $item->price,
                    'unit_price' => (float) $item->price,
                    'quantity' => (int) $item->quantity,
                    'variant' => $item->variant,
                    'image' => $absImg,
                    'image_url' => $absImg,
                ];
            }),
            'first_item' => $firstItem ? [
                'product_id' => $firstItem->product_id,
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

