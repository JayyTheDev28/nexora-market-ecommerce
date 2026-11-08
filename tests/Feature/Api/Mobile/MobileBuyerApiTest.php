<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\Address;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MobileBuyerApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $buyer;
    protected string $buyerToken;
    protected User $seller1;
    protected User $seller2;
    protected Category $category;
    protected Product $product1;
    protected Product $product2;
    protected Address $address;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        // 1. Create approved & active buyer
        $this->buyer = User::factory()->create([
            'role' => 'buyer',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
        $this->buyerToken = $this->buyer->createToken('buyer-test')->plainTextToken;

        // 2. Create buyer address
        $this->address = Address::create([
            'user_id' => $this->buyer->id,
            'recipient_name' => 'Juan Dela Cruz',
            'recipient_phone' => '09171234567',
            'province_code' => '043400000',
            'province_name' => 'Laguna',
            'municipality_code' => '043426000',
            'municipality_name' => 'Santa Cruz',
            'barangay_code' => '043426001',
            'barangay_name' => 'Poblacion I',
            'street' => '123 Market St',
            'is_default' => true,
        ]);

        // 3. Create Sellers
        $this->seller1 = User::factory()->create([
            'role' => 'seller',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
        SellerProfile::create([
            'user_id' => $this->seller1->id,
            'business_name' => 'Apex Footwear Store',
            'category' => 'Shoes & Footwear',
            'is_vacation_mode' => false,
        ]);

        $this->seller2 = User::factory()->create([
            'role' => 'seller',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
        SellerProfile::create([
            'user_id' => $this->seller2->id,
            'business_name' => 'Laguna Organic Crafts',
            'category' => 'Crafts & Accessories',
            'is_vacation_mode' => false,
        ]);

        // 4. Create Category
        $this->category = Category::create([
            'name' => 'Shoes & Footwear',
            'slug' => 'shoes-footwear',
        ]);

        // 5. Create Products
        $this->product1 = Product::create([
            'seller_id' => $this->seller1->id,
            'category_id' => $this->category->id,
            'name' => 'Running Sneakers Pro',
            'slug' => 'running-sneakers-pro',
            'description' => 'Lightweight athletic running shoes.',
            'price' => 1500.00,
            'stock' => 20,
            'weight' => 1.2, // Will bill as ceil(1.2) = 2kg
            'status' => 'active',
            'gallery' => ['products/sneakers1.jpg'],
        ]);

        $this->product2 = Product::create([
            'seller_id' => $this->seller2->id,
            'category_id' => $this->category->id,
            'name' => 'Handmade Leather Belt',
            'slug' => 'handmade-leather-belt',
            'description' => 'Genuine artisan leather belt.',
            'price' => 500.00,
            'stock' => 15,
            'weight' => 0.5, // Will bill as ceil(0.5) = 1kg
            'status' => 'active',
            'gallery' => ['products/belt1.jpg'],
        ]);
    }

    public function test_buyer_can_browse_catalog_and_search(): void
    {
        $response = $this->getJson('/api/mobile/v1/buyer/catalog?q=Sneakers');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.name', 'Running Sneakers Pro')
            ->assertJsonStructure(['data', 'pagination', 'message']);
    }

    public function test_buyer_can_view_product_details_with_seller_info(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->buyerToken}")
            ->getJson("/api/mobile/v1/buyer/products/{$this->product1->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.product.id', $this->product1->id)
            ->assertJsonPath('data.product.seller.business_name', 'Apex Footwear Store');
    }

    public function test_buyer_can_manage_wishlist_and_followed_stores(): void
    {
        // Toggle wishlist
        $wishResponse = $this->withHeader('Authorization', "Bearer {$this->buyerToken}")
            ->postJson("/api/mobile/v1/buyer/wishlist/{$this->product1->id}/toggle");

        $wishResponse->assertStatus(200)
            ->assertJsonPath('data.in_wishlist', true);

        // Get wishlist
        $listResponse = $this->withHeader('Authorization', "Bearer {$this->buyerToken}")
            ->getJson('/api/mobile/v1/buyer/wishlist');

        $listResponse->assertStatus(200)
            ->assertJsonCount(1, 'data');

        // Toggle store follow
        $followResponse = $this->withHeader('Authorization', "Bearer {$this->buyerToken}")
            ->postJson("/api/mobile/v1/buyer/stores/{$this->seller1->id}/follow-toggle");

        $followResponse->assertStatus(200)
            ->assertJsonPath('data.is_following', true);
    }

    public function test_buyer_can_add_to_cart_with_line_merging(): void
    {
        // Add 2 units
        $add1 = $this->withHeader('Authorization', "Bearer {$this->buyerToken}")
            ->postJson('/api/mobile/v1/buyer/cart/items', [
                'product_id' => $this->product1->id,
                'quantity' => 2,
            ]);

        $add1->assertStatus(201)
            ->assertJsonPath('data.item.quantity', 2);

        // Add 3 more units of the same product -> should merge into 5 units
        $add2 = $this->withHeader('Authorization', "Bearer {$this->buyerToken}")
            ->postJson('/api/mobile/v1/buyer/cart/items', [
                'product_id' => $this->product1->id,
                'quantity' => 3,
            ]);

        $add2->assertStatus(201)
            ->assertJsonPath('data.item.quantity', 5);

        $this->assertCount(1, $this->buyer->cartItems);
        $this->assertEquals(5, $this->buyer->cartItems->first()->quantity);
    }

    public function test_checkout_multi_seller_splitting_and_weight_based_shipping(): void
    {
        // Put 1 item from Seller 1 and 2 items from Seller 2 into cart
        $this->buyer->cartItems()->create([
            'product_id' => $this->product1->id,
            'quantity' => 2, // 2 * 1500 = 3000 subtotal, weight 2.4kg -> billed 3kg -> 50 + 20*(3-1) = 90 shipping
            'selected' => true,
        ]);

        $this->buyer->cartItems()->create([
            'product_id' => $this->product2->id,
            'quantity' => 1, // 1 * 500 = 500 subtotal, weight 0.5kg -> billed 1kg -> 50 + 20*(1-1) = 50 shipping
            'selected' => true,
        ]);

        // Check summary
        $summaryResponse = $this->withHeader('Authorization', "Bearer {$this->buyerToken}")
            ->getJson('/api/mobile/v1/buyer/checkout/summary');

        $summaryResponse->assertStatus(200)
            ->assertJsonPath('data.total_subtotal', 3500)
            ->assertJsonPath('data.total_shipping', 140); // 90 + 50 = 140

        // Process checkout
        $checkoutResponse = $this->withHeader('Authorization', "Bearer {$this->buyerToken}")
            ->withHeader('Idempotency-Key', 'TEST_CHECKOUT_IDEMP_KEY_001')
            ->postJson('/api/mobile/v1/buyer/checkout', [
                'address_id' => $this->address->id,
            ]);

        $checkoutResponse->assertStatus(201)
            ->assertJsonPath('data.orders_count', 2)
            ->assertJsonStructure(['data' => ['orders', 'checkout_session_id']]);

        // Verifications
        $orders = Order::where('buyer_id', $this->buyer->id)->get();
        $this->assertCount(2, $orders);

        // Seller 1 order: subtotal 3000, shipping 90, total 3090
        $order1 = $orders->firstWhere('seller_id', $this->seller1->id);
        $this->assertNotNull($order1);
        $this->assertEquals(3000.00, (float) $order1->subtotal);
        $this->assertEquals(90.00, (float) $order1->shipping_fee);
        $this->assertEquals(3090.00, (float) $order1->total);
        $this->assertEquals('cod', $order1->payment_method);
        $this->assertEquals('placed', $order1->status);

        // Seller 2 order: subtotal 500, shipping 50, total 550
        $order2 = $orders->firstWhere('seller_id', $this->seller2->id);
        $this->assertNotNull($order2);
        $this->assertEquals(500.00, (float) $order2->subtotal);
        $this->assertEquals(50.00, (float) $order2->shipping_fee);
        $this->assertEquals(550.00, (float) $order2->total);

        // Shared checkout_session_id
        $this->assertEquals($order1->checkout_session_id, $order2->checkout_session_id);

        // Product stock decremented
        $this->assertEquals(18, $this->product1->fresh()->stock);
        $this->assertEquals(14, $this->product2->fresh()->stock);

        // Commissions created (10%)
        $this->assertDatabaseHas('commissions', [
            'order_id' => $order1->id,
            'rate' => 0.10,
            'commission_amount' => 300.00,
            'seller_payout' => 2700.00,
        ]);

        // Cart items cleared
        $this->assertCount(0, $this->buyer->fresh()->cartItems);
    }

    public function test_buyer_can_cancel_pre_shipping_order_restoring_stock(): void
    {
        $order = Order::create([
            'order_number' => 'NX-CANCEL001',
            'checkout_session_id' => 'CS-CANCEL001',
            'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller1->id,
            'address_id' => $this->address->id,
            'status' => 'placed',
            'payment_method' => 'cod',
            'subtotal' => 1500.00,
            'total' => 1550.00,
            'placed_at' => now(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product1->id,
            'product_name' => $this->product1->name,
            'price' => 1500.00,
            'quantity' => 2,
        ]);
        $this->product1->update(['stock' => 18]);

        $cancelResponse = $this->withHeader('Authorization', "Bearer {$this->buyerToken}")
            ->postJson("/api/mobile/v1/buyer/orders/{$order->id}/cancel", [
                'reason' => 'Changed my mind.',
            ]);

        $cancelResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertEquals('cancelled', $order->fresh()->status);
        $this->assertEquals(20, $this->product1->fresh()->stock); // Stock restored!
    }

    public function test_buyer_can_chat_with_seller(): void
    {
        // Start chat
        $startResponse = $this->withHeader('Authorization', "Bearer {$this->buyerToken}")
            ->postJson('/api/mobile/v1/buyer/chat/start', [
                'seller_id' => $this->seller1->id,
                'product_id' => $this->product1->id,
            ]);

        $startResponse->assertStatus(200);
        $convoId = $startResponse->json('data.id');

        // Send message
        $msgResponse = $this->withHeader('Authorization', "Bearer {$this->buyerToken}")
            ->postJson("/api/mobile/v1/buyer/chat/{$convoId}/message", [
                'body' => 'Is this shoe size 10 available?',
            ]);

        $msgResponse->assertStatus(201)
            ->assertJsonPath('data.body', 'Is this shoe size 10 available?');
    }

    public function test_checkout_parity_between_web_controller_and_mobile_checkout_service(): void
    {
        // 1. Setup a web buyer with identical address & vouchers
        $webBuyer = User::factory()->create([
            'role' => 'buyer',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
        $webAddress = Address::create([
            'user_id' => $webBuyer->id,
            'recipient_name' => 'Maria Clara',
            'recipient_phone' => '09181234567',
            'province_code' => '043400000',
            'province_name' => 'Laguna',
            'municipality_code' => '043426000',
            'municipality_name' => 'Santa Cruz',
            'barangay_code' => '043426001',
            'barangay_name' => 'Poblacion I',
            'street' => '456 Rizal St',
            'is_default' => true,
        ]);

        // Platform voucher: 10% discount, min spend 1000
        $platformVoucher = Voucher::create([
            'code' => 'PLATFORM10',
            'voucher_scope' => 'platform',
            'type' => 'percent',
            'value' => 10,
            'min_spend' => 1000,
            'is_active' => true,
        ]);

        // Claim voucher for both web buyer and mobile buyer
        $webBuyer->vouchers()->attach($platformVoucher->id);
        $this->buyer->vouchers()->attach($platformVoucher->id);

        // Put identical items in web buyer cart:
        // Seller 1 product: qty 2 (2 * 1500 = 3000, weight 2.4kg -> billed 3kg -> shipping 50 + 20*(3-1) = 90)
        // Seller 2 product: qty 1 (1 * 500 = 500, weight 0.5kg -> billed 1kg -> shipping 50 + 20*(1-1) = 50)
        $webBuyer->cartItems()->create([
            'product_id' => $this->product1->id,
            'quantity' => 2,
            'selected' => true,
        ]);
        $webBuyer->cartItems()->create([
            'product_id' => $this->product2->id,
            'quantity' => 1,
            'selected' => true,
        ]);

        // Put identical items in mobile buyer cart:
        $this->buyer->cartItems()->create([
            'product_id' => $this->product1->id,
            'quantity' => 2,
            'selected' => true,
        ]);
        $this->buyer->cartItems()->create([
            'product_id' => $this->product2->id,
            'quantity' => 1,
            'selected' => true,
        ]);

        // 2. Perform Web Checkout via POST /checkout
        $webResponse = $this->actingAs($webBuyer)
            ->post('/checkout', [
                'address_id' => $webAddress->id,
                'platform_voucher_id' => $platformVoucher->id,
                'payment_method' => 'cod',
            ]);
        $webResponse->assertSessionHasNoErrors();
        $webOrders = Order::where('buyer_id', $webBuyer->id)->orderBy('seller_id')->get();

        // 3. Clear web session so Sanctum authenticates the mobile buyer
        auth('web')->logout();
        auth()->forgetGuards();

        // Perform Mobile Checkout via POST /api/mobile/v1/buyer/checkout
        $mobileResponse = $this->withHeader('Authorization', "Bearer {$this->buyerToken}")
            ->withHeader('Idempotency-Key', 'PARITY_CHECKOUT_IDEMP_001')
            ->postJson('/api/mobile/v1/buyer/checkout', [
                'address_id' => $this->address->id,
                'platform_voucher_id' => $platformVoucher->id,
                'payment_method' => 'cod',
            ]);
        $mobileResponse->assertStatus(201);
        $mobileOrders = Order::where('buyer_id', $this->buyer->id)->orderBy('seller_id')->get();

        // 4. Assert Exact Side-by-Side Parity Across Split Orders
        $this->assertCount(2, $webOrders);
        $this->assertCount(2, $mobileOrders);

        for ($i = 0; $i < 2; $i++) {
            $webOrder = $webOrders[$i];
            $mobileOrder = $mobileOrders[$i];

            // Same seller split
            $this->assertEquals($webOrder->seller_id, $mobileOrder->seller_id);

            // Same financial totals down to centavos
            $this->assertEquals((float) $webOrder->subtotal, (float) $mobileOrder->subtotal);
            $this->assertEquals((float) $webOrder->shipping_fee, (float) $mobileOrder->shipping_fee);
            $this->assertEquals((float) $webOrder->discount, (float) $mobileOrder->discount);
            $this->assertEquals((float) $webOrder->total, (float) $mobileOrder->total);
            $this->assertEquals($webOrder->shipping_weight, $mobileOrder->shipping_weight);

            // Same statuses & payment methods
            $this->assertEquals($webOrder->status, $mobileOrder->status);
            $this->assertEquals($webOrder->payment_method, $mobileOrder->payment_method);
            $this->assertEquals($webOrder->payment_status, $mobileOrder->payment_status);

            // Commission parity
            $webComm = \App\Models\Commission::where('order_id', $webOrder->id)->first();
            $mobileComm = \App\Models\Commission::where('order_id', $mobileOrder->id)->first();
            $this->assertNotNull($webComm);
            $this->assertNotNull($mobileComm);
            $this->assertEquals((float) $webComm->rate, (float) $mobileComm->rate);
            $this->assertEquals((float) $webComm->commission_amount, (float) $mobileComm->commission_amount);
            $this->assertEquals((float) $webComm->seller_payout, (float) $mobileComm->seller_payout);

            // Order items parity
            $this->assertCount($webOrder->items->count(), $mobileOrder->items);
            foreach ($webOrder->items as $wItem) {
                $mItem = $mobileOrder->items->firstWhere('product_id', $wItem->product_id);
                $this->assertNotNull($mItem);
                $this->assertEquals((float) $wItem->price, (float) $mItem->price);
                $this->assertEquals($wItem->quantity, $mItem->quantity);
            }
        }
    }

    public function test_s1_followed_stores_route_works_and_non_numeric_store_returns_404(): void
    {
        $this->withHeader('Authorization', "Bearer {$this->buyerToken}")
            ->postJson("/api/mobile/v1/buyer/stores/{$this->seller1->id}/follow-toggle")
            ->assertStatus(200);

        $followed = $this->withHeader('Authorization', "Bearer {$this->buyerToken}")
            ->getJson('/api/mobile/v1/buyer/stores/followed');

        $followed->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->seller1->id);

        $this->getJson('/api/mobile/v1/buyer/stores/nonexistent-slug')
            ->assertStatus(404);
    }

    public function test_s2_orders_tabs_include_hub_and_return_statuses_and_block_transit_cancel(): void
    {
        $variantProduct = Product::create([
            'seller_id' => $this->seller1->id,
            'category_id' => $this->category->id,
            'name' => 'Variant Tee',
            'slug' => 'variant-tee',
            'description' => 'Cotton tee with color image.',
            'price' => 350.00,
            'stock' => 10,
            'weight' => 0.3,
            'status' => 'active',
            'gallery' => ['products/tee-default.jpg'],
            'variants_data' => [
                ['name' => 'Crimson Red', 'sku' => 'TEE-RED', 'stock' => 5, 'price' => 399.00, 'image' => 'products/tee-red.jpg'],
            ],
        ]);

        $statuses = ['assigned', 'at_hub', 'received_at_hub', 'in_transit_hub', 'failed_delivery', 'delivery_failed'];
        $transitOrder = null;
        foreach ($statuses as $idx => $st) {
            $o = Order::create([
                'order_number' => 'NX-HUB000' . $idx,
                'checkout_session_id' => 'CS-HUB000' . $idx,
                'buyer_id' => $this->buyer->id,
                'seller_id' => $this->seller1->id,
                'address_id' => $this->address->id,
                'status' => $st,
                'payment_method' => 'cod',
                'subtotal' => 399.00,
                'total' => 449.00,
                'placed_at' => now()->subMinutes($idx),
            ]);
            OrderItem::create([
                'order_id' => $o->id,
                'product_id' => $variantProduct->id,
                'product_name' => $variantProduct->name,
                'price' => 399.00,
                'quantity' => 1,
                'variant' => ['variation' => 'Crimson Red', 'size' => 'M'],
            ]);
            if ($st === 'in_transit_hub') {
                $transitOrder = $o;
            }
        }

        // Return/refund statuses + dispute order
        $rts1 = Order::create([
            'order_number' => 'NX-RTS0001',
            'checkout_session_id' => 'CS-RTS0001',
            'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller1->id,
            'address_id' => $this->address->id,
            'status' => 'return_to_sender',
            'payment_method' => 'cod',
            'subtotal' => 500.00,
            'total' => 550.00,
            'placed_at' => now()->subHours(2),
        ]);
        $rts2 = Order::create([
            'order_number' => 'NX-RTS0002',
            'checkout_session_id' => 'CS-RTS0002',
            'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller1->id,
            'address_id' => $this->address->id,
            'status' => 'returned_to_sender',
            'payment_method' => 'cod',
            'subtotal' => 500.00,
            'total' => 550.00,
            'placed_at' => now()->subHours(3),
        ]);
        $disputedDelivered = Order::create([
            'order_number' => 'NX-DISP0001',
            'checkout_session_id' => 'CS-DISP0001',
            'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller1->id,
            'address_id' => $this->address->id,
            'status' => 'delivered',
            'delivered_at' => now()->subDay(),
            'payment_method' => 'cod',
            'subtotal' => 500.00,
            'total' => 550.00,
            'placed_at' => now()->subHours(4),
        ]);
        $disputedDelivered->dispute()->create([
            'raised_by' => $this->buyer->id,
            'reason' => 'Damaged item',
            'details' => 'Box was crushed upon delivery.',
            'media_paths' => ['returns/evidence1.jpg'],
            'status' => 'open',
        ]);

        $toReceiveRes = $this->withHeader('Authorization', "Bearer {$this->buyerToken}")
            ->getJson('/api/mobile/v1/buyer/orders?tab=to_receive');

        $toReceiveRes->assertStatus(200)
            ->assertJsonPath('counts.to_receive', 6)
            ->assertJsonPath('counts.return_refund', 3)
            ->assertJsonCount(6, 'data');

        $firstImg = $toReceiveRes->json('data.0.items.0.image');
        $this->assertStringContainsString('tee-red.jpg', (string) $firstImg);

        $returnRes = $this->withHeader('Authorization', "Bearer {$this->buyerToken}")
            ->getJson('/api/mobile/v1/buyer/orders?tab=return_refund');

        $returnRes->assertStatus(200)
            ->assertJsonCount(3, 'data');

        $cancelTransit = $this->withHeader('Authorization', "Bearer {$this->buyerToken}")
            ->postJson("/api/mobile/v1/buyer/orders/{$transitOrder->id}/cancel", [
                'reason' => 'Already shipped',
            ]);

        $cancelTransit->assertStatus(422)
            ->assertJsonPath('message', 'This order cannot be cancelled as it is already being processed or prepared for pickup.');
    }

    public function test_s3_buy_now_selective_checkout_vouchers_idempotency_and_variation_stock(): void
    {
        $variantProduct = Product::create([
            'seller_id' => $this->seller1->id,
            'category_id' => $this->category->id,
            'name' => 'Pro Jersey',
            'slug' => 'pro-jersey',
            'description' => 'Variation stock only.',
            'price' => 800.00,
            'stock' => 0, // Parent stock 0, stock lives in variants_data
            'weight' => 0.5,
            'status' => 'active',
            'gallery' => ['products/jersey-main.jpg'],
            'variants_data' => [
                ['name' => 'Blue', 'sku' => 'JER-BLUE', 'stock' => 4, 'price' => 850.00, 'image' => 'products/jersey-blue.jpg'],
                ['name' => 'OutOfStockBlack', 'sku' => 'JER-BLK', 'stock' => 0, 'price' => 850.00],
            ],
        ]);

        // Unrelated selected item stays in cart after selective Buy Now checkout
        $unrelatedCartItem = $this->buyer->cartItems()->create([
            'product_id' => $this->product2->id,
            'quantity' => 1,
            'selected' => true,
        ]);

        // Add variant via API, ignoring untrusted client price
        $addRes = $this->withHeader('Authorization', "Bearer {$this->buyerToken}")
            ->postJson('/api/mobile/v1/buyer/cart/items', [
                'product_id' => $variantProduct->id,
                'quantity' => 2,
                'variant' => ['variation' => 'Blue', 'size' => 'L', 'price' => 1.00],
            ]);

        $addRes->assertStatus(201)
            ->assertJsonPath('data.item.price', 850)
            ->assertJsonPath('data.item.stock', 4);
        $buyNowCartId = $addRes->json('data.cart_item_id');
        $this->assertNotNull($buyNowCartId);

        // Summary scoped to cart_item_ids only includes the Buy Now item (2 * 850 = 1700)
        $summaryRes = $this->withHeader('Authorization', "Bearer {$this->buyerToken}")
            ->getJson("/api/mobile/v1/buyer/checkout/summary?cart_item_ids={$buyNowCartId}");
        $summaryRes->assertStatus(200)
            ->assertJsonPath('data.total_subtotal', 1700)
            ->assertJsonPath('data.total_shipping', 50);

        // Foreign / nonexistent cart_item_id rejected with 422
        $this->withHeader('Authorization', "Bearer {$this->buyerToken}")
            ->postJson('/api/mobile/v1/buyer/checkout', [
                'address_id' => $this->address->id,
                'cart_item_ids' => [999999],
            ])
            ->assertStatus(422);

        // Expired voucher rejected with 422
        $expiredVoucher = Voucher::create([
            'code' => 'EXPIRED100',
            'voucher_scope' => 'platform',
            'type' => 'flat',
            'value' => 100,
            'min_spend' => 100,
            'is_active' => true,
            'expires_at' => now()->subDay(),
        ]);
        $this->withHeader('Authorization', "Bearer {$this->buyerToken}")
            ->postJson('/api/mobile/v1/buyer/checkout', [
                'address_id' => $this->address->id,
                'cart_item_ids' => [$buyNowCartId],
                'platform_voucher_id' => $expiredVoucher->id,
            ])
            ->assertStatus(422);

        // Below-min-spend voucher rejected with 422
        $highMinVoucher = Voucher::create([
            'code' => 'BIGSPEND500',
            'voucher_scope' => 'platform',
            'type' => 'flat',
            'value' => 500,
            'min_spend' => 10000,
            'is_active' => true,
        ]);
        $this->withHeader('Authorization', "Bearer {$this->buyerToken}")
            ->postJson('/api/mobile/v1/buyer/checkout', [
                'address_id' => $this->address->id,
                'cart_item_ids' => [$buyNowCartId],
                'platform_voucher_id' => $highMinVoucher->id,
            ])
            ->assertStatus(422);

        // Double-tap / retry with same Idempotency-Key creates only 1 order
        $idempKey = 'BUY-NOW-IDEMP-001';
        $checkout1 = $this->withHeader('Authorization', "Bearer {$this->buyerToken}")
            ->withHeader('Idempotency-Key', $idempKey)
            ->postJson('/api/mobile/v1/buyer/checkout', [
                'address_id' => $this->address->id,
                'cart_item_ids' => [$buyNowCartId],
            ]);
        $checkout1->assertStatus(201)->assertJsonPath('data.orders_count', 1);
        $firstOrderNum = $checkout1->json('data.orders.0.order_number');
        $firstOrderId = $checkout1->json('data.orders.0.id');

        $checkout2 = $this->withHeader('Authorization', "Bearer {$this->buyerToken}")
            ->withHeader('Idempotency-Key', $idempKey)
            ->postJson('/api/mobile/v1/buyer/checkout', [
                'address_id' => $this->address->id,
                'cart_item_ids' => [$buyNowCartId],
            ]);
        $checkout2->assertStatus(201)
            ->assertJsonPath('data.orders.0.order_number', $firstOrderNum);

        // Only 1 order created, unrelated selected cart item preserved
        $this->assertEquals(1, Order::where('buyer_id', $this->buyer->id)->count());
        $this->assertDatabaseHas('cart_items', ['id' => $unrelatedCartItem->id]);
        $this->assertDatabaseMissing('cart_items', ['id' => $buyNowCartId]);

        // PSGC codes persisted in address_snapshot
        $order = Order::findOrFail($firstOrderId);
        $this->assertEquals('043400000', $order->address_snapshot['province_code']);
        $this->assertEquals('043426000', $order->address_snapshot['municipality_code']);
        $this->assertEquals('043426001', $order->address_snapshot['barangay_code']);

        // Variation stock decremented cleanly without unsigned SQL error
        $freshVariants = $variantProduct->fresh()->variants_data;
        $this->assertEquals(2, $freshVariants[0]['stock']);
    }

    public function test_s4_cart_validation_rejects_vacation_and_out_of_stock_variants(): void
    {
        $vacationSeller = User::factory()->create([
            'role' => 'seller',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
        SellerProfile::create([
            'user_id' => $vacationSeller->id,
            'business_name' => 'Holiday Shop',
            'category' => 'General',
            'is_vacation_mode' => true,
        ]);
        $vacationProduct = Product::create([
            'seller_id' => $vacationSeller->id,
            'category_id' => $this->category->id,
            'name' => 'Closed Shop Candle',
            'slug' => 'closed-shop-candle',
            'description' => 'Temp unavailable.',
            'price' => 200.00,
            'stock' => 10,
            'status' => 'active',
        ]);

        $this->withHeader('Authorization', "Bearer {$this->buyerToken}")
            ->postJson('/api/mobile/v1/buyer/cart/items', [
                'product_id' => $vacationProduct->id,
                'quantity' => 1,
            ])
            ->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Holiday Shop is currently on vacation mode and not accepting new orders.',
            ]);

        $variantProduct = Product::create([
            'seller_id' => $this->seller1->id,
            'category_id' => $this->category->id,
            'name' => 'Cap',
            'slug' => 'cap',
            'description' => 'Limited stock variant.',
            'price' => 300.00,
            'stock' => 10,
            'status' => 'active',
            'variants_data' => [
                ['name' => 'Black', 'sku' => 'CAP-BLK', 'stock' => 0, 'price' => 300.00],
                ['name' => 'White', 'sku' => 'CAP-WHT', 'stock' => 2, 'price' => 320.00],
            ],
        ]);

        $this->withHeader('Authorization', "Bearer {$this->buyerToken}")
            ->postJson('/api/mobile/v1/buyer/cart/items', [
                'product_id' => $variantProduct->id,
                'quantity' => 1,
                'variant' => ['variation' => 'Black'],
            ])
            ->assertStatus(422);

        $okAdd = $this->withHeader('Authorization', "Bearer {$this->buyerToken}")
            ->postJson('/api/mobile/v1/buyer/cart/items', [
                'product_id' => $variantProduct->id,
                'quantity' => 1,
                'variant' => ['variation' => 'White'],
            ])
            ->assertStatus(201);

        $itemId = $okAdd->json('data.cart_item_id');

        $this->withHeader('Authorization', "Bearer {$this->buyerToken}")
            ->patchJson("/api/mobile/v1/buyer/cart/items/{$itemId}", [
                'quantity' => 3,
            ])
            ->assertStatus(422);
    }
}

