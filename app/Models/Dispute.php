<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dispute extends Model
{
    protected $fillable = [
        'order_id',
        'raised_by',
        'reason',
        'details',
        'status',
        'seller_status',
        'seller_response',
        'seller_response_at',
        'return_instructions',
        'resolution_note',
        'media_paths',
    ];

    protected function casts(): array
    {
        return [
            'media_paths' => 'array',
            'seller_response_at' => 'datetime',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function raisedBy()
    {
        return $this->belongsTo(User::class, 'raised_by');
    }
}
