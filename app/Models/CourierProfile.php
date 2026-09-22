<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourierProfile extends Model
{
    protected $fillable = ['user_id', 'vehicle_type', 'plate_number', 'or_cr_path', 'license_path'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
