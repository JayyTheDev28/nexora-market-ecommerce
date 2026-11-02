<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Zone extends Model
{
    use HasFactory;

    protected $fillable = [
        'logistics_company_id',
        'hub_id',
        'name',
        'code',
        'province_code',
        'province_name',
        'city_code',
        'city_name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function logisticsCompany(): BelongsTo
    {
        return $this->belongsTo(User::class, 'logistics_company_id');
    }

    public function hub(): BelongsTo
    {
        return $this->belongsTo(Hub::class, 'hub_id');
    }

    public function barangays(): HasMany
    {
        return $this->hasMany(ZoneBarangay::class, 'zone_id');
    }

    public function riders(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'rider_zones', 'zone_id', 'rider_id')->withTimestamps();
    }
}

