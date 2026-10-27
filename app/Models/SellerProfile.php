<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellerProfile extends Model
{
    protected $fillable = [
        'user_id',
        'business_name',
        'category',
        'business_permit_path',
        'description',
        'logo_path',
        'banner_path',
        'contact_phone',
        'contact_email',
        'is_vacation_mode',
        'vacation_notice',
    ];

    protected function casts(): array
    {
        return [
            'is_vacation_mode' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getLineOfBusinessAttribute(): ?string
    {
        return $this->category;
    }
}
