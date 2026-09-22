<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellerWarning extends Model
{
    protected $fillable = ['seller_id', 'issued_by', 'product_id', 'violation_type', 'details'];

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function issuedBy()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
