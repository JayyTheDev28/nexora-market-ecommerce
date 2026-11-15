<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CodRemittance extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING = 'pending';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_PARTIALLY_VERIFIED = 'partially_verified';
    public const STATUS_DISCREPANCY_FLAGGED = 'discrepancy_flagged';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    public const OPEN_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_SUBMITTED,
        self::STATUS_UNDER_REVIEW,
    ];

    public const VERIFIED_STATUSES = [
        self::STATUS_APPROVED,
        self::STATUS_VERIFIED,
        self::STATUS_PARTIALLY_VERIFIED,
    ];

    protected $fillable = [
        'remittance_number',
        'courier_id',
        'sorting_center_id',
        'collecting_hub_id',
        'amount',
        'claimed_amount',
        'actual_received_amount',
        'expected_amount',
        'shortage_amount',
        'overpayment_amount',
        'payment_method',
        'external_reference',
        'transfer_date',
        'proof_path',
        'verification_reference',
        'status',
        'rider_notes',
        'operator_notes',
        'discrepancy_reason',
        'reviewed_by',
        'remitted_at',
        'reviewed_at',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'claimed_amount' => 'decimal:2',
            'actual_received_amount' => 'decimal:2',
            'expected_amount' => 'decimal:2',
            'shortage_amount' => 'decimal:2',
            'overpayment_amount' => 'decimal:2',
            'remitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'transfer_date' => 'datetime',
        ];
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'courier_id');
    }

    public function sortingCenter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sorting_center_id');
    }

    public function collectingHub(): BelongsTo
    {
        return $this->belongsTo(Hub::class, 'collecting_hub_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(CodRemittanceAllocation::class, 'cod_remittance_id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function isVerified(): bool
    {
        return in_array($this->status, self::VERIFIED_STATUSES, true);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            self::STATUS_APPROVED,
            self::STATUS_VERIFIED,
            self::STATUS_PARTIALLY_VERIFIED,
            self::STATUS_REJECTED,
            self::STATUS_CANCELLED,
        ], true);
    }

    public function hasDiscrepancy(): bool
    {
        $claimedCents = (int) round((float) ($this->claimed_amount ?? $this->amount) * 100);
        $expectedCents = (int) round((float) $this->expected_amount * 100);
        $shortageCents = (int) round((float) $this->shortage_amount * 100);
        $overpayCents = (int) round((float) $this->overpayment_amount * 100);

        return $claimedCents !== $expectedCents || $shortageCents > 0 || $overpayCents > 0;
    }
}
