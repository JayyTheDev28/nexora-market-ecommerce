<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Notifications\OrderStatusUpdated;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    /**
     * Statuses a seller can move an order through themselves, per the ERP
     * flow doc: Receive -> View details -> Check stock -> Accept/Confirm ->
     * Prepare -> Pack -> Print label -> Mark ready for pickup -> (rider
     * arrives) -> Confirm pickup. Everything after picked_up belongs to
     * the courier/sorting-center phases, not built yet.
     */
    private const SELLER_TRANSITIONS = [
        'placed' => 'confirmed',
        'confirmed' => 'preparing',
        'preparing' => 'ready_for_pickup',
        // ready_for_pickup -> picked_up stands in here for "rider arrives,
        // hand over parcel, confirm pickup" since no courier role exists
        // yet to do this step themselves.
        'ready_for_pickup' => 'picked_up',
    ];

    private const ACTION_LABELS = [
        'confirmed' => 'Accept Order',
        'preparing' => 'Start Preparing',
        'ready_for_pickup' => 'Mark Ready for Pickup',
        'picked_up' => 'Confirm Rider Pickup',
    ];

    public function index(Request $request): View
    {
        $sellerId = $request->user()->id;

        $orders = Order::whereHas('items.product', fn ($q) => $q->where('seller_id', $sellerId))
            ->with(['items' => fn ($q) => $q->whereHas('product', fn ($q2) => $q2->where('seller_id', $sellerId)), 'buyer', 'address'])
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest('placed_at')
            ->get();

        $counts = [
            'new' => Order::whereHas('items.product', fn ($q) => $q->where('seller_id', $sellerId))->where('status', 'placed')->count(),
            'active' => Order::whereHas('items.product', fn ($q) => $q->where('seller_id', $sellerId))
                ->whereIn('status', ['confirmed', 'preparing', 'ready_for_pickup'])->count(),
            'shipped' => Order::whereHas('items.product', fn ($q) => $q->where('seller_id', $sellerId))
                ->whereNotIn('status', ['placed', 'confirmed', 'preparing', 'ready_for_pickup'])->count(),
        ];

        return view('seller.orders.index', compact('orders', 'counts'));
    }

    public function show(Request $request, Order $order): View
    {
        $this->authorizeSellerInvolved($request, $order);

        $order->load(['items.product', 'buyer', 'address', 'statusHistory.changedBy']);

        $nextStatus = self::SELLER_TRANSITIONS[$order->status] ?? null;
        $actionLabel = $nextStatus ? self::ACTION_LABELS[$nextStatus] : null;

        return view('seller.orders.show', compact('order', 'nextStatus', 'actionLabel'));
    }

    public function advance(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeSellerInvolved($request, $order);

        $nextStatus = self::SELLER_TRANSITIONS[$order->status] ?? null;

        abort_if(! $nextStatus, 400, 'This order cannot be advanced from its current status.');

        $order->update(['status' => $nextStatus]);

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'status' => $nextStatus,
            'changed_by' => $request->user()->id,
            'changed_at' => now(),
        ]);

        // "Confirm delivery (seller will be notified once the customer
        // receives the order)" works the other direction too — the ERP doc
        // implies sellers should know what's happening with their orders,
        // so the buyer gets notified here as their order actually moves.
        $order->buyer->notify(new OrderStatusUpdated($order, $nextStatus));

        return redirect()->route('seller.orders.show', $order)
            ->with('status', 'Order updated to "' . str_replace('_', ' ', $nextStatus) . '".');
    }

    private function authorizeSellerInvolved(Request $request, Order $order): void
    {
        $involved = $order->items()->whereHas('product', fn ($q) => $q->where('seller_id', $request->user()->id))->exists();

        abort_unless($involved, 403);
    }
}
