<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    protected $fillable = [
        'seller_id',
        'code',
        'type',
        'voucher_scope',
        'value',
        'min_spend',
        'max_discount',
        'usage_limit',
        'is_active',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'min_spend' => 'decimal:2',
            'max_discount' => 'decimal:2',
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function userClaims()
    {
        return $this->hasMany(UserVoucher::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isUsableFor(float $subtotal, ?int $sellerId = null): bool
    {
        if (! $this->is_active || $this->isExpired()) {
            return false;
        }

        if ($this->voucher_scope === 'store' && $this->seller_id !== null) {
            if ($sellerId !== null && (int) $this->seller_id !== (int) $sellerId) {
                return false;
            }
        }

        if ($subtotal < (float) $this->min_spend) {
            return false;
        }

        return true;
    }

    public function calculateDiscount(float $amount): float
    {
        if ($amount <= 0) {
            return 0.00;
        }

        if ($this->type === 'flat') {
            return round(min((float) $this->value, $amount), 2);
        }

        // Percentage discount
        $discount = $amount * ((float) $this->value / 100);
        if ($this->max_discount !== null && $this->max_discount > 0) {
            $discount = min($discount, (float) $this->max_discount);
        }

        return round(min($discount, $amount), 2);
    }
}
