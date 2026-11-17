<?php

namespace App\Http\Controllers\Api\Mobile\Buyer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Mobile\CheckoutRequest;
use App\Http\Resources\Api\Mobile\AddressResource;
use App\Http\Resources\Api\Mobile\CartItemResource;
use App\Http\Resources\Api\Mobile\OrderResource;
use App\Http\Resources\Api\Mobile\VoucherResource;
use App\Services\IdempotencyService;
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
        $rawIds = $request->query('cart_item_ids');
        $cartItemIds = null;
        if (is_array($rawIds)) {
            $cartItemIds = array_values(array_filter(array_map('intval', $rawIds), fn (int $id) => $id > 0));
        } elseif (is_string($rawIds) && trim($rawIds) !== '') {
            $cartItemIds = array_values(array_filter(array_map('intval', explode(',', $rawIds)), fn (int $id) => $id > 0));
        }

        $addressId = $request->filled('address_id') ? (int) $request->query('address_id') : null;
        $locationOverride = null;
        if (! $addressId && ($request->filled('province_code') || $request->filled('province_name') || $request->filled('municipality_code') || $request->filled('municipality_name'))) {
            $locationOverride = $request->only([
                'province_code', 'province_name', 'municipality_code', 'municipality_name', 'barangay_code', 'barangay_name',
            ]);
        }

        $directProductId = $request->filled('direct_product_id') ? (int) $request->query('direct_product_id') : null;
        $directQuantity = $request->filled('direct_quantity') ? max(1, (int) $request->query('direct_quantity')) : 1;

        $summary = $this->checkoutService->getSummary($request->user(), $cartItemIds, $addressId, $locationOverride, $directProductId, $directQuantity);
        $voucherPreview = $this->checkoutService->previewVouchers($request->user(), $summary, [
            'platform_voucher_id' => $request->query('platform_voucher_id'),
            'shipping_voucher_id' => $request->query('shipping_voucher_id'),
            'store_voucher_id' => $request->query('store_voucher_id'),
        ]);

        $formattedStores = [];
        foreach ($summary['grouped'] as $sellerId => $storeItems) {
            $seller = $storeItems->first()?->product?->seller;
            $storeName = $seller?->sellerProfile?->business_name ?? $seller?->full_name ?? 'Seller';
            $quote = $summary['store_quotes'][$sellerId] ?? null;

            $formattedStores[] = [
                'seller_id' => $sellerId,
                'store_name' => $storeName,
                'raw_weight' => $summary['store_weights'][$sellerId]['raw'] ?? 0,
                'volumetric_weight' => $summary['store_weights'][$sellerId]['volumetric'] ?? 0,
                'billed_weight' => $summary['store_weights'][$sellerId]['billed'] ?? 1,
                'is_oversized' => $summary['store_weights'][$sellerId]['is_oversized'] ?? false,
                'shipping_fee' => $summary['store_shipping_fees'][$sellerId] ?? 50.0,
                'route_type' => $quote['route_type'] ?? 'same_city',
                'serviceable' => $quote['serviceable'] ?? true,
                'serviceability_error' => $quote['serviceability_error'] ?? null,
                'shipping_breakdown' => $quote['breakdown'] ?? null,
                'subtotal' => round($storeItems->sum(fn ($i) => $i->unit_price * $i->quantity), 2),
                'items' => CartItemResource::collection($storeItems),
            ];
        }

        $estimatedTotal = max(
            0.0,
            round($summary['total_subtotal'] + $summary['total_shipping'] - $voucherPreview['total_discount'], 2)
        );

        return response()->json([
            'data' => [
                'stores' => $formattedStores,
                'addresses' => AddressResource::collection($summary['addresses']),
                'default_address' => $summary['default_address'] ? new AddressResource($summary['default_address']) : null,
                'available_vouchers' => VoucherResource::collection($summary['vouchers']),
                'total_subtotal' => $summary['total_subtotal'],
                'total_shipping' => $summary['total_shipping'],
                'all_serviceable' => $summary['all_serviceable'] ?? true,
                'serviceability_error' => $summary['serviceability_error'] ?? null,
                'platform_discount' => $voucherPreview['platform_discount'],
                'shipping_discount' => $voucherPreview['shipping_discount'],
                'store_discount' => $voucherPreview['store_discount'],
                'total_discount' => $voucherPreview['total_discount'],
                'voucher_errors' => $voucherPreview['voucher_errors'],
                'estimated_total' => $estimatedTotal,
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
        $user = $request->user();
        $validated = $request->validated();
        $idempotencyKey = $request->header('Idempotency-Key') ?? ($validated['idempotency_token'] ?? null);

        return IdempotencyService::run($idempotencyKey, $user->id, 'mobile_checkout_' . $user->id, function () use ($user, $validated, $idempotencyKey) {
            $orders = $this->checkoutService->processCheckout(
                $user,
                $validated,
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
        });
    }
}

