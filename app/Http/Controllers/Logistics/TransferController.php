<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Hub;
use App\Models\HubTransfer;
use App\Models\Order;
use App\Models\User;
use App\Services\InterRegionLogisticsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TransferController extends Controller
{
    public function __construct(
        protected InterRegionLogisticsService $logisticsService
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

        // Parcels in this company currently awaiting hub dispatch (both internal and cross-company!)
        $awaitingDispatch = Order::where('current_custody_logistics_id', $companyId)
            ->where(function ($q) {
                $q->whereColumn('origin_hub_id', '!=', 'destination_hub_id')
                  ->orWhereNull('origin_hub_id');
            })
            ->whereIn('status', ['received_at_hub', 'at_sorting_center', 'sorted'])
            ->whereDoesntHave('transfers', fn ($t) => $t->where('status', 'in_transit'))
            ->with(['buyer', 'address', 'originHub', 'destinationHub.logisticsCompany', 'pickupLogistics', 'deliveryLogistics'])
            ->get();

        $myHubs = Hub::where('logistics_company_id', $companyId)->where('is_active', true)->orderBy('name')->get();
        $allDestinationHubs = Hub::where('is_active', true)->with('logisticsCompany')->orderBy('name')->get();

        return view('logistics.transfers.index', compact('transfers', 'counts', 'tab', 'search', 'awaitingDispatch', 'myHubs', 'allDestinationHubs'));
    }

    /**
     * Dispatch an inter-hub or internal transfer to the destination hub.
     */
    public function dispatch(Request $request, Order $order): RedirectResponse
    {
        $companyId = $request->user()->id;

        abort_unless(
            (int) $order->current_custody_logistics_id === (int) $companyId,
            403,
            'Parcel is not in the custody of your logistics company.'
        );

        abort_if(
            !empty($order->destination_hub_id) && (int) $order->current_hub_id === (int) $order->destination_hub_id,
            422,
            'This parcel has already arrived at its destination hub (' . ($order->destinationHub?->name ?? 'Destination Hub') . '). Hub transfer is already complete.'
        );

        $validated = $request->validate([
            'sender_hub_id' => ['required', 'exists:hubs,id'],
            'receiver_hub_id' => ['required', 'exists:hubs,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $senderHub = Hub::where('id', $validated['sender_hub_id'])->where('logistics_company_id', $companyId)->firstOrFail();
        $receiverHub = Hub::where('id', $validated['receiver_hub_id'])->firstOrFail();

        abort_if((int) $senderHub->id === (int) $receiverHub->id, 422, 'Origin and destination hubs cannot be the same hub.');

        if (!empty($order->current_hub_id) && (int) $order->current_hub_id !== (int) $senderHub->id) {
            return back()->withInput()->withErrors([
                'sender_hub_id' => "Parcel is currently physically located at " . ($order->currentHub?->name ?? 'another hub') . ", not at {$senderHub->name}.",
            ]);
        }

        $transfer = $this->logisticsService->dispatchHubTransfer(
            $order,
            $senderHub,
            $receiverHub,
            $request->user(),
            $validated['notes'] ?? null
        );

        $transferType = $transfer->is_internal ? 'internal hub transfer' : 'inter-company transfer';
        return back()->with('status', "Parcel #{$order->order_number} dispatched in {$transferType} to {$receiverHub->name}. Custody remains with your company until receipt.");
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
}

