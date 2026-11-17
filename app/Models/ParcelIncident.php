<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class ParcelIncident extends Model
{
    public const TYPE_DAMAGE = 'damage';
    public const TYPE_MISSING = 'missing';
    public const TYPE_DISCREPANCY = 'discrepancy';
    public const TYPE_WRONG_HUB = 'wrong_hub';

    public const STAGE_MANIFEST_LOADING = 'manifest_loading';
    public const STAGE_COURIER_TRANSIT = 'courier_transit';
    public const STAGE_MANIFEST_RECEIVING = 'manifest_receiving';
    public const STAGE_HUB_RECEIVING = 'hub_receiving';
    public const STAGE_POST_RECEIPT = 'post_receipt';

    // Investigation lifecycle statuses (Phase 2)
    public const STATUS_OPEN = 'open';
    public const STATUS_UNDER_INVESTIGATION = 'under_investigation';
    public const STATUS_AWAITING_EVIDENCE = 'awaiting_evidence';
    public const STATUS_FOUND = 'found';
    public const STATUS_AWAITING_DISPOSITION = 'awaiting_disposition';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_CLOSED = 'closed';

    // Explicit Dispositions
    public const DISPOSITION_RECOVERED_AND_RECEIVED = 'recovered_and_received';
    public const DISPOSITION_INSPECTED_AND_CLEARED = 'inspected_and_cleared';
    public const DISPOSITION_ACCEPTED_INTO_QUARANTINE = 'accepted_into_quarantine';
    public const DISPOSITION_RETURNED_TO_SENDER = 'returned_to_sender';
    public const DISPOSITION_DECLARED_LOST = 'declared_lost';
    public const DISPOSITION_DAMAGED_BEYOND_DELIVERY = 'damaged_beyond_delivery';
    public const DISPOSITION_RESOLVED_BY_CORRECTION = 'resolved_by_authorized_correction';
    public const DISPOSITION_ESCALATED_TO_CLAIM = 'escalated_to_claim_refund';

    // Handling restrictions
    public const RESTRICTION_NONE = 'none';
    public const RESTRICTION_HOLD_DISPATCH = 'hold_dispatch';
    public const RESTRICTION_QUARANTINED = 'quarantined';
    public const RESTRICTION_RETURN_TO_SENDER = 'return_to_sender';
    public const RESTRICTION_REROUTE_REQUIRED = 'reroute_required';
    public const RESTRICTION_TERMINATED_LOST = 'terminated_lost';
    public const RESTRICTION_TERMINATED_DAMAGED = 'terminated_damaged';
    public const RESTRICTION_CLEARED = 'cleared';

    // Financial hold statuses
    public const FIN_HOLD_NONE = 'none';
    public const FIN_HOLD_ON_HOLD = 'on_hold';
    public const FIN_HOLD_CLAIM_ESCALATED = 'claim_escalated';
    public const FIN_HOLD_REFUND_AUTHORIZED = 'refund_authorized';
    public const FIN_HOLD_LOSS_WRITTEN_OFF = 'loss_written_off';
    public const FIN_HOLD_CLEARED = 'cleared';

    public const ALL_TYPES = [
        self::TYPE_DAMAGE,
        self::TYPE_MISSING,
        self::TYPE_DISCREPANCY,
        self::TYPE_WRONG_HUB,
    ];

    public const ALL_STAGES = [
        self::STAGE_MANIFEST_LOADING,
        self::STAGE_COURIER_TRANSIT,
        self::STAGE_MANIFEST_RECEIVING,
        self::STAGE_HUB_RECEIVING,
        self::STAGE_POST_RECEIPT,
    ];

    public const ALL_STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_UNDER_INVESTIGATION,
        self::STATUS_AWAITING_EVIDENCE,
        self::STATUS_FOUND,
        self::STATUS_AWAITING_DISPOSITION,
        self::STATUS_RESOLVED,
        self::STATUS_CLOSED,
    ];

    public const UNRESOLVED_STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_UNDER_INVESTIGATION,
        self::STATUS_AWAITING_EVIDENCE,
        self::STATUS_FOUND,
        self::STATUS_AWAITING_DISPOSITION,
    ];

    public const TERMINAL_STATUSES = [
        self::STATUS_RESOLVED,
        self::STATUS_CLOSED,
    ];

    public const ALL_DISPOSITIONS = [
        self::DISPOSITION_RECOVERED_AND_RECEIVED => 'Recovered and Received',
        self::DISPOSITION_INSPECTED_AND_CLEARED => 'Inspected and Cleared for Delivery',
        self::DISPOSITION_ACCEPTED_INTO_QUARANTINE => 'Accepted Into Quarantine',
        self::DISPOSITION_RETURNED_TO_SENDER => 'Returned to Sender',
        self::DISPOSITION_DECLARED_LOST => 'Declared Permanently Lost',
        self::DISPOSITION_DAMAGED_BEYOND_DELIVERY => 'Damaged Beyond Delivery',
        self::DISPOSITION_RESOLVED_BY_CORRECTION => 'Resolved by Authorized Correction / Reroute',
        self::DISPOSITION_ESCALATED_TO_CLAIM => 'Escalated to Claim / Refund',
    ];

    public const FINAL_DISPOSITIONS = [
        self::DISPOSITION_RECOVERED_AND_RECEIVED,
        self::DISPOSITION_INSPECTED_AND_CLEARED,
        self::DISPOSITION_RETURNED_TO_SENDER,
        self::DISPOSITION_DECLARED_LOST,
        self::DISPOSITION_DAMAGED_BEYOND_DELIVERY,
        self::DISPOSITION_RESOLVED_BY_CORRECTION,
        self::DISPOSITION_ESCALATED_TO_CLAIM,
    ];

    public const DAMAGE_TYPES = [
        'crushed_packaging' => 'Crushed / Dented Packaging',
        'torn_open' => 'Torn / Opened Packaging',
        'water_damage' => 'Water / Moisture Damage',
        'broken_contents' => 'Broken / Rattling Contents',
        'tampered_seal' => 'Tampered Security Seal',
        'wet_or_stained' => 'Stained / Soiled Exterior',
        'partial_missing' => 'Missing Item / Partial Contents',
        'wrong_hub_misrouted' => 'Misrouted / Wrong Sorting Hub',
        'other' => 'Other Damage / Discrepancy',
    ];

    protected $fillable = [
        'incident_number',
        'order_id',
        'manifest_id',
        'manifest_item_id',
        'hub_id',
        'expected_hub_id',
        'actual_hub_id',
        'rerouted_to_hub_id',
        'logistics_company_id',
        'reported_by',
        'resolved_by',
        'assigned_to',
        'quarantined_by',
        'recovered_by',
        'closed_by',
        'tracking_number',
        'incident_type',
        'damage_type',
        'source_stage',
        'investigation_status',
        'disposition',
        'handling_restriction',
        'description',
        'evidence_path',
        'last_known_location',
        'last_known_event',
        'resolution_notes',
        'recovery_location',
        'recovery_evidence_path',
        'financial_hold_status',
        'reported_at',
        'quarantined_at',
        'found_at',
        'recovered_at',
        'due_at',
        'resolved_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'reported_at' => 'datetime',
            'quarantined_at' => 'datetime',
            'found_at' => 'datetime',
            'recovered_at' => 'datetime',
            'due_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function manifest(): BelongsTo
    {
        return $this->belongsTo(TransferManifest::class, 'manifest_id');
    }

    public function manifestItem(): BelongsTo
    {
        return $this->belongsTo(TransferManifestItem::class, 'manifest_item_id');
    }

    public function hub(): BelongsTo
    {
        return $this->belongsTo(Hub::class);
    }

    public function expectedHub(): BelongsTo
    {
        return $this->belongsTo(Hub::class, 'expected_hub_id');
    }

    public function actualHub(): BelongsTo
    {
        return $this->belongsTo(Hub::class, 'actual_hub_id');
    }

    public function reroutedToHub(): BelongsTo
    {
        return $this->belongsTo(Hub::class, 'rerouted_to_hub_id');
    }

    public function logisticsCompany(): BelongsTo
    {
        return $this->belongsTo(User::class, 'logistics_company_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function quarantinedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'quarantined_by');
    }

    public function recoveredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recovered_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ParcelIncidentEvent::class, 'incident_id')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');
    }

    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->whereIn('investigation_status', self::UNRESOLVED_STATUSES);
    }

    public function scopeQuarantined(Builder $query): Builder
    {
        return $query->where('handling_restriction', self::RESTRICTION_QUARANTINED);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereIn('investigation_status', self::UNRESOLVED_STATUSES)
            ->whereNotNull('due_at')
            ->where('due_at', '<', now());
    }

    public function isResolved(): bool
    {
        return in_array($this->investigation_status, self::TERMINAL_STATUSES, true);
    }

    public function isUnresolved(): bool
    {
        return ! $this->isResolved();
    }

    public function isQuarantined(): bool
    {
        return $this->handling_restriction === self::RESTRICTION_QUARANTINED
            || $this->disposition === self::DISPOSITION_ACCEPTED_INTO_QUARANTINE;
    }

    public function isOverdue(): bool
    {
        return $this->isUnresolved() && $this->due_at !== null && $this->due_at->isPast();
    }

    public function getDamageTypeLabelAttribute(): ?string
    {
        if (! $this->damage_type) {
            return null;
        }

        return self::DAMAGE_TYPES[$this->damage_type] ?? ucwords(str_replace('_', ' ', $this->damage_type));
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->investigation_status) {
            self::STATUS_OPEN => 'Open',
            self::STATUS_UNDER_INVESTIGATION => 'Under Investigation',
            self::STATUS_AWAITING_EVIDENCE => 'Awaiting Evidence',
            self::STATUS_FOUND => 'Located / Found',
            self::STATUS_AWAITING_DISPOSITION => 'Awaiting Disposition',
            self::STATUS_RESOLVED => 'Resolved',
            self::STATUS_CLOSED => 'Closed',
            default => ucwords(str_replace('_', ' ', (string) $this->investigation_status)),
        };
    }

    public function getDispositionLabelAttribute(): ?string
    {
        if (! $this->disposition) {
            return null;
        }

        return self::ALL_DISPOSITIONS[$this->disposition] ?? ucwords(str_replace('_', ' ', $this->disposition));
    }

    public function getEvidenceUrlAttribute(): ?string
    {
        if (! $this->evidence_path) {
            return null;
        }

        if (str_starts_with($this->evidence_path, 'http://') || str_starts_with($this->evidence_path, 'https://')) {
            return $this->evidence_path;
        }

        return Storage::url($this->evidence_path);
    }

    public function getRecoveryEvidenceUrlAttribute(): ?string
    {
        if (! $this->recovery_evidence_path) {
            return null;
        }

        if (str_starts_with($this->recovery_evidence_path, 'http://') || str_starts_with($this->recovery_evidence_path, 'https://')) {
            return $this->recovery_evidence_path;
        }

        return Storage::url($this->recovery_evidence_path);
    }

    /**
     * Sanitized, buyer-safe exception summary (Phase 7):
     * Never exposes internal notes, employee names, payouts, or liability fields.
     */
    public function toBuyerSafeArray(): array
    {
        $state = $this->resolveBuyerSafeState();

        return [
            'reference' => $this->incident_number,
            'state' => $state['code'],
            'badge' => $state['badge'],
            'message' => $state['message'],
            'reported_at' => $this->reported_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    protected function resolveBuyerSafeState(): array
    {
        if ($this->disposition === self::DISPOSITION_DECLARED_LOST) {
            return [
                'code' => 'declared_lost',
                'badge' => 'Parcel Declared Lost',
                'message' => 'Parcel Declared Lost — Support Reviewing Resolution.',
            ];
        }

        if ($this->disposition === self::DISPOSITION_RETURNED_TO_SENDER || $this->handling_restriction === self::RESTRICTION_RETURN_TO_SENDER) {
            return [
                'code' => 'return_to_sender',
                'badge' => 'Return to Sender Initiated',
                'message' => 'Return to Sender Initiated after hub inspection.',
            ];
        }

        if ($this->disposition === self::DISPOSITION_DAMAGED_BEYOND_DELIVERY || $this->disposition === self::DISPOSITION_ESCALATED_TO_CLAIM) {
            return [
                'code' => 'claim_review',
                'badge' => 'Delivery Delayed — Support Reviewing',
                'message' => 'Delivery Exception Logged — Support Reviewing Resolution.',
            ];
        }

        if (in_array($this->disposition, [self::DISPOSITION_RECOVERED_AND_RECEIVED, self::DISPOSITION_INSPECTED_AND_CLEARED, self::DISPOSITION_RESOLVED_BY_CORRECTION], true)
            || ($this->isResolved() && $this->handling_restriction === self::RESTRICTION_CLEARED)) {
            return [
                'code' => 'delivery_resuming',
                'badge' => 'Delivery Resuming',
                'message' => 'Parcel Cleared — Delivery Resuming.',
            ];
        }

        if ($this->investigation_status === self::STATUS_FOUND) {
            return [
                'code' => 'parcel_located',
                'badge' => 'Parcel Located',
                'message' => 'Parcel Located — preparing to resume transit.',
            ];
        }

        if ($this->isQuarantined()) {
            return [
                'code' => 'quarantined',
                'badge' => 'Delivery Delayed',
                'message' => 'Delivery Delayed — Parcel is undergoing quality inspection at sorting hub.',
            ];
        }

        if ($this->incident_type === self::TYPE_WRONG_HUB) {
            return [
                'code' => 'rerouting',
                'badge' => 'Delivery Delayed — Rerouting',
                'message' => 'Delivery Delayed — Parcel is being rerouted to the correct sorting hub.',
            ];
        }

        if ($this->incident_type === self::TYPE_MISSING) {
            return [
                'code' => 'under_investigation',
                'badge' => 'Parcel Under Investigation',
                'message' => 'Parcel Under Investigation — Logistics team is locating your shipment.',
            ];
        }

        return [
            'code' => 'delivery_delayed',
            'badge' => 'Delivery Delayed',
            'message' => 'Delivery Delayed — Parcel handling review is underway.',
        ];
    }
}
