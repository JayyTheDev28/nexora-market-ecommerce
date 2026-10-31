<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobOffer extends Model
{
    protected $fillable = [
        'order_id',
        'courier_id',
        'type',
        'status',
        'pickup_location',
        'dropoff_location',
        'cod_amount',
        'expected_pay',
        'expires_at',
        'consecutive_declines_count',
    ];

    protected function casts(): array
    {
        return [
            'cod_amount' => 'decimal:2',
            'expected_pay' => 'decimal:2',
            'expires_at' => 'datetime',
            'consecutive_declines_count' => 'integer',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function courier()
    {
        return $this->belongsTo(User::class, 'courier_id');
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired' || ($this->status === 'pending' && now()->greaterThan($this->expires_at));
    }

    public function isPending(): bool
    {
        return $this->status === 'pending' && now()->lessThanOrEqualTo($this->expires_at);
    }
}
