<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'operator_id',
        'action',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'reason',
        'ip_address',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Enforce read-only constraint: audit logs are strictly append-only
        static::updating(function () {
            throw new RuntimeException('Audit logs are strictly read-only and cannot be updated.');
        });

        static::deleting(function () {
            throw new RuntimeException('Audit logs are strictly read-only and cannot be deleted.');
        });
    }

    public function operator()
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function auditable()
    {
        return $this->morphTo();
    }

    /**
     * Record an audit event.
     */
    public static function log(
        string $action,
        Model $auditable,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $reason = null,
        ?int $operatorId = null
    ): self {
        return self::create([
            'operator_id' => $operatorId ?? auth()->id(),
            'action' => $action,
            'auditable_type' => get_class($auditable),
            'auditable_id' => $auditable->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'reason' => $reason,
            'ip_address' => request()?->ip(),
            'created_at' => now(),
        ]);
    }
}
