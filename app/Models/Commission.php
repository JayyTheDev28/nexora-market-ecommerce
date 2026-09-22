<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Commission extends Model
{
    protected $fillable = [
        'order_id', 'seller_id', 'order_total', 'rate',
        'commission_amount', 'seller_payout', 'status', 'settled_at',
    ];

    protected function casts(): array
    {
        return [
            'order_total' => 'decimal:2',
            'rate' => 'decimal:4',
            'commission_amount' => 'decimal:2',
            'seller_payout' => 'decimal:2',
            'settled_at' => 'datetime',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }
}
