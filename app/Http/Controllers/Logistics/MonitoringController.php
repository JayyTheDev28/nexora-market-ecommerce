<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\DeliveryArea;
use App\Models\DeliveryAttempt;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderLogisticsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MonitoringController extends Controller
{
    /**
     * Display live in-flight parcel deliveries and status monitoring.
     */
    public function index(Request $request): View
    {
        $sortingCenterId = $request->user()->id;
        $tab = $request->query('tab', 'in_flight');
        $status = $request->query('status');
        $areaId = $request->query('area_id');
        $courierId = $request->query('courier_id');
        $search = $request->query('q');

        $query = Order::where('sorting_center_id', $sortingCenterId)
            ->with([
                'buyer',
                'address',
                'deliveryArea',
                'deliveryCourier.courierProfile',
                'deliveryAttempts',
                'items.product.seller.sellerProfile',
                'items.product.seller.address',
                'statusHistory',
            ]);

        match ($tab) {
            'in_flight' => $query->whereIn('status', ['assigned_to_rider', 'out_for_delivery']),
            'exceptions' => $query->whereIn('status', ['delivery_failed', 'returned']),
            'completed' => $query->whereIn('status', ['delivered', 'completed']),
            'all' => $query->whereIn('status', ['assigned_to_rider', 'out_for_delivery', 'delivered', 'completed', 'delivery_failed', 'returned']),
            default => $query->whereIn('status', ['assigned_to_rider', 'out_for_delivery']),
        };

        if ($status) {
            $query->where('status', $status);
        }

        if ($areaId) {
            $query->where('delivery_area_id', $areaId);
        }

        if ($courierId) {
            $query->where('delivery_courier_id', $courierId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('tracking_number', 'like', "%{$search}%")
                    ->orWhereHas('buyer', fn ($bq) => $bq->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"))
                    ->orWhereHas('deliveryCourier', fn ($cq) => $cq->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"));
            });
        }

        $deliveries = $query->latest('updated_at')->paginate(15)->withQueryString();

        $counts = [
            'in_flight' => Order::where('sorting_center_id', $sortingCenterId)->whereIn('status', ['assigned_to_rider', 'out_for_delivery'])->count(),
            'assigned' => Order::where('sorting_center_id', $sortingCenterId)->where('status', 'assigned_to_rider')->count(),
            'out_for_delivery' => Order::where('sorting_center_id', $sortingCenterId)->where('status', 'out_for_delivery')->count(),
            'exceptions' => Order::where('sorting_center_id', $sortingCenterId)->whereIn('status', ['delivery_failed', 'returned'])->count(),
            'completed' => Order::where('sorting_center_id', $sortingCenterId)->whereIn('status', ['delivered', 'completed'])->count(),
            'all' => Order::where('sorting_center_id', $sortingCenterId)->whereIn('status', ['assigned_to_rider', 'out_for_delivery', 'delivered', 'completed', 'delivery_failed', 'returned'])->count(),
        ];

        $couriers = User::where('role', 'courier')
            ->where('approval_status', 'approved')
            ->orderBy('first_name')
            ->get();

        $deliveryAreas = DeliveryArea::where('sorting_center_id', $sortingCenterId)->get();

        return view('logistics.deliveries.index', compact(
            'deliveries',
            'counts',
            'tab',
            'status',
            'areaId',
            'courierId',
            'search',
            'couriers',
            'deliveryAreas'
        ));
    }

    /**
     * Show detailed monitoring view for an individual delivery.
     */
    public function show(Request $request, Order $order): View
    {
        $order->load([
            'buyer',
            'address',
            'deliveryArea',
            'deliveryCourier.courierProfile',
            'pickupCourier',
            'items.product.seller.sellerProfile',
            'items.product.seller.address',
            'deliveryAttempts.courier',
            'statusHistory.changedBy',
        ]);

        return view('logistics.deliveries.show', compact('order'));
    }

    /**
     * Handle delivery exception resolution (reschedule attempt or mark returned).
     */
    public function resolveException(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:reschedule,return_to_seller'],
            'notes' => ['required', 'string', 'max:500'],
        ]);

        if ($validated['action'] === 'reschedule') {
            // Keep as assigned_to_rider for another delivery attempt
            OrderLogisticsService::transition(
                order: $order,
                newStatus: 'assigned_to_rider',
                changedBy: $request->user()->id,
                note: "Delivery rescheduled by sorting center: {$validated['notes']}"
            );

            return back()->with('status', "Delivery rescheduled. Order returned to 'Assigned to Rider' for re-attempt.");
        }

        // Return to seller
        OrderLogisticsService::transition(
            order: $order,
            newStatus: 'returned',
            changedBy: $request->user()->id,
            note: "Delivery terminated by sorting center. Returning parcel to seller: {$validated['notes']}"
        );

        return back()->with('status', "Order marked as 'Returned'. Parcel is returning to the seller.");
    }
}
