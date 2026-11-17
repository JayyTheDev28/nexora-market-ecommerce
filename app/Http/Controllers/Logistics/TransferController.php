<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Hub;
use App\Models\HubTransfer;
use App\Models\Order;
use App\Models\ParcelIncident;
use App\Models\TransferManifest;
use App\Models\TransferManifestItem;
use App\Models\User;
use App\Services\InterRegionLogisticsService;
use App\Services\ParcelIncidentService;
use App\Services\TransferManifestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class TransferController extends Controller
{
    public function __construct(
        protected InterRegionLogisticsService $logisticsService,
        protected TransferManifestService $manifestService,
        protected ParcelIncidentService $incidentService
    ) {}

    /**
     * Display hub transfers: incoming transfers, outgoing in-transit, and history.
     */
    public function index(Request $request): View
    {
        $companyId = $request->user()->id;
        $tab = $request->query('tab', 'incoming');
        $search = $request->query('q');

        $baseQuery = fn () => HubTransfer::query()->with([
            'order.items.product',
            'order.buyer',
            'senderLogistics',
            'senderHub',
            'receiverLogistics',
            'receiverHub',
            'dispatchedBy',
            'receivedBy',
        ]);

        if ($search) {
            $baseQuery = fn () => HubTransfer::query()->where(function ($q) use ($search) {
                $q->where('tracking_number', 'like', "%{$search}%")
                  ->orWhereHas('order', fn ($oq) => $oq->where('order_number', 'like', "%{$search}%"))
                  ->orWhereHas('senderLogistics', fn ($sq) => $sq->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"))
                  ->orWhereHas('receiverLogistics', fn ($rq) => $rq->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"));
            })->with([
                'order.items.product',
                'order.buyer',
                'senderLogistics',
                'senderHub',
                'receiverLogistics',
                'receiverHub',
                'dispatchedBy',
                'receivedBy',
            ]);
        }

        $query = match ($tab) {
            // Incoming: Dispatched by other companies or other hubs to our hubs, awaiting receipt
            'incoming' => $baseQuery()
                ->where('receiver_logistics_id', $companyId)
                ->where('status', 'in_transit'),

            // In Transit / Outgoing: Dispatched by us, traveling between hubs
            'in_transit' => $baseQuery()
                ->where('sender_logistics_id', $companyId)
                ->where('status', 'in_transit'),

            // Internal: Internal hub transfers within our own company
            'internal' => $baseQuery()
                ->where('sender_logistics_id', $companyId)
                ->where('receiver_logistics_id', $companyId),

            // Completed: Received transfers history
            'completed' => $baseQuery()
                ->where(function ($q) use ($companyId) {
                    $q->where('receiver_logistics_id', $companyId)
                      ->orWhere('sender_logistics_id', $companyId);
                })
                ->where('status', 'received'),

            default => $baseQuery()->where('receiver_logistics_id', $companyId)->where('status', 'in_transit'),
        };

        $transfers = $query->orderByDesc('dispatched_at')->orderByDesc('updated_at')->orderByDesc('id')->paginate(15)->withQueryString();

        $counts = [
            'incoming' => HubTransfer::where('receiver_logistics_id', $companyId)->where('status', 'in_transit')->count(),
            'in_transit' => HubTransfer::where('sender_logistics_id', $companyId)->where('status', 'in_transit')->count(),
            'internal' => HubTransfer::where('sender_logistics_id', $companyId)->where('receiver_logistics_id', $companyId)->count(),
            'completed' => HubTransfer::where(fn ($q) => $q->where('receiver_logistics_id', $companyId)->orWhere('sender_logistics_id', $companyId))->where('status', 'received')->count(),
        ];

        // Eligible parcels physically at an origin hub of this company, needing hub transfer,
        // not in transit, and not assigned to any active manifest.
        $candidateOrders = Order::where(function ($q) use ($companyId) {
                $q->where('current_custody_logistics_id', $companyId)
                  ->orWhere(function ($sub) use ($companyId) {
                      $sub->whereNull('current_custody_logistics_id')
                          ->where(fn ($w) => $w->where('pickup_logistics_id', $companyId)->orWhere('sorting_center_id', $companyId));
                  });
            })
            ->whereIn('status', ['received_at_hub', 'at_sorting_center', 'sorted'])
            ->whereDoesntHave('transfers', fn ($t) => $t->where('status', 'in_transit'))
            ->whereDoesntHave('manifestItems', fn ($mi) => $mi->whereHas('manifest', fn ($mq) => $mq->whereIn('status', TransferManifest::ACTIVE_STATUSES)))
            ->whereDoesntHave('manifestItems', fn ($mi) => $mi->where('receiving_status', TransferManifestItem::RECEIVING_RECEIVED)
                ->whereHas('manifest', fn ($mq) => $mq->where('status', TransferManifest::STATUS_COMPLETED)
                    ->whereColumn('transfer_manifests.destination_hub_id', 'orders.current_hub_id')))
            ->where(function ($q) {
                $q->whereNull('current_hub_id')
                  ->orWhereNull('destination_hub_id')
                  ->orWhereColumn('current_hub_id', '!=', 'destination_hub_id');
            })
            ->with(['buyer', 'address', 'currentHub.logisticsCompany', 'originHub.logisticsCompany', 'destinationHub.logisticsCompany', 'pickupLogistics', 'deliveryLogistics', 'unresolvedIncidents'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        // Separate cleanly routed parcels from parcels requiring routing review
        $eligibleForManifest = $candidateOrders
            ->filter(fn (Order $order) => $order->hasValidTransferRouting()
                && (int) $order->currentHub->logistics_company_id === (int) $companyId
                && ! $order->hasUnresolvedHandlingRestrictions())
            ->values();

        $routingReviewParcels = $candidateOrders
            ->reject(fn (Order $order) => $order->hasValidTransferRouting()
                && (int) $order->currentHub->logistics_company_id === (int) $companyId)
            ->values();

        $awaitingDispatch = $eligibleForManifest;

        $myHubs = Hub::where('logistics_company_id', $companyId)->where('is_active', true)->orderBy('name')->get();
        $allDestinationHubs = Hub::where('is_active', true)->with('logisticsCompany')->orderBy('name')->get();

        // Transfer Manifests for this company (as sender or receiver)
        $manifests = TransferManifest::where(function ($q) use ($companyId) {
                $q->where('sender_logistics_id', $companyId)
                  ->orWhere('receiver_logistics_id', $companyId);
            })
            ->with(['originHub', 'destinationHub', 'assignedCourier', 'senderLogistics', 'receiverLogistics', 'items', 'incidents'])
            ->latest('updated_at')
            ->latest('id')
            ->limit(25)
            ->get();

        // Editable pre-dispatch manifests owned by this company (for "Add to Existing Draft Manifest")
        $editableManifests = TransferManifest::where('sender_logistics_id', $companyId)
            ->whereNull('dispatched_at')
            ->whereIn('status', [
                TransferManifest::STATUS_DRAFT,
                TransferManifest::STATUS_LOADING,
                TransferManifest::STATUS_READY_TO_DISPATCH,
            ])
            ->with(['originHub', 'destinationHub', 'assignedCourier'])
            ->latest('updated_at')
            ->latest('id')
            ->get();

        // Approved active Transfer Couriers belonging to this company
        $transferCouriers = User::where('role', 'courier')
            ->where('approval_status', 'approved')
            ->where('account_status', 'active')
            ->whereHas('courierProfile', fn ($q) => $q->where('logistics_company_id', $companyId)->orWhere('sorting_center_id', $companyId))
            ->with(['courierProfile.assignedHub', 'assignedManifests' => fn ($q) => $q->whereIn('status', TransferManifest::ACTIVE_STATUSES)])
            ->orderBy('first_name')
            ->get();

        return view('logistics.transfers.index', compact(
            'transfers',
            'counts',
            'tab',
            'search',
            'awaitingDispatch',
            'routingReviewParcels',
            'myHubs',
            'allDestinationHubs',
            'manifests',
            'editableManifests',
            'eligibleForManifest',
            'transferCouriers'
        ));
    }

    /**
     * Legacy individual-parcel dispatch endpoint.
     * Blocked from bypassing the Transfer Manifest workflow.
     */
    public function dispatch(Request $request, Order $order): RedirectResponse
    {
        $companyId = $request->user()->id;
        $currentCustody = $order->current_custody_logistics_id ?? ($order->pickup_logistics_id ?? $order->sorting_center_id);
        abort_unless(
            (int) $currentCustody === (int) $companyId,
            403,
            'Parcel is not in the custody of your logistics company.'
        );

        abort(
            422,
            'Direct individual parcel dispatch is disabled. Create and dispatch a Transfer Manifest to transfer parcels between hubs.'
        );
    }

    /**
     * Confirm receipt of incoming transfer at our hub.
     * CUSTODY OFFICIALLY TRANSFERS TO RECEIVER HERE!
     */
    public function confirmReceipt(Request $request, HubTransfer $transfer): RedirectResponse
    {
        $companyId = $request->user()->id;

        abort_unless(
            (int) $transfer->receiver_logistics_id === (int) $companyId,
            403,
            'Unauthorized. You are not the receiving logistics company for this transfer.'
        );

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $this->logisticsService->confirmHubTransferReceipt(
            $transfer,
            $request->user(),
            $validated['notes'] ?? null
        );

        return back()->with('status', "Transfer confirmed! Parcel #{$transfer->order->order_number} received at your hub. Custody transferred to your company.");
    }

    /**
     * Create a multi-parcel Transfer Manifest.
     */
    public function storeManifest(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'origin_hub_id' => ['required', 'integer', 'exists:hubs,id'],
            'destination_hub_id' => ['required', 'integer', 'exists:hubs,id', 'different:origin_hub_id'],
            'order_ids' => ['required', 'array', 'min:1'],
            'order_ids.*' => ['integer', 'exists:orders,id'],
            'assigned_courier_id' => ['nullable', 'integer', 'exists:users,id'],
            'vehicle_plate_number' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'destination_hub_id.different' => 'Destination hub cannot be the same as the origin or current hub.',
        ]);

        $manifest = $this->manifestService->createManifest($request->user(), $validated);

        return redirect()->route('logistics.transfers.manifests.show', $manifest)
            ->with('status', "Transfer Manifest {$manifest->manifest_number} created with {$manifest->total_parcels} parcel(s).");
    }

    /**
     * Show Transfer Manifest details, loading progress, and receiving verification.
     */
    public function showManifest(Request $request, TransferManifest $manifest): View
    {
        $companyId = $request->user()->id;
        abort_unless(
            (int) $manifest->sender_logistics_id === (int) $companyId
            || (int) $manifest->receiver_logistics_id === (int) $companyId,
            403,
            'Unauthorized access to this transfer manifest.'
        );

        $manifest->load([
            'originHub',
            'destinationHub',
            'senderLogistics',
            'receiverLogistics',
            'assignedCourier.courierProfile',
            'items.order',
            'items.hubTransfer',
            'items.receivedByUser',
            'items.incidents.reporter',
            'items.incidents.resolver',
            'items.incidents.hub',
            'items.incidents.events.actor',
            'incidents.reporter',
            'incidents.resolver',
            'incidents.hub',
            'incidents.events.actor',
        ]);

        $receivingCounts = $this->manifestService->getReceivingCounts($manifest->id);
        $transferCouriers = User::where('role', 'courier')
            ->where('approval_status', 'approved')
            ->where('account_status', 'active')
            ->whereHas('courierProfile', fn ($q) => $q->where('logistics_company_id', $companyId)->orWhere('sorting_center_id', $companyId))
            ->with(['assignedManifests' => fn ($q) => $q->whereIn('status', TransferManifest::ACTIVE_STATUSES)])
            ->orderBy('first_name')
            ->get();

        $auditLogs = \App\Models\AuditLog::where('auditable_type', TransferManifest::class)
            ->where('auditable_id', $manifest->id)
            ->with('operator')
            ->latest('created_at')
            ->latest('id')
            ->get();

        return view('logistics.transfers.manifest_show', compact('manifest', 'receivingCounts', 'transferCouriers', 'auditLogs'));
    }

    /**
     * Assign or update transfer courier on a manifest before dispatch.
     */
    public function assignManifestCourier(Request $request, TransferManifest $manifest): RedirectResponse
    {
        $validated = $request->validate([
            'assigned_courier_id' => ['required', 'integer', 'exists:users,id'],
            'vehicle_plate_number' => ['nullable', 'string', 'max:30'],
        ]);

        $this->manifestService->assignCourier(
            $manifest,
            (int) $validated['assigned_courier_id'],
            $request->user(),
            $validated['vehicle_plate_number'] ?? null
        );

        return back()->with('status', "Transfer courier assigned to manifest {$manifest->manifest_number}.");
    }

    /**
     * Resolve a scanned barcode against this manifest for receiving verification without modifying custody.
     */
    public function lookupManifestParcel(Request $request, TransferManifest $manifest): \Illuminate\Http\JsonResponse
    {
        $companyId = (int) $request->user()->id;

        if ((int) $manifest->receiver_logistics_id !== $companyId) {
            return response()->json([
                'outcome' => 'unauthorized',
                'message' => 'Only authorized staff of the receiving logistics company can verify incoming parcels.',
            ], 403);
        }

        $validated = $request->validate([
            'tracking_number' => ['required', 'string', 'max:100'],
        ]);

        $normalized = \App\Models\Order::normalizeScanIdentifier($validated['tracking_number']);
        if ($normalized === '') {
            return response()->json([
                'outcome' => 'invalid_barcode',
                'message' => 'Scanned barcode is empty or contains invalid characters.',
            ], 422);
        }

        $manifest->loadMissing(['destinationHub', 'originHub']);

        $item = $manifest->items()
            ->with(['order.buyer', 'order.address', 'order.seller.sellerProfile'])
            ->where(function ($q) use ($normalized) {
                $q->where('tracking_number', $normalized)
                  ->orWhereRaw('UPPER(tracking_number) = ?', [$normalized])
                  ->orWhereHas('order', fn ($oq) => $oq->where('order_number', $normalized)->orWhereRaw('UPPER(order_number) = ?', [$normalized]));
            })
            ->first();

        if (! $item) {
            return response()->json([
                'outcome' => 'not_on_manifest',
                'message' => "Tracking number '{$normalized}' does not belong to manifest {$manifest->manifest_number}.",
            ], 422);
        }

        $order = $item->order;
        $counts = $this->manifestService->getReceivingCounts($manifest->id);
        $alreadyReceived = $item->receiving_status === \App\Models\TransferManifestItem::RECEIVING_RECEIVED;
        $canVerifyManifest = in_array($manifest->status, [
            TransferManifest::STATUS_ARRIVED,
            TransferManifest::STATUS_RECEIVING,
            TransferManifest::STATUS_EXCEPTION,
        ], true);

        $outcome = match (true) {
            $alreadyReceived => 'already_received',
            ! $canVerifyManifest => 'manifest_not_ready',
            default => 'eligible',
        };

        $message = match ($outcome) {
            'already_received' => "Parcel {$item->tracking_number} has already been received and verified on manifest {$manifest->manifest_number}.",
            'manifest_not_ready' => "Manifest {$manifest->manifest_number} is currently " . strtoupper(str_replace('_', ' ', $manifest->status)) . ". Only arrived or active receiving manifests can verify parcels.",
            default => "Parcel {$item->tracking_number} verified on manifest {$manifest->manifest_number}. Choose condition to confirm.",
        };

        return response()->json([
            'outcome' => $outcome,
            'data' => [
                'outcome' => $outcome,
                'item_id' => $item->id,
                'tracking_number' => $item->tracking_number,
                'order_number' => $order?->order_number,
                'loading_status' => $item->loading_status,
                'receiving_status' => $item->receiving_status,
                'already_received' => $alreadyReceived,
                'can_verify' => $canVerifyManifest,
                'issue_notes' => $item->issue_notes,
                'sender_name' => $order?->seller?->sellerProfile?->business_name ?? $order?->seller?->full_name ?? 'Merchant',
                'recipient_name' => $order?->address?->recipient_name ?? $order?->buyer?->full_name ?? 'Customer',
                'destination' => $order?->formatted_destination ?? 'Destination Unavailable',
                'weight_kg' => (float) ($order?->shipping_weight ?? 0),
                'cod_amount' => strtolower((string) ($order?->payment_method ?? '')) === 'cod' ? (float) ($order?->total ?? 0) : 0.0,
                'payment_method' => strtoupper((string) ($order?->payment_method ?? 'cod')),
                'destination_hub' => $manifest->destinationHub ? "{$manifest->destinationHub->name} ({$manifest->destinationHub->code})" : 'Destination Hub',
                'receiving_counts' => $counts,
            ],
            'message' => $message,
        ]);
    }

    /**
     * Receiving hub operator scans/verifies an incoming parcel on a manifest.
     */
    public function verifyManifestParcel(Request $request, TransferManifest $manifest): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'tracking_number' => ['required', 'string', 'max:100'],
            'classification' => ['required', 'string', 'in:received,missing,damaged,discrepancy'],
            'notes' => ['nullable', 'string', 'max:500'],
            'auto_scan' => ['nullable', 'boolean'],
            'accept_into_quarantine' => ['nullable', 'boolean'],
            'damage_type' => ['nullable', 'string', 'max:50'],
            'evidence_photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $trackingNumber = Order::normalizeScanIdentifier($validated['tracking_number']);
        if ($trackingNumber === '') {
            if ($request->expectsJson()) {
                return response()->json([
                    'outcome' => 'invalid_barcode',
                    'newly_received' => false,
                    'already_received' => false,
                    'message' => 'Scanned barcode is empty or invalid.',
                ], 422);
            }

            return back()->withErrors(['tracking_number' => 'Scanned barcode is empty or invalid.']);
        }

        $autoScan = $request->boolean('auto_scan');

        try {
            $result = $this->manifestService->verifyReceivingParcel(
                $manifest,
                $trackingNumber,
                $validated['classification'],
                $request->user(),
                $validated['notes'] ?? null,
                $autoScan,
                $request->boolean('accept_into_quarantine', false),
                $request->file('evidence_photo'),
                $validated['damage_type'] ?? null
            );
        } catch (HttpExceptionInterface $e) {
            if (! $request->expectsJson()) {
                throw $e;
            }

            $msg = $e->getMessage() ?: 'Unable to verify manifest parcel.';
            $lower = strtolower($msg);
            $outcome = match (true) {
                $e->getStatusCode() === 403 => 'unauthorized',
                str_contains($lower, 'flagged for investigation') => 'flagged_for_investigation',
                str_contains($lower, 'does not belong to manifest') => 'not_on_manifest',
                str_contains($lower, 'must arrive') || str_contains($lower, 'completed') => 'manifest_not_ready',
                default => 'invalid_barcode',
            };

            return response()->json([
                'outcome' => $outcome,
                'newly_received' => false,
                'already_received' => false,
                'message' => $msg,
            ], $e->getStatusCode());
        }

        $label = ucfirst($validated['classification']);
        $manifestModel = $result['manifest'];
        $item = $result['item'];
        $isIdempotent = (bool) ($result['already_verified'] ?? $result['idempotent'] ?? false);
        $isReceived = $validated['classification'] === TransferManifestItem::RECEIVING_RECEIVED;
        $statusText = $isIdempotent
            ? "Parcel {$item->tracking_number} was already received on manifest {$manifestModel->manifest_number}."
            : "Parcel {$item->tracking_number} marked as {$label}. Manifest status: " . strtoupper(str_replace('_', ' ', $manifestModel->status)) . '.';

        if ($request->expectsJson()) {
            $manifestModel->loadMissing('destinationHub');
            $item->loadMissing('incidents');
            $outcome = match (true) {
                $isIdempotent && $isReceived => 'already_received',
                $isReceived => 'received',
                default => 'classified',
            };

            return response()->json([
                'outcome' => $outcome,
                'newly_received' => ! $isIdempotent && $isReceived,
                'already_received' => $isIdempotent && $isReceived,
                'manifest_status' => $manifestModel->status,
                'destination_hub' => $manifestModel->destinationHub
                    ? "{$manifestModel->destinationHub->name} ({$manifestModel->destinationHub->code})"
                    : 'Destination Hub',
                'counts' => $result['counts'],
                'item' => [
                    'id' => $item->id,
                    'tracking_number' => $item->tracking_number,
                    'receiving_status' => $item->receiving_status,
                    'issue_notes' => $item->issue_notes,
                    'condition_summary' => $item->condition_summary,
                ],
                'message' => $statusText,
            ]);
        }

        return back()->with('status', $statusText);
    }

    /**
     * Cancel a draft/loading manifest before departure.
     */
    public function cancelManifest(Request $request, TransferManifest $manifest): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $this->manifestService->cancelManifest($manifest, $request->user(), $validated['reason'] ?? null);

        return redirect()->route('logistics.transfers.index')
            ->with('status', "Manifest {$manifest->manifest_number} cancelled.");
    }

    /**
     * Append selected compatible parcels to an existing editable pre-dispatch manifest.
     */
    public function addParcelsToManifest(Request $request, TransferManifest $manifest): RedirectResponse
    {
        $validated = $request->validate([
            'order_ids' => ['required', 'array', 'min:1'],
            'order_ids.*' => ['integer', 'exists:orders,id'],
        ]);

        $updated = $this->manifestService->addParcelsToManifest(
            $manifest,
            $validated['order_ids'],
            $request->user()
        );

        return redirect()->route('logistics.transfers.manifests.show', $updated)
            ->with('status', "Added " . count($validated['order_ids']) . " parcel(s) to manifest {$updated->manifest_number}. Total: {$updated->total_parcels}.");
    }

    /**
     * Remove a parcel from an editable pre-dispatch manifest.
     */
    public function removeManifestParcel(Request $request, TransferManifest $manifest, TransferManifestItem $item): RedirectResponse
    {
        $updated = $this->manifestService->removeParcelFromManifest($manifest, $item, $request->user());

        return back()->with('status', "Parcel {$item->tracking_number} removed from manifest {$updated->manifest_number} and returned to available staging if eligible.");
    }

    /**
     * Report post-receipt damage on an already-received manifest parcel without overwriting receipt history.
     */
    public function reportManifestItemDamage(Request $request, TransferManifest $manifest, TransferManifestItem $item): RedirectResponse|JsonResponse
    {
        $damageKeys = implode(',', array_keys(ParcelIncident::DAMAGE_TYPES));
        $validated = $request->validate([
            'damage_type' => ['required', 'string', "in:{$damageKeys}"],
            'description' => ['required', 'string', 'min:3', 'max:1000'],
            'evidence' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $incident = $this->incidentService->reportPostReceiptDamage(
            $manifest,
            $item,
            $request->user(),
            $validated,
            $request->file('evidence')
        );

        $message = "Post-receipt damage report logged for parcel {$incident->tracking_number}. Original receipt record remains preserved.";

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'incident' => $incident,
            ], 201);
        }

        return back()->with('status', $message);
    }

    /**
     * Report an already-received manifest parcel as missing/lost post-receipt without overwriting receipt history.
     */
    public function reportManifestItemMissing(Request $request, TransferManifest $manifest, TransferManifestItem $item): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'description' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        $incident = $this->incidentService->reportPostReceiptMissing(
            $manifest,
            $item,
            $request->user(),
            $validated['description']
        );

        $message = "Parcel {$incident->tracking_number} reported missing/lost after receipt. Investigation status is now Open.";

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'incident' => $incident,
            ], 201);
        }

        return back()->with('status', $message);
    }

    /**
     * Transition investigation status on a manifest parcel incident (Open / Under Investigation / Found / Resolved).
     */
    public function updateIncidentStatus(Request $request, TransferManifest $manifest, ParcelIncident $incident): RedirectResponse|JsonResponse
    {
        $statuses = implode(',', ParcelIncident::ALL_STATUSES);
        $validated = $request->validate([
            'investigation_status' => ['required', 'string', "in:{$statuses}"],
            'resolution_notes' => ['nullable', 'string', 'max:1000'],
            'disposition' => ['nullable', 'string'],
            'recovery_location' => ['nullable', 'string', 'max:255'],
            'evidence_photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $updated = $this->incidentService->updateIncidentStatus(
            $incident,
            $request->user(),
            $validated['investigation_status'],
            $validated['resolution_notes'] ?? null,
            $manifest,
            $validated['disposition'] ?? null,
            $validated['recovery_location'] ?? null,
            $request->file('evidence_photo')
        );

        $message = "Incident for parcel {$updated->tracking_number} updated to {$updated->status_label}.";

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'incident' => $updated,
            ]);
        }

        return back()->with('status', $message);
    }

    /**
     * Controlled Closed With Exceptions manifest closure (Phase 6).
     */
    public function closeManifestWithExceptions(Request $request, TransferManifest $manifest): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'reconciliation_notes' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $closed = $this->manifestService->closeWithExceptions(
            $manifest,
            $request->user(),
            $validated['reconciliation_notes']
        );

        $message = "Manifest {$closed->manifest_number} closed with exceptions cleanly (no missing/damaged items fabricated as received).";

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'manifest' => $closed,
            ]);
        }

        return back()->with('status', $message);
    }
}

