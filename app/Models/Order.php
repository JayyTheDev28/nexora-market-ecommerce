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
        'handling_status',
        'payment_method',
        'payment_status',
        'payment_expires_at',
        'subtotal',
        'gross_merchandise_subtotal',
        'discount',
        'store_voucher_discount',
        'platform_voucher_discount',
        'shipping_voucher_discount',
        'platform_shipping_subsidy',
        'shipping_fee',
        'base_shipping_fee',
        'shipping_weight',
        'shipping_voucher_id',
        'shipping_voucher_funder',
        'shipping_rate_policy_id',
        'shipping_route_type',
        'shipping_quote_breakdown',
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
        'rerouted_from_hub_id',
        'reroute_count',
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
            'gross_merchandise_subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'store_voucher_discount' => 'decimal:2',
            'platform_voucher_discount' => 'decimal:2',
            'shipping_voucher_discount' => 'decimal:2',
            'platform_shipping_subsidy' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'base_shipping_fee' => 'decimal:2',
            'shipping_weight' => 'decimal:2',
            'shipping_quote_breakdown' => 'array',
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

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            if (trim((string) ($order->tracking_number ?? '')) === '') {
                $order->tracking_number = \App\Services\OrderLogisticsService::generateUniqueTrackingNumber();
            }
        });
    }

    /**
     * Resolve a parcel unambiguously by canonical tracking number first,
     * falling back to unique legacy order_number only when unmatched.
     */
    /**
     * Normalize barcode/manual scan text (strip USB scanner CR/LF/control chars & whitespace, uppercase).
     */
    public static function normalizeScanIdentifier(string $identifier): string
    {
        $cleaned = preg_replace('/[\x00-\x1F\x7F\s]+/', '', trim($identifier)) ?? '';

        return strtoupper($cleaned);
    }

    public static function findByScanIdentifier(string $identifier): ?self
    {
        $normalized = static::normalizeScanIdentifier($identifier);
        if ($normalized === '') {
            return null;
        }

        $byTracking = static::where('tracking_number', $normalized)->first()
            ?? static::whereRaw('UPPER(tracking_number) = ?', [$normalized])->first();
        if ($byTracking) {
            return $byTracking;
        }

        $byOrderNumber = static::where('order_number', $normalized)
            ->orWhereRaw('UPPER(order_number) = ?', [$normalized])
            ->limit(2)
            ->get();

        return $byOrderNumber->count() === 1 ? $byOrderNumber->first() : null;
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
        return $this->belongsTo(Address::class)->withTrashed();
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

    public function reroutedFromHub()
    {
        return $this->belongsTo(Hub::class, 'rerouted_from_hub_id');
    }

    public function manifestItems()
    {
        return $this->hasMany(TransferManifestItem::class);
    }

    public function incidents()
    {
        return $this->hasMany(ParcelIncident::class)->latest('reported_at')->latest('id');
    }

    public function unresolvedIncidents()
    {
        return $this->hasMany(ParcelIncident::class)
            ->whereIn('investigation_status', ParcelIncident::UNRESOLVED_STATUSES)
            ->latest('reported_at');
    }

    public function hasUnresolvedHandlingRestrictions(): bool
    {
        if (in_array($this->handling_status, [
            ParcelIncident::RESTRICTION_QUARANTINED,
            ParcelIncident::RESTRICTION_HOLD_DISPATCH,
            ParcelIncident::RESTRICTION_RETURN_TO_SENDER,
            ParcelIncident::RESTRICTION_REROUTE_REQUIRED,
            ParcelIncident::RESTRICTION_TERMINATED_LOST,
            ParcelIncident::RESTRICTION_TERMINATED_DAMAGED,
        ], true)) {
            return true;
        }

        if ($this->relationLoaded('unresolvedIncidents') && $this->unresolvedIncidents->isNotEmpty()) {
            return true;
        }

        return ParcelIncident::where('order_id', $this->id)
            ->where(function ($q) {
                $q->whereIn('investigation_status', ParcelIncident::UNRESOLVED_STATUSES)
                    ->orWhereIn('disposition', [
                        ParcelIncident::DISPOSITION_ACCEPTED_INTO_QUARANTINE,
                        ParcelIncident::DISPOSITION_RETURNED_TO_SENDER,
                        ParcelIncident::DISPOSITION_DECLARED_LOST,
                        ParcelIncident::DISPOSITION_DAMAGED_BEYOND_DELIVERY,
                        ParcelIncident::DISPOSITION_ESCALATED_TO_CLAIM,
                    ]);
            })
            ->exists();
    }

    public function isQuarantined(): bool
    {
        return $this->handling_status === ParcelIncident::RESTRICTION_QUARANTINED
            || ParcelIncident::where('order_id', $this->id)->quarantined()->whereIn('investigation_status', ParcelIncident::UNRESOLVED_STATUSES)->exists();
    }

    public function hasFinancialHold(): bool
    {
        return ParcelIncident::where('order_id', $this->id)
            ->whereIn('financial_hold_status', [
                ParcelIncident::FIN_HOLD_ON_HOLD,
                ParcelIncident::FIN_HOLD_CLAIM_ESCALATED,
                ParcelIncident::FIN_HOLD_LOSS_WRITTEN_OFF,
            ])
            ->exists();
    }

    public function hasUnresolvedMissingIncident(): bool
    {
        return ParcelIncident::where('order_id', $this->id)
            ->where('incident_type', ParcelIncident::TYPE_MISSING)
            ->whereIn('investigation_status', ParcelIncident::UNRESOLVED_STATUSES)
            ->exists();
    }

    public function hasBlockingFlagHistoryOrIncidents(): bool
    {
        if ($this->hasUnresolvedHandlingRestrictions()) {
            return true;
        }

        // Preserve blocking behavior for legacy/direct 'ISSUE FLAGGED:' history entries when no resolved incident covers them
        $hasIncidents = ParcelIncident::where('order_id', $this->id)->exists();
        if (! $hasIncidents) {
            return OrderStatusHistory::where('order_id', $this->id)
                ->where('note', 'like', 'ISSUE FLAGGED:%')
                ->exists();
        }

        return false;
    }

    public function requiresHubTransfer(): bool
    {
        $activeHubId = $this->current_hub_id ?: $this->origin_hub_id;

        return !empty($activeHubId) &&
               !empty($this->destination_hub_id) &&
               (int) $activeHubId !== (int) $this->destination_hub_id;
    }

    public function isInternalTransfer(): bool
    {
        if (! $this->requiresHubTransfer()) {
            return false;
        }

        $originCompanyId = $this->currentHub?->logistics_company_id
            ?? $this->originHub?->logistics_company_id
            ?? $this->current_custody_logistics_id
            ?? $this->pickup_logistics_id;
        $destCompanyId = $this->destinationHub?->logistics_company_id
            ?? $this->delivery_logistics_id;

        return !empty($originCompanyId) &&
               !empty($destCompanyId) &&
               (int) $originCompanyId === (int) $destCompanyId;
    }

    public function hasValidTransferRouting(): bool
    {
        return !empty($this->current_hub_id)
            && !empty($this->destination_hub_id)
            && (int) $this->current_hub_id !== (int) $this->destination_hub_id
            && $this->currentHub !== null
            && $this->destinationHub !== null
            && !empty($this->currentHub->logistics_company_id)
            && !empty($this->destinationHub->logistics_company_id);
    }

    public function getFormattedDestinationAttribute(): string
    {
        $snapshot = is_array($this->address_snapshot) ? $this->address_snapshot : [];
        $barangay = trim((string) ($this->address?->barangay_name ?? $snapshot['barangay_name'] ?? $this->shipping_barangay ?? ''));
        $city = trim((string) ($this->address?->municipality_name ?? $snapshot['municipality_name'] ?? $this->shipping_city ?? ''));
        $province = trim((string) ($this->address?->province_name ?? $snapshot['province_name'] ?? $this->shipping_province ?? ''));

        $parts = array_values(array_filter([$barangay, $city, $province], fn ($v) => $v !== ''));

        return !empty($parts)
            ? implode(', ', $parts)
            : 'Destination Unavailable — Requires Review';
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

    public function remittanceItem()
    {
        return $this->hasOne(LogisticsRemittanceItem::class, 'order_id');
    }

    public function settlementAllocation()
    {
        return $this->hasOne(SellerSettlementAllocation::class, 'order_id');
    }

    public function codCollection()
    {
        return $this->hasOne(CodCollection::class, 'order_id');
    }

    public function interCompanyShippingAllocation()
    {
        return $this->hasOne(InterCompanyShippingAllocation::class, 'order_id');
    }

    public function merchandiseAmount(): float
    {
        if ($this->gross_merchandise_subtotal !== null) {
            return max(
                0.00,
                round(
                    (float) $this->gross_merchandise_subtotal
                    - (float) ($this->store_voucher_discount ?? 0)
                    - (float) ($this->platform_voucher_discount ?? 0),
                    2
                )
            );
        }

        // Legacy fallback: COD total collected minus buyer-paid shipping fee preserves true merchandise
        // even when a shipping voucher was included inside orders.discount.
        if ($this->total !== null && (float) $this->total >= (float) $this->shipping_fee) {
            return max(0.00, round((float) $this->total - (float) $this->shipping_fee, 2));
        }

        return max(0.00, round((float) $this->subtotal - (float) $this->discount, 2));
    }

    public function shippingFeeAmount(): float
    {
        return (float) $this->shipping_fee;
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
        return $this->hasMany(OrderStatusHistory::class)
            ->orderByDesc('changed_at')
            ->orderByDesc('id');
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
