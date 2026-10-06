<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'order_number', 'tracking_number', 'buyer_id', 'address_id', 'status',
        'payment_method', 'subtotal', 'discount', 'shipping_fee', 'total',
        'pickup_courier_id', 'delivery_courier_id', 'sorting_center_id',
        'delivery_area_id', 'placed_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'total' => 'decimal:2',
            'placed_at' => 'datetime',
        ];
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function address()
    {
        return $this->belongsTo(Address::class);
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

    public function commission()
    {
        return $this->hasOne(Commission::class);
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
}
