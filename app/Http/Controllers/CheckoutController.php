<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\Commission;
use App\Models\DeliveryArea;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\UserVoucher;
use App\Models\Voucher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    /**
     * B8, B9: Checkout summary, addresses, weight shipping calculation, vouchers
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $items = $user->cartItems()
            ->with(['product' => fn ($q) => $q->withTrashed()->with('seller.sellerProfile')])
            ->where('selected', true)
            ->get();

        if ($items->isEmpty()) {
            return view('buyer.checkout.index', [
                'items' => collect(),
                'grouped' => collect(),
                'addresses' => collect(),
                'defaultAddress' => null,
                'vouchers' => collect(),
                'storeWeights' => [],
                'storeShippingFees' => [],
                'totalSubtotal' => 0,
                'totalShipping' => 0,
            ]);
        }

        $addresses = $user->addresses()->orderByDesc('is_default')->latest()->get();
        $defaultAddress = $addresses->firstWhere('is_default', true) ?: $addresses->first();

        // Group items by seller
        $grouped = $items->groupBy(fn ($item) => $item->product->seller_id);

        // Calculate store weights and shipping fees: ceil(weight)
        $storeWeights = [];
        $storeShippingFees = [];
        $totalSubtotal = 0;
        $totalShipping = 0;

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

        // Available user vouchers
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

        return view('buyer.checkout.index', compact(
            'items',
            'grouped',
            'addresses',
            'defaultAddress',
            'userVouchers',
            'storeWeights',
            'storeShippingFees',
            'totalSubtotal',
            'totalShipping'
        ));
    }

    /**
     * B8, B9: Process checkout with multi-seller splitting, ceil(weight) shipping,
     * address snapshot, voucher stacking (max 1 store + 1 platform + 1 shipping),
     * double-click guardrails, and 60-min auto-cancellation for unpaid gateway orders.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Guardrail: Ensure user has selected cart items
        $items = $user->cartItems()
            ->with(['product' => fn ($q) => $q->withTrashed()->with('seller.sellerProfile')])
            ->where('selected', true)
            ->get();

        if ($items->isEmpty()) {
            return redirect()->route('cart.index')->withErrors([
                'checkout' => 'Your cart has no selected items to check out.',
            ]);
        }

        // Check stock availability & limits & seller vacation mode
        foreach ($items as $item) {
            if ($item->product->seller?->sellerProfile?->is_vacation_mode) {
                $storeName = $item->product->seller->sellerProfile->business_name ?? 'A seller';
                return redirect()->route('cart.index')->withErrors([
                    'checkout' => "Store '{$storeName}' is currently on vacation mode and cannot accept orders right now.",
                ]);
            }

            if ($item->product->trashed() || $item->product->status !== 'active') {
                return redirect()->route('cart.index')->withErrors([
                    'checkout' => "Product '{$item->product->name}' is no longer available.",
                ]);
            }

            if ($item->product->stock < $item->quantity) {
                return redirect()->route('cart.index')->withErrors([
                    'checkout' => "Insufficient stock for '{$item->product->name}'. Available: {$item->product->stock}.",
                ]);
            }
        }

        $validated = $request->validate([
            'address_id' => ['nullable', 'integer'],
            'recipient_name' => ['required_without:address_id', 'nullable', 'string', 'max:255'],
            'recipient_phone' => ['required_without:address_id', 'nullable', 'string', 'regex:/^(09|\+639)\d{9}$/'],
            'region_code' => ['nullable', 'string'],
            'region_name' => ['nullable', 'string'],
            'province_code' => ['required_without:address_id', 'nullable', 'string'],
            'province_name' => ['nullable', 'string'],
            'municipality_code' => ['required_without:address_id', 'nullable', 'string'],
            'municipality_name' => ['nullable', 'string'],
            'barangay_code' => ['required_without:address_id', 'nullable', 'string'],
            'barangay_name' => ['nullable', 'string'],
            'street' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'payment_method' => ['required', 'in:cod,ewallet,card'],
            'platform_voucher_id' => ['nullable', 'integer', 'exists:vouchers,id'],
            'shipping_voucher_id' => ['nullable', 'integer', 'exists:vouchers,id'],
            'store_voucher_id' => ['nullable', 'integer', 'exists:vouchers,id'],
            'idempotency_token' => ['nullable', 'string'],
        ]);

        // Resolve Address & Create Snapshot (Rule B2)
        $address = null;
        if (! empty($validated['address_id'])) {
            $address = $user->addresses()->findOrFail($validated['address_id']);
        } else {
            // Check max 10 addresses
            if ($user->addresses()->count() >= 10) {
                return back()->withErrors(['address' => 'You have reached the maximum limit of 10 addresses. Please select an existing address.']);
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

        // B8: Unserviceable address checking
        // Check if specific delivery areas are configured
        if (DeliveryArea::exists()) {
            $isCovered = DeliveryArea::where(function ($q) use ($address) {
                $q->where('name', 'LIKE', "%{$address->province_name}%")
                  ->orWhere('name', 'LIKE', "%{$address->municipality_name}%");
            })->exists();

            if (! $isCovered) {
                return back()->withErrors([
                    'address' => "Notice: The delivery address in '{$address->province_name}' is currently outside our courier servicing areas.",
                ]);
            }
        }

        // Immutable address snapshot
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

        // Voucher Stacking Validation (Rule B7)
        // Stacking rule: Maximum 1 Store Voucher + 1 Platform Voucher + 1 Shipping Voucher
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

        // Validate minimum spends
        if ($platformVoucher && ! $platformVoucher->isUsableFor($totalCartSubtotal)) {
            $platformVoucher = null; // Auto-remove if subtotal drops below minimum spend
        }

        if ($shippingVoucher && ! $shippingVoucher->isUsableFor($totalCartSubtotal)) {
            $shippingVoucher = null;
        }

        // Group by seller for multi-seller order splitting (Rule B8)
        $grouped = $items->groupBy(fn ($i) => $i->product->seller_id);
        $checkoutSessionId = 'CS-' . strtoupper(Str::random(12));

        $createdOrders = DB::transaction(function () use (
            $user,
            $grouped,
            $address,
            $addressSnapshot,
            $validated,
            $platformVoucher,
            $shippingVoucher,
            $storeVoucher,
            $totalCartSubtotal,
            $checkoutSessionId
        ) {
            $orders = [];
            $sellerCount = $grouped->count();

            // Distribute platform discount across split orders proportionally
            $totalPlatformDiscount = $platformVoucher ? $platformVoucher->calculateDiscount($totalCartSubtotal) : 0.00;
            $platformDiscountRemaining = $totalPlatformDiscount;

            // Distribute shipping discount
            $totalShippingDiscount = 0.00;
            if ($shippingVoucher) {
                // Calculate total shipping across all stores
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
                $billedWeight = max(1, (int) ceil($rawWeight)); // ceil(weight) per store

                $storeShippingFee = 50.00 + (20.00 * ($billedWeight - 1));

                // Store voucher applies only if matched with this seller
                $storeDiscount = 0.00;
                $appliedStoreVoucherId = null;
                if ($storeVoucher && (int) $storeVoucher->seller_id === (int) $sellerId) {
                    if ($storeVoucher->isUsableFor($storeSubtotal, $sellerId)) {
                        $storeDiscount = $storeVoucher->calculateDiscount($storeSubtotal);
                        $appliedStoreVoucherId = $storeVoucher->id;
                    }
                }

                // Proportionate platform discount
                $storePlatformDiscount = 0.00;
                if ($totalPlatformDiscount > 0) {
                    if ($isLast) {
                        $storePlatformDiscount = $platformDiscountRemaining;
                    } else {
                        $storePlatformDiscount = round($totalPlatformDiscount * ($storeSubtotal / $totalCartSubtotal), 2);
                        $platformDiscountRemaining -= $storePlatformDiscount;
                    }
                }

                // Proportionate shipping discount
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

                // B9: Payment status and 1-hour expiration for unpaid gateway orders
                $paymentMethod = $validated['payment_method'];
                $isGateway = in_array($paymentMethod, ['card', 'ewallet'], true);
                $paymentStatus = 'unpaid';
                $paymentExpiresAt = $isGateway ? now()->addMinutes(60) : null;

                $order = Order::create([
                    'order_number' => 'NX-' . strtoupper(Str::random(10)),
                    'checkout_session_id' => $checkoutSessionId,
                    'buyer_id' => $user->id,
                    'seller_id' => $sellerId,
                    'address_id' => $address->id,
                    'address_snapshot' => $addressSnapshot,
                    'status' => 'placed',
                    'payment_method' => $paymentMethod,
                    'payment_status' => $paymentStatus,
                    'payment_expires_at' => $paymentExpiresAt,
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

                // Create Order Items and decrement stock
                foreach ($storeItems as $item) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $item->product_id,
                        'product_name' => $item->product->name,
                        'price' => $item->unit_price,
                        'quantity' => $item->quantity,
                        'variant' => $item->variant,
                    ]);

                    // Deduct stock
                    $item->product->decrement('stock', $item->quantity);
                }

                // Create Commission record
                Commission::create([
                    'order_id' => $order->id,
                    'seller_id' => $sellerId,
                    'order_total' => $storeSubtotal,
                    'rate' => 0.10,
                    'commission_amount' => round($storeSubtotal * 0.10, 2),
                    'seller_payout' => round($storeSubtotal * 0.90, 2),
                    'status' => 'pending',
                ]);

                // Record initial Order Status History
                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'status' => 'placed',
                    'changed_by' => $user->id,
                    'changed_at' => now(),
                ]);

                $orders[] = $order;
            }

            // Mark vouchers as used
            if ($platformVoucher) {
                UserVoucher::where('user_id', $user->id)->where('voucher_id', $platformVoucher->id)->update(['used_at' => now()]);
            }
            if ($shippingVoucher) {
                UserVoucher::where('user_id', $user->id)->where('voucher_id', $shippingVoucher->id)->update(['used_at' => now()]);
            }
            if ($storeVoucher) {
                UserVoucher::where('user_id', $user->id)->where('voucher_id', $storeVoucher->id)->update(['used_at' => now()]);
            }

            // Clear checked-out items from cart
            $user->cartItems()->where('selected', true)->delete();

            return $orders;
        });

        $orderCount = count($createdOrders);
        $msg = $orderCount > 1
            ? "Your checkout was split into {$orderCount} store orders and successfully placed!"
            : "Order {$createdOrders[0]->order_number} placed successfully!";

        return redirect()->route('buyer.orders')->with('status', $msg);
    }
}
