<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Hub;
use App\Models\HubTransfer;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\ParcelIncident;
use App\Models\ParcelIncidentEvent;
use App\Models\TransferManifest;
use App\Models\TransferManifestItem;
use App\Models\User;
use App\Notifications\ParcelIncidentNotification;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ParcelIncidentService
{
    public const MISSING_SLA_HOURS = 48;
    public const DEFAULT_SLA_HOURS = 72;

    /**
     * Authorize logistics/admin operators for exception actions.
     */
    public function authorizeLogisticsOperator(User $actor, ParcelIncident $incident): void
    {
        if ($actor->role === 'admin') {
            return;
        }

        if ($actor->role !== 'logistics') {
            abort(403, 'Couriers, buyers, and sellers cannot alter logistics custody, declare loss, or resolve parcel incidents.');
        }

        $companyId = $actor->logisticsCompanyId();
        $manifest = $incident->manifest;

        $isReceiverScopedStage = in_array($incident->source_stage, [
            ParcelIncident::STAGE_MANIFEST_RECEIVING,
            ParcelIncident::STAGE_HUB_RECEIVING,
            ParcelIncident::STAGE_POST_RECEIPT,
        ], true);

        $authorized = ((int) $incident->logistics_company_id === $companyId)
            || ($manifest && (int) $manifest->receiver_logistics_id === $companyId)
            || (! $isReceiverScopedStage && $manifest && (int) $manifest->sender_logistics_id === $companyId)
            || ($incident->hub && (int) $incident->hub->logistics_company_id === $companyId)
            || ($incident->actualHub && (int) $incident->actualHub->logistics_company_id === $companyId);

        abort_unless($authorized, 403, 'You do not have permission to manage this parcel incident.');
    }

    /**
     * Record a manifest-level exception incident (Missing, Damaged, Discrepancy) during receiving.
     */
    public function recordManifestExceptionIncident(
        TransferManifest $manifest,
        TransferManifestItem $item,
        string $classification,
        User $actor,
        ?string $notes = null,
        ?UploadedFile $evidenceFile = null,
        ?string $damageType = null,
        bool $acceptIntoQuarantine = false
    ): ?ParcelIncident {
        $type = match ($classification) {
            TransferManifestItem::RECEIVING_DAMAGED => ParcelIncident::TYPE_DAMAGE,
            TransferManifestItem::RECEIVING_MISSING => ParcelIncident::TYPE_MISSING,
            TransferManifestItem::RECEIVING_DISCREPANCY => ParcelIncident::TYPE_DISCREPANCY,
            default => null,
        };

        if (! $type) {
            return null;
        }

        return DB::transaction(function () use ($manifest, $item, $type, $notes, $actor, $evidenceFile, $damageType, $acceptIntoQuarantine) {
            $evidencePath = $evidenceFile ? $evidenceFile->store('parcel-incidents', 'public') : null;
            $now = now();

            $disposition = null;
            $handlingRestriction = ParcelIncident::RESTRICTION_HOLD_DISPATCH;
            $financialHold = ParcelIncident::FIN_HOLD_ON_HOLD;
            $quarantinedAt = null;
            $quarantinedBy = null;

            if ($acceptIntoQuarantine && in_array($type, [ParcelIncident::TYPE_DAMAGE, ParcelIncident::TYPE_DISCREPANCY], true)) {
                $disposition = ParcelIncident::DISPOSITION_ACCEPTED_INTO_QUARANTINE;
                $handlingRestriction = ParcelIncident::RESTRICTION_QUARANTINED;
                $quarantinedAt = $now;
                $quarantinedBy = $actor->id;
            }

            $existing = ParcelIncident::where('manifest_item_id', $item->id)
                ->where('incident_type', $type)
                ->where('source_stage', ParcelIncident::STAGE_MANIFEST_RECEIVING)
                ->whereIn('investigation_status', ParcelIncident::UNRESOLVED_STATUSES)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $existing->update([
                    'damage_type' => $damageType ?: $existing->damage_type,
                    'description' => trim((string) ($notes ?? '')) ?: $existing->description,
                    'evidence_path' => $evidencePath ?: $existing->evidence_path,
                    'disposition' => $disposition ?: $existing->disposition,
                    'handling_restriction' => $acceptIntoQuarantine ? $handlingRestriction : $existing->handling_restriction,
                    'quarantined_at' => $quarantinedAt ?: $existing->quarantined_at,
                    'quarantined_by' => $quarantinedBy ?: $existing->quarantined_by,
                    'financial_hold_status' => $existing->financial_hold_status ?: $financialHold,
                    'last_known_location' => $manifest->destinationHub?->name ?? $existing->last_known_location,
                    'last_known_event' => $acceptIntoQuarantine ? 'Accepted into hub quarantine' : 'Updated at manifest receiving',
                ]);

                $this->syncOrderHandlingStatus($item->order, $handlingRestriction);

                $this->recordEvent($existing, $actor, $acceptIntoQuarantine ? 'quarantined' : 'updated', [
                    'to_status' => $existing->investigation_status,
                    'disposition' => $existing->disposition,
                    'location' => $manifest->destinationHub?->name,
                    'notes' => $notes ?: 'Updated manifest receiving classification.',
                    'evidence_path' => $evidencePath,
                ]);

                AuditLog::log('parcel_incident_updated', $existing, null, [
                    'manifest_id' => $manifest->id,
                    'order_id' => $item->order_id,
                    'incident_type' => $type,
                    'disposition' => $existing->disposition,
                ], $actor->id);

                return $existing->fresh();
            }

            $incident = ParcelIncident::create([
                'incident_number' => $this->generateIncidentNumber(),
                'order_id' => $item->order_id,
                'manifest_id' => $manifest->id,
                'manifest_item_id' => $item->id,
                'hub_id' => $manifest->destination_hub_id,
                'expected_hub_id' => $manifest->destination_hub_id,
                'actual_hub_id' => $type === ParcelIncident::TYPE_MISSING ? null : $manifest->destination_hub_id,
                'logistics_company_id' => $manifest->receiver_logistics_id ?: $manifest->sender_logistics_id,
                'reported_by' => $actor->id,
                'quarantined_by' => $quarantinedBy,
                'tracking_number' => $item->tracking_number,
                'incident_type' => $type,
                'damage_type' => $damageType,
                'source_stage' => ParcelIncident::STAGE_MANIFEST_RECEIVING,
                'investigation_status' => ParcelIncident::STATUS_OPEN,
                'disposition' => $disposition,
                'handling_restriction' => $handlingRestriction,
                'description' => trim((string) ($notes ?? '')) ?: 'Exception reported during manifest receiving.',
                'evidence_path' => $evidencePath,
                'last_known_location' => $type === ParcelIncident::TYPE_MISSING
                    ? ($manifest->originHub?->name ?? 'Origin Hub / In Transit')
                    : ($manifest->destinationHub?->name ?? 'Destination Hub'),
                'last_known_event' => $acceptIntoQuarantine ? 'Damaged parcel accepted into hub quarantine' : 'Reported during manifest receiving',
                'financial_hold_status' => $financialHold,
                'reported_at' => $now,
                'quarantined_at' => $quarantinedAt,
                'due_at' => $this->defaultDueAt($type, $now),
            ]);

            $this->syncOrderHandlingStatus($item->order, $handlingRestriction);

            $this->recordEvent($incident, $actor, $acceptIntoQuarantine ? 'quarantined' : 'reported', [
                'to_status' => ParcelIncident::STATUS_OPEN,
                'disposition' => $disposition,
                'location' => $incident->last_known_location,
                'notes' => $incident->description,
                'evidence_path' => $evidencePath,
            ]);

            AuditLog::log('parcel_incident_reported', $incident, null, [
                'incident_number' => $incident->incident_number,
                'manifest_id' => $manifest->id,
                'order_id' => $item->order_id,
                'tracking_number' => $item->tracking_number,
                'incident_type' => $type,
                'source_stage' => ParcelIncident::STAGE_MANIFEST_RECEIVING,
                'disposition' => $disposition,
            ], $actor->id);

            $this->notifyIncidentStakeholders(
                $incident,
                $acceptIntoQuarantine ? 'quarantined' : 'incident_reported',
                $acceptIntoQuarantine ? "Parcel Quarantined: {$item->tracking_number}" : "Parcel Exception Reported: {$item->tracking_number}",
                "{$incident->incident_number}: Parcel {$item->tracking_number} flagged as " . ucwords(str_replace('_', ' ', $type)) . " on Manifest {$manifest->manifest_number}."
            );

            return $incident;
        });
    }

    /**
     * Record incident reported by a Transfer Courier (mobile app) during loading or in-transit.
     */
    public function recordCourierTransitIncident(
        TransferManifest $manifest,
        TransferManifestItem $item,
        string $issueType,
        string $notes,
        User $courier,
        ?string $lastKnownLocation = null,
        ?UploadedFile $evidencePhoto = null
    ): ParcelIncident {
        return DB::transaction(function () use ($manifest, $item, $issueType, $notes, $courier, $lastKnownLocation, $evidencePhoto) {
            $type = match ($issueType) {
                'missing' => ParcelIncident::TYPE_MISSING,
                'discrepancy' => ParcelIncident::TYPE_DISCREPANCY,
                default => ParcelIncident::TYPE_DAMAGE,
            };

            $stage = $manifest->dispatched_at
                ? ParcelIncident::STAGE_COURIER_TRANSIT
                : ParcelIncident::STAGE_MANIFEST_LOADING;

            $evidencePath = $evidencePhoto ? $evidencePhoto->store('parcel-incidents', 'public') : null;
            $now = now();
            $location = trim((string) $lastKnownLocation) !== ''
                ? trim((string) $lastKnownLocation)
                : ($manifest->dispatched_at ? 'In Transit between ' . ($manifest->originHub?->name ?? 'Origin') . ' and ' . ($manifest->destinationHub?->name ?? 'Destination') : ($manifest->originHub?->name ?? 'Origin Hub Loading Bay'));

            $existing = ParcelIncident::where('manifest_item_id', $item->id)
                ->where('incident_type', $type)
                ->whereIn('investigation_status', ParcelIncident::UNRESOLVED_STATUSES)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $existing->update([
                    'description' => trim($existing->description . "\n[Courier update] " . trim($notes)),
                    'last_known_location' => $location,
                    'last_known_event' => 'Courier follow-up report during ' . ($manifest->dispatched_at ? 'transit' : 'loading'),
                    'evidence_path' => $evidencePath ?: $existing->evidence_path,
                ]);

                $this->recordEvent($existing, $courier, 'courier_update', [
                    'to_status' => $existing->investigation_status,
                    'location' => $location,
                    'notes' => trim($notes),
                    'evidence_path' => $evidencePath,
                ]);

                $item->update([
                    'courier_issue_type' => $issueType,
                    'courier_issue_reported_at' => $now,
                    'courier_issue_evidence_path' => $evidencePath ?: $item->courier_issue_evidence_path,
                    'courier_issue_location' => $location,
                ]);

                return $existing->fresh(['events']);
            }

            $incident = ParcelIncident::create([
                'incident_number' => $this->generateIncidentNumber(),
                'order_id' => $item->order_id,
                'manifest_id' => $manifest->id,
                'manifest_item_id' => $item->id,
                'hub_id' => $manifest->dispatched_at ? $manifest->destination_hub_id : $manifest->origin_hub_id,
                'expected_hub_id' => $manifest->destination_hub_id,
                'logistics_company_id' => $manifest->sender_logistics_id ?: $manifest->receiver_logistics_id,
                'reported_by' => $courier->id,
                'tracking_number' => $item->tracking_number,
                'incident_type' => $type,
                'source_stage' => $stage,
                'investigation_status' => ParcelIncident::STATUS_OPEN,
                'handling_restriction' => ParcelIncident::RESTRICTION_HOLD_DISPATCH,
                'description' => trim($notes),
                'evidence_path' => $evidencePath,
                'last_known_location' => $location,
                'last_known_event' => $manifest->dispatched_at ? 'Reported by transfer courier in transit' : 'Reported during manifest loading',
                'financial_hold_status' => ParcelIncident::FIN_HOLD_ON_HOLD,
                'reported_at' => $now,
                'due_at' => $this->defaultDueAt($type, $now),
            ]);

            $item->update([
                'courier_issue_type' => $issueType,
                'courier_issue_reported_at' => $now,
                'courier_issue_evidence_path' => $evidencePath,
                'courier_issue_location' => $location,
            ]);

            $this->syncOrderHandlingStatus($item->order, ParcelIncident::RESTRICTION_HOLD_DISPATCH);

            $this->recordEvent($incident, $courier, 'reported', [
                'to_status' => ParcelIncident::STATUS_OPEN,
                'location' => $location,
                'notes' => trim($notes),
                'evidence_path' => $evidencePath,
                'metadata' => ['stage' => $stage, 'manifest_number' => $manifest->manifest_number],
            ]);

            AuditLog::log('courier_parcel_incident_reported', $incident, null, [
                'incident_number' => $incident->incident_number,
                'manifest_id' => $manifest->id,
                'tracking_number' => $item->tracking_number,
                'issue_type' => $issueType,
                'location' => $location,
            ], $courier->id);

            $this->notifyIncidentStakeholders(
                $incident,
                'incident_reported',
                "Courier Reported {$type}: {$item->tracking_number}",
                "{$incident->incident_number}: Courier {$courier->full_name} reported {$issueType} on {$manifest->manifest_number} at {$location}."
            );

            return $incident->fresh(['events']);
        });
    }

    /**
     * Authorize destination receiving company operator for manifest post-receipt actions.
     */
    private function authorizeManifestReceiver(TransferManifest $manifest, TransferManifestItem $item, User $actor): void
    {
        abort_unless((int) $item->manifest_id === (int) $manifest->id, 404, 'Parcel item does not belong to this manifest.');

        if ($actor->role === 'admin') {
            return;
        }

        $manifest->loadMissing('destinationHub');
        $receiverCompanyId = (int) ($manifest->receiver_logistics_id ?: $manifest->destinationHub?->logistics_company_id);

        abort_unless(
            in_array($actor->role, ['logistics', 'sorting_center'], true) && $actor->logisticsCompanyId() === $receiverCompanyId,
            403,
            'Only the authorized receiving logistics company can file post-receipt parcel incidents.'
        );
    }

    /**
     * Report newly discovered damage after parcel receipt without overwriting immutable receipt status.
     */
    public function reportPostReceiptDamage(
        TransferManifest $manifest,
        TransferManifestItem $item,
        User $actor,
        array $data,
        ?UploadedFile $evidenceFile = null
    ): ParcelIncident {
        $this->authorizeManifestReceiver($manifest, $item, $actor);

        if ($item->receiving_status !== TransferManifestItem::RECEIVING_RECEIVED) {
            throw ValidationException::withMessages([
                'item' => 'Post-receipt damage reporting is only allowed for already received parcels.',
            ]);
        }

        if (empty($data['damage_type']) || ! array_key_exists($data['damage_type'], ParcelIncident::DAMAGE_TYPES)) {
            throw ValidationException::withMessages([
                'damage_type' => 'Please select a valid damage classification.',
            ]);
        }

        $description = trim((string) ($data['description'] ?? ''));
        if ($description === '') {
            throw ValidationException::withMessages([
                'description' => 'A detailed description of the discovered damage is required.',
            ]);
        }

        return DB::transaction(function () use ($manifest, $item, $data, $description, $actor, $evidenceFile) {
            $hasOpenDamage = ParcelIncident::where('manifest_item_id', $item->id)
                ->where('incident_type', ParcelIncident::TYPE_DAMAGE)
                ->whereIn('investigation_status', ParcelIncident::UNRESOLVED_STATUSES)
                ->lockForUpdate()
                ->exists();

            if ($hasOpenDamage) {
                throw ValidationException::withMessages([
                    'item' => 'An unresolved damage report already exists for this parcel.',
                ]);
            }

            $evidencePath = $evidenceFile ? $evidenceFile->store('parcel-incidents', 'public') : null;
            $now = now();
            $quarantine = ! array_key_exists('quarantine', $data) || (bool) $data['quarantine'];

            $disposition = $quarantine ? ParcelIncident::DISPOSITION_ACCEPTED_INTO_QUARANTINE : null;
            $restriction = $quarantine ? ParcelIncident::RESTRICTION_QUARANTINED : ParcelIncident::RESTRICTION_HOLD_DISPATCH;

            $incident = ParcelIncident::create([
                'incident_number' => $this->generateIncidentNumber(),
                'order_id' => $item->order_id,
                'manifest_id' => $manifest->id,
                'manifest_item_id' => $item->id,
                'hub_id' => $manifest->destination_hub_id,
                'expected_hub_id' => $manifest->destination_hub_id,
                'actual_hub_id' => $manifest->destination_hub_id,
                'logistics_company_id' => $actor->logisticsCompanyId(),
                'reported_by' => $actor->id,
                'quarantined_by' => $quarantine ? $actor->id : null,
                'tracking_number' => $item->tracking_number,
                'incident_type' => ParcelIncident::TYPE_DAMAGE,
                'damage_type' => $data['damage_type'],
                'source_stage' => ParcelIncident::STAGE_POST_RECEIPT,
                'investigation_status' => ParcelIncident::STATUS_OPEN,
                'disposition' => $disposition,
                'handling_restriction' => $restriction,
                'description' => $description,
                'evidence_path' => $evidencePath,
                'last_known_location' => $manifest->destinationHub?->name ?? 'Destination Hub',
                'last_known_event' => 'Post-receipt damage discovered at hub',
                'financial_hold_status' => ParcelIncident::FIN_HOLD_ON_HOLD,
                'reported_at' => $now,
                'quarantined_at' => $quarantine ? $now : null,
                'due_at' => $this->defaultDueAt(ParcelIncident::TYPE_DAMAGE, $now),
            ]);

            $this->syncOrderHandlingStatus($item->order, $restriction);

            if ($item->order) {
                OrderStatusHistory::create([
                    'order_id' => $item->order->id,
                    'status' => $item->order->status,
                    'changed_by' => $actor->id,
                    'changed_at' => $now,
                    'note' => "Post-Receipt Damage reported ({$incident->damage_type_label}): {$description}",
                ]);
            }

            $this->recordEvent($incident, $actor, $quarantine ? 'quarantined' : 'reported', [
                'to_status' => ParcelIncident::STATUS_OPEN,
                'disposition' => $disposition,
                'location' => $incident->last_known_location,
                'notes' => $description,
                'evidence_path' => $evidencePath,
            ]);

            AuditLog::log('manifest_parcel_damage_reported', $manifest, null, [
                'incident_id' => $incident->id,
                'incident_number' => $incident->incident_number,
                'manifest_id' => $manifest->id,
                'manifest_item_id' => $item->id,
                'order_id' => $item->order_id,
                'tracking_number' => $item->tracking_number,
                'damage_type' => $data['damage_type'],
                'preserved_receiving_status' => $item->receiving_status,
                'preserved_received_at' => $item->received_at?->toIso8601String(),
                'quarantined' => $quarantine,
            ], $actor->id);

            $this->notifyIncidentStakeholders(
                $incident,
                'incident_reported',
                "Post-Receipt Damage Reported: {$item->tracking_number}",
                "{$incident->incident_number}: Damage reported after receipt at {$incident->last_known_location}."
            );

            return $incident;
        });
    }

    /**
     * Report a missing parcel after initial receipt without altering immutable receiving_status.
     */
    public function reportPostReceiptMissing(
        TransferManifest $manifest,
        TransferManifestItem $item,
        User $actor,
        string $description,
        ?UploadedFile $evidenceFile = null,
        ?string $lastKnownLocation = null
    ): ParcelIncident {
        $this->authorizeManifestReceiver($manifest, $item, $actor);

        if ($item->receiving_status !== TransferManifestItem::RECEIVING_RECEIVED) {
            throw ValidationException::withMessages([
                'item' => 'Post-receipt missing incident can only be filed for parcels already marked received.',
            ]);
        }

        $cleanDescription = trim($description);
        if ($cleanDescription === '') {
            throw ValidationException::withMessages([
                'description' => 'Please provide details about where the parcel was last seen or how it went missing.',
            ]);
        }

        return DB::transaction(function () use ($manifest, $item, $cleanDescription, $actor, $evidenceFile, $lastKnownLocation) {
            $hasOpenMissing = ParcelIncident::where('manifest_item_id', $item->id)
                ->where('incident_type', ParcelIncident::TYPE_MISSING)
                ->whereIn('investigation_status', ParcelIncident::UNRESOLVED_STATUSES)
                ->lockForUpdate()
                ->exists();

            if ($hasOpenMissing) {
                throw ValidationException::withMessages([
                    'item' => 'An unresolved missing parcel report already exists for this parcel.',
                ]);
            }

            $evidencePath = $evidenceFile ? $evidenceFile->store('parcel-incidents', 'public') : null;
            $now = now();
            $location = trim((string) $lastKnownLocation) !== '' ? trim((string) $lastKnownLocation) : ($manifest->destinationHub?->name ?? 'Destination Hub');

            $incident = ParcelIncident::create([
                'incident_number' => $this->generateIncidentNumber(),
                'order_id' => $item->order_id,
                'manifest_id' => $manifest->id,
                'manifest_item_id' => $item->id,
                'hub_id' => $manifest->destination_hub_id,
                'expected_hub_id' => $manifest->destination_hub_id,
                'logistics_company_id' => $actor->logisticsCompanyId(),
                'reported_by' => $actor->id,
                'tracking_number' => $item->tracking_number,
                'incident_type' => ParcelIncident::TYPE_MISSING,
                'source_stage' => ParcelIncident::STAGE_POST_RECEIPT,
                'investigation_status' => ParcelIncident::STATUS_OPEN,
                'handling_restriction' => ParcelIncident::RESTRICTION_HOLD_DISPATCH,
                'description' => $cleanDescription,
                'evidence_path' => $evidencePath,
                'last_known_location' => $location,
                'last_known_event' => 'Reported missing after hub receipt',
                'financial_hold_status' => ParcelIncident::FIN_HOLD_ON_HOLD,
                'reported_at' => $now,
                'due_at' => $this->defaultDueAt(ParcelIncident::TYPE_MISSING, $now),
            ]);

            $this->syncOrderHandlingStatus($item->order, ParcelIncident::RESTRICTION_HOLD_DISPATCH);

            $this->recordEvent($incident, $actor, 'reported', [
                'to_status' => ParcelIncident::STATUS_OPEN,
                'location' => $location,
                'notes' => $cleanDescription,
                'evidence_path' => $evidencePath,
            ]);

            AuditLog::log('post_receipt_missing_reported', $incident, null, [
                'incident_number' => $incident->incident_number,
                'manifest_id' => $manifest->id,
                'manifest_item_id' => $item->id,
                'order_id' => $item->order_id,
                'tracking_number' => $item->tracking_number,
                'preserved_receiving_status' => $item->receiving_status,
                'preserved_received_at' => $item->received_at?->toIso8601String(),
            ], $actor->id);

            $this->notifyIncidentStakeholders(
                $incident,
                'incident_reported',
                "Post-Receipt Missing Parcel: {$item->tracking_number}",
                "{$incident->incident_number}: Parcel {$item->tracking_number} reported missing after receipt at {$location}."
            );

            return $incident;
        });
    }

    /**
     * Record a Wrong-Hub / Misrouted parcel incident without silently overwriting historical hubs.
     */
    public function recordWrongHubIncident(
        Order $order,
        ?Hub $expectedHub,
        Hub $actualHub,
        User $actor,
        string $notes,
        ?string $location = null
    ): ParcelIncident {
        $cleanNotes = trim($notes);
        if ($cleanNotes === '') {
            throw ValidationException::withMessages([
                'notes' => 'Please enter details for the misrouted parcel exception.',
            ]);
        }

        return DB::transaction(function () use ($order, $expectedHub, $actualHub, $actor, $cleanNotes, $location) {
            $now = now();
            $existing = ParcelIncident::where('order_id', $order->id)
                ->where('incident_type', ParcelIncident::TYPE_WRONG_HUB)
                ->where('actual_hub_id', $actualHub->id)
                ->whereIn('investigation_status', ParcelIncident::UNRESOLVED_STATUSES)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $existing->update([
                    'description' => trim($existing->description . "\n" . $cleanNotes),
                    'last_known_location' => $location ?: $actualHub->name,
                    'last_known_event' => "Held at unexpected hub {$actualHub->name}",
                ]);
                $this->recordEvent($existing, $actor, 'updated', [
                    'to_status' => $existing->investigation_status,
                    'location' => $location ?: $actualHub->name,
                    'notes' => $cleanNotes,
                ]);

                return $existing->fresh();
            }

            $incident = ParcelIncident::create([
                'incident_number' => $this->generateIncidentNumber(),
                'order_id' => $order->id,
                'hub_id' => $actualHub->id,
                'expected_hub_id' => $expectedHub?->id ?: $order->destination_hub_id,
                'actual_hub_id' => $actualHub->id,
                'logistics_company_id' => $actor->logisticsCompanyId() ?: $actualHub->logistics_company_id,
                'reported_by' => $actor->id,
                'tracking_number' => $order->tracking_number ?? $order->order_number,
                'incident_type' => ParcelIncident::TYPE_WRONG_HUB,
                'damage_type' => 'wrong_hub_misrouted',
                'source_stage' => ParcelIncident::STAGE_HUB_RECEIVING,
                'investigation_status' => ParcelIncident::STATUS_OPEN,
                'handling_restriction' => ParcelIncident::RESTRICTION_REROUTE_REQUIRED,
                'description' => $cleanNotes,
                'last_known_location' => $location ?: $actualHub->name,
                'last_known_event' => "Scanned at unexpected hub {$actualHub->name} instead of " . ($expectedHub?->name ?? $order->destinationHub?->name ?? 'intended destination hub'),
                'financial_hold_status' => ParcelIncident::FIN_HOLD_ON_HOLD,
                'reported_at' => $now,
                'due_at' => $this->defaultDueAt(ParcelIncident::TYPE_WRONG_HUB, $now),
            ]);

            $this->syncOrderHandlingStatus($order, ParcelIncident::RESTRICTION_REROUTE_REQUIRED);

            $this->recordEvent($incident, $actor, 'wrong_hub_detected', [
                'to_status' => ParcelIncident::STATUS_OPEN,
                'location' => $actualHub->name,
                'notes' => $cleanNotes,
                'metadata' => [
                    'expected_hub_id' => $incident->expected_hub_id,
                    'actual_hub_id' => $actualHub->id,
                ],
            ]);

            AuditLog::log('wrong_hub_incident_reported', $incident, null, [
                'incident_number' => $incident->incident_number,
                'order_id' => $order->id,
                'expected_hub_id' => $incident->expected_hub_id,
                'actual_hub_id' => $actualHub->id,
            ], $actor->id);

            $this->notifyIncidentStakeholders(
                $incident,
                'incident_reported',
                "Misrouted Parcel Flagged: {$incident->tracking_number}",
                "{$incident->incident_number}: Parcel scanned at {$actualHub->name}; requires authorized reroute."
            );

            return $incident;
        });
    }

    /**
     * Authorize and create a controlled reroute leg for a misrouted / wrong-hub parcel.
     */
    public function rerouteParcel(
        ParcelIncident $incident,
        Hub $targetHub,
        User $actor,
        string $notes,
        bool $overrideDestinationHub = false
    ): ParcelIncident {
        $this->authorizeLogisticsOperator($actor, $incident);

        $order = $incident->order;
        if (! $order) {
            throw ValidationException::withMessages(['order' => 'Order record not found for incident.']);
        }

        $actualHub = $incident->actualHub ?: $incident->hub ?: $order->currentHub;
        if (! $actualHub) {
            throw ValidationException::withMessages(['actual_hub_id' => 'Current physical hub must be specified before rerouting.']);
        }

        if ((int) $actualHub->id === (int) $targetHub->id) {
            throw ValidationException::withMessages(['target_hub_id' => 'Target reroute hub must differ from current physical hub.']);
        }

        $cleanNotes = trim($notes);
        if ($cleanNotes === '') {
            throw ValidationException::withMessages(['notes' => 'Please enter reroute authorization notes.']);
        }

        return DB::transaction(function () use ($incident, $order, $actualHub, $targetHub, $actor, $cleanNotes, $overrideDestinationHub) {
            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->firstOrFail();
            $lockedIncident = ParcelIncident::where('id', $incident->id)->lockForUpdate()->firstOrFail();

            $fromStatus = $lockedIncident->investigation_status;
            $now = now();

            $orderUpdates = [
                'current_hub_id' => $actualHub->id,
                'rerouted_from_hub_id' => $actualHub->id,
                'reroute_count' => (int) ($lockedOrder->reroute_count ?? 0) + 1,
                'handling_status' => ParcelIncident::RESTRICTION_CLEARED,
                'current_custody_logistics_id' => $actualHub->logistics_company_id,
            ];

            if ($overrideDestinationHub) {
                $orderUpdates['destination_hub_id'] = $targetHub->id;
            }

            if (in_array($lockedOrder->status, ['in_transit_hub', 'picked_up', 'ready_for_pickup', 'preparing'], true)) {
                OrderLogisticsService::transition(
                    order: $lockedOrder,
                    newStatus: 'received_at_hub',
                    changedBy: $actor->id,
                    note: "Reroute initiated from {$actualHub->name} to {$targetHub->name} ({$lockedIncident->incident_number}): {$cleanNotes}",
                    extraAttributes: $orderUpdates
                );
            } else {
                $lockedOrder->update($orderUpdates);
                OrderStatusHistory::create([
                    'order_id' => $lockedOrder->id,
                    'status' => $lockedOrder->status,
                    'changed_by' => $actor->id,
                    'changed_at' => $now,
                    'note' => "Authorized reroute leg created: {$actualHub->name} → {$targetHub->name} ({$lockedIncident->incident_number}).",
                ]);
            }

            $transfer = HubTransfer::create([
                'order_id' => $lockedOrder->id,
                'sender_logistics_id' => $actualHub->logistics_company_id,
                'sender_hub_id' => $actualHub->id,
                'receiver_logistics_id' => $targetHub->logistics_company_id,
                'receiver_hub_id' => $targetHub->id,
                'tracking_number' => $lockedOrder->tracking_number ?? $lockedOrder->order_number,
                'status' => 'in_transit',
                'dispatched_by' => $actor->id,
                'dispatched_at' => $now,
                'notes' => "Authorized reroute from incident {$lockedIncident->incident_number}: {$cleanNotes}",
            ]);

            $lockedOrder->update([
                'status' => 'in_transit_hub',
            ]);

            OrderStatusHistory::create([
                'order_id' => $lockedOrder->id,
                'status' => 'in_transit_hub',
                'changed_by' => $actor->id,
                'changed_at' => $now,
                'note' => "Parcel rerouted from {$actualHub->name} toward {$targetHub->name} (Transfer #{$transfer->id}).",
            ]);

            $lockedIncident->update([
                'rerouted_to_hub_id' => $targetHub->id,
                'investigation_status' => ParcelIncident::STATUS_RESOLVED,
                'disposition' => ParcelIncident::DISPOSITION_RESOLVED_BY_CORRECTION,
                'handling_restriction' => ParcelIncident::RESTRICTION_CLEARED,
                'financial_hold_status' => ParcelIncident::FIN_HOLD_CLEARED,
                'resolved_by' => $actor->id,
                'resolved_at' => $now,
                'resolution_notes' => "Rerouted from {$actualHub->name} to {$targetHub->name}. {$cleanNotes}",
                'last_known_location' => $actualHub->name,
                'last_known_event' => "Rerouted to {$targetHub->name}",
            ]);

            $this->recordEvent($lockedIncident, $actor, 'rerouted', [
                'from_status' => $fromStatus,
                'to_status' => ParcelIncident::STATUS_RESOLVED,
                'disposition' => ParcelIncident::DISPOSITION_RESOLVED_BY_CORRECTION,
                'location' => "{$actualHub->name} -> {$targetHub->name}",
                'notes' => $cleanNotes,
                'metadata' => [
                    'transfer_id' => $transfer->id,
                    'from_hub_id' => $actualHub->id,
                    'to_hub_id' => $targetHub->id,
                    'reroute_count' => $lockedOrder->reroute_count,
                ],
            ]);

            AuditLog::log('parcel_rerouted', $lockedOrder, ['current_hub_id' => $order->current_hub_id], [
                'incident_id' => $lockedIncident->id,
                'incident_number' => $lockedIncident->incident_number,
                'transfer_id' => $transfer->id,
                'from_hub_id' => $actualHub->id,
                'to_hub_id' => $targetHub->id,
                'reroute_count' => $lockedOrder->reroute_count,
            ], $actor->id);

            $this->notifyIncidentStakeholders(
                $lockedIncident,
                'parcel_rerouted',
                "Parcel Rerouted: {$lockedIncident->tracking_number}",
                "{$lockedIncident->incident_number}: Parcel rerouted from {$actualHub->name} to {$targetHub->name}."
            );

            return $lockedIncident->fresh(['events', 'order', 'actualHub', 'reroutedToHub']);
        });
    }

    /**
     * Assign an investigator to an incident.
     */
    public function assignInvestigator(ParcelIncident $incident, User $investigator, User $actor, ?string $notes = null): ParcelIncident
    {
        $this->authorizeLogisticsOperator($actor, $incident);

        if (! in_array($investigator->role, ['logistics', 'admin'], true)) {
            throw ValidationException::withMessages([
                'assigned_to' => 'Investigator must be a logistics operator or administrator.',
            ]);
        }

        return DB::transaction(function () use ($incident, $investigator, $actor, $notes) {
            $locked = ParcelIncident::where('id', $incident->id)->lockForUpdate()->firstOrFail();
            $fromStatus = $locked->investigation_status;
            $nextStatus = $locked->investigation_status === ParcelIncident::STATUS_OPEN
                ? ParcelIncident::STATUS_UNDER_INVESTIGATION
                : $locked->investigation_status;

            $locked->update([
                'assigned_to' => $investigator->id,
                'investigation_status' => $nextStatus,
            ]);

            $this->recordEvent($locked, $actor, 'investigator_assigned', [
                'from_status' => $fromStatus,
                'to_status' => $nextStatus,
                'notes' => trim((string) $notes) ?: "Assigned to {$investigator->full_name}.",
                'metadata' => ['assigned_to' => $investigator->id, 'assigned_name' => $investigator->full_name],
            ]);

            AuditLog::log('parcel_incident_assigned', $locked, null, [
                'assigned_to' => $investigator->id,
                'investigation_status' => $nextStatus,
            ], $actor->id);

            return $locked->fresh(['assignee', 'events']);
        });
    }

    /**
     * Append investigation evidence/update/status progression (never allowing notes-only shortcut close).
     */
    public function addInvestigationEvent(
        ParcelIncident $incident,
        User $actor,
        string $notes,
        ?string $nextStatus = null,
        ?string $location = null,
        ?UploadedFile $evidenceFile = null
    ): ParcelIncident {
        $this->authorizeLogisticsOperator($actor, $incident);

        $cleanNotes = trim($notes);
        if ($cleanNotes === '') {
            throw ValidationException::withMessages([
                'notes' => 'Investigation note or evidence commentary is required.',
            ]);
        }

        if ($nextStatus !== null) {
            if (in_array($nextStatus, [ParcelIncident::STATUS_FOUND, ParcelIncident::STATUS_RESOLVED, ParcelIncident::STATUS_CLOSED], true)) {
                throw ValidationException::withMessages([
                    'investigation_status' => 'Use physical recovery confirmation or explicit disposition actions to mark found, resolved, or closed.',
                ]);
            }

            if (! in_array($nextStatus, [
                ParcelIncident::STATUS_OPEN,
                ParcelIncident::STATUS_UNDER_INVESTIGATION,
                ParcelIncident::STATUS_AWAITING_EVIDENCE,
                ParcelIncident::STATUS_AWAITING_DISPOSITION,
            ], true)) {
                throw ValidationException::withMessages([
                    'investigation_status' => 'Invalid intermediate investigation status.',
                ]);
            }
        }

        return DB::transaction(function () use ($incident, $actor, $cleanNotes, $nextStatus, $location, $evidenceFile) {
            $locked = ParcelIncident::where('id', $incident->id)->lockForUpdate()->firstOrFail();
            $fromStatus = $locked->investigation_status;
            $evidencePath = $evidenceFile ? $evidenceFile->store('parcel-incidents', 'public') : null;

            $updates = [
                'investigation_status' => $nextStatus ?? $fromStatus,
            ];

            if ($location !== null && trim($location) !== '') {
                $updates['last_known_location'] = trim($location);
            }
            if ($evidencePath) {
                $updates['evidence_path'] = $locked->evidence_path ?: $evidencePath;
            }
            $updates['last_known_event'] = 'Investigation note logged';

            $locked->update($updates);

            $this->recordEvent($locked, $actor, 'investigation_update', [
                'from_status' => $fromStatus,
                'to_status' => $locked->investigation_status,
                'location' => $location,
                'notes' => $cleanNotes,
                'evidence_path' => $evidencePath,
            ]);

            AuditLog::log('parcel_incident_note_added', $locked, ['status' => $fromStatus], [
                'status' => $locked->investigation_status,
                'location' => $location,
            ], $actor->id);

            return $locked->fresh(['events']);
        });
    }

    /**
     * Place parcel into formal hub quarantine (blocks normal delivery dispatch until cleared/disposed).
     */
    public function quarantineParcel(
        ParcelIncident $incident,
        User $actor,
        string $notes,
        ?string $location = null
    ): ParcelIncident {
        $this->authorizeLogisticsOperator($actor, $incident);

        return DB::transaction(function () use ($incident, $actor, $notes, $location) {
            $locked = ParcelIncident::where('id', $incident->id)->lockForUpdate()->firstOrFail();
            $fromStatus = $locked->investigation_status;
            $now = now();

            $locked->update([
                'investigation_status' => ParcelIncident::STATUS_AWAITING_DISPOSITION,
                'disposition' => ParcelIncident::DISPOSITION_ACCEPTED_INTO_QUARANTINE,
                'handling_restriction' => ParcelIncident::RESTRICTION_QUARANTINED,
                'financial_hold_status' => ParcelIncident::FIN_HOLD_ON_HOLD,
                'quarantined_at' => $locked->quarantined_at ?: $now,
                'quarantined_by' => $locked->quarantined_by ?: $actor->id,
                'last_known_location' => $location ?: ($locked->last_known_location ?: $locked->hub?->name),
                'last_known_event' => 'Parcel placed in hub quarantine',
            ]);

            if ($locked->manifestItem) {
                $locked->manifestItem->update([
                    'disposition' => ParcelIncident::DISPOSITION_ACCEPTED_INTO_QUARANTINE,
                ]);
            }

            $this->syncOrderHandlingStatus($locked->order, ParcelIncident::RESTRICTION_QUARANTINED);

            $this->recordEvent($locked, $actor, 'quarantined', [
                'from_status' => $fromStatus,
                'to_status' => ParcelIncident::STATUS_AWAITING_DISPOSITION,
                'disposition' => ParcelIncident::DISPOSITION_ACCEPTED_INTO_QUARANTINE,
                'location' => $locked->last_known_location,
                'notes' => trim($notes) ?: 'Parcel accepted into quarantine pending damage/safety inspection.',
            ]);

            AuditLog::log('parcel_quarantined', $locked, ['status' => $fromStatus], [
                'disposition' => ParcelIncident::DISPOSITION_ACCEPTED_INTO_QUARANTINE,
                'handling_restriction' => ParcelIncident::RESTRICTION_QUARANTINED,
            ], $actor->id);

            $this->notifyIncidentStakeholders(
                $locked,
                'quarantined',
                "Parcel Quarantined: {$locked->tracking_number}",
                "{$locked->incident_number}: Parcel {$locked->tracking_number} placed in quarantine and blocked from normal dispatch."
            );

            return $locked->fresh(['events', 'order']);
        });
    }

    /**
     * Missing parcel recovery: document recovery location + evidence and optionally verify physical receipt.
     */
    public function recoverMissingParcel(
        ParcelIncident $incident,
        User $actor,
        string $recoveryLocation,
        string $notes,
        ?UploadedFile $evidenceFile = null,
        bool $confirmPhysicalReceipt = true
    ): ParcelIncident {
        $this->authorizeLogisticsOperator($actor, $incident);

        $cleanLocation = trim($recoveryLocation);
        $cleanNotes = trim($notes);
        if ($cleanLocation === '' || $cleanNotes === '') {
            throw ValidationException::withMessages([
                'recovery_location' => 'Recovery location and verification notes are both required.',
            ]);
        }

        return DB::transaction(function () use ($incident, $actor, $cleanLocation, $cleanNotes, $evidenceFile, $confirmPhysicalReceipt) {
            $locked = ParcelIncident::where('id', $incident->id)
                ->with(['manifest', 'manifestItem.hubTransfer', 'order'])
                ->lockForUpdate()
                ->firstOrFail();

            $fromStatus = $locked->investigation_status;
            $now = now();
            $evidencePath = $evidenceFile ? $evidenceFile->store('parcel-incidents', 'public') : null;

            $locked->update([
                'investigation_status' => $confirmPhysicalReceipt ? ParcelIncident::STATUS_RESOLVED : ParcelIncident::STATUS_FOUND,
                'disposition' => $confirmPhysicalReceipt ? ParcelIncident::DISPOSITION_RECOVERED_AND_RECEIVED : $locked->disposition,
                'handling_restriction' => $confirmPhysicalReceipt ? ParcelIncident::RESTRICTION_CLEARED : ParcelIncident::RESTRICTION_HOLD_DISPATCH,
                'financial_hold_status' => $confirmPhysicalReceipt ? ParcelIncident::FIN_HOLD_CLEARED : $locked->financial_hold_status,
                'found_at' => $locked->found_at ?: $now,
                'recovered_at' => $now,
                'recovered_by' => $actor->id,
                'recovery_location' => $cleanLocation,
                'recovery_evidence_path' => $evidencePath ?: $locked->recovery_evidence_path,
                'last_known_location' => $cleanLocation,
                'last_known_event' => $confirmPhysicalReceipt ? 'Parcel recovered & physically received at hub' : 'Parcel physically located',
                'resolved_by' => $confirmPhysicalReceipt ? $actor->id : $locked->resolved_by,
                'resolved_at' => $confirmPhysicalReceipt ? $now : $locked->resolved_at,
                'resolution_notes' => $cleanNotes,
            ]);

            if ($confirmPhysicalReceipt) {
                $item = $locked->manifestItem;
                $manifest = $locked->manifest;

                if ($item) {
                    // For Missing During Receiving (unreceived), record real physical receipt timestamp.
                    // For Missing After Receipt, preserve original immutable received_at & received_by!
                    if ($item->receiving_status !== TransferManifestItem::RECEIVING_RECEIVED) {
                        $item->update([
                            'receiving_status' => TransferManifestItem::RECEIVING_RECEIVED,
                            'received_at' => $now,
                            'received_by' => $actor->id,
                            'disposition' => ParcelIncident::DISPOSITION_RECOVERED_AND_RECEIVED,
                        ]);

                        if ($item->hubTransfer && $item->hubTransfer->status === 'in_transit') {
                            $item->hubTransfer->update([
                                'status' => 'received',
                                'received_by' => $actor->id,
                                'received_at' => $now,
                            ]);
                        }
                    } else {
                        $item->update([
                            'disposition' => ParcelIncident::DISPOSITION_RECOVERED_AND_RECEIVED,
                        ]);
                    }
                }

                if ($locked->order) {
                    $order = $locked->order;
                    $destHubId = $manifest?->destination_hub_id ?: $locked->hub_id ?: $order->current_hub_id;
                    $destLogisticsId = $manifest?->receiver_logistics_id ?: $order->current_custody_logistics_id;

                    $order->update(['handling_status' => ParcelIncident::RESTRICTION_CLEARED]);

                    if (in_array($order->status, ['in_transit_hub', 'picked_up', 'ready_for_pickup', 'preparing'], true) && $destHubId) {
                        $targetStatus = ((int) $destHubId === (int) $order->destination_hub_id) ? 'at_sorting_center' : 'received_at_hub';
                        OrderLogisticsService::transition(
                            order: $order,
                            newStatus: $targetStatus,
                            changedBy: $actor->id,
                            note: "Recovered parcel {$order->tracking_number} verified at {$cleanLocation} ({$locked->incident_number}).",
                            extraAttributes: [
                                'current_hub_id' => $destHubId,
                                'current_custody_logistics_id' => $destLogisticsId,
                                'sorting_center_id' => $destLogisticsId ?: $order->sorting_center_id,
                                'handling_status' => null,
                            ]
                        );
                    } else {
                        $order->update(['handling_status' => null]);
                    }
                }
            }

            $this->recordEvent($locked, $actor, $confirmPhysicalReceipt ? 'recovered_and_received' : 'located', [
                'from_status' => $fromStatus,
                'to_status' => $locked->investigation_status,
                'disposition' => $locked->disposition,
                'location' => $cleanLocation,
                'notes' => $cleanNotes,
                'evidence_path' => $evidencePath,
            ]);

            AuditLog::log('parcel_incident_recovered', $locked, ['status' => $fromStatus], [
                'incident_number' => $locked->incident_number,
                'recovery_location' => $cleanLocation,
                'confirm_physical_receipt' => $confirmPhysicalReceipt,
                'to_status' => $locked->investigation_status,
            ], $actor->id);

            $this->notifyIncidentStakeholders(
                $locked,
                'recovery_reported',
                "Parcel Recovered: {$locked->tracking_number}",
                "{$locked->incident_number}: Missing parcel {$locked->tracking_number} located at {$cleanLocation}" . ($confirmPhysicalReceipt ? ' and physically verified.' : '.')
            );

            return $locked->fresh(['events', 'order', 'manifestItem']);
        });
    }

    /**
     * Apply explicit final or containment disposition (Phase 2 & 4).
     * Never allows marking an unreceived missing parcel resolved/closed with notes only!
     */
    public function applyDisposition(
        ParcelIncident $incident,
        User $actor,
        string $disposition,
        string $notes,
        ?UploadedFile $evidenceFile = null,
        bool $closeImmediately = false
    ): ParcelIncident {
        $this->authorizeLogisticsOperator($actor, $incident);

        $cleanNotes = trim($notes);
        if ($cleanNotes === '') {
            throw ValidationException::withMessages([
                'resolution_notes' => 'Resolution / disposition notes are required.',
            ]);
        }

        if (! array_key_exists($disposition, ParcelIncident::ALL_DISPOSITIONS)) {
            throw ValidationException::withMessages([
                'disposition' => 'Invalid parcel incident disposition selected.',
            ]);
        }

        if ($disposition === ParcelIncident::DISPOSITION_ACCEPTED_INTO_QUARANTINE) {
            return $this->quarantineParcel($incident, $actor, $cleanNotes);
        }

        return DB::transaction(function () use ($incident, $actor, $disposition, $cleanNotes, $evidenceFile, $closeImmediately) {
            $locked = ParcelIncident::where('id', $incident->id)
                ->with(['manifest', 'manifestItem', 'order'])
                ->lockForUpdate()
                ->firstOrFail();

            $item = $locked->manifestItem;
            $fromStatus = $locked->investigation_status;
            $now = now();
            $evidencePath = $evidenceFile ? $evidenceFile->store('parcel-incidents', 'public') : null;

            // Block shortcut clearing of an unreceived missing parcel without physical recovery
            if (in_array($disposition, [
                ParcelIncident::DISPOSITION_INSPECTED_AND_CLEARED,
                ParcelIncident::DISPOSITION_RECOVERED_AND_RECEIVED,
            ], true) && $locked->incident_type === ParcelIncident::TYPE_MISSING) {
                $isMissingAtManifest = $item && $item->receiving_status === TransferManifestItem::RECEIVING_MISSING;
                $hasRecoveryProof = $locked->found_at !== null || $locked->recovered_at !== null;

                if ($disposition === ParcelIncident::DISPOSITION_INSPECTED_AND_CLEARED && ($isMissingAtManifest || ! $hasRecoveryProof)) {
                    throw ValidationException::withMessages([
                        'disposition' => 'An unreceived missing parcel cannot be cleared for delivery until physically recovered and verified.',
                    ]);
                }

                if ($disposition === ParcelIncident::DISPOSITION_RECOVERED_AND_RECEIVED && ! $hasRecoveryProof) {
                    throw ValidationException::withMessages([
                        'disposition' => 'Record recovery location and verification before marking recovered and received.',
                    ]);
                }
            }

            $restriction = match ($disposition) {
                ParcelIncident::DISPOSITION_RECOVERED_AND_RECEIVED,
                ParcelIncident::DISPOSITION_INSPECTED_AND_CLEARED,
                ParcelIncident::DISPOSITION_RESOLVED_BY_CORRECTION => ParcelIncident::RESTRICTION_CLEARED,
                ParcelIncident::DISPOSITION_RETURNED_TO_SENDER => ParcelIncident::RESTRICTION_RETURN_TO_SENDER,
                ParcelIncident::DISPOSITION_DECLARED_LOST => ParcelIncident::RESTRICTION_TERMINATED_LOST,
                ParcelIncident::DISPOSITION_DAMAGED_BEYOND_DELIVERY => ParcelIncident::RESTRICTION_TERMINATED_DAMAGED,
                ParcelIncident::DISPOSITION_ESCALATED_TO_CLAIM => ParcelIncident::RESTRICTION_HOLD_DISPATCH,
                default => ParcelIncident::RESTRICTION_HOLD_DISPATCH,
            };

            $financialHold = match ($disposition) {
                ParcelIncident::DISPOSITION_RECOVERED_AND_RECEIVED,
                ParcelIncident::DISPOSITION_INSPECTED_AND_CLEARED,
                ParcelIncident::DISPOSITION_RESOLVED_BY_CORRECTION => ParcelIncident::FIN_HOLD_CLEARED,
                ParcelIncident::DISPOSITION_DECLARED_LOST => ParcelIncident::FIN_HOLD_LOSS_WRITTEN_OFF,
                ParcelIncident::DISPOSITION_DAMAGED_BEYOND_DELIVERY,
                ParcelIncident::DISPOSITION_ESCALATED_TO_CLAIM => ParcelIncident::FIN_HOLD_CLAIM_ESCALATED,
                ParcelIncident::DISPOSITION_RETURNED_TO_SENDER => ParcelIncident::FIN_HOLD_ON_HOLD,
                default => ParcelIncident::FIN_HOLD_ON_HOLD,
            };

            $newStatus = $closeImmediately ? ParcelIncident::STATUS_CLOSED : ParcelIncident::STATUS_RESOLVED;

            $locked->update([
                'investigation_status' => $newStatus,
                'disposition' => $disposition,
                'handling_restriction' => $restriction,
                'financial_hold_status' => $financialHold,
                'resolution_notes' => $cleanNotes,
                'resolved_by' => $locked->resolved_by ?: $actor->id,
                'resolved_at' => $locked->resolved_at ?: $now,
                'closed_by' => $closeImmediately ? $actor->id : $locked->closed_by,
                'closed_at' => $closeImmediately ? $now : $locked->closed_at,
                'evidence_path' => $evidencePath ?: $locked->evidence_path,
                'last_known_event' => ParcelIncident::ALL_DISPOSITIONS[$disposition] ?? $disposition,
            ]);

            if ($item) {
                $item->update(['disposition' => $disposition]);
            }

            if ($locked->order) {
                $orderHandling = $restriction === ParcelIncident::RESTRICTION_CLEARED ? null : $restriction;
                $this->syncOrderHandlingStatus($locked->order, $orderHandling);

                if (in_array($disposition, [
                    ParcelIncident::DISPOSITION_DECLARED_LOST,
                    ParcelIncident::DISPOSITION_DAMAGED_BEYOND_DELIVERY,
                    ParcelIncident::DISPOSITION_RETURNED_TO_SENDER,
                ], true)) {
                    OrderStatusHistory::create([
                        'order_id' => $locked->order->id,
                        'status' => $locked->order->status,
                        'changed_by' => $actor->id,
                        'changed_at' => $now,
                        'note' => "Incident {$locked->incident_number} disposition: " . (ParcelIncident::ALL_DISPOSITIONS[$disposition] ?? $disposition) . '.',
                    ]);
                }
            }

            $this->recordEvent($locked, $actor, 'disposition_applied', [
                'from_status' => $fromStatus,
                'to_status' => $newStatus,
                'disposition' => $disposition,
                'notes' => $cleanNotes,
                'evidence_path' => $evidencePath,
                'metadata' => [
                    'handling_restriction' => $restriction,
                    'financial_hold_status' => $financialHold,
                ],
            ]);

            AuditLog::log('parcel_incident_disposition_applied', $locked, [
                'investigation_status' => $fromStatus,
            ], [
                'investigation_status' => $newStatus,
                'disposition' => $disposition,
                'handling_restriction' => $restriction,
                'financial_hold_status' => $financialHold,
            ], $actor->id);

            $this->notifyIncidentStakeholders(
                $locked,
                'disposition_applied',
                "Incident Disposition: {$locked->incident_number}",
                "{$locked->incident_number} ({$locked->tracking_number}) disposition set to " . (ParcelIncident::ALL_DISPOSITIONS[$disposition] ?? $disposition) . '.'
            );

            return $locked->fresh(['events', 'order', 'manifestItem']);
        });
    }

    /**
     * Backward-compatible status transition method used by manifest modal, hardened with strict lifecycle rules.
     */
    public function updateIncidentStatus(
        ParcelIncident $incident,
        User $actor,
        string $newStatus,
        ?string $resolutionNotes = null,
        ?TransferManifest $manifest = null,
        ?string $disposition = null,
        ?string $recoveryLocation = null,
        ?UploadedFile $evidenceFile = null
    ): ParcelIncident {
        if ($manifest && (int) $incident->manifest_id !== (int) $manifest->id) {
            abort(404);
        }
        $this->authorizeLogisticsOperator($actor, $incident);

        if (! in_array($newStatus, ParcelIncident::ALL_STATUSES, true)) {
            throw ValidationException::withMessages([
                'investigation_status' => 'Invalid incident investigation status.',
            ]);
        }

        // Strict Phase 2 guard: Missing parcel CANNOT jump to resolved/closed without an explicit valid disposition
        if (in_array($newStatus, ParcelIncident::TERMINAL_STATUSES, true)) {
            if ($incident->incident_type === ParcelIncident::TYPE_MISSING) {
                $allowedMissingDispositions = [
                    ParcelIncident::DISPOSITION_RECOVERED_AND_RECEIVED,
                    ParcelIncident::DISPOSITION_DECLARED_LOST,
                    ParcelIncident::DISPOSITION_ESCALATED_TO_CLAIM,
                ];

                if ($disposition && in_array($disposition, $allowedMissingDispositions, true)) {
                    return $this->applyDisposition(
                        $incident,
                        $actor,
                        $disposition,
                        (string) $resolutionNotes,
                        $evidenceFile,
                        $newStatus === ParcelIncident::STATUS_CLOSED
                    );
                }

                // Only allow auto-resolved_and_received if it was already verified as recovered/received
                $isPhysicallyRecovered = $incident->found_at !== null
                    || $incident->recovered_at !== null
                    || ($incident->manifestItem && $incident->manifestItem->receiving_status === TransferManifestItem::RECEIVING_RECEIVED && $incident->source_stage === ParcelIncident::STAGE_MANIFEST_RECEIVING);

                if (! $isPhysicallyRecovered) {
                    throw ValidationException::withMessages([
                        'investigation_status' => 'An unreceived missing parcel cannot be marked resolved with notes alone. Confirm physical recovery or apply an authorized disposition (Declared Lost / Escalated to Claim).',
                    ]);
                }

                $disposition = ParcelIncident::DISPOSITION_RECOVERED_AND_RECEIVED;
            }

            if ($disposition) {
                return $this->applyDisposition(
                    $incident,
                    $actor,
                    $disposition,
                    (string) $resolutionNotes,
                    $evidenceFile,
                    $newStatus === ParcelIncident::STATUS_CLOSED
                );
            }

            // Default explicit disposition when resolving damage/discrepancy/found
            $defaultDisposition = match ($incident->incident_type) {
                ParcelIncident::TYPE_MISSING => ParcelIncident::DISPOSITION_RECOVERED_AND_RECEIVED,
                ParcelIncident::TYPE_WRONG_HUB => ParcelIncident::DISPOSITION_RESOLVED_BY_CORRECTION,
                default => ParcelIncident::DISPOSITION_INSPECTED_AND_CLEARED,
            };

            return $this->applyDisposition(
                $incident,
                $actor,
                $defaultDisposition,
                (string) ($resolutionNotes ?: 'Inspected and cleared by logistics operator.'),
                $evidenceFile,
                $newStatus === ParcelIncident::STATUS_CLOSED
            );
        }

        if ($newStatus === ParcelIncident::STATUS_FOUND) {
            return $this->recoverMissingParcel(
                $incident,
                $actor,
                $recoveryLocation ?: ($incident->hub?->name ?? 'Destination Sorting Hub'),
                (string) ($resolutionNotes ?: 'Missing parcel physically located.'),
                $evidenceFile,
                confirmPhysicalReceipt: false
            );
        }

        return DB::transaction(function () use ($incident, $newStatus, $resolutionNotes, $actor, $evidenceFile) {
            $locked = ParcelIncident::where('id', $incident->id)->lockForUpdate()->firstOrFail();
            $oldStatus = $locked->investigation_status;
            $evidencePath = $evidenceFile ? $evidenceFile->store('parcel-incidents', 'public') : null;

            $locked->update([
                'investigation_status' => $newStatus,
                'resolution_notes' => trim((string) ($resolutionNotes ?? '')) ?: $locked->resolution_notes,
                'evidence_path' => $evidencePath ?: $locked->evidence_path,
                'last_known_event' => 'Status moved to ' . ucwords(str_replace('_', ' ', $newStatus)),
            ]);

            $this->recordEvent($locked, $actor, 'status_changed', [
                'from_status' => $oldStatus,
                'to_status' => $newStatus,
                'notes' => trim((string) ($resolutionNotes ?? '')) ?: "Moved from {$oldStatus} to {$newStatus}.",
                'evidence_path' => $evidencePath,
            ]);

            AuditLog::log('parcel_incident_status_updated', $locked, [
                'investigation_status' => $oldStatus,
            ], [
                'investigation_status' => $newStatus,
                'resolution_notes' => $locked->resolution_notes,
            ], $actor->id);

            return $locked->fresh(['events']);
        });
    }

    /**
     * Resolve unresolved receiving-stage incidents when a parcel is subsequently verified as cleanly received.
     */
    public function resolveManifestReceivingIncidents(TransferManifestItem $item, User $actor, ?string $note = null): void
    {
        $resolvedNote = trim((string) $note) !== '' ? trim((string) $note) : 'Parcel verified and physically received cleanly.';
        $openIncidents = ParcelIncident::where('manifest_item_id', $item->id)
            ->whereIn('source_stage', [
                ParcelIncident::STAGE_MANIFEST_RECEIVING,
                ParcelIncident::STAGE_COURIER_TRANSIT,
                ParcelIncident::STAGE_MANIFEST_LOADING,
            ])
            ->whereIn('investigation_status', ParcelIncident::UNRESOLVED_STATUSES)
            ->lockForUpdate()
            ->get();

        foreach ($openIncidents as $incident) {
            $oldStatus = $incident->investigation_status;
            $now = now();
            $disposition = $incident->incident_type === ParcelIncident::TYPE_MISSING
                ? ParcelIncident::DISPOSITION_RECOVERED_AND_RECEIVED
                : ParcelIncident::DISPOSITION_INSPECTED_AND_CLEARED;

            $incident->update([
                'investigation_status' => ParcelIncident::STATUS_RESOLVED,
                'disposition' => $disposition,
                'handling_restriction' => ParcelIncident::RESTRICTION_CLEARED,
                'financial_hold_status' => ParcelIncident::FIN_HOLD_CLEARED,
                'resolved_by' => $actor->id,
                'found_at' => $incident->found_at ?: $now,
                'recovered_at' => $incident->incident_type === ParcelIncident::TYPE_MISSING ? ($incident->recovered_at ?: $now) : $incident->recovered_at,
                'recovered_by' => $incident->incident_type === ParcelIncident::TYPE_MISSING ? ($incident->recovered_by ?: $actor->id) : $incident->recovered_by,
                'resolved_at' => $now,
                'resolution_notes' => $resolvedNote,
                'last_known_event' => 'Verified and physically received at hub',
            ]);

            $item->update(['disposition' => $disposition]);

            if ($incident->order) {
                $this->syncOrderHandlingStatus($incident->order, null);
            }

            $this->recordEvent($incident, $actor, 'recovered_and_received', [
                'from_status' => $oldStatus,
                'to_status' => ParcelIncident::STATUS_RESOLVED,
                'disposition' => $disposition,
                'notes' => $resolvedNote,
            ]);

            AuditLog::log('parcel_incident_auto_resolved_on_recovery', $incident, [
                'investigation_status' => $oldStatus,
            ], [
                'investigation_status' => ParcelIncident::STATUS_RESOLVED,
                'disposition' => $disposition,
                'resolution_notes' => $resolvedNote,
            ], $actor->id);
        }
    }

    /**
     * Query unresolved incidents past SLA deadline (48h missing / 72h damage by default).
     */
    public function getOverdueIncidents(?int $logisticsCompanyId = null): EloquentCollection
    {
        return ParcelIncident::with(['order', 'manifest', 'hub', 'assignee'])
            ->overdue()
            ->when($logisticsCompanyId, fn ($q) => $q->where('logistics_company_id', $logisticsCompanyId))
            ->orderBy('due_at')
            ->get();
    }

    /**
     * Dispatch overdue investigation alerts without duplicate notification spam.
     */
    public function dispatchOverdueAlerts(?int $logisticsCompanyId = null): int
    {
        $overdue = $this->getOverdueIncidents($logisticsCompanyId);
        $dispatched = 0;

        foreach ($overdue as $incident) {
            $alreadyAlertedRecently = $incident->events()
                ->where('event_type', 'sla_overdue_alert_sent')
                ->where('occurred_at', '>=', now()->subMinutes(720))
                ->exists();

            if ($alreadyAlertedRecently) {
                continue;
            }

            $sent = $this->notifyIncidentStakeholders(
                $incident,
                'overdue_investigation',
                "Overdue Parcel Investigation: {$incident->incident_number}",
                "{$incident->incident_number} ({$incident->tracking_number}) exceeded its investigation SLA and requires immediate action.",
                ['due_at' => $incident->due_at?->toIso8601String()],
                dedupWindowMinutes: 720
            );

            if ($sent > 0) {
                $this->recordEvent($incident, null, 'sla_overdue_alert_sent', [
                    'to_status' => $incident->investigation_status,
                    'notes' => "SLA overdue alert dispatched to {$sent} stakeholder(s).",
                ]);
            }

            $dispatched += $sent;
        }

        return $dispatched;
    }

    public function recordEvent(ParcelIncident $incident, ?User $actor, string $eventType, array $attrs = []): ParcelIncidentEvent
    {
        return ParcelIncidentEvent::create([
            'incident_id' => $incident->id,
            'actor_id' => $actor?->id,
            'actor_role' => $actor?->role,
            'event_type' => $eventType,
            'from_status' => $attrs['from_status'] ?? null,
            'to_status' => $attrs['to_status'] ?? $incident->investigation_status,
            'disposition' => $attrs['disposition'] ?? $incident->disposition,
            'location' => $attrs['location'] ?? null,
            'notes' => $attrs['notes'] ?? null,
            'evidence_path' => $attrs['evidence_path'] ?? null,
            'metadata' => $attrs['metadata'] ?? null,
            'occurred_at' => $attrs['occurred_at'] ?? now(),
        ]);
    }

    /**
     * Role-scoped notification fan-out with deduplication guard.
     */
    public function notifyIncidentStakeholders(
        ParcelIncident $incident,
        string $event,
        string $title,
        string $message,
        array $extra = [],
        int $dedupWindowMinutes = 5
    ): int {
        $recipientIds = collect();

        if ($incident->logistics_company_id) {
            $recipientIds->push((int) $incident->logistics_company_id);
        }
        if ($incident->manifest) {
            $recipientIds->push((int) $incident->manifest->sender_logistics_id);
            $recipientIds->push((int) $incident->manifest->receiver_logistics_id);
        }
        if ($incident->assigned_to) {
            $recipientIds->push((int) $incident->assigned_to);
        }

        // Admins alerted on serious incidents, quarantine, permanent loss, claim escalation, and overdue alerts
        if (in_array($incident->incident_type, [ParcelIncident::TYPE_MISSING, ParcelIncident::TYPE_WRONG_HUB], true)
            || in_array($incident->disposition, [
                ParcelIncident::DISPOSITION_DECLARED_LOST,
                ParcelIncident::DISPOSITION_DAMAGED_BEYOND_DELIVERY,
                ParcelIncident::DISPOSITION_ESCALATED_TO_CLAIM,
            ], true)
            || in_array($event, ['overdue_investigation', 'quarantined', 'disposition_applied'], true)) {
            User::where('role', 'admin')->pluck('id')->each(fn ($id) => $recipientIds->push((int) $id));
        }

        $recipients = User::whereIn('id', $recipientIds->filter()->unique()->values())->get();
        $count = 0;

        foreach ($recipients as $user) {
            $alreadySent = DB::table('notifications')
                ->where('notifiable_type', User::class)
                ->where('notifiable_id', $user->id)
                ->where('type', ParcelIncidentNotification::class)
                ->where('created_at', '>=', now()->subMinutes($dedupWindowMinutes))
                ->get()
                ->contains(function ($row) use ($incident, $event) {
                    $data = json_decode((string) $row->data, true);
                    return is_array($data)
                        && (int) ($data['incident_id'] ?? 0) === (int) $incident->id
                        && ($data['event'] ?? '') === $event;
                });

            if (! $alreadySent) {
                $user->notify(new ParcelIncidentNotification($incident, $event, $title, $message, $extra));
                $count++;
            }
        }

        return $count;
    }

    protected function syncOrderHandlingStatus(?Order $order, ?string $restriction): void
    {
        if (! $order) {
            return;
        }

        $order->update([
            'handling_status' => $restriction === ParcelIncident::RESTRICTION_CLEARED ? null : $restriction,
        ]);
    }

    protected function defaultDueAt(string $type, \Carbon\CarbonInterface $from): \Carbon\CarbonInterface
    {
        $hours = $type === ParcelIncident::TYPE_MISSING ? self::MISSING_SLA_HOURS : self::DEFAULT_SLA_HOURS;

        return $from->copy()->addHours($hours);
    }

    protected function generateIncidentNumber(): string
    {
        do {
            $candidate = 'INC-' . now()->format('Ymd') . '-' . str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
        } while (ParcelIncident::where('incident_number', $candidate)->exists());

        return $candidate;
    }
}
