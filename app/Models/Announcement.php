<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    protected $fillable = ['posted_by', 'title', 'body', 'target_roles', 'published_at'];

    protected function casts(): array
    {
        return [
            'target_roles' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function postedBy()
    {
        return $this->belongsTo(User::class, 'posted_by');
    }
}
