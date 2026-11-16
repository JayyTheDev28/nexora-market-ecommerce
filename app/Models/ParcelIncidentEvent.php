<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ParcelIncidentEvent extends Model
{
    protected $fillable = [
        'incident_id',
        'actor_id',
        'actor_role',
        'event_type',
        'from_status',
        'to_status',
        'disposition',
        'location',
        'notes',
        'evidence_path',
        'metadata',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new RuntimeException('ParcelIncidentEvent records are immutable and cannot be modified.');
        });

        static::deleting(function () {
            throw new RuntimeException('ParcelIncidentEvent records are immutable and cannot be deleted.');
        });
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(ParcelIncident::class, 'incident_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function getEvidenceUrlAttribute(): ?string
    {
        if (! $this->evidence_path) {
            return null;
        }

        if (str_starts_with($this->evidence_path, 'http://') || str_starts_with($this->evidence_path, 'https://')) {
            return $this->evidence_path;
        }

        return Storage::url($this->evidence_path);
    }
}
