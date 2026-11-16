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
     * Record an audit event with automatic redaction of sensitive keys.
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
            'old_values' => self::redactSensitive($oldValues),
            'new_values' => self::redactSensitive($newValues),
            'reason' => $reason,
            'ip_address' => request()?->ip(),
            'created_at' => now(),
        ]);
    }

    private static function redactSensitive(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $sensitiveKeys = [
            'password',
            'password_confirmation',
            'current_password',
            'token',
            'remember_token',
            'activation_token',
            'secret',
            'two_factor_secret',
            'api_key',
            'authorization',
            'email_verification_otp',
            'pending_email_otp',
            'pending_phone_otp',
        ];

        foreach ($values as $key => $val) {
            if (is_array($val)) {
                $values[$key] = self::redactSensitive($val);
            } elseif (in_array(strtolower((string) $key), $sensitiveKeys, true)) {
                $values[$key] = '[REDACTED]';
            }
        }

        return $values;
    }
}
