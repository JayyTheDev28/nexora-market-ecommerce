<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Category;
use App\Models\Conversation;
use App\Models\Dispute;
use App\Models\Message;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\SellerPayoutRequest;
use App\Models\SellerProfile;
use App\Models\StoreCategory;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SellerModuleIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $seller;
    protected SellerProfile $profile;
    protected User $buyer;
    protected Category $platformCategory;
    protected Address $address;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seller = User::factory()->create([
            'role' => 'seller',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $this->profile = SellerProfile::create([
            'user_id' => $this->seller->id,
            'business_name' => 'Artisan Craft Workshop',
            'category' => 'Crafts',
            'description' => 'Handmade premium goods.',
            'contact_email' => 'contact@artisancraft.com',
            'contact_phone' => '09171234567',
        ]);

        $this->buyer = User::factory()->create([
            'role' => 'buyer',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $this->address = Address::create([
            'user_id' => $this->buyer->id,
            'province_code' => '043400000',
            'province_name' => 'Laguna',
            'municipality_code' => '043426000',
            'municipality_name' => 'Santa Cruz',
            'barangay_code' => '043426001',
            'barangay_name' => 'Poblacion I',
            'street' => '456 Market St',
            'house_number' => 'Building B',
            'recipient_name' => $this->buyer->full_name,
            'recipient_phone' => '09179876543',
            'is_default' => true,
        ]);

        $this->platformCategory = Category::firstOrCreate(
            ['slug' => 'home-living'],
            ['name' => 'Home & Living']
        );
        $this->seller->allowedCategories()->attach($this->platformCategory->id);
    }

    public function test_seller_can_update_store_profile_and_toggle_vacation_mode(): void
    {
        $response = $this->actingAs($this->seller)->put(route('seller.store.update'), [
            'business_name' => 'Artisan Studio Manila',
            'description' => 'Updated curated collection.',
            'contact_email' => 'studio@artisanmanila.ph',
            'contact_phone' => '09189998888',
        ]);

        $response->assertRedirect(route('seller.store.edit'));
        $this->assertDatabaseHas('seller_profiles', [
            'user_id' => $this->seller->id,
            'business_name' => 'Artisan Studio Manila',
            'description' => 'Updated curated collection.',
            'contact_email' => 'studio@artisanmanila.ph',
            'contact_phone' => '09189998888',
        ]);

        // Toggle vacation mode ON
        $vacationResponse = $this->actingAs($this->seller)->post(route('seller.store.vacation'), [
            'is_vacation_mode' => '1',
            'vacation_notice' => 'We are taking a seasonal break! Back on Monday.',
        ]);

        $vacationResponse->assertRedirect(route('seller.store.edit'));
        $this->profile->refresh();
        $this->assertTrue($this->profile->is_vacation_mode);
        $this->assertEquals('We are taking a seasonal break! Back on Monday.', $this->profile->vacation_notice);

        // Check public storefront displays store info and vacation notice
        $storefront = $this->get(route('stores.show', $this->seller));
        $storefront->assertStatus(200);
        $storefront->assertSee('Artisan Studio Manila');
        $storefront->assertSee('Store is Currently on Vacation');
        $storefront->assertSee('We are taking a seasonal break! Back on Monday.');
    }

    public function test_vacation_mode_blocks_buyers_from_adding_products_to_cart(): void
    {
        $product = Product::create([
            'seller_id' => $this->seller->id,
            'category_id' => $this->platformCategory->id,
            'name' => 'Ceramic Mug',
            'slug' => 'ceramic-mug',
            'price' => 250,
            'stock' => 20,
            'status' => 'active',
        ]);

        // Put store on vacation
        $this->profile->update([
            'is_vacation_mode' => true,
            'vacation_notice' => 'Store on vacation.',
        ]);

        $cartResponse = $this->actingAs($this->buyer)->postJson(route('cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $cartResponse->assertStatus(422);
        $cartResponse->assertJsonFragment([
            'error' => 'Artisan Craft Workshop is currently on vacation mode and not accepting new orders.',
        ]);

        // Turn vacation mode off
        $this->profile->update([
            'is_vacation_mode' => false,
        ]);

        $cartResponse2 = $this->actingAs($this->buyer)->postJson(route('cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $cartResponse2->assertStatus(200);
        $cartResponse2->assertJson(['success' => true]);
    }

    public function test_seller_can_manage_store_categories_and_safe_delete(): void
    {
        // 1. Create Parent Category
        $parentResponse = $this->actingAs($this->seller)->post(route('seller.categories.store'), [
            'name' => 'Pottery & Ceramics',
            'order_index' => 1,
            'is_visible' => 1,
        ]);
        $parentResponse->assertRedirect(route('seller.categories.index'));

        $parent = StoreCategory::where('seller_id', $this->seller->id)->where('name', 'Pottery & Ceramics')->first();
        $this->assertNotNull($parent);
        $this->assertNull($parent->parent_id);

        // 2. Create Child Category
        $childResponse = $this->actingAs($this->seller)->post(route('seller.categories.store'), [
            'name' => 'Coffee Mugs',
            'parent_id' => $parent->id,
            'order_index' => 2,
            'is_visible' => 1,
        ]);
        $childResponse->assertRedirect(route('seller.categories.index'));

        $child = StoreCategory::where('seller_id', $this->seller->id)->where('name', 'Coffee Mugs')->first();
        $this->assertNotNull($child);
        $this->assertEquals($parent->id, $child->parent_id);

        // 3. Assign to Product
        $product = Product::create([
            'seller_id' => $this->seller->id,
            'category_id' => $this->platformCategory->id,
            'store_category_id' => $child->id,
            'name' => 'Handmade Espresso Cup',
            'slug' => 'handmade-espresso-cup',
            'price' => 300,
            'stock' => 15,
            'status' => 'active',
        ]);

        // 4. Delete Category safely (should unlink product store_category_id)
        $deleteResponse = $this->actingAs($this->seller)->delete(route('seller.categories.destroy', $child));
        $deleteResponse->assertRedirect(route('seller.categories.index'));

        $this->assertDatabaseMissing('store_categories', ['id' => $child->id]);
        $product->refresh();
        $this->assertNull($product->store_category_id);
    }

    public function test_seller_can_create_edit_duplicate_and_archive_product(): void
    {
        // Create product with variants JSON
        $createResponse = $this->actingAs($this->seller)->post(route('seller.products.store'), [
            'name' => 'Linen Cushion Cover',
            'category_id' => $this->platformCategory->id,
            'sku' => 'LINEN-CUSH-01',
            'price' => 450,
            'stock' => 50,
            'low_stock_threshold' => 5,
            'description' => 'Natural organic linen cushion.',
            'action' => 'publish',
            'variants_json' => json_encode([
                ['name' => 'Standard / Cream', 'sku' => 'LINEN-CUSH-CRM', 'price' => 450, 'stock' => 25],
                ['name' => 'Standard / Slate', 'sku' => 'LINEN-CUSH-SLT', 'price' => 480, 'stock' => 25],
            ]),
        ]);

        $createResponse->assertRedirect(route('seller.products.index'));

        $product = Product::where('seller_id', $this->seller->id)->where('name', 'Linen Cushion Cover')->first();
        $this->assertNotNull($product);
        $this->assertEquals('active', $product->status);
        $this->assertEquals(50, $product->stock);
        $this->assertEquals('LINEN-CUSH-01', $product->sku);
        $this->assertCount(2, $product->variants);

        // Edit product
        $editResponse = $this->actingAs($this->seller)->put(route('seller.products.update', $product), [
            'name' => 'Organic Linen Cushion Cover',
            'category_id' => $this->platformCategory->id,
            'sku' => 'LINEN-CUSH-ORG',
            'price' => 499,
            'stock' => 50,
            'low_stock_threshold' => 10,
            'description' => '100% Belgian Flax linen.',
            'action' => 'publish',
        ]);

        $editResponse->assertRedirect(route('seller.products.index'));
        $product->refresh();
        $this->assertEquals('Organic Linen Cushion Cover', $product->name);
        $this->assertEquals(499, $product->price);
        $this->assertEquals(10, $product->low_stock_threshold);

        // Duplicate product
        $dupResponse = $this->actingAs($this->seller)->post(route('seller.products.duplicate', $product));

        $duplicate = Product::where('seller_id', $this->seller->id)
            ->where('name', 'Organic Linen Cushion Cover (Copy)')
            ->first();
        $this->assertNotNull($duplicate);
        $dupResponse->assertRedirect(route('seller.products.edit', $duplicate));
        $this->assertEquals('draft', $duplicate->status);
        $this->assertCount(2, $duplicate->variants);

        // Archive and Activate
        $this->actingAs($this->seller)->post(route('seller.products.archive', $product));
        $product->refresh();
        $this->assertEquals('archived', $product->status);

        $this->actingAs($this->seller)->post(route('seller.products.activate', $product));
        $product->refresh();
        $this->assertEquals('active', $product->status);
    }

    public function test_seller_can_create_product_with_variation_image_and_shows_on_buyer_side(): void
    {
        Storage::fake('public');

        $variantFile = UploadedFile::fake()->create('crimson-variant.jpg', 300, 'image/jpeg');

        $response = $this->actingAs($this->seller)->post(route('seller.products.store'), [
            'name' => 'Premium Silk Scarf',
            'category_id' => $this->platformCategory->id,
            'sku' => 'SILK-SCARF-01',
            'price' => 750,
            'stock' => 30,
            'weight' => 0.25,
            'action' => 'publish',
            'variants_json' => json_encode([
                [
                    'row_id' => 'row_101',
                    'name' => 'Crimson Red',
                    'sku' => 'SILK-RED',
                    'price' => 750,
                    'stock' => 15,
                ],
                [
                    'row_id' => 'row_102',
                    'name' => 'Navy Blue',
                    'sku' => 'SILK-BLU',
                    'price' => 790,
                    'stock' => 15,
                ],
            ]),
            'variant_images' => [
                'row_101' => $variantFile,
            ],
        ]);

        $response->assertRedirect(route('seller.products.index'));

        $product = Product::where('seller_id', $this->seller->id)->where('name', 'Premium Silk Scarf')->first();
        $this->assertNotNull($product);
        $this->assertCount(2, $product->variants);

        // Assert Crimson Red has stored image
        $crimson = $product->variants[0];
        $this->assertEquals('Crimson Red', $crimson['name']);
        $this->assertNotEmpty($crimson['image']);
        $this->assertStringContainsString('products/variants/', $crimson['image']);

        // Assert variation image is in gallery
        $this->assertContains($crimson['image'], $product->gallery);

        // Assert Navy Blue has no image
        $navy = $product->variants[1];
        $this->assertEmpty($navy['image'] ?? null);

        // Buyer side test: product detail page displays variation data with image
        $buyerResponse = $this->actingAs($this->buyer)->get(route('products.show', $product->id));
        $buyerResponse->assertOk();
        $buyerResponse->assertSee('Crimson Red');
        $this->assertStringContainsString(basename($crimson['image']), $buyerResponse->getContent());

        // Test helper method getVariantImageUrl
        $this->assertEquals($crimson['image'], $product->getVariantImageUrl(['variation' => 'Crimson Red']));
    }

    public function test_seller_cannot_delete_product_with_active_orders(): void
    {
        $product = Product::create([
            'seller_id' => $this->seller->id,
            'category_id' => $this->platformCategory->id,
            'name' => 'Handwoven Basket',
            'slug' => 'handwoven-basket',
            'price' => 800,
            'stock' => 10,
            'status' => 'active',
        ]);

        $order = Order::create([
            'order_number' => 'NEX-ORD-DELTEST-1',
            'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'address_id' => $this->address->id,
            'subtotal' => 800,
            'shipping_fee' => 100,
            'total' => 900,
            'payment_method' => 'cod',
            'status' => 'placed',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => 800,
            'quantity' => 1,
        ]);

        // Attempting to delete should be blocked
        $deleteResponse = $this->actingAs($this->seller)->delete(route('seller.products.destroy', $product));
        $deleteResponse->assertSessionHasErrors('product');
        $this->assertDatabaseHas('products', ['id' => $product->id]);

        // Complete the order
        $order->update(['status' => 'delivered']);

        // Now deletion should succeed
        $deleteSuccess = $this->actingAs($this->seller)->delete(route('seller.products.destroy', $product));
        $deleteSuccess->assertRedirect(route('seller.products.index'));
        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_seller_can_adjust_inventory_and_track_low_stock(): void
    {
        $product = Product::create([
            'seller_id' => $this->seller->id,
            'category_id' => $this->platformCategory->id,
            'name' => 'Bamboo Cutting Board',
            'slug' => 'bamboo-cutting-board',
            'price' => 350,
            'stock' => 10,
            'low_stock_threshold' => 5,
            'status' => 'active',
        ]);

        // Add 15 to stock
        $this->actingAs($this->seller)->post(route('seller.inventory.adjust', $product), [
            'adjustment_type' => 'add',
            'quantity' => 15,
            'reason' => 'Restocked batch #102',
        ]);
        $product->refresh();
        $this->assertEquals(25, $product->stock);

        // Subtract 22 from stock
        $this->actingAs($this->seller)->post(route('seller.inventory.adjust', $product), [
            'adjustment_type' => 'subtract',
            'quantity' => 22,
            'reason' => 'Damaged during transit',
        ]);
        $product->refresh();
        $this->assertEquals(3, $product->stock);
        $this->assertTrue($product->isLowStock());

        // Update low stock threshold
        $this->actingAs($this->seller)->post(route('seller.inventory.adjust', $product), [
            'adjustment_type' => 'set',
            'quantity' => 3,
            'threshold' => 2,
        ]);
        $product->refresh();
        $this->assertEquals(2, $product->low_stock_threshold);
        $this->assertFalse($product->isLowStock());
    }

    public function test_seller_can_create_and_manage_store_vouchers(): void
    {
        $response = $this->actingAs($this->seller)->post(route('seller.vouchers.store'), [
            'code' => 'ARTISAN20',
            'type' => 'percent',
            'value' => 20,
            'min_spend' => 500,
            'max_discount' => 150,
            'usage_limit' => 100,
            'expires_at' => now()->addDays(14)->format('Y-m-d\TH:i'),
        ]);

        $response->assertRedirect(route('seller.vouchers.index'));

        $voucher = Voucher::where('code', 'ARTISAN20')->first();
        $this->assertNotNull($voucher);
        $this->assertEquals($this->seller->id, $voucher->seller_id);
        $this->assertEquals('percent', $voucher->type);
        $this->assertEquals(20, $voucher->value);

        // Buyer claims voucher via JSON API
        $claimResponse = $this->actingAs($this->buyer)->postJson(route('buyer.vouchers.claim', $voucher));
        $claimResponse->assertJson(['success' => true]);

        $this->assertDatabaseHas('user_vouchers', [
            'user_id' => $this->buyer->id,
            'voucher_id' => $voucher->id,
        ]);

        // Seller views claims list
        $claimsView = $this->actingAs($this->seller)->get(route('seller.vouchers.claims', $voucher));
        $claimsView->assertStatus(200);
        $claimsView->assertSee('ARTISAN20');
        $claimsView->assertSee($this->buyer->full_name);
    }

    public function test_seller_can_schedule_flash_sales_and_validate_discount(): void
    {
        $product = Product::create([
            'seller_id' => $this->seller->id,
            'category_id' => $this->platformCategory->id,
            'name' => 'Aromatherapy Diffuser',
            'slug' => 'aromatherapy-diffuser',
            'price' => 1200,
            'stock' => 30,
            'status' => 'active',
        ]);

        // Attempting to set flash sale price higher than regular price should fail
        $invalidResponse = $this->actingAs($this->seller)->post(route('seller.promotions.flash-sale.store'), [
            'product_id' => $product->id,
            'flash_sale_price' => 1300,
            'ends_at' => now()->addDays(3)->format('Y-m-d\TH:i'),
        ]);
        $invalidResponse->assertSessionHasErrors('flash_sale_price');

        // Valid flash sale
        $validResponse = $this->actingAs($this->seller)->post(route('seller.promotions.flash-sale.store'), [
            'product_id' => $product->id,
            'flash_sale_price' => 899,
            'ends_at' => now()->addDays(3)->format('Y-m-d\TH:i'),
        ]);
        $validResponse->assertRedirect(route('seller.promotions.index'));

        $product->refresh();
        $this->assertEquals(899, $product->flash_sale_price);
        $this->assertNotNull($product->flash_sale_ends_at);

        // End flash sale early
        $endResponse = $this->actingAs($this->seller)->post(route('seller.promotions.flash-sale.end', $product));
        $endResponse->assertRedirect(route('seller.promotions.index'));

        $product->refresh();
        $this->assertNull($product->flash_sale_price);
        $this->assertNull($product->flash_sale_ends_at);
    }

    public function test_seller_can_advance_order_view_waybill_and_cancel_with_stock_restoration(): void
    {
        $product = Product::create([
            'seller_id' => $this->seller->id,
            'category_id' => $this->platformCategory->id,
            'name' => 'Woven Placemat Set',
            'slug' => 'woven-placemat-set',
            'price' => 600,
            'stock' => 10,
            'status' => 'active',
        ]);

        $order = Order::create([
            'order_number' => 'NEX-ORD-WAYBILL-01',
            'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'address_id' => $this->address->id,
            'subtotal' => 1200,
            'shipping_fee' => 150,
            'total' => 1350,
            'payment_method' => 'cod',
            'status' => 'confirmed',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => 600,
            'quantity' => 2,
        ]);

        // Advance to preparing
        $advanceResponse = $this->actingAs($this->seller)->post(route('seller.orders.advance', $order), [
            'status' => 'preparing',
        ]);
        $advanceResponse->assertRedirect();
        $order->refresh();
        $this->assertEquals('preparing', $order->status);

        // View waybill
        $waybillResponse = $this->actingAs($this->seller)->get(route('seller.orders.waybill', $order));
        $waybillResponse->assertStatus(200);
        $waybillResponse->assertSee($order->order_number);
        $waybillResponse->assertSee('Woven Placemat Set');

        // Cancel order and verify stock is restored
        $cancelResponse = $this->actingAs($this->seller)->post(route('seller.orders.cancel', $order), [
            'reason' => 'Material out of stock.',
        ]);
        $cancelResponse->assertRedirect();
        $order->refresh();
        $this->assertEquals('cancelled', $order->status);
        $this->assertStringContainsString('Material out of stock.', $order->cancellation_reason);

        $product->refresh();
        // Started with 10, order had 2, cancellation added 2 back -> 12
        $this->assertEquals(12, $product->stock);
    }

    public function test_seller_can_arbitrate_returns_with_instructions_and_stock_restoration(): void
    {
        $product = Product::create([
            'seller_id' => $this->seller->id,
            'category_id' => $this->platformCategory->id,
            'name' => 'Terracotta Planter',
            'slug' => 'terracotta-planter',
            'price' => 500,
            'stock' => 5,
            'status' => 'active',
        ]);

        $order = Order::create([
            'order_number' => 'NEX-ORD-RET-01',
            'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'address_id' => $this->address->id,
            'subtotal' => 500,
            'shipping_fee' => 80,
            'total' => 580,
            'payment_method' => 'cod',
            'status' => 'delivered',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => 500,
            'quantity' => 1,
        ]);

        $dispute = Dispute::create([
            'order_id' => $order->id,
            'raised_by' => $this->buyer->id,
            'reason' => 'damaged_item',
            'details' => 'Slight chip on base rim during shipping.',
            'status' => 'open',
        ]);

        // Approve return with return instructions and restock
        $approveResponse = $this->actingAs($this->seller)->post(route('seller.returns.approve', $dispute), [
            'seller_response' => 'We accept the return. Please pack it securely.',
            'return_instructions' => 'Drop off parcel at J&T branch using Return ID #123.',
            'restore_stock' => 1,
        ]);

        $approveResponse->assertRedirect(route('seller.returns.show', $dispute));
        $dispute->refresh();
        $this->assertEquals('approved', $dispute->seller_status);
        $this->assertEquals('Drop off parcel at J&T branch using Return ID #123.', $dispute->return_instructions);

        $product->refresh();
        // Restocked 1 back: 5 + 1 = 6
        $this->assertEquals(6, $product->stock);

        // Test dispute rejection with reason
        $dispute2 = Dispute::create([
            'order_id' => $order->id,
            'raised_by' => $this->buyer->id,
            'reason' => 'wrong_item',
            'details' => 'Buyer claims wrong color.',
            'status' => 'open',
        ]);

        $rejectResponse = $this->actingAs($this->seller)->post(route('seller.returns.reject', $dispute2), [
            'rejection_reason' => 'Item shipped matches exactly the color selected in order invoice.',
        ]);

        $rejectResponse->assertRedirect(route('seller.returns.show', $dispute2));
        $dispute2->refresh();
        $this->assertEquals('rejected', $dispute2->seller_status);
    }

    public function test_seller_can_reply_to_product_reviews(): void
    {
        $product = Product::create([
            'seller_id' => $this->seller->id,
            'category_id' => $this->platformCategory->id,
            'name' => 'Handmade Leather Journal',
            'slug' => 'handmade-leather-journal',
            'price' => 650,
            'stock' => 15,
            'status' => 'active',
        ]);

        $order = Order::create([
            'order_number' => 'NEX-ORD-REV-01',
            'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'address_id' => $this->address->id,
            'subtotal' => 650,
            'shipping_fee' => 50,
            'total' => 700,
            'payment_method' => 'cod',
            'status' => 'delivered',
        ]);

        $review = Review::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'buyer_id' => $this->buyer->id,
            'rating' => 5,
            'comment' => 'Exquisite craftsmanship, the leather feels amazing!',
        ]);

        // Seller replies to review
        $replyResponse = $this->actingAs($this->seller)->post(route('seller.feedback.reply', $review), [
            'reply' => 'Thank you for supporting handcrafted artisans! Enjoy your journal.',
        ]);

        $replyResponse->assertRedirect(route('seller.feedback.index'));

        $review->refresh();
        $this->assertEquals('Thank you for supporting handcrafted artisans! Enjoy your journal.', $review->seller_reply);
        $this->assertNotNull($review->seller_replied_at);

        // Buyer viewing product show page sees seller reply
        $buyerShow = $this->get(route('products.show', $product->id));
        $buyerShow->assertStatus(200);
        $buyerShow->assertSee('Seller Response');
        $buyerShow->assertSee('Thank you for supporting handcrafted artisans! Enjoy your journal.');
    }

    public function test_seller_can_chat_with_buyers(): void
    {
        $conversation = Conversation::create([
            'subject' => 'Customization question',
            'last_message_at' => now(),
        ]);
        $conversation->participants()->attach([$this->seller->id, $this->buyer->id]);

        $response = $this->actingAs($this->seller)->post(route('seller.chat.message', $conversation), [
            'message' => 'Hello! Your customized engraving is now ready and will ship today.',
        ]);

        $response->assertRedirect(route('seller.chat.index', ['conversation' => $conversation->id]));

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $this->seller->id,
            'body' => 'Hello! Your customized engraving is now ready and will ship today.',
        ]);
    }

    public function test_seller_reports_and_payout_requests(): void
    {
        $product = Product::create([
            'seller_id' => $this->seller->id,
            'category_id' => $this->platformCategory->id,
            'name' => 'Artisan Ceramic Vase',
            'slug' => 'artisan-ceramic-vase',
            'price' => 1000,
            'stock' => 5,
            'status' => 'active',
        ]);

        // Create completed order with 1000 subtotal (Net 90% is 900)
        $order = Order::create([
            'order_number' => 'NEX-ORD-PAYOUT-01',
            'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'address_id' => $this->address->id,
            'subtotal' => 1000,
            'shipping_fee' => 100,
            'total' => 1100,
            'payment_method' => 'cod',
            'status' => 'completed',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => 1000,
            'quantity' => 1,
        ]);

        $reportView = $this->actingAs($this->seller)->get(route('seller.reports.index'));
        $reportView->assertStatus(200);
        $reportView->assertSee('Finances & Payouts');
        $reportView->assertSee('900.00'); // Available balance: 1000 * 0.90

        // Payout request exceeding available balance should fail
        $excessiveResponse = $this->actingAs($this->seller)->post(route('seller.payouts.request'), [
            'amount' => 1500,
            'payout_method' => 'gcash',
            'account_name' => 'Artisan Workshop',
            'account_number' => '09171234567',
        ]);
        $excessiveResponse->assertSessionHasErrors('amount');

        // Valid payout request (e.g. 700)
        $validPayoutResponse = $this->actingAs($this->seller)->post(route('seller.payouts.request'), [
            'amount' => 700,
            'payout_method' => 'gcash',
            'account_name' => 'Artisan Workshop',
            'account_number' => '09171234567',
        ]);
        $validPayoutResponse->assertRedirect(route('seller.reports.index'));

        $this->assertDatabaseHas('seller_payout_requests', [
            'seller_id' => $this->seller->id,
            'amount' => 700,
            'payout_method' => 'gcash',
            'status' => 'pending',
        ]);
    }

    public function test_seller_registration_with_multiple_online_shopping_categories(): void
    {
        Storage::fake('public');

        $catElectronics = Category::firstOrCreate(['slug' => 'electronics'], ['name' => 'Electronics', 'icon' => 'devices']);
        $catFashion = Category::firstOrCreate(['slug' => 'fashion'], ['name' => 'Fashion & Apparel', 'icon' => 'checkroom']);
        $catGroceries = Category::firstOrCreate(['slug' => 'groceries'], ['name' => 'Groceries & Food', 'icon' => 'local_grocery_store']);

        $response = $this->post(route('register.store'), [
            'role' => 'seller',
            'first_name' => 'Marco',
            'last_name' => 'Valdez',
            'sex' => 'male',
            'birthday' => '1995-05-15',
            'email' => 'marco.seller@nexusph.test',
            'contact_number' => '09171112233',
            'password' => 'SecurePass123!@#',
            'password_confirmation' => 'SecurePass123!@#',
            'province' => '043400000',
            'province_name' => 'Laguna',
            'municipality' => '043426000',
            'municipality_name' => 'Santa Cruz',
            'barangay' => '043426001',
            'barangay_name' => 'Poblacion I',
            'business_name' => 'Nexus Multi-Store Enterprise',
            'categories' => [$catElectronics->id, $catFashion->id, $catGroceries->id],
            'valid_id' => UploadedFile::fake()->create('id.jpg', 200, 'image/jpeg'),
            'business_permit' => UploadedFile::fake()->create('permit.pdf', 300, 'application/pdf'),
            'terms' => '1',
        ]);

        $response->assertRedirect(route('verification.notice'));

        $newSeller = User::where('email', 'marco.seller@nexusph.test')->first();
        $this->assertNotNull($newSeller);
        $this->assertEquals('seller', $newSeller->role);
        $this->assertNotNull($newSeller->sellerProfile);
        $this->assertEquals('Nexus Multi-Store Enterprise', $newSeller->sellerProfile->business_name);

        // Verify category_seller pivot table
        $this->assertCount(3, $newSeller->allowedCategories);
        $this->assertTrue($newSeller->allowedCategories->contains('id', $catElectronics->id));
        $this->assertTrue($newSeller->allowedCategories->contains('id', $catFashion->id));
        $this->assertTrue($newSeller->allowedCategories->contains('id', $catGroceries->id));
    }

    public function test_seller_can_update_shop_categories_and_product_upload_is_strictly_filtered(): void
    {
        // Setup 6 categories
        $c1 = Category::firstOrCreate(['slug' => 'cat-1'], ['name' => 'Category 1']);
        $c2 = Category::firstOrCreate(['slug' => 'cat-2'], ['name' => 'Category 2']);
        $c3 = Category::firstOrCreate(['slug' => 'cat-3'], ['name' => 'Category 3']);
        $c4 = Category::firstOrCreate(['slug' => 'cat-4'], ['name' => 'Category 4']);
        $c5 = Category::firstOrCreate(['slug' => 'cat-5'], ['name' => 'Category 5']);
        $unauthorizedCat = Category::firstOrCreate(['slug' => 'cat-unauth'], ['name' => 'Unauthorized Category']);

        // Seller updates their store categories to have 5 categories
        $updateResponse = $this->actingAs($this->seller)->put(route('seller.store.update'), [
            'business_name' => 'Artisan Craft Workshop',
            'categories' => [$c1->id, $c2->id, $c3->id, $c4->id, $c5->id],
        ]);
        $updateResponse->assertRedirect(route('seller.store.edit'));

        $this->seller->refresh();
        $this->assertCount(5, $this->seller->allowedCategories);

        // Check Product create page only presents the 5 enabled categories
        $createView = $this->actingAs($this->seller)->get(route('seller.products.create'));
        $createView->assertStatus(200);
        $createView->assertSee('Category 1');
        $createView->assertSee('Category 5');
        $createView->assertDontSee('Unauthorized Category');

        // Seller creates a product under an allowed category -> SUCCESS
        $validProductResponse = $this->actingAs($this->seller)->post(route('seller.products.store'), [
            'name' => 'Permitted Artisan Item',
            'category_id' => $c2->id,
            'price' => 350,
            'stock' => 10,
            'action' => 'publish',
        ]);
        $validProductResponse->assertRedirect(route('seller.products.index'));
        $this->assertDatabaseHas('products', [
            'name' => 'Permitted Artisan Item',
            'category_id' => $c2->id,
            'seller_id' => $this->seller->id,
        ]);

        // Seller tries to create a product in an unauthorized category -> BLOCKED
        $blockedResponse = $this->actingAs($this->seller)->post(route('seller.products.store'), [
            'name' => 'Prohibited Category Item',
            'category_id' => $unauthorizedCat->id,
            'price' => 500,
            'stock' => 5,
            'action' => 'publish',
        ]);
        $blockedResponse->assertSessionHasErrors('category_id');
        $this->assertDatabaseMissing('products', [
            'name' => 'Prohibited Category Item',
        ]);
    }
}
