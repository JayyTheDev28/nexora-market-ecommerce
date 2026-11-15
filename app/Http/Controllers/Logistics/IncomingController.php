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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $query->where(function ($q) use ($companyId) {
            $q->where('current_custody_logistics_id', $companyId)
              ->orWhere('pickup_logistics_id', $companyId)
              ->orWhere('delivery_logistics_id', $companyId)
              ->orWhere('sorting_center_id', $companyId);
        });

        match ($tab) {
            'awaiting' => $query->where('status', 'picked_up'),
            'received' => $query->whereIn('status', ['at_sorting_center', 'received_at_hub']),
            'all' => $query->whereIn('status', ['picked_up', 'at_sorting_center', 'received_at_hub', 'sorted']),
            default => $query->where('status', 'picked_up'),
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

        $baseCountQuery = fn () => Order::where(function ($q) use ($companyId) {
            $q->where('current_custody_logistics_id', $companyId)
              ->orWhere('pickup_logistics_id', $companyId)
              ->orWhere('delivery_logistics_id', $companyId)
              ->orWhere('sorting_center_id', $companyId);
        });

        $counts = [
            'awaiting' => (clone $baseCountQuery())->where('status', 'picked_up')->count(),
            'received' => (clone $baseCountQuery())->whereIn('status', ['at_sorting_center', 'received_at_hub'])->count(),
            'all' => (clone $baseCountQuery())->whereIn('status', ['picked_up', 'at_sorting_center', 'received_at_hub', 'sorted'])->count(),
        ];

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
        $identifier = trim($validated['identifier']);

        $selectedHub = null;
        if (! empty($validated['hub_id'])) {
            $selectedHub = Hub::where('id', $validated['hub_id'])
                ->where('logistics_company_id', $companyId)
                ->where('is_active', true)
                ->first();

            if (! $selectedHub) {
                return response()->json([
                    'message' => 'The selected hub does not belong to your logistics company or is inactive.',
                ], 403);
            }
        }

        $order = Order::findByScanIdentifier($identifier);
        if (! $order) {
            return response()->json([
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
                'message' => 'You are not authorized to access or receive this parcel at your logistics company.',
            ], 403);
        }

        $hasHubs = Hub::where('logistics_company_id', $companyId)->where('is_active', true)->exists();
        $resolvedHub = $this->resolveVerifiedHub($order, $companyId, $selectedHub, $activeTransferToCompany);

        $requiresManifest = $activeManifestItem !== null;
        $alreadyPassed = in_array($order->status, ['assigned_to_rider', 'out_for_delivery', 'delivered', 'completed'], true);
        $alreadyAtHub = in_array($order->status, ['at_sorting_center', 'received_at_hub', 'sorted'], true)
            && (! $hasHubs || ($resolvedHub && (int) $order->current_hub_id === (int) $resolvedHub->id));

        $hubMismatchError = $this->detectHubMismatchError($order, $companyId, $selectedHub, $activeTransferToCompany);

        $eligibleForReceipt = ! $requiresManifest
            && ! $alreadyPassed
            && ! $alreadyAtHub
            && $hubMismatchError === null
            && (! $hasHubs || $resolvedHub !== null);

        $statusMessage = match (true) {
            $requiresManifest => "Parcel belongs to active Transfer Manifest {$activeManifestItem->manifest->manifest_number}. Use Manifest Receiving Verification instead of general inbound check-in.",
            $hubMismatchError !== null => $hubMismatchError,
            $alreadyPassed => 'Parcel has already passed hub receiving (current status: ' . ucwords(str_replace('_', ' ', $order->status)) . ').',
            $alreadyAtHub => 'Parcel is already checked in at ' . ($resolvedHub?->name ?? 'this facility') . '.',
            $eligibleForReceipt => 'Parcel verified and ready to confirm receipt' . ($resolvedHub ? " at {$resolvedHub->name}" : '') . '.',
            default => 'Parcel requires hub routing before receipt can be confirmed.',
        };

        return response()->json([
            'data' => [
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
     * Scan or enter a parcel identifier to receive it at the hub.
     */
    public function scan(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'hub_id' => ['nullable', 'exists:hubs,id'],
        ]);

        $companyId = (int) $request->user()->id;
        $identifier = trim($validated['identifier']);

        $selectedHub = null;
        if (! empty($validated['hub_id'])) {
            $selectedHub = Hub::where('id', $validated['hub_id'])
                ->where('logistics_company_id', $companyId)
                ->where('is_active', true)
                ->first();

            if (! $selectedHub) {
                return back()->withInput()->withErrors([
                    'hub_id' => 'The selected hub does not belong to your logistics company or is inactive.',
                ]);
            }
        }

        $order = Order::findByScanIdentifier($identifier);

        if (! $order) {
            return back()->withInput()->withErrors([
                'identifier' => "Parcel with identifier '{$identifier}' could not be found.",
            ]);
        }

        // Critical protection: never allow general inbound scanner to bypass an active Transfer Manifest
        $activeManifestItem = TransferManifestItem::where('order_id', $order->id)
            ->whereHas('manifest', fn ($q) => $q->whereIn('status', TransferManifest::ACTIVE_STATUSES))
            ->with('manifest')
            ->latest()
            ->first();

        if ($activeManifestItem) {
            $manifestUrl = route('logistics.transfers.manifests.show', $activeManifestItem->manifest_id);

            return back()->withInput()->withErrors([
                'identifier' => "Parcel {$order->tracking_number} belongs to active Transfer Manifest {$activeManifestItem->manifest->manifest_number} ({$activeManifestItem->manifest->status}). Open the manifest receiving screen to verify it: {$manifestUrl}",
            ]);
        }

        $activeTransferToCompany = HubTransfer::where('order_id', $order->id)
            ->where('status', 'in_transit')
            ->where('receiver_logistics_id', $companyId)
            ->latest()
            ->first();

        if (! $this->isParcelAuthorizedForCompany($order, $companyId, $activeTransferToCompany)) {
            return back()->withInput()->withErrors([
                'identifier' => 'Unauthorized: this parcel is not assigned to or transferring to your logistics company.',
            ]);
        }

        // Fallback for legacy sorting center users without configured hubs
        $hasHubs = Hub::where('logistics_company_id', $companyId)->where('is_active', true)->exists();

        if (! $hasHubs) {
            if (in_array($order->status, ['assigned_to_rider', 'out_for_delivery', 'delivered', 'completed'])) {
                return back()->with('info', "Parcel {$order->order_number} has already passed incoming receiving (current status: " . ucwords(str_replace('_', ' ', $order->status)) . ").");
            }

            if (in_array($order->status, ['at_sorting_center', 'received_at_hub', 'sorted'])) {
                return back()->with('info', "Parcel {$order->order_number} is already checked in at this facility (current status: " . ucwords(str_replace('_', ' ', $order->status)) . ").");
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
                note: "Scanned and physically received at Sorting Hub (" . ($request->user()->sortingCenterProfile?->business_name ?? 'Central Hub') . ").",
                extraAttributes: $extra
            );

            $msg = "Parcel {$order->order_number} successfully received and verified at sorting center.";
            if ($matchedArea) {
                $msg .= " Automatically mapped to destination area: {$matchedArea->name}.";
            }

            return back()->with('status', $msg);
        }

        // Ensure order is routed with origin and destination hubs
        if (empty($order->origin_hub_id) || empty($order->destination_hub_id)) {
            $order = $this->logisticsService->matchLogisticsAndRouteOrder($order);
        }

        $hubMismatchError = $this->detectHubMismatchError($order, $companyId, $selectedHub, $activeTransferToCompany);
        if ($hubMismatchError !== null) {
            return back()->withInput()->withErrors([
                'hub_id' => $hubMismatchError,
            ]);
        }

        $hub = $this->resolveVerifiedHub($order, $companyId, $selectedHub, $activeTransferToCompany);

        if (! $hub) {
            return back()->withInput()->withErrors([
                'hub_id' => 'No verified active hub matching this parcel route was found for your logistics company.',
            ]);
        }

        // If parcel was traveling between hubs (non-manifest historical Hub Transfer arrival scan)
        if ($order->status === 'in_transit_hub') {
            if (! $activeTransferToCompany) {
                return back()->withInput()->withErrors([
                    'identifier' => "Parcel {$order->order_number} is in transit to another logistics company.",
                ]);
            }

            $this->logisticsService->confirmHubTransferReceipt($activeTransferToCompany, $request->user());

            return back()->with('status', "Transfer confirmed! Parcel {$order->order_number} received at destination hub {$hub->name} and added to parcel inventory.");
        }

        // Must be in acceptable receiving states
        if (in_array($order->status, ['assigned_to_rider', 'out_for_delivery', 'delivered', 'completed'])) {
            return back()->with('info', "Parcel {$order->order_number} has already passed hub receiving (current status: " . ucwords(str_replace('_', ' ', $order->status)) . ").");
        }

        // If already at hub and current_hub_id matches, idempotency note
        if (in_array($order->status, ['received_at_hub', 'at_sorting_center', 'sorted']) && (int) $order->current_hub_id === (int) $hub->id) {
            return back()->with('info', "Parcel {$order->order_number} is already in the inventory of {$hub->name}.");
        }

        // Process hub receipt
        $this->logisticsService->confirmReceiptAtHub($order, $hub, $request->user());

        $routeInfo = (int) $order->origin_hub_id === (int) $order->destination_hub_id
            ? "Direct local delivery from this hub."
            : "Staged for transfer to " . ($order->destinationHub?->name ?? 'destination hub') . ".";

        return back()->with('status', "Parcel {$order->order_number} successfully received and verified at {$hub->name}. {$routeInfo}");
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
}
