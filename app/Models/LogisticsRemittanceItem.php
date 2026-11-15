<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogisticsRemittanceItem extends Model
{
    protected $fillable = [
        'logistics_admin_remittance_id',
        'order_id',
        'cod_remittance_id',
        'total_cod_amount',
        'shipping_component',
        'merchandise_component',
    ];

    protected function casts(): array
    {
        return [
            'total_cod_amount' => 'decimal:2',
            'shipping_component' => 'decimal:2',
            'merchandise_component' => 'decimal:2',
        ];
    }

    public function remittance(): BelongsTo
    {
        return $this->belongsTo(LogisticsAdminRemittance::class, 'logistics_admin_remittance_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function codRemittance(): BelongsTo
    {
        return $this->belongsTo(CodRemittance::class, 'cod_remittance_id');
    }
}

