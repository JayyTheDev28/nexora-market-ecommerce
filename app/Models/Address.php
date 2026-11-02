<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    protected $fillable = [
        'user_id',
        'region_code',
        'region_name',
        'province_code',
        'province_name',
        'municipality_code',
        'municipality_name',
        'barangay_code',
        'barangay_name',
        'street',
        'house_number',
        'recipient_name',
        'recipient_phone',
        'postal_code',
        'is_default',
        'needs_manual_review',
        'manual_review_reason',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'needs_manual_review' => 'boolean',
        ];
    }

    public function getCityCodeAttribute(): ?string
    {
        return $this->municipality_code;
    }

    public function getCityNameAttribute(): ?string
    {
        return $this->municipality_name;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->house_number,
            $this->street,
            $this->barangay_name,
            $this->municipality_name,
            $this->province_name,
            $this->region_name,
            $this->postal_code,
        ]);

        return implode(', ', $parts);
    }
}
