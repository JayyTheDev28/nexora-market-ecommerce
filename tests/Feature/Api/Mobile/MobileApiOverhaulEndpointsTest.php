<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\Address;
use App\Models\Category;
use App\Models\CourierProfile;
use App\Models\Dispute;
use App\Models\Hub;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\SellerProfile;
use App\Models\TransferManifest;
use App\Models\User;
use App\Models\Voucher;
use App\Notifications\OrderStatusUpdated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileApiOverhaulEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_home_catalog_filters_and_paginated_reviews_use_real_database_records(): void
    {
        $category = Category::create([
            'name' => 'Audio Gear',
            'slug' => 'audio-gear',
            'description' => 'Real headphones and mics',
        ]);

        $seller = User::factory()->create([
            'role' => 'seller',
            'approval_status' => 'approved',
            'account_status' => 'active',
        ]);

        SellerProfile::create([
            'user_id' => $seller->id,
            'business_name' => 'Laguna Sound Hub',
            'category' => 'Electronics',
            'is_vacation_mode' => false,
        ]);

        Address::create([
            'user_id' => $seller->id,
            'province_code' => '043400000',
            'province_name' => 'Laguna',
            'municipality_code' => '043405000',
            'municipality_name' => 'Calamba',
            'barangay_code' => '043405001',
            'barangay_name' => 'Real',
            'street' => 'National Hwy',
            'house_number' => '10',
            'recipient_name' => 'Seller Hub',
            'recipient_phone' => '09170001111',
            'is_default' => true,
        ]);

        $flashProduct = Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'name' => 'Studio Mic X1',
            'slug' => 'studio-mic-x1',
            'description' => 'Condenser microphone',
            'price' => 1500.00,
            'flash_sale_price' => 1199.00,
            'flash_sale_ends_at' => now()->addHours(6),
            'stock' => 12,
            'status' => 'active',
        ]);

        $regularProduct = Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'name' => 'Monitor Stand',
            'slug' => 'monitor-stand',
            'description' => 'Steel desk arm',
            'price' => 650.00,
            'stock' => 8,
            'status' => 'active',
        ]);

        // Empty product reviews -> breakdown ratios must be 0.0 (no hardcoded 0.85/0.12/0.03)
        $emptyReviews = $this->getJson(route('api.mobile.buyer.products.reviews', $regularProduct->id));
        $emptyReviews->assertStatus(200)
            ->assertJsonPath('data.reviews_count', 0)
            ->assertJsonPath('data.breakdown.5.ratio', 0)
            ->assertJsonCount(0, 'data.reviews');

        // Real DB review computes accurate rating & breakdown
        $buyer = User::factory()->create(['role' => 'buyer', 'approval_status' => 'approved', 'account_status' => 'active']);
        $buyerAddr = Address::create([
            'user_id' => $buyer->id,
            'province_code' => '043400000',
            'province_name' => 'Laguna',
            'municipality_code' => '043426000',
            'municipality_name' => 'Santa Cruz',
            'barangay_code' => '043426001',
            'barangay_name' => 'Poblacion I',
            'street' => 'Main St',
            'house_number' => '1',
            'recipient_name' => $buyer->full_name,
            'recipient_phone' => '09171112222',
            'is_default' => true,
        ]);

        $order = Order::create([
            'order_number' => 'NX-REV-' . uniqid(),
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'address_id' => $buyerAddr->id,
            'subtotal' => 1199.00,
            'shipping_fee' => 50.00,
            'total' => 1249.00,
            'payment_method' => 'cod',
            'status' => 'completed',
        ]);

        Review::create([
            'order_id' => $order->id,
            'product_id' => $flashProduct->id,
            'buyer_id' => $buyer->id,
            'rating' => 4,
            'comment' => 'Clear vocal pickup.',
            'edited_count' => 0,
        ]);

        $this->getJson(route('api.mobile.buyer.products.reviews', $flashProduct->id))
            ->assertStatus(200)
            ->assertJsonPath('data.reviews_count', 1)
            ->assertJsonPath('data.breakdown.4.count', 1)
            ->assertJsonPath('data.breakdown.4.ratio', 1)
            ->assertJsonPath('data.reviews.0.comment', 'Clear vocal pickup.');

        // Buyer Home returns real flash sales, categories, and featured stores
        $this->getJson(route('api.mobile.buyer.home'))
            ->assertStatus(200)
            ->assertJsonPath('data.has_active_flash_sales', true)
            ->assertJsonCount(1, 'data.flash_sales')
            ->assertJsonPath('data.flash_sales.0.name', 'Studio Mic X1')
            ->assertJsonCount(1, 'data.featured_stores');

        // Catalog filters: flash_sale=1, min_rating=4, location=Calamba
        $this->getJson(route('api.mobile.buyer.catalog', [
            'flash_sale' => 1,
            'min_rating' => 4,
            'location' => 'Calamba',
        ]))->assertStatus(200)
          ->assertJsonCount(1, 'data')
          ->assertJsonPath('data.0.id', $flashProduct->id);
    }

    public function test_buyer_cart_checkout_preview_returns_lifecycle_and_notifications(): void
    {
        $buyer = User::factory()->create(['role' => 'buyer', 'approval_status' => 'approved', 'account_status' => 'active']);
        $seller = User::factory()->create(['role' => 'seller', 'approval_status' => 'approved', 'account_status' => 'active']);
        SellerProfile::create(['user_id' => $seller->id, 'business_name' => 'Real Craft PH', 'category' => 'Home & Living']);

        $category = Category::create(['name' => 'Home', 'slug' => 'home']);
        $product = Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'name' => 'Bamboo Lamp',
            'slug' => 'bamboo-lamp',
            'description' => 'Handcrafted lamp',
            'price' => 1000.00,
            'stock' => 5,
            'weight' => 1.0,
            'status' => 'active',
        ]);

        $address = Address::create([
            'user_id' => $buyer->id,
            'province_code' => '043400000',
            'province_name' => 'Laguna',
            'municipality_code' => '043426000',
            'municipality_name' => 'Santa Cruz',
            'barangay_code' => '043426001',
            'barangay_name' => 'Poblacion I',
            'street' => '22 Lakeview',
            'house_number' => '22',
            'recipient_name' => $buyer->full_name,
            'recipient_phone' => '09172223333',
            'is_default' => true,
        ]);

        $platformVoucher = Voucher::create([
            'seller_id' => null,
            'code' => 'REAL50',
            'type' => 'flat',
            'voucher_scope' => 'platform',
            'value' => 50.00,
            'min_spend' => 500.00,
            'is_active' => true,
            'expires_at' => now()->addDays(5),
        ]);

        Sanctum::actingAs($buyer);

        // Cart includes selected_count & selected_subtotal
        $this->postJson(route('api.mobile.buyer.cart.items.store'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertStatus(201)
          ->assertJsonPath('data.selected_count', 1)
          ->assertJsonPath('data.selected_subtotal', 1000);

        // Checkout summary calculates real voucher preview
        $this->getJson(route('api.mobile.buyer.checkout.summary', [
            'platform_voucher_id' => $platformVoucher->id,
        ]))->assertStatus(200)
          ->assertJsonPath('data.total_subtotal', 1000)
          ->assertJsonPath('data.total_shipping', 50)
          ->assertJsonPath('data.platform_discount', 50)
          ->assertJsonPath('data.total_discount', 50)
          ->assertJsonPath('data.estimated_total', 1000);

        // Delivered order + Dispute return stages + return shipment tracking + detailed serializer
        $order = Order::create([
            'order_number' => 'NX-RET-' . uniqid(),
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'address_id' => $address->id,
            'subtotal' => 1000.00,
            'shipping_fee' => 50.00,
            'total' => 1050.00,
            'payment_method' => 'cod',
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => 1000.00,
            'quantity' => 1,
        ]);

        $dispute = Dispute::create([
            'order_id' => $order->id,
            'raised_by' => $buyer->id,
            'reason' => 'defective',
            'details' => 'Switch flickers when plugged in.',
            'status' => 'under_review',
            'seller_status' => 'approved',
            'return_stage' => Dispute::RETURN_STAGE_APPROVED_AWAITING_RETURN,
            'return_instructions' => 'Send via J&T to Laguna Hub',
        ]);

        $this->postJson(route('api.mobile.buyer.orders.return-shipment', $order), [
            'return_tracking_number' => 'JNT-RET-771',
        ])->assertStatus(200)
          ->assertJsonPath('data.order.dispute.return_stage', Dispute::RETURN_STAGE_IN_RETURN_TRANSIT)
          ->assertJsonPath('data.order.dispute.return_tracking_number', 'JNT-RET-771');

        $this->getJson(route('api.mobile.buyer.returns.index'))
            ->assertStatus(200)
            ->assertJsonPath('data.returns.0.id', $dispute->id)
            ->assertJsonPath('data.returns.0.return_stage', Dispute::RETURN_STAGE_IN_RETURN_TRANSIT);

        // Notifications API reads real DB notifications created by OrderStatusUpdated
        $buyer->notify(new OrderStatusUpdated($order, 'delivered'));
        $notifRes = $this->getJson(route('api.mobile.notifications.index'));
        $notifRes->assertStatus(200)
            ->assertJsonPath('data.unread_count', 1);

        $notifId = $notifRes->json('data.notifications.0.id');
        $this->postJson(route('api.mobile.notifications.read', $notifId))
            ->assertStatus(200)
            ->assertJsonPath('data.unread_count', 0);
    }

    public function test_rider_read_only_lookup_password_change_and_courier_exception_history(): void
    {
        $logistics = User::factory()->create(['role' => 'logistics', 'approval_status' => 'approved', 'account_status' => 'active']);
        $otherLogistics = User::factory()->create(['role' => 'logistics', 'approval_status' => 'approved', 'account_status' => 'active']);

        $hubA = Hub::create([
            'logistics_company_id' => $logistics->id,
            'name' => 'Calamba Hub',
            'code' => 'CAL-LKP',
            'province_code' => '043400000',
            'province_name' => 'Laguna',
            'city_code' => '043405000',
            'city_name' => 'Calamba',
            'barangay_code' => '043405001',
            'barangay_name' => 'Real',
            'is_active' => true,
        ]);
        $hubB = Hub::create([
            'logistics_company_id' => $logistics->id,
            'name' => 'Santa Cruz Hub',
            'code' => 'STC-LKP',
            'province_code' => '043400000',
            'province_name' => 'Laguna',
            'city_code' => '043426000',
            'city_name' => 'Santa Cruz',
            'barangay_code' => '043426001',
            'barangay_name' => 'Poblacion I',
            'is_active' => true,
        ]);

        $rider = User::factory()->create([
            'role' => 'rider',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'password' => Hash::make('OldPass123!'),
        ]);
        CourierProfile::create([
            'user_id' => $rider->id,
            'sorting_center_id' => $logistics->id,
            'logistics_company_id' => $logistics->id,
            'assigned_hub_id' => $hubA->id,
            'is_online' => true,
            'vehicle_type' => 'Motorcycle',
            'plate_number' => 'NX-1234',
            'license_number' => 'N01-24-111222',
            'license_expiry_date' => now()->addYear(),
            'vehicle_verification_status' => 'verified',
        ]);

        $buyer = User::factory()->create(['role' => 'buyer', 'approval_status' => 'approved', 'account_status' => 'active']);
        $seller = User::factory()->create(['role' => 'seller', 'approval_status' => 'approved', 'account_status' => 'active']);
        $addr = Address::create([
            'user_id' => $buyer->id,
            'province_code' => '043400000',
            'province_name' => 'Laguna',
            'municipality_code' => '043426000',
            'municipality_name' => 'Santa Cruz',
            'barangay_code' => '043426001',
            'barangay_name' => 'Poblacion I',
            'street' => '33 Bay St',
            'house_number' => '33',
            'recipient_name' => $buyer->full_name,
            'recipient_phone' => '09173334444',
            'is_default' => true,
        ]);

        $authorizedOrder = Order::create([
            'order_number' => 'NX-LKP-01',
            'tracking_number' => 'NX-TRK-LKP-01',
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'address_id' => $addr->id,
            'sorting_center_id' => $logistics->id,
            'pickup_logistics_id' => $logistics->id,
            'pickup_courier_id' => $rider->id,
            'subtotal' => 400.00,
            'shipping_fee' => 50.00,
            'total' => 450.00,
            'payment_method' => 'cod',
            'status' => 'ready_for_pickup',
        ]);

        $foreignOrder = Order::create([
            'order_number' => 'NX-LKP-FOREIGN',
            'tracking_number' => 'NX-TRK-FOREIGN',
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'address_id' => $addr->id,
            'sorting_center_id' => $otherLogistics->id,
            'subtotal' => 400.00,
            'shipping_fee' => 50.00,
            'total' => 450.00,
            'payment_method' => 'cod',
            'status' => 'ready_for_pickup',
        ]);

        Sanctum::actingAs($rider);

        // Read-only lookup succeeds without mutating status
        $this->getJson(route('api.mobile.rider.lookup', ['tracking_number' => 'NX-TRK-LKP-01']))
            ->assertStatus(200)
            ->assertJsonPath('data.order_id', $authorizedOrder->id)
            ->assertJsonPath('data.status', 'ready_for_pickup')
            ->assertJsonPath('data.can_pickup', true);
        $this->assertSame('ready_for_pickup', $authorizedOrder->fresh()->status);

        // Unauthorized foreign parcel returns 403
        $this->getJson(route('api.mobile.rider.lookup', ['tracking_number' => 'NX-TRK-FOREIGN']))
            ->assertStatus(403);

        // Unknown barcode returns 404
        $this->getJson(route('api.mobile.rider.lookup', ['tracking_number' => 'NX-DOES-NOT-EXIST']))
            ->assertStatus(404);

        // Authenticated rider password change via /api/mobile/v1/auth/password
        $this->putJson(route('api.mobile.auth.password'), [
            'current_password' => 'OldPass123!',
            'password' => 'NewPass123!',
            'password_confirmation' => 'NewPass123!',
        ])->assertStatus(200);
        $this->assertTrue(Hash::check('NewPass123!', $rider->fresh()->password));

        // Transfer courier history includes closed_with_exceptions manifests
        $courier = User::factory()->create(['role' => 'courier', 'approval_status' => 'approved', 'account_status' => 'active']);
        $manifest = TransferManifest::create([
            'manifest_number' => 'MF-EXC-HIST-01',
            'origin_hub_id' => $hubA->id,
            'destination_hub_id' => $hubB->id,
            'sender_logistics_id' => $logistics->id,
            'receiver_logistics_id' => $logistics->id,
            'assigned_courier_id' => $courier->id,
            'status' => TransferManifest::STATUS_CLOSED_WITH_EXCEPTIONS,
            'total_parcels' => 1,
            'created_by' => $logistics->id,
        ]);

        Sanctum::actingAs($courier);
        $this->getJson(route('api.mobile.transfer-courier.manifests.index', ['status' => 'history']))
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $manifest->id)
            ->assertJsonPath('data.0.status', TransferManifest::STATUS_CLOSED_WITH_EXCEPTIONS);
    }
}
