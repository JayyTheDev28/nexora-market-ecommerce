<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrdersController extends Controller
{
    public function index(Request $request): View
    {
        $orders = $request->user()->orders()
            ->with(['items', 'dispute', 'review'])
            ->latest('placed_at')
            ->get();

        return view('buyer.orders.index', compact('orders'));
    }

    public function confirmReceipt(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeOwner($request, $order);

        abort_unless($order->status === 'out_for_delivery' || $order->status === 'delivered', 400);

        $order->update(['status' => 'completed']);
        $order->statusHistory()->create([
            'order_id' => $order->id,
            'status' => 'completed',
            'changed_by' => $request->user()->id,
            'changed_at' => now(),
        ]);

        return back()->with('status', 'Thanks for confirming! Order marked as completed.');
    }

    public function submitFeedback(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeOwner($request, $order);

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        Review::updateOrCreate(
            ['order_id' => $order->id],
            ['buyer_id' => $request->user()->id, 'rating' => $validated['rating'], 'comment' => $validated['comment'] ?? null]
        );

        return back()->with('status', 'Thanks for your feedback!');
    }

    public function submitDispute(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeOwner($request, $order);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'string', 'max:1000'],
        ]);

        $order->dispute()->updateOrCreate(
            ['order_id' => $order->id],
            ['raised_by' => $request->user()->id, 'reason' => $validated['reason'], 'details' => $validated['details'] ?? null, 'status' => 'open']
        );

        return back()->with('status', 'Your dispute has been submitted. Our team will review it shortly.');
    }

    private function authorizeOwner(Request $request, Order $order): void
    {
        abort_if($order->buyer_id !== $request->user()->id, 403);
    }
}
