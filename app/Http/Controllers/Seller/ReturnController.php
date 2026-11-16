<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Notifications\OrderStatusUpdated;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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

        $dispute->load([
            'order.buyer',
            'order.items.product',
            'order.address',
            'raisedBy',
            'returnReceivedBy',
            'inspectedBy',
            'refundAuthorizedBy',
            'refundRecordedBy',
        ]);

        return view('seller.returns.show', compact('dispute'));
    }

    /**
     * Stage 1: Return approval with return instructions.
     * NEVER restocks inventory or settles refund before physical receipt & inspection!
     */
    public function approve(Request $request, Dispute $dispute): RedirectResponse
    {
        $this->authorizeSeller($request, $dispute);

        abort_if($dispute->seller_status !== 'pending', 422, 'Return request has already been reviewed by seller.');

        $validated = $request->validate([
            'return_instructions' => ['nullable', 'string', 'max:1000'],
            'seller_response' => ['nullable', 'string', 'max:1000'],
            'return_tracking_number' => ['nullable', 'string', 'max:100'],
        ]);

        DB::transaction(function () use ($dispute, $validated, $request) {
            $dispute->update([
                'seller_status' => 'approved',
                'return_stage' => Dispute::RETURN_STAGE_APPROVED_AWAITING_RETURN,
                'seller_response' => $validated['seller_response'] ?? 'Return request approved. Awaiting physical parcel return and inspection.',
                'seller_response_at' => now(),
                'return_instructions' => $validated['return_instructions'] ?? null,
                'return_tracking_number' => $validated['return_tracking_number'] ?? $dispute->return_tracking_number,
                'status' => 'under_review',
                'resolution_note' => 'Return approved by merchant; awaiting physical return receipt before inspection, restock, and refund.',
            ]);

            $order = $dispute->order;
            if ($order) {
                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'status' => $order->status,
                    'changed_by' => $request->user()->id,
                    'changed_at' => now(),
                    'note' => 'Seller approved return request; awaiting buyer return shipment.',
                ]);

                AuditLog::log('return_approved_awaiting_receipt', $dispute, null, [
                    'order_id' => $order->id,
                    'return_stage' => Dispute::RETURN_STAGE_APPROVED_AWAITING_RETURN,
                ], $request->user()->id);

                if ($order->buyer) {
                    $order->buyer->notify(new OrderStatusUpdated($order, $order->status));
                }
            }
        });

        return redirect()->route('seller.returns.show', $dispute)
            ->with('status', 'Return request approved. Inventory and refund remain held until physical parcel receipt and inspection are recorded.');
    }

    /**
     * Stage 2 & 3: Record physical receipt of the returned parcel before inspection/restock.
     */
    public function markReceived(Request $request, Dispute $dispute): RedirectResponse
    {
        $this->authorizeSeller($request, $dispute);

        abort_unless($dispute->seller_status === 'approved', 422, 'Return must be approved before recording physical return receipt.');
        abort_if($dispute->return_received_at !== null, 422, 'Physical return receipt was already recorded.');

        $validated = $request->validate([
            'return_tracking_number' => ['nullable', 'string', 'max:100'],
        ]);

        DB::transaction(function () use ($dispute, $validated, $request) {
            $dispute->update([
                'return_stage' => Dispute::RETURN_STAGE_RECEIVED_FOR_INSPECTION,
                'return_tracking_number' => $validated['return_tracking_number'] ?? $dispute->return_tracking_number,
                'return_received_at' => now(),
                'return_received_by' => $request->user()->id,
            ]);

            AuditLog::log('return_physically_received', $dispute, null, [
                'order_id' => $dispute->order_id,
                'return_tracking_number' => $dispute->return_tracking_number,
            ], $request->user()->id);
        });

        return redirect()->route('seller.returns.show', $dispute)
            ->with('status', 'Physical return parcel receipt recorded. Complete condition inspection next.');
    }

    /**
     * Stage 4: Physical condition inspection & conditional inventory restock decision.
     */
    public function inspect(Request $request, Dispute $dispute): RedirectResponse
    {
        $this->authorizeSeller($request, $dispute);

        abort_if($dispute->return_received_at === null, 422, 'Cannot inspect or restock before recording physical return receipt.');
        abort_if($dispute->inspected_at !== null, 422, 'Return inspection has already been completed.');

        $validated = $request->validate([
            'inspection_condition' => ['required', Rule::in(['restockable', 'damaged_resellable', 'damaged_unusable', 'missing_item'])],
            'inspection_notes' => ['required', 'string', 'min:5', 'max:1500'],
            'restock_approved' => ['nullable', 'boolean'],
        ]);

        $wantsRestock = $request->boolean('restock_approved', false);
        if ($wantsRestock && in_array($validated['inspection_condition'], ['damaged_unusable', 'missing_item'], true)) {
            return back()->withErrors(['restock_approved' => 'Unusable or missing items cannot be restocked into sellable inventory.']);
        }

        DB::transaction(function () use ($dispute, $validated, $wantsRestock, $request) {
            $now = now();
            $dispute->update([
                'return_stage' => Dispute::RETURN_STAGE_INSPECTED,
                'inspection_condition' => $validated['inspection_condition'],
                'inspection_notes' => $validated['inspection_notes'],
                'inspected_at' => $now,
                'inspected_by' => $request->user()->id,
                'restock_approved' => $wantsRestock,
                'restocked_at' => $wantsRestock ? $now : null,
            ]);

            $order = $dispute->order;
            if ($order) {
                if ($wantsRestock) {
                    foreach ($order->items as $item) {
                        if ($item->product) {
                            $item->product->increment('stock', $item->quantity);
                        }
                    }
                }

                if ($order->status !== 'returned') {
                    $order->update(['status' => 'returned']);

                    OrderStatusHistory::create([
                        'order_id' => $order->id,
                        'status' => 'returned',
                        'changed_by' => $request->user()->id,
                        'changed_at' => $now,
                        'note' => 'Returned parcel physically received & inspected (' . $validated['inspection_condition'] . ').',
                    ]);
                }

                AuditLog::log('return_inspected', $dispute, null, [
                    'order_id' => $order->id,
                    'inspection_condition' => $validated['inspection_condition'],
                    'restocked' => $wantsRestock,
                ], $request->user()->id);
            }
        });

        return redirect()->route('seller.returns.show', $dispute)
            ->with('status', 'Inspection saved' . ($wantsRestock ? ' and verified items restocked.' : ' (no inventory restock performed).') . ' Proceed to refund authorization.');
    }

    /**
     * Stage 5a: Authorize refund amount and payout channel (does not fabricate gateway payout).
     */
    public function authorizeRefund(Request $request, Dispute $dispute): RedirectResponse
    {
        $this->authorizeSeller($request, $dispute);

        abort_if($dispute->inspected_at === null, 422, 'Complete return receipt and inspection before authorizing a refund.');
        abort_if($dispute->refund_authorized_at !== null, 422, 'Refund authorization has already been recorded.');

        $maxRefund = (float) ($dispute->order?->total ?? 0);
        $validated = $request->validate([
            'refund_amount' => ['required', 'numeric', 'min:1', 'max:' . max(1, $maxRefund)],
            'refund_method' => ['required', Rule::in(['gcash', 'maya', 'bank_transfer', 'manual_cash'])],
            'refund_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($dispute, $validated, $request) {
            $dispute->update([
                'return_stage' => Dispute::RETURN_STAGE_REFUND_AUTHORIZED,
                'refund_amount' => $validated['refund_amount'],
                'refund_method' => $validated['refund_method'],
                'refund_authorized_at' => now(),
                'refund_authorized_by' => $request->user()->id,
                'refund_notes' => $validated['refund_notes'] ?? $dispute->refund_notes,
            ]);

            AuditLog::log('return_refund_authorized', $dispute, null, [
                'order_id' => $dispute->order_id,
                'refund_amount' => (float) $validated['refund_amount'],
                'refund_method' => $validated['refund_method'],
            ], $request->user()->id);
        });

        return redirect()->route('seller.returns.show', $dispute)
            ->with('status', 'Refund authorized. Record the verified payout reference after manual transfer completes.');
    }

    /**
     * Stage 5b: Record verified manual refund reference (never simulates fake payment gateway).
     */
    public function recordRefund(Request $request, Dispute $dispute): RedirectResponse
    {
        $this->authorizeSeller($request, $dispute);

        abort_if($dispute->refund_authorized_at === null, 422, 'Authorize the refund before recording a payout reference.');
        abort_if($dispute->refund_recorded_at !== null, 422, 'Refund payout reference has already been recorded.');

        $validated = $request->validate([
            'refund_reference' => ['required', 'string', 'min:4', 'max:120'],
            'refund_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($dispute, $validated, $request) {
            $dispute->update([
                'return_stage' => Dispute::RETURN_STAGE_REFUND_RECORDED,
                'refund_reference' => trim($validated['refund_reference']),
                'refund_recorded_at' => now(),
                'refund_recorded_by' => $request->user()->id,
                'refund_notes' => $validated['refund_notes'] ?? $dispute->refund_notes,
                'status' => 'resolved',
                'resolution_note' => 'Return inspected and manual refund verified (Ref: ' . trim($validated['refund_reference']) . ').',
            ]);

            AuditLog::log('return_refund_recorded', $dispute, null, [
                'order_id' => $dispute->order_id,
                'refund_amount' => (float) $dispute->refund_amount,
                'refund_reference' => trim($validated['refund_reference']),
            ], $request->user()->id);

            $order = $dispute->order;
            if ($order?->buyer) {
                $order->buyer->notify(new OrderStatusUpdated($order, 'returned'));
            }
        });

        return redirect()->route('seller.returns.show', $dispute)
            ->with('status', 'Verified manual refund payout recorded and return dispute closed.');
    }

    public function reject(Request $request, Dispute $dispute): RedirectResponse
    {
        $this->authorizeSeller($request, $dispute);

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $dispute->update([
            'seller_status' => 'rejected',
            'return_stage' => Dispute::RETURN_STAGE_REJECTED,
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
