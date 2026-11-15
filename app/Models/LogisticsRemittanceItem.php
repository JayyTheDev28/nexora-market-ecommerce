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
        'cod_collection_id',
        'total_cod_amount',
        'shipping_component',
        'merchandise_component',
        'seller_discount_amount',
        'platform_discount_amount',
        'platform_shipping_subsidy',
    ];

    protected function casts(): array
    {
        return [
            'total_cod_amount' => 'decimal:2',
            'shipping_component' => 'decimal:2',
            'merchandise_component' => 'decimal:2',
            'seller_discount_amount' => 'decimal:2',
            'platform_discount_amount' => 'decimal:2',
            'platform_shipping_subsidy' => 'decimal:2',
        ];
    }

    public function codCollection(): BelongsTo
    {
        return $this->belongsTo(CodCollection::class, 'cod_collection_id');
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

