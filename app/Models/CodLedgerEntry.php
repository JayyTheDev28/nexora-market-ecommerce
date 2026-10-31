<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CodLedgerEntry extends Model
{
    protected $fillable = [
        'courier_id',
        'order_id',
        'type',
        'amount',
        'balance_after',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
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
