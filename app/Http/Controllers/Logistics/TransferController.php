<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Hub;
use App\Models\HubTransfer;
use App\Models\Order;
use App\Models\TransferManifest;
use App\Models\TransferManifestItem;
use App\Models\User;
use App\Services\InterRegionLogisticsService;
use App\Services\TransferManifestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TransferController extends Controller
{
    public function __construct(
        protected InterRegionLogisticsService $logisticsService,
        protected TransferManifestService $manifestService
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

        $transfers = $query->latest('dispatched_at')->paginate(15)->withQueryString();

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
            ->with(['buyer', 'address', 'currentHub.logisticsCompany', 'originHub.logisticsCompany', 'destinationHub.logisticsCompany', 'pickupLogistics', 'deliveryLogistics'])
            ->orderByDesc('updated_at')
            ->get();

        // Separate cleanly routed parcels from parcels requiring routing review
        $eligibleForManifest = $candidateOrders
            ->filter(fn (Order $order) => $order->hasValidTransferRouting()
                && (int) $order->currentHub->logistics_company_id === (int) $companyId)
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
            ->with(['originHub', 'destinationHub', 'assignedCourier', 'senderLogistics', 'receiverLogistics', 'items'])
            ->latest()
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
            ->latest()
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
     * Receiving hub operator scans/verifies an incoming parcel on a manifest.
     */
    public function verifyManifestParcel(Request $request, TransferManifest $manifest): RedirectResponse
    {
        $validated = $request->validate([
            'tracking_number' => ['required', 'string', 'max:100'],
            'classification' => ['required', 'string', 'in:received,missing,damaged,discrepancy'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $result = $this->manifestService->verifyReceivingParcel(
            $manifest,
            $validated['tracking_number'],
            $validated['classification'],
            $request->user(),
            $validated['notes'] ?? null
        );

        $label = ucfirst($validated['classification']);
        return back()->with('status', "Parcel {$validated['tracking_number']} marked as {$label}. Manifest status: " . strtoupper(str_replace('_', ' ', $result['manifest']->status)) . '.');
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
}

