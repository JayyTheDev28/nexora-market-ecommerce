<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SafetyReport extends Model
{
    public const STATUSES = [
        'new',
        'under_investigation',
        'awaiting_evidence',
        'resolved',
        'rejected',
        'escalated',
        'closed',
    ];

    public const PRIORITIES = ['low', 'medium', 'high', 'critical'];

    public const CATEGORIES = [
        'fraud_scam' => 'Fraud or suspected scam',
        'fake_misleading_product' => 'Fake or misleading products',
        'counterfeit_product' => 'Counterfeit products',
        'harassment_abuse' => 'Harassment or abusive messages',
        'suspicious_account' => 'Suspicious account activity',
        'fake_reviews' => 'Fake reviews',
        'seller_misconduct' => 'Seller misconduct',
        'buyer_misconduct' => 'Buyer misconduct',
        'rider_misconduct' => 'Rider misconduct',
        'logistics_misconduct' => 'Logistics misconduct',
        'parcel_theft_tampering' => 'Parcel theft or tampering',
        'payment_remittance_dispute' => 'Payment or remittance disputes',
        'other_violation' => 'Other policy violations',
    ];

    protected $fillable = [
        'reference_number',
        'reporter_id',
        'reporter_role',
        'category',
        'subject_type',
        'subject_id',
        'reported_user_id',
        'order_id',
        'product_id',
        'parcel_incident_id',
        'dispute_id',
        'title',
        'description',
        'evidence_paths',
        'status',
        'priority',
        'assigned_admin_id',
        'resolution_summary',
        'closed_reason',
        'resolved_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'evidence_paths' => 'array',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function reportedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_user_id');
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_admin_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function parcelIncident(): BelongsTo
    {
        return $this->belongsTo(ParcelIncident::class);
    }

    public function dispute(): BelongsTo
    {
        return $this->belongsTo(Dispute::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(SafetyReportNote::class)->latest();
    }

    public function publicNotes(): HasMany
    {
        return $this->hasMany(SafetyReportNote::class)->where('is_internal', false)->latest();
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? ucwords(str_replace('_', ' ', $this->category));
    }
}
