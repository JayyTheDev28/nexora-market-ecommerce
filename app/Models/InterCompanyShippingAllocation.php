<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InterCompanyShippingAllocation extends Model
{
    protected $fillable = [
        'order_id',
        'shipping_rate_policy_id',
        'total_shipping_fee',
        'buyer_paid_shipping',
        'platform_shipping_subsidy',
        'pickup_logistics_id',
        'pickup_share_amount',
        'transfer_logistics_id',
        'transfer_share_amount',
        'delivery_logistics_id',
        'delivery_share_amount',
        'collecting_logistics_id',
        'allocation_mode',
        'contract_reference',
        'settlement_status',
        'settled_at',
        'breakdown',
    ];

    protected function casts(): array
    {
        return [
            'total_shipping_fee' => 'decimal:2',
            'buyer_paid_shipping' => 'decimal:2',
            'platform_shipping_subsidy' => 'decimal:2',
            'pickup_share_amount' => 'decimal:2',
            'transfer_share_amount' => 'decimal:2',
            'delivery_share_amount' => 'decimal:2',
            'settled_at' => 'datetime',
            'breakdown' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(ShippingRatePolicy::class, 'shipping_rate_policy_id');
    }

    public function pickupLogistics(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pickup_logistics_id');
    }

    public function transferLogistics(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transfer_logistics_id');
    }

    public function deliveryLogistics(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivery_logistics_id');
    }

    public function collectingLogistics(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collecting_logistics_id');
    }
}
