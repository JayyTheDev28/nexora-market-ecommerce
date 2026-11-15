<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Hub;
use App\Models\Order;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use App\Models\Zone;
use App\Services\MobileCheckoutService;
use Database\Seeders\InterRegionLogisticsSeeder;
use Database\Seeders\PsgcSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CaviteOrderingAndAddressTest extends TestCase
{
    use RefreshDatabase;

    protected User $caviteCompany;
    protected User $buyerCavite;
    protected User $seller;
    protected Product $product;
    protected Category $category;
    protected Hub $caviteHub;
    protected Zone $caviteZone;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed PSGC and inter-region baseline logistics data
        $this->seed(PsgcSeeder::class);
        $this->seed(InterRegionLogisticsSeeder::class);

        $this->caviteCompany = User::where('email', 'cavite.logistics@nexora.test')->firstOrFail();
        $this->caviteHub = Hub::where('code', 'HUB-CAV-BAC')->firstOrFail();
        $this->caviteZone = Zone::where('logistics_company_id', $this->caviteCompany->id)
            ->where('city_name', 'City of Bacoor')
            ->firstOrFail();

        $this->category = Category::firstOrCreate(
            ['slug' => 'gadgets'],
            ['name' => 'Gadgets', 'is_active' => true]
        );

        // Create Seller
        $this->seller = User::create([
            'first_name' => 'Tech',
            'last_name' => 'Merchant',
            'sex' => 'other',
            'birthday' => '1990-01-01',
            'age' => 36,
            'contact_number' => '09171112233',
            'email' => 'tech_merchant_' . uniqid() . '@nexora.test',
            'password' => Hash::make('Password123!@#'),
            'role' => 'seller',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
        SellerProfile::create([
            'user_id' => $this->seller->id,
            'business_name' => 'Tech Merchant Store',
            'category' => 'Gadgets',
            'contact_phone' => '09171112233',
        ]);

        $this->product = Product::create([
            'seller_id' => $this->seller->id,
            'category_id' => $this->category->id,
            'name' => 'Gaming Headset Pro',
            'slug' => 'gaming-headset-pro-' . uniqid(),
            'price' => 1299.00,
            'stock' => 50,
            'weight' => 1.0,
            'status' => 'active',
        ]);

        // Create Buyer in Cavite
        $this->buyerCavite = User::create([
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'sex' => 'male',
            'birthday' => '1995-07-20',
            'age' => 31,
            'contact_number' => '09189998877',
            'email' => 'buyer_cavite_' . uniqid() . '@nexora.test',
            'password' => Hash::make('Password123!@#'),
            'role' => 'buyer',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    public function test_buyer_can_order_from_cavite_via_web_checkout(): void
    {
        $this->actingAs($this->buyerCavite);

        // Address located in Bacoor, Cavite
        $caviteAddress = Address::create([
            'user_id' => $this->buyerCavite->id,
            'recipient_name' => 'Juan Dela Cruz',
            'recipient_phone' => '09189998877',
            'province_code' => '042100000',
            'province_name' => 'Cavite',
            'municipality_code' => '042103000',
            'municipality_name' => 'City of Bacoor',
            'barangay_code' => '042103001',
            'barangay_name' => 'Alima',
            'street' => '100 Coastal Road',
            'house_number' => 'Lot 5',
            'is_default' => true,
        ]);

        // Add product to cart
        CartItem::create([
            'user_id' => $this->buyerCavite->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => $this->product->price,
            'selected' => true,
        ]);

        // Post to web checkout
        $response = $this->post(route('checkout.store'), [
            'address_id' => $caviteAddress->id,
            'payment_method' => 'cod',
        ]);

        // Must NOT return error about Cavite being outside servicing areas
        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('buyer.orders'));

        // Order is placed in database
        $order = Order::where('buyer_id', $this->buyerCavite->id)->latest()->first();
        $this->assertNotNull($order);
        $this->assertEquals('placed', $order->status);
        $this->assertEquals($caviteAddress->id, $order->address_id);
        $this->assertEquals('Cavite', $order->address_snapshot['province_name']);
        $this->assertEquals('City of Bacoor', $order->address_snapshot['municipality_name']);

        // Cavite routing: destination hub matches Cavite North Hub - Bacoor
        $this->assertEquals($this->caviteCompany->id, $order->delivery_logistics_id);
        $this->assertEquals($this->caviteHub->id, $order->destination_hub_id);
    }

    public function test_buyer_can_order_from_cavite_via_mobile_checkout_service(): void
    {
        $caviteAddress = Address::create([
            'user_id' => $this->buyerCavite->id,
            'recipient_name' => 'Juan Dela Cruz',
            'recipient_phone' => '09189998877',
            'province_code' => '042100000',
            'province_name' => 'Cavite',
            'municipality_code' => '042106000',
            'municipality_name' => 'City of Dasmariñas',
            'barangay_code' => '042106001',
            'barangay_name' => 'Burol',
            'street' => 'Aguinaldo Highway',
            'house_number' => 'Block 3',
            'is_default' => true,
        ]);

        $cartItem = CartItem::create([
            'user_id' => $this->buyerCavite->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => $this->product->price,
            'selected' => true,
        ]);

        $mobileService = app(MobileCheckoutService::class);

        $result = $mobileService->processCheckout($this->buyerCavite, [
            'address_id' => $caviteAddress->id,
            'payment_method' => 'cod',
            'cart_item_ids' => [$cartItem->id],
        ]);

        $this->assertNotEmpty($result);
        $order = $result[0];
        $this->assertEquals('placed', $order->status);
        $this->assertEquals($caviteAddress->id, $order->address_id);
    }

    public function test_buyer_can_delete_address_even_if_associated_with_past_orders(): void
    {
        $this->actingAs($this->buyerCavite);

        // Address 1 (Primary)
        $address1 = Address::create([
            'user_id' => $this->buyerCavite->id,
            'recipient_name' => 'Juan Primary',
            'recipient_phone' => '09189998877',
            'province_code' => '042100000',
            'province_name' => 'Cavite',
            'municipality_code' => '042103000',
            'municipality_name' => 'City of Bacoor',
            'barangay_code' => '042103001',
            'barangay_name' => 'Alima',
            'street' => '100 Coastal Road',
            'is_default' => true,
        ]);

        // Address 2 (Secondary)
        $address2 = Address::create([
            'user_id' => $this->buyerCavite->id,
            'recipient_name' => 'Juan Office',
            'recipient_phone' => '09189998877',
            'province_code' => '042100000',
            'province_name' => 'Cavite',
            'municipality_code' => '042103000',
            'municipality_name' => 'City of Bacoor',
            'barangay_code' => '042103001',
            'barangay_name' => 'Alima',
            'street' => '200 Main St',
            'is_default' => false,
        ]);

        // Simulate an existing order that was created using Address 1
        $order = Order::create([
            'order_number' => 'NX-' . strtoupper(uniqid()),
            'buyer_id' => $this->buyerCavite->id,
            'seller_id' => $this->seller->id,
            'address_id' => $address1->id,
            'address_snapshot' => [
                'id' => $address1->id,
                'recipient_name' => $address1->recipient_name,
                'province_name' => 'Cavite',
                'municipality_name' => 'City of Bacoor',
            ],
            'status' => 'delivered',
            'payment_method' => 'cod',
            'payment_status' => 'paid',
            'subtotal' => 1299.00,
            'shipping_fee' => 50.00,
            'total' => 1349.00,
            'placed_at' => now()->subDays(2),
        ]);

        // Buyer deletes Address 1 from account
        $deleteResponse = $this->delete(route('buyer.addresses.destroy', $address1->id));

        // Must succeed with redirect, NOT fail with 500 server error
        $deleteResponse->assertRedirect();
        $deleteResponse->assertSessionHas('status', 'Address removed successfully.');

        // Address 1 is soft-deleted
        $this->assertTrue($address1->fresh()->trashed());

        // Address 1 is excluded from active addresses count
        $this->assertEquals(1, $this->buyerCavite->addresses()->count());

        // Address 2 was promoted to default
        $this->assertTrue($address2->fresh()->is_default);

        // Historical order still loads address relationship via withTrashed()
        $this->assertNotNull($order->fresh()->address);
        $this->assertEquals($address1->id, $order->fresh()->address->id);
    }

    public function test_unserviced_address_is_properly_rejected(): void
    {
        $this->actingAs($this->buyerCavite);

        // Address in an area with no hub, no zone, and no delivery area
        $unservicedAddress = Address::create([
            'user_id' => $this->buyerCavite->id,
            'recipient_name' => 'Remote Buyer',
            'recipient_phone' => '09189998877',
            'province_code' => '999999999',
            'province_name' => 'Unserviced Remote Island',
            'municipality_code' => '999999000',
            'municipality_name' => 'Remote Town',
            'barangay_code' => '999999001',
            'barangay_name' => 'Remote Barangay',
            'street' => 'Isolated Rd',
            'is_default' => true,
        ]);

        CartItem::create([
            'user_id' => $this->buyerCavite->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => $this->product->price,
            'selected' => true,
        ]);

        $response = $this->post(route('checkout.store'), [
            'address_id' => $unservicedAddress->id,
            'payment_method' => 'cod',
        ]);

        // Must correctly trigger the unserviceable notice
        $response->assertSessionHasErrors(['address']);
    }
}
