<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PsgcBarangay extends Model
{
    protected $fillable = ['code', 'name', 'city_code', 'city_name', 'province_code'];

    public function city(): BelongsTo
    {
        return $this->belongsTo(PsgcCity::class, 'city_code', 'code');
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(PsgcProvince::class, 'province_code', 'code');
    }
}

