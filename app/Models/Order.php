<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'order_number',
        'checkout_session_id',
        'tracking_number',
        'buyer_id',
        'seller_id',
        'address_id',
        'address_snapshot',
        'status',
        'payment_method',
        'payment_status',
        'payment_expires_at',
        'subtotal',
        'discount',
        'shipping_fee',
        'shipping_weight',
        'shipping_voucher_id',
        'store_voucher_id',
        'platform_voucher_id',
        'total',
        'pickup_courier_id',
        'delivery_courier_id',
        'sorting_center_id',
        'delivery_area_id',
        'placed_at',
        'delivered_at',
        'completed_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'address_snapshot' => 'array',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'shipping_weight' => 'decimal:2',
            'total' => 'decimal:2',
            'placed_at' => 'datetime',
            'payment_expires_at' => 'datetime',
            'delivered_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function address()
    {
        return $this->belongsTo(Address::class);
    }

    public function shippingVoucher()
    {
        return $this->belongsTo(Voucher::class, 'shipping_voucher_id');
    }

    public function storeVoucher()
    {
        return $this->belongsTo(Voucher::class, 'store_voucher_id');
    }

    public function platformVoucher()
    {
        return $this->belongsTo(Voucher::class, 'platform_voucher_id');
    }

    public function pickupCourier()
    {
        return $this->belongsTo(User::class, 'pickup_courier_id');
    }

    public function deliveryCourier()
    {
        return $this->belongsTo(User::class, 'delivery_courier_id');
    }

    public function deliveryArea()
    {
        return $this->belongsTo(DeliveryArea::class);
    }

    public function deliveryAttempts()
    {
        return $this->hasMany(DeliveryAttempt::class);
    }

    public function commissions()
    {
        return $this->hasMany(Commission::class);
    }

    public function sortingCenter()
    {
        return $this->belongsTo(User::class, 'sorting_center_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistory()
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('changed_at');
    }

    public function dispute()
    {
        return $this->hasOne(Dispute::class);
    }

    public function review()
    {
        return $this->hasOne(Review::class);
    }

    public function getPrimarySellerAttribute(): ?User
    {
        if ($this->seller) {
            return $this->seller;
        }

        return $this->items->first()?->product?->seller;
    }

    public function getSellerAddressAttribute(): ?Address
    {
        return $this->primary_seller?->address;
    }

    /**
     * Pre-shipping cancellation guard:
     * Allowed only when status is 'placed' or 'confirmed' before logistics preparation.
     */
    public function canBeCancelledByBuyer(): bool
    {
        return in_array($this->status, ['placed', 'confirmed'], true);
    }

    /**
     * 7-day return request window check.
     */
    public function canBeReturned(): bool
    {
        if ($this->status !== 'delivered' && $this->status !== 'completed') {
            return false;
        }

        if ($this->dispute && in_array($this->dispute->status, ['open', 'under_review', 'resolved'], true)) {
            return false;
        }

        $deliveredTime = $this->delivered_at ?: $this->updated_at;
        return $deliveredTime && $deliveredTime->greaterThanOrEqualTo(now()->subDays(7));
    }

    /**
     * 30-day review window check.
     */
    public function canBeReviewed(): bool
    {
        if ($this->status !== 'completed') {
            return false;
        }

        $completedTime = $this->completed_at ?: $this->updated_at;
        return $completedTime && $completedTime->greaterThanOrEqualTo(now()->subDays(30));
    }
}
