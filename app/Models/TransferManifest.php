<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransferManifest extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_LOADING = 'loading';
    public const STATUS_READY_TO_DISPATCH = 'ready_to_dispatch';
    public const STATUS_IN_TRANSIT = 'in_transit';
    public const STATUS_ARRIVED = 'arrived';
    public const STATUS_RECEIVING = 'receiving';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CLOSED_WITH_EXCEPTIONS = 'closed_with_exceptions';
    public const STATUS_EXCEPTION = 'exception';
    public const STATUS_CANCELLED = 'cancelled';

    public const ACTIVE_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_LOADING,
        self::STATUS_READY_TO_DISPATCH,
        self::STATUS_IN_TRANSIT,
        self::STATUS_ARRIVED,
        self::STATUS_RECEIVING,
        self::STATUS_EXCEPTION,
    ];

    public const ALLOWED_TRANSITIONS = [
        self::STATUS_DRAFT => [
            self::STATUS_LOADING,
            self::STATUS_READY_TO_DISPATCH,
            self::STATUS_CANCELLED,
        ],
        self::STATUS_LOADING => [
            self::STATUS_READY_TO_DISPATCH,
            self::STATUS_IN_TRANSIT,
            self::STATUS_EXCEPTION,
            self::STATUS_CANCELLED,
        ],
        self::STATUS_READY_TO_DISPATCH => [
            self::STATUS_IN_TRANSIT,
            self::STATUS_EXCEPTION,
            self::STATUS_CANCELLED,
        ],
        self::STATUS_IN_TRANSIT => [
            self::STATUS_ARRIVED,
            self::STATUS_EXCEPTION,
        ],
        self::STATUS_ARRIVED => [
            self::STATUS_RECEIVING,
            self::STATUS_COMPLETED,
            self::STATUS_CLOSED_WITH_EXCEPTIONS,
            self::STATUS_EXCEPTION,
        ],
        self::STATUS_RECEIVING => [
            self::STATUS_COMPLETED,
            self::STATUS_CLOSED_WITH_EXCEPTIONS,
            self::STATUS_EXCEPTION,
        ],
        self::STATUS_EXCEPTION => [
            self::STATUS_LOADING,
            self::STATUS_READY_TO_DISPATCH,
            self::STATUS_IN_TRANSIT,
            self::STATUS_ARRIVED,
            self::STATUS_RECEIVING,
            self::STATUS_COMPLETED,
            self::STATUS_CLOSED_WITH_EXCEPTIONS,
            self::STATUS_CANCELLED,
        ],
        self::STATUS_COMPLETED => [],
        self::STATUS_CLOSED_WITH_EXCEPTIONS => [],
        self::STATUS_CANCELLED => [],
    ];

    protected $fillable = [
        'manifest_number',
        'sender_logistics_id',
        'origin_hub_id',
        'receiver_logistics_id',
        'destination_hub_id',
        'assigned_courier_id',
        'vehicle_plate_number',
        'distance_km',
        'status',
        'total_parcels',
        'dispatched_at',
        'arrived_at',
        'completed_at',
        'closed_with_exceptions_at',
        'closed_with_exceptions_by',
        'created_by',
        'notes',
        'reconciliation_notes',
    ];

    protected function casts(): array
    {
        return [
            'total_parcels' => 'integer',
            'distance_km' => 'decimal:2',
            'dispatched_at' => 'datetime',
            'arrived_at' => 'datetime',
            'completed_at' => 'datetime',
            'closed_with_exceptions_at' => 'datetime',
        ];
    }

    public function canTransitionTo(string $newStatus): bool
    {
        if ($this->status === $newStatus) {
            return true;
        }

        return in_array($newStatus, self::ALLOWED_TRANSITIONS[$this->status] ?? [], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_CLOSED_WITH_EXCEPTIONS,
            self::STATUS_CANCELLED,
        ], true);
    }

    public function getIsInternalAttribute(): bool
    {
        return (int) $this->sender_logistics_id === (int) $this->receiver_logistics_id;
    }

    public function getTransferTypeAttribute(): string
    {
        return $this->is_internal ? 'internal' : 'external';
    }

    public function senderLogistics(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_logistics_id');
    }

    public function receiverLogistics(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_logistics_id');
    }

    public function originHub(): BelongsTo
    {
        return $this->belongsTo(Hub::class, 'origin_hub_id');
    }

    public function destinationHub(): BelongsTo
    {
        return $this->belongsTo(Hub::class, 'destination_hub_id');
    }

    public function assignedCourier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_courier_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function closedWithExceptionsBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_with_exceptions_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransferManifestItem::class, 'manifest_id');
    }

    public function hubTransfers(): HasMany
    {
        return $this->hasMany(HubTransfer::class, 'manifest_id');
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(ParcelIncident::class, 'manifest_id');
    }
}
