<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiderEarning extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_EARNED = 'earned';
    public const STATUS_APPROVED_FOR_PAYOUT = 'approved_for_payout';
    public const STATUS_PAID = 'paid';
    public const STATUS_ON_HOLD = 'on_hold';
    public const STATUS_DISPUTED = 'disputed';
    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'courier_id',
        'logistics_company_id',
        'order_id',
        'manifest_id',
        'policy_id',
        'courier_payout_id',
        'earning_key',
        'type',
        'amount',
        'status',
        'description',
        'rate_snapshot',
        'earned_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'rate_snapshot' => 'array',
            'earned_at' => 'datetime',
        ];
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'courier_id');
    }

    public function logisticsCompany(): BelongsTo
    {
        return $this->belongsTo(User::class, 'logistics_company_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function manifest(): BelongsTo
    {
        return $this->belongsTo(TransferManifest::class, 'manifest_id');
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(CompensationPolicy::class, 'policy_id');
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(CourierPayout::class, 'courier_payout_id');
    }
}
