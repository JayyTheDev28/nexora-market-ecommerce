<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\DeliveryArea;
use App\Models\Hub;
use App\Models\HubTransfer;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Services\InterRegionLogisticsService;
use App\Services\OrderLogisticsService;
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
     * Scan or enter a parcel identifier to receive it at the hub.
     */
    public function scan(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'hub_id' => ['nullable', 'exists:hubs,id'],
        ]);

        $identifier = trim($validated['identifier']);

        $order = Order::where('tracking_number', $identifier)
            ->orWhere('order_number', $identifier)
            ->first();

        if (! $order) {
            return back()->withInput()->withErrors([
                'identifier' => "Parcel with identifier '{$identifier}' could not be found.",
            ]);
        }

        // Fallback for legacy sorting center users without configured hubs
        $hasHubs = Hub::where('logistics_company_id', $request->user()->id)->where('is_active', true)->exists();

        if (! $hasHubs) {
            if (in_array($order->status, ['assigned_to_rider', 'out_for_delivery', 'delivered', 'completed'])) {
                return back()->with('info', "Parcel {$order->order_number} has already passed incoming receiving (current status: " . ucwords(str_replace('_', ' ', $order->status)) . ").");
            }

            if (in_array($order->status, ['at_sorting_center', 'received_at_hub', 'sorted'])) {
                return back()->with('info', "Parcel {$order->order_number} is already checked in at this facility (current status: " . ucwords(str_replace('_', ' ', $order->status)) . ").");
            }

            $matchedArea = DeliveryArea::where('sorting_center_id', $request->user()->id)
                ->where(function ($q) use ($order) {
                    if ($order->address?->municipality_name) {
                        $q->where('name', 'like', "%{$order->address->municipality_name}%");
                    }
                })
                ->first();

            $extra = [
                'sorting_center_id' => $request->user()->id,
                'current_custody_logistics_id' => $request->user()->id,
            ];

            if ($matchedArea && empty($order->delivery_area_id)) {
                $extra['delivery_area_id'] = $matchedArea->id;
            }

            OrderLogisticsService::transition(
                order: $order,
                newStatus: 'at_sorting_center',
                changedBy: $request->user()->id,
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

        // Determine which hub station this is being received at
        $hub = null;
        if (! empty($validated['hub_id'])) {
            $hub = Hub::where('id', $validated['hub_id'])
                ->where('logistics_company_id', $request->user()->id)
                ->first();
        }

        if (! $hub) {
            if ($order->status === 'in_transit_hub' && $order->destination_hub_id) {
                $hub = Hub::where('id', $order->destination_hub_id)
                    ->where('logistics_company_id', $request->user()->id)
                    ->first();
            }

            if (! $hub && $order->origin_hub_id) {
                $hub = Hub::where('id', $order->origin_hub_id)
                    ->where('logistics_company_id', $request->user()->id)
                    ->first();
            }

            if (! $hub) {
                $hub = Hub::where('logistics_company_id', $request->user()->id)->where('is_active', true)->first();
            }
        }

        abort_unless($hub, 422, 'No active hub found for your logistics company to receive parcels.');

        // If parcel was traveling between hubs (Hub Transfer arrival scan)
        if ($order->status === 'in_transit_hub') {
            $activeTransfer = HubTransfer::where('order_id', $order->id)
                ->where('status', 'in_transit')
                ->latest()
                ->first();

            if ($activeTransfer) {
                $this->logisticsService->confirmHubTransferReceipt($activeTransfer, $request->user());
                return back()->with('status', "Transfer confirmed! Parcel {$order->order_number} received at destination hub {$hub->name} and added to parcel inventory.");
            }
        }

        // Must be in acceptable receiving states
        if (in_array($order->status, ['assigned_to_rider', 'out_for_delivery', 'delivered', 'completed'])) {
            return back()->with('info', "Parcel {$order->order_number} has already passed hub receiving (current status: " . ucwords(str_replace('_', ' ', $order->status)) . ").");
        }

        // If already at hub and current_hub_id matches, idempotency note
        if (in_array($order->status, ['received_at_hub', 'at_sorting_center']) && (int) $order->current_hub_id === (int) $hub->id) {
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
