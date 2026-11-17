<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\ParcelIncident;
use App\Models\ParcelIncidentEvent;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\SortingCenterProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LatestOrderingWebTest extends TestCase
{
    use DatabaseTransactions;

    private User $logistics;
    private User $seller;
    private User $buyer;
    private Address $address;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logistics = User::create([
            'first_name' => 'Sorting',
            'last_name' => 'Hub',
            'sex' => 'other',
            'birthday' => '1990-01-01',
            'age' => 35,
            'contact_number' => '09171110001',
            'email' => 'logistics_' . uniqid() . '@nexora.test',
            'password' => Hash::make('password'),
            'role' => 'sorting_center',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        SortingCenterProfile::create([
            'user_id' => $this->logistics->id,
            'business_name' => 'Nexora Laguna Hub',
        ]);

        $this->seller = User::create([
            'first_name' => 'Latest',
            'last_name' => 'Seller',
            'sex' => 'female',
            'birthday' => '1992-02-02',
            'age' => 33,
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
            'business_name' => 'Latest Test Shop',
            'category' => 'General Merchandise',
        ]);

        $this->buyer = User::create([
            'first_name' => 'Latest',
            'last_name' => 'Buyer',
            'sex' => 'male',
            'birthday' => '1995-05-05',
            'age' => 30,
            'contact_number' => '09171110003',
            'email' => 'buyer_' . uniqid() . '@nexora.test',
            'password' => Hash::make('password'),
            'role' => 'buyer',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $this->address = Address::create([
            'user_id' => $this->buyer->id,
            'province_code' => '043400000',
            'province_name' => 'Laguna',
            'municipality_code' => '043424000',
            'municipality_name' => 'San Pablo City',
            'barangay_code' => '043424001',
            'barangay_name' => 'Barangay I-A',
            'street' => 'Main St',
            'house_number' => '10',
        ]);

        $category = Category::firstOrCreate(
            ['slug' => 'test-cat-' . uniqid()],
            ['name' => 'Test Cat', 'icon' => 'inventory_2']
        );

        $this->product = Product::create([
            'seller_id' => $this->seller->id,
            'category_id' => $category->id,
            'name' => 'Order Timeline Widget',
            'slug' => 'widget-' . uniqid(),
            'price' => 500,
            'stock' => 25,
            'status' => 'active',
        ]);
    }

    private function createOrder(string $status, string $tracking, \Carbon\Carbon $placedAt, \Carbon\Carbon $updatedAt): Order
    {
        $order = new Order([
            'order_number' => 'NX-ORD-' . strtoupper(uniqid()),
            'tracking_number' => $tracking,
            'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'address_id' => $this->address->id,
            'status' => $status,
            'payment_method' => 'cod',
            'subtotal' => 500,
            'discount' => 0,
            'shipping_fee' => 50,
            'total' => 550,
            'sorting_center_id' => $this->logistics->id,
            'pickup_logistics_id' => $this->logistics->id,
            'current_custody_logistics_id' => $this->logistics->id,
            'placed_at' => $placedAt,
        ]);
        $order->timestamps = false;
        $order->created_at = $placedAt;
        $order->updated_at = $updatedAt;
        $order->save();

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'price' => 500,
            'quantity' => 1,
        ]);

        return $order;
    }

    public function test_order_and_incident_timelines_display_latest_entry_first(): void
    {
        $t0 = now()->subHours(5);
        $t1 = now()->subHours(2);
        $t2 = now()->subMinutes(10);

        $order = $this->createOrder('out_for_delivery', 'NX-TRK-TIMELINE-1', $t0, $t2);

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'status' => 'placed',
            'changed_by' => $this->buyer->id,
            'note' => 'Order created',
            'changed_at' => $t0,
        ]);
        OrderStatusHistory::create([
            'order_id' => $order->id,
            'status' => 'ready_for_pickup',
            'changed_by' => $this->seller->id,
            'note' => 'Seller packed',
            'changed_at' => $t1,
        ]);
        OrderStatusHistory::create([
            'order_id' => $order->id,
            'status' => 'out_for_delivery',
            'changed_by' => $this->logistics->id,
            'note' => 'Rider on the way',
            'changed_at' => $t2,
        ]);

        // Seller order detail shows latest ("out for delivery") before "ready for pickup" before "placed"
        $sellerHtml = $this->actingAs($this->seller)
            ->get(route('seller.orders.show', $order))
            ->assertOk()
            ->getContent();
        $sellerTimeline = substr($sellerHtml, (int) strpos($sellerHtml, 'Lifecycle Audit Timeline'));
        $this->assertNotFalse(stripos($sellerTimeline, 'out for delivery'));
        $this->assertNotFalse(stripos($sellerTimeline, 'ready for pickup'));
        $this->assertNotFalse(stripos($sellerTimeline, 'placed'));
        $this->assertLessThan(
            stripos($sellerTimeline, 'ready for pickup'),
            stripos($sellerTimeline, 'out for delivery')
        );
        $this->assertLessThan(
            stripos($sellerTimeline, 'placed'),
            stripos($sellerTimeline, 'ready for pickup')
        );

        // Logistics pickup & delivery details show latest before earliest inside their timelines
        $pickupHtml = $this->actingAs($this->logistics)
            ->get(route('logistics.pickups.show', $order))
            ->assertOk()
            ->getContent();
        $pickupTimeline = substr($pickupHtml, (int) strpos($pickupHtml, 'Audit & Status History'));
        $this->assertLessThan(
            stripos($pickupTimeline, 'Ready For Pickup'),
            stripos($pickupTimeline, 'Out For Delivery')
        );

        $deliveryHtml = $this->actingAs($this->logistics)
            ->get(route('logistics.deliveries.show', $order))
            ->assertOk()
            ->getContent();
        $deliveryTimeline = substr($deliveryHtml, (int) strpos($deliveryHtml, 'Full Order Lifecycle'));
        $this->assertLessThan(
            stripos($deliveryTimeline, 'Ready For Pickup'),
            stripos($deliveryTimeline, 'Out For Delivery')
        );

        // Buyer orders timeline shows "Out for Delivery" before "Order Placed"
        $buyerHtml = $this->actingAs($this->buyer)
            ->get(route('buyer.orders'))
            ->assertOk()
            ->getContent();
        $buyerTimeline = substr($buyerHtml, (int) strpos($buyerHtml, 'Shipment &amp; Transfer Timeline'));
        $this->assertLessThan(
            strpos($buyerTimeline, 'Order Placed'),
            strpos($buyerTimeline, 'Out for Delivery')
        );

        // Parcel incident timeline shows latest event before older event
        $incident = ParcelIncident::create([
            'order_id' => $order->id,
            'logistics_company_id' => $this->logistics->id,
            'incident_number' => 'INC-' . strtoupper(uniqid()),
            'tracking_number' => $order->tracking_number,
            'incident_type' => 'damaged',
            'source_stage' => 'sorting',
            'investigation_status' => 'under_investigation',
            'description' => 'Damaged box corner',
            'reported_by' => $this->logistics->id,
            'reported_at' => $t1,
        ]);

        ParcelIncidentEvent::create([
            'incident_id' => $incident->id,
            'event_type' => 'reported',
            'actor_id' => $this->logistics->id,
            'actor_role' => 'logistics',
            'notes' => 'FIRST_EVENT_REPORTED',
            'occurred_at' => $t1,
        ]);
        ParcelIncidentEvent::create([
            'incident_id' => $incident->id,
            'event_type' => 'investigation_updated',
            'actor_id' => $this->logistics->id,
            'actor_role' => 'logistics',
            'notes' => 'LATEST_EVENT_INVESTIGATED',
            'occurred_at' => $t2,
        ]);

        $exceptionHtml = $this->actingAs($this->logistics)
            ->get(route('logistics.exceptions.show', $incident))
            ->assertOk()
            ->getContent();
        $this->assertLessThan(
            strpos($exceptionHtml, 'FIRST_EVENT_REPORTED'),
            strpos($exceptionHtml, 'LATEST_EVENT_INVESTIGATED')
        );
    }

    public function test_logistics_and_seller_queues_show_latest_updated_parcels_first(): void
    {
        $olderCreatedOlderUpdate = now()->subHours(4);
        $olderCreatedRecentUpdate = now()->subMinutes(5);
        $newerCreatedStaleUpdate = now()->subHours(1);

        // Incoming parcels (picked_up): older order just updated should appear BEFORE newer created order with stale updated_at
        $latestActivePickup = $this->createOrder('picked_up', 'NX-IN-LATEST', now()->subDays(2), $olderCreatedRecentUpdate);
        $staleNewOrder = $this->createOrder('picked_up', 'NX-IN-STALE', $newerCreatedStaleUpdate, $olderCreatedOlderUpdate);

        $incomingHtml = $this->actingAs($this->logistics)
            ->get(route('logistics.incoming.index', ['tab' => 'all']))
            ->assertOk()
            ->getContent();
        $this->assertLessThan(
            strpos($incomingHtml, 'NX-IN-STALE'),
            strpos($incomingHtml, 'NX-IN-LATEST')
        );

        // Sorting parcels (at_sorting_center)
        $latestSort = $this->createOrder('at_sorting_center', 'NX-SRT-LATEST', now()->subDays(2), $olderCreatedRecentUpdate);
        $staleSort = $this->createOrder('at_sorting_center', 'NX-SRT-STALE', $newerCreatedStaleUpdate, $olderCreatedOlderUpdate);

        $sortingHtml = $this->actingAs($this->logistics)
            ->get(route('logistics.sorting.index', ['tab' => 'all']))
            ->assertOk()
            ->getContent();
        $this->assertLessThan(
            strpos($sortingHtml, 'NX-SRT-STALE'),
            strpos($sortingHtml, 'NX-SRT-LATEST')
        );

        // Rider assignments (sorted)
        $latestAssign = $this->createOrder('sorted', 'NX-ASN-LATEST', now()->subDays(2), $olderCreatedRecentUpdate);
        $staleAssign = $this->createOrder('sorted', 'NX-ASN-STALE', $newerCreatedStaleUpdate, $olderCreatedOlderUpdate);

        $assignHtml = $this->actingAs($this->logistics)
            ->get(route('logistics.assignments.index', ['tab' => 'all']))
            ->assertOk()
            ->getContent();
        $this->assertLessThan(
            strpos($assignHtml, 'NX-ASN-STALE'),
            strpos($assignHtml, 'NX-ASN-LATEST')
        );

        // Seller dashboard actionable orders should show newest placed/updated order before older one
        $olderSellerOrder = $this->createOrder('placed', 'NX-SEL-OLDER', now()->subDays(1), now()->subDays(1));
        $newerSellerOrder = $this->createOrder('placed', 'NX-SEL-NEWER', now()->subMinutes(2), now()->subMinutes(2));

        $sellerDashHtml = $this->actingAs($this->seller)
            ->get(route('seller.dashboard'))
            ->assertOk()
            ->getContent();
        $this->assertLessThan(
            strpos($sellerDashHtml, $olderSellerOrder->order_number),
            strpos($sellerDashHtml, $newerSellerOrder->order_number)
        );
    }
}
