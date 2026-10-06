<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\Commission;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function index(Request $request): View
    {
        $items = $request->user()->cartItems()->with('product.seller.sellerProfile')
            ->where('selected', true)->get();

        return view('buyer.checkout.index', compact('items'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'province_code' => ['required', 'string'],
            'province_name' => ['required', 'string'],
            'municipality_code' => ['required', 'string'],
            'municipality_name' => ['required', 'string'],
            'barangay_code' => ['required', 'string'],
            'barangay_name' => ['required', 'string'],
            'street' => ['nullable', 'string', 'max:255'],
            'payment_method' => ['required', 'in:cod,ewallet,card'],
        ]);

        $user = $request->user();
        $items = $user->cartItems()->with('product')->where('selected', true)->get();

        if ($items->isEmpty()) {
            return redirect()->route('cart.index')->withErrors([
                'checkout' => 'Your cart has no selected items to check out.',
            ]);
        }

        $order = DB::transaction(function () use ($user, $items, $validated) {
            $address = Address::create([
                'user_id' => $user->id,
                'province_code' => $validated['province_code'],
                'province_name' => $validated['province_name'],
                'municipality_code' => $validated['municipality_code'],
                'municipality_name' => $validated['municipality_name'],
                'barangay_code' => $validated['barangay_code'],
                'barangay_name' => $validated['barangay_name'],
                'street' => $validated['street'] ?? null,
                'house_number' => null,
            ]);

            $subtotal = $items->sum(fn ($item) => $item->product->price * $item->quantity);
            $shipping = $subtotal >= 2000 ? 0 : 60;
            $total = $subtotal + $shipping;

            $order = Order::create([
                'order_number' => 'NX-' . strtoupper(uniqid()),
                'buyer_id' => $user->id,
                'address_id' => $address->id,
                'status' => 'placed',
                'payment_method' => $validated['payment_method'],
                'subtotal' => $subtotal,
                'discount' => 0,
                'shipping_fee' => $shipping,
                'total' => $total,
                'placed_at' => now(),
            ]);

            foreach ($items as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name,
                    'price' => $item->product->price,
                    'quantity' => $item->quantity,
                    'variant' => $item->variant,
                ]);

                // One commission record per seller represented in this
                // order. Simplification: if items are split across sellers,
                // this attributes the whole line to that line's seller
                // rather than splitting a single order's shipping fee.
                Commission::create([
                    'order_id' => $order->id,
                    'seller_id' => $item->product->seller_id,
                    'order_total' => $item->product->price * $item->quantity,
                    'rate' => 0.10,
                    'commission_amount' => round($item->product->price * $item->quantity * 0.10, 2),
                    'seller_payout' => round($item->product->price * $item->quantity * 0.90, 2),
                    'status' => 'pending',
                ]);
            }

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status' => 'placed',
                'changed_by' => $user->id,
                'changed_at' => now(),
            ]);

            // Only the checked-out items leave the cart — anything left
            // unselected stays for next time.
            $user->cartItems()->where('selected', true)->delete();

            return $order;
        });

        return redirect()->route('buyer.orders')->with('status', "Order {$order->order_number} placed successfully!");
    }
}
