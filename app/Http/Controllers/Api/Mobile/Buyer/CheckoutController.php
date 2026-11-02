<?php

namespace App\Http\Controllers\Api\Mobile\Buyer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Mobile\CheckoutRequest;
use App\Http\Resources\Api\Mobile\AddressResource;
use App\Http\Resources\Api\Mobile\CartItemResource;
use App\Http\Resources\Api\Mobile\OrderResource;
use App\Http\Resources\Api\Mobile\VoucherResource;
use App\Services\MobileCheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function __construct(
        protected MobileCheckoutService $checkoutService
    ) {}

    /**
     * B8, B9: Checkout summary calculation
     * GET /api/mobile/v1/buyer/checkout/summary
     */
    public function summary(Request $request): JsonResponse
    {
        $summary = $this->checkoutService->getSummary($request->user());

        $formattedStores = [];
        foreach ($summary['grouped'] as $sellerId => $storeItems) {
            $seller = $storeItems->first()?->product?->seller;
            $storeName = $seller?->sellerProfile?->business_name ?? $seller?->full_name ?? 'Seller';

            $formattedStores[] = [
                'seller_id' => $sellerId,
                'store_name' => $storeName,
                'raw_weight' => $summary['store_weights'][$sellerId]['raw'] ?? 0,
                'billed_weight' => $summary['store_weights'][$sellerId]['billed'] ?? 1,
                'shipping_fee' => $summary['store_shipping_fees'][$sellerId] ?? 50.0,
                'subtotal' => round($storeItems->sum(fn ($i) => $i->unit_price * $i->quantity), 2),
                'items' => CartItemResource::collection($storeItems),
            ];
        }

        return response()->json([
            'data' => [
                'stores' => $formattedStores,
                'addresses' => AddressResource::collection($summary['addresses']),
                'default_address' => $summary['default_address'] ? new AddressResource($summary['default_address']) : null,
                'available_vouchers' => VoucherResource::collection($summary['vouchers']),
                'total_subtotal' => $summary['total_subtotal'],
                'total_shipping' => $summary['total_shipping'],
                'estimated_total' => round($summary['total_subtotal'] + $summary['total_shipping'], 2),
            ],
            'message' => 'Checkout summary calculated successfully.',
        ]);
    }

    /**
     * B8, B9: Place order with multi-seller splitting, vouchers, and idempotency
     * POST /api/mobile/v1/buyer/checkout
     */
    public function store(CheckoutRequest $request): JsonResponse
    {
        $idempotencyKey = $request->header('Idempotency-Key') ?? $request->input('idempotency_token');

        $orders = $this->checkoutService->processCheckout(
            $request->user(),
            $request->validated(),
            $idempotencyKey
        );

        $orderCount = count($orders);
        $message = $orderCount > 1
            ? "Your checkout was split into {$orderCount} store orders and successfully placed!"
            : "Order {$orders[0]->order_number} placed successfully!";

        return response()->json([
            'data' => [
                'orders' => OrderResource::collection($orders),
                'checkout_session_id' => $orders[0]->checkout_session_id ?? null,
                'orders_count' => $orderCount,
            ],
            'message' => $message,
        ], 201);
    }
}

