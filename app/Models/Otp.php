<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Otp extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'logistics_profile_id',
        'otp_code',
        'expires_at',
        'attempts',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function logisticsProfile(): BelongsTo
    {
        return $this->belongsTo(LogisticsProfile::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function hasExceededMaxAttempts(int $max = 5): bool
    {
        return $this->attempts >= $max;
    }

    public function incrementAttempts(): void
    {
        $this->increment('attempts');
    }

    public function matches(string $code): bool
    {
        return hash_equals((string) $this->otp_code, (string) $code);
    }
}

