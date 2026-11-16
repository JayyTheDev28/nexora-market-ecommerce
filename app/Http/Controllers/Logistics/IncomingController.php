<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\DeliveryArea;
use App\Models\Hub;
use App\Models\HubTransfer;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\TransferManifest;
use App\Models\TransferManifestItem;
use App\Services\InterRegionLogisticsService;
use App\Services\OrderLogisticsService;
use App\Services\ParcelIncidentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class IncomingController extends Controller
{
    public function __construct(
        protected InterRegionLogisticsService $logisticsService
    ) {}

    /**
     * Display incoming parcels awaiting receipt and recently received parcels.
     */
    public function index(Request $request): View
    {
        $companyId = $request->user()->id;
        $tab = $request->query('tab', 'awaiting');
        $search = $request->query('q');
        $hubFilter = $request->query('hub_id');

        $query = Order::with([
            'buyer',
            'address',
            'items.product.seller.sellerProfile',
            'items.product.seller.address',
            'pickupCourier',
            'deliveryArea',
            'originHub',
            'destinationHub',
            'currentHub',
        ]);

        $awaitingScope = function ($q) use ($companyId) {
            $q->where('status', 'picked_up')
                ->where(function ($pq) use ($companyId) {
                    $pq->where('pickup_logistics_id', $companyId)
                        ->orWhere(function ($lq) use ($companyId) {
                            $lq->whereNull('pickup_logistics_id')
                                ->where('sorting_center_id', $companyId);
                        });
                });
        };

        $receivedScope = function ($q) use ($companyId) {
            $q->whereIn('status', ['at_sorting_center', 'received_at_hub'])
                ->where(function ($cq) use ($companyId) {
                    $cq->where('current_custody_logistics_id', $companyId)
                        ->orWhere(function ($lq) use ($companyId) {
                            $lq->whereNull('current_custody_logistics_id')
                                ->where('sorting_center_id', $companyId);
                        });
                });
        };

        $allScope = function ($q) use ($companyId, $awaitingScope) {
            $q->where($awaitingScope)
                ->orWhere(function ($rq) use ($companyId) {
                    $rq->whereIn('status', ['at_sorting_center', 'received_at_hub', 'sorted'])
                        ->where(function ($cq) use ($companyId) {
                            $cq->where('current_custody_logistics_id', $companyId)
                                ->orWhere(function ($lq) use ($companyId) {
                                    $lq->whereNull('current_custody_logistics_id')
                                        ->where('sorting_center_id', $companyId);
                                });
                        });
                });
        };

        match ($tab) {
            'awaiting' => $query->where($awaitingScope),
            'received' => $query->where($receivedScope),
            'all' => $query->where($allScope),
            default => $query->where($awaitingScope),
        };

        if ($hubFilter) {
            $query->where(function ($q) use ($hubFilter) {
                $q->where('current_hub_id', $hubFilter)
                  ->orWhere('origin_hub_id', $hubFilter);
            });
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('tracking_number', 'like', "%{$search}%")
                    ->orWhereHas('buyer', fn ($bq) => $bq->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"))
                    ->orWhereHas('address', fn ($aq) => $aq->where('municipality_name', 'like', "%{$search}%")->orWhere('province_name', 'like', "%{$search}%"));
            });
        }

        $parcels = $query->latest()->paginate(15)->withQueryString();
        $counts = $this->buildCounts((int) $companyId);

        // Delivery areas managed by this sorting center for quick destination matching
        $deliveryAreas = DeliveryArea::where('sorting_center_id', $companyId)->get();
        $myHubs = Hub::where('logistics_company_id', $companyId)->where('is_active', true)->orderBy('name')->get();

        return view('logistics.incoming.index', compact('parcels', 'counts', 'tab', 'search', 'deliveryAreas', 'myHubs', 'hubFilter'));
    }

    /**
     * Lookup parcel details by scanned barcode without modifying custody.
     */
    public function lookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'hub_id' => ['nullable', 'integer', 'exists:hubs,id'],
        ]);

        $companyId = (int) $request->user()->id;
        $identifier = Order::normalizeScanIdentifier($validated['identifier']);

        if ($identifier === '') {
            return response()->json([
                'outcome' => 'invalid_barcode',
                'message' => 'Scanned barcode is empty or contains only invalid characters.',
            ], 422);
        }

        $selectedHub = null;
        if (! empty($validated['hub_id'])) {
            $selectedHub = Hub::where('id', $validated['hub_id'])
                ->where('logistics_company_id', $companyId)
                ->where('is_active', true)
                ->first();

            if (! $selectedHub) {
                return response()->json([
                    'outcome' => 'invalid_hub',
                    'message' => 'The selected hub does not belong to your logistics company or is inactive.',
                ], 403);
            }
        }

        $order = Order::findByScanIdentifier($identifier);
        if (! $order) {
            return response()->json([
                'outcome' => 'not_found',
                'message' => "Parcel with tracking or order number '{$identifier}' could not be found.",
            ], 404);
        }

        $order->load(['buyer', 'address', 'seller.sellerProfile', 'originHub', 'destinationHub', 'currentHub']);

        $activeManifestItem = TransferManifestItem::where('order_id', $order->id)
            ->whereHas('manifest', fn ($q) => $q->whereIn('status', TransferManifest::ACTIVE_STATUSES))
            ->with(['manifest.originHub', 'manifest.destinationHub'])
            ->latest()
            ->first();

        $activeTransferToCompany = HubTransfer::where('order_id', $order->id)
            ->where('status', 'in_transit')
            ->where('receiver_logistics_id', $companyId)
            ->latest()
            ->first();

        $hasCompanyOwnership = $this->isParcelAuthorizedForCompany($order, $companyId, $activeTransferToCompany);
        if (! $hasCompanyOwnership && ! $activeManifestItem) {
            return response()->json([
                'outcome' => 'unauthorized_company',
                'message' => 'You are not authorized to access or receive this parcel at your logistics company.',
            ], 403);
        }

        $hasHubs = Hub::where('logistics_company_id', $companyId)->where('is_active', true)->exists();
        $resolvedHub = $this->resolveVerifiedHub($order, $companyId, $selectedHub, $activeTransferToCompany);

        $requiresManifest = $activeManifestItem !== null;
        $hasCompanyCustody = $order->current_custody_logistics_id === null
            || (int) $order->current_custody_logistics_id === $companyId;
        $isAtOriginCompanyStill = ! $hasCompanyCustody && $order->status !== 'in_transit_hub';

        $alreadyPassed = $hasCompanyCustody
            && in_array($order->status, ['assigned_to_rider', 'out_for_delivery', 'delivered', 'completed'], true);
        $alreadyAtHub = $hasCompanyCustody
            && in_array($order->status, ['at_sorting_center', 'received_at_hub', 'sorted'], true)
            && (! $hasHubs || ($resolvedHub && (int) $order->current_hub_id === (int) $resolvedHub->id));

        $hubMismatchError = $this->detectHubMismatchError($order, $companyId, $selectedHub, $activeTransferToCompany);

        $eligibleForReceipt = ! $requiresManifest
            && ! $isAtOriginCompanyStill
            && ! $alreadyPassed
            && ! $alreadyAtHub
            && $hubMismatchError === null
            && (! $hasHubs || $resolvedHub !== null);

        $outcome = match (true) {
            $requiresManifest => 'manifest_required',
            $isAtOriginCompanyStill => 'not_eligible',
            $hubMismatchError !== null => 'wrong_hub',
            $alreadyAtHub || $alreadyPassed => 'already_received',
            $eligibleForReceipt => 'eligible',
            default => 'not_eligible',
        };

        $statusMessage = match ($outcome) {
            'manifest_required' => "Parcel belongs to active Transfer Manifest {$activeManifestItem->manifest->manifest_number}. Use Manifest Receiving Verification instead of general inbound check-in.",
            'wrong_hub' => $hubMismatchError,
            'already_received' => $alreadyPassed
                ? 'Parcel has already passed hub receiving (current status: ' . ucwords(str_replace('_', ' ', $order->status)) . ').'
                : 'Parcel is already checked in at ' . ($resolvedHub?->name ?? 'this facility') . '.',
            'eligible' => 'Parcel verified and ready to confirm receipt' . ($resolvedHub ? " at {$resolvedHub->name}" : '') . '.',
            default => $isAtOriginCompanyStill
                ? 'Parcel is still in custody of origin logistics and has not been dispatched to your facility.'
                : 'Parcel requires hub routing before receipt can be confirmed.',
        };

        return response()->json([
            'outcome' => $outcome,
            'data' => [
                'outcome' => $outcome,
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'tracking_number' => $order->tracking_number ?: $order->order_number,
                'status' => $order->status,
                'status_label' => ucwords(str_replace('_', ' ', $order->status)),
                'sender_name' => $order->seller?->sellerProfile?->business_name ?? $order->seller?->full_name ?? 'Merchant',
                'recipient_name' => $order->address?->recipient_name ?? $order->buyer?->full_name ?? 'Customer',
                'destination' => $order->formatted_destination,
                'origin_hub' => $order->originHub ? "{$order->originHub->name} ({$order->originHub->code})" : 'Unassigned',
                'destination_hub' => $order->destinationHub ? "{$order->destinationHub->name} ({$order->destinationHub->code})" : 'Unassigned',
                'current_hub' => $order->currentHub ? "{$order->currentHub->name} ({$order->currentHub->code})" : null,
                'resolved_hub' => $resolvedHub ? [
                    'id' => $resolvedHub->id,
                    'name' => $resolvedHub->name,
                    'code' => $resolvedHub->code,
                ] : null,
                'weight_kg' => (float) ($order->shipping_weight ?? 0),
                'cod_amount' => strtolower((string) $order->payment_method) === 'cod' ? (float) $order->total : 0.0,
                'payment_method' => strtoupper((string) $order->payment_method),
                'eligible_for_receipt' => $eligibleForReceipt,
                'already_received' => $alreadyAtHub || $alreadyPassed,
                'requires_manifest_receiving' => $requiresManifest,
                'manifest' => $activeManifestItem ? [
                    'id' => $activeManifestItem->manifest_id,
                    'manifest_number' => $activeManifestItem->manifest->manifest_number,
                    'status' => $activeManifestItem->manifest->status,
                    'url' => route('logistics.transfers.manifests.show', $activeManifestItem->manifest_id),
                ] : null,
                'status_message' => $statusMessage,
            ],
            'message' => $statusMessage,
        ], $hubMismatchError ? 422 : 200);
    }

    /**
     * Scan or enter a parcel identifier to receive it at the hub (transaction + row lock).
     */
    public function scan(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'hub_id' => ['nullable', 'exists:hubs,id'],
            'auto_scan' => ['nullable', 'boolean'],
        ]);

        $companyId = (int) $request->user()->id;
        $identifier = Order::normalizeScanIdentifier($validated['identifier']);
        $autoScan = $request->boolean('auto_scan');

        if ($identifier === '') {
            return $this->scanErrorResponse($request, 'invalid_barcode', 'Invalid or empty barcode identifier.', 422, 'identifier');
        }

        $selectedHub = null;
        if (! empty($validated['hub_id'])) {
            $selectedHub = Hub::where('id', $validated['hub_id'])
                ->where('logistics_company_id', $companyId)
                ->where('is_active', true)
                ->first();

            if (! $selectedHub) {
                return $this->scanErrorResponse(
                    $request,
                    'wrong_hub',
                    'The selected hub does not belong to your logistics company or is inactive.',
                    403,
                    'hub_id'
                );
            }
        }

        $preliminaryOrder = Order::findByScanIdentifier($identifier);
        if (! $preliminaryOrder) {
            return $this->scanErrorResponse(
                $request,
                'invalid_barcode',
                "Parcel with identifier '{$identifier}' could not be found.",
                404,
                'identifier'
            );
        }

        $txResult = DB::transaction(function () use ($request, $preliminaryOrder, $companyId, $selectedHub, $autoScan) {
            $order = Order::whereKey($preliminaryOrder->id)
                ->with(['buyer', 'address', 'seller.sellerProfile', 'originHub', 'destinationHub', 'currentHub'])
                ->lockForUpdate()
                ->first();

            if (! $order) {
                return [
                    'type' => 'error',
                    'outcome' => 'invalid_barcode',
                    'status' => 404,
                    'field' => 'identifier',
                    'message' => 'Parcel could not be found.',
                ];
            }

            // Critical protection: never allow general inbound scanner to bypass an active Transfer Manifest
            $activeManifestItem = TransferManifestItem::where('order_id', $order->id)
                ->whereHas('manifest', fn ($q) => $q->whereIn('status', TransferManifest::ACTIVE_STATUSES))
                ->with('manifest')
                ->latest()
                ->lockForUpdate()
                ->first();

            if ($activeManifestItem) {
                $manifestUrl = route('logistics.transfers.manifests.show', $activeManifestItem->manifest_id);

                return [
                    'type' => 'error',
                    'outcome' => 'manifest_required',
                    'status' => 422,
                    'field' => 'identifier',
                    'message' => "Parcel {$order->tracking_number} belongs to active Transfer Manifest {$activeManifestItem->manifest->manifest_number} ({$activeManifestItem->manifest->status}). Open the manifest receiving screen to verify it: {$manifestUrl}",
                    'manifest' => [
                        'id' => $activeManifestItem->manifest_id,
                        'manifest_number' => $activeManifestItem->manifest->manifest_number,
                        'status' => $activeManifestItem->manifest->status,
                        'url' => $manifestUrl,
                    ],
                ];
            }

            $activeTransferToCompany = HubTransfer::where('order_id', $order->id)
                ->where('status', 'in_transit')
                ->where('receiver_logistics_id', $companyId)
                ->latest()
                ->lockForUpdate()
                ->first();

            if (! $this->isParcelAuthorizedForCompany($order, $companyId, $activeTransferToCompany)) {
                return [
                    'type' => 'error',
                    'outcome' => 'unauthorized_company',
                    'status' => 403,
                    'field' => 'identifier',
                    'message' => 'Unauthorized: this parcel is not assigned to or transferring to your logistics company.',
                ];
            }

            if (in_array($order->status, ['cancelled', 'refunded', 'returned'], true)) {
                return [
                    'type' => 'error',
                    'outcome' => 'invalid_state',
                    'status' => 422,
                    'field' => 'identifier',
                    'message' => "Parcel {$order->tracking_number} is in a non-receivable status (" . ucwords(str_replace('_', ' ', $order->status)) . ').',
                ];
            }

            if ($autoScan && $order->hasBlockingFlagHistoryOrIncidents()) {
                return [
                    'type' => 'error',
                    'outcome' => 'flagged_for_investigation',
                    'status' => 422,
                    'field' => 'identifier',
                    'message' => "Parcel {$order->tracking_number} is flagged for investigation and cannot be automatically received.",
                ];
            }

            // Fallback for legacy sorting center users without configured hubs
            $hasHubs = Hub::where('logistics_company_id', $companyId)->where('is_active', true)->exists();

            if (! $hasHubs) {
                if (in_array($order->status, ['assigned_to_rider', 'out_for_delivery', 'delivered', 'completed'], true)) {
                    return [
                        'type' => 'already_received',
                        'order' => $order,
                        'hub' => null,
                        'message' => "Parcel {$order->order_number} has already passed incoming receiving (current status: " . ucwords(str_replace('_', ' ', $order->status)) . ').',
                    ];
                }

                if (in_array($order->status, ['at_sorting_center', 'received_at_hub', 'sorted'], true)) {
                    return [
                        'type' => 'already_received',
                        'order' => $order,
                        'hub' => null,
                        'message' => "Parcel {$order->order_number} is already checked in at this facility (current status: " . ucwords(str_replace('_', ' ', $order->status)) . ').',
                    ];
                }

                $matchedArea = DeliveryArea::where('sorting_center_id', $companyId)
                    ->where(function ($q) use ($order) {
                        if ($order->address?->municipality_name) {
                            $q->where('name', 'like', "%{$order->address->municipality_name}%");
                        }
                    })
                    ->first();

                $extra = [
                    'sorting_center_id' => $companyId,
                    'current_custody_logistics_id' => $companyId,
                ];

                if ($matchedArea && empty($order->delivery_area_id)) {
                    $extra['delivery_area_id'] = $matchedArea->id;
                }

                OrderLogisticsService::transition(
                    order: $order,
                    newStatus: 'at_sorting_center',
                    changedBy: $companyId,
                    note: 'Scanned and physically received at Sorting Hub (' . ($request->user()->sortingCenterProfile?->business_name ?? 'Central Hub') . ').',
                    extraAttributes: $extra
                );

                $msg = "Parcel {$order->order_number} successfully received and verified at sorting center.";
                if ($matchedArea) {
                    $msg .= " Automatically mapped to destination area: {$matchedArea->name}.";
                }

                return [
                    'type' => 'received',
                    'order' => $order->fresh(['originHub', 'destinationHub', 'currentHub', 'address', 'buyer', 'seller.sellerProfile']),
                    'hub' => null,
                    'message' => $msg,
                    'route_info' => $matchedArea ? "Mapped to {$matchedArea->name}" : 'Received at Sorting Center',
                ];
            }

            // Ensure order is routed with origin and destination hubs
            if (empty($order->origin_hub_id) || empty($order->destination_hub_id)) {
                $order = $this->logisticsService->matchLogisticsAndRouteOrder($order);
            }

            $hubMismatchError = $this->detectHubMismatchError($order, $companyId, $selectedHub, $activeTransferToCompany);
            if ($hubMismatchError !== null) {
                return [
                    'type' => 'error',
                    'outcome' => 'wrong_hub',
                    'status' => 422,
                    'field' => 'hub_id',
                    'order_id' => $order->id,
                    'tracking_number' => $order->tracking_number ?? $order->order_number,
                    'expected_hub_name' => $order->destinationHub?->name ?? $order->originHub?->name,
                    'actual_hub_id' => $selectedHub?->id,
                    'can_flag_wrong_hub' => $selectedHub !== null,
                    'wrong_hub_url' => route('logistics.incoming.wrong-hub', $order),
                    'message' => $hubMismatchError,
                ];
            }

            $hub = $this->resolveVerifiedHub($order, $companyId, $selectedHub, $activeTransferToCompany);
            if (! $hub) {
                return [
                    'type' => 'error',
                    'outcome' => 'wrong_hub',
                    'status' => 422,
                    'field' => 'hub_id',
                    'message' => 'No verified active hub matching this parcel route was found for your logistics company.',
                ];
            }

            // Guard: Prevent destination logistics from receiving a parcel still held at origin logistics before transfer
            if (
                $order->current_custody_logistics_id !== null
                && (int) $order->current_custody_logistics_id !== $companyId
                && $order->status !== 'in_transit_hub'
            ) {
                return [
                    'type' => 'error',
                    'outcome' => 'not_in_transit',
                    'status' => 422,
                    'field' => 'identifier',
                    'message' => "Parcel {$order->order_number} is still in custody of origin logistics and has not been dispatched to your hub.",
                ];
            }

            // Idempotency checks inside lock before making any transitions
            if (in_array($order->status, ['assigned_to_rider', 'out_for_delivery', 'delivered', 'completed'], true)) {
                return [
                    'type' => 'already_received',
                    'order' => $order,
                    'hub' => $hub,
                    'message' => "Parcel {$order->order_number} has already passed hub receiving (current status: " . ucwords(str_replace('_', ' ', $order->status)) . ').',
                ];
            }

            if (
                in_array($order->status, ['received_at_hub', 'at_sorting_center', 'sorted'], true)
                && (int) $order->current_hub_id === (int) $hub->id
                && ($order->current_custody_logistics_id === null || (int) $order->current_custody_logistics_id === $companyId)
            ) {
                return [
                    'type' => 'already_received',
                    'order' => $order,
                    'hub' => $hub,
                    'message' => "Parcel {$order->order_number} is already in the inventory of {$hub->name}.",
                ];
            }

            // Inter-hub historical non-manifest arrival
            if ($order->status === 'in_transit_hub') {
                if (! $activeTransferToCompany) {
                    return [
                        'type' => 'error',
                        'outcome' => 'wrong_hub',
                        'status' => 422,
                        'field' => 'identifier',
                        'message' => "Parcel {$order->order_number} is in transit to another logistics company.",
                    ];
                }

                $this->logisticsService->confirmHubTransferReceipt($activeTransferToCompany, $request->user());

                return [
                    'type' => 'received',
                    'order' => $order->fresh(['originHub', 'destinationHub', 'currentHub', 'address', 'buyer', 'seller.sellerProfile']),
                    'hub' => $hub,
                    'message' => "Transfer confirmed! Parcel {$order->order_number} received at destination hub {$hub->name} and added to parcel inventory.",
                    'route_info' => "Transfer received at {$hub->name}.",
                ];
            }

            $this->logisticsService->confirmReceiptAtHub($order, $hub, $request->user());

            $freshOrder = $order->fresh(['originHub', 'destinationHub', 'currentHub', 'address', 'buyer', 'seller.sellerProfile']);
            $routeInfo = (int) $freshOrder->origin_hub_id === (int) $freshOrder->destination_hub_id
                ? 'Direct local delivery from this hub.'
                : 'Staged for transfer to ' . ($freshOrder->destinationHub?->name ?? 'destination hub') . '.';

            return [
                'type' => 'received',
                'order' => $freshOrder,
                'hub' => $hub,
                'message' => "Parcel {$freshOrder->order_number} successfully received and verified at {$hub->name}. {$routeInfo}",
                'route_info' => $routeInfo,
            ];
        });

        if ($txResult['type'] === 'error') {
            return $this->scanErrorResponse(
                $request,
                $txResult['outcome'],
                $txResult['message'],
                $txResult['status'],
                $txResult['field'],
                $txResult['manifest'] ?? null
            );
        }

        if ($txResult['type'] === 'already_received') {
            if ($request->expectsJson()) {
                $order = $txResult['order'];
                $hub = $txResult['hub'];

                return response()->json([
                    'outcome' => 'already_received',
                    'newly_received' => false,
                    'already_received' => true,
                    'counts' => $this->buildCounts($companyId),
                    'data' => [
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                        'tracking_number' => $order->tracking_number ?: $order->order_number,
                        'status' => $order->status,
                        'status_label' => ucwords(str_replace('_', ' ', $order->status)),
                        'resolved_hub' => $hub ? ['id' => $hub->id, 'name' => $hub->name, 'code' => $hub->code] : null,
                    ],
                    'message' => $txResult['message'],
                ], 200);
            }

            return back()->with('info', $txResult['message']);
        }

        if ($request->expectsJson()) {
            $order = $txResult['order'];
            $hub = $txResult['hub'];
            $hubDisplay = $hub ? "{$hub->name} ({$hub->code})" : ($request->user()->sortingCenterProfile?->business_name ?? 'Central Hub');

            return response()->json([
                'outcome' => 'received',
                'newly_received' => true,
                'already_received' => false,
                'counts' => $this->buildCounts($companyId),
                'data' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'tracking_number' => $order->tracking_number ?: $order->order_number,
                    'status' => $order->status,
                    'status_label' => ucwords(str_replace('_', ' ', $order->status)),
                    'sender_name' => $order->seller?->sellerProfile?->business_name ?? $order->seller?->full_name ?? 'Merchant',
                    'recipient_name' => $order->address?->recipient_name ?? $order->buyer?->full_name ?? 'Customer',
                    'destination' => $order->formatted_destination,
                    'weight_kg' => (float) ($order->shipping_weight ?? 0),
                    'cod_amount' => strtolower((string) $order->payment_method) === 'cod' ? (float) $order->total : 0.0,
                    'payment_method' => strtoupper((string) $order->payment_method),
                    'resolved_hub_label' => $hubDisplay,
                    'resolved_hub' => $hub ? ['id' => $hub->id, 'name' => $hub->name, 'code' => $hub->code] : null,
                    'route_info' => $txResult['route_info'] ?? '',
                ],
                'message' => $txResult['message'],
            ], 200);
        }

        return back()->with('status', $txResult['message']);
    }

    private function buildCounts(int $companyId): array
    {
        $awaitingCount = Order::where('status', 'picked_up')
            ->where(function ($pq) use ($companyId) {
                $pq->where('pickup_logistics_id', $companyId)
                    ->orWhere(function ($lq) use ($companyId) {
                        $lq->whereNull('pickup_logistics_id')
                            ->where('sorting_center_id', $companyId);
                    });
            })->count();

        $receivedQuery = Order::where(function ($cq) use ($companyId) {
            $cq->where('current_custody_logistics_id', $companyId)
                ->orWhere(function ($lq) use ($companyId) {
                    $lq->whereNull('current_custody_logistics_id')
                        ->where('sorting_center_id', $companyId);
                });
        });

        $receivedCount = (clone $receivedQuery)->whereIn('status', ['at_sorting_center', 'received_at_hub'])->count();
        $sortedCount = (clone $receivedQuery)->where('status', 'sorted')->count();

        return [
            'awaiting' => $awaitingCount,
            'received' => $receivedCount,
            'all' => $awaitingCount + $receivedCount + $sortedCount,
        ];
    }

    private function scanErrorResponse(
        Request $request,
        string $outcome,
        string $message,
        int $statusCode,
        string $field,
        ?array $manifest = null
    ): RedirectResponse|JsonResponse {
        if ($request->expectsJson()) {
            return response()->json([
                'outcome' => $outcome,
                'newly_received' => false,
                'already_received' => false,
                'manifest' => $manifest,
                'message' => $message,
            ], $statusCode);
        }

        return back()->withInput()->withErrors([$field => $message]);
    }

    /**
     * Check whether the acting logistics company has ownership or incoming transfer rights for the parcel.
     */
    private function isParcelAuthorizedForCompany(Order $order, int $companyId, ?HubTransfer $activeTransferToCompany): bool
    {
        if ($activeTransferToCompany) {
            return true;
        }

        $ownerIds = array_filter([
            $order->current_custody_logistics_id ? (int) $order->current_custody_logistics_id : null,
            $order->pickup_logistics_id ? (int) $order->pickup_logistics_id : null,
            $order->delivery_logistics_id ? (int) $order->delivery_logistics_id : null,
            $order->sorting_center_id ? (int) $order->sorting_center_id : null,
        ]);

        // Preserve compatibility for legacy sorting-center orders with no company IDs populated yet
        if ($ownerIds === []) {
            return true;
        }

        return in_array($companyId, $ownerIds, true);
    }

    /**
     * Resolve the verified hub for this company without falling back to an arbitrary hub.
     */
    private function resolveVerifiedHub(Order $order, int $companyId, ?Hub $selectedHub, ?HubTransfer $activeTransfer): ?Hub
    {
        if ($order->status === 'in_transit_hub') {
            $expectedHubId = $activeTransfer?->receiver_hub_id ?? $order->destination_hub_id;
            if (! $expectedHubId) {
                return null;
            }

            if ($selectedHub && (int) $selectedHub->id !== (int) $expectedHubId) {
                return null;
            }

            return Hub::where('id', $expectedHubId)
                ->where('logistics_company_id', $companyId)
                ->where('is_active', true)
                ->first();
        }

        if ($selectedHub) {
            return $selectedHub;
        }

        if ($order->origin_hub_id) {
            $originHub = Hub::where('id', $order->origin_hub_id)
                ->where('logistics_company_id', $companyId)
                ->where('is_active', true)
                ->first();

            if ($originHub) {
                return $originHub;
            }
        }

        if ($order->destination_hub_id) {
            $destHub = Hub::where('id', $order->destination_hub_id)
                ->where('logistics_company_id', $companyId)
                ->where('is_active', true)
                ->first();

            if ($destHub) {
                return $destHub;
            }
        }

        // Only auto-select a single company hub when the company operates exactly one active hub
        $companyHubs = Hub::where('logistics_company_id', $companyId)->where('is_active', true)->get();

        return $companyHubs->count() === 1 ? $companyHubs->first() : null;
    }

    /**
     * Detect explicit hub selection mismatches against the verified route or transfer destination.
     */
    private function detectHubMismatchError(Order $order, int $companyId, ?Hub $selectedHub, ?HubTransfer $activeTransfer): ?string
    {
        if (! $selectedHub) {
            return null;
        }

        if ($order->status === 'in_transit_hub') {
            $expectedHubId = $activeTransfer?->receiver_hub_id ?? $order->destination_hub_id;
            if ($expectedHubId && (int) $selectedHub->id !== (int) $expectedHubId) {
                $expectedHub = Hub::find($expectedHubId);

                return "Hub mismatch: incoming transfer is destined for " . ($expectedHub?->name ?? 'another hub') . ", not {$selectedHub->name}.";
            }
        }

        if ($order->status === 'picked_up' && $order->origin_hub_id) {
            $originHub = Hub::find($order->origin_hub_id);
            if ($originHub && (int) $originHub->logistics_company_id === $companyId && (int) $originHub->id !== (int) $selectedHub->id) {
                return "Hub mismatch: parcel pickup is routed to {$originHub->name}, not {$selectedHub->name}.";
            }
        }

        return null;
    }

    /**
     * Flag an incoming parcel with an exception or issue.
     */
    public function flag(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'status' => $order->status,
            'changed_by' => $request->user()->id,
            'note' => "ISSUE FLAGGED: {$validated['reason']}",
            'changed_at' => now(),
        ]);

        return back()->with('status', "Recorded issue note on parcel {$order->order_number}.");
    }

    /**
     * Phase 9: Log a formal Wrong-Hub / Misrouted Parcel incident without silently overwriting historical hubs.
     */
    public function flagWrongHub(Request $request, Order $order): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'actual_hub_id' => ['required', 'integer', 'exists:hubs,id'],
            'notes' => ['required', 'string', 'min:5', 'max:1000'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        $actualHub = Hub::findOrFail((int) $validated['actual_hub_id']);
        $companyId = $request->user()->logisticsCompanyId();
        abort_unless((int) $actualHub->logistics_company_id === $companyId || $request->user()->role === 'admin', 403);

        $expectedHub = $order->destinationHub ?? $order->originHub;

        $incident = app(ParcelIncidentService::class)->recordWrongHubIncident(
            $order,
            $expectedHub,
            $actualHub,
            $request->user(),
            $validated['notes'],
            $validated['location'] ?? $actualHub->name
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => "Logged Wrong-Hub incident {$incident->incident_number} for parcel {$order->tracking_number}.",
                'incident' => $incident,
                'redirect_url' => route('logistics.exceptions.show', $incident),
            ], 201);
        }

        return redirect()
            ->route('logistics.exceptions.show', $incident)
            ->with('status', "Logged Wrong-Hub incident {$incident->incident_number}. Authorize a reroute leg below.");
    }
}
