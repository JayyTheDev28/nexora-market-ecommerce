<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryAttempt extends Model
{
    protected $fillable = [
        'order_id', 'courier_id', 'result', 'failure_reason', 'notes', 'attempted_at',
    ];

    protected function casts(): array
    {
        return ['attempted_at' => 'datetime'];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function courier()
    {
        return $this->belongsTo(User::class, 'courier_id');
    }
}
