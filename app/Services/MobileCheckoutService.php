<?php

namespace App\Services;

use App\Models\Address;
use App\Models\Commission;
use App\Models\DeliveryArea;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\User;
use App\Models\UserVoucher;
use App\Models\Voucher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MobileCheckoutService
{
    /**
     * Replicates CheckoutController@index rules:
     * - Group selected cart items by seller
     * - Ceil(weight) shipping fee calculation (₱50 for first kg, ₱20 for each additional kg)
     * - Collect available active, non-expired, unused vouchers for user
     */
    public function getSummary(User $user): array
    {
        $items = $user->cartItems()
            ->with(['product' => fn ($q) => $q->withTrashed()->with('seller.sellerProfile')])
            ->where('selected', true)
            ->get();

        if ($items->isEmpty()) {
            return [
                'items' => [],
                'grouped' => [],
                'addresses' => [],
                'default_address' => null,
                'vouchers' => [],
                'store_weights' => [],
                'store_shipping_fees' => [],
                'total_subtotal' => 0.0,
                'total_shipping' => 0.0,
            ];
        }

        $addresses = $user->addresses()->orderByDesc('is_default')->latest()->get();
        $defaultAddress = $addresses->firstWhere('is_default', true) ?: $addresses->first();

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
     * Replicates exact CheckoutController@store rules:
     * 1. Check selected cart items exist
     * 2. Check store vacation mode
     * 3. Check product active & not trashed
     * 4. Check product stock >= quantity
     * 5. Resolve address: existing address_id or create new address (max 10 limit)
     * 6. Check delivery area servicing (DeliveryArea::exists())
     * 7. Immutable address snapshot
     * 8. Voucher stacking validation: max 1 platform + 1 shipping + 1 store voucher
     * 9. Minimum spend validation for vouchers
     * 10. Multi-seller order splitting (groupBy seller_id)
     * 11. Shared checkout_session_id = 'CS-' . strtoupper(Str::random(12))
     * 12. Billed weight per store: ceil(weight), shipping fee: 50 + 20*(billed-1)
     * 13. Proportionate platform discount across stores (last gets remainder)
     * 14. Proportionate shipping discount across stores (last gets remainder)
     * 15. Store voucher discount applied to matching seller
     * 16. COD payment method (unpaid, expires null)
     * 17. Create Order per seller ('NX-' . 10 chars)
     * 18. Create OrderItem and decrement product stock
     * 19. Create Commission record (10% platform rate)
     * 20. Create OrderStatusHistory, mark vouchers used, delete selected cart items
     */
    public function processCheckout(User $user, array $validated, ?string $idempotencyKey = null): array
    {
        $idempotencyKey = $idempotencyKey ?? ($validated['idempotency_token'] ?? null);

        $execution = function () use ($user, $validated) {
            // Rule 1: Guardrail - Ensure user has selected cart items
            $items = $user->cartItems()
                ->with(['product' => fn ($q) => $q->withTrashed()->with('seller.sellerProfile')])
                ->where('selected', true)
                ->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'checkout' => ['Your cart has no selected items to check out.'],
                ]);
            }

            // Rules 2, 3, 4: Vacation mode, active status, and stock availability
            foreach ($items as $item) {
                if ($item->product->seller?->sellerProfile?->is_vacation_mode) {
                    $storeName = $item->product->seller->sellerProfile->business_name ?? 'A seller';
                    throw ValidationException::withMessages([
                        'checkout' => ["Store '{$storeName}' is currently on vacation mode and cannot accept orders right now."],
                    ]);
                }

                if ($item->product->trashed() || $item->product->status !== 'active') {
                    throw ValidationException::withMessages([
                        'checkout' => ["Product '{$item->product->name}' is no longer available."],
                    ]);
                }

                if ($item->product->stock < $item->quantity) {
                    throw ValidationException::withMessages([
                        'checkout' => ["Insufficient stock for '{$item->product->name}'. Available: {$item->product->stock}."],
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

            // Rule 7: Immutable address snapshot
            $addressSnapshot = [
                'id' => $address->id,
                'recipient_name' => $address->recipient_name ?? $user->full_name,
                'recipient_phone' => $address->recipient_phone ?? $user->contact_number,
                'full_address' => $address->full_address,
                'region_name' => $address->region_name,
                'province_name' => $address->province_name,
                'municipality_name' => $address->municipality_name,
                'barangay_name' => $address->barangay_name,
                'street' => $address->street,
                'postal_code' => $address->postal_code,
            ];

            // Rules 8, 9: Voucher Stacking & Minimum Spends
            $platformVoucher = null;
            if (! empty($validated['platform_voucher_id'])) {
                $platformVoucher = Voucher::where('is_active', true)
                    ->where('voucher_scope', 'platform')
                    ->find($validated['platform_voucher_id']);
            }

            $shippingVoucher = null;
            if (! empty($validated['shipping_voucher_id'])) {
                $shippingVoucher = Voucher::where('is_active', true)
                    ->where('voucher_scope', 'shipping')
                    ->find($validated['shipping_voucher_id']);
            }

            $storeVoucher = null;
            if (! empty($validated['store_voucher_id'])) {
                $storeVoucher = Voucher::where('is_active', true)
                    ->where('voucher_scope', 'store')
                    ->find($validated['store_voucher_id']);
            }

            $totalCartSubtotal = $items->sum(fn ($i) => $i->unit_price * $i->quantity);

            if ($platformVoucher && ! $platformVoucher->isUsableFor($totalCartSubtotal)) {
                $platformVoucher = null;
            }

            if ($shippingVoucher && ! $shippingVoucher->isUsableFor($totalCartSubtotal)) {
                $shippingVoucher = null;
            }

            // Rules 10, 11: Multi-seller splitting & checkout session ID
            $grouped = $items->groupBy(fn ($i) => $i->product->seller_id);
            $checkoutSessionId = 'CS-' . strtoupper(Str::random(12));

            return DB::transaction(function () use (
                $user,
                $grouped,
                $address,
                $addressSnapshot,
                $platformVoucher,
                $shippingVoucher,
                $storeVoucher,
                $totalCartSubtotal,
                $checkoutSessionId
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

                    // Rule 18: Order Items creation and stock decrement
                    foreach ($storeItems as $item) {
                        OrderItem::create([
                            'order_id' => $order->id,
                            'product_id' => $item->product_id,
                            'product_name' => $item->product->name,
                            'price' => $item->unit_price,
                            'quantity' => $item->quantity,
                            'variant' => $item->variant,
                        ]);

                        $item->product->decrement('stock', $item->quantity);
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

                // Rule 20: Delete checked-out items from cart
                $user->cartItems()->where('selected', true)->delete();

                return $orders;
            });
        };

        if ($idempotencyKey) {
            return IdempotencyService::run($idempotencyKey, $user->id, 'mobile_checkout_' . $user->id, $execution);
        }

        return $execution();
    }
}

