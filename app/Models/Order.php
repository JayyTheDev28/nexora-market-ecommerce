<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'order_number',
        'checkout_session_id',
        'tracking_number',
        'buyer_id',
        'seller_id',
        'address_id',
        'address_snapshot',
        'status',
        'payment_method',
        'payment_status',
        'payment_expires_at',
        'subtotal',
        'discount',
        'shipping_fee',
        'shipping_weight',
        'shipping_voucher_id',
        'store_voucher_id',
        'platform_voucher_id',
        'total',
        'pickup_courier_id',
        'delivery_courier_id',
        'pickup_rider_id',
        'delivery_rider_id',
        'sorting_center_id',
        'pickup_logistics_id',
        'delivery_logistics_id',
        'current_custody_logistics_id',
        'origin_hub_id',
        'destination_hub_id',
        'current_hub_id',
        'pickup_zone_id',
        'delivery_zone_id',
        'delivery_area_id',
        'placed_at',
        'delivered_at',
        'completed_at',
        'cancelled_at',
        'cancellation_reason',
        'delivery_otp',
        'delivery_otp_failed_attempts',
        'delivery_otp_locked_until',
    ];

    protected function casts(): array
    {
        return [
            'address_snapshot' => 'array',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'shipping_weight' => 'decimal:2',
            'total' => 'decimal:2',
            'placed_at' => 'datetime',
            'payment_expires_at' => 'datetime',
            'delivered_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'delivery_otp_locked_until' => 'datetime',
            'delivery_otp_failed_attempts' => 'integer',
        ];
    }

    public function getTotalAmountAttribute(): float
    {
        return (float) ($this->total ?? 0);
    }

    /**
     * Enforce COD as the exclusive payment method system-wide.
     */
    public function setPaymentMethodAttribute($value): void
    {
        $this->attributes['payment_method'] = 'cod';
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function address()
    {
        return $this->belongsTo(Address::class);
    }

    public function shippingVoucher()
    {
        return $this->belongsTo(Voucher::class, 'shipping_voucher_id');
    }

    public function storeVoucher()
    {
        return $this->belongsTo(Voucher::class, 'store_voucher_id');
    }

    public function platformVoucher()
    {
        return $this->belongsTo(Voucher::class, 'platform_voucher_id');
    }

    public function pickupCourier()
    {
        return $this->belongsTo(User::class, 'pickup_rider_id');
    }

    public function deliveryCourier()
    {
        return $this->belongsTo(User::class, 'delivery_rider_id');
    }

    public function pickupRider()
    {
        return $this->belongsTo(User::class, 'pickup_rider_id');
    }

    public function deliveryRider()
    {
        return $this->belongsTo(User::class, 'delivery_rider_id');
    }

    public function pickupLogistics()
    {
        return $this->belongsTo(User::class, 'pickup_logistics_id');
    }

    public function deliveryLogistics()
    {
        return $this->belongsTo(User::class, 'delivery_logistics_id');
    }

    public function currentCustodyLogistics()
    {
        return $this->belongsTo(User::class, 'current_custody_logistics_id');
    }

    public function originHub()
    {
        return $this->belongsTo(Hub::class, 'origin_hub_id');
    }

    public function destinationHub()
    {
        return $this->belongsTo(Hub::class, 'destination_hub_id');
    }

    public function currentHub()
    {
        return $this->belongsTo(Hub::class, 'current_hub_id');
    }

    public function requiresHubTransfer(): bool
    {
        return !empty($this->origin_hub_id) &&
               !empty($this->destination_hub_id) &&
               (int) $this->origin_hub_id !== (int) $this->destination_hub_id;
    }

    public function isInternalTransfer(): bool
    {
        return $this->requiresHubTransfer() &&
               (int) $this->pickup_logistics_id === (int) $this->delivery_logistics_id;
    }

    public function isSameHub(): bool
    {
        return !empty($this->origin_hub_id) &&
               !empty($this->destination_hub_id) &&
               (int) $this->origin_hub_id === (int) $this->destination_hub_id;
    }

    public function pickupZone()
    {
        return $this->belongsTo(Zone::class, 'pickup_zone_id');
    }

    public function deliveryZone()
    {
        return $this->belongsTo(Zone::class, 'delivery_zone_id');
    }

    public function transfers()
    {
        return $this->hasMany(HubTransfer::class, 'order_id');
    }

    public function latestTransfer()
    {
        return $this->hasOne(HubTransfer::class, 'order_id')->latestOfMany();
    }

    public function deliveryArea()
    {
        return $this->belongsTo(DeliveryArea::class);
    }

    public function deliveryAttempts()
    {
        return $this->hasMany(DeliveryAttempt::class);
    }

    public function commissions()
    {
        return $this->hasMany(Commission::class);
    }

    public function sortingCenter()
    {
        return $this->belongsTo(User::class, 'sorting_center_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistory()
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('changed_at');
    }

    public function dispute()
    {
        return $this->hasOne(Dispute::class);
    }

    public function review()
    {
        return $this->hasOne(Review::class);
    }

    public function proofOfDelivery()
    {
        return $this->hasOne(ProofOfDelivery::class);
    }

    public function jobOffers()
    {
        return $this->hasMany(JobOffer::class);
    }

    public function riderEarnings()
    {
        return $this->hasMany(RiderEarning::class);
    }

    public function codLedgerEntries()
    {
        return $this->hasMany(CodLedgerEntry::class);
    }

    /**
     * Generate a fresh 6-digit numeric OTP for delivery verification.
     */
    public function generateDeliveryOtp(): string
    {
        $otp = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $this->update([
            'delivery_otp' => $otp,
            'delivery_otp_failed_attempts' => 0,
            'delivery_otp_locked_until' => null,
        ]);

        return $otp;
    }

    /**
     * Check if delivery OTP verification is currently locked due to brute-force attempts.
     */
    public function isDeliveryOtpLocked(): bool
    {
        return $this->delivery_otp_locked_until !== null && now()->lessThan($this->delivery_otp_locked_until);
    }

    /**
     * Record a failed OTP attempt with 5-attempt / 15-minute brute-force lockout.
     */
    public function recordFailedOtpAttempt(): int
    {
        $attempts = $this->delivery_otp_failed_attempts + 1;
        $updates = ['delivery_otp_failed_attempts' => $attempts];

        if ($attempts >= 5) {
            $updates['delivery_otp_locked_until'] = now()->addMinutes(15);
        }

        $this->update($updates);

        return $attempts;
    }

    /**
     * Verify delivery OTP.
     */
    public function verifyDeliveryOtp(string $otp): bool
    {
        if ($this->isDeliveryOtpLocked()) {
            return false;
        }

        if (trim($this->delivery_otp) === trim($otp)) {
            $this->update([
                'delivery_otp_failed_attempts' => 0,
                'delivery_otp_locked_until' => null,
            ]);
            return true;
        }

        $this->recordFailedOtpAttempt();
        return false;
    }

    public function getPrimarySellerAttribute(): ?User
    {
        if ($this->seller) {
            return $this->seller;
        }

        return $this->items->first()?->product?->seller;
    }

    public function getSellerAddressAttribute(): ?Address
    {
        return $this->primary_seller?->address;
    }

    /**
     * Pre-shipping cancellation guard:
     * Allowed only when status is 'placed' or 'confirmed' before logistics preparation.
     */
    public function canBeCancelledByBuyer(): bool
    {
        return in_array($this->status, ['placed', 'confirmed'], true);
    }

    /**
     * Seller cancellation guard:
     * Allowed when status is 'placed', 'confirmed', or 'preparing' before parcel handover.
     */
    public function canBeCancelledBySeller(): bool
    {
        return in_array($this->status, ['placed', 'confirmed', 'preparing'], true);
    }

    /**
     * 7-day return request window check.
     */
    public function canBeReturned(): bool
    {
        if ($this->status !== 'delivered' && $this->status !== 'completed') {
            return false;
        }

        if ($this->dispute && in_array($this->dispute->status, ['open', 'under_review', 'resolved'], true)) {
            return false;
        }

        $deliveredTime = $this->delivered_at ?: $this->updated_at;
        return $deliveredTime && $deliveredTime->greaterThanOrEqualTo(now()->subDays(7));
    }

    /**
     * 30-day review window check.
     */
    public function canBeReviewed(): bool
    {
        if ($this->status !== 'completed') {
            return false;
        }

        $completedTime = $this->completed_at ?: $this->updated_at;
        return $completedTime && $completedTime->greaterThanOrEqualTo(now()->subDays(30));
    }
}
