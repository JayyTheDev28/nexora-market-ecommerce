<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SellerPayoutRequest extends Model
{
    protected $fillable = [
        'seller_id',
        'reference_number',
        'amount',
        'payout_method',
        'account_name',
        'account_number',
        'transaction_reference',
        'proof_path',
        'status', // pending, approved, paid, rejected
        'notes',
        'admin_notes',
        'reviewed_by',
        'rejection_reason',
        'processed_at',
        'disbursed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'processed_at' => 'datetime',
            'disbursed_at' => 'datetime',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(SellerSettlementAllocation::class, 'seller_payout_request_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isPaid(): bool
    {
        return in_array($this->status, ['paid', 'processed']);
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'paid', 'processed' => 'bg-emerald-500/10 text-emerald-800 border-emerald-500/20',
            'approved' => 'bg-indigo-500/10 text-indigo-800 border-indigo-500/20',
            'rejected' => 'bg-rose-500/10 text-rose-800 border-rose-500/20',
            default => 'bg-amber-500/10 text-amber-800 border-amber-500/20', // pending
        };
    }
}
