<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PsgcProvince extends Model
{
    protected $fillable = ['code', 'name', 'region_code', 'region_name'];

    public function cities(): HasMany
    {
        return $this->hasMany(PsgcCity::class, 'province_code', 'code');
    }

    public function barangays(): HasMany
    {
        return $this->hasMany(PsgcBarangay::class, 'province_code', 'code');
    }
}

