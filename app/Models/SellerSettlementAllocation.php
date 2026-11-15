<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerSettlementAllocation extends Model
{
    protected $fillable = [
        'order_id',
        'seller_id',
        'logistics_admin_remittance_id',
        'eligible_merchandise_amount',
        'commission_rate',
        'platform_commission',
        'seller_payable',
        'status',
        'seller_payout_request_id',
        'settled_at',
    ];

    protected function casts(): array
    {
        return [
            'eligible_merchandise_amount' => 'decimal:2',
            'commission_rate' => 'decimal:4',
            'platform_commission' => 'decimal:2',
            'seller_payable' => 'decimal:2',
            'settled_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function logisticsAdminRemittance(): BelongsTo
    {
        return $this->belongsTo(LogisticsAdminRemittance::class, 'logistics_admin_remittance_id');
    }

    public function payoutRequest(): BelongsTo
    {
        return $this->belongsTo(SellerPayoutRequest::class, 'seller_payout_request_id');
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', 'available');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeWithdrawn(Builder $query): Builder
    {
        return $query->where('status', 'paid');
    }
}

