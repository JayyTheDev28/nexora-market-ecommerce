<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Models\UserVoucher;
use App\Models\Voucher;
use App\Models\Wishlist;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class BuyerSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Primary Demo Buyer
        $buyer = User::updateOrCreate(
            ['email' => 'buyer@nexora.test'],
            [
                'first_name' => 'Juan',
                'last_name' => 'Dela Cruz',
                'middle_initial' => 'M',
                'sex' => 'male',
                'birthday' => '1998-06-15',
                'age' => \Carbon\Carbon::parse('1998-06-15')->age,
                'contact_number' => '09171234567',
                'password' => Hash::make('Password123!@#'),
                'role' => 'buyer',
                'approval_status' => 'approved',
                'account_status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        // 2. Secondary Demo Buyer
        $buyer2 = User::updateOrCreate(
            ['email' => 'maria@nexora.test'],
            [
                'first_name' => 'Maria',
                'last_name' => 'Santos',
                'middle_initial' => 'C',
                'sex' => 'female',
                'birthday' => '1999-09-20',
                'age' => \Carbon\Carbon::parse('1999-09-20')->age,
                'contact_number' => '09189876543',
                'password' => Hash::make('Password123!@#'),
                'role' => 'buyer',
                'approval_status' => 'approved',
                'account_status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        // 3. Buyer Addresses
        $primaryAddress = Address::updateOrCreate(
            ['user_id' => $buyer->id, 'street' => '123 Rizal Street'],
            [
                'recipient_name' => 'Juan Dela Cruz',
                'recipient_phone' => '09171234567',
                'region_code' => '040000000',
                'region_name' => 'Region IV-A (CALABARZON)',
                'province_code' => '043400000',
                'province_name' => 'Laguna',
                'municipality_code' => '043426000',
                'municipality_name' => 'Santa Cruz',
                'barangay_code' => '043426001',
                'barangay_name' => 'Poblacion I',
                'house_number' => 'Unit 4B',
                'postal_code' => '4009',
                'is_default' => true,
            ]
        );

        Address::updateOrCreate(
            ['user_id' => $buyer->id, 'street' => 'National Highway'],
            [
                'recipient_name' => 'Juan Dela Cruz (Office)',
                'recipient_phone' => '09171234567',
                'region_code' => '040000000',
                'region_name' => 'Region IV-A (CALABARZON)',
                'province_code' => '043400000',
                'province_name' => 'Laguna',
                'municipality_code' => '043419000',
                'municipality_name' => 'Pagsanjan',
                'barangay_code' => '043419002',
                'barangay_name' => 'Sampaloc',
                'house_number' => 'Floor 2, Plaza Bldg',
                'postal_code' => '4008',
                'is_default' => false,
            ]
        );

        Address::updateOrCreate(
            ['user_id' => $buyer2->id, 'street' => '45 Mabini Street'],
            [
                'recipient_name' => 'Maria Santos',
                'recipient_phone' => '09189876543',
                'region_code' => '040000000',
                'region_name' => 'Region IV-A (CALABARZON)',
                'province_code' => '043400000',
                'province_name' => 'Laguna',
                'municipality_code' => '043426000',
                'municipality_name' => 'Santa Cruz',
                'barangay_code' => '043426002',
                'barangay_name' => 'Poblacion II',
                'house_number' => 'House 12',
                'postal_code' => '4009',
                'is_default' => true,
            ]
        );

        // 4. Store Follows
        $sellers = User::where('role', 'seller')->take(2)->get();
        foreach ($sellers as $seller) {
            DB::table('store_follows')->updateOrInsert(
                ['user_id' => $buyer->id, 'seller_id' => $seller->id],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }

        // 5. Claimed Vouchers
        $vouchers = Voucher::where('is_active', true)->take(2)->get();
        foreach ($vouchers as $voucher) {
            UserVoucher::updateOrCreate(
                ['user_id' => $buyer->id, 'voucher_id' => $voucher->id],
                ['claimed_at' => now()]
            );
        }

        // 6. Wishlist Items
        $products = Product::where('status', 'active')->take(3)->get();
        foreach ($products as $product) {
            Wishlist::firstOrCreate([
                'user_id' => $buyer->id,
                'product_id' => $product->id,
            ]);
        }

        // 7. Cart Item
        if ($products->isNotEmpty()) {
            $sampleProduct = $products->first();
            $color = ! empty($sampleProduct->colors) ? $sampleProduct->colors[0] : null;
            $size = ! empty($sampleProduct->sizes) ? $sampleProduct->sizes[0] : null;

            CartItem::updateOrCreate(
                [
                    'user_id' => $buyer->id,
                    'product_id' => $sampleProduct->id,
                ],
                [
                    'quantity' => 1,
                    'selected' => true,
                    'variant' => [
                        'color' => $color,
                        'size' => $size,
                        'price' => (float) $sampleProduct->price,
                    ],
                ]
            );
        }

        // 8. Sample Past Delivered Order with Review
        $deliveredProduct = Product::where('slug', 'nimbus-mechanical-keyboard')->first() ?? $products->first();
        if ($deliveredProduct) {
            $order = Order::updateOrCreate(
                ['order_number' => 'NEX-20261016-0001'],
                [
                    'buyer_id' => $buyer->id,
                    'seller_id' => $deliveredProduct->seller_id,
                    'address_id' => $primaryAddress->id,
                    'address_snapshot' => [
                        'recipient_name' => $primaryAddress->recipient_name,
                        'recipient_phone' => $primaryAddress->recipient_phone,
                        'region_name' => $primaryAddress->region_name,
                        'province_name' => $primaryAddress->province_name,
                        'municipality_name' => $primaryAddress->municipality_name,
                        'barangay_name' => $primaryAddress->barangay_name,
                        'street' => $primaryAddress->street,
                        'house_number' => $primaryAddress->house_number,
                        'postal_code' => $primaryAddress->postal_code,
                    ],
                    'status' => 'delivered',
                    'payment_method' => 'cod',
                    'payment_status' => 'paid',
                    'subtotal' => $deliveredProduct->price,
                    'discount' => 0.00,
                    'shipping_fee' => 50.00,
                    'shipping_weight' => 1.20,
                    'total' => $deliveredProduct->price + 50.00,
                    'placed_at' => now()->subDays(4),
                    'delivered_at' => now()->subDays(2),
                    'completed_at' => now()->subDays(1),
                ]
            );

            OrderItem::updateOrCreate(
                [
                    'order_id' => $order->id,
                    'product_id' => $deliveredProduct->id,
                ],
                [
                    'product_name' => $deliveredProduct->name,
                    'price' => $deliveredProduct->price,
                    'quantity' => 1,
                    'variant' => ['color' => 'Graphite', 'size' => 'TKL'],
                ]
            );

            // Seed order milestone status histories
            OrderStatusHistory::firstOrCreate(
                ['order_id' => $order->id, 'status' => 'placed'],
                ['changed_by' => $buyer->id, 'note' => 'Order placed by buyer.', 'changed_at' => now()->subDays(4)]
            );
            OrderStatusHistory::firstOrCreate(
                ['order_id' => $order->id, 'status' => 'approved'],
                ['changed_by' => $deliveredProduct->seller_id, 'note' => 'Order confirmed and ready for pickup.', 'changed_at' => now()->subDays(3)->subHours(12)]
            );
            OrderStatusHistory::firstOrCreate(
                ['order_id' => $order->id, 'status' => 'delivered'],
                ['changed_by' => null, 'note' => 'Parcel delivered successfully to buyer.', 'changed_at' => now()->subDays(2)]
            );

            // Seed customer review
            Review::updateOrCreate(
                ['order_id' => $order->id, 'product_id' => $deliveredProduct->id],
                [
                    'buyer_id' => $buyer->id,
                    'rating' => 5,
                    'comment' => 'Exceptional quality! Keys are buttery smooth and the delivery was fast to Laguna. Will buy again!',
                    'edited_count' => 0,
                ]
            );
        }

        // 9. Sample Active Order (Out for delivery)
        $activeProduct = Product::where('slug', 'artisan-ceramic-pour-over-set')->first() ?? $products->skip(1)->first();
        $courier = User::where('role', 'courier')->first();
        $sortingCenter = User::where('role', 'sorting_center')->first();

        if ($activeProduct) {
            $activeOrder = Order::updateOrCreate(
                ['order_number' => 'NEX-20261016-0002'],
                [
                    'buyer_id' => $buyer->id,
                    'seller_id' => $activeProduct->seller_id,
                    'address_id' => $primaryAddress->id,
                    'address_snapshot' => [
                        'recipient_name' => $primaryAddress->recipient_name,
                        'recipient_phone' => $primaryAddress->recipient_phone,
                        'region_name' => $primaryAddress->region_name,
                        'province_name' => $primaryAddress->province_name,
                        'municipality_name' => $primaryAddress->municipality_name,
                        'barangay_name' => $primaryAddress->barangay_name,
                        'street' => $primaryAddress->street,
                        'house_number' => $primaryAddress->house_number,
                        'postal_code' => $primaryAddress->postal_code,
                    ],
                    'status' => 'out_for_delivery',
                    'payment_method' => 'ewallet',
                    'payment_status' => 'paid',
                    'subtotal' => $activeProduct->price,
                    'discount' => 0.00,
                    'shipping_fee' => 50.00,
                    'shipping_weight' => 0.80,
                    'total' => $activeProduct->price + 50.00,
                    'delivery_courier_id' => $courier?->id,
                    'sorting_center_id' => $sortingCenter?->id,
                    'placed_at' => now()->subHours(18),
                ]
            );

            OrderItem::updateOrCreate(
                [
                    'order_id' => $activeOrder->id,
                    'product_id' => $activeProduct->id,
                ],
                [
                    'product_name' => $activeProduct->name,
                    'price' => $activeProduct->price,
                    'quantity' => 1,
                    'variant' => ['color' => 'Terracotta'],
                ]
            );

            OrderStatusHistory::firstOrCreate(
                ['order_id' => $activeOrder->id, 'status' => 'placed'],
                ['changed_by' => $buyer->id, 'note' => 'Order placed with online payment.', 'changed_at' => now()->subHours(18)]
            );
            OrderStatusHistory::firstOrCreate(
                ['order_id' => $activeOrder->id, 'status' => 'at_sorting_center'],
                ['changed_by' => $sortingCenter?->id, 'note' => 'Arrived at Nexora Laguna Sorting Hub.', 'changed_at' => now()->subHours(8)]
            );
            OrderStatusHistory::firstOrCreate(
                ['order_id' => $activeOrder->id, 'status' => 'out_for_delivery'],
                ['changed_by' => $courier?->id, 'note' => 'Rider is out for delivery in Santa Cruz.', 'changed_at' => now()->subHours(2)]
            );
        }
    }
}
