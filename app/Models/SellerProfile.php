<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellerProfile extends Model
{
    protected $fillable = ['user_id', 'business_name', 'category', 'business_permit_path'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
