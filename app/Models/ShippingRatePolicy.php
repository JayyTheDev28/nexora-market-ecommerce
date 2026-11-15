<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingRatePolicy extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';

    public const REVIEW_PENDING = 'pending_admin_review';
    public const REVIEW_APPROVED = 'approved';
    public const REVIEW_REJECTED = 'rejected';

    protected $fillable = [
        'name',
        'logistics_company_id',
        'version',
        'status',
        'review_status',
        'contract_reference',
        'base_fee',
        'base_weight_kg',
        'per_additional_kg_fee',
        'minimum_fee',
        'origin_logistics_share_pct',
        'transfer_carrier_share_pct',
        'delivery_logistics_share_pct',
        'platform_shipping_commission_pct',
        'effective_from',
        'effective_until',
        'created_by',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'base_fee' => 'decimal:2',
            'base_weight_kg' => 'decimal:2',
            'per_additional_kg_fee' => 'decimal:2',
            'minimum_fee' => 'decimal:2',
            'origin_logistics_share_pct' => 'decimal:2',
            'transfer_carrier_share_pct' => 'decimal:2',
            'delivery_logistics_share_pct' => 'decimal:2',
            'platform_shipping_commission_pct' => 'decimal:2',
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function logisticsCompany(): BelongsTo
    {
        return $this->belongsTo(User::class, 'logistics_company_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isApprovedForSharing(? \Carbon\CarbonInterface $at = null): bool
    {
        if ($this->review_status !== self::REVIEW_APPROVED) {
            return false;
        }
        $point = $at ?? now();
        if ($this->effective_from && $point->lt($this->effective_from)) {
            return false;
        }
        if ($this->effective_until && $point->gt($this->effective_until)) {
            return false;
        }

        return true;
    }
}
