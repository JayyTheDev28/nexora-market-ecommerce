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
        'resolution_note',
        'media_paths',
    ];

    protected function casts(): array
    {
        return [
            'media_paths' => 'array',
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
