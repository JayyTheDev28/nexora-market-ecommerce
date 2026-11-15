<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransferManifestItem extends Model
{
    use HasFactory;

    public const LOADING_PENDING = 'pending';
    public const LOADING_LOADED = 'loaded';
    public const LOADING_ISSUE = 'issue';

    public const RECEIVING_PENDING = 'pending';
    public const RECEIVING_RECEIVED = 'received';
    public const RECEIVING_MISSING = 'missing';
    public const RECEIVING_DAMAGED = 'damaged';
    public const RECEIVING_DISCREPANCY = 'discrepancy';

    protected $fillable = [
        'manifest_id',
        'hub_transfer_id',
        'order_id',
        'tracking_number',
        'loading_status',
        'loaded_at',
        'loaded_by',
        'receiving_status',
        'received_at',
        'received_by',
        'issue_notes',
    ];

    protected function casts(): array
    {
        return [
            'loaded_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function manifest(): BelongsTo
    {
        return $this->belongsTo(TransferManifest::class, 'manifest_id');
    }

    public function hubTransfer(): BelongsTo
    {
        return $this->belongsTo(HubTransfer::class, 'hub_transfer_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function loadedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'loaded_by');
    }

    public function receivedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(ParcelIncident::class, 'manifest_item_id')->latest('reported_at');
    }

    /**
     * Resolve the Parcel Condition badge metadata separating physical receiving status from incident condition.
     */
    public function getConditionSummaryAttribute(): array
    {
        $incidents = $this->relationLoaded('incidents')
            ? $this->incidents
            : $this->incidents()->get();

        $unresolved = $incidents->where('investigation_status', '!=', ParcelIncident::STATUS_RESOLVED);
        $openDamage = $unresolved->firstWhere('incident_type', ParcelIncident::TYPE_DAMAGE);
        $openMissing = $unresolved->firstWhere('incident_type', ParcelIncident::TYPE_MISSING);
        $openDiscrepancy = $unresolved->firstWhere('incident_type', ParcelIncident::TYPE_DISCREPANCY);

        $hasAnyDamage = $incidents->contains('incident_type', ParcelIncident::TYPE_DAMAGE)
            || $this->receiving_status === self::RECEIVING_DAMAGED;
        $hasAnyMissing = $incidents->contains('incident_type', ParcelIncident::TYPE_MISSING)
            || $this->receiving_status === self::RECEIVING_MISSING;
        $hasAnyDiscrepancy = $incidents->contains('incident_type', ParcelIncident::TYPE_DISCREPANCY)
            || $this->receiving_status === self::RECEIVING_DISCREPANCY;

        $underInvestigation = $unresolved->firstWhere('investigation_status', ParcelIncident::STATUS_UNDER_INVESTIGATION);
        $foundMissing = $unresolved->where('incident_type', ParcelIncident::TYPE_MISSING)
            ->firstWhere('investigation_status', ParcelIncident::STATUS_FOUND);

        if ($underInvestigation) {
            return [
                'key' => 'under_investigation',
                'label' => 'Under Investigation',
                'classes' => 'bg-amber-500/15 text-amber-800',
                'sublabel' => ucfirst($underInvestigation->incident_type),
                'has_open_damage' => (bool) $openDamage,
                'has_open_missing' => (bool) $openMissing,
                'has_damage_report' => $hasAnyDamage,
                'has_missing_report' => $hasAnyMissing,
                'has_found_missing' => (bool) $foundMissing,
                'has_unresolved' => true,
                'primary_incident' => $underInvestigation,
            ];
        }

        if ($foundMissing) {
            return [
                'key' => 'under_investigation',
                'label' => 'Under Investigation',
                'classes' => 'bg-amber-500/15 text-amber-800',
                'sublabel' => 'Found — Awaiting Verification',
                'has_open_damage' => (bool) $openDamage,
                'has_open_missing' => true,
                'has_damage_report' => $hasAnyDamage,
                'has_missing_report' => true,
                'has_found_missing' => true,
                'has_unresolved' => true,
                'primary_incident' => $foundMissing,
            ];
        }

        if ($openMissing || ($this->receiving_status === self::RECEIVING_MISSING && $incidents->isEmpty())) {
            return [
                'key' => 'missing',
                'label' => 'Missing',
                'classes' => 'bg-rose-600 text-white',
                'sublabel' => $openMissing?->source_stage === ParcelIncident::STAGE_POST_RECEIPT ? 'Lost After Receipt' : null,
                'has_open_damage' => (bool) $openDamage,
                'has_open_missing' => true,
                'has_damage_report' => $hasAnyDamage,
                'has_missing_report' => true,
                'has_found_missing' => false,
                'has_unresolved' => true,
                'primary_incident' => $openMissing,
            ];
        }

        if ($openDamage || ($this->receiving_status === self::RECEIVING_DAMAGED && $incidents->isEmpty())) {
            return [
                'key' => 'damage_reported',
                'label' => 'Damage Reported',
                'classes' => 'bg-rose-500/15 text-rose-700',
                'sublabel' => $openDamage?->source_stage === ParcelIncident::STAGE_POST_RECEIPT ? 'Post-Receipt' : null,
                'has_open_damage' => true,
                'has_open_missing' => false,
                'has_damage_report' => true,
                'has_missing_report' => $hasAnyMissing,
                'has_found_missing' => false,
                'has_unresolved' => true,
                'primary_incident' => $openDamage,
            ];
        }

        if ($openDiscrepancy || ($this->receiving_status === self::RECEIVING_DISCREPANCY && $incidents->isEmpty())) {
            return [
                'key' => 'under_investigation',
                'label' => 'Under Investigation',
                'classes' => 'bg-amber-500/15 text-amber-800',
                'sublabel' => 'Discrepancy',
                'has_open_damage' => false,
                'has_open_missing' => false,
                'has_damage_report' => $hasAnyDamage,
                'has_missing_report' => $hasAnyMissing,
                'has_found_missing' => false,
                'has_unresolved' => true,
                'primary_incident' => $openDiscrepancy,
            ];
        }

        if ($incidents->isNotEmpty() || $hasAnyDamage || $hasAnyMissing || $hasAnyDiscrepancy) {
            return [
                'key' => 'resolved',
                'label' => 'Resolved',
                'classes' => 'bg-teal-500/15 text-teal-700',
                'sublabel' => null,
                'has_open_damage' => false,
                'has_open_missing' => false,
                'has_damage_report' => $hasAnyDamage,
                'has_missing_report' => $hasAnyMissing,
                'has_found_missing' => false,
                'has_unresolved' => false,
                'primary_incident' => $incidents->first(),
            ];
        }

        return [
            'key' => 'good',
            'label' => 'Good',
            'classes' => 'bg-emerald-500/15 text-emerald-700',
            'sublabel' => null,
            'has_open_damage' => false,
            'has_open_missing' => false,
            'has_damage_report' => false,
            'has_missing_report' => false,
            'has_found_missing' => false,
            'has_unresolved' => false,
            'primary_incident' => null,
        ];
    }
}
