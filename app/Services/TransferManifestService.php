<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Hub;
use App\Models\HubTransfer;
use App\Models\Order;
use App\Models\TransferManifest;
use App\Models\TransferManifestItem;
use App\Models\User;
use App\Notifications\TransferManifestNotification;
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
                if (empty($order->current_hub_id) || (int) ($order->currentHub?->logistics_company_id ?? $companyId) === (int) $companyId) {
                    $order->update([
                        'current_hub_id' => $originHub->id,
                        'current_custody_logistics_id' => $companyId,
                    ]);
                }

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

            if ($manifest->status === TransferManifest::STATUS_ARRIVED) {
                return $manifest;
            }

            abort_unless(
                $manifest->status === TransferManifest::STATUS_IN_TRANSIT,
                422,
                "Manifest cannot be marked arrived from status '{$manifest->status}'."
            );

            $manifest->update([
                'status' => TransferManifest::STATUS_ARRIVED,
                'arrived_at' => $manifest->arrived_at ?? now(),
            ]);

            AuditLog::log(
                'transfer_manifest_arrived',
                $manifest,
                ['status' => TransferManifest::STATUS_IN_TRANSIT],
                ['status' => TransferManifest::STATUS_ARRIVED, 'arrived_at' => $manifest->arrived_at?->toIso8601String()],
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
        User $courier
    ): TransferManifestItem {
        return DB::transaction(function () use ($manifest, $trackingNumber, $issueType, $notes, $courier) {
            $manifest = TransferManifest::whereKey($manifest->id)->lockForUpdate()->firstOrFail();

            abort_unless(
                $courier->isCourier() && (int) $manifest->assigned_courier_id === (int) $courier->id,
                403,
                'You are not assigned to this transfer manifest.'
            );

            abort_if($manifest->isTerminal(), 422, 'Cannot report issues on a closed manifest.');
            abort_unless(in_array($issueType, ['missing', 'damaged'], true), 422, 'Invalid issue type.');

            $normalized = trim($trackingNumber);
            $item = TransferManifestItem::where('manifest_id', $manifest->id)
                ->where(function ($q) use ($normalized) {
                    $q->where('tracking_number', $normalized)
                      ->orWhereHas('order', fn ($oq) => $oq->where('order_number', $normalized));
                })
                ->lockForUpdate()
                ->firstOrFail();

            $item->update([
                'loading_status' => TransferManifestItem::LOADING_ISSUE,
                'issue_notes' => trim($notes),
            ]);

            if ($manifest->status === TransferManifest::STATUS_IN_TRANSIT || $manifest->status === TransferManifest::STATUS_ARRIVED) {
                $manifest->update(['status' => TransferManifest::STATUS_EXCEPTION]);
            }

            AuditLog::log(
                'transfer_manifest_courier_issue',
                $manifest,
                null,
                [
                    'tracking_number' => $item->tracking_number,
                    'issue_type' => $issueType,
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
                "Courier reported {$issueType} parcel ({$item->tracking_number}) on manifest {$manifest->manifest_number}: {$notes}"
            );
            if ($manifest->receiver_logistics_id !== $manifest->sender_logistics_id) {
                $this->notifyUser(
                    $manifest->receiverLogistics,
                    $manifest,
                    'manifest_issue_reported',
                    'Parcel Issue Reported on Incoming Manifest',
                    "Courier reported {$issueType} parcel ({$item->tracking_number}) on manifest {$manifest->manifest_number}: {$notes}"
                );
            }

            return $item->fresh();
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
        ?string $notes = null
    ): array {
        return DB::transaction(function () use ($manifest, $trackingNumber, $classification, $operator, $notes) {
            $manifest = TransferManifest::whereKey($manifest->id)
                ->with(['destinationHub', 'originHub'])
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless(
                (int) $manifest->receiver_logistics_id === (int) $operator->id,
                403,
                'Only authorized staff of the receiving logistics company can verify incoming parcels.'
            );

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

            $normalized = trim($trackingNumber);
            $item = TransferManifestItem::where('manifest_id', $manifest->id)
                ->where(function ($q) use ($normalized) {
                    $q->where('tracking_number', $normalized)
                      ->orWhereHas('order', fn ($oq) => $oq->where('order_number', $normalized));
                })
                ->with('hubTransfer')
                ->lockForUpdate()
                ->first();

            abort_unless(
                $item !== null,
                422,
                "Tracking number '{$normalized}' does not belong to manifest {$manifest->manifest_number}."
            );

            if ($item->receiving_status === TransferManifestItem::RECEIVING_RECEIVED && $classification === TransferManifestItem::RECEIVING_RECEIVED) {
                return $this->buildReceivingSummary($manifest, $item, true);
            }

            if ($classification === TransferManifestItem::RECEIVING_RECEIVED) {
                $hubTransfer = $item->hubTransfer
                    ?? HubTransfer::where('manifest_id', $manifest->id)->where('order_id', $item->order_id)->first();

                if ($hubTransfer && $hubTransfer->status === 'in_transit') {
                    $this->interRegionLogisticsService->confirmHubTransferReceipt($hubTransfer, $operator, $notes);
                }

                $item->update([
                    'receiving_status' => TransferManifestItem::RECEIVING_RECEIVED,
                    'received_at' => now(),
                    'received_by' => $operator->id,
                    'issue_notes' => $notes ? trim($notes) : $item->issue_notes,
                ]);
            } else {
                // Missing, damaged, or discrepancy: DO NOT transfer custody!
                $item->update([
                    'receiving_status' => $classification,
                    'received_at' => now(),
                    'received_by' => $operator->id,
                    'issue_notes' => $notes ? trim($notes) : $item->issue_notes,
                ]);
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

    public function getReceivingCounts(int $manifestId): array
    {
        $items = TransferManifestItem::where('manifest_id', $manifestId)->get();

        $expected = $items->count();
        $received = $items->where('receiving_status', TransferManifestItem::RECEIVING_RECEIVED)->count();
        $missing = $items->where('receiving_status', TransferManifestItem::RECEIVING_MISSING)->count();
        $damaged = $items->where('receiving_status', TransferManifestItem::RECEIVING_DAMAGED)->count();
        $discrepancy = $items->where('receiving_status', TransferManifestItem::RECEIVING_DISCREPANCY)->count();
        $outstanding = $items->where('receiving_status', TransferManifestItem::RECEIVING_PENDING)->count();

        return [
            'expected' => $expected,
            'received' => $received,
            'missing' => $missing,
            'damaged' => $damaged,
            'discrepancy' => $discrepancy,
            'outstanding' => $outstanding,
        ];
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
