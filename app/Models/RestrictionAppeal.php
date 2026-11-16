<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestrictionAppeal extends Model
{
    protected $fillable = [
        'account_restriction_id',
        'user_id',
        'reason',
        'evidence_paths',
        'status',
        'reviewed_by',
        'admin_notes',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'evidence_paths' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function restriction(): BelongsTo
    {
        return $this->belongsTo(AccountRestriction::class, 'account_restriction_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
