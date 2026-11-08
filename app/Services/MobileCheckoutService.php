<?php

namespace App\Services;

use App\Models\Address;
use App\Models\Commission;
use App\Models\DeliveryArea;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\User;
use App\Models\UserVoucher;
use App\Models\Voucher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MobileCheckoutService
{
    /**
     * Resolve available stock for a product + optional variant payload.
     */
    protected function resolveAvailableStock(Product $product, mixed $variant): int
    {
        $availableStock = (int) $product->stock;

        if ($availableStock === 0 && ! empty($product->variants_data) && is_array($product->variants_data)) {
            $availableStock = (int) array_sum(array_map(fn ($v) => (int) ($v['stock'] ?? 0), $product->variants_data));
        }

        $variation = is_array($variant) ? ($variant['variation'] ?? $variant['name'] ?? null) : null;
        if ($variation && ! empty($product->variants_data) && is_array($product->variants_data)) {
            foreach ($product->variants_data as $key => $v) {
                if (! is_array($v)) {
                    continue;
                }
                $vName = $v['name'] ?? (is_string($key) ? str_replace('_', ' / ', $key) : null);
                $vSku = $v['sku'] ?? null;
                if ($vName === $variation || $vSku === $variation || (string) $key === $variation) {
                    if (isset($v['stock'])) {
                        $availableStock = (int) $v['stock'];
                    }
                    break;
                }
            }
        }

        return $availableStock;
    }

    /**
     * Replicates CheckoutController@index rules:
     * - Group selected (or explicit cart_item_ids) cart items by seller
     * - Ceil(weight) shipping fee calculation (₱50 for first kg, ₱20 for each additional kg)
     * - Collect available active, non-expired, unused vouchers for user
     */
    public function getSummary(User $user, ?array $cartItemIds = null): array
    {
        $query = $user->cartItems()
            ->with(['product' => fn ($q) => $q->withTrashed()->with('seller.sellerProfile')]);

        if ($cartItemIds !== null && ! empty($cartItemIds)) {
            $requestedIds = array_values(array_unique(array_map('intval', $cartItemIds)));
            $items = $query->whereIn('id', $requestedIds)->get();
            if ($items->count() !== count($requestedIds)) {
                throw ValidationException::withMessages([
                    'cart_item_ids' => ['One or more selected cart items are invalid or no longer belong to your cart.'],
                ]);
            }
        } else {
            $items = $query->where('selected', true)->get();
        }

        $addresses = $user->addresses()->orderByDesc('is_default')->latest()->get();
        $defaultAddress = $addresses->firstWhere('is_default', true) ?: $addresses->first();

        if ($items->isEmpty()) {
            return [
                'items' => [],
                'grouped' => [],
                'addresses' => $addresses,
                'default_address' => $defaultAddress,
                'vouchers' => [],
                'store_weights' => [],
                'store_shipping_fees' => [],
                'total_subtotal' => 0.0,
                'total_shipping' => 0.0,
            ];
        }

        // Group items by seller
        $grouped = $items->groupBy(fn ($item) => $item->product->seller_id);

        $storeWeights = [];
        $storeShippingFees = [];
        $totalSubtotal = 0.0;
        $totalShipping = 0.0;

        foreach ($grouped as $sellerId => $storeItems) {
            $rawWeight = $storeItems->sum(fn ($i) => (float) ($i->product->weight ?? 1.0) * $i->quantity);
            $billedWeight = max(1, (int) ceil($rawWeight));
            $storeWeights[$sellerId] = [
                'raw' => $rawWeight,
                'billed' => $billedWeight,
            ];

            // ₱50 for first kg, ₱20 for each additional kg
            $shippingFee = 50.00 + (20.00 * ($billedWeight - 1));
            $storeShippingFees[$sellerId] = $shippingFee;

            $storeSubtotal = $storeItems->sum(fn ($i) => $i->unit_price * $i->quantity);
            $totalSubtotal += $storeSubtotal;
            $totalShipping += $shippingFee;
        }

        $sellerIds = $grouped->keys()->all();
        $userVouchers = $user->vouchers()
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->wherePivotNull('used_at')
            ->where(function ($q) use ($sellerIds) {
                $q->whereIn('voucher_scope', ['platform', 'shipping'])
                  ->orWhere(fn ($sq) => $sq->where('voucher_scope', 'store')->whereIn('seller_id', $sellerIds));
            })
            ->get();

        return [
            'items' => $items,
            'grouped' => $grouped,
            'addresses' => $addresses,
            'default_address' => $defaultAddress,
            'vouchers' => $userVouchers,
            'store_weights' => $storeWeights,
            'store_shipping_fees' => $storeShippingFees,
            'total_subtotal' => round($totalSubtotal, 2),
            'total_shipping' => round($totalShipping, 2),
        ];
    }

    /**
     * Validate a claimed or public voucher explicitly so invalid/expired/below-min-spend
     * submissions fail loudly with a clear 422 message instead of silently disappearing.
     */
    protected function resolveValidatedVoucher(
        User $user,
        int $voucherId,
        string $expectedScope,
        float $totalCartSubtotal,
        array $sellerSubtotals = []
    ): Voucher {
        $voucher = Voucher::find($voucherId);

        if (! $voucher) {
            throw ValidationException::withMessages([
                'voucher' => ['The selected voucher could not be found.'],
            ]);
        }

        if ($voucher->voucher_scope !== $expectedScope) {
            throw ValidationException::withMessages([
                'voucher' => ["Voucher '{$voucher->code}' is not valid as a {$expectedScope} voucher."],
            ]);
        }

        if (! $voucher->is_active || $voucher->isExpired()) {
            throw ValidationException::withMessages([
                'voucher' => ["Voucher '{$voucher->code}' is inactive or has expired."],
            ]);
        }

        $userVoucher = UserVoucher::where('user_id', $user->id)
            ->where('voucher_id', $voucher->id)
            ->first();

        if ($userVoucher && $userVoucher->used_at !== null) {
            throw ValidationException::withMessages([
                'voucher' => ["Voucher '{$voucher->code}' has already been used."],
            ]);
        }

        if ($expectedScope === 'store') {
            $sellerId = $voucher->seller_id !== null ? (int) $voucher->seller_id : null;
            if ($sellerId !== null && ! array_key_exists($sellerId, $sellerSubtotals)) {
                throw ValidationException::withMessages([
                    'voucher' => ["Store voucher '{$voucher->code}' does not apply to any store in this checkout."],
                ]);
            }

            $storeSubtotal = $sellerId !== null ? (float) $sellerSubtotals[$sellerId] : $totalCartSubtotal;
            if (! $voucher->isUsableFor($storeSubtotal, $sellerId)) {
                $minSpend = number_format((float) $voucher->min_spend, 2);
                throw ValidationException::withMessages([
                    'voucher' => ["Minimum store spend of ₱{$minSpend} is required for voucher '{$voucher->code}'."],
                ]);
            }
        } else {
            if (! $voucher->isUsableFor($totalCartSubtotal)) {
                $minSpend = number_format((float) $voucher->min_spend, 2);
                throw ValidationException::withMessages([
                    'voucher' => ["Minimum spend of ₱{$minSpend} is required for voucher '{$voucher->code}'."],
                ]);
            }
        }

        return $voucher;
    }

    /**
     * Replicates exact CheckoutController@store rules:
     * 1. Check selected cart items (or explicit cart_item_ids) exist
     * 2. Check store vacation mode
     * 3. Check product active & not trashed
     * 4. Check product/variation stock >= quantity
     * 5. Resolve address: existing address_id or create new address (max 10 limit)
     * 6. Check delivery area servicing (DeliveryArea::exists())
     * 7. Immutable address snapshot (including PSGC codes for routing)
     * 8. Voucher stacking validation: max 1 platform + 1 shipping + 1 store voucher
     * 9. Minimum spend & expiry validation for vouchers (422 on invalid voucher)
     * 10. Multi-seller order splitting (groupBy seller_id)
     * 11. Shared checkout_session_id = 'CS-' . strtoupper(Str::random(12))
     * 12. Billed weight per store: ceil(weight), shipping fee: 50 + 20*(billed-1)
     * 13. Proportionate platform discount across stores (last gets remainder)
     * 14. Proportionate shipping discount across stores (last gets remainder)
     * 15. Store voucher discount applied to matching seller
     * 16. COD payment method (unpaid, expires null)
     * 17. Create Order per seller ('NX-' . 10 chars)
     * 18. Create OrderItem and decrement product + variation stock safely
     * 19. Create Commission record (10% platform rate)
     * 20. Create OrderStatusHistory, mark vouchers used, delete checked-out cart items
     */
    public function processCheckout(User $user, array $validated, ?string $idempotencyKey = null): array
    {
        // Rule 1: Guardrail - Resolve explicit cart_item_ids or selected cart items
        $query = $user->cartItems()
            ->with(['product' => fn ($q) => $q->withTrashed()->with('seller.sellerProfile')]);

        if (! empty($validated['cart_item_ids'])) {
            $requestedIds = array_values(array_unique(array_map('intval', $validated['cart_item_ids'])));
            $items = $query->whereIn('id', $requestedIds)->get();
            if ($items->count() !== count($requestedIds)) {
                throw ValidationException::withMessages([
                    'cart_item_ids' => ['One or more selected cart items are invalid or do not belong to your cart.'],
                ]);
            }
        } else {
            $items = $query->where('selected', true)->get();
        }

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'checkout' => ['Your cart has no selected items to check out.'],
            ]);
        }

        // Rules 2, 3, 4: Vacation mode, active status, and product/variation stock availability
        foreach ($items as $item) {
            if (! $item->product || $item->product->trashed() || $item->product->status !== 'active') {
                $name = $item->product?->name ?? 'Item';
                throw ValidationException::withMessages([
                    'checkout' => ["Product '{$name}' is no longer available."],
                ]);
            }

            if ($item->product->seller?->sellerProfile?->is_vacation_mode) {
                $storeName = $item->product->seller->sellerProfile->business_name ?? 'A seller';
                throw ValidationException::withMessages([
                    'checkout' => ["Store '{$storeName}' is currently on vacation mode and cannot accept orders right now."],
                ]);
            }

            $availableStock = $this->resolveAvailableStock($item->product, $item->variant);
            if ($availableStock < $item->quantity) {
                throw ValidationException::withMessages([
                    'checkout' => ["Insufficient stock for '{$item->product->name}'. Available: {$availableStock}."],
                ]);
            }
        }

        // Rule 5: Resolve Address & Create Snapshot
        $address = null;
        if (! empty($validated['address_id'])) {
            $address = $user->addresses()->findOrFail($validated['address_id']);
        } else {
            if ($user->addresses()->count() >= 10) {
                throw ValidationException::withMessages([
                    'address' => ['You have reached the maximum limit of 10 addresses. Please select an existing address.'],
                ]);
            }

            $address = $user->addresses()->create([
                'recipient_name' => $validated['recipient_name'],
                'recipient_phone' => $validated['recipient_phone'],
                'region_code' => $validated['region_code'] ?? null,
                'region_name' => $validated['region_name'] ?? null,
                'province_code' => $validated['province_code'],
                'province_name' => $validated['province_name'] ?: $validated['province_code'],
                'municipality_code' => $validated['municipality_code'],
                'municipality_name' => $validated['municipality_name'] ?: $validated['municipality_code'],
                'barangay_code' => $validated['barangay_code'],
                'barangay_name' => $validated['barangay_name'] ?: $validated['barangay_code'],
                'house_number' => $validated['house_number'] ?? null,
                'street' => $validated['street'] ?? null,
                'postal_code' => $validated['postal_code'] ?? null,
                'is_default' => $user->addresses()->count() === 0,
            ]);
        }

        // Rule 6: Delivery area coverage checking
        if (DeliveryArea::exists()) {
            $isCovered = DeliveryArea::where(function ($q) use ($address) {
                $q->where('name', 'LIKE', "%{$address->province_name}%")
                  ->orWhere('name', 'LIKE', "%{$address->municipality_name}%");
            })->exists();

            if (! $isCovered) {
                throw ValidationException::withMessages([
                    'address' => ["Notice: The delivery address in '{$address->province_name}' is currently outside our courier servicing areas."],
                ]);
            }
        }

        // Rule 7: Immutable address snapshot (preserving PSGC codes for routing)
        $addressSnapshot = [
            'id' => $address->id,
            'recipient_name' => $address->recipient_name ?? $user->full_name,
            'recipient_phone' => $address->recipient_phone ?? $user->contact_number,
            'full_address' => $address->full_address,
            'region_code' => $address->region_code,
            'region_name' => $address->region_name,
            'province_code' => $address->province_code,
            'province_name' => $address->province_name,
            'municipality_code' => $address->municipality_code,
            'municipality_name' => $address->municipality_name,
            'barangay_code' => $address->barangay_code,
            'barangay_name' => $address->barangay_name,
            'house_number' => $address->house_number,
            'street' => $address->street,
            'postal_code' => $address->postal_code,
        ];

        // Rules 8, 9, 10: Group by seller and validate vouchers explicitly
        $grouped = $items->groupBy(fn ($i) => $i->product->seller_id);
        $sellerSubtotals = [];
        foreach ($grouped as $sellerId => $storeItems) {
            $sellerSubtotals[(int) $sellerId] = (float) $storeItems->sum(fn ($i) => $i->unit_price * $i->quantity);
        }
        $totalCartSubtotal = (float) $items->sum(fn ($i) => $i->unit_price * $i->quantity);

        $platformVoucher = ! empty($validated['platform_voucher_id'])
            ? $this->resolveValidatedVoucher($user, (int) $validated['platform_voucher_id'], 'platform', $totalCartSubtotal, $sellerSubtotals)
            : null;

        $shippingVoucher = ! empty($validated['shipping_voucher_id'])
            ? $this->resolveValidatedVoucher($user, (int) $validated['shipping_voucher_id'], 'shipping', $totalCartSubtotal, $sellerSubtotals)
            : null;

        $storeVoucher = ! empty($validated['store_voucher_id'])
            ? $this->resolveValidatedVoucher($user, (int) $validated['store_voucher_id'], 'store', $totalCartSubtotal, $sellerSubtotals)
            : null;

        // Rule 11: Checkout session ID
        $checkoutSessionId = 'CS-' . strtoupper(Str::random(12));
        $checkedOutItemIds = $items->pluck('id')->all();

        return DB::transaction(function () use (
            $user,
            $grouped,
            $address,
            $addressSnapshot,
            $platformVoucher,
            $shippingVoucher,
            $storeVoucher,
            $totalCartSubtotal,
            $checkoutSessionId,
            $checkedOutItemIds
        ) {
            $orders = [];
            $sellerCount = $grouped->count();

            // Proportionate platform discount
            $totalPlatformDiscount = $platformVoucher ? $platformVoucher->calculateDiscount($totalCartSubtotal) : 0.00;
            $platformDiscountRemaining = $totalPlatformDiscount;

            // Proportionate shipping discount
            $totalShippingDiscount = 0.00;
            if ($shippingVoucher) {
                $totalBaseShipping = 0;
                foreach ($grouped as $storeItems) {
                    $rawWeight = $storeItems->sum(fn ($i) => (float) ($i->product->weight ?? 1.0) * $i->quantity);
                    $billedWeight = max(1, (int) ceil($rawWeight));
                    $totalBaseShipping += (50.00 + (20.00 * ($billedWeight - 1)));
                }
                $totalShippingDiscount = $shippingVoucher->calculateDiscount($totalBaseShipping);
            }
            $shippingDiscountRemaining = $totalShippingDiscount;

            $index = 0;
            foreach ($grouped as $sellerId => $storeItems) {
                $index++;
                $isLast = ($index === $sellerCount);

                $storeSubtotal = $storeItems->sum(fn ($i) => $i->unit_price * $i->quantity);
                $rawWeight = $storeItems->sum(fn ($i) => (float) ($i->product->weight ?? 1.0) * $i->quantity);
                $billedWeight = max(1, (int) ceil($rawWeight));

                // Rule 12: Shipping calculation
                $storeShippingFee = 50.00 + (20.00 * ($billedWeight - 1));

                // Rule 15: Store voucher discount
                $storeDiscount = 0.00;
                $appliedStoreVoucherId = null;
                if ($storeVoucher && (int) $storeVoucher->seller_id === (int) $sellerId) {
                    if ($storeVoucher->isUsableFor($storeSubtotal, $sellerId)) {
                        $storeDiscount = $storeVoucher->calculateDiscount($storeSubtotal);
                        $appliedStoreVoucherId = $storeVoucher->id;
                    }
                }

                // Rule 13: Proportionate platform discount
                $storePlatformDiscount = 0.00;
                if ($totalPlatformDiscount > 0) {
                    if ($isLast) {
                        $storePlatformDiscount = $platformDiscountRemaining;
                    } else {
                        $storePlatformDiscount = round($totalPlatformDiscount * ($storeSubtotal / $totalCartSubtotal), 2);
                        $platformDiscountRemaining -= $storePlatformDiscount;
                    }
                }

                // Rule 14: Proportionate shipping discount
                $storeShippingDiscount = 0.00;
                if ($totalShippingDiscount > 0) {
                    if ($isLast) {
                        $storeShippingDiscount = min($storeShippingFee, $shippingDiscountRemaining);
                    } else {
                        $storeShippingDiscount = round($totalShippingDiscount / $sellerCount, 2);
                        $storeShippingDiscount = min($storeShippingFee, $storeShippingDiscount);
                        $shippingDiscountRemaining -= $storeShippingDiscount;
                    }
                }

                $effectiveShippingFee = max(0.00, round($storeShippingFee - $storeShippingDiscount, 2));
                $storeTotalDiscount = round($storeDiscount + $storePlatformDiscount + $storeShippingDiscount, 2);
                $storeTotal = max(0.00, round($storeSubtotal + $effectiveShippingFee - $storeDiscount - $storePlatformDiscount, 2));

                // Rules 16, 17: COD Payment Method and Order creation
                $order = Order::create([
                    'order_number' => 'NX-' . strtoupper(Str::random(10)),
                    'checkout_session_id' => $checkoutSessionId,
                    'buyer_id' => $user->id,
                    'seller_id' => $sellerId,
                    'address_id' => $address->id,
                    'address_snapshot' => $addressSnapshot,
                    'status' => 'placed',
                    'payment_method' => 'cod',
                    'payment_status' => 'unpaid',
                    'payment_expires_at' => null,
                    'subtotal' => $storeSubtotal,
                    'discount' => $storeTotalDiscount,
                    'shipping_fee' => $effectiveShippingFee,
                    'shipping_weight' => $billedWeight,
                    'shipping_voucher_id' => $shippingVoucher?->id,
                    'store_voucher_id' => $appliedStoreVoucherId,
                    'platform_voucher_id' => $platformVoucher?->id,
                    'total' => $storeTotal,
                    'placed_at' => now(),
                ]);

                // Rule 18: Order Items creation and locked stock decrement
                foreach ($storeItems as $item) {
                    $lockedProduct = Product::lockForUpdate()->find($item->product_id);
                    if (! $lockedProduct) {
                        throw ValidationException::withMessages([
                            'checkout' => ["Product '{$item->product->name}' is no longer available."],
                        ]);
                    }

                    $lockedAvailable = $this->resolveAvailableStock($lockedProduct, $item->variant);
                    if ($lockedAvailable < $item->quantity) {
                        throw ValidationException::withMessages([
                            'checkout' => ["Insufficient stock for '{$lockedProduct->name}'. Available: {$lockedAvailable}."],
                        ]);
                    }

                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $item->product_id,
                        'product_name' => $lockedProduct->name,
                        'price' => $item->unit_price,
                        'quantity' => $item->quantity,
                        'variant' => $item->variant,
                    ]);

                    $decrementTotal = min((int) $lockedProduct->stock, (int) $item->quantity);
                    if ($decrementTotal > 0) {
                        $lockedProduct->decrement('stock', $decrementTotal);
                    }

                    $variation = is_array($item->variant) ? ($item->variant['variation'] ?? $item->variant['name'] ?? null) : null;
                    if ($variation && is_array($lockedProduct->variants_data)) {
                        $variantsData = $lockedProduct->variants_data;
                        $updatedVariant = false;
                        foreach ($variantsData as $k => $v) {
                            if (! is_array($v)) {
                                continue;
                            }
                            $vName = $v['name'] ?? (is_string($k) ? str_replace('_', ' / ', $k) : null);
                            $vSku = $v['sku'] ?? null;
                            if ($vName === $variation || $vSku === $variation || (string) $k === $variation) {
                                if (isset($v['stock'])) {
                                    $variantsData[$k]['stock'] = max(0, (int) $v['stock'] - (int) $item->quantity);
                                    $updatedVariant = true;
                                }
                                break;
                            }
                        }
                        if ($updatedVariant) {
                            $lockedProduct->update(['variants_data' => $variantsData]);
                        }
                    }
                }

                // Rule 19: 10% Platform Commission
                Commission::create([
                    'order_id' => $order->id,
                    'seller_id' => $sellerId,
                    'order_total' => $storeSubtotal,
                    'rate' => 0.10,
                    'commission_amount' => round($storeSubtotal * 0.10, 2),
                    'seller_payout' => round($storeSubtotal * 0.90, 2),
                    'status' => 'pending',
                ]);

                // Rule 20: Initial Order Status History
                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'status' => 'placed',
                    'changed_by' => $user->id,
                    'changed_at' => now(),
                ]);

                // Auto-route order to assign origin & destination hubs and logistics
                try {
                    $order = app(InterRegionLogisticsService::class)->matchLogisticsAndRouteOrder($order);
                } catch (\Throwable $e) {
                    Log::warning("Could not auto-route mobile order {$order->id}: " . $e->getMessage());
                }

                $order->loadMissing(['items.product', 'seller.sellerProfile', 'dispute', 'review']);
                $orders[] = $order;
            }

            // Rule 20: Mark applied vouchers as used
            if ($platformVoucher) {
                UserVoucher::where('user_id', $user->id)->where('voucher_id', $platformVoucher->id)->update(['used_at' => now()]);
            }
            if ($shippingVoucher) {
                UserVoucher::where('user_id', $user->id)->where('voucher_id', $shippingVoucher->id)->update(['used_at' => now()]);
            }
            if ($storeVoucher) {
                UserVoucher::where('user_id', $user->id)->where('voucher_id', $storeVoucher->id)->update(['used_at' => now()]);
            }

            // Rule 20: Delete only checked-out items from cart (safe for Buy Now!)
            $user->cartItems()->whereIn('id', $checkedOutItemIds)->delete();

            return $orders;
        });
    }
}

