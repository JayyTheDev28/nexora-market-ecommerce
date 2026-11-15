<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class LogisticsAdminRemittance extends Model
{
    protected $fillable = [
        'logistics_id',
        'reference_number',
        'total_cod_amount',
        'shipping_retained',
        'shipping_subsidy_total',
        'merchandise_amount',
        'actual_received_amount',
        'variance_amount',
        'transfer_method',
        'transfer_reference',
        'verified_bank_reference',
        'proof_path',
        'notes',
        'status',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'discrepancy_notes',
    ];

    protected function casts(): array
    {
        return [
            'total_cod_amount' => 'decimal:2',
            'shipping_retained' => 'decimal:2',
            'shipping_subsidy_total' => 'decimal:2',
            'merchandise_amount' => 'decimal:2',
            'actual_received_amount' => 'decimal:2',
            'variance_amount' => 'decimal:2',
            'reviewed_at' => 'datetime',
        ];
    }

    public function logistics(): BelongsTo
    {
        return $this->belongsTo(User::class, 'logistics_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(LogisticsRemittanceItem::class, 'logistics_admin_remittance_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(SellerSettlementAllocation::class, 'logistics_admin_remittance_id');
    }

    public function isSubmitted(): bool
    {
        return $this->status === 'submitted';
    }

    public function isUnderReview(): bool
    {
        return $this->status === 'under_review';
    }

    public function isVerified(): bool
    {
        return $this->status === 'verified';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isDisputed(): bool
    {
        return $this->status === 'disputed';
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'verified' => 'bg-emerald-500/10 text-emerald-800 border-emerald-500/20',
            'under_review' => 'bg-indigo-500/10 text-indigo-800 border-indigo-500/20',
            'rejected' => 'bg-rose-500/10 text-rose-800 border-rose-500/20',
            'disputed' => 'bg-purple-500/10 text-purple-800 border-purple-500/20',
            default => 'bg-amber-500/10 text-amber-800 border-amber-500/20', // submitted
        };
    }
}

