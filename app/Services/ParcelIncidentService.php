<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\ParcelIncident;
use App\Models\TransferManifest;
use App\Models\TransferManifestItem;
use App\Models\User;
use App\Notifications\TransferManifestNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ParcelIncidentService
{
    /**
     * Record or preserve an open manifest-receiving incident when an operator classifies a parcel
     * as missing, damaged, or discrepancy.
     */
    public function recordManifestExceptionIncident(
        TransferManifest $manifest,
        TransferManifestItem $item,
        string $classification,
        User $operator,
        string $notes,
        ?string $damageType = null,
        ?string $evidencePath = null
    ): ParcelIncident {
        $incidentType = match ($classification) {
            TransferManifestItem::RECEIVING_DAMAGED => ParcelIncident::TYPE_DAMAGE,
            TransferManifestItem::RECEIVING_MISSING => ParcelIncident::TYPE_MISSING,
            default => ParcelIncident::TYPE_DISCREPANCY,
        };

        $existing = ParcelIncident::where('order_id', $item->order_id)
            ->where('manifest_item_id', $item->id)
            ->where('incident_type', $incidentType)
            ->where('investigation_status', '!=', ParcelIncident::STATUS_RESOLVED)
            ->lockForUpdate()
            ->first();

        if ($existing) {
            return $existing;
        }

        $incident = ParcelIncident::create([
            'order_id' => $item->order_id,
            'manifest_id' => $manifest->id,
            'manifest_item_id' => $item->id,
            'hub_id' => $manifest->destination_hub_id,
            'logistics_company_id' => $manifest->receiver_logistics_id,
            'reported_by' => $operator->id,
            'tracking_number' => $item->tracking_number,
            'incident_type' => $incidentType,
            'damage_type' => $incidentType === ParcelIncident::TYPE_DAMAGE ? ($damageType ?: 'other') : null,
            'source_stage' => ParcelIncident::STAGE_MANIFEST_RECEIVING,
            'investigation_status' => ParcelIncident::STATUS_OPEN,
            'description' => trim($notes),
            'evidence_path' => $evidencePath,
            'reported_at' => now(),
        ]);

        if ($item->order) {
            OrderStatusHistory::create([
                'order_id' => $item->order_id,
                'status' => $item->order->status,
                'changed_by' => $operator->id,
                'note' => 'ISSUE FLAGGED (' . strtoupper($classification) . " on {$manifest->manifest_number}): " . trim($notes),
                'changed_at' => now(),
            ]);
        }

        return $incident;
    }

    /**
     * Report post-receipt damage on an already-received manifest parcel without altering
     * the original receiving_status, receipt timestamp, operator, or custody records.
     */
    public function reportPostReceiptDamage(
        TransferManifest $manifest,
        TransferManifestItem $item,
        User $operator,
        array $data,
        ?UploadedFile $evidence = null
    ): ParcelIncident {
        return DB::transaction(function () use ($manifest, $item, $operator, $data, $evidence) {
            $manifest = TransferManifest::whereKey($manifest->id)
                ->with(['destinationHub', 'senderLogistics'])
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless(
                (int) $manifest->receiver_logistics_id === (int) $operator->id,
                403,
                'Only authorized receiving Logistics operators can report damage on this manifest.'
            );

            $item = TransferManifestItem::whereKey($item->id)
                ->where('manifest_id', $manifest->id)
                ->with('order')
                ->lockForUpdate()
                ->firstOrFail();

            abort_if(
                $manifest->status === TransferManifest::STATUS_CANCELLED,
                422,
                'Cannot report damage on a cancelled manifest.'
            );

            abort_unless(
                $item->receiving_status === TransferManifestItem::RECEIVING_RECEIVED,
                422,
                'Post-receipt damage reporting is only available for parcels already classified as Received.'
            );

            $duplicateOpen = ParcelIncident::where('order_id', $item->order_id)
                ->where('incident_type', ParcelIncident::TYPE_DAMAGE)
                ->where('investigation_status', '!=', ParcelIncident::STATUS_RESOLVED)
                ->lockForUpdate()
                ->exists();

            abort_if(
                $duplicateOpen,
                422,
                "An unresolved damage report already exists for parcel {$item->tracking_number}."
            );

            $evidencePath = null;
            if ($evidence) {
                $evidencePath = $evidence->store("parcel-incidents/manifest-{$manifest->id}", 'public');
            }

            $damageType = (string) ($data['damage_type'] ?? 'other');
            $description = trim((string) ($data['description'] ?? ''));

            $incident = ParcelIncident::create([
                'order_id' => $item->order_id,
                'manifest_id' => $manifest->id,
                'manifest_item_id' => $item->id,
                'hub_id' => $manifest->destination_hub_id,
                'logistics_company_id' => $manifest->receiver_logistics_id,
                'reported_by' => $operator->id,
                'tracking_number' => $item->tracking_number,
                'incident_type' => ParcelIncident::TYPE_DAMAGE,
                'damage_type' => $damageType,
                'source_stage' => ParcelIncident::STAGE_POST_RECEIPT,
                'investigation_status' => ParcelIncident::STATUS_OPEN,
                'description' => $description,
                'evidence_path' => $evidencePath,
                'reported_at' => now(),
            ]);

            $damageLabel = ParcelIncident::DAMAGE_TYPES[$damageType] ?? ucwords(str_replace('_', ' ', $damageType));

            if ($item->order) {
                OrderStatusHistory::create([
                    'order_id' => $item->order_id,
                    'status' => $item->order->status,
                    'changed_by' => $operator->id,
                    'note' => "ISSUE FLAGGED (Post-Receipt Damage — {$damageLabel}): {$description}",
                    'changed_at' => now(),
                ]);
            }

            AuditLog::log(
                'manifest_parcel_damage_reported',
                $manifest,
                ['receiving_status' => $item->receiving_status],
                [
                    'incident_id' => $incident->id,
                    'tracking_number' => $item->tracking_number,
                    'damage_type' => $damageType,
                    'source_stage' => ParcelIncident::STAGE_POST_RECEIPT,
                    'evidence_path' => $evidencePath,
                ],
                $description,
                $operator->id
            );

            $this->notifySenderCompany(
                $manifest,
                'manifest_post_receipt_damage',
                'Post-Receipt Damage Reported',
                "Receiving hub reported damage ({$damageLabel}) on parcel {$item->tracking_number} from manifest {$manifest->manifest_number}: {$description}"
            );

            return $incident->fresh(['reporter', 'hub', 'manifest', 'order']);
        });
    }

    /**
     * Report an already-received parcel as missing/lost post-receipt without deleting
     * or altering its original receiving classification or custody history.
     */
    public function reportPostReceiptMissing(
        TransferManifest $manifest,
        TransferManifestItem $item,
        User $operator,
        string $description
    ): ParcelIncident {
        return DB::transaction(function () use ($manifest, $item, $operator, $description) {
            $manifest = TransferManifest::whereKey($manifest->id)
                ->with(['destinationHub', 'senderLogistics'])
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless(
                (int) $manifest->receiver_logistics_id === (int) $operator->id,
                403,
                'Only authorized receiving Logistics operators can report missing parcels on this manifest.'
            );

            $item = TransferManifestItem::whereKey($item->id)
                ->where('manifest_id', $manifest->id)
                ->with('order')
                ->lockForUpdate()
                ->firstOrFail();

            abort_if(
                $manifest->status === TransferManifest::STATUS_CANCELLED,
                422,
                'Cannot report missing parcels on a cancelled manifest.'
            );

            abort_unless(
                $item->receiving_status === TransferManifestItem::RECEIVING_RECEIVED,
                422,
                'Report Missing is for parcels already classified as Received. For unreceived pending parcels, use Classify -> Missing.'
            );

            $duplicateOpen = ParcelIncident::where('order_id', $item->order_id)
                ->where('incident_type', ParcelIncident::TYPE_MISSING)
                ->where('investigation_status', '!=', ParcelIncident::STATUS_RESOLVED)
                ->lockForUpdate()
                ->exists();

            abort_if(
                $duplicateOpen,
                422,
                "An unresolved missing parcel incident already exists for {$item->tracking_number}."
            );

            $cleanNotes = trim($description);

            $incident = ParcelIncident::create([
                'order_id' => $item->order_id,
                'manifest_id' => $manifest->id,
                'manifest_item_id' => $item->id,
                'hub_id' => $manifest->destination_hub_id,
                'logistics_company_id' => $manifest->receiver_logistics_id,
                'reported_by' => $operator->id,
                'tracking_number' => $item->tracking_number,
                'incident_type' => ParcelIncident::TYPE_MISSING,
                'source_stage' => ParcelIncident::STAGE_POST_RECEIPT,
                'investigation_status' => ParcelIncident::STATUS_OPEN,
                'description' => $cleanNotes,
                'reported_at' => now(),
            ]);

            if ($item->order) {
                OrderStatusHistory::create([
                    'order_id' => $item->order_id,
                    'status' => $item->order->status,
                    'changed_by' => $operator->id,
                    'note' => "ISSUE FLAGGED (Lost After Receipt on {$manifest->manifest_number}): {$cleanNotes}",
                    'changed_at' => now(),
                ]);
            }

            AuditLog::log(
                'manifest_parcel_missing_reported',
                $manifest,
                ['receiving_status' => $item->receiving_status],
                [
                    'incident_id' => $incident->id,
                    'tracking_number' => $item->tracking_number,
                    'source_stage' => ParcelIncident::STAGE_POST_RECEIPT,
                ],
                $cleanNotes,
                $operator->id
            );

            $this->notifySenderCompany(
                $manifest,
                'manifest_post_receipt_missing',
                'Parcel Reported Missing After Receipt',
                "Parcel {$item->tracking_number} from manifest {$manifest->manifest_number} was reported missing after receipt: {$cleanNotes}"
            );

            return $incident->fresh(['reporter', 'hub', 'manifest', 'order']);
        });
    }

    /**
     * Transition investigation status on a ParcelIncident (Open -> Under Investigation -> Found -> Resolved).
     */
    public function updateIncidentStatus(
        ParcelIncident $incident,
        User $operator,
        string $newStatus,
        ?string $resolutionNotes = null,
        ?TransferManifest $expectedManifest = null
    ): ParcelIncident {
        return DB::transaction(function () use ($incident, $operator, $newStatus, $resolutionNotes, $expectedManifest) {
            $incident = ParcelIncident::whereKey($incident->id)
                ->with(['manifest', 'manifestItem', 'order'])
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless(
                (int) $incident->logistics_company_id === (int) $operator->id,
                403,
                'Unauthorized: only the owning receiving logistics company may update this incident status.'
            );

            if ($expectedManifest !== null) {
                abort_unless(
                    (int) $incident->manifest_id === (int) $expectedManifest->id,
                    404,
                    'Incident does not belong to this transfer manifest.'
                );
            }

            abort_unless(
                in_array($newStatus, ParcelIncident::ALL_STATUSES, true),
                422,
                'Invalid investigation status.'
            );

            if ($newStatus === ParcelIncident::STATUS_FOUND && $incident->incident_type === ParcelIncident::TYPE_DAMAGE) {
                abort(422, 'Found status applies to missing or discrepancy parcel investigations.');
            }

            if ($incident->investigation_status === ParcelIncident::STATUS_RESOLVED && $newStatus === ParcelIncident::STATUS_RESOLVED) {
                abort(422, 'This incident is already resolved.');
            }

            // If an unreceived manifest item is still missing and operator tries to mark it Resolved directly
            // without completing physical receiving verification, require Marking Found + Complete Receiving first.
            if ($newStatus === ParcelIncident::STATUS_RESOLVED
                && $incident->incident_type === ParcelIncident::TYPE_MISSING
                && $incident->manifestItem
                && $incident->manifestItem->receiving_status === TransferManifestItem::RECEIVING_MISSING
                && trim((string) $resolutionNotes) === '') {
                abort(422, 'Mark the missing parcel as Found and complete receiving verification, or provide resolution notes explaining final disposition.');
            }

            $oldStatus = $incident->investigation_status;
            $cleanNote = $resolutionNotes !== null && trim($resolutionNotes) !== '' ? trim($resolutionNotes) : null;

            $updates = [
                'investigation_status' => $newStatus,
            ];

            if ($newStatus === ParcelIncident::STATUS_FOUND) {
                $updates['found_at'] = $incident->found_at ?? now();
            }

            if ($newStatus === ParcelIncident::STATUS_RESOLVED) {
                $updates['resolved_at'] = now();
                $updates['resolved_by'] = $operator->id;
            }

            if ($cleanNote !== null) {
                $updates['resolution_notes'] = $cleanNote;
            }

            $incident->update($updates);

            if ($incident->order) {
                $historyNote = 'INCIDENT ' . strtoupper(str_replace('_', ' ', $newStatus))
                    . " ({$incident->tracking_number}, {$incident->incident_type})"
                    . ($cleanNote ? ": {$cleanNote}" : '.');

                OrderStatusHistory::create([
                    'order_id' => $incident->order_id,
                    'status' => $incident->order->status,
                    'changed_by' => $operator->id,
                    'note' => $historyNote,
                    'changed_at' => now(),
                ]);
            }

            $auditTarget = $incident->manifest ?? $incident;
            AuditLog::log(
                'parcel_incident_status_updated',
                $auditTarget,
                ['investigation_status' => $oldStatus],
                [
                    'incident_id' => $incident->id,
                    'tracking_number' => $incident->tracking_number,
                    'incident_type' => $incident->incident_type,
                    'investigation_status' => $newStatus,
                ],
                $cleanNote,
                $operator->id
            );

            return $incident->fresh(['reporter', 'resolver', 'hub', 'manifest', 'manifestItem', 'order']);
        });
    }

    /**
     * Resolve open manifest-receiving incidents when an operator manually completes physical receiving.
     */
    public function resolveManifestReceivingIncidents(
        TransferManifestItem $item,
        User $operator,
        ?string $notes = null
    ): void {
        $openIncidents = ParcelIncident::where('manifest_item_id', $item->id)
            ->where('source_stage', ParcelIncident::STAGE_MANIFEST_RECEIVING)
            ->where('investigation_status', '!=', ParcelIncident::STATUS_RESOLVED)
            ->lockForUpdate()
            ->get();

        foreach ($openIncidents as $incident) {
            $incident->update([
                'investigation_status' => ParcelIncident::STATUS_RESOLVED,
                'found_at' => $incident->incident_type === ParcelIncident::TYPE_MISSING ? ($incident->found_at ?? now()) : $incident->found_at,
                'resolved_at' => now(),
                'resolved_by' => $operator->id,
                'resolution_notes' => trim((string) $notes) !== ''
                    ? trim((string) $notes)
                    : ($incident->incident_type === ParcelIncident::TYPE_MISSING
                        ? 'Parcel found and receiving verification completed.'
                        : 'Resolved upon manifest receiving verification.'),
            ]);
        }
    }

    protected function notifySenderCompany(TransferManifest $manifest, string $event, string $title, string $message): void
    {
        $sender = $manifest->senderLogistics;
        if (! $sender || (int) $sender->id === (int) $manifest->receiver_logistics_id) {
            return;
        }

        try {
            $sender->notify(new TransferManifestNotification($manifest, $event, $title, $message));
        } catch (\Throwable $e) {
            // Do not abort financial/incident transaction on notification failure
        }
    }
}
