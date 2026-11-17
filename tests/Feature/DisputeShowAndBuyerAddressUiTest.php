<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DisputeShowAndBuyerAddressUiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_dispute_arbitrate_page_renders_with_array_variant_and_status_history(): void
    {
        Notification::fake();

        $admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'sex' => 'other',
            'birthday' => '1990-01-01',
            'age' => 35,
            'contact_number' => '09173330001',
            'email' => 'admin_' . uniqid() . '@nexora.test',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $seller = User::create([
            'first_name' => 'Paper',
            'last_name' => 'Collective',
            'sex' => 'female',
            'birthday' => '1991-01-01',
            'age' => 34,
            'contact_number' => '09173330002',
            'email' => 'seller_' . uniqid() . '@nexora.test',
            'password' => Hash::make('password'),
            'role' => 'seller',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        SellerProfile::create([
            'user_id' => $seller->id,
            'business_name' => 'Paper & Ink Collective',
            'category' => 'Stationery',
        ]);

        $buyer = User::create([
            'first_name' => 'Jon',
            'last_name' => 'Twirk',
            'sex' => 'male',
            'birthday' => '1995-01-01',
            'age' => 30,
            'contact_number' => '09173330003',
            'email' => 'buyer_' . uniqid() . '@nexora.test',
            'password' => Hash::make('password'),
            'role' => 'buyer',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $address = Address::create([
            'user_id' => $buyer->id,
            'province_code' => '042100000',
            'province_name' => 'Cavite',
            'municipality_code' => '042111000',
            'municipality_name' => 'Magallanes',
            'barangay_code' => '042111001',
            'barangay_name' => 'San Agustin',
            'street' => '123sd',
            'house_number' => '10',
        ]);

        $category = Category::firstOrCreate(
            ['slug' => 'stat-' . uniqid()],
            ['name' => 'Stationery', 'icon' => 'edit']
        );

        $product = Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'name' => 'Ceramic Notebook Mug',
            'slug' => 'mug-' . uniqid(),
            'price' => 296.40,
            'stock' => 5,
            'status' => 'active',
        ]);

        $order = Order::create([
            'order_number' => 'NX-USPKZL-' . strtoupper(uniqid()),
            'tracking_number' => 'NX-TRK-' . strtoupper(uniqid()),
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'address_id' => $address->id,
            'status' => 'delivered',
            'payment_method' => 'cod',
            'subtotal' => 296.40,
            'shipping_fee' => 50,
            'total' => 346.40,
            'placed_at' => now(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'variant' => ['Color' => 'White', 'Edition' => 'Standard'],
            'price' => 296.40,
            'quantity' => 1,
        ]);

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'status' => 'delivered',
            'changed_by' => $seller->id,
            'changed_at' => now(),
        ]);

        $dispute = Dispute::create([
            'order_id' => $order->id,
            'raised_by' => $buyer->id,
            'reason' => 'Item damaged or defective upon arrival',
            'details' => 'Damaged ceramic item',
            'status' => 'open',
            'seller_status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.disputes.show', $dispute))
            ->assertOk()
            ->assertSee('Paper &amp; Ink Collective', false)
            ->assertSee('Color: White, Edition: Standard');
    }

    public function test_buyer_address_is_editable_on_address_book_and_viewable_on_checkout(): void
    {
        $buyer = User::create([
            'first_name' => 'Address',
            'last_name' => 'Buyer',
            'sex' => 'female',
            'birthday' => '1995-05-05',
            'age' => 30,
            'contact_number' => '09174440001',
            'email' => 'addr_buyer_' . uniqid() . '@nexora.test',
            'password' => Hash::make('password'),
            'role' => 'buyer',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $seller = User::create([
            'first_name' => 'Shop',
            'last_name' => 'Seller',
            'sex' => 'male',
            'birthday' => '1992-01-01',
            'age' => 33,
            'contact_number' => '09174440002',
            'email' => 'addr_seller_' . uniqid() . '@nexora.test',
            'password' => Hash::make('password'),
            'role' => 'seller',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        SellerProfile::create([
            'user_id' => $seller->id,
            'business_name' => 'Address Test Store',
            'category' => 'General',
        ]);

        $address = Address::create([
            'user_id' => $buyer->id,
            'recipient_name' => 'Old Recipient',
            'recipient_phone' => '09171112222',
            'region_code' => '040000000',
            'region_name' => 'Region IV-A (CALABARZON)',
            'province_code' => '043400000',
            'province_name' => 'Laguna',
            'municipality_code' => '043424000',
            'municipality_name' => 'San Pablo City',
            'barangay_code' => '043424001',
            'barangay_name' => 'Barangay I-A',
            'house_number' => '10',
            'street' => 'Old Rizal St',
            'postal_code' => '4000',
            'is_default' => true,
        ]);

        // 1. Address book exposes edit modal trigger & full_address is appended in JSON
        $this->actingAs($buyer)
            ->get(route('buyer.account', ['tab' => 'addresses']))
            ->assertOk()
            ->assertSee('openEditAddressModal')
            ->assertSee('Old Rizal St');

        // 2. Updating address persists changes and keeps address_tab flash
        $this->actingAs($buyer)
            ->put(route('buyer.addresses.update', $address), [
                'recipient_name' => 'New Recipient',
                'recipient_phone' => '09179998888',
                'region_code' => '040000000',
                'region_name' => 'Region IV-A (CALABARZON)',
                'province_code' => '043400000',
                'province_name' => 'Laguna',
                'municipality_code' => '043424000',
                'municipality_name' => 'San Pablo City',
                'barangay_code' => '043424001',
                'barangay_name' => 'Barangay I-A',
                'house_number' => '22B',
                'street' => 'Updated Mabini Ave',
                'postal_code' => '4001',
                'is_default' => true,
            ])
            ->assertRedirect()
            ->assertSessionHas('address_tab', true);

        $address->refresh();
        $this->assertSame('New Recipient', $address->recipient_name);
        $this->assertSame('22B', $address->house_number);
        $this->assertSame('Updated Mabini Ave', $address->street);
        $this->assertStringContainsString('Updated Mabini Ave', $address->toArray()['full_address']);

        // 3. Checkout serializes full_address into addresses JSON so address is viewable
        $category = Category::firstOrCreate(
            ['slug' => 'gen-' . uniqid()],
            ['name' => 'General', 'icon' => 'inventory_2']
        );

        $product = Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'name' => 'Sample Item',
            'slug' => 'sample-' . uniqid(),
            'price' => 300,
            'stock' => 10,
            'status' => 'active',
        ]);

        CartItem::create([
            'user_id' => $buyer->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'is_selected' => true,
        ]);

        $this->actingAs($buyer)
            ->get(route('checkout.index'))
            ->assertOk()
            ->assertSee('Updated Mabini Ave')
            ->assertSee('full_address')
            ->assertSee('Manage / Edit Book');
    }
}
