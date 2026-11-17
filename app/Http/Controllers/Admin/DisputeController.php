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

        $disputes = $query->latest('updated_at')->latest('id')->paginate(15)->withQueryString();

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

            // Phase 9: Separate dispute resolution in favor of return from physical return receipt & inspection.
            // Never mark an order physically returned merely because Admin approved a return request.
            if ($newStatus === 'resolved' && $actionType === 'mark_returned') {
                $dispute->update([
                    'return_stage' => Dispute::RETURN_STAGE_APPROVED_AWAITING_RETURN,
                    'return_status' => 'return_authorized',
                    'return_authorized_at' => now(),
                ]);

                AuditLog::log(
                    action: 'dispute_return_authorized',
                    auditable: $dispute,
                    oldValues: ['return_stage' => $dispute->getOriginal('return_stage')],
                    newValues: ['return_stage' => Dispute::RETURN_STAGE_APPROVED_AWAITING_RETURN],
                    reason: "Return authorized for dispute #{$dispute->id}; awaiting physical parcel return & inspection"
                );
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

    public function recordReturnStep(Request $request, Dispute $dispute): RedirectResponse
    {
        $validated = $request->validate([
            'step' => ['required', Rule::in([
                Dispute::RETURN_STAGE_IN_RETURN_TRANSIT,
                Dispute::RETURN_STAGE_RECEIVED_FOR_INSPECTION,
                Dispute::RETURN_STAGE_INSPECTED,
                Dispute::RETURN_STAGE_REFUND_AUTHORIZED,
                Dispute::RETURN_STAGE_REFUND_RECORDED,
            ])],
            'return_tracking_number' => ['nullable', 'string', 'max:100'],
            'inspection_condition' => ['nullable', 'string', 'max:100'],
            'inspection_notes' => ['nullable', 'string', 'max:2000'],
            'restock_approved' => ['nullable', 'boolean'],
            'refund_amount' => ['nullable', 'numeric', 'min:0'],
            'refund_method' => ['nullable', 'string', 'max:60'],
            'refund_reference' => ['nullable', 'string', 'max:100'],
            'refund_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $step = $validated['step'];
        $adminId = $request->user()->id;

        DB::transaction(function () use ($dispute, $validated, $step, $adminId) {
            $oldStage = $dispute->return_stage;
            $updates = ['return_stage' => $step, 'return_status' => $step];

            if ($step === Dispute::RETURN_STAGE_IN_RETURN_TRANSIT) {
                $updates['return_tracking_number'] = $validated['return_tracking_number'] ?? $dispute->return_tracking_number;
            } elseif ($step === Dispute::RETURN_STAGE_RECEIVED_FOR_INSPECTION) {
                $updates['return_received_at'] = now();
                $updates['return_received_by'] = $adminId;
            } elseif ($step === Dispute::RETURN_STAGE_INSPECTED) {
                $updates['inspection_condition'] = $validated['inspection_condition'] ?? 'inspected';
                $updates['inspection_notes'] = $validated['inspection_notes'] ?? null;
                $updates['return_inspection_notes'] = $validated['inspection_notes'] ?? null;
                $updates['inspected_at'] = now();
                $updates['inspected_by'] = $adminId;
                $updates['restock_approved'] = (bool) ($validated['restock_approved'] ?? false);
                $updates['inventory_disposition'] = $updates['restock_approved'] ? 'restock' : 'damaged_write_off';

                if ($dispute->order && $dispute->order->status !== 'returned') {
                    $dispute->order->update(['status' => 'returned']);
                    OrderStatusHistory::create([
                        'order_id' => $dispute->order->id,
                        'status' => 'returned',
                        'changed_by' => $adminId,
                        'note' => 'Physical return received and inspected.',
                        'changed_at' => now(),
                    ]);
                }

                if ($updates['restock_approved'] && ! $dispute->restocked_at && $dispute->order) {
                    foreach ($dispute->order->items as $item) {
                        if ($item->product) {
                            $item->product->increment('stock', (int) $item->quantity);
                        }
                    }
                    $updates['restocked_at'] = now();
                }
            } elseif ($step === Dispute::RETURN_STAGE_REFUND_AUTHORIZED) {
                abort_unless($dispute->inspected_at !== null, 422, 'Physical return inspection must be completed before authorizing refund.');
                $updates['refund_amount'] = $validated['refund_amount'] ?? $dispute->order?->total ?? 0;
                $updates['refund_authorized_at'] = now();
                $updates['refund_authorized_by'] = $adminId;
            } elseif ($step === Dispute::RETURN_STAGE_REFUND_RECORDED) {
                abort_unless($dispute->refund_authorized_at !== null, 422, 'Refund must be authorized before recording disbursement.');
                abort_if(empty($validated['refund_reference']), 422, 'Actual refund reference is required.');
                $updates['refund_method'] = $validated['refund_method'] ?? 'manual_transfer';
                $updates['refund_reference'] = $validated['refund_reference'];
                $updates['refund_notes'] = $validated['refund_notes'] ?? null;
                $updates['refund_recorded_at'] = now();
                $updates['refund_recorded_by'] = $adminId;
                $updates['refunded_at'] = now();
            }

            $dispute->update($updates);

            AuditLog::log(
                action: 'dispute_return_step_recorded',
                auditable: $dispute,
                oldValues: ['return_stage' => $oldStage],
                newValues: ['return_stage' => $step],
                reason: "Return workflow advanced to {$step}",
                operatorId: $adminId
            );
        });

        return back()->with('status', 'Return workflow updated to ' . ucwords(str_replace('_', ' ', $step)) . '.');
    }
}
