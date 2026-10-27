<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CodRemittance extends Model
{
    protected $fillable = [
        'remittance_number',
        'courier_id',
        'sorting_center_id',
        'amount',
        'expected_amount',
        'status',
        'rider_notes',
        'operator_notes',
        'reviewed_by',
        'remitted_at',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expected_amount' => 'decimal:2',
            'remitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function courier()
    {
        return $this->belongsTo(User::class, 'courier_id');
    }

    public function sortingCenter()
    {
        return $this->belongsTo(User::class, 'sorting_center_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function hasDiscrepancy(): bool
    {
        return (float) $this->amount !== (float) $this->expected_amount;
    }
}
