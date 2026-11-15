<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogisticsExpense extends Model
{
    public const CATEGORIES = [
        'fuel' => 'Fuel & Fleet Energy',
        'vehicle_maintenance' => 'Vehicle Maintenance',
        'hub_rent' => 'Hub Rent & Lease',
        'utilities' => 'Utilities & Internet',
        'packaging' => 'Packaging & Waybills',
        'staff_wages' => 'Hub Staff Wages',
        'tolls' => 'Expressway & Toll Fees',
        'other' => 'Other Operating Expense',
    ];

    protected $fillable = [
        'expense_number',
        'logistics_company_id',
        'hub_id',
        'manifest_id',
        'category',
        'description',
        'amount',
        'expense_date',
        'reference_number',
        'receipt_path',
        'recorded_by',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expense_date' => 'date',
        ];
    }

    public function logisticsCompany(): BelongsTo
    {
        return $this->belongsTo(User::class, 'logistics_company_id');
    }

    public function hub(): BelongsTo
    {
        return $this->belongsTo(Hub::class);
    }

    public function manifest(): BelongsTo
    {
        return $this->belongsTo(TransferManifest::class, 'manifest_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
