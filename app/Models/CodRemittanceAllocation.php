<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CodRemittanceAllocation extends Model
{
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_PARTIALLY_VERIFIED = 'partially_verified';
    public const STATUS_DISPUTED = 'disputed';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_RELEASED = 'released';

    protected $fillable = [
        'cod_remittance_id',
        'cod_collection_id',
        'order_id',
        'allocated_amount',
        'verified_amount',
        'shortage_amount',
        'overpayment_amount',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'allocated_amount' => 'decimal:2',
            'verified_amount' => 'decimal:2',
            'shortage_amount' => 'decimal:2',
            'overpayment_amount' => 'decimal:2',
        ];
    }

    public function remittance(): BelongsTo
    {
        return $this->belongsTo(CodRemittance::class, 'cod_remittance_id');
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(CodCollection::class, 'cod_collection_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
