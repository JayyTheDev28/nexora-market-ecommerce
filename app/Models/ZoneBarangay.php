<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZoneBarangay extends Model
{
    use HasFactory;

    protected $fillable = [
        'zone_id',
        'logistics_company_id',
        'province_code',
        'province_name',
        'city_code',
        'city_name',
        'barangay_code',
        'barangay_name',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function logisticsCompany(): BelongsTo
    {
        return $this->belongsTo(User::class, 'logistics_company_id');
    }
}

