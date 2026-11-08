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

        $toAbsoluteUrl = function (?string $path): ?string {
            if (empty($path)) return null;
            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) return $path;
            if (str_starts_with($path, '/storage/') || str_starts_with($path, 'storage/')) {
                return url('/' . ltrim($path, '/'));
            }
            return url(\Illuminate\Support\Facades\Storage::url($path));
        };

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
            'current_hub_id' => $this->current_hub_id,
            'current_hub_name' => $this->currentHub?->name,
            'origin_hub_name' => $this->originHub?->name,
            'destination_hub_name' => $this->destinationHub?->name,
            'current_hub' => $this->currentHub ? [
                'id' => $this->currentHub->id,
                'name' => $this->currentHub->name,
                'city_name' => $this->currentHub->city_name,
                'province_name' => $this->currentHub->province_name,
            ] : null,
            'origin_hub' => $this->originHub ? [
                'id' => $this->originHub->id,
                'name' => $this->originHub->name,
                'city_name' => $this->originHub->city_name,
                'province_name' => $this->originHub->province_name,
            ] : null,
            'destination_hub' => $this->destinationHub ? [
                'id' => $this->destinationHub->id,
                'name' => $this->destinationHub->name,
                'city_name' => $this->destinationHub->city_name,
                'province_name' => $this->destinationHub->province_name,
            ] : null,
            'seller_id' => $this->seller_id,
            'seller_name' => $sellerName,
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
                'image' => $toAbsoluteUrl($item->product?->getVariantImageUrl(is_array($item->variant) ? $item->variant : null)),
            ]),
            'status_history' => $this->statusHistory->map(fn ($h) => [
                'id' => $h->id,
                'status' => $h->status,
                'note' => $h->note,
                'changed_at' => $h->changed_at?->format('Y-m-d H:i:s'),
            ]),
            'has_dispute' => $this->dispute !== null,
            'has_review' => $this->review !== null,
            'dispute' => $this->dispute ? [
                'id' => $this->dispute->id,
                'reason' => $this->dispute->reason,
                'details' => $this->dispute->details,
                'status' => $this->dispute->status,
                'media_paths' => array_values(array_filter(array_map($toAbsoluteUrl, $this->dispute->media_paths ?? []))),
                'created_at' => $this->dispute->created_at?->format('Y-m-d H:i:s'),
            ] : null,
            'review' => $this->review ? [
                'id' => $this->review->id,
                'rating' => $this->review->rating,
                'comment' => $this->review->comment,
                'photos' => array_values(array_filter(array_map($toAbsoluteUrl, $this->review->photos ?? []))),
                'edited_count' => $this->review->edited_count,
                'can_be_edited' => $this->review->canBeEdited(),
                'created_at' => $this->review->created_at?->format('Y-m-d H:i:s'),
            ] : null,
        ];
    }
}

