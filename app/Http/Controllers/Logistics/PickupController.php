<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderLogisticsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PickupController extends Controller
{
    /**
     * Display seller pickup requests awaiting sorting center action.
     */
    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'pending');
        $search = $request->query('q');

        $query = Order::with([
            'items.product.seller.sellerProfile',
            'items.product.seller.address',
            'buyer',
            'address',
            'pickupCourier',
        ]);

        match ($tab) {
            'pending' => $query->where('status', 'ready_for_pickup')->whereNull('pickup_courier_id'),
            'scheduled' => $query->where('status', 'ready_for_pickup')->whereNotNull('pickup_courier_id'),
            'picked_up' => $query->where('status', 'picked_up'),
            default => $query->whereIn('status', ['ready_for_pickup', 'picked_up']),
        };

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('tracking_number', 'like', "%{$search}%")
                    ->orWhereHas('items.product.seller', function ($sq) use ($search) {
                        $sq->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhereHas('sellerProfile', fn ($p) => $p->where('business_name', 'like', "%{$search}%"));
                    });
            });
        }

        $orders = $query->latest('placed_at')->paginate(15)->withQueryString();

        $counts = [
            'pending' => Order::where('status', 'ready_for_pickup')->whereNull('pickup_courier_id')->count(),
            'scheduled' => Order::where('status', 'ready_for_pickup')->whereNotNull('pickup_courier_id')->count(),
            'picked_up' => Order::where('status', 'picked_up')->count(),
            'all' => Order::whereIn('status', ['ready_for_pickup', 'picked_up'])->count(),
        ];

        // Eligible active couriers for pickup assignment
        $couriers = User::where('role', 'courier')
            ->where('approval_status', 'approved')
            ->where('account_status', 'active')
            ->with('courierProfile')
            ->orderBy('first_name')
            ->get();

        return view('logistics.pickups.index', compact('orders', 'counts', 'tab', 'search', 'couriers'));
    }

    /**
     * Show pickup request details.
     */
    public function show(Request $request, Order $order): View
    {
        $order->load([
            'items.product.seller.sellerProfile',
            'items.product.seller.address',
            'buyer',
            'address',
            'pickupCourier.courierProfile',
            'statusHistory.changedBy',
        ]);

        $couriers = User::where('role', 'courier')
            ->where('approval_status', 'approved')
            ->where('account_status', 'active')
            ->with('courierProfile')
            ->orderBy('first_name')
            ->get();

        return view('logistics.pickups.show', compact('order', 'couriers'));
    }

    /**
     * Schedule pickup and assign a pickup courier.
     */
    public function schedule(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->status === 'ready_for_pickup', 422, 'Only orders ready for pickup can be scheduled.');

        $validated = $request->validate([
            'courier_id' => ['required', 'exists:users,id'],
            'scheduled_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $courier = User::where('id', $validated['courier_id'])
            ->where('role', 'courier')
            ->where('approval_status', 'approved')
            ->where('account_status', 'active')
            ->firstOrFail();

        OrderLogisticsService::ensureTrackingNumber($order);

        $order->update([
            'pickup_courier_id' => $courier->id,
            'sorting_center_id' => $request->user()->id,
        ]);

        $note = "Pickup scheduled with rider {$courier->full_name}.";
        if (! empty($validated['scheduled_notes'])) {
            $note .= " Note: {$validated['scheduled_notes']}";
        }

        \App\Models\OrderStatusHistory::create([
            'order_id' => $order->id,
            'status' => 'ready_for_pickup',
            'changed_by' => $request->user()->id,
            'note' => $note,
            'changed_at' => now(),
        ]);

        return back()->with('status', "Pickup scheduled successfully. Assigned to rider {$courier->full_name}.");
    }

    /**
     * Confirm physical pickup from seller.
     */
    public function confirmPickup(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->status === 'ready_for_pickup', 422, 'Order must be ready for pickup to confirm collection.');

        OrderLogisticsService::transition(
            order: $order,
            newStatus: 'picked_up',
            changedBy: $request->user()->id,
            note: 'Parcel physically collected from seller.',
            extraAttributes: [
                'sorting_center_id' => $request->user()->id,
            ]
        );

        return back()->with('status', "Parcel pickup confirmed. Order is now marked as 'Picked Up' and heading to sorting center.");
    }

    /**
     * Reject or return pickup request back to seller with reason.
     */
    public function reject(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->status === 'ready_for_pickup', 422, 'Only orders ready for pickup can be rejected.');

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        // Roll back to preparing so the seller can correct and re-request
        OrderLogisticsService::transition(
            order: $order,
            newStatus: 'preparing',
            changedBy: $request->user()->id,
            note: "Pickup request rejected by sorting center: {$validated['reason']}",
            extraAttributes: [
                'pickup_courier_id' => null,
            ]
        );

        return redirect()->route('logistics.pickups.index')
            ->with('status', "Pickup request rejected. Order returned to 'Preparing' status with notice to seller.");
    }
}
