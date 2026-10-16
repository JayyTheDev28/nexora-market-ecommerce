<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\SortingCenterProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RoleAccessAndOrderingTest extends TestCase
{
    use DatabaseTransactions;

    private User $buyer;
    private User $seller;
    private User $sortingCenter;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Buyer
        $this->buyer = User::create([
            'first_name' => 'Buyer',
            'last_name' => 'User',
            'sex' => 'female',
            'birthday' => '1995-05-15',
            'age' => 30,
            'contact_number' => '09171110001',
            'email' => 'buyer_' . uniqid() . '@nexora.test',
            'password' => Hash::make('password'),
            'role' => 'buyer',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        // 2. Seller
        $this->seller = User::create([
            'first_name' => 'Seller',
            'last_name' => 'Merchant',
            'sex' => 'male',
            'birthday' => '1988-08-20',
            'age' => 37,
            'contact_number' => '09171110002',
            'email' => 'seller_' . uniqid() . '@nexora.test',
            'password' => Hash::make('password'),
            'role' => 'seller',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        SellerProfile::create([
            'user_id' => $this->seller->id,
            'business_name' => 'Test Seller Store',
            'category' => 'Electronics',
        ]);

        // 3. Sorting Center (Logistics)
        $this->sortingCenter = User::create([
            'first_name' => 'Logistics',
            'last_name' => 'Staff',
            'sex' => 'other',
            'birthday' => '1992-03-10',
            'age' => 33,
            'contact_number' => '09171110003',
            'email' => 'logistics_' . uniqid() . '@nexora.test',
            'password' => Hash::make('password'),
            'role' => 'sorting_center',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        SortingCenterProfile::create([
            'user_id' => $this->sortingCenter->id,
            'business_name' => 'Test Logistics Hub',
        ]);

        // Sample product
        $category = Category::firstOrCreate(
            ['slug' => 'electronics'],
            ['name' => 'Electronics', 'icon' => 'devices']
        );

        $this->product = Product::create([
            'seller_id' => $this->seller->id,
            'category_id' => $category->id,
            'name' => 'Test Smartphone 5G',
            'slug' => 'test-smartphone-5g-' . uniqid(),
            'description' => 'Test product for role access test',
            'price' => 15000.00,
            'stock' => 20,
            'status' => 'active',
        ]);
    }

    public function test_seller_is_redirected_away_from_buyer_cart_and_checkout_pages(): void
    {
        $this->actingAs($this->seller);

        // Accessing cart web page redirects to seller dashboard
        $responseCart = $this->get('/cart');
        $responseCart->assertRedirect('/seller/dashboard');

        // Accessing checkout web page redirects to seller dashboard
        $responseCheckout = $this->get('/checkout');
        $responseCheckout->assertRedirect('/seller/dashboard');

        // Accessing buyer orders redirects to seller dashboard
        $responseOrders = $this->get('/buyer/orders');
        $responseOrders->assertRedirect('/seller/dashboard');
    }

    public function test_seller_cannot_add_to_cart_or_checkout_via_ajax(): void
    {
        $this->actingAs($this->seller);

        // Attempt to add to cart via JSON / AJAX
        $responseAdd = $this->postJson('/cart/items', [
            'product_id' => $this->product->id,
            'quantity' => 1,
        ]);
        $responseAdd->assertStatus(403);
        $responseAdd->assertJson(['message' => 'Forbidden.']);

        // Attempt to checkout via JSON
        $responseCheckout = $this->postJson('/checkout', [
            'name' => 'Unauthorized Seller',
            'phone' => '09170000000',
            'province_code' => '0434',
            'municipality_code' => '043404',
            'barangay_code' => '043404001',
            'payment_method' => 'cod',
        ]);
        $responseCheckout->assertStatus(403);
    }

    public function test_logistics_is_redirected_away_from_buyer_cart_and_checkout_pages(): void
    {
        $this->actingAs($this->sortingCenter);

        // Accessing cart web page redirects to logistics dashboard
        $responseCart = $this->get('/cart');
        $responseCart->assertRedirect('/logistics/dashboard');

        // Accessing checkout web page redirects to logistics dashboard
        $responseCheckout = $this->get('/checkout');
        $responseCheckout->assertRedirect('/logistics/dashboard');

        // Accessing buyer orders redirects to logistics dashboard
        $responseOrders = $this->get('/buyer/orders');
        $responseOrders->assertRedirect('/logistics/dashboard');
    }

    public function test_logistics_cannot_add_to_cart_or_checkout_via_ajax(): void
    {
        $this->actingAs($this->sortingCenter);

        // Attempt to add to cart via JSON / AJAX
        $responseAdd = $this->postJson('/cart/items', [
            'product_id' => $this->product->id,
            'quantity' => 1,
        ]);
        $responseAdd->assertStatus(403);
        $responseAdd->assertJson(['message' => 'Forbidden.']);

        // Attempt to checkout via JSON
        $responseCheckout = $this->postJson('/checkout', [
            'name' => 'Unauthorized Logistics',
            'phone' => '09170000000',
            'province_code' => '0434',
            'municipality_code' => '043404',
            'barangay_code' => '043404001',
            'payment_method' => 'cod',
        ]);
        $responseCheckout->assertStatus(403);
    }

    public function test_buyer_can_access_cart_and_orders(): void
    {
        $this->actingAs($this->buyer);

        $responseCart = $this->get('/cart');
        $responseCart->assertOk();

        $responseOrders = $this->get('/buyer/orders');
        $responseOrders->assertOk();
    }

    public function test_navbar_does_not_render_category_navigation_or_cart_for_seller(): void
    {
        $this->actingAs($this->seller);

        $response = $this->get('/seller/dashboard');
        $response->assertOk();

        // Must not contain the Category Shortcuts Quick-Bar
        $response->assertDontSee('Category Shortcuts Quick-Bar');
        $response->assertDontSee('All Products');
        $response->assertDontSee('Special Offers');

        // Must not contain the Cart button or category search dropdown
        $response->assertDontSee('aria-label="Shopping Cart"', false);
        $response->assertDontSee('All Categories');
        $response->assertDontSee('Shop by Category');

        // Must display the Seller Workspace indicator
        $response->assertSee('Seller Management Workspace');
        $response->assertSee('Test Seller Store');
    }

    public function test_navbar_does_not_render_category_navigation_or_cart_for_logistics(): void
    {
        $this->actingAs($this->sortingCenter);

        $response = $this->get('/logistics/dashboard');
        $response->assertOk();

        // Must not contain the Category Shortcuts Quick-Bar
        $response->assertDontSee('Category Shortcuts Quick-Bar');
        $response->assertDontSee('Special Offers');

        // Must not contain the Cart button or category search dropdown
        $response->assertDontSee('aria-label="Shopping Cart"', false);
        $response->assertDontSee('Shop by Category');

        // Must display Logistics Hub indicator
        $response->assertSee('Sorting Center Management Workspace');
        $response->assertSee('Test Logistics Hub');
    }

    public function test_navbar_renders_category_navigation_and_cart_for_buyer(): void
    {
        $this->actingAs($this->buyer);

        $response = $this->get('/buyer/dashboard');
        $response->assertOk();

        // Category quick bar should be visible for buyer
        $response->assertSee('All Products');
        $response->assertSee('Special Offers');
        $response->assertSee('aria-label="Shopping Cart"', false);
        $response->assertSee('All Categories');
        $response->assertSee('Shop by Category');
    }

    public function test_product_detail_page_hides_order_controls_from_seller_and_logistics(): void
    {
        // For Seller
        $this->actingAs($this->seller);
        $responseSeller = $this->get("/products/{$this->product->id}");
        $responseSeller->assertOk();
        $responseSeller->assertSee('Purchasing is exclusive to Buyer accounts');
        $responseSeller->assertDontSee(':disabled="stock === 0 || !canAddToCart || addingToCart"', false);

        // For Logistics
        $this->actingAs($this->sortingCenter);
        $responseLogistics = $this->get("/products/{$this->product->id}");
        $responseLogistics->assertOk();
        $responseLogistics->assertSee('Purchasing is exclusive to Buyer accounts');
        $responseLogistics->assertDontSee(':disabled="stock === 0 || !canAddToCart || addingToCart"', false);

        // For Buyer
        $this->actingAs($this->buyer);
        $responseBuyer = $this->get("/products/{$this->product->id}");
        $responseBuyer->assertOk();
        $responseBuyer->assertDontSee('Purchasing is exclusive to Buyer accounts');
        $responseBuyer->assertSee(':disabled="stock === 0 || !canAddToCart || addingToCart"', false);
    }
}
