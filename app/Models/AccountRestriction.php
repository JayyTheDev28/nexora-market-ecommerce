<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountRestriction extends Model
{
    public const TYPES = [
        'temporary_restriction' => 'Temporary Account Restriction',
        'suspension' => 'Account Suspension',
        'listing_restriction' => 'Product Listing Restriction',
        'selling_restriction' => 'Seller Selling Restriction',
        'logistics_restriction' => 'Logistics Operational Restriction',
        'payout_hold' => 'Financial Payout Hold',
    ];

    protected $fillable = [
        'user_id',
        'restriction_type',
        'reason',
        'reference_type',
        'reference_id',
        'safety_report_id',
        'issued_by',
        'starts_at',
        'expires_at',
        'lifted_at',
        'lifted_by',
        'lift_reason',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'lifted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function lifter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lifted_by');
    }

    public function safetyReport(): BelongsTo
    {
        return $this->belongsTo(SafetyReport::class);
    }

    public function appeals(): HasMany
    {
        return $this->hasMany(RestrictionAppeal::class)->latest();
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->restriction_type] ?? ucwords(str_replace('_', ' ', $this->restriction_type));
    }

    public function isCurrentlyEffective(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }
        return true;
    }
}
