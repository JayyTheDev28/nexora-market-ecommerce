<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HubTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'tracking_number',
        'sender_logistics_id',
        'sender_hub_id',
        'receiver_logistics_id',
        'receiver_hub_id',
        'status',
        'dispatched_at',
        'dispatched_by',
        'received_at',
        'received_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'dispatched_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function senderLogistics(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_logistics_id');
    }

    public function senderHub(): BelongsTo
    {
        return $this->belongsTo(Hub::class, 'sender_hub_id');
    }

    public function receiverLogistics(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_logistics_id');
    }

    public function receiverHub(): BelongsTo
    {
        return $this->belongsTo(Hub::class, 'receiver_hub_id');
    }

    public function dispatchedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}

