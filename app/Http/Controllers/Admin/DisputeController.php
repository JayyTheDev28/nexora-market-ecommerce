<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Dispute;
use App\Models\OrderStatusHistory;
use App\Notifications\DisputeResolvedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DisputeController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status', 'all');
        $search = $request->query('q');

        $query = Dispute::query()->with(['order.buyer', 'order.seller.sellerProfile', 'raisedBy']);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('reason', 'like', "%{$search}%")
                    ->orWhere('details', 'like', "%{$search}%")
                    ->orWhereHas('order', fn ($oq) => $oq->where('order_number', 'like', "%{$search}%"))
                    ->orWhereHas('raisedBy', fn ($uq) => $uq->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%"));
            });
        }

        $disputes = $query->latest()->paginate(15)->withQueryString();

        $counts = [
            'all' => Dispute::count(),
            'open' => Dispute::where('status', 'open')->count(),
            'under_review' => Dispute::where('status', 'under_review')->count(),
            'resolved' => Dispute::where('status', 'resolved')->count(),
            'rejected' => Dispute::where('status', 'rejected')->count(),
        ];

        return view('admin.disputes.index', compact('disputes', 'status', 'search', 'counts'));
    }

    public function show(Dispute $dispute): View
    {
        $dispute->load([
            'order.buyer.address',
            'order.seller.sellerProfile',
            'order.items.product',
            'order.statusHistory.changer',
            'raisedBy',
        ]);

        return view('admin.disputes.show', compact('dispute'));
    }

    public function resolve(Request $request, Dispute $dispute): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['open', 'under_review', 'resolved', 'rejected'])],
            'resolution_note' => ['required', 'string', 'max:2000'],
            'action_type' => ['nullable', Rule::in(['maintain_status', 'mark_returned'])],
        ]);

        $oldStatus = $dispute->status;
        $newStatus = $validated['status'];
        $note = $validated['resolution_note'];
        $actionType = $validated['action_type'] ?? 'maintain_status';

        DB::transaction(function () use ($dispute, $newStatus, $note, $actionType, $oldStatus) {
            $dispute->update([
                'status' => $newStatus,
                'resolution_note' => $note,
            ]);

            $order = $dispute->order;

            // If resolved in favor of return/refund, transition order status to returned
            if ($newStatus === 'resolved' && $actionType === 'mark_returned') {
                if ($order && $order->status !== 'returned') {
                    $prevOrderStatus = $order->status;
                    $order->update(['status' => 'returned']);

                    OrderStatusHistory::create([
                        'order_id' => $order->id,
                        'status' => 'returned',
                        'changed_by' => auth()->id(),
                        'changed_at' => now(),
                    ]);

                    AuditLog::log(
                        action: 'order_status_overridden_dispute',
                        auditable: $order,
                        oldValues: ['status' => $prevOrderStatus],
                        newValues: ['status' => 'returned'],
                        reason: "Order marked as returned per dispute resolution #{$dispute->id}"
                    );
                }
            }

            AuditLog::log(
                action: 'dispute_resolved',
                auditable: $dispute,
                oldValues: ['status' => $oldStatus],
                newValues: ['status' => $newStatus, 'resolution_note' => $note],
                reason: $note
            );
        });

        // Notify buyer and seller
        $order = $dispute->order;
        if ($order) {
            if ($order->buyer) {
                try {
                    $order->buyer->notify(new DisputeResolvedNotification($dispute, 'buyer'));
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning("Dispute notification failed for buyer: " . $e->getMessage());
                }
            }
            if ($order->seller) {
                try {
                    $order->seller->notify(new DisputeResolvedNotification($dispute, 'seller'));
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning("Dispute notification failed for seller: " . $e->getMessage());
                }
            }
        }

        return redirect()->route('admin.disputes.show', $dispute)
            ->with('status', "Dispute updated to '{$newStatus}'. Parties notified.");
    }
}
