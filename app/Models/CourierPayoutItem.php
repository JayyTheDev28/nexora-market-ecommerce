<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourierPayoutItem extends Model
{
    protected $fillable = [
        'courier_payout_id',
        'rider_earning_id',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(CourierPayout::class, 'courier_payout_id');
    }

    public function earning(): BelongsTo
    {
        return $this->belongsTo(RiderEarning::class, 'rider_earning_id');
    }
}
