<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\DeliveryArea;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Services\OrderLogisticsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IncomingController extends Controller
{
    /**
     * Display incoming parcels awaiting receipt and recently received parcels.
     */
    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'awaiting');
        $search = $request->query('q');

        $query = Order::with([
            'buyer',
            'address',
            'items.product.seller.sellerProfile',
            'items.product.seller.address',
            'pickupCourier',
            'deliveryArea',
        ]);

        match ($tab) {
            'awaiting' => $query->where('status', 'picked_up'),
            'received' => $query->where('status', 'at_sorting_center'),
            'all' => $query->whereIn('status', ['picked_up', 'at_sorting_center', 'sorted']),
            default => $query->where('status', 'picked_up'),
        };

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('tracking_number', 'like', "%{$search}%")
                    ->orWhereHas('buyer', fn ($bq) => $bq->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"))
                    ->orWhereHas('address', fn ($aq) => $aq->where('municipality_name', 'like', "%{$search}%")->orWhere('province_name', 'like', "%{$search}%"));
            });
        }

        $parcels = $query->latest()->paginate(15)->withQueryString();

        $counts = [
            'awaiting' => Order::where('status', 'picked_up')->count(),
            'received' => Order::where('status', 'at_sorting_center')->count(),
            'all' => Order::whereIn('status', ['picked_up', 'at_sorting_center', 'sorted'])->count(),
        ];

        // Delivery areas managed by this sorting center for quick destination matching
        $deliveryAreas = DeliveryArea::where('sorting_center_id', $request->user()->id)->get();

        return view('logistics.incoming.index', compact('parcels', 'counts', 'tab', 'search', 'deliveryAreas'));
    }

    /**
     * Scan or enter a parcel identifier to receive it at the sorting center.
     */
    public function scan(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
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

        // Idempotency: If already at sorting center or beyond
        if ($order->status === 'at_sorting_center') {
            return back()->with('info', "Parcel {$order->order_number} is already checked in at this sorting hub.");
        }

        if (in_array($order->status, ['sorted', 'assigned_to_rider', 'out_for_delivery', 'delivered', 'completed'])) {
            return back()->with('info', "Parcel {$order->order_number} has already passed incoming receiving (current status: " . ucwords(str_replace('_', ' ', $order->status)) . ").");
        }

        // Must be in picked_up or ready_for_pickup state
        if (! in_array($order->status, ['picked_up', 'ready_for_pickup'])) {
            return back()->withInput()->withErrors([
                'identifier' => "Parcel {$order->order_number} cannot be received yet (current status: " . ucwords(str_replace('_', ' ', $order->status)) . ").",
            ]);
        }

        // Auto-match delivery area if buyer municipality matches a delivery area name
        $matchedArea = DeliveryArea::where('sorting_center_id', $request->user()->id)
            ->where(function ($q) use ($order) {
                if ($order->address?->municipality_name) {
                    $q->where('name', 'like', "%{$order->address->municipality_name}%");
                }
            })
            ->first();

        $extra = [
            'sorting_center_id' => $request->user()->id,
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
