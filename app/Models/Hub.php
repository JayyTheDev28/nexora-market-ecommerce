<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Hub extends Model
{
    use HasFactory;

    protected $fillable = [
        'logistics_company_id',
        'name',
        'code',
        'province_code',
        'province_name',
        'city_code',
        'city_name',
        'barangay_code',
        'barangay_name',
        'street_address',
        'contact_number',
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

    public function zones(): HasMany
    {
        return $this->hasMany(Zone::class, 'hub_id');
    }

    public function transfersSent(): HasMany
    {
        return $this->hasMany(HubTransfer::class, 'sender_hub_id');
    }

    public function transfersReceived(): HasMany
    {
        return $this->hasMany(HubTransfer::class, 'receiver_hub_id');
    }

    public function getFullLocationAttribute(): string
    {
        $parts = array_filter([
            $this->street_address,
            $this->barangay_name,
            $this->city_name,
            $this->province_name,
        ]);

        return implode(', ', $parts);
    }
}

