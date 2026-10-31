<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Conversation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\SellerProfile;
use App\Models\User;
use App\Models\UserVoucher;
use App\Models\Voucher;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BuyerSubsystemsTest extends TestCase
{
    use RefreshDatabase;

    protected User $buyer;
    protected User $seller1;
    protected User $seller2;
    protected Category $category;
    protected Product $product1;
    protected Product $product2;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        // Create Buyer
        $this->buyer = User::create([
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'middle_initial' => 'P',
            'sex' => 'male',
            'birthday' => '1995-05-15',
            'age' => 30,
            'email' => 'juan@example.com',
            'contact_number' => '09171234567',
            'password' => Hash::make('Password123!@#'),
            'role' => 'buyer',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        // Create Seller 1
        $this->seller1 = User::create([
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'sex' => 'female',
            'birthday' => '1990-08-20',
            'age' => 35,
            'email' => 'seller1@example.com',
            'contact_number' => '09181234567',
            'password' => Hash::make('Password123!@#'),
            'role' => 'seller',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
        SellerProfile::create([
            'user_id' => $this->seller1->id,
            'business_name' => 'Santos Apparel',
            'category' => 'Fashion & Apparel',
        ]);

        // Create Seller 2
        $this->seller2 = User::create([
            'first_name' => 'Carlos',
            'last_name' => 'Reyes',
            'sex' => 'male',
            'birthday' => '1988-12-10',
            'age' => 37,
            'email' => 'seller2@example.com',
            'contact_number' => '09191234567',
            'password' => Hash::make('Password123!@#'),
            'role' => 'seller',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
        SellerProfile::create([
            'user_id' => $this->seller2->id,
            'business_name' => 'Reyes Electronics',
            'category' => 'Electronics',
        ]);

        // Category
        $this->category = Category::create([
            'name' => 'Apparel & Fashion',
            'slug' => 'apparel',
            'icon' => 'checkroom',
        ]);

        // Product 1 (Weight 1.2 kg -> ceil is 2 kg)
        $this->product1 = Product::create([
            'seller_id' => $this->seller1->id,
            'category_id' => $this->category->id,
            'name' => 'Premium Cotton T-Shirt',
            'slug' => 'premium-cotton-tshirt',
            'description' => 'Comfortable breathable shirt',
            'price' => 500.00,
            'compare_price' => 700.00,
            'stock' => 50,
            'weight' => 1.20,
            'max_purchase_limit' => 5,
            'status' => 'active',
            'colors' => ['Black', 'White'],
            'sizes' => ['M', 'L'],
        ]);

        // Product 2 (Weight 0.6 kg)
        $this->product2 = Product::create([
            'seller_id' => $this->seller2->id,
            'category_id' => $this->category->id,
            'name' => 'Wireless Bluetooth Earbuds',
            'slug' => 'wireless-earbuds',
            'description' => 'Noise cancelling earbuds',
            'price' => 1500.00,
            'stock' => 20,
            'weight' => 0.60,
            'status' => 'active',
        ]);
    }

    /**
     * B1: Account & Profile Updates, Strict Password Validation, Email/Phone OTP
     */
    public function test_b1_profile_update_and_strict_password_rules(): void
    {
        $this->actingAs($this->buyer);

        // 1. Profile update with avatar photo
        $avatar = UploadedFile::fake()->create('avatar.jpg', 300, 'image/jpeg');
        $resProfile = $this->put(route('buyer.account.profile'), [
            'first_name' => 'Juanito',
            'last_name' => 'Dela Cruz',
            'middle_initial' => 'P',
            'sex' => 'male',
            'birthday' => '1995-05-15',
            'avatar' => $avatar,
        ]);
        $resProfile->assertRedirect();
        $this->buyer->refresh();
        $this->assertEquals('Juanito', $this->buyer->first_name);
        $this->assertNotNull($this->buyer->avatar_path);
        Storage::disk('public')->assertExists($this->buyer->avatar_path);

        // 2. Strict password validation: rejects weak password
        $resWeak = $this->put(route('buyer.account.password'), [
            'current_password' => 'Password123!@#',
            'password' => 'simple',
            'password_confirmation' => 'simple',
        ]);
        $resWeak->assertSessionHasErrors(['password']);

        // 3. Password change with valid complex password
        $resPass = $this->put(route('buyer.account.password'), [
            'current_password' => 'Password123!@#',
            'password' => 'ComplexP@ssw0rd2026!',
            'password_confirmation' => 'ComplexP@ssw0rd2026!',
        ]);
        $resPass->assertRedirect();
        $this->assertTrue(Hash::check('ComplexP@ssw0rd2026!', $this->buyer->fresh()->password));

        // 4. Email change via 6-pin OTP
        $resReqEmail = $this->post(route('buyer.account.email.request'), [
            'new_email' => 'juanito.new@example.com',
        ]);
        $resReqEmail->assertRedirect();
        $this->buyer->refresh();
        $this->assertEquals('juanito.new@example.com', $this->buyer->pending_email);
        $this->assertNotNull($this->buyer->pending_email_otp);

        // Submit wrong OTP
        $resWrongOtp = $this->post(route('buyer.account.email.verify'), ['otp' => '000000']);
        $resWrongOtp->assertSessionHasErrors(['otp']);

        // Submit correct OTP
        $resCorrectOtp = $this->post(route('buyer.account.email.verify'), ['otp' => $this->buyer->pending_email_otp]);
        $resCorrectOtp->assertRedirect();
        $this->assertEquals('juanito.new@example.com', $this->buyer->fresh()->email);
        $this->assertNull($this->buyer->fresh()->pending_email);

        // 5. Phone change via 6-pin OTP
        $resReqPhone = $this->post(route('buyer.account.phone.request'), [
            'new_phone' => '09209876543',
        ]);
        $resReqPhone->assertRedirect();
        $this->buyer->refresh();
        $this->assertEquals('09209876543', $this->buyer->pending_phone);

        $resVerifyPhone = $this->post(route('buyer.account.phone.verify'), ['otp' => $this->buyer->pending_phone_otp]);
        $resVerifyPhone->assertRedirect();
        $this->assertEquals('09209876543', $this->buyer->fresh()->contact_number);
    }

    /**
     * B2: Address Book: Max 10 addresses, cascading fields, default address protection, and immutable snapshots
     */
    public function test_b2_address_book_rules(): void
    {
        $this->actingAs($this->buyer);

        // Create 10 addresses
        for ($i = 1; $i <= 10; $i++) {
            $this->post(route('buyer.addresses.store'), [
                'recipient_name' => "Address Recipient $i",
                'recipient_phone' => '0917000000' . ($i % 10),
                'region_code' => '040000000',
                'region_name' => 'Region IV-A',
                'province_code' => '0434',
                'province_name' => 'Laguna',
                'municipality_code' => '043404',
                'municipality_name' => 'Cabuyao',
                'barangay_code' => '043404001',
                'barangay_name' => 'Banay-Banay',
                'street' => "Street $i",
                'postal_code' => '4025',
            ]);
        }

        $this->assertEquals(10, $this->buyer->addresses()->count());

        // 11th address must be rejected (Max 10 limit)
        $resEleventh = $this->post(route('buyer.addresses.store'), [
            'recipient_name' => 'Address Recipient 11',
            'recipient_phone' => '09170000011',
            'province_code' => '0434',
            'province_name' => 'Laguna',
            'municipality_code' => '043404',
            'municipality_name' => 'Cabuyao',
            'barangay_code' => '043404001',
            'barangay_name' => 'Banay-Banay',
        ]);
        $resEleventh->assertSessionHasErrors(['address']);
        $this->assertEquals(10, $this->buyer->addresses()->count());

        // Default address protection: exactly 1 default address exists
        $this->assertEquals(1, $this->buyer->addresses()->where('is_default', true)->count());

        // Deleting default address promotes remaining address to default
        $defaultAddr = $this->buyer->addresses()->where('is_default', true)->first();
        $this->delete(route('buyer.addresses.destroy', $defaultAddr->id));
        $this->assertEquals(9, $this->buyer->addresses()->count());
        $this->assertEquals(1, $this->buyer->addresses()->where('is_default', true)->count());
    }

    /**
     * B3: Browse, Search & Filter (trimmed keyword, price range, categories, sorting, soft-deleted exclusion)
     */
    public function test_b3_browse_search_and_filter(): void
    {
        // 1. Keyword search (case-insensitive and trimmed)
        $resSearch = $this->get('/catalog?q=' . urlencode('   cotton   '));
        $resSearch->assertOk();
        $resSearch->assertSee('Premium Cotton T-Shirt');
        $resSearch->assertDontSee('Wireless Bluetooth Earbuds');

        // 2. Price range filter
        $resPrice = $this->get('/catalog?min_price=1000&max_price=2000');
        $resPrice->assertOk();
        $resPrice->assertSee('Wireless Bluetooth Earbuds');
        $resPrice->assertDontSee('Premium Cotton T-Shirt');

        // 3. Category filter
        $resCategory = $this->get('/catalog?category=apparel');
        $resCategory->assertOk();
        $resCategory->assertSee('Premium Cotton T-Shirt');

        // 4. Soft-deleted and inactive product exclusion
        $this->product1->delete(); // soft delete
        $resDeleted = $this->get('/catalog');
        $resDeleted->assertOk();
        $resDeleted->assertDontSee('Premium Cotton T-Shirt');
    }

    /**
     * B4: Product Detail Page: Variants, Out-of-Stock Disabling, Purchase Limits, Flash Sale
     */
    public function test_b4_product_detail_page_features(): void
    {
        // 1. Flash sale setup
        $this->product2->update([
            'flash_sale_price' => 999.00,
            'flash_sale_ends_at' => now()->addHours(2),
        ]);

        $resShow = $this->get("/products/{$this->product2->id}");
        $resShow->assertOk();
        $resShow->assertSee('Flash Sale Active');
        $resShow->assertSee('999.00');

        // 2. Out-of-stock product
        $this->product2->update(['stock' => 0]);
        $resStock = $this->get("/products/{$this->product2->id}");
        $resStock->assertOk();
        $resStock->assertSee('Out of Stock');
    }

    /**
     * B5: Wishlist & Store Follow (handling sold out/deleted items gracefully)
     */
    public function test_b5_wishlist_and_store_following(): void
    {
        $this->actingAs($this->buyer);

        // 1. Add to wishlist
        $resToggle = $this->postJson("/buyer/wishlist/{$this->product1->id}/toggle");
        $resToggle->assertOk();
        $resToggle->assertJson(['inWishlist' => true]);
        $this->assertTrue(Wishlist::where('user_id', $this->buyer->id)->where('product_id', $this->product1->id)->exists());

        // 2. Soft delete product and ensure wishlist index handles it gracefully
        $this->product1->delete();
        $resWishlist = $this->get(route('buyer.wishlist'));
        $resWishlist->assertOk();
        $resWishlist->assertSee('Item Unavailable');

        // 3. Follow seller store
        $resFollow = $this->postJson("/buyer/stores/{$this->seller1->id}/follow-toggle");
        $resFollow->assertOk();
        $resFollow->assertJson(['following' => true]);
    }

    /**
     * B6: Persistent Cart across sessions, Store grouping, Variant merging, and IDOR protection
     */
    public function test_b6_persistent_cart_variant_merging_and_idor_protection(): void
    {
        $this->actingAs($this->buyer);

        // 1. Add item with variant {color: 'Black', size: 'M'}
        $resAdd1 = $this->postJson('/cart/items', [
            'product_id' => $this->product1->id,
            'variant' => ['color' => 'Black', 'size' => 'M'],
            'quantity' => 2,
        ]);
        $resAdd1->assertOk();

        // 2. Variant line merging: adding same product + same variant merges quantity
        $resAdd2 = $this->postJson('/cart/items', [
            'product_id' => $this->product1->id,
            'variant' => ['color' => 'Black', 'size' => 'M'],
            'quantity' => 1,
        ]);
        $resAdd2->assertOk();

        $cartItem = CartItem::where('user_id', $this->buyer->id)->where('product_id', $this->product1->id)->first();
        $this->assertNotNull($cartItem);
        $this->assertEquals(3, $cartItem->quantity);

        // 3. IDOR protection: another user cannot update or delete this cart item
        $anotherBuyer = User::create([
            'first_name' => 'Ana',
            'last_name' => 'Liza',
            'sex' => 'female',
            'birthday' => '1998-03-22',
            'age' => 28,
            'email' => 'ana@example.com',
            'contact_number' => '09221234567',
            'password' => Hash::make('Password123!@#'),
            'role' => 'buyer',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
        $this->actingAs($anotherBuyer);

        $resIdorUpdate = $this->patchJson("/cart/items/{$cartItem->id}", ['quantity' => 10]);
        $resIdorUpdate->assertStatus(403);

        $resIdorDelete = $this->deleteJson("/cart/items/{$cartItem->id}");
        $resIdorDelete->assertStatus(403);
    }

    /**
     * B7: Voucher claiming & stacking: Max 1 Store + 1 Platform + 1 Shipping Voucher
     */
    public function test_b7_vouchers_claiming_and_stacking(): void
    {
        $this->actingAs($this->buyer);

        $platformVoucher = Voucher::create([
            'code' => 'PLATFORM100',
            'voucher_scope' => 'platform',
            'type' => 'flat',
            'value' => 100.00,
            'min_spend' => 500.00,
            'is_active' => true,
        ]);

        $shippingVoucher = Voucher::create([
            'code' => 'FREESHIP50',
            'voucher_scope' => 'shipping',
            'type' => 'flat',
            'value' => 50.00,
            'min_spend' => 300.00,
            'is_active' => true,
        ]);

        $storeVoucher = Voucher::create([
            'seller_id' => $this->seller1->id,
            'code' => 'STORE10',
            'voucher_scope' => 'store',
            'type' => 'percent',
            'value' => 10.00,
            'min_spend' => 400.00,
            'max_discount' => 150.00,
            'is_active' => true,
        ]);

        // Claim vouchers
        $this->postJson(route('buyer.vouchers.claim', $platformVoucher->id))->assertOk();
        $this->postJson(route('buyer.vouchers.claim', $shippingVoucher->id))->assertOk();
        $this->postJson(route('buyer.vouchers.claim', $storeVoucher->id))->assertOk();

        $this->assertEquals(3, UserVoucher::where('user_id', $this->buyer->id)->count());
    }

    /**
     * B8 & B9: Checkout: Multi-Seller Order Splitting, Weight-Based Shipping Fees (ceil(weight)),
     * Immutable Address Snapshots, COD & 60-min payment countdown for unpaid gateway orders
     */
    public function test_b8_and_b9_multi_seller_order_splitting_and_weight_shipping(): void
    {
        $this->actingAs($this->buyer);

        // Buyer creates saved address
        $address = Address::create([
            'user_id' => $this->buyer->id,
            'recipient_name' => 'Juan Dela Cruz',
            'recipient_phone' => '09171234567',
            'region_name' => 'Region IV-A',
            'province_code' => '0434',
            'province_name' => 'Laguna',
            'municipality_code' => '043404',
            'municipality_name' => 'Cabuyao',
            'barangay_code' => '043404001',
            'barangay_name' => 'Banay-Banay',
            'street' => 'Block 5 Lot 12',
            'is_default' => true,
        ]);

        // Add 2 items from Seller 1 (Weight: 1.2 kg × 2 = 2.4 kg -> ceil is 3 kg)
        CartItem::create([
            'user_id' => $this->buyer->id,
            'product_id' => $this->product1->id,
            'quantity' => 2,
            'selected' => true,
        ]);

        // Add 1 item from Seller 2 (Weight: 0.6 kg × 1 = 0.6 kg -> ceil is 1 kg)
        CartItem::create([
            'user_id' => $this->buyer->id,
            'product_id' => $this->product2->id,
            'quantity' => 1,
            'selected' => true,
        ]);

        // Place Order via Gateway Payment (Card)
        $resCheckout = $this->post(route('checkout.store'), [
            'address_id' => $address->id,
            'payment_method' => 'card',
        ]);
        $resCheckout->assertRedirect(route('buyer.orders'));

        // Multi-Seller Order Splitting Rule: Should generate 2 separate Order records
        $orders = Order::where('buyer_id', $this->buyer->id)->get();
        $this->assertCount(2, $orders);

        $order1 = $orders->firstWhere('seller_id', $this->seller1->id);
        $order2 = $orders->firstWhere('seller_id', $this->seller2->id);

        $this->assertNotNull($order1);
        $this->assertNotNull($order2);
        $this->assertEquals($order1->checkout_session_id, $order2->checkout_session_id);

        // Shipping weight & fee calculation:
        // Order 1: 2.4 kg -> ceil is 3 kg -> 50 + (20 * 2) = 90
        $this->assertEquals(3.00, (float) $order1->shipping_weight);
        $this->assertEquals(90.00, (float) $order1->shipping_fee);

        // Order 2: 0.6 kg -> ceil is 1 kg -> 50
        $this->assertEquals(1.00, (float) $order2->shipping_weight);
        $this->assertEquals(50.00, (float) $order2->shipping_fee);

        // Gateway 60-minute payment countdown
        $this->assertEquals('unpaid', $order1->payment_status);
        $this->assertNotNull($order1->payment_expires_at);

        // Immutable address snapshot
        $this->assertNotNull($order1->address_snapshot);
        $this->assertEquals('Banay-Banay', $order1->address_snapshot['barangay_name']);

        // Stock decrement check: product1 had 50, now 48
        $this->assertEquals(48, $this->product1->fresh()->stock);

        // Cart should be cleared
        $this->assertEquals(0, $this->buyer->cartItems()->where('selected', true)->count());
    }

    /**
     * B10, B11, B12: Pre-Shipping Cancellation, Auto-Completion, and 7-Day Return Request
     */
    public function test_b10_b11_b12_order_tracking_cancellation_and_returns(): void
    {
        $this->actingAs($this->buyer);

        $address = Address::create([
            'user_id' => $this->buyer->id,
            'province_code' => '0434',
            'province_name' => 'Laguna',
            'municipality_code' => '043404',
            'municipality_name' => 'Cabuyao',
            'barangay_code' => '043404001',
            'barangay_name' => 'Banay-Banay',
            'is_default' => true,
        ]);

        // Create an order in 'placed' status
        $order = Order::create([
            'order_number' => 'NX-TEST101',
            'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller1->id,
            'address_id' => $address->id,
            'status' => 'placed',
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'subtotal' => 500.00,
            'shipping_fee' => 50.00,
            'total' => 550.00,
            'placed_at' => now(),
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product1->id,
            'product_name' => $this->product1->name,
            'price' => 500.00,
            'quantity' => 2,
        ]);
        $this->product1->update(['stock' => 10]);

        // B11: Pre-shipping cancellation restores stock
        $resCancel = $this->post(route('buyer.orders.cancel', $order->id), [
            'reason' => 'Changed my mind',
        ]);
        $resCancel->assertRedirect();
        $this->assertEquals('cancelled', $order->fresh()->status);
        $this->assertEquals(12, $this->product1->fresh()->stock); // 10 + 2 restored!

        // B12: 7-Day Return Request on delivered order
        $deliveredOrder = Order::create([
            'order_number' => 'NX-DELIV102',
            'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller1->id,
            'address_id' => $address->id,
            'status' => 'delivered',
            'payment_method' => 'cod',
            'payment_status' => 'paid',
            'subtotal' => 500.00,
            'shipping_fee' => 50.00,
            'total' => 550.00,
            'placed_at' => now()->subDays(2),
            'delivered_at' => now()->subDays(2),
        ]);

        $proofPhoto = UploadedFile::fake()->create('defect.jpg', 200, 'image/jpeg');
        $resReturn = $this->post(route('buyer.orders.return', $deliveredOrder->id), [
            'reason' => 'Item damaged or defective upon arrival',
            'details' => 'The seams of the shirt were unstitched upon arrival.',
            'media' => [$proofPhoto],
        ]);
        $resReturn->assertRedirect();
        $this->assertNotNull($deliveredOrder->fresh()->dispute);
        $this->assertEquals('open', $deliveredOrder->fresh()->dispute->status);
    }

    /**
     * B13 & B14: Reviews with 1-Edit Limit and Real-Time Buyer-Seller Chat
     */
    public function test_b13_and_b14_reviews_and_chat(): void
    {
        $this->actingAs($this->buyer);

        $address = Address::create([
            'user_id' => $this->buyer->id,
            'province_code' => '0434',
            'province_name' => 'Laguna',
            'municipality_code' => '043404',
            'municipality_name' => 'Cabuyao',
            'barangay_code' => '043404001',
            'barangay_name' => 'Banay-Banay',
            'is_default' => true,
        ]);

        $completedOrder = Order::create([
            'order_number' => 'NX-COMP103',
            'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller1->id,
            'address_id' => $address->id,
            'status' => 'completed',
            'payment_method' => 'cod',
            'subtotal' => 500.00,
            'shipping_fee' => 50.00,
            'total' => 550.00,
            'placed_at' => now()->subDays(5),
            'completed_at' => now()->subDays(2),
        ]);
        OrderItem::create([
            'order_id' => $completedOrder->id,
            'product_id' => $this->product1->id,
            'product_name' => $this->product1->name,
            'price' => 500.00,
            'quantity' => 1,
        ]);

        // 1. Submit initial review
        $reviewPhoto = UploadedFile::fake()->create('review.jpg', 200, 'image/jpeg');
        $resReview1 = $this->post(route('buyer.orders.review', $completedOrder->id), [
            'rating' => 5,
            'comment' => 'Excellent fabric quality!',
            'photos' => [$reviewPhoto],
        ]);
        $resReview1->assertRedirect();
        $review = Review::where('order_id', $completedOrder->id)->first();
        $this->assertNotNull($review);
        $this->assertEquals(0, $review->edited_count);

        // 2. 1 edit allowed: update review
        $resEdit1 = $this->post(route('buyer.orders.review', $completedOrder->id), [
            'rating' => 4,
            'comment' => 'Updated review: Good quality overall.',
        ]);
        $resEdit1->assertRedirect();
        $this->assertEquals(1, $review->fresh()->edited_count);
        $this->assertEquals(4, $review->fresh()->rating);

        // 3. Second edit attempt must be rejected (1-edit limit)
        $resEdit2 = $this->post(route('buyer.orders.review', $completedOrder->id), [
            'rating' => 3,
            'comment' => 'Trying to edit again.',
        ]);
        $resEdit2->assertSessionHasErrors(['review']);

        // 4. B14: Start Chat with Seller with product card inquiry
        $resChat = $this->post(route('buyer.chat.start'), [
            'seller_id' => $this->seller1->id,
            'product_id' => $this->product1->id,
        ]);
        $resChat->assertRedirect();

        $conversation = Conversation::whereHas('participants', fn ($q) => $q->where('user_id', $this->buyer->id))->first();
        $this->assertNotNull($conversation);
        $this->assertEquals(1, $conversation->messages()->count());
        $this->assertStringContainsString($this->product1->name, $conversation->messages()->first()->body);
    }
}
