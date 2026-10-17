<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Category;
use App\Models\CourierProfile;
use App\Models\DeliveryArea;
use App\Models\DeliveryAttempt;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\SortingCenterProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LogisticsOperationsTest extends TestCase
{
    use DatabaseTransactions;

    private User $sortingCenter;
    private User $buyer;
    private User $seller;
    private User $courier1;
    private User $courier2;
    private DeliveryArea $areaA;
    private DeliveryArea $areaB;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Sorting Center User
        $this->sortingCenter = User::create([
            'first_name' => 'Test Hub',
            'last_name' => 'Logistics',
            'sex' => 'other',
            'birthday' => '1990-01-01',
            'age' => 35,
            'contact_number' => '09170000001',
            'email' => 'hub_' . uniqid() . '@nexora.test',
            'password' => Hash::make('password'),
            'role' => 'sorting_center',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        SortingCenterProfile::create([
            'user_id' => $this->sortingCenter->id,
            'business_name' => 'Nexora Test Hub Laguna',
        ]);

        // 2. Buyer User
        $this->buyer = User::create([
            'first_name' => 'Buyer',
            'last_name' => 'Test',
            'sex' => 'male',
            'birthday' => '1995-05-05',
            'age' => 30,
            'contact_number' => '09170000002',
            'email' => 'buyer_' . uniqid() . '@nexora.test',
            'password' => Hash::make('password'),
            'role' => 'buyer',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        // 3. Seller User
        $this->seller = User::create([
            'first_name' => 'Seller',
            'last_name' => 'Store',
            'sex' => 'female',
            'birthday' => '1992-02-02',
            'age' => 33,
            'contact_number' => '09170000003',
            'email' => 'seller_' . uniqid() . '@nexora.test',
            'password' => Hash::make('password'),
            'role' => 'seller',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        // 4. Approved & Active Courier 1
        $this->courier1 = User::create([
            'first_name' => 'Courier',
            'last_name' => 'One',
            'sex' => 'male',
            'birthday' => '1998-08-08',
            'age' => 28,
            'contact_number' => '09180000001',
            'email' => 'courier1_' . uniqid() . '@nexora.test',
            'password' => Hash::make('password'),
            'role' => 'courier',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        CourierProfile::create([
            'user_id' => $this->courier1->id,
            'vehicle_type' => 'Motorcycle',
            'plate_number' => 'PLT-101',
        ]);

        // 5. Approved & Active Courier 2
        $this->courier2 = User::create([
            'first_name' => 'Courier',
            'last_name' => 'Two',
            'sex' => 'male',
            'birthday' => '1999-09-09',
            'age' => 27,
            'contact_number' => '09180000002',
            'email' => 'courier2_' . uniqid() . '@nexora.test',
            'password' => Hash::make('password'),
            'role' => 'courier',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        CourierProfile::create([
            'user_id' => $this->courier2->id,
            'vehicle_type' => 'Motorcycle',
            'plate_number' => 'PLT-202',
        ]);

        // 6. Delivery Areas
        $this->areaA = DeliveryArea::create([
            'sorting_center_id' => $this->sortingCenter->id,
            'name' => 'Zone A - Santa Cruz',
            'courier_id' => $this->courier1->id,
        ]);

        $this->areaB = DeliveryArea::create([
            'sorting_center_id' => $this->sortingCenter->id,
            'name' => 'Zone B - Pagsanjan',
            'courier_id' => $this->courier2->id,
        ]);
    }

    private function createTestOrder(string $status = 'ready_for_pickup'): Order
    {
        $address = Address::create([
            'user_id' => $this->buyer->id,
            'province_code' => '043400000',
            'province_name' => 'Laguna',
            'municipality_code' => '043426000',
            'municipality_name' => 'Santa Cruz',
            'barangay_code' => '043426001',
            'barangay_name' => 'Poblacion I',
            'street' => 'Rizal Street',
            'house_number' => '123',
        ]);

        $order = Order::create([
            'order_number' => 'NX-' . strtoupper(uniqid()),
            'tracking_number' => 'NX-TRK-' . strtoupper(uniqid()),
            'buyer_id' => $this->buyer->id,
            'address_id' => $address->id,
            'status' => $status,
            'payment_method' => 'cod',
            'subtotal' => 1500,
            'discount' => 0,
            'shipping_fee' => 60,
            'total' => 1560,
            'sorting_center_id' => $this->sortingCenter->id,
            'placed_at' => now(),
        ]);

        $category = Category::firstOrCreate(
            ['slug' => 'test-category'],
            ['name' => 'Test Category', 'icon' => 'inventory_2']
        );

        $product = Product::first() ?? Product::create([
            'seller_id' => $this->seller->id,
            'category_id' => $category->id,
            'name' => 'Test Product',
            'slug' => 'test-product-' . uniqid(),
            'price' => 1500,
            'stock' => 10,
            'status' => 'active',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => 1500,
            'quantity' => 1,
        ]);

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'status' => $status,
            'changed_by' => $this->sortingCenter->id,
            'changed_at' => now(),
        ]);

        return $order;
    }

    public function test_unauthorized_users_cannot_access_logistics(): void
    {
        // Unauthenticated guest is redirected to login
        $this->get('/logistics/dashboard')->assertRedirect('/login');

        // Web request from another role redirects them to their own home route
        $this->actingAs($this->buyer)
            ->get('/logistics/dashboard')
            ->assertRedirect($this->buyer->homeRoute());

        // JSON / AJAX request returns 403 Forbidden
        $this->actingAs($this->buyer)
            ->getJson('/logistics/dashboard')
            ->assertStatus(403);

        $this->actingAs($this->courier1)
            ->getJson('/logistics/dashboard')
            ->assertStatus(403);
    }

    public function test_logistics_dashboard_displays_real_authorized_data(): void
    {
        $this->createTestOrder('ready_for_pickup');

        $response = $this->actingAs($this->sortingCenter)->get('/logistics/dashboard');

        $response->assertStatus(200);
        $response->assertSee('Nexora Test Hub Laguna');
        $response->assertSee('Pending Pickup');
    }

    public function test_logistics_staff_can_approve_and_disapprove_rider_application(): void
    {
        $applicant = User::create([
            'first_name' => 'Pending',
            'last_name' => 'Applicant',
            'sex' => 'male',
            'birthday' => '1997-07-07',
            'age' => 29,
            'contact_number' => '09199999999',
            'email' => 'app_' . uniqid() . '@nexora.test',
            'password' => Hash::make('password'),
            'role' => 'courier',
            'approval_status' => 'pending',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        // Approve
        $this->actingAs($this->sortingCenter)
            ->post("/logistics/riders/{$applicant->id}/approve")
            ->assertSessionHas('status');

        $applicant->refresh();
        $this->assertTrue($applicant->isApproved());
        $this->assertEquals('active', $applicant->account_status);

        // Disapprove with reason
        $this->actingAs($this->sortingCenter)
            ->post("/logistics/riders/{$applicant->id}/disapprove", [
                'reason' => 'Expired driver license submitted.',
            ])
            ->assertSessionHas('status');

        $applicant->refresh();
        $this->assertEquals('disapproved', $applicant->approval_status);
        $this->assertEquals('Expired driver license submitted.', $applicant->disapproval_reason);
    }

    public function test_deactivated_rider_cannot_receive_assignments(): void
    {
        // Deactivate courier 1
        $this->actingAs($this->sortingCenter)
            ->post("/logistics/riders/{$this->courier1->id}/toggle-status", [
                'action' => 'deactivate',
                'reason' => 'Under maintenance review',
            ])
            ->assertSessionHas('status');

        $this->courier1->refresh();
        $this->assertEquals('deactivated', $this->courier1->account_status);

        // Attempting to assign parcel to deactivated courier fails
        $order = $this->createTestOrder('sorted');
        $order->update(['delivery_area_id' => $this->areaA->id]);

        $response = $this->actingAs($this->sortingCenter)
            ->post("/logistics/assignments/{$order->id}/assign", [
                'courier_id' => $this->courier1->id,
            ]);

        $response->assertStatus(422);
    }

    public function test_seller_pickup_request_scheduling_and_confirmation(): void
    {
        $order = $this->createTestOrder('ready_for_pickup');

        // Schedule pickup with rider
        $this->actingAs($this->sortingCenter)
            ->post("/logistics/pickups/{$order->id}/schedule", [
                'courier_id' => $this->courier1->id,
                'scheduled_notes' => 'Collect by 2 PM',
            ])
            ->assertSessionHas('status');

        $order->refresh();
        $this->assertEquals($this->courier1->id, $order->pickup_courier_id);
        $this->assertNotNull($order->tracking_number);

        // Confirm physical collection from seller
        $this->actingAs($this->sortingCenter)
            ->post("/logistics/pickups/{$order->id}/confirm")
            ->assertSessionHas('status');

        $order->refresh();
        $this->assertEquals('picked_up', $order->status);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'status' => 'picked_up',
        ]);
    }

    public function test_incoming_parcel_scan_and_check_in(): void
    {
        $order = $this->createTestOrder('picked_up');

        // Scan parcel identifier
        $this->actingAs($this->sortingCenter)
            ->post('/logistics/incoming/scan', [
                'identifier' => $order->tracking_number,
            ])
            ->assertSessionHas('status');

        $order->refresh();
        $this->assertEquals('at_sorting_center', $order->status);
        $this->assertEquals($this->areaA->id, $order->delivery_area_id); // Auto-matched by municipality Santa Cruz!

        // Idempotent duplicate scan should return info notice without failing
        $dupResponse = $this->actingAs($this->sortingCenter)
            ->post('/logistics/incoming/scan', [
                'identifier' => $order->tracking_number,
            ]);

        $dupResponse->assertSessionHas('info');
    }

    public function test_parcel_sorting_by_delivery_area(): void
    {
        $order = $this->createTestOrder('at_sorting_center');

        // Confirm sort to Zone A
        $this->actingAs($this->sortingCenter)
            ->post("/logistics/sorting/{$order->id}/confirm", [
                'delivery_area_id' => $this->areaA->id,
            ])
            ->assertSessionHas('status');

        $order->refresh();
        $this->assertEquals('sorted', $order->status);
        $this->assertEquals($this->areaA->id, $order->delivery_area_id);

        // Changing area requires override reason
        $failResponse = $this->actingAs($this->sortingCenter)
            ->post("/logistics/sorting/{$order->id}/confirm", [
                'delivery_area_id' => $this->areaB->id,
                'override_reason' => '',
            ]);

        $failResponse->assertSessionHasErrors('override_reason');
    }

    public function test_rider_assignment_by_area_and_reassignment(): void
    {
        $order = $this->createTestOrder('sorted');
        $order->update(['delivery_area_id' => $this->areaA->id]);

        // Assign courier 1 (assigned to Zone A)
        $this->actingAs($this->sortingCenter)
            ->post("/logistics/assignments/{$order->id}/assign", [
                'courier_id' => $this->courier1->id,
            ])
            ->assertSessionHas('status');

        $order->refresh();
        $this->assertEquals('assigned_to_rider', $order->status);
        $this->assertEquals($this->courier1->id, $order->delivery_courier_id);

        // Assigning to courier 2 (Zone B) without override reason fails
        $failResponse = $this->actingAs($this->sortingCenter)
            ->post("/logistics/assignments/{$order->id}/assign", [
                'courier_id' => $this->courier2->id,
                'override_reason' => '',
            ]);

        $failResponse->assertSessionHasErrors('override_reason');

        // Reassigning with override reason succeeds
        $this->actingAs($this->sortingCenter)
            ->post("/logistics/assignments/{$order->id}/assign", [
                'courier_id' => $this->courier2->id,
                'override_reason' => 'Courier 1 vehicle broke down',
            ])
            ->assertSessionHas('status');

        $order->refresh();
        $this->assertEquals($this->courier2->id, $order->delivery_courier_id);
    }

    public function test_delivery_monitoring_and_exception_resolution(): void
    {
        $order = $this->createTestOrder('delivery_failed');
        $order->update(['delivery_courier_id' => $this->courier1->id]);

        DeliveryAttempt::create([
            'order_id' => $order->id,
            'courier_id' => $this->courier1->id,
            'result' => 'failed',
            'failure_reason' => 'Customer unavailable',
            'notes' => 'Attempted at 3:15 PM, gate locked',
        ]);

        // Reschedule delivery attempt
        $this->actingAs($this->sortingCenter)
            ->post("/logistics/deliveries/{$order->id}/resolve", [
                'action' => 'reschedule',
                'notes' => 'Rescheduled per customer SMS for tomorrow morning',
            ])
            ->assertSessionHas('status');

        $order->refresh();
        $this->assertEquals('assigned_to_rider', $order->status);

        // Or return to seller (RTS)
        $this->actingAs($this->sortingCenter)
            ->post("/logistics/deliveries/{$order->id}/resolve", [
                'action' => 'return_to_seller',
                'notes' => 'Customer refused package, returning to merchant',
            ])
            ->assertSessionHas('status');

        $order->refresh();
        $this->assertEquals('returned', $order->status);
    }

    public function test_logistics_reports_endpoint(): void
    {
        $this->createTestOrder('delivered');

        $response = $this->actingAs($this->sortingCenter)
            ->get('/logistics/reports?from=' . now()->subDays(7)->toDateString() . '&to=' . now()->toDateString());

        $response->assertStatus(200);
        $response->assertSee('Logistics Operational Reports');
        $response->assertSee('Parcels Processed');
    }

    public function test_sorting_center_account_profile_update(): void
    {
        $this->actingAs($this->sortingCenter)
            ->post('/logistics/account/profile', [
                'business_name' => 'Nexora Hub Updated Laguna',
                'first_name' => 'UpdatedManager',
                'last_name' => 'Logistics',
                'contact_number' => '09171239999',
            ])
            ->assertSessionHas('status');

        $this->sortingCenter->refresh();
        $this->assertEquals('UpdatedManager', $this->sortingCenter->first_name);
        $this->assertEquals('sorting_center', $this->sortingCenter->role); // Role remains protected
        $this->assertEquals('Nexora Hub Updated Laguna', $this->sortingCenter->sortingCenterProfile->business_name);
    }
}
