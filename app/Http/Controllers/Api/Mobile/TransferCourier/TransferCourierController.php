<?php

namespace App\Http\Controllers\Api\Mobile\TransferCourier;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Mobile\RiderProfileResource;
use App\Http\Resources\Api\Mobile\UserResource;
use App\Models\TransferManifest;
use App\Models\TransferManifestItem;
use App\Models\User;
use App\Services\TransferManifestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransferCourierController extends Controller
{
    public function __construct(
        protected TransferManifestService $manifestService
    ) {}

    /**
     * GET /api/mobile/v1/transfer-courier/dashboard
     */
    public function dashboard(Request $request): JsonResponse
    {
        /** @var User $courier */
        $courier = $request->user();
        $profile = $courier->courierProfile()->with(['logisticsCompany', 'sortingCenter', 'assignedHub'])->first();

        $activeManifest = TransferManifest::where('assigned_courier_id', $courier->id)
            ->whereIn('status', TransferManifest::ACTIVE_STATUSES)
            ->with(['originHub', 'destinationHub', 'items'])
            ->latest()
            ->first();

        $allManifests = TransferManifest::where('assigned_courier_id', $courier->id)
            ->withCount([
                'items',
                'items as loaded_items_count' => fn ($q) => $q->where('loading_status', TransferManifestItem::LOADING_LOADED),
                'items as issue_items_count' => fn ($q) => $q->where(function ($sq) {
                    $sq->where('loading_status', TransferManifestItem::LOADING_ISSUE)
                       ->orWhereIn('receiving_status', [
                           TransferManifestItem::RECEIVING_MISSING,
                           TransferManifestItem::RECEIVING_DAMAGED,
                           TransferManifestItem::RECEIVING_DISCREPANCY,
                       ]);
                }),
            ])
            ->get();

        $totalAssignedParcels = $allManifests->whereIn('status', TransferManifest::ACTIVE_STATUSES)->sum('items_count');
        $totalLoadedParcels = $allManifests->whereIn('status', TransferManifest::ACTIVE_STATUSES)->sum('loaded_items_count');
        $completedTransfers = $allManifests->where('status', TransferManifest::STATUS_COMPLETED)->count();
        $reportedExceptions = $allManifests->sum('issue_items_count') + $allManifests->where('status', TransferManifest::STATUS_EXCEPTION)->count();

        return response()->json([
            'data' => [
                'courier' => new UserResource($courier),
                'profile' => $profile ? new RiderProfileResource($profile) : null,
                'company_name' => $profile?->logisticsCompany?->full_name ?? $profile?->sortingCenter?->full_name ?? 'Logistics Partner',
                'assigned_hub' => $profile?->assignedHub ? [
                    'id' => $profile->assignedHub->id,
                    'name' => $profile->assignedHub->name,
                    'code' => $profile->assignedHub->code,
                ] : null,
                'active_manifest' => $activeManifest ? $this->formatManifest($activeManifest) : null,
                'stats' => [
                    'total_assigned_parcels' => (int) $totalAssignedParcels,
                    'total_loaded_parcels' => (int) $totalLoadedParcels,
                    'completed_transfers' => (int) $completedTransfers,
                    'reported_exceptions' => (int) $reportedExceptions,
                ],
            ],
            'message' => 'Transfer courier dashboard retrieved successfully.',
        ]);
    }

    /**
     * GET /api/mobile/v1/transfer-courier/manifests
     */
    public function manifests(Request $request): JsonResponse
    {
        /** @var User $courier */
        $courier = $request->user();
        $filter = $request->query('status', 'all');

        $query = TransferManifest::where('assigned_courier_id', $courier->id)
            ->with(['originHub', 'destinationHub', 'items'])
            ->latest();

        if ($filter === 'active') {
            $query->whereIn('status', TransferManifest::ACTIVE_STATUSES);
        } elseif ($filter === 'history') {
            $query->whereIn('status', [TransferManifest::STATUS_COMPLETED, TransferManifest::STATUS_CANCELLED]);
        }

        $manifests = $query->get()->map(fn (TransferManifest $m) => $this->formatManifest($m));

        return response()->json([
            'data' => $manifests,
            'message' => 'Assigned transfer manifests retrieved successfully.',
        ]);
    }

    /**
     * GET /api/mobile/v1/transfer-courier/manifests/{id}
     */
    public function showManifest(Request $request, int $id): JsonResponse
    {
        /** @var User $courier */
        $courier = $request->user();
        $manifest = $this->findAuthorizedManifest($courier, $id);

        return response()->json([
            'data' => $this->formatManifest($manifest, true),
            'message' => 'Transfer manifest retrieved successfully.',
        ]);
    }

    /**
     * GET /api/mobile/v1/transfer-courier/manifests/{id}/inventory
     */
    public function manifestInventory(Request $request, int $id): JsonResponse
    {
        /** @var User $courier */
        $courier = $request->user();
        $manifest = $this->findAuthorizedManifest($courier, $id);

        return response()->json([
            'data' => [
                'manifest_id' => $manifest->id,
                'manifest_number' => $manifest->manifest_number,
                'items' => $manifest->items->map(fn (TransferManifestItem $item) => $this->formatInventoryItem($item, $manifest))->values(),
            ],
            'message' => 'Manifest inventory retrieved successfully.',
        ]);
    }

    /**
     * GET /api/mobile/v1/transfer-courier/inventory
     * All parcels belonging to the authenticated courier's assigned manifests.
     */
    public function inventory(Request $request): JsonResponse
    {
        /** @var User $courier */
        $courier = $request->user();
        $search = trim((string) $request->query('q', ''));

        $items = TransferManifestItem::query()
            ->whereHas('manifest', fn ($q) => $q->where('assigned_courier_id', $courier->id))
            ->when($search !== '', fn ($q) => $q->where('tracking_number', 'like', "%{$search}%"))
            ->with(['manifest.originHub', 'manifest.destinationHub'])
            ->latest()
            ->get()
            ->map(fn (TransferManifestItem $item) => $this->formatInventoryItem($item, $item->manifest));

        return response()->json([
            'data' => $items->values(),
            'message' => 'Courier parcel inventory retrieved successfully.',
        ]);
    }

    /**
     * POST /api/mobile/v1/transfer-courier/manifests/{id}/scan
     */
    public function scanParcel(Request $request, int $id): JsonResponse
    {
        /** @var User $courier */
        $courier = $request->user();
        $manifest = $this->findAuthorizedManifest($courier, $id);

        $validated = $request->validate([
            'tracking_number' => ['required', 'string', 'max:100'],
        ]);

        $result = $this->manifestService->scanLoadingParcel($manifest, $validated['tracking_number'], $courier);

        return response()->json([
            'data' => [
                'already_loaded' => $result['already_loaded'],
                'loaded_count' => $result['loaded_count'],
                'total_count' => $result['total_count'],
                'progress_text' => $result['progress_text'],
                'manifest' => $this->formatManifest($result['manifest']->load(['originHub', 'destinationHub', 'items']), true),
            ],
            'message' => $result['already_loaded']
                ? "Parcel already scanned. {$result['progress_text']}"
                : "Parcel scanned and loaded. {$result['progress_text']}",
        ]);
    }

    /**
     * POST /api/mobile/v1/transfer-courier/manifests/{id}/depart
     */
    public function depart(Request $request, int $id): JsonResponse
    {
        /** @var User $courier */
        $courier = $request->user();
        $manifest = $this->findAuthorizedManifest($courier, $id);

        $validated = $request->validate([
            'vehicle_plate_number' => ['nullable', 'string', 'max:30'],
        ]);

        $updated = $this->manifestService->confirmDeparture(
            $manifest,
            $courier,
            $validated['vehicle_plate_number'] ?? null
        );

        return response()->json([
            'data' => $this->formatManifest($updated, true),
            'message' => "Manifest {$updated->manifest_number} departed and is now in transit.",
        ]);
    }

    /**
     * POST /api/mobile/v1/transfer-courier/manifests/{id}/arrive
     */
    public function arrive(Request $request, int $id): JsonResponse
    {
        /** @var User $courier */
        $courier = $request->user();
        $manifest = $this->findAuthorizedManifest($courier, $id);

        $updated = $this->manifestService->markArrived($manifest, $courier);

        return response()->json([
            'data' => $this->formatManifest($updated, true),
            'message' => "Arrival reported for manifest {$updated->manifest_number}. Awaiting destination hub verification.",
        ]);
    }

    /**
     * POST /api/mobile/v1/transfer-courier/manifests/{id}/report-issue
     */
    public function reportIssue(Request $request, int $id): JsonResponse
    {
        /** @var User $courier */
        $courier = $request->user();
        $manifest = $this->findAuthorizedManifest($courier, $id);

        $validated = $request->validate([
            'tracking_number' => ['required', 'string', 'max:100'],
            'issue_type' => ['required', 'string', 'in:missing,damaged'],
            'notes' => ['required', 'string', 'max:500'],
        ]);

        $item = $this->manifestService->reportCourierIssue(
            $manifest,
            $validated['tracking_number'],
            $validated['issue_type'],
            $validated['notes'],
            $courier
        );

        return response()->json([
            'data' => $this->formatInventoryItem($item, $manifest->fresh(['originHub', 'destinationHub'])),
            'message' => 'Parcel issue reported to logistics operators.',
        ]);
    }

    /**
     * GET /api/mobile/v1/transfer-courier/profile
     */
    public function profile(Request $request): JsonResponse
    {
        /** @var User $courier */
        $courier = $request->user();
        $profile = $courier->courierProfile()->with(['logisticsCompany', 'sortingCenter', 'assignedHub'])->first();

        return response()->json([
            'data' => [
                'user' => new UserResource($courier),
                'courier_profile' => $profile ? new RiderProfileResource($profile) : null,
                'company_name' => $profile?->logisticsCompany?->full_name ?? $profile?->sortingCenter?->full_name ?? 'Logistics Partner',
                'assigned_hub' => $profile?->assignedHub ? [
                    'id' => $profile->assignedHub->id,
                    'name' => $profile->assignedHub->name,
                    'code' => $profile->assignedHub->code,
                ] : null,
                'license_number' => $profile?->license_number,
                'license_expiry_date' => $profile?->license_expiry_date?->format('Y-m-d'),
                'license_status' => $profile?->license_expiry_date && $profile->license_expiry_date->isPast() ? 'Expired' : 'Valid',
                'account_status' => $courier->account_status,
            ],
            'message' => 'Transfer courier profile retrieved successfully.',
        ]);
    }

    protected function findAuthorizedManifest(User $courier, int $manifestId): TransferManifest
    {
        $manifest = TransferManifest::with(['originHub', 'destinationHub', 'items'])->findOrFail($manifestId);

        abort_unless(
            (int) $manifest->assigned_courier_id === (int) $courier->id,
            403,
            'Access denied. You are not assigned to this transfer manifest.'
        );

        return $manifest;
    }

    protected function formatManifest(TransferManifest $manifest, bool $includeItems = false): array
    {
        $items = $manifest->items;
        $loadedCount = $items->where('loading_status', TransferManifestItem::LOADING_LOADED)->count();
        $totalCount = $items->count() ?: $manifest->total_parcels;

        $data = [
            'id' => $manifest->id,
            'manifest_number' => $manifest->manifest_number,
            'status' => $manifest->status,
            'transfer_type' => $manifest->transfer_type,
            'origin_hub' => $manifest->originHub ? [
                'id' => $manifest->originHub->id,
                'name' => $manifest->originHub->name,
                'code' => $manifest->originHub->code,
                'city_name' => $manifest->originHub->city_name,
                'province_name' => $manifest->originHub->province_name,
            ] : null,
            'destination_hub' => $manifest->destinationHub ? [
                'id' => $manifest->destinationHub->id,
                'name' => $manifest->destinationHub->name,
                'code' => $manifest->destinationHub->code,
                'city_name' => $manifest->destinationHub->city_name,
                'province_name' => $manifest->destinationHub->province_name,
            ] : null,
            'total_parcels' => (int) $totalCount,
            'loaded_parcels' => (int) $loadedCount,
            'loading_progress' => "{$loadedCount} of {$totalCount} parcels loaded",
            'all_loaded' => $totalCount > 0 && $loadedCount >= $totalCount,
            'vehicle_plate_number' => $manifest->vehicle_plate_number,
            'dispatched_at' => $manifest->dispatched_at?->toIso8601String(),
            'arrived_at' => $manifest->arrived_at?->toIso8601String(),
            'completed_at' => $manifest->completed_at?->toIso8601String(),
            'notes' => $manifest->notes,
            'created_at' => $manifest->created_at?->toIso8601String(),
        ];

        if ($includeItems) {
            $data['items'] = $items->map(fn (TransferManifestItem $item) => $this->formatInventoryItem($item, $manifest))->values();
        }

        return $data;
    }

    protected function formatInventoryItem(TransferManifestItem $item, ?TransferManifest $manifest): array
    {
        return [
            'id' => $item->id,
            'manifest_id' => $item->manifest_id,
            'manifest_number' => $manifest?->manifest_number,
            'order_id' => $item->order_id,
            'tracking_number' => $item->tracking_number,
            'origin_hub_name' => $manifest?->originHub?->name,
            'destination_hub_name' => $manifest?->destinationHub?->name,
            'loading_status' => $item->loading_status,
            'loaded_at' => $item->loaded_at?->toIso8601String(),
            'receiving_status' => $item->receiving_status,
            'received_at' => $item->received_at?->toIso8601String(),
            'issue_notes' => $item->issue_notes,
        ];
    }
}
