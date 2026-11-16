<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SafetyReportNote extends Model
{
    protected $fillable = [
        'safety_report_id',
        'author_id',
        'is_internal',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(SafetyReport::class, 'safety_report_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
