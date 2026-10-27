<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourierProfile extends Model
{
    protected $fillable = [
        'user_id',
        'sorting_center_id',
        'is_online',
        'earnings_balance',
        'unremitted_cod_balance',
        'vehicle_type',
        'plate_number',
        'license_number',
        'license_expiry_date',
        'or_cr_path',
        'license_path',
        'vehicle_verification_status',
        'pending_vehicle_type',
        'pending_plate_number',
        'pending_or_cr_path',
    ];

    protected function casts(): array
    {
        return [
            'is_online' => 'boolean',
            'earnings_balance' => 'decimal:2',
            'unremitted_cod_balance' => 'decimal:2',
            'license_expiry_date' => 'date',
        ];
    }

    public function isOnline(): bool
    {
        return (bool) $this->is_online;
    }

    /**
     * Active jobs currently held by this courier.
     * Max allowed simultaneously is 3.
     */
    public function activeJobsCount(): int
    {
        return Order::where(function ($q) {
            $q->where('pickup_courier_id', $this->user_id)
                ->whereIn('status', ['assigned', 'ready_for_pickup']);
        })->orWhere(function ($q) {
            $q->where('delivery_courier_id', $this->user_id)
                ->whereIn('status', ['assigned_to_rider', 'out_for_delivery']);
        })->count();
    }

    public function canAcceptJobs(): bool
    {
        return $this->is_online
            && $this->user?->isActive()
            && $this->activeJobsCount() < 3;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sortingCenter()
    {
        return $this->belongsTo(User::class, 'sorting_center_id');
    }
}
