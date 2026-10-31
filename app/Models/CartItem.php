<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    protected $fillable = ['user_id', 'product_id', 'variant', 'quantity', 'selected'];

    protected $casts = [
        'variant' => 'array',
        'selected' => 'boolean',
        'quantity' => 'integer',
    ];

    protected function casts(): array
    {
        return [
            'variant' => 'array',
            'selected' => 'boolean',
            'quantity' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function getUnitPriceAttribute(): float
    {
        if (! $this->product) {
            return 0.0;
        }

        // If variant contains custom price in variant matrix or product flash sale:
        if (is_array($this->variant) && isset($this->variant['price']) && is_numeric($this->variant['price'])) {
            return (float) $this->variant['price'];
        }

        return (float) $this->product->effective_price;
    }

    public function getLineTotalAttribute(): float
    {
        return round($this->unit_price * $this->quantity, 2);
    }

    public function getWeightTotalAttribute(): float
    {
        if (! $this->product) {
            return 0.0;
        }

        $unitWeight = (float) ($this->product->weight ?? 1.0);
        return round($unitWeight * $this->quantity, 2);
    }
}
