<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LogisticsProfile extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'company_name',
        'sec_registration_no',
        'tin',
        'region',
        'province',
        'city_municipality',
        'barangay',
        'street_address',
        'postal_code',
        'contact_person_first_name',
        'contact_person_last_name',
        'position',
        'email',
        'mobile_number',
        'service_areas',
        'sec_certificate_path',
        'bir_2303_path',
        'mayors_permit_path',
        'valid_id_path',
        'status',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'service_areas' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function otps(): HasMany
    {
        return $this->hasMany(Otp::class);
    }

    public function isUnverified(): bool
    {
        return $this->status === 'unverified';
    }

    public function isPendingApproval(): bool
    {
        return $this->status === 'pending_approval';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function getContactPersonFullNameAttribute(): string
    {
        return trim("{$this->contact_person_first_name} {$this->contact_person_last_name}");
    }

    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->street_address,
            $this->barangay,
            $this->city_municipality,
            $this->province,
            $this->region,
            $this->postal_code,
        ]);

        return implode(', ', $parts);
    }
}

