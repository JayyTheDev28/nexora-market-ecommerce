<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class OrdersController extends Controller
{
    /**
     * B10: Order list with status tabs, auto-cancellation for expired unpaid orders,
     * and 7-day auto-completion for delivered orders.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        // Check & process background auto-cancellations (1-hour expired unpaid gateway orders)
        $this->processExpiredUnpaidOrders($user->id);

        // Check & process 7-day auto-completion
        $this->processDeliveredAutoCompletion($user->id);

        $activeTab = $request->query('tab', 'all');

        $query = $user->orders()
            ->with(['items.product', 'seller.sellerProfile', 'dispute', 'review', 'statusHistory'])
            ->latest('placed_at');

        // Apply Tab Filter (Rule B10)
        match ($activeTab) {
            'to_pay' => $query->where('payment_status', 'unpaid')
                ->whereIn('payment_method', ['card', 'ewallet'])
                ->where('status', '!=', 'cancelled'),

            'to_ship' => $query->whereIn('status', ['placed', 'confirmed', 'preparing'])
                ->where(function ($q) {
                    $q->where('payment_method', 'cod')
                      ->orWhere('payment_status', 'paid');
                }),

            'to_receive' => $query->whereIn('status', [
                'ready_for_pickup',
                'picked_up',
                'at_sorting_center',
                'sorted',
                'assigned_to_rider',
                'out_for_delivery',
            ]),

            'completed' => $query->whereIn('status', ['completed', 'delivered']),

            'cancelled' => $query->where('status', 'cancelled'),

            'return_refund' => $query->where(function ($q) {
                $q->where('status', 'returned')
                  ->orWhereHas('dispute');
            }),

            default => null,
        };

        $orders = $query->get();

        // Calculate counts for each tab
        $allOrders = $user->orders()->get();
        $counts = [
            'all' => $allOrders->count(),
            'to_pay' => $allOrders->where('payment_status', 'unpaid')
                ->whereIn('payment_method', ['card', 'ewallet'])
                ->where('status', '!=', 'cancelled')
                ->count(),
            'to_ship' => $allOrders->whereIn('status', ['placed', 'confirmed', 'preparing'])
                ->filter(fn ($o) => $o->payment_method === 'cod' || $o->payment_status === 'paid')
                ->count(),
            'to_receive' => $allOrders->whereIn('status', [
                'ready_for_pickup',
                'picked_up',
                'at_sorting_center',
                'sorted',
                'assigned_to_rider',
                'out_for_delivery',
            ])->count(),
            'completed' => $allOrders->whereIn('status', ['completed', 'delivered'])->count(),
            'cancelled' => $allOrders->where('status', 'cancelled')->count(),
            'return_refund' => $allOrders->filter(fn ($o) => $o->status === 'returned' || $o->dispute !== null)->count(),
        ];

        return view('buyer.orders.index', compact('orders', 'activeTab', 'counts'));
    }

    /**
     * B11: Pre-shipping cancellation
     * Allowed while status is 'placed' or 'confirmed' before logistics pickup/preparing.
     * Restores product stock.
     */
    public function cancelOrder(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeOwner($request, $order);

        if (! $order->canBeCancelledByBuyer()) {
            return back()->withErrors(['cancellation' => 'This order cannot be cancelled as it is already being processed or prepared for pickup.']);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($order, $validated, $request) {
            // Restore product stock
            foreach ($order->items as $item) {
                if ($item->product) {
                    $item->product->increment('stock', $item->quantity);
                }
            }

            $order->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancellation_reason' => $validated['reason'],
            ]);

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status' => 'cancelled',
                'changed_by' => $request->user()->id,
                'changed_at' => now(),
            ]);
        });

        return back()->with('status', "Order {$order->order_number} has been cancelled and items were restored.");
    }

    /**
     * Confirm delivery receipt
     */
    public function confirmReceipt(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeOwner($request, $order);

        abort_unless(in_array($order->status, ['out_for_delivery', 'delivered'], true), 400);

        DB::transaction(function () use ($order, $request) {
            $order->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status' => 'completed',
                'changed_by' => $request->user()->id,
                'changed_at' => now(),
            ]);
        });

        return back()->with('status', 'Thank you for confirming receipt! The order is now completed.');
    }

    /**
     * B12: 7-day return request window requiring 1-5 photos/videos and description
     */
    public function submitReturnRequest(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeOwner($request, $order);

        if (! $order->canBeReturned()) {
            return back()->withErrors(['return' => 'Return/Refund request window is only valid within 7 days of delivery.']);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'details' => ['required', 'string', 'min:10', 'max:1500'],
            'media' => ['required', 'array', 'min:1', 'max:5'],
            'media.*' => ['file', 'mimes:jpg,jpeg,png,webp,mp4,mov', 'max:10240'],
        ], [
            'media.required' => 'Please provide at least 1 photo or video proof for your return request.',
            'media.min' => 'You must upload at least 1 photo or video evidence.',
            'media.max' => 'You may upload a maximum of 5 evidence files.',
        ]);

        $uploadedPaths = [];
        if ($request->hasFile('media')) {
            foreach ($request->file('media') as $file) {
                $uploadedPaths[] = $file->store('returns', 'public');
            }
        }

        $order->dispute()->create([
            'raised_by' => $request->user()->id,
            'reason' => $validated['reason'],
            'details' => $validated['details'],
            'media_paths' => $uploadedPaths,
            'status' => 'open',
        ]);

        return back()->with('status', 'Your return/refund request has been submitted with evidence. Our team and seller will review it promptly.');
    }

    /**
     * B13: Submit or edit product review within 30 days of completion (1 edit allowed)
     */
    public function submitReview(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeOwner($request, $order);

        if (! $order->canBeReviewed()) {
            return back()->withErrors(['review' => 'Reviews can only be submitted for completed orders within 30 days.']);
        }

        $existingReview = $order->review;

        // B13: 1 edit allowed
        if ($existingReview && ! $existingReview->canBeEdited()) {
            return back()->withErrors(['review' => 'You have already reached the maximum limit of 1 edit for this review.']);
        }

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => ['image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ]);

        $photoPaths = $existingReview ? ($existingReview->photos ?? []) : [];
        if ($request->hasFile('photos')) {
            $photoPaths = [];
            foreach ($request->file('photos') as $file) {
                $photoPaths[] = $file->store('reviews', 'public');
            }
        }

        if ($existingReview) {
            $existingReview->update([
                'rating' => $validated['rating'],
                'comment' => $validated['comment'] ?? null,
                'photos' => $photoPaths,
                'edited_count' => $existingReview->edited_count + 1,
            ]);
            $msg = 'Your review has been updated (1 edit maximum reached).';
        } else {
            Review::create([
                'order_id' => $order->id,
                'product_id' => $order->items->first()?->product_id,
                'buyer_id' => $request->user()->id,
                'rating' => $validated['rating'],
                'comment' => $validated['comment'] ?? null,
                'photos' => $photoPaths,
                'edited_count' => 0,
            ]);
            $msg = 'Thank you for your rating and feedback!';
        }

        return back()->with('status', $msg);
    }

    /**
     * Auto-cancel 60-min expired unpaid gateway orders
     */
    private function processExpiredUnpaidOrders(int $userId): void
    {
        $expired = Order::where('buyer_id', $userId)
            ->where('payment_status', 'unpaid')
            ->whereIn('payment_method', ['card', 'ewallet'])
            ->where('status', 'placed')
            ->whereNotNull('payment_expires_at')
            ->where('payment_expires_at', '<', now())
            ->get();

        foreach ($expired as $order) {
            DB::transaction(function () use ($order) {
                foreach ($order->items as $item) {
                    if ($item->product) {
                        $item->product->increment('stock', $item->quantity);
                    }
                }

                $order->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancellation_reason' => 'Payment expired (unpaid after 60 minutes)',
                ]);

                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'status' => 'cancelled',
                    'changed_by' => $order->buyer_id,
                    'changed_at' => now(),
                ]);
            });
        }
    }

    /**
     * Auto-complete delivered orders older than 7 days
     */
    private function processDeliveredAutoCompletion(int $userId): void
    {
        $deliveredExpired = Order::where('buyer_id', $userId)
            ->where('status', 'delivered')
            ->whereNotNull('delivered_at')
            ->where('delivered_at', '<=', now()->subDays(7))
            ->whereDoesntHave('dispute')
            ->get();

        foreach ($deliveredExpired as $order) {
            DB::transaction(function () use ($order) {
                $order->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);

                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'status' => 'completed',
                    'changed_by' => $order->buyer_id,
                    'changed_at' => now(),
                ]);
            });
        }
    }

    private function authorizeOwner(Request $request, Order $order): void
    {
        abort_if($order->buyer_id !== $request->user()->id, 403);
    }
}
