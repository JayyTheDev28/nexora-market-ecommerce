<?php

namespace App\Http\Controllers\Api\Mobile\Buyer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Mobile\CancelOrderRequest;
use App\Http\Requests\Api\Mobile\ReturnOrderRequest;
use App\Http\Requests\Api\Mobile\ReviewOrderRequest;
use App\Http\Resources\Api\Mobile\OrderDetailResource;
use App\Http\Resources\Api\Mobile\OrderResource;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * B10: Buyer orders list with status tabs
     * GET /api/mobile/v1/buyer/orders
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Check & process background auto-cancellations (1-hour expired unpaid gateway orders)
        $this->processExpiredUnpaidOrders($user->id);

        // Check & process 7-day auto-completion
        $this->processDeliveredAutoCompletion($user->id);

        $activeTab = $request->query('tab', 'all');

        $query = $user->orders()
            ->with([
                'items.product',
                'seller.sellerProfile',
                'deliveryCourier.courierProfile',
                'currentHub',
                'originHub',
                'destinationHub',
                'dispute',
                'review',
                'statusHistory',
            ])
            ->latest('placed_at');

        $toReceiveStatuses = [
            'ready_for_pickup',
            'assigned',
            'picked_up',
            'at_hub',
            'at_sorting_center',
            'received_at_hub',
            'in_transit_hub',
            'in_transit',
            'sorting_hub',
            'sorted',
            'assigned_to_rider',
            'out_for_delivery',
            'failed_delivery',
            'delivery_failed',
        ];

        $returnRefundStatuses = [
            'returned',
            'return_to_sender',
            'returned_to_sender',
        ];

        match ($activeTab) {
            'to_pay' => $query->whereRaw('1 = 0'),
            'to_ship' => $query->whereIn('status', ['placed', 'confirmed', 'preparing']),
            'to_receive' => $query->whereIn('status', $toReceiveStatuses),
            'completed' => $query->whereIn('status', ['completed', 'delivered']),
            'cancelled' => $query->where('status', 'cancelled'),
            'return_refund' => $query->where(function ($q) use ($returnRefundStatuses) {
                $q->whereIn('status', $returnRefundStatuses)
                  ->orWhereHas('dispute');
            }),
            default => null,
        };

        $perPage = min(50, max(1, (int) $request->query('per_page', 20)));
        $paginated = $query->paginate($perPage)->withQueryString();

        foreach ($paginated->items() as $orderItem) {
            if ($orderItem->status === 'out_for_delivery' && empty($orderItem->delivery_otp)) {
                $orderItem->generateDeliveryOtp();
            }
        }

        // Calculate tab counts
        $allOrders = $user->orders()->with('dispute:id,order_id')->get();
        $counts = [
            'all' => $allOrders->count(),
            'to_pay' => 0,
            'to_ship' => $allOrders->whereIn('status', ['placed', 'confirmed', 'preparing'])->count(),
            'to_receive' => $allOrders->whereIn('status', $toReceiveStatuses)->count(),
            'completed' => $allOrders->whereIn('status', ['completed', 'delivered'])->count(),
            'cancelled' => $allOrders->where('status', 'cancelled')->count(),
            'return_refund' => $allOrders->filter(
                fn ($o) => in_array($o->status, $returnRefundStatuses, true) || $o->dispute !== null
            )->count(),
        ];

        return response()->json([
            'data' => OrderResource::collection($paginated->items()),
            'counts' => $counts,
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ],
            'message' => 'Orders retrieved successfully.',
        ]);
    }

    /**
     * Order detail
     * GET /api/mobile/v1/buyer/orders/{order}
     */
    public function show(Request $request, Order $order): JsonResponse
    {
        if ($order->buyer_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($order->status === 'out_for_delivery' && empty($order->delivery_otp)) {
            $order->generateDeliveryOtp();
        }

        $order->load([
            'items.product',
            'seller.sellerProfile',
            'deliveryCourier.courierProfile',
            'currentHub',
            'originHub',
            'destinationHub',
            'dispute',
            'review',
            'statusHistory',
        ]);

        return response()->json([
            'data' => new OrderDetailResource($order),
            'message' => 'Order details retrieved successfully.',
        ]);
    }

    /**
     * B11: Pre-shipping cancellation
     * POST /api/mobile/v1/buyer/orders/{order}/cancel
     */
    public function cancelOrder(CancelOrderRequest $request, Order $order): JsonResponse
    {
        if ($order->buyer_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if (! $order->canBeCancelledByBuyer()) {
            return response()->json([
                'message' => 'This order cannot be cancelled as it is already being processed or prepared for pickup.',
                'errors' => ['cancellation' => ['Order cannot be cancelled at this stage.']],
            ], 422);
        }

        $validated = $request->validated();

        DB::transaction(function () use ($order, $validated, $request) {
            foreach ($order->items as $item) {
                if ($item->product) {
                    $item->product->increment('stock', $item->quantity);

                    $variation = is_array($item->variant) ? ($item->variant['variation'] ?? $item->variant['name'] ?? null) : null;
                    if ($variation && is_array($item->product->variants_data)) {
                        $variantsData = $item->product->variants_data;
                        $updatedVariant = false;
                        foreach ($variantsData as $k => $v) {
                            if (! is_array($v)) {
                                continue;
                            }
                            $vName = $v['name'] ?? (is_string($k) ? str_replace('_', ' / ', $k) : null);
                            $vSku = $v['sku'] ?? null;
                            if ($vName === $variation || $vSku === $variation || (string) $k === $variation) {
                                if (isset($v['stock'])) {
                                    $variantsData[$k]['stock'] = (int) $v['stock'] + (int) $item->quantity;
                                    $updatedVariant = true;
                                }
                                break;
                            }
                        }
                        if ($updatedVariant) {
                            $item->product->update(['variants_data' => $variantsData]);
                        }
                    }
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

        return response()->json([
            'data' => new OrderDetailResource($order->fresh([
                'items.product',
                'seller.sellerProfile',
                'deliveryCourier.courierProfile',
                'currentHub',
                'originHub',
                'destinationHub',
                'dispute',
                'review',
                'statusHistory',
            ])),
            'message' => "Order {$order->order_number} has been cancelled and items were restored.",
        ]);
    }

    /**
     * Confirm delivery receipt
     * POST /api/mobile/v1/buyer/orders/{order}/confirm-receipt
     */
    public function confirmReceipt(Request $request, Order $order): JsonResponse
    {
        if ($order->buyer_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if (! in_array($order->status, ['out_for_delivery', 'delivered'], true)) {
            return response()->json([
                'message' => 'Order cannot be confirmed before delivery dispatch.',
                'errors' => ['status' => ['Current status is ' . $order->status]],
            ], 422);
        }

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

        return response()->json([
            'data' => new OrderDetailResource($order->fresh([
                'items.product',
                'seller.sellerProfile',
                'deliveryCourier.courierProfile',
                'currentHub',
                'originHub',
                'destinationHub',
                'dispute',
                'review',
                'statusHistory',
            ])),
            'message' => 'Thank you for confirming receipt! The order is now completed.',
        ]);
    }

    /**
     * B12: 7-day return request with media upload
     * POST /api/mobile/v1/buyer/orders/{order}/return
     */
    public function submitReturn(ReturnOrderRequest $request, Order $order): JsonResponse
    {
        if ($order->buyer_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if (! $order->canBeReturned()) {
            return response()->json([
                'message' => 'Return/Refund request window is only valid within 7 days of delivery.',
                'errors' => ['return' => ['Return window expired or invalid status.']],
            ], 422);
        }

        $validated = $request->validated();
        $uploadedPaths = [];
        if ($request->hasFile('media')) {
            foreach ($request->file('media') as $file) {
                $uploadedPaths[] = $file->store('returns', 'public');
            }
        }

        $dispute = $order->dispute()->create([
            'raised_by' => $request->user()->id,
            'reason' => $validated['reason'],
            'details' => $validated['details'],
            'media_paths' => $uploadedPaths,
            'status' => 'open',
        ]);

        return response()->json([
            'data' => [
                'order' => new OrderDetailResource($order->fresh([
                    'items.product',
                    'seller.sellerProfile',
                    'deliveryCourier.courierProfile',
                    'currentHub',
                    'originHub',
                    'destinationHub',
                    'dispute',
                    'review',
                    'statusHistory',
                ])),
            ],
            'message' => 'Your return/refund request has been submitted with evidence.',
        ], 201);
    }

    /**
     * B13: Submit or edit product review within 30 days
     * POST /api/mobile/v1/buyer/orders/{order}/review
     */
    public function submitReview(ReviewOrderRequest $request, Order $order): JsonResponse
    {
        if ($order->buyer_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if (! $order->canBeReviewed()) {
            return response()->json([
                'message' => 'Reviews can only be submitted for completed orders within 30 days.',
                'errors' => ['review' => ['Review window expired or order not completed.']],
            ], 422);
        }

        $existingReview = $order->review;
        if ($existingReview && ! $existingReview->canBeEdited()) {
            return response()->json([
                'message' => 'You have already reached the maximum limit of 1 edit for this review.',
                'errors' => ['review' => ['Maximum 1 edit reached.']],
            ], 422);
        }

        $validated = $request->validated();
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

        return response()->json([
            'data' => [
                'order' => new OrderDetailResource($order->fresh([
                    'items.product',
                    'seller.sellerProfile',
                    'deliveryCourier.courierProfile',
                    'currentHub',
                    'originHub',
                    'destinationHub',
                    'dispute',
                    'review',
                    'statusHistory',
                ])),
            ],
            'message' => $msg,
        ]);
    }

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
}

