<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Notifications\OrderStatusUpdated;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OrderController extends Controller
{
    private const SELLER_TRANSITIONS = [
        'placed' => 'confirmed',
        'confirmed' => 'preparing',
        'preparing' => 'ready_for_pickup',
        'ready_for_pickup' => 'picked_up',
    ];

    private const ACTION_LABELS = [
        'confirmed' => 'Accept Order',
        'preparing' => 'Start Packing & Preparing',
        'ready_for_pickup' => 'Mark Ready for Courier Pickup',
        'picked_up' => 'Confirm Courier Handover',
    ];

    public function index(Request $request): View
    {
        $sellerId = $request->user()->id;

        $query = Order::where(function ($q) use ($sellerId) {
            $q->where('seller_id', $sellerId)
              ->orWhereHas('items.product', fn ($sq) => $sq->where('seller_id', $sellerId));
        })
        ->with([
            'items' => fn ($q) => $q->whereHas('product', fn ($sq) => $sq->where('seller_id', $sellerId)),
            'buyer',
            'address',
        ])
        ->latest('placed_at');

        // Status filter tab
        $statusTab = $request->query('status', 'all');
        if ($statusTab === 'to_confirm') {
            $query->where('status', 'placed');
        } elseif ($statusTab === 'processing') {
            $query->whereIn('status', ['confirmed', 'preparing']);
        } elseif ($statusTab === 'ready_for_pickup') {
            $query->where('status', 'ready_for_pickup');
        } elseif ($statusTab === 'shipped') {
            $query->whereIn('status', ['picked_up', 'sorting_hub', 'in_transit', 'out_for_delivery']);
        } elseif ($statusTab === 'completed') {
            $query->whereIn('status', ['delivered', 'completed']);
        } elseif ($statusTab === 'cancelled') {
            $query->where('status', 'cancelled');
        } elseif ($statusTab === 'returned') {
            $query->where('status', 'returned');
        } elseif ($statusTab !== 'all') {
            $query->where('status', $statusTab);
        }

        // Search
        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhereHas('buyer', fn ($bq) => $bq->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"))
                  ->orWhereHas('items', fn ($iq) => $iq->where('product_name', 'like', "%{$search}%"));
            });
        }

        // Date filter
        if ($request->query('from')) {
            $query->where('placed_at', '>=', Carbon::parse($request->query('from'))->startOfDay());
        }
        if ($request->query('to')) {
            $query->where('placed_at', '<=', Carbon::parse($request->query('to'))->endOfDay());
        }

        $orders = $query->paginate(15)->withQueryString();

        $baseCountQuery = fn () => Order::where(function ($q) use ($sellerId) {
            $q->where('seller_id', $sellerId)
              ->orWhereHas('items.product', fn ($sq) => $sq->where('seller_id', $sellerId));
        });

        $counts = [
            'all' => $baseCountQuery()->count(),
            'to_confirm' => $baseCountQuery()->where('status', 'placed')->count(),
            'processing' => $baseCountQuery()->whereIn('status', ['confirmed', 'preparing'])->count(),
            'ready_for_pickup' => $baseCountQuery()->where('status', 'ready_for_pickup')->count(),
            'shipped' => $baseCountQuery()->whereIn('status', ['picked_up', 'sorting_hub', 'in_transit', 'out_for_delivery'])->count(),
            'completed' => $baseCountQuery()->whereIn('status', ['delivered', 'completed'])->count(),
            'cancelled' => $baseCountQuery()->where('status', 'cancelled')->count(),
            'returned' => $baseCountQuery()->where('status', 'returned')->count(),
        ];

        return view('seller.orders.index', compact('orders', 'counts', 'statusTab'));
    }

    public function show(Request $request, Order $order): View
    {
        $this->authorizeSellerInvolved($request, $order);

        $order->load(['items.product', 'buyer', 'address', 'statusHistory.changedBy', 'dispute']);

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

        $order->buyer->notify(new OrderStatusUpdated($order, $nextStatus));

        return redirect()->route('seller.orders.show', $order)
            ->with('status', 'Order updated to "' . str_replace('_', ' ', $nextStatus) . '".');
    }

    public function cancel(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeSellerInvolved($request, $order);

        if (! $order->canBeCancelledBySeller()) {
            return back()->withErrors(['cancellation' => 'This order cannot be cancelled as parcel collection is already scheduled or in transit.']);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($order, $validated, $request) {
            // Restore inventory stock
            foreach ($order->items as $item) {
                if ($item->product) {
                    $item->product->increment('stock', $item->quantity);
                }
            }

            $order->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancellation_reason' => "[Seller Cancelled] " . $validated['reason'],
            ]);

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status' => 'cancelled',
                'changed_by' => $request->user()->id,
                'changed_at' => now(),
            ]);

            $order->buyer->notify(new OrderStatusUpdated($order, 'cancelled'));
        });

        return redirect()->route('seller.orders.show', $order)
            ->with('status', "Order {$order->order_number} was cancelled and inventory was restored.");
    }

    public function waybill(Request $request, Order $order): View
    {
        $this->authorizeSellerInvolved($request, $order);

        $order->load(['items.product', 'buyer', 'address', 'seller.sellerProfile']);

        return view('seller.orders.waybill', compact('order'));
    }

    private function authorizeSellerInvolved(Request $request, Order $order): void
    {
        $sellerId = $request->user()->id;
        $involved = ($order->seller_id === $sellerId) ||
            $order->items()->whereHas('product', fn ($q) => $q->where('seller_id', $sellerId))->exists();

        abort_unless($involved, 403);
    }
}
