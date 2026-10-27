<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Notifications\OrderStatusUpdated;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReturnController extends Controller
{
    public function index(Request $request): View
    {
        $sellerId = $request->user()->id;

        $query = Dispute::whereHas('order', function ($q) use ($sellerId) {
            $q->where('seller_id', $sellerId)
              ->orWhereHas('items.product', fn ($sq) => $sq->where('seller_id', $sellerId));
        })
        ->with(['order.buyer', 'order.items.product', 'raisedBy'])
        ->latest();

        if ($status = $request->query('status')) {
            if ($status === 'pending') {
                $query->where('seller_status', 'pending');
            } elseif ($status === 'approved') {
                $query->where('seller_status', 'approved');
            } elseif ($status === 'rejected') {
                $query->where('seller_status', 'rejected');
            }
        }

        $disputes = $query->paginate(15)->withQueryString();

        $counts = [
            'all' => Dispute::whereHas('order', fn ($q) => $q->where('seller_id', $sellerId))->count(),
            'pending' => Dispute::whereHas('order', fn ($q) => $q->where('seller_id', $sellerId))->where('seller_status', 'pending')->count(),
            'approved' => Dispute::whereHas('order', fn ($q) => $q->where('seller_id', $sellerId))->where('seller_status', 'approved')->count(),
            'rejected' => Dispute::whereHas('order', fn ($q) => $q->where('seller_id', $sellerId))->where('seller_status', 'rejected')->count(),
        ];

        return view('seller.returns.index', compact('disputes', 'counts'));
    }

    public function show(Request $request, Dispute $dispute): View
    {
        $this->authorizeSeller($request, $dispute);

        $dispute->load(['order.buyer', 'order.items.product', 'order.address', 'raisedBy']);

        return view('seller.returns.show', compact('dispute'));
    }

    public function approve(Request $request, Dispute $dispute): RedirectResponse
    {
        $this->authorizeSeller($request, $dispute);

        $validated = $request->validate([
            'return_instructions' => ['nullable', 'string', 'max:1000'],
            'seller_response' => ['nullable', 'string', 'max:1000'],
            'restore_stock' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($dispute, $validated, $request) {
            $dispute->update([
                'seller_status' => 'approved',
                'seller_response' => $validated['seller_response'] ?? 'Return request approved by seller.',
                'seller_response_at' => now(),
                'return_instructions' => $validated['return_instructions'] ?? null,
                'status' => 'resolved',
                'resolution_note' => 'Approved by merchant: ' . ($validated['seller_response'] ?? 'Eligible return/refund.'),
            ]);

            $order = $dispute->order;
            $order->update(['status' => 'returned']);

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status' => 'returned',
                'changed_by' => $request->user()->id,
                'changed_at' => now(),
            ]);

            // Restore product stock if selected
            if ($request->boolean('restore_stock', true)) {
                foreach ($order->items as $item) {
                    if ($item->product) {
                        $item->product->increment('stock', $item->quantity);
                    }
                }
            }

            // Notify buyer
            $order->buyer->notify(new OrderStatusUpdated($order, 'returned'));
        });

        return redirect()->route('seller.returns.show', $dispute)
            ->with('status', 'Return request has been approved. The buyer has been notified with return instructions.');
    }

    public function reject(Request $request, Dispute $dispute): RedirectResponse
    {
        $this->authorizeSeller($request, $dispute);

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $dispute->update([
            'seller_status' => 'rejected',
            'seller_response' => $validated['rejection_reason'],
            'seller_response_at' => now(),
        ]);

        return redirect()->route('seller.returns.show', $dispute)
            ->with('status', 'Return request rejected. Reason recorded and communicated to buyer.');
    }

    private function authorizeSeller(Request $request, Dispute $dispute): void
    {
        $sellerId = $request->user()->id;
        $order = $dispute->order;

        $involved = ($order->seller_id === $sellerId) ||
            $order->items()->whereHas('product', fn ($q) => $q->where('seller_id', $sellerId))->exists();

        abort_unless($involved, 403);
    }
}
