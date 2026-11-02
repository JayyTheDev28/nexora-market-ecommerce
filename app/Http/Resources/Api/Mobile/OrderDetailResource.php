<?php

namespace App\Http\Resources\Api\Mobile;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderDetailResource extends JsonResource
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
            'subtotal' => (float) $this->subtotal,
            'shipping_fee' => (float) $this->shipping_fee,
            'discount' => (float) $this->discount,
            'total' => (float) $this->total,
            'shipping_weight' => (float) ($this->shipping_weight ?? 1.0),
            'tracking_number' => $this->tracking_number,
            'delivery_otp' => $this->delivery_otp,
            'placed_at' => $this->placed_at?->format('Y-m-d H:i:s'),
            'delivered_at' => $this->delivered_at?->format('Y-m-d H:i:s'),
            'completed_at' => $this->completed_at?->format('Y-m-d H:i:s'),
            'cancelled_at' => $this->cancelled_at?->format('Y-m-d H:i:s'),
            'cancellation_reason' => $this->cancellation_reason,
            'can_be_cancelled' => $this->canBeCancelledByBuyer(),
            'can_be_returned' => $this->canBeReturned(),
            'can_be_reviewed' => $this->canBeReviewed(),
            'address_snapshot' => $this->address_snapshot,
            'seller' => [
                'id' => $this->seller_id,
                'name' => $sellerName,
                'avatar_url' => $seller?->avatar_url,
            ],
            'courier' => $this->deliveryCourier ? [
                'name' => $this->deliveryCourier->full_name,
                'contact_number' => $this->deliveryCourier->contact_number,
                'vehicle_type' => $this->deliveryCourier->courierProfile?->vehicle_type,
                'plate_number' => $this->deliveryCourier->courierProfile?->plate_number,
            ] : null,
            'items' => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'price' => (float) $item->price,
                'quantity' => (int) $item->quantity,
                'variant' => $item->variant,
                'image' => $item->product?->gallery[0] ?? null,
            ]),
            'status_history' => $this->statusHistory->map(fn ($h) => [
                'id' => $h->id,
                'status' => $h->status,
                'note' => $h->note,
                'changed_at' => $h->changed_at?->format('Y-m-d H:i:s'),
            ]),
            'dispute' => $this->dispute ? [
                'id' => $this->dispute->id,
                'reason' => $this->dispute->reason,
                'details' => $this->dispute->details,
                'status' => $this->dispute->status,
                'media_paths' => $this->dispute->media_paths ?? [],
                'created_at' => $this->dispute->created_at?->format('Y-m-d H:i:s'),
            ] : null,
            'review' => $this->review ? [
                'id' => $this->review->id,
                'rating' => $this->review->rating,
                'comment' => $this->review->comment,
                'photos' => $this->review->photos ?? [],
                'edited_count' => $this->review->edited_count,
                'can_be_edited' => $this->review->canBeEdited(),
                'created_at' => $this->review->created_at?->format('Y-m-d H:i:s'),
            ] : null,
        ];
    }
}

