<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransferManifestItem extends Model
{
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
        'courier_issue_type',
        'courier_issue_reported_at',
        'courier_issue_evidence_path',
        'courier_issue_location',
        'disposition',
    ];

    protected function casts(): array
    {
        return [
            'loaded_at' => 'datetime',
            'received_at' => 'datetime',
            'courier_issue_reported_at' => 'datetime',
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
        return $this->belongsTo(Order::class);
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
        return $this->hasMany(ParcelIncident::class, 'manifest_item_id')->latest('id');
    }

    public function getConditionSummaryAttribute(): array
    {
        $incidents = $this->relationLoaded('incidents')
            ? $this->incidents
            : $this->incidents()->get();

        $activeIncident = $incidents->first(fn (ParcelIncident $incident) => $incident->isUnresolved());
        $latestIncident = $incidents->first();
        $primaryIncident = $activeIncident ?? $latestIncident;

        $hasDamageReport = $incidents->contains(fn (ParcelIncident $incident) => $incident->incident_type === ParcelIncident::TYPE_DAMAGE);
        $hasOpenDamage = $incidents->contains(fn (ParcelIncident $incident) => $incident->incident_type === ParcelIncident::TYPE_DAMAGE && $incident->isUnresolved());
        $hasOpenMissing = $incidents->contains(fn (ParcelIncident $incident) => $incident->incident_type === ParcelIncident::TYPE_MISSING && $incident->isUnresolved());
        $hasFoundMissing = $incidents->contains(fn (ParcelIncident $incident) => $incident->incident_type === ParcelIncident::TYPE_MISSING && $incident->investigation_status === ParcelIncident::STATUS_FOUND);

        $format = function (string $key, string $label, string $classes, ?string $sublabel = null) use (
            $activeIncident,
            $latestIncident,
            $primaryIncident,
            $hasDamageReport,
            $hasOpenDamage,
            $hasOpenMissing,
            $hasFoundMissing
        ): array {
            return [
                'key' => $key,
                'label' => $label,
                'sublabel' => $sublabel,
                'classes' => $classes,
                'badge_class' => $classes,
                'active_incident' => $activeIncident,
                'latest_incident' => $latestIncident,
                'primary_incident' => $primaryIncident,
                'has_damage_report' => $hasDamageReport,
                'has_open_damage' => $hasOpenDamage,
                'has_open_missing' => $hasOpenMissing,
                'has_found_missing' => $hasFoundMissing,
            ];
        };

        if ($activeIncident) {
            $sublabel = $activeIncident->incident_number
                ? trim($activeIncident->incident_number . ($activeIncident->status_label ? ' · ' . $activeIncident->status_label : ''))
                : $activeIncident->status_label;

            if ($activeIncident->isQuarantined()) {
                $qLabel = $activeIncident->incident_type === ParcelIncident::TYPE_DAMAGE
                    ? 'Damage Reported (Quarantined)'
                    : 'Quarantined';

                return $format('quarantined', $qLabel, 'bg-purple-100 text-purple-800 border-purple-200', $sublabel);
            }

            if ($activeIncident->incident_type === ParcelIncident::TYPE_WRONG_HUB) {
                return $format('wrong_hub', 'Wrong Hub / Reroute', 'bg-amber-100 text-amber-800 border-amber-200', $sublabel);
            }

            if ($activeIncident->investigation_status === ParcelIncident::STATUS_FOUND) {
                return $format('found', 'Found / Recovery Pending', 'bg-cyan-100 text-cyan-800 border-cyan-200', $sublabel);
            }

            if ($activeIncident->incident_type === ParcelIncident::TYPE_MISSING) {
                return $format('missing', 'Missing', 'bg-rose-100 text-rose-800 border-rose-200', $sublabel);
            }

            if (in_array($activeIncident->investigation_status, [
                ParcelIncident::STATUS_UNDER_INVESTIGATION,
                ParcelIncident::STATUS_AWAITING_EVIDENCE,
                ParcelIncident::STATUS_AWAITING_DISPOSITION,
            ], true)) {
                return $format('under_investigation', $activeIncident->status_label, 'bg-amber-100 text-amber-800 border-amber-200', $sublabel);
            }

            return $format('damage_reported', 'Damage Reported', 'bg-orange-100 text-orange-800 border-orange-200', $sublabel);
        }

        if ($latestIncident && $latestIncident->isResolved()) {
            $label = match ($latestIncident->disposition) {
                ParcelIncident::DISPOSITION_DECLARED_LOST => 'Declared Lost',
                ParcelIncident::DISPOSITION_DAMAGED_BEYOND_DELIVERY => 'Damaged Beyond Delivery',
                ParcelIncident::DISPOSITION_RETURNED_TO_SENDER => 'Returned to Sender',
                ParcelIncident::DISPOSITION_ESCALATED_TO_CLAIM => 'Claim / Refund Escalated',
                default => 'Resolved',
            };
            $classes = in_array($latestIncident->disposition, [
                ParcelIncident::DISPOSITION_DECLARED_LOST,
                ParcelIncident::DISPOSITION_DAMAGED_BEYOND_DELIVERY,
            ], true)
                ? 'bg-rose-100 text-rose-800 border-rose-200'
                : 'bg-sky-100 text-sky-800 border-sky-200';

            return $format('resolved', $label, $classes, $latestIncident->incident_number);
        }

        if ($this->receiving_status === self::RECEIVING_MISSING) {
            return $format('missing', 'Missing', 'bg-rose-100 text-rose-800 border-rose-200');
        }

        if (in_array($this->receiving_status, [self::RECEIVING_DAMAGED, self::RECEIVING_DISCREPANCY], true)) {
            return $format(
                'damage_reported',
                $this->receiving_status === self::RECEIVING_DAMAGED ? 'Damage Reported' : 'Under Investigation',
                'bg-orange-100 text-orange-800 border-orange-200'
            );
        }

        if ($this->loading_status === self::LOADING_ISSUE) {
            return $format('under_investigation', 'Loading / Transit Issue', 'bg-amber-100 text-amber-800 border-amber-200');
        }

        return $format('good', 'Good', 'bg-emerald-100 text-emerald-800 border-emerald-200');
    }
}
