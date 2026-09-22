<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryArea extends Model
{
    protected $fillable = ['sorting_center_id', 'courier_id', 'name'];

    public function sortingCenter()
    {
        return $this->belongsTo(User::class, 'sorting_center_id');
    }

    public function courier()
    {
        return $this->belongsTo(User::class, 'courier_id');
    }
}
