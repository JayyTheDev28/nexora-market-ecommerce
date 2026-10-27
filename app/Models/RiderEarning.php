<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiderEarning extends Model
{
    protected $fillable = [
        'courier_id',
        'order_id',
        'type',
        'amount',
        'description',
        'earned_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'earned_at' => 'datetime',
        ];
    }

    public function courier()
    {
        return $this->belongsTo(User::class, 'courier_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
