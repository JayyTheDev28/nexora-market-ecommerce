<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'first_name',
        'last_name',
        'middle_initial',
        'sex',
        'birthday',
        'age',
        'email',
        'password',
        'contact_number',
        'role',
        'approval_status',
        'disapproval_reason',
        'account_status',
        'status_reason',
        'valid_id_path',
        'avatar_path',
        'pending_email',
        'pending_email_otp',
        'pending_email_otp_expires_at',
        'pending_phone',
        'pending_phone_otp',
        'pending_phone_otp_expires_at',
        'email_verified_at',
        'email_verification_otp',
        'email_verification_otp_expires_at',
        'activation_token',
        'activation_token_expires_at',
        'admin_permissions',
        'two_factor_enabled',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'email_verification_otp_expires_at' => 'datetime',
            'pending_email_otp_expires_at' => 'datetime',
            'pending_phone_otp_expires_at' => 'datetime',
            'activation_token_expires_at' => 'datetime',
            'birthday' => 'date',
            'password' => 'hashed',
            'admin_permissions' => 'array',
            'two_factor_enabled' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function setRoleAttribute($value): void
    {
        $this->attributes['role'] = match ($value) {
            'sorting_center' => 'logistics',
            default => $value,
        };
    }

    public function getAvatarUrlAttribute(): string
    {
        if ($this->avatar_path) {
            return asset('storage/' . $this->avatar_path);
        }

        return 'https://ui-avatars.com/api/?name=' . urlencode($this->full_name) . '&background=0058be&color=ffffff&bold=true';
    }

    /**
     * Generate a new 6-digit numeric OTP and set 15-minute expiration for pending email change.
     */
    public function generatePendingEmailOtp(string $newEmail): string
    {
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $this->forceFill([
            'pending_email' => $newEmail,
            'pending_email_otp' => $otp,
            'pending_email_otp_expires_at' => now()->addMinutes(15),
        ])->save();

        return $otp;
    }

    /**
     * Generate a new 6-digit numeric OTP and set 15-minute expiration for pending phone change.
     */
    public function generatePendingPhoneOtp(string $newPhone): string
    {
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $this->forceFill([
            'pending_phone' => $newPhone,
            'pending_phone_otp' => $otp,
            'pending_phone_otp_expires_at' => now()->addMinutes(15),
        ])->save();

        return $otp;
    }

    /**
     * Generate a new 6-digit numeric OTP and set 15-minute expiration.
     */
    public function generateEmailVerificationOtp(): string
    {
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $this->forceFill([
            'email_verification_otp' => $otp,
            'email_verification_otp_expires_at' => now()->addMinutes(15),
        ])->save();

        return $otp;
    }

    /**
     * Send 6-pin OTP email to user.
     */
    public function sendEmailVerificationOtp(bool $force = false): string
    {
        // Prevent duplicate generation if an active OTP was generated less than 60 seconds ago (unless forced)
        if (! $force && $this->email_verification_otp && $this->email_verification_otp_expires_at && $this->email_verification_otp_expires_at->isFuture()) {
            $generatedAt = $this->email_verification_otp_expires_at->copy()->subMinutes(15);
            if ($generatedAt->diffInSeconds(now()) < 60) {
                return $this->email_verification_otp;
            }
        }

        $otp = $this->generateEmailVerificationOtp();

        try {
            $this->notify(new \App\Notifications\EmailVerificationOtpNotification($otp));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Failed to send OTP email: " . $e->getMessage());
        }

        return $otp;
    }

    /**
     * Override standard Laravel notification to send 6-pin OTP instead of a link.
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->sendEmailVerificationOtp();
    }

    public function getFullNameAttribute(): string
    {
        $middle = $this->middle_initial ? " {$this->middle_initial}." : '';
        return "{$this->first_name}{$middle} {$this->last_name}";
    }

    public function isApproved(): bool
    {
        return $this->approval_status === 'approved';
    }

    public function isPending(): bool
    {
        return $this->approval_status === 'pending';
    }

    public function isDisapproved(): bool
    {
        return $this->approval_status === 'disapproved';
    }

    // --- Ongoing account status (admin can suspend/deactivate after approval) ---
    public function isActive(): bool
    {
        return $this->account_status === 'active';
    }

    public function isSuspended(): bool
    {
        return $this->account_status === 'suspended';
    }

    public function isDeactivated(): bool
    {
        return $this->account_status === 'deactivated';
    }

    public function isPendingActivation(): bool
    {
        return $this->account_status === 'pending_activation';
    }

    /**
     * Whether this account is cleared to actually use the platform:
     * verified, approved, and not suspended/deactivated.
     */
    public function canAccessPlatform(): bool
    {
        return $this->hasVerifiedEmail() && $this->isApproved() && $this->isActive();
    }

    // --- Role checks ---
    public function isBuyer(): bool { return $this->role === 'buyer'; }
    public function isSeller(): bool { return $this->role === 'seller'; }
    public function isRider(): bool { return $this->role === 'rider'; }
    public function isCourier(): bool { return $this->role === 'courier'; }
    public function isMobileDeliveryPersonnel(): bool { return $this->isRider() || $this->isCourier(); }
    public function isLogistics(): bool { return $this->role === 'logistics' || $this->role === 'sorting_center'; }
    public function isSortingCenter(): bool { return $this->isLogistics(); }
    public function isAdmin(): bool { return $this->role === 'admin'; }

    /**
     * Resolve the owning logistics company (User ID of the logistics partner account).
     */
    public function logisticsCompanyId(): ?int
    {
        if ($this->isLogistics()) {
            return (int) $this->id;
        }

        if ($this->isMobileDeliveryPersonnel()) {
            $companyId = $this->courierProfile?->logistics_company_id;
            return $companyId ? (int) $companyId : null;
        }

        return null;
    }

    public function address()
    {
        return $this->hasOne(Address::class);
    }

    public function defaultAddress()
    {
        return $this->hasOne(Address::class)->where('is_default', true);
    }

    public function addresses()
    {
        return $this->hasMany(Address::class);
    }

    public function wishlists()
    {
        return $this->hasMany(Wishlist::class);
    }

    public function wishlistProducts()
    {
        return $this->belongsToMany(Product::class, 'wishlists');
    }

    public function followedStores()
    {
        return $this->belongsToMany(User::class, 'store_follows', 'user_id', 'seller_id');
    }

    public function storeFollowers()
    {
        return $this->belongsToMany(User::class, 'store_follows', 'seller_id', 'user_id');
    }

    public function claimedVouchers()
    {
        return $this->hasMany(UserVoucher::class);
    }

    public function vouchers()
    {
        return $this->belongsToMany(Voucher::class, 'user_vouchers')->withPivot('claimed_at', 'used_at', 'order_id');
    }

    public function sellerProfile()
    {
        return $this->hasOne(SellerProfile::class);
    }

    public function courierProfile()
    {
        return $this->hasOne(CourierProfile::class);
    }

    public function riderProfile()
    {
        return $this->hasOne(CourierProfile::class);
    }

    public function sortingCenterProfile()
    {
        return $this->hasOne(SortingCenterProfile::class);
    }

    public function logisticsProfile()
    {
        return $this->hasOne(LogisticsProfile::class);
    }

    public function hubs()
    {
        return $this->hasMany(Hub::class, 'logistics_company_id');
    }

    public function zones()
    {
        return $this->hasMany(Zone::class, 'logistics_company_id');
    }

    public function assignedZones()
    {
        return $this->belongsToMany(Zone::class, 'rider_zones', 'rider_id', 'zone_id')->withTimestamps();
    }

    public function getAssignedZoneAttribute(): ?Zone
    {
        if ($this->relationLoaded('assignedZones')) {
            return $this->assignedZones->first();
        }

        return $this->assignedZones()->with(['hub', 'barangays'])->first();
    }

    public function sentTransfers()
    {
        return $this->hasMany(HubTransfer::class, 'sender_logistics_id');
    }

    public function receivedTransfers()
    {
        return $this->hasMany(HubTransfer::class, 'receiver_logistics_id');
    }

    public function assignedManifests()
    {
        return $this->hasMany(TransferManifest::class, 'assigned_courier_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'seller_id');
    }

    public function storeCategories()
    {
        return $this->hasMany(StoreCategory::class, 'seller_id');
    }

    public function payoutRequests()
    {
        return $this->hasMany(SellerPayoutRequest::class, 'seller_id');
    }

    public function sellerDisputes()
    {
        return $this->hasManyThrough(Dispute::class, Order::class, 'seller_id', 'order_id');
    }

    public function allowedCategories()
    {
        return $this->belongsToMany(Category::class, 'category_seller', 'seller_id', 'category_id')->withTimestamps();
    }

    public function getEffectiveSellerCategories()
    {
        $cats = $this->allowedCategories;
        if ($cats->isNotEmpty()) {
            return $cats;
        }

        if ($this->sellerProfile && $this->sellerProfile->category) {
            $matched = Category::where('slug', $this->sellerProfile->category)
                ->orWhere('name', $this->sellerProfile->category)
                ->get();
            if ($matched->isNotEmpty()) {
                return $matched;
            }
        }

        return Category::orderBy('name')->get();
    }

    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'buyer_id');
    }

    public function deliveryAreas()
    {
        return $this->hasMany(DeliveryArea::class, 'courier_id');
    }

    public function managedDeliveryAreas()
    {
        return $this->hasMany(DeliveryArea::class, 'sorting_center_id');
    }

    public function activeDeliveries()
    {
        return $this->hasMany(Order::class, 'delivery_courier_id')
            ->whereIn('status', ['assigned_to_rider', 'out_for_delivery']);
    }

    public function completedDeliveries()
    {
        return $this->hasMany(Order::class, 'delivery_courier_id')
            ->where('status', 'delivered');
    }

    public function jobOffers()
    {
        return $this->hasMany(JobOffer::class, 'courier_id');
    }

    public function riderEarnings()
    {
        return $this->hasMany(RiderEarning::class, 'courier_id');
    }

    public function codLedgerEntries()
    {
        return $this->hasMany(CodLedgerEntry::class, 'courier_id');
    }

    public function codRemittances()
    {
        return $this->hasMany(CodRemittance::class, 'courier_id');
    }

    public function logisticsAdminRemittances()
    {
        return $this->hasMany(LogisticsAdminRemittance::class, 'logistics_id');
    }

    public function sellerSettlementAllocations()
    {
        return $this->hasMany(SellerSettlementAllocation::class, 'seller_id');
    }

    /**
     * Where this user lands after logging in, based on their role.
     */
    public function homeRoute(): string
    {
        return match ($this->role) {
            'buyer' => '/catalog',
            'seller' => '/seller/dashboard',
            'logistics', 'sorting_center' => '/logistics/dashboard',
            'rider' => '/rider/dashboard',
            'courier' => '/courier/dashboard',
            'admin' => '/admin/dashboard',
            default => '/',
        };
    }

    /**
     * Human-readable role label for display.
     */
    public function roleLabel(): string
    {
        return match ($this->role) {
            'buyer' => 'Buyer',
            'seller' => 'Seller',
            'logistics', 'sorting_center' => 'Logistics Partner',
            'rider' => 'Rider',
            'courier' => 'Transfer Courier',
            'admin' => 'Administrator',
            default => 'User',
        };
    }

    public function accountRestrictions()
    {
        return $this->hasMany(AccountRestriction::class)->latest();
    }

    public function sentTransferManifests()
    {
        return $this->hasMany(TransferManifest::class, 'sender_logistics_id');
    }

    public function hasRestriction(string $type): bool
    {
        return $this->accountRestrictions()
            ->where('restriction_type', $type)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    public function hasAdminPermission(string $permission): bool
    {
        if (! $this->isAdmin()) {
            return false;
        }
        $perms = $this->admin_permissions;
        // Null/empty permissions preserves full super-admin compatibility for single-admin setups
        if (empty($perms)) {
            return true;
        }
        return in_array('*', $perms, true) || in_array($permission, $perms, true);
    }
}
