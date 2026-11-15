<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ParcelIncident extends Model
{
    use HasFactory;

    public const TYPE_DAMAGE = 'damage';
    public const TYPE_MISSING = 'missing';
    public const TYPE_DISCREPANCY = 'discrepancy';

    public const STAGE_MANIFEST_RECEIVING = 'manifest_receiving';
    public const STAGE_POST_RECEIPT = 'post_receipt';

    public const STATUS_OPEN = 'open';
    public const STATUS_UNDER_INVESTIGATION = 'under_investigation';
    public const STATUS_FOUND = 'found';
    public const STATUS_RESOLVED = 'resolved';

    public const ALL_TYPES = [
        self::TYPE_DAMAGE,
        self::TYPE_MISSING,
        self::TYPE_DISCREPANCY,
    ];

    public const ALL_STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_UNDER_INVESTIGATION,
        self::STATUS_FOUND,
        self::STATUS_RESOLVED,
    ];

    public const UNRESOLVED_STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_UNDER_INVESTIGATION,
        self::STATUS_FOUND,
    ];

    public const DAMAGE_TYPES = [
        'crushed_packaging' => 'Crushed / Dented Packaging',
        'water_damage' => 'Water / Liquid Damage',
        'torn_open' => 'Torn / Opened Parcel',
        'broken_contents' => 'Broken / Leaking Contents',
        'tampered_seal' => 'Tampered / Missing Seal',
        'other' => 'Other Physical Damage',
    ];

    protected $fillable = [
        'order_id',
        'manifest_id',
        'manifest_item_id',
        'hub_id',
        'logistics_company_id',
        'reported_by',
        'resolved_by',
        'tracking_number',
        'incident_type',
        'damage_type',
        'source_stage',
        'investigation_status',
        'description',
        'evidence_path',
        'resolution_notes',
        'reported_at',
        'found_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'reported_at' => 'datetime',
            'found_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function manifest(): BelongsTo
    {
        return $this->belongsTo(TransferManifest::class, 'manifest_id');
    }

    public function manifestItem(): BelongsTo
    {
        return $this->belongsTo(TransferManifestItem::class, 'manifest_item_id');
    }

    public function hub(): BelongsTo
    {
        return $this->belongsTo(Hub::class);
    }

    public function logisticsCompany(): BelongsTo
    {
        return $this->belongsTo(User::class, 'logistics_company_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->where('investigation_status', '!=', self::STATUS_RESOLVED);
    }

    public function isResolved(): bool
    {
        return $this->investigation_status === self::STATUS_RESOLVED;
    }

    public function isUnresolved(): bool
    {
        return ! $this->isResolved();
    }

    public function getDamageTypeLabelAttribute(): ?string
    {
        if (! $this->damage_type) {
            return null;
        }

        return self::DAMAGE_TYPES[$this->damage_type] ?? ucwords(str_replace('_', ' ', $this->damage_type));
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->investigation_status) {
            self::STATUS_OPEN => 'Open',
            self::STATUS_UNDER_INVESTIGATION => 'Under Investigation',
            self::STATUS_FOUND => 'Found',
            self::STATUS_RESOLVED => 'Resolved',
            default => ucfirst(str_replace('_', ' ', (string) $this->investigation_status)),
        };
    }

    public function getEvidenceUrlAttribute(): ?string
    {
        if (! $this->evidence_path) {
            return null;
        }

        return Storage::disk('public')->url($this->evidence_path);
    }
}
