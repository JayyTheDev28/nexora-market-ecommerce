<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'seller_id',
        'category_id',
        'name',
        'slug',
        'description',
        'price',
        'compare_price',
        'flash_sale_price',
        'flash_sale_ends_at',
        'stock',
        'weight',
        'max_purchase_limit',
        'status',
        'gallery',
        'colors',
        'sizes',
        'variants_data',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'compare_price' => 'decimal:2',
            'flash_sale_price' => 'decimal:2',
            'flash_sale_ends_at' => 'datetime',
            'weight' => 'decimal:2',
            'max_purchase_limit' => 'integer',
            'gallery' => 'array',
            'colors' => 'array',
            'sizes' => 'array',
            'variants_data' => 'array',
        ];
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function wishlists()
    {
        return $this->hasMany(Wishlist::class);
    }

    public function isFlashSaleActive(): bool
    {
        return $this->flash_sale_price !== null
            && $this->flash_sale_price > 0
            && $this->flash_sale_ends_at !== null
            && $this->flash_sale_ends_at->isFuture();
    }

    public function getEffectivePriceAttribute(): float
    {
        if ($this->isFlashSaleActive()) {
            return (float) $this->flash_sale_price;
        }

        return (float) $this->price;
    }

    public function averageRating(): float
    {
        $avg = $this->reviews()->avg('rating');
        return $avg ? round($avg, 1) : 5.0;
    }
}
