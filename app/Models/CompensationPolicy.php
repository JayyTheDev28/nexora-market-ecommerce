<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompensationPolicy extends Model
{
    public const ROLE_RIDER = 'rider';
    public const ROLE_COURIER = 'courier';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PROPOSED = 'proposed';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'logistics_company_id',
        'role',
        'name',
        'version',
        'status',
        'pickup_rate',
        'delivery_rate',
        'failed_attempt_rate',
        'distance_allowance_rate',
        'batch_completion_bonus',
        'transfer_trip_base_rate',
        'transfer_per_km_rate',
        'transfer_per_parcel_rate',
        'transfer_per_kg_rate',
        'transfer_fuel_allowance',
        'transfer_exception_rate',
        'pay_on_exception',
        'expected_manifest_parcels',
        'effective_from',
        'effective_to',
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'pickup_rate' => 'decimal:2',
            'delivery_rate' => 'decimal:2',
            'failed_attempt_rate' => 'decimal:2',
            'distance_allowance_rate' => 'decimal:2',
            'batch_completion_bonus' => 'decimal:2',
            'transfer_trip_base_rate' => 'decimal:2',
            'transfer_per_km_rate' => 'decimal:2',
            'transfer_per_parcel_rate' => 'decimal:2',
            'transfer_per_kg_rate' => 'decimal:2',
            'transfer_fuel_allowance' => 'decimal:2',
            'transfer_exception_rate' => 'decimal:2',
            'pay_on_exception' => 'boolean',
            'expected_manifest_parcels' => 'integer',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function logisticsCompany(): BelongsTo
    {
        return $this->belongsTo(User::class, 'logistics_company_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
