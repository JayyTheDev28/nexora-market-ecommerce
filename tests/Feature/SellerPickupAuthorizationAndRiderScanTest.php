<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Category;
use App\Models\CourierProfile;
use App\Models\Hub;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\RiderEarning;
use App\Models\SellerProfile;
use App\Models\SortingCenterProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SellerPickupAuthorizationAndRiderScanTest extends TestCase
{
    use DatabaseTransactions;

    private User $logistics;
    private Hub $originHub;
    private User $seller;
    private User $buyer;
    private User $assignedRider;
    private User $otherRider;
    private Address $address;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        $this->logistics = User::create([
            'first_name' => 'Laguna',
            'last_name' => 'Logistics',
            'sex' => 'other',
            'birthday' => '1990-01-01',
            'age' => 35,
            'contact_number' => '09172220001',
            'email' => 'logistics_' . uniqid() . '@nexora.test',
            'password' => Hash::make('password'),
            'role' => 'sorting_center',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        SortingCenterProfile::create([
            'user_id' => $this->logistics->id,
            'business_name' => 'Laguna Express Hub',
        ]);

        $this->originHub = Hub::create([
            'logistics_company_id' => $this->logistics->id,
            'name' => 'San Pablo Origin Hub',
            'code' => 'LAG-SPL-' . strtoupper(uniqid()),
            'province_code' => '043400000',
            'province_name' => 'Laguna',
            'city_code' => '043424000',
            'city_name' => 'San Pablo City',
            'barangay_code' => '043424001',
            'barangay_name' => 'Barangay I-A',
            'is_active' => true,
        ]);

        $this->seller = User::create([
            'first_name' => 'Pedro',
            'last_name' => 'Seller',
            'sex' => 'male',
            'birthday' => '1991-03-03',
            'age' => 34,
            'contact_number' => '09172220002',
            'email' => 'seller_' . uniqid() . '@nexora.test',
            'password' => Hash::make('password'),
            'role' => 'seller',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        SellerProfile::create([
            'user_id' => $this->seller->id,
            'business_name' => 'Pedro Hardware Shop',
            'category' => 'Hardware',
        ]);

        $this->buyer = User::create([
            'first_name' => 'Maria',
            'last_name' => 'Buyer',
            'sex' => 'female',
            'birthday' => '1995-05-05',
            'age' => 30,
            'contact_number' => '09172220003',
            'email' => 'buyer_' . uniqid() . '@nexora.test',
            'password' => Hash::make('password'),
            'role' => 'buyer',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $this->assignedRider = User::create([
            'first_name' => 'Juan',
            'last_name' => 'Rider',
            'sex' => 'male',
            'birthday' => '1996-06-06',
            'age' => 29,
            'contact_number' => '09172220004',
            'email' => 'rider1_' . uniqid() . '@nexora.test',
            'password' => Hash::make('password'),
            'role' => 'rider',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        CourierProfile::create([
            'user_id' => $this->assignedRider->id,
            'sorting_center_id' => $this->logistics->id,
            'logistics_company_id' => $this->logistics->id,
            'vehicle_type' => 'Motorcycle',
            'plate_number' => 'NX-1234',
            'is_online' => true,
            'earnings_balance' => 0,
            'unremitted_cod_balance' => 0,
        ]);

        $this->otherRider = User::create([
            'first_name' => 'Other',
            'last_name' => 'Rider',
            'sex' => 'male',
            'birthday' => '1997-07-07',
            'age' => 28,
            'contact_number' => '09172220005',
            'email' => 'rider2_' . uniqid() . '@nexora.test',
            'password' => Hash::make('password'),
            'role' => 'rider',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        CourierProfile::create([
            'user_id' => $this->otherRider->id,
            'sorting_center_id' => $this->logistics->id,
            'logistics_company_id' => $this->logistics->id,
            'vehicle_type' => 'Motorcycle',
            'plate_number' => 'NX-9999',
            'is_online' => true,
            'earnings_balance' => 0,
            'unremitted_cod_balance' => 0,
        ]);

        $this->address = Address::create([
            'user_id' => $this->buyer->id,
            'province_code' => '043400000',
            'province_name' => 'Laguna',
            'municipality_code' => '043424000',
            'municipality_name' => 'San Pablo City',
            'barangay_code' => '043424001',
            'barangay_name' => 'Barangay I-A',
            'street' => 'Rizal Ave',
            'house_number' => '50',
        ]);

        $category = Category::firstOrCreate(
            ['slug' => 'hardware-' . uniqid()],
            ['name' => 'Hardware', 'icon' => 'build']
        );

        $this->product = Product::create([
            'seller_id' => $this->seller->id,
            'category_id' => $category->id,
            'name' => 'Cordless Drill',
            'slug' => 'drill-' . uniqid(),
            'price' => 1200,
            'stock' => 10,
            'status' => 'active',
        ]);
    }

    private function createOrder(string $status = 'placed'): Order
    {
        $order = Order::create([
            'order_number' => 'NX-ORD-' . strtoupper(uniqid()),
            'tracking_number' => 'NX-TRK-' . strtoupper(uniqid()),
            'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'address_id' => $this->address->id,
            'status' => $status,
            'payment_method' => 'cod',
            'subtotal' => 1200,
            'discount' => 0,
            'shipping_fee' => 50,
            'total' => 1250,
            'sorting_center_id' => $this->logistics->id,
            'pickup_logistics_id' => $this->logistics->id,
            'origin_hub_id' => $this->originHub->id,
            'current_custody_logistics_id' => $this->logistics->id,
            'placed_at' => now(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'price' => 1200,
            'quantity' => 1,
        ]);

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'status' => $status,
            'changed_by' => $this->buyer->id,
            'changed_at' => now(),
        ]);

        return $order;
    }

    public function test_seller_can_advance_to_ready_for_pickup_but_cannot_mark_picked_up(): void
    {
        $order = $this->createOrder('placed');

        // placed -> confirmed -> preparing -> ready_for_pickup succeed
        $this->actingAs($this->seller)
            ->post(route('seller.orders.advance', $order))
            ->assertRedirect();
        $this->assertSame('confirmed', $order->fresh()->status);

        $this->actingAs($this->seller)
            ->post(route('seller.orders.advance', $order))
            ->assertRedirect();
        $this->assertSame('preparing', $order->fresh()->status);

        // Seller cannot bypass directly to picked_up even while preparing
        $this->actingAs($this->seller)
            ->post(route('seller.orders.advance', $order), ['status' => 'picked_up'])
            ->assertStatus(403);
        $this->assertSame('preparing', $order->fresh()->status);

        // Normal advance preparing -> ready_for_pickup works
        $this->actingAs($this->seller)
            ->post(route('seller.orders.advance', $order))
            ->assertRedirect();
        $order->refresh();
        $this->assertSame('ready_for_pickup', $order->status);

        // Seller UI shows Awaiting Rider Pickup & does NOT show Confirm Courier Handover
        $this->actingAs($this->seller)
            ->get(route('seller.orders.show', $order))
            ->assertOk()
            ->assertSee('Status: Awaiting Rider Pickup')
            ->assertDontSee('Confirm Courier Handover');

        // When Logistics assigns a pickup rider, Seller UI displays assigned rider details
        $order->update([
            'pickup_courier_id' => $this->assignedRider->id,
            'pickup_rider_id' => $this->assignedRider->id,
        ]);

        $this->actingAs($this->seller)
            ->get(route('seller.orders.show', $order))
            ->assertOk()
            ->assertSee('Status: Awaiting Rider Pickup')
            ->assertSee('Juan Rider')
            ->assertSee('NX-1234');

        // Seller attempting ready_for_pickup -> picked_up is blocked server-side with 403
        $this->actingAs($this->seller)
            ->post(route('seller.orders.advance', $order))
            ->assertStatus(403);

        $this->assertSame('ready_for_pickup', $order->fresh()->status);
        $this->assertNull($order->fresh()->picked_up_at);
    }

    public function test_verified_rider_barcode_scan_pickup_enforces_security_and_synchronizes_all_roles(): void
    {
        $order = $this->createOrder('ready_for_pickup');
        $otherOrder = $this->createOrder('ready_for_pickup');

        // 1. Unassigned rider cannot confirm pickup (neither unassigned order nor assigned to another rider)
        Sanctum::actingAs($this->otherRider);
        $this->postJson(route('api.mobile.rider.pickup'), [
            'tracking_number' => $order->tracking_number,
            'expected_order_id' => $order->id,
        ])->assertStatus(403);

        // Assign rider1
        $order->update([
            'pickup_courier_id' => $this->assignedRider->id,
            'pickup_rider_id' => $this->assignedRider->id,
        ]);

        // Different rider still rejected with 403
        Sanctum::actingAs($this->otherRider);
        $this->postJson(route('api.mobile.rider.pickup'), [
            'tracking_number' => $order->tracking_number,
            'expected_order_id' => $order->id,
        ])->assertStatus(403);
        $this->assertSame('ready_for_pickup', $order->fresh()->status);

        // 2. Assigned rider with invalid barcode is rejected
        Sanctum::actingAs($this->assignedRider);
        $this->postJson(route('api.mobile.rider.pickup'), [
            'tracking_number' => 'NX-TRK-INVALID',
            'expected_order_id' => $order->id,
        ])->assertStatus(404);

        // Passing order_number instead of waybill barcode tracking_number is rejected
        $this->postJson(route('api.mobile.rider.pickup'), [
            'tracking_number' => $order->order_number,
            'expected_order_id' => $order->id,
        ])->assertStatus(422);

        // Scanning another parcel's barcode is rejected
        $this->postJson(route('api.mobile.rider.pickup'), [
            'tracking_number' => $otherOrder->tracking_number,
            'expected_order_id' => $order->id,
        ])->assertStatus(422);

        $this->assertSame('ready_for_pickup', $order->fresh()->status);

        // 3. Assigned rider with valid waybill barcode confirms pickup
        $pickupRes = $this->withHeader('Idempotency-Key', 'verified-pickup-key-1')
            ->postJson(route('api.mobile.rider.pickup'), [
                'tracking_number' => $order->tracking_number,
                'expected_order_id' => $order->id,
            ]);

        $pickupRes->assertOk()
            ->assertJsonPath('data.order_status', 'picked_up')
            ->assertJsonPath('data.pickup_rider_id', $this->assignedRider->id);

        $order->refresh();
        $this->assertSame('picked_up', $order->status);
        $this->assertNotNull($order->picked_up_at);
        $this->assertSame($this->assignedRider->id, (int) $order->pickup_courier_id);
        $this->assertSame($this->assignedRider->id, (int) $order->pickup_rider_id);

        // Single status history and single earning created
        $this->assertSame(1, OrderStatusHistory::where('order_id', $order->id)->where('status', 'picked_up')->count());
        $this->assertSame(1, RiderEarning::where('order_id', $order->id)->where('type', 'pickup')->count());

        // 4. Same Idempotency-Key network retry replays cleanly without duplicate events or earnings
        $this->withHeader('Idempotency-Key', 'verified-pickup-key-1')
            ->postJson(route('api.mobile.rider.pickup'), [
                'tracking_number' => $order->tracking_number,
                'expected_order_id' => $order->id,
            ])
            ->assertOk();

        // Repeated fresh confirmation is rejected (422) and creates zero duplicates
        $this->withHeader('Idempotency-Key', 'verified-pickup-key-2')
            ->postJson(route('api.mobile.rider.pickup'), [
                'tracking_number' => $order->tracking_number,
                'expected_order_id' => $order->id,
            ])
            ->assertStatus(422);

        $this->assertSame(1, OrderStatusHistory::where('order_id', $order->id)->where('status', 'picked_up')->count());
        $this->assertSame(1, RiderEarning::where('order_id', $order->id)->where('type', 'pickup')->count());

        // 5. Role synchronization:
        // Seller sees Picked Up
        $this->actingAs($this->seller)
            ->get(route('seller.orders.show', $order))
            ->assertOk()
            ->assertSee('Picked Up by Rider');

        // Buyer sees Parcel Picked Up
        $this->actingAs($this->buyer)
            ->get(route('buyer.orders'))
            ->assertOk()
            ->assertSee('Parcel Picked Up');

        // Rider completed task history includes pickup
        Sanctum::actingAs($this->assignedRider);
        $history = $this->getJson(route('api.mobile.rider.deliveries.history'))
            ->assertOk()
            ->json('data');
        $this->assertTrue(collect($history)->contains(fn ($t) => $t['id'] === $order->id && $t['task_outcome'] === 'picked_up'));

        // Logistics Incoming shows parcel awaiting physical receiving at hub (NOT yet received_at_hub)
        $this->actingAs($this->logistics)
            ->get(route('logistics.incoming.index', ['tab' => 'awaiting']))
            ->assertOk()
            ->assertSee($order->tracking_number);
        $this->assertSame('picked_up', $order->fresh()->status);
    }

    public function test_web_courier_and_v1_rider_pickup_endpoints_enforce_barcode_assignment_and_duplicate_protection(): void
    {
        $webOrder = $this->createOrder('ready_for_pickup');
        $webOrder->update([
            'pickup_courier_id' => $this->assignedRider->id,
            'pickup_rider_id' => $this->assignedRider->id,
        ]);

        // Web pickup rejects unassigned rider
        $this->actingAs($this->otherRider)
            ->postJson(route('courier.pickup.confirm'), [
                'tracking_number' => $webOrder->tracking_number,
            ])
            ->assertStatus(403);

        // Web pickup rejects passing order_number instead of waybill tracking_number
        $this->actingAs($this->assignedRider)
            ->postJson(route('courier.pickup.confirm'), [
                'tracking_number' => $webOrder->order_number,
            ])
            ->assertStatus(422);

        // Web pickup succeeds with canonical barcode and records timestamp
        $this->actingAs($this->assignedRider)
            ->postJson(route('courier.pickup.confirm'), [
                'tracking_number' => $webOrder->tracking_number,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('picked_up', $webOrder->fresh()->status);
        $this->assertNotNull($webOrder->fresh()->picked_up_at);

        // Duplicate web pickup confirmation rejected with 422
        $this->actingAs($this->assignedRider)
            ->postJson(route('courier.pickup.confirm'), [
                'tracking_number' => $webOrder->tracking_number,
            ])
            ->assertStatus(422);
        $this->assertSame(1, RiderEarning::where('order_id', $webOrder->id)->where('type', 'pickup')->count());

        // Legacy /api/v1/rider/pickup enforces same rules
        $apiOrder = $this->createOrder('ready_for_pickup');
        $apiOrder->update([
            'pickup_courier_id' => $this->assignedRider->id,
            'pickup_rider_id' => $this->assignedRider->id,
        ]);

        $this->actingAs($this->assignedRider)
            ->postJson('/api/v1/rider/pickup', [
                'tracking_number' => $apiOrder->order_number,
            ])
            ->assertStatus(422);

        $this->actingAs($this->assignedRider)
            ->postJson('/api/v1/rider/pickup', [
                'tracking_number' => $apiOrder->tracking_number,
            ])
            ->assertOk()
            ->assertJsonPath('order_status', 'picked_up');

        $this->actingAs($this->assignedRider)
            ->postJson('/api/v1/rider/pickup', [
                'tracking_number' => $apiOrder->tracking_number,
            ])
            ->assertStatus(422);
        $this->assertSame(1, RiderEarning::where('order_id', $apiOrder->id)->where('type', 'pickup')->count());
    }
}
