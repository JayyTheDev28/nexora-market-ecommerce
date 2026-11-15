<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransferManifestItem extends Model
{
    use HasFactory;

    public const LOADING_PENDING = 'pending';
    public const LOADING_LOADED = 'loaded';
    public const LOADING_ISSUE = 'issue';

    public const RECEIVING_PENDING = 'pending';
    public const RECEIVING_RECEIVED = 'received';
    public const RECEIVING_MISSING = 'missing';
    public const RECEIVING_DAMAGED = 'damaged';
    public const RECEIVING_DISCREPANCY = 'discrepancy';

    protected $fillable = [
        'manifest_id',
        'hub_transfer_id',
        'order_id',
        'tracking_number',
        'loading_status',
        'loaded_at',
        'loaded_by',
        'receiving_status',
        'received_at',
        'received_by',
        'issue_notes',
    ];

    protected function casts(): array
    {
        return [
            'loaded_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function manifest(): BelongsTo
    {
        return $this->belongsTo(TransferManifest::class, 'manifest_id');
    }

    public function hubTransfer(): BelongsTo
    {
        return $this->belongsTo(HubTransfer::class, 'hub_transfer_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function loadedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'loaded_by');
    }

    public function receivedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
