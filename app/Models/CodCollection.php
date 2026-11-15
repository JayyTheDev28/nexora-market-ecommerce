<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CodCollection extends Model
{
    public const STATUS_COLLECTED = 'collected';
    public const STATUS_SUBMITTED = 'submitted_for_remittance';
    public const STATUS_PARTIAL = 'partially_remitted';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_DISPUTED = 'disputed';
    public const STATUS_SHORTAGE_FLAGGED = 'shortage_flagged';
    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'order_id',
        'courier_id',
        'collecting_logistics_id',
        'delivery_hub_id',
        'proof_of_delivery_id',
        'cod_amount_due',
        'actual_collected_amount',
        'verified_remitted_amount',
        'outstanding_amount',
        'shortage_amount',
        'overpayment_amount',
        'status',
        'idempotency_key',
        'collected_at',
        'verified_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'cod_amount_due' => 'decimal:2',
            'actual_collected_amount' => 'decimal:2',
            'verified_remitted_amount' => 'decimal:2',
            'outstanding_amount' => 'decimal:2',
            'shortage_amount' => 'decimal:2',
            'overpayment_amount' => 'decimal:2',
            'collected_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'courier_id');
    }

    public function collectingLogistics(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collecting_logistics_id');
    }

    public function deliveryHub(): BelongsTo
    {
        return $this->belongsTo(Hub::class, 'delivery_hub_id');
    }

    public function proofOfDelivery(): BelongsTo
    {
        return $this->belongsTo(ProofOfDelivery::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(CodRemittanceAllocation::class, 'cod_collection_id');
    }

    public function isFullyVerifiedForAdminRemittance(): bool
    {
        $dueCents = (int) round((float) $this->cod_amount_due * 100);
        $verifiedCents = (int) round((float) $this->verified_remitted_amount * 100);
        $shortageCents = (int) round((float) $this->shortage_amount * 100);

        return $this->status === self::STATUS_VERIFIED
            && $verifiedCents >= $dueCents
            && $shortageCents <= 0;
    }
}
