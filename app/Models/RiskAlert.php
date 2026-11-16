<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskAlert extends Model
{
    public const STATUSES = ['open', 'investigating', 'resolved', 'dismissed', 'escalated'];
    public const SEVERITIES = ['low', 'medium', 'high', 'critical'];

    protected $fillable = [
        'rule_code',
        'title',
        'severity',
        'entity_type',
        'entity_id',
        'user_id',
        'supporting_data',
        'status',
        'assigned_admin_id',
        'decision_notes',
        'detected_at',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'supporting_data' => 'array',
            'detected_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_admin_id');
    }
}
