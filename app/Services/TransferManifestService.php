<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Hub;
use App\Models\HubTransfer;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\ParcelIncident;
use App\Models\TransferManifest;
use App\Models\TransferManifestItem;
use App\Models\User;
use App\Notifications\TransferManifestNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransferManifestService
{
    public function __construct(
        protected InterRegionLogisticsService $interRegionLogisticsService
    ) {}

    /**
     * Create a new Transfer Manifest with selected eligible parcels and optional courier assignment.
     * Parcels remain in origin hub until loading verification + departure confirmation.
     */
    public function createManifest(User $operator, array $data): TransferManifest
    {
        return DB::transaction(function () use ($operator, $data) {
            $companyId = $operator->id;

            $originHub = Hub::where('id', $data['origin_hub_id'])
                ->where('logistics_company_id', $companyId)
                ->where('is_active', true)
                ->firstOrFail();

            $destinationHub = Hub::where('id', $data['destination_hub_id'])
                ->where('is_active', true)
                ->firstOrFail();

            abort_if(
                (int) $originHub->id === (int) $destinationHub->id,
                422,
                'Origin and destination hubs cannot be the same hub.'
            );

            $orderIds = array_values(array_unique(array_map('intval', $data['order_ids'] ?? [])));
            abort_if(empty($orderIds), 422, 'Select at least one eligible parcel for the manifest.');

            $orders = Order::whereIn('id', $orderIds)->lockForUpdate()->get();
            abort_if($orders->count() !== count($orderIds), 422, 'One or more selected parcels could not be found.');

            foreach ($orders as $order) {
                $currentCustody = $order->current_custody_logistics_id ?? ($order->pickup_logistics_id ?? $order->sorting_center_id);
                abort_unless(
                    (int) $currentCustody === (int) $companyId,
                    403,
                    "Parcel #{$order->order_number} is not in the custody of your logistics company."
                );

                abort_unless(
                    in_array($order->status, ['received_at_hub', 'at_sorting_center', 'sorted'], true),
                    422,
                    "Parcel #{$order->order_number} cannot be added in status '{$order->status}'."
                );

                abort_unless(
                    ! empty($order->current_hub_id) && (int) $order->current_hub_id === (int) $originHub->id,
                    422,
                    "Parcel #{$order->order_number} is not physically recorded at {$originHub->name}."
                );

                abort_if(
                    $order->transfers()->where('status', 'in_transit')->exists(),
                    422,
                    "Parcel #{$order->order_number} already has an active hub transfer in transit."
                );

                $alreadyInActiveManifest = TransferManifestItem::where('order_id', $order->id)
                    ->whereHas('manifest', fn ($q) => $q->whereIn('status', TransferManifest::ACTIVE_STATUSES))
                    ->exists();
                abort_if(
                    $alreadyInActiveManifest,
                    422,
                    "Parcel #{$order->order_number} is already assigned to an active transfer manifest."
                );

                abort_if(
                    $order->hasUnresolvedHandlingRestrictions(),
                    422,
                    "Parcel #{$order->order_number} has an unresolved damage or missing incident and cannot be dispatched."
                );
            }

            $assignedCourier = null;
            if (! empty($data['assigned_courier_id'])) {
                $assignedCourier = $this->validateAssignableCourier((int) $data['assigned_courier_id'], $companyId);
            }

            $manifestNumber = $this->generateManifestNumber();
            $initialStatus = $assignedCourier ? TransferManifest::STATUS_LOADING : TransferManifest::STATUS_DRAFT;

            $manifest = TransferManifest::create([
                'manifest_number' => $manifestNumber,
                'sender_logistics_id' => $originHub->logistics_company_id,
                'origin_hub_id' => $originHub->id,
                'receiver_logistics_id' => $destinationHub->logistics_company_id,
                'destination_hub_id' => $destinationHub->id,
                'assigned_courier_id' => $assignedCourier?->id,
                'vehicle_plate_number' => ! empty($data['vehicle_plate_number']) ? strtoupper(trim($data['vehicle_plate_number'])) : null,
                'status' => $initialStatus,
                'total_parcels' => $orders->count(),
                'created_by' => $operator->id,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($orders as $order) {
                TransferManifestItem::create([
                    'manifest_id' => $manifest->id,
                    'order_id' => $order->id,
                    'tracking_number' => $order->tracking_number ?: $order->order_number,
                    'loading_status' => TransferManifestItem::LOADING_PENDING,
                    'receiving_status' => TransferManifestItem::RECEIVING_PENDING,
                ]);
            }

            AuditLog::log(
                'transfer_manifest_created',
                $manifest,
                null,
                [
                    'manifest_number' => $manifest->manifest_number,
                    'status' => $manifest->status,
                    'total_parcels' => $manifest->total_parcels,
                    'assigned_courier_id' => $manifest->assigned_courier_id,
                ],
                null,
                $operator->id
            );

            if ($assignedCourier) {
                $this->notifyUser(
                    $assignedCourier,
                    $manifest,
                    'manifest_courier_assigned',
                    'New Transfer Manifest Assigned',
                    "Manifest {$manifest->manifest_number} ({$originHub->name} → {$destinationHub->name}, {$manifest->total_parcels} parcels) has been assigned to you."
                );
                $this->notifyUser(
                    $originHub->logisticsCompany,
                    $manifest,
                    'manifest_ready_for_loading',
                    'Manifest Ready for Loading',
                    "Manifest {$manifest->manifest_number} is ready for loading by {$assignedCourier->full_name}."
                );
            }

            return $manifest->fresh(['originHub', 'destinationHub', 'assignedCourier', 'items.order']);
        });
    }

    /**
     * Assign or reassign an authorized transfer courier to a manifest before dispatch.
     */
    public function assignCourier(
        TransferManifest $manifest,
        int $courierId,
        User $operator,
        ?string $vehiclePlateNumber = null
    ): TransferManifest {
        return DB::transaction(function () use ($manifest, $courierId, $operator, $vehiclePlateNumber) {
            $manifest = TransferManifest::whereKey($manifest->id)->lockForUpdate()->firstOrFail();

            abort_unless(
                (int) $manifest->sender_logistics_id === (int) $operator->id,
                403,
                'Only the sending logistics company can assign a transfer courier.'
            );

            abort_unless(
                in_array($manifest->status, [
                    TransferManifest::STATUS_DRAFT,
                    TransferManifest::STATUS_LOADING,
                    TransferManifest::STATUS_READY_TO_DISPATCH,
                ], true),
                422,
                'Cannot change courier after manifest has been dispatched or closed.'
            );

            $courier = $this->validateAssignableCourier($courierId, $operator->id, $manifest->id);

            $oldValues = [
                'assigned_courier_id' => $manifest->assigned_courier_id,
                'vehicle_plate_number' => $manifest->vehicle_plate_number,
                'status' => $manifest->status,
            ];

            $newStatus = $manifest->status === TransferManifest::STATUS_DRAFT
                ? TransferManifest::STATUS_LOADING
                : $manifest->status;

            $manifest->update([
                'assigned_courier_id' => $courier->id,
                'vehicle_plate_number' => $vehiclePlateNumber !== null && trim($vehiclePlateNumber) !== ''
                    ? strtoupper(trim($vehiclePlateNumber))
                    : $manifest->vehicle_plate_number,
                'status' => $newStatus,
            ]);

            AuditLog::log(
                'transfer_manifest_courier_assigned',
                $manifest,
                $oldValues,
                [
                    'assigned_courier_id' => $courier->id,
                    'vehicle_plate_number' => $manifest->vehicle_plate_number,
                    'status' => $manifest->status,
                ],
                null,
                $operator->id
            );

            $this->notifyUser(
                $courier,
                $manifest,
                'manifest_courier_assigned',
                'Transfer Manifest Assigned',
                "Manifest {$manifest->manifest_number} ({$manifest->total_parcels} parcels) has been assigned to you."
            );

            return $manifest->fresh(['originHub', 'destinationHub', 'assignedCourier', 'items']);
        });
    }

    /**
     * Courier scans/verifies a parcel during loading at the origin hub (idempotent).
     */
    public function scanLoadingParcel(TransferManifest $manifest, string $trackingNumber, User $courier): array
    {
        return DB::transaction(function () use ($manifest, $trackingNumber, $courier) {
            $manifest = TransferManifest::whereKey($manifest->id)->lockForUpdate()->firstOrFail();

            abort_unless(
                $courier->isCourier() && (int) $manifest->assigned_courier_id === (int) $courier->id,
                403,
                'You are not assigned to this transfer manifest.'
            );

            abort_if(
                $manifest->isTerminal(),
                422,
                'This manifest is already closed and cannot be modified.'
            );

            abort_unless(
                in_array($manifest->status, [
                    TransferManifest::STATUS_DRAFT,
                    TransferManifest::STATUS_LOADING,
                    TransferManifest::STATUS_READY_TO_DISPATCH,
                ], true),
                422,
                'Parcels can only be scanned for loading before departure.'
            );

            $normalized = trim($trackingNumber);
            $item = TransferManifestItem::where('manifest_id', $manifest->id)
                ->where(function ($q) use ($normalized) {
                    $q->where('tracking_number', $normalized)
                      ->orWhereHas('order', fn ($oq) => $oq->where('order_number', $normalized));
                })
                ->lockForUpdate()
                ->first();

            abort_unless(
                $item !== null,
                422,
                "Tracking number '{$normalized}' does not belong to manifest {$manifest->manifest_number}."
            );

            $alreadyLoaded = $item->loading_status === TransferManifestItem::LOADING_LOADED;

            if (! $alreadyLoaded) {
                $item->update([
                    'loading_status' => TransferManifestItem::LOADING_LOADED,
                    'loaded_at' => now(),
                    'loaded_by' => $courier->id,
                ]);

                AuditLog::log(
                    'transfer_manifest_parcel_loaded',
                    $manifest,
                    null,
                    ['item_id' => $item->id, 'tracking_number' => $item->tracking_number],
                    null,
                    $courier->id
                );
            }

            $totalCount = TransferManifestItem::where('manifest_id', $manifest->id)->count();
            $loadedCount = TransferManifestItem::where('manifest_id', $manifest->id)
                ->where('loading_status', TransferManifestItem::LOADING_LOADED)
                ->count();

            $newStatus = $loadedCount >= $totalCount && $totalCount > 0
                ? TransferManifest::STATUS_READY_TO_DISPATCH
                : TransferManifest::STATUS_LOADING;

            if ($manifest->status !== $newStatus) {
                $manifest->update(['status' => $newStatus]);
            }

            return [
                'already_loaded' => $alreadyLoaded,
                'item' => $item->fresh(),
                'manifest' => $manifest->fresh(),
                'loaded_count' => $loadedCount,
                'total_count' => $totalCount,
                'progress_text' => "{$loadedCount} of {$totalCount} parcels loaded.",
            ];
        });
    }

    /**
     * Courier confirms departure after all manifest parcels are loaded (idempotent if already in_transit).
     */
    public function confirmDeparture(
        TransferManifest $manifest,
        User $courier,
        ?string $vehiclePlateNumber = null
    ): TransferManifest {
        return DB::transaction(function () use ($manifest, $courier, $vehiclePlateNumber) {
            $manifest = TransferManifest::whereKey($manifest->id)
                ->with(['originHub', 'destinationHub', 'items.order'])
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless(
                $courier->isCourier() && (int) $manifest->assigned_courier_id === (int) $courier->id,
                403,
                'You are not assigned to this transfer manifest.'
            );

            if ($manifest->status === TransferManifest::STATUS_IN_TRANSIT) {
                return $manifest;
            }

            abort_unless(
                in_array($manifest->status, [
                    TransferManifest::STATUS_LOADING,
                    TransferManifest::STATUS_READY_TO_DISPATCH,
                ], true),
                422,
                "Manifest cannot depart in status '{$manifest->status}'."
            );

            $issueItemsCount = $manifest->items()
                ->where('loading_status', TransferManifestItem::LOADING_ISSUE)
                ->count();

            abort_if(
                $issueItemsCount > 0,
                422,
                "{$issueItemsCount} parcel(s) have unresolved loading issues. Remove affected parcels or resolve the loading incident before departure."
            );

            $unloadedCount = $manifest->items()
                ->where('loading_status', '!=', TransferManifestItem::LOADING_LOADED)
                ->count();

            abort_if(
                $unloadedCount > 0,
                422,
                "{$unloadedCount} parcel(s) are not yet scanned and loaded. Scan all parcels before departure."
            );

            $plateToSave = $vehiclePlateNumber !== null && trim($vehiclePlateNumber) !== ''
                ? strtoupper(trim($vehiclePlateNumber))
                : $manifest->vehicle_plate_number;

            foreach ($manifest->items as $item) {
                $order = Order::whereKey($item->order_id)->lockForUpdate()->firstOrFail();

                abort_if(
                    $order->hasUnresolvedHandlingRestrictions(),
                    422,
                    "Parcel #{$order->order_number} has an unresolved damage or missing incident and cannot be dispatched."
                );

                if (! $item->hub_transfer_id) {
                    $hubTransfer = $this->interRegionLogisticsService->dispatchHubTransfer(
                        $order,
                        $manifest->originHub,
                        $manifest->destinationHub,
                        $courier,
                        "Manifest {$manifest->manifest_number}" . ($plateToSave ? " | Vehicle: {$plateToSave}" : ''),
                        $manifest->id
                    );

                    $item->update(['hub_transfer_id' => $hubTransfer->id]);
                }
            }

            $manifest->update([
                'status' => TransferManifest::STATUS_IN_TRANSIT,
                'dispatched_at' => $manifest->dispatched_at ?? now(),
                'vehicle_plate_number' => $plateToSave,
            ]);

            AuditLog::log(
                'transfer_manifest_departed',
                $manifest,
                ['status' => TransferManifest::STATUS_READY_TO_DISPATCH],
                [
                    'status' => TransferManifest::STATUS_IN_TRANSIT,
                    'dispatched_at' => $manifest->dispatched_at?->toIso8601String(),
                    'vehicle_plate_number' => $plateToSave,
                    'courier_id' => $courier->id,
                ],
                null,
                $courier->id
            );

            $this->notifyUser(
                $manifest->senderLogistics,
                $manifest,
                'manifest_departed',
                'Transfer Manifest Departed',
                "Manifest {$manifest->manifest_number} departed {$manifest->originHub->name} with {$courier->full_name}."
            );
            if ($manifest->receiver_logistics_id !== $manifest->sender_logistics_id) {
                $this->notifyUser(
                    $manifest->receiverLogistics,
                    $manifest,
                    'manifest_departed',
                    'Incoming Transfer Manifest In Transit',
                    "Manifest {$manifest->manifest_number} ({$manifest->total_parcels} parcels) is in transit to {$manifest->destinationHub->name}."
                );
            }

            return $manifest->fresh(['originHub', 'destinationHub', 'assignedCourier', 'items.order']);
        });
    }

    /**
     * Courier reports arrival at destination hub. Does NOT complete transfer or change custody.
     */
    public function markArrived(TransferManifest $manifest, User $courier): TransferManifest
    {
        return DB::transaction(function () use ($manifest, $courier) {
            $manifest = TransferManifest::whereKey($manifest->id)
                ->with(['originHub', 'destinationHub'])
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless(
                $courier->isCourier() && (int) $manifest->assigned_courier_id === (int) $courier->id,
                403,
                'You are not assigned to this transfer manifest.'
            );

            if ($manifest->status === TransferManifest::STATUS_ARRIVED || ($manifest->status === TransferManifest::STATUS_EXCEPTION && $manifest->arrived_at !== null)) {
                return $manifest;
            }

            abort_unless(
                in_array($manifest->status, [
                    TransferManifest::STATUS_IN_TRANSIT,
                    TransferManifest::STATUS_EXCEPTION,
                ], true) && $manifest->dispatched_at !== null,
                422,
                "Manifest cannot be marked arrived from status '{$manifest->status}'."
            );

            $previousStatus = $manifest->status;
            $nextStatus = $previousStatus === TransferManifest::STATUS_EXCEPTION
                ? TransferManifest::STATUS_EXCEPTION
                : TransferManifest::STATUS_ARRIVED;

            $manifest->update([
                'status' => $nextStatus,
                'arrived_at' => $manifest->arrived_at ?? now(),
            ]);

            AuditLog::log(
                'transfer_manifest_arrived',
                $manifest,
                ['status' => $previousStatus],
                ['status' => $nextStatus, 'arrived_at' => $manifest->arrived_at?->toIso8601String()],
                null,
                $courier->id
            );

            $this->notifyUser(
                $manifest->receiverLogistics,
                $manifest,
                'manifest_arrived',
                'Transfer Manifest Arrived at Hub',
                "Manifest {$manifest->manifest_number} has arrived at {$manifest->destinationHub->name} and is awaiting parcel verification."
            );

            return $manifest->fresh(['originHub', 'destinationHub', 'assignedCourier', 'items']);
        });
    }

    /**
     * Courier reports a missing or damaged parcel issue on their assigned manifest.
     */
    public function reportCourierIssue(
        TransferManifest $manifest,
        string $trackingNumber,
        string $issueType,
        string $notes,
        User $courier,
        ?string $lastKnownLocation = null,
        ?UploadedFile $evidencePhoto = null
    ): TransferManifestItem {
        return DB::transaction(function () use ($manifest, $trackingNumber, $issueType, $notes, $courier, $lastKnownLocation, $evidencePhoto) {
            $manifest = TransferManifest::whereKey($manifest->id)
                ->with(['originHub', 'destinationHub', 'senderLogistics', 'receiverLogistics'])
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless(
                $courier->isCourier() && (int) $manifest->assigned_courier_id === (int) $courier->id,
                403,
                'You are not assigned to this transfer manifest.'
            );

            abort_if($manifest->isTerminal(), 422, 'Cannot report issues on a closed manifest.');
            abort_unless(in_array($issueType, ['missing', 'damaged', 'discrepancy'], true), 422, 'Invalid issue type.');

            $normalized = trim($trackingNumber);
            $item = TransferManifestItem::where('manifest_id', $manifest->id)
                ->where(function ($q) use ($normalized) {
                    $q->where('tracking_number', $normalized)
                      ->orWhereRaw('UPPER(tracking_number) = ?', [strtoupper($normalized)])
                      ->orWhereHas('order', fn ($oq) => $oq->where('order_number', $normalized));
                })
                ->with('order')
                ->lockForUpdate()
                ->firstOrFail();

            $item->update([
                'loading_status' => TransferManifestItem::LOADING_ISSUE,
                'issue_notes' => trim($notes),
            ]);

            if (in_array($manifest->status, [
                TransferManifest::STATUS_LOADING,
                TransferManifest::STATUS_READY_TO_DISPATCH,
                TransferManifest::STATUS_IN_TRANSIT,
                TransferManifest::STATUS_ARRIVED,
            ], true)) {
                $manifest->update(['status' => TransferManifest::STATUS_EXCEPTION]);
            }

            $incident = app(ParcelIncidentService::class)->recordCourierTransitIncident(
                $manifest,
                $item,
                $issueType,
                $notes,
                $courier,
                $lastKnownLocation,
                $evidencePhoto
            );

            AuditLog::log(
                'transfer_manifest_courier_issue',
                $manifest,
                null,
                [
                    'tracking_number' => $item->tracking_number,
                    'issue_type' => $issueType,
                    'incident_number' => $incident->incident_number,
                    'notes' => $notes,
                ],
                $notes,
                $courier->id
            );

            $this->notifyUser(
                $manifest->senderLogistics,
                $manifest,
                'manifest_issue_reported',
                'Parcel Issue Reported on Manifest',
                "Courier reported {$issueType} parcel ({$item->tracking_number}) on manifest {$manifest->manifest_number} ({$incident->incident_number}): {$notes}"
            );
            if ($manifest->receiver_logistics_id !== $manifest->sender_logistics_id) {
                $this->notifyUser(
                    $manifest->receiverLogistics,
                    $manifest,
                    'manifest_issue_reported',
                    'Parcel Issue Reported on Incoming Manifest',
                    "Courier reported {$issueType} parcel ({$item->tracking_number}) on manifest {$manifest->manifest_number} ({$incident->incident_number}): {$notes}"
                );
            }

            return $item->fresh(['incidents.events', 'order']);
        });
    }

    /**
     * Destination hub logistics operator scans/classifies an incoming parcel.
     * Custody transfers ONLY for accepted ('received') parcels via confirmHubTransferReceipt().
     */
    public function verifyReceivingParcel(
        TransferManifest $manifest,
        string $trackingNumber,
        string $classification,
        User $operator,
        ?string $notes = null,
        bool $autoScan = false,
        bool $acceptIntoQuarantine = false,
        ?UploadedFile $evidenceFile = null,
        ?string $damageType = null
    ): array {
        return DB::transaction(function () use ($manifest, $trackingNumber, $classification, $operator, $notes, $autoScan, $acceptIntoQuarantine, $evidenceFile, $damageType) {
            $manifest = TransferManifest::whereKey($manifest->id)
                ->with(['destinationHub', 'originHub'])
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless(
                (int) $manifest->receiver_logistics_id === (int) $operator->id,
                403,
                'Only authorized staff of the receiving logistics company can verify incoming parcels.'
            );

            $normalized = Order::normalizeScanIdentifier($trackingNumber);
            abort_unless($normalized !== '', 422, 'Scanned barcode is empty or invalid.');

            $item = TransferManifestItem::where('manifest_id', $manifest->id)
                ->where(function ($q) use ($normalized) {
                    $q->where('tracking_number', $normalized)
                      ->orWhereRaw('UPPER(tracking_number) = ?', [$normalized])
                      ->orWhereHas('order', fn ($oq) => $oq->where('order_number', $normalized)->orWhereRaw('UPPER(order_number) = ?', [$normalized]));
                })
                ->with(['hubTransfer', 'order'])
                ->lockForUpdate()
                ->first();

            if ($manifest->status === TransferManifest::STATUS_COMPLETED
                && $item !== null
                && $item->receiving_status === TransferManifestItem::RECEIVING_RECEIVED
                && $classification === TransferManifestItem::RECEIVING_RECEIVED) {
                return $this->buildReceivingSummary($manifest, $item, true);
            }

            abort_if(
                $manifest->isTerminal(),
                422,
                'This manifest is already completed or cancelled and cannot be processed again.'
            );

            abort_unless(
                in_array($manifest->status, [
                    TransferManifest::STATUS_ARRIVED,
                    TransferManifest::STATUS_RECEIVING,
                    TransferManifest::STATUS_EXCEPTION,
                ], true),
                422,
                'Manifest must arrive at the destination hub before receiving verification.'
            );

            abort_unless(
                in_array($classification, [
                    TransferManifestItem::RECEIVING_RECEIVED,
                    TransferManifestItem::RECEIVING_MISSING,
                    TransferManifestItem::RECEIVING_DAMAGED,
                    TransferManifestItem::RECEIVING_DISCREPANCY,
                ], true),
                422,
                'Invalid parcel verification classification.'
            );

            abort_unless(
                $item !== null,
                422,
                "Tracking number '{$normalized}' does not belong to manifest {$manifest->manifest_number}."
            );

            if ($item->receiving_status === TransferManifestItem::RECEIVING_RECEIVED && $classification === TransferManifestItem::RECEIVING_RECEIVED) {
                return $this->buildReceivingSummary($manifest, $item, true);
            }

            if ($autoScan
                && $classification === TransferManifestItem::RECEIVING_RECEIVED
                && (in_array($item->receiving_status, [
                    TransferManifestItem::RECEIVING_MISSING,
                    TransferManifestItem::RECEIVING_DAMAGED,
                    TransferManifestItem::RECEIVING_DISCREPANCY,
                ], true) || ($item->order && $item->order->hasBlockingFlagHistoryOrIncidents()))) {
                abort(
                    422,
                    "Parcel #{$item->tracking_number} is flagged for investigation (" . strtoupper($item->receiving_status) . ') and cannot be automatically accepted. Review manually before resolution.'
                );
            }

            if (in_array($classification, [
                TransferManifestItem::RECEIVING_MISSING,
                TransferManifestItem::RECEIVING_DAMAGED,
                TransferManifestItem::RECEIVING_DISCREPANCY,
            ], true)) {
                abort_if(
                    trim((string) $notes) === '',
                    422,
                    'Notes are required when classifying a parcel as missing, damaged, or discrepancy.'
                );
            }

            abort_if(
                $item->receiving_status === TransferManifestItem::RECEIVING_RECEIVED
                    && $classification !== TransferManifestItem::RECEIVING_RECEIVED,
                422,
                "Parcel #{$item->tracking_number} has already been received and cannot be reclassified as '{$classification}' without a controlled correction."
            );

            if ($classification === TransferManifestItem::RECEIVING_RECEIVED) {
                $hubTransfer = $item->hubTransfer
                    ?? HubTransfer::where('manifest_id', $manifest->id)->where('order_id', $item->order_id)->first();

                $alreadyReceivedForThisManifest = $hubTransfer !== null
                    && $hubTransfer->status === 'received'
                    && (int) $hubTransfer->manifest_id === (int) $manifest->id
                    && (int) $hubTransfer->receiver_hub_id === (int) $manifest->destination_hub_id
                    && (int) $hubTransfer->receiver_logistics_id === (int) $operator->id;

                abort_unless(
                    $hubTransfer !== null && ($hubTransfer->status === 'in_transit' || $alreadyReceivedForThisManifest),
                    422,
                    "Parcel #{$item->tracking_number} does not have a valid active in-transit hub transfer."
                );

                abort_unless(
                    (int) $hubTransfer->receiver_hub_id === (int) $manifest->destination_hub_id
                        && (int) $hubTransfer->receiver_logistics_id === (int) $operator->id,
                    403,
                    "Parcel #{$item->tracking_number} transfer destination does not match this receiving hub/company."
                );

                if ($hubTransfer->status === 'in_transit') {
                    $this->interRegionLogisticsService->confirmHubTransferReceipt($hubTransfer, $operator, $notes);
                }

                $item->update([
                    'receiving_status' => TransferManifestItem::RECEIVING_RECEIVED,
                    'received_at' => now(),
                    'received_by' => $operator->id,
                    'issue_notes' => $notes ? trim($notes) : $item->issue_notes,
                ]);

                app(ParcelIncidentService::class)->resolveManifestReceivingIncidents($item, $operator, $notes);
            } else {
                // Missing, damaged, or discrepancy: DO NOT transfer custody!
                $item->update([
                    'receiving_status' => $classification,
                    'received_at' => $acceptIntoQuarantine ? now() : null,
                    'received_by' => $acceptIntoQuarantine ? $operator->id : null,
                    'issue_notes' => $notes ? trim($notes) : $item->issue_notes,
                    'disposition' => $acceptIntoQuarantine ? ParcelIncident::DISPOSITION_ACCEPTED_INTO_QUARANTINE : $item->disposition,
                ]);

                // Phase 4: Damage Before Receiving with explicit Accept Into Quarantine receives physical custody at hub
                // while locking order in quarantine so dispatch is blocked until reinspection clears it.
                if ($acceptIntoQuarantine && in_array($classification, [TransferManifestItem::RECEIVING_DAMAGED, TransferManifestItem::RECEIVING_DISCREPANCY], true)) {
                    if ($item->hubTransfer && $item->hubTransfer->status === 'in_transit') {
                        $item->hubTransfer->update([
                            'status' => 'received',
                            'received_by' => $operator->id,
                            'received_at' => now(),
                            'notes' => trim(($item->hubTransfer->notes ?? '') . "\n[QUARANTINE] " . trim((string) $notes)),
                        ]);
                    }

                    if ($item->order) {
                        $order = $item->order;
                        $quarantineHubId = $manifest->destination_hub_id;
                        $targetOrderStatus = in_array($order->status, ['in_transit_hub', 'picked_up', 'ready_for_pickup', 'preparing'], true)
                            ? (((int) $quarantineHubId === (int) $order->destination_hub_id) ? 'at_sorting_center' : 'received_at_hub')
                            : $order->status;

                        if ($targetOrderStatus !== $order->status) {
                            OrderLogisticsService::transition(
                                order: $order,
                                newStatus: $targetOrderStatus,
                                changedBy: $operator->id,
                                note: "Parcel {$order->tracking_number} arrived at {$manifest->destinationHub?->name} and was placed under hub inspection hold.",
                                extraAttributes: [
                                    'current_hub_id' => $quarantineHubId,
                                    'current_custody_logistics_id' => $manifest->receiver_logistics_id,
                                    'sorting_center_id' => $manifest->receiver_logistics_id ?: $order->sorting_center_id,
                                    'handling_status' => ParcelIncident::RESTRICTION_QUARANTINED,
                                ]
                            );
                        } else {
                            $order->update([
                                'current_hub_id' => $quarantineHubId,
                                'current_custody_logistics_id' => $manifest->receiver_logistics_id,
                                'handling_status' => ParcelIncident::RESTRICTION_QUARANTINED,
                            ]);
                        }
                    }
                }

                app(ParcelIncidentService::class)->recordManifestExceptionIncident(
                    $manifest,
                    $item,
                    $classification,
                    $operator,
                    (string) $notes,
                    $evidenceFile,
                    $damageType,
                    $acceptIntoQuarantine
                );
            }

            AuditLog::log(
                'transfer_manifest_parcel_verified',
                $manifest,
                null,
                [
                    'tracking_number' => $item->tracking_number,
                    'classification' => $classification,
                    'notes' => $notes,
                ],
                $notes,
                $operator->id
            );

            // Recompute manifest status
            $counts = $this->getReceivingCounts($manifest->id);

            if ($counts['outstanding'] === 0 && $counts['missing'] === 0 && $counts['damaged'] === 0 && $counts['discrepancy'] === 0 && $counts['received'] === $counts['expected']) {
                $manifest->update([
                    'status' => TransferManifest::STATUS_COMPLETED,
                    'completed_at' => now(),
                ]);

                app(CompensationPolicyService::class)->creditTransferCourierForManifest($manifest->fresh(), false);

                AuditLog::log(
                    'transfer_manifest_completed',
                    $manifest,
                    null,
                    ['status' => TransferManifest::STATUS_COMPLETED],
                    null,
                    $operator->id
                );

                $this->notifyUser(
                    $manifest->senderLogistics,
                    $manifest,
                    'manifest_completed',
                    'Transfer Manifest Completed',
                    "Manifest {$manifest->manifest_number} has been fully verified and completed at {$manifest->destinationHub->name}."
                );
                if ($manifest->assignedCourier) {
                    $this->notifyUser(
                        $manifest->assignedCourier,
                        $manifest,
                        'manifest_completed',
                        'Transfer Manifest Completed',
                        "Manifest {$manifest->manifest_number} has been completed."
                    );
                }
            } elseif ($counts['missing'] > 0 || $counts['damaged'] > 0 || $counts['discrepancy'] > 0) {
                $manifest->update(['status' => TransferManifest::STATUS_EXCEPTION]);

                app(CompensationPolicyService::class)->creditTransferCourierForManifest($manifest->fresh(), true);

                $this->notifyUser(
                    $manifest->senderLogistics,
                    $manifest,
                    'manifest_issue_reported',
                    'Discrepancy on Transfer Manifest',
                    "Manifest {$manifest->manifest_number} has {$counts['missing']} missing, {$counts['damaged']} damaged, {$counts['discrepancy']} discrepancy parcel(s)."
                );
            } else {
                $manifest->update(['status' => TransferManifest::STATUS_RECEIVING]);

                if ($counts['outstanding'] > 0) {
                    $this->notifyUser(
                        $manifest->receiverLogistics,
                        $manifest,
                        'manifest_receiving_outstanding',
                        'Receiving Hub Has Outstanding Parcels',
                        "Manifest {$manifest->manifest_number} still has {$counts['outstanding']} outstanding parcel(s) awaiting scan."
                    );
                }
            }

            return $this->buildReceivingSummary($manifest->fresh(), $item->fresh(), false);
        });
    }

    /**
     * Cancel a manifest before it has been dispatched.
     */
    public function cancelManifest(TransferManifest $manifest, User $operator, ?string $reason = null): TransferManifest
    {
        return DB::transaction(function () use ($manifest, $operator, $reason) {
            $manifest = TransferManifest::whereKey($manifest->id)->lockForUpdate()->firstOrFail();

            abort_unless(
                (int) $manifest->sender_logistics_id === (int) $operator->id,
                403,
                'Only the sending logistics company can cancel this manifest.'
            );

            abort_unless(
                in_array($manifest->status, [
                    TransferManifest::STATUS_DRAFT,
                    TransferManifest::STATUS_LOADING,
                    TransferManifest::STATUS_READY_TO_DISPATCH,
                ], true),
                422,
                'Cannot cancel a manifest that has already departed or completed.'
            );

            $manifest->update([
                'status' => TransferManifest::STATUS_CANCELLED,
                'notes' => $reason ? trim("{$manifest->notes} | Cancelled: {$reason}") : $manifest->notes,
            ]);

            AuditLog::log(
                'transfer_manifest_cancelled',
                $manifest,
                null,
                ['status' => TransferManifest::STATUS_CANCELLED],
                $reason,
                $operator->id
            );

            return $manifest->fresh();
        });
    }

    /**
     * Append compatible parcels to an existing editable pre-dispatch manifest.
     */
    public function addParcelsToManifest(TransferManifest $manifest, array $orderIds, User $operator): TransferManifest
    {
        return DB::transaction(function () use ($manifest, $orderIds, $operator) {
            $manifest = TransferManifest::whereKey($manifest->id)
                ->with(['originHub', 'destinationHub'])
                ->lockForUpdate()
                ->firstOrFail();

            $companyId = $operator->id;

            abort_unless(
                (int) $manifest->sender_logistics_id === (int) $companyId,
                403,
                'Only the sending logistics company can add parcels to this manifest.'
            );

            abort_unless(
                $manifest->dispatched_at === null && in_array($manifest->status, [
                    TransferManifest::STATUS_DRAFT,
                    TransferManifest::STATUS_LOADING,
                    TransferManifest::STATUS_READY_TO_DISPATCH,
                ], true),
                422,
                "Cannot add parcels to manifest {$manifest->manifest_number} in status '{$manifest->status}'."
            );

            $cleanOrderIds = array_values(array_unique(array_map('intval', $orderIds)));
            abort_if(empty($cleanOrderIds), 422, 'Select at least one eligible parcel to add.');

            $orders = Order::whereIn('id', $cleanOrderIds)->lockForUpdate()->get();
            abort_if($orders->count() !== count($cleanOrderIds), 422, 'One or more selected parcels could not be found.');

            foreach ($orders as $order) {
                $currentCustody = $order->current_custody_logistics_id ?? ($order->pickup_logistics_id ?? $order->sorting_center_id);
                abort_unless(
                    (int) $currentCustody === (int) $companyId,
                    403,
                    "Parcel #{$order->order_number} is not in the custody of your logistics company."
                );

                abort_unless(
                    in_array($order->status, ['received_at_hub', 'at_sorting_center', 'sorted'], true),
                    422,
                    "Parcel #{$order->order_number} cannot be added in status '{$order->status}'."
                );

                abort_unless(
                    ! empty($order->current_hub_id) && (int) $order->current_hub_id === (int) $manifest->origin_hub_id,
                    422,
                    "Parcel #{$order->order_number} is not physically recorded at {$manifest->originHub->name}."
                );

                abort_if(
                    ! empty($order->destination_hub_id) && (int) $order->destination_hub_id !== (int) $manifest->destination_hub_id,
                    422,
                    "Parcel #{$order->order_number} is routed to a different destination hub than manifest {$manifest->manifest_number}."
                );

                abort_if(
                    $order->transfers()->where('status', 'in_transit')->exists(),
                    422,
                    "Parcel #{$order->order_number} already has an active hub transfer in transit."
                );

                $alreadyInActiveManifest = TransferManifestItem::where('order_id', $order->id)
                    ->whereHas('manifest', fn ($q) => $q->whereIn('status', TransferManifest::ACTIVE_STATUSES))
                    ->exists();
                abort_if(
                    $alreadyInActiveManifest,
                    422,
                    "Parcel #{$order->order_number} is already assigned to an active transfer manifest."
                );

                abort_if(
                    $order->hasUnresolvedHandlingRestrictions(),
                    422,
                    "Parcel #{$order->order_number} has an unresolved damage or missing incident and cannot be dispatched."
                );
            }

            foreach ($orders as $order) {
                TransferManifestItem::create([
                    'manifest_id' => $manifest->id,
                    'order_id' => $order->id,
                    'tracking_number' => $order->tracking_number ?: $order->order_number,
                    'loading_status' => TransferManifestItem::LOADING_PENDING,
                    'receiving_status' => TransferManifestItem::RECEIVING_PENDING,
                ]);
            }

            $newTotal = $manifest->items()->count();
            $nextStatus = $manifest->status === TransferManifest::STATUS_READY_TO_DISPATCH
                ? TransferManifest::STATUS_LOADING
                : $manifest->status;

            $manifest->update([
                'total_parcels' => $newTotal,
                'status' => $nextStatus,
            ]);

            AuditLog::log(
                'transfer_manifest_parcels_added',
                $manifest,
                null,
                [
                    'added_order_ids' => $cleanOrderIds,
                    'total_parcels' => $newTotal,
                    'status' => $nextStatus,
                ],
                null,
                $operator->id
            );

            return $manifest->fresh(['originHub', 'destinationHub', 'assignedCourier', 'items']);
        });
    }

    /**
     * Remove a parcel from an editable pre-dispatch manifest so it returns to eligible staging.
     */
    public function removeParcelFromManifest(TransferManifest $manifest, TransferManifestItem $item, User $operator): TransferManifest
    {
        return DB::transaction(function () use ($manifest, $item, $operator) {
            $manifest = TransferManifest::whereKey($manifest->id)->lockForUpdate()->firstOrFail();

            abort_unless(
                (int) $manifest->sender_logistics_id === (int) $operator->id,
                403,
                'Only the sending logistics company can remove parcels from this manifest.'
            );

            abort_unless(
                (int) $item->manifest_id === (int) $manifest->id,
                404,
                'Manifest item does not belong to this manifest.'
            );

            abort_unless(
                $manifest->dispatched_at === null && in_array($manifest->status, [
                    TransferManifest::STATUS_DRAFT,
                    TransferManifest::STATUS_LOADING,
                    TransferManifest::STATUS_READY_TO_DISPATCH,
                    TransferManifest::STATUS_EXCEPTION,
                ], true),
                422,
                "Cannot remove parcels from manifest {$manifest->manifest_number} in status '{$manifest->status}'."
            );

            $tracking = $item->tracking_number;
            $orderId = $item->order_id;
            $item->delete();

            $newTotal = $manifest->items()->count();
            $loadedCount = $manifest->items()->where('loading_status', TransferManifestItem::LOADING_LOADED)->count();
            $issueCount = $manifest->items()->where('loading_status', TransferManifestItem::LOADING_ISSUE)->count();

            $nextStatus = $manifest->status;
            if ($manifest->status === TransferManifest::STATUS_EXCEPTION && $issueCount === 0) {
                $nextStatus = ($newTotal > 0 && $loadedCount === $newTotal)
                    ? TransferManifest::STATUS_READY_TO_DISPATCH
                    : TransferManifest::STATUS_LOADING;
            }

            $manifest->update([
                'total_parcels' => $newTotal,
                'status' => $nextStatus,
            ]);

            AuditLog::log(
                'transfer_manifest_parcel_removed',
                $manifest,
                ['order_id' => $orderId, 'tracking_number' => $tracking],
                ['total_parcels' => $newTotal],
                null,
                $operator->id
            );

            return $manifest->fresh(['originHub', 'destinationHub', 'assignedCourier', 'items']);
        });
    }

    public function getReceivingCounts(int $manifestId): array
    {
        $items = TransferManifestItem::where('manifest_id', $manifestId)->get();

        $expected = $items->count();
        $received = $items->where('receiving_status', TransferManifestItem::RECEIVING_RECEIVED)->count();
        $missing = $items->where('receiving_status', TransferManifestItem::RECEIVING_MISSING)->count();
        $damaged = $items->where('receiving_status', TransferManifestItem::RECEIVING_DAMAGED)->count();
        $discrepancy = $items->where('receiving_status', TransferManifestItem::RECEIVING_DISCREPANCY)->count();
        $outstanding = $items->where('receiving_status', TransferManifestItem::RECEIVING_PENDING)->count();

        $incidents = ParcelIncident::where('manifest_id', $manifestId)->get();
        $unresolvedIncidents = $incidents->whereIn('investigation_status', ParcelIncident::UNRESOLVED_STATUSES);
        $quarantinedCount = $incidents->filter(fn (ParcelIncident $inc) => $inc->isQuarantined() && $inc->isUnresolved())->count();

        $exceptionItems = $items->whereIn('receiving_status', [
            TransferManifestItem::RECEIVING_MISSING,
            TransferManifestItem::RECEIVING_DAMAGED,
            TransferManifestItem::RECEIVING_DISCREPANCY,
        ]);

        $exceptionItemsDocumented = $exceptionItems->every(function (TransferManifestItem $item) use ($incidents) {
            return $incidents->where('manifest_item_id', $item->id)->isNotEmpty();
        });

        return [
            'expected' => $expected,
            'received' => $received,
            'missing' => $missing,
            'damaged' => $damaged,
            'discrepancy' => $discrepancy,
            'outstanding' => $outstanding,
            'quarantined' => $quarantinedCount,
            'open_incidents' => $unresolvedIncidents->count(),
            'open_damage_incidents' => $unresolvedIncidents->where('incident_type', ParcelIncident::TYPE_DAMAGE)->count(),
            'open_missing_incidents' => $unresolvedIncidents->where('incident_type', ParcelIncident::TYPE_MISSING)->count(),
            'can_close_with_exceptions' => $outstanding === 0
                && ($missing > 0 || $damaged > 0 || $discrepancy > 0)
                && $exceptionItemsDocumented,
        ];
    }

    /**
     * Controlled Phase 6 Closure: Close manifest with documented exceptions without fabricating receipts
     * and without double-paying courier incentives.
     */
    public function closeWithExceptions(
        TransferManifest $manifest,
        User $operator,
        string $reconciliationNotes
    ): TransferManifest {
        $cleanNotes = trim($reconciliationNotes);
        abort_if($cleanNotes === '', 422, 'Reconciliation notes explaining the exception closure are required.');

        return DB::transaction(function () use ($manifest, $operator, $cleanNotes) {
            $manifest = TransferManifest::whereKey($manifest->id)
                ->with(['items.incidents', 'originHub', 'destinationHub', 'senderLogistics', 'assignedCourier'])
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless(
                (int) $manifest->receiver_logistics_id === $operator->logisticsCompanyId() || $operator->role === 'admin',
                403,
                'Only authorized receiving hub logistics operators or admins can close a manifest with exceptions.'
            );

            abort_if(
                $manifest->status === TransferManifest::STATUS_CLOSED_WITH_EXCEPTIONS,
                422,
                'This manifest is already closed with exceptions.'
            );

            abort_if(
                $manifest->status === TransferManifest::STATUS_COMPLETED || $manifest->status === TransferManifest::STATUS_CANCELLED,
                422,
                "Manifest in status '{$manifest->status}' cannot be closed with exceptions."
            );

            abort_if(
                $manifest->arrived_at === null,
                422,
                'Manifest must arrive at destination hub before closing with exceptions.'
            );

            $pendingCount = $manifest->items->where('receiving_status', TransferManifestItem::RECEIVING_PENDING)->count();
            abort_if(
                $pendingCount > 0,
                422,
                "Cannot close manifest while {$pendingCount} parcel(s) are still Pending. Classify every parcel first."
            );

            foreach ($manifest->items as $item) {
                if (in_array($item->receiving_status, [
                    TransferManifestItem::RECEIVING_MISSING,
                    TransferManifestItem::RECEIVING_DAMAGED,
                    TransferManifestItem::RECEIVING_DISCREPANCY,
                ], true)) {
                    $hasIncident = $item->incidents->isNotEmpty();
                    abort_unless(
                        $hasIncident,
                        422,
                        "Exception parcel {$item->tracking_number} must have a documented Parcel Incident before closure."
                    );
                }
            }

            $now = now();
            $manifest->update([
                'status' => TransferManifest::STATUS_CLOSED_WITH_EXCEPTIONS,
                'closed_with_exceptions_at' => $now,
                'closed_with_exceptions_by' => $operator->id,
                'completed_at' => $manifest->completed_at ?: $now,
                'reconciliation_notes' => $cleanNotes,
            ]);

            // Idempotent exception trip payout — never double pays if already credited when entering exception status
            app(CompensationPolicyService::class)->creditTransferCourierForManifest($manifest->fresh(), true);

            $counts = $this->getReceivingCounts($manifest->id);

            AuditLog::log(
                'transfer_manifest_closed_with_exceptions',
                $manifest,
                null,
                [
                    'manifest_number' => $manifest->manifest_number,
                    'received' => $counts['received'],
                    'missing' => $counts['missing'],
                    'damaged' => $counts['damaged'],
                    'discrepancy' => $counts['discrepancy'],
                    'reconciliation_notes' => $cleanNotes,
                ],
                $cleanNotes,
                $operator->id
            );

            $this->notifyUser(
                $manifest->senderLogistics,
                $manifest,
                'manifest_closed_with_exceptions',
                "Manifest Closed With Exceptions: {$manifest->manifest_number}",
                "Manifest {$manifest->manifest_number} closed with {$counts['received']} received, {$counts['missing']} missing, {$counts['damaged']} damaged, {$counts['discrepancy']} discrepancy."
            );

            return $manifest->fresh(['items.incidents', 'originHub', 'destinationHub', 'assignedCourier', 'closedWithExceptionsBy']);
        });
    }

    protected function buildReceivingSummary(TransferManifest $manifest, TransferManifestItem $item, bool $alreadyVerified): array
    {
        return [
            'already_verified' => $alreadyVerified,
            'item' => $item,
            'manifest' => $manifest,
            'counts' => $this->getReceivingCounts($manifest->id),
        ];
    }

    protected function validateAssignableCourier(int $courierId, int $companyId, ?int $excludeManifestId = null): User
    {
        $courier = User::with('courierProfile')->find($courierId);

        abort_unless(
            $courier && $courier->isCourier(),
            422,
            'Selected user is not a Transfer Courier account.'
        );

        abort_unless(
            $courier->isApproved() && $courier->isActive(),
            422,
            'Selected Transfer Courier is not approved and active.'
        );

        $courierCompanyId = $courier->courierProfile?->logistics_company_id ?? $courier->courierProfile?->sorting_center_id;
        abort_unless(
            (int) $courierCompanyId === (int) $companyId,
            403,
            'Selected Transfer Courier does not belong to your logistics company.'
        );

        $overlappingTrip = TransferManifest::where('assigned_courier_id', $courier->id)
            ->when($excludeManifestId, fn ($q) => $q->where('id', '!=', $excludeManifestId))
            ->whereIn('status', [
                TransferManifest::STATUS_DRAFT,
                TransferManifest::STATUS_LOADING,
                TransferManifest::STATUS_READY_TO_DISPATCH,
                TransferManifest::STATUS_IN_TRANSIT,
                TransferManifest::STATUS_ARRIVED,
            ])
            ->exists();

        abort_if(
            $overlappingTrip,
            422,
            "{$courier->full_name} already has an active transfer manifest assigned."
        );

        return $courier;
    }

    protected function generateManifestNumber(): string
    {
        do {
            $number = 'MF-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
        } while (TransferManifest::where('manifest_number', $number)->exists());

        return $number;
    }

    protected function notifyUser(?User $user, TransferManifest $manifest, string $event, string $title, string $message): void
    {
        if (! $user) {
            return;
        }

        try {
            $user->notify(new TransferManifestNotification($manifest, $event, $title, $message));
        } catch (\Throwable $e) {
            // Never fail core transaction on notification dispatch
        }
    }
}
