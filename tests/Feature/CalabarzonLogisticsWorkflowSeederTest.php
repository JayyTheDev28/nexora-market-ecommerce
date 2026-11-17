<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Category;
use App\Models\CourierProfile;
use App\Models\Hub;
use App\Models\LogisticsProfile;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RiderEarning;
use App\Models\SellerProfile;
use App\Models\SortingCenterProfile;
use App\Models\TransferManifest;
use App\Models\TransferManifestItem;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\CalabarzonLogisticsWorkflowSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CalabarzonLogisticsWorkflowSeederTest extends TestCase
{
    use DatabaseTransactions;

    private const PROVINCES = [
        '043400000' => [
            'name' => 'Laguna',
            'hubs' => [
                ['043424000', 'San Pablo'],
                ['043426000', 'Santa Cruz'],
                ['043405000', 'Calamba'],
                ['043428000', 'Santa Rosa'],
                ['043403000', 'Biñan'],
            ],
        ],
        '042100000' => [
            'name' => 'Cavite',
            'hubs' => [
                ['042106000', 'Dasmariñas'],
                ['042103000', 'Bacoor'],
                ['042109000', 'Imus'],
                ['042108000', 'General Trias'],
                ['042119000', 'Tagaytay'],
            ],
        ],
        '041000000' => [
            'name' => 'Batangas',
            'hubs' => [
                ['041005000', 'Batangas City'],
                ['041014000', 'Lipa'],
                ['041031000', 'Tanauan'],
                ['041020000', 'Nasugbu'],
                ['041001000', 'Agoncillo'],
            ],
        ],
        '045600000' => [
            'name' => 'Quezon',
            'hubs' => [
                ['045624000', 'Lucena'],
                ['045647000', 'Tayabas'],
                ['045644000', 'Sariaya'],
                ['045608000', 'Candelaria'],
                ['045623000', 'Lucban'],
            ],
        ],
        '045800000' => [
            'name' => 'Rizal',
            'hubs' => [
                ['045802000', 'Antipolo'],
                ['045805000', 'Cainta'],
                ['045813000', 'Taytay'],
                ['045808000', 'Rodriguez'],
                ['045804000', 'Binangonan'],
            ],
        ],
    ];

    /** @var array<string, User> */
    private array $companies = [];

    private Order $genuineOrder;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->seedOfflineCalabarzonFixture();
    }

    public function test_calabarzon_workflow_seeder_creates_strictly_separated_workflows_and_is_repeatable(): void
    {
        $preOrderCount = Order::count();
        $preManifestCount = TransferManifest::count();
        $preEarningCount = RiderEarning::count();

        $seeder = app(CalabarzonLogisticsWorkflowSeeder::class);
        $seeder->run();

        // Repeat run must be idempotent and never duplicate orders/manifests or fail on pre-existing financials
        $seeder->run();

        $this->assertSame($preOrderCount + 60, Order::count());
        $this->assertSame($preManifestCount + 10, TransferManifest::count());
        $this->assertSame($preEarningCount, RiderEarning::count());
        $this->assertTrue(Order::whereKey($this->genuineOrder->id)->exists());

        $pickups = Order::where('order_number', 'like', 'NX-CBZ-PKP-%')->get();
        $unsorted = Order::where('order_number', 'like', 'NX-CBZ-SRT-%')->get();
        $sorted = Order::where('order_number', 'like', 'NX-CBZ-ASN-%')->get();
        $hth = Order::where('order_number', 'like', 'NX-CBZ-HTH-%')->get();
        $ltl = Order::where('order_number', 'like', 'NX-CBZ-LTL-%')->get();

        $this->assertCount(10, $pickups);
        $this->assertCount(10, $unsorted);
        $this->assertCount(10, $sorted);
        $this->assertCount(15, $hth);
        $this->assertCount(15, $ltl);

        // No seeded parcel appears in multiple groups or has riders/transfers/PODs/financials
        $allSeeded = $pickups->concat($unsorted)->concat($sorted)->concat($hth)->concat($ltl);
        $this->assertCount(60, $allSeeded->pluck('id')->unique());
        $this->assertCount(60, $allSeeded->pluck('tracking_number')->unique());

        foreach ($allSeeded as $order) {
            $this->assertSame('cod', $order->payment_method);
            $this->assertSame('unpaid', $order->payment_status);
            $this->assertNull($order->pickup_courier_id);
            $this->assertNull($order->pickup_rider_id);
            $this->assertNull($order->delivery_courier_id);
            $this->assertNull($order->delivery_rider_id);
            $this->assertNotNull($order->pickup_logistics_id);
            $this->assertNotNull($order->delivery_logistics_id);
            $this->assertNotNull($order->origin_hub_id);
            $this->assertNotNull($order->destination_hub_id);
            $this->assertGreaterThan(0, (float) $order->subtotal);
            $this->assertGreaterThan(0, (float) $order->shipping_fee);
            $this->assertSame(0, DB::table('rider_earnings')->where('order_id', $order->id)->count());
            $this->assertSame(0, DB::table('cod_collections')->where('order_id', $order->id)->count());
            $this->assertSame(0, DB::table('proof_of_deliveries')->where('order_id', $order->id)->count());
        }

        // Group 1: Merchant pickups awaiting assignment
        foreach ($pickups as $order) {
            $this->assertSame('ready_for_pickup', $order->status);
            $this->assertNull($order->current_hub_id);
            $this->assertSame(0, TransferManifestItem::where('order_id', $order->id)->count());
        }

        // Group 2: Parcel sorting awaiting sorting
        foreach ($unsorted as $order) {
            $this->assertSame('received_at_hub', $order->status);
            $this->assertSame((int) $order->destination_hub_id, (int) $order->current_hub_id);
            $this->assertSame((int) $order->delivery_logistics_id, (int) $order->current_custody_logistics_id);
            $this->assertNotNull($order->delivery_zone_id);
            $this->assertSame(0, TransferManifestItem::where('order_id', $order->id)->count());
        }

        // Group 3: Sorted unassigned awaiting delivery rider
        foreach ($sorted as $order) {
            $this->assertSame('sorted', $order->status);
            $this->assertSame((int) $order->destination_hub_id, (int) $order->current_hub_id);
            $this->assertSame((int) $order->delivery_logistics_id, (int) $order->current_custody_logistics_id);
            $this->assertNotNull($order->delivery_zone_id);
            $this->assertSame(0, TransferManifestItem::where('order_id', $order->id)->count());
        }

        // Group 4 & 5: 10 Draft manifests (5 internal + 5 intercompany), 30 items, 0 assigned transfer couriers
        $hthManifests = TransferManifest::where('notes', 'like', '%SEED-CBZ-HTH-M%')->with('items.order')->get();
        $ltlManifests = TransferManifest::where('notes', 'like', '%SEED-CBZ-LTL-M%')->with('items.order')->get();
        $this->assertCount(5, $hthManifests);
        $this->assertCount(5, $ltlManifests);

        foreach ($hthManifests as $manifest) {
            $this->assertSame(TransferManifest::STATUS_DRAFT, $manifest->status);
            $this->assertNull($manifest->assigned_courier_id);
            $this->assertSame((int) $manifest->sender_logistics_id, (int) $manifest->receiver_logistics_id);
            $this->assertNotSame((int) $manifest->origin_hub_id, (int) $manifest->destination_hub_id);
            $this->assertCount(3, $manifest->items);
            foreach ($manifest->items as $item) {
                $this->assertSame('pending', $item->loading_status);
                $this->assertSame('pending', $item->receiving_status);
                $this->assertSame((int) $manifest->origin_hub_id, (int) $item->order->current_hub_id);
                $this->assertNotSame((int) $item->order->current_hub_id, (int) $item->order->destination_hub_id);
            }
        }

        foreach ($ltlManifests as $manifest) {
            $this->assertSame(TransferManifest::STATUS_DRAFT, $manifest->status);
            $this->assertNull($manifest->assigned_courier_id);
            $this->assertNotSame((int) $manifest->sender_logistics_id, (int) $manifest->receiver_logistics_id);
            $this->assertCount(3, $manifest->items);
            foreach ($manifest->items as $item) {
                $this->assertSame('pending', $item->loading_status);
                $this->assertSame('pending', $item->receiving_status);
                $this->assertSame((int) $manifest->origin_hub_id, (int) $item->order->current_hub_id);
                $this->assertSame((int) $manifest->sender_logistics_id, (int) $item->order->current_custody_logistics_id);
            }
        }
    }

    public function test_logistics_dashboards_show_correct_queues_isolate_tenants_and_enforce_seller_pickup_lock(): void
    {
        app(CalabarzonLogisticsWorkflowSeeder::class)->run();

        $laguna = $this->companies['043400000'];

        // Pickups pending for Laguna (2 PKP parcels), and not Batangas PKP parcels
        $pickupHtml = $this->actingAs($laguna)->get(route('logistics.pickups.index'))->assertOk()->getContent();
        $this->assertStringContainsString('NX-CBZ-PKP-01', $pickupHtml);
        $this->assertStringContainsString('NX-CBZ-PKP-02', $pickupHtml);
        $this->assertStringNotContainsString('NX-CBZ-PKP-05', $pickupHtml);
        $this->assertStringNotContainsString('NX-CBZ-SRT-01', $pickupHtml);

        // Sorting unsorted queue for Laguna shows SRT-01/02, NOT ASN-01 or HTH/LTL transfer parcels
        $sortingHtml = $this->actingAs($laguna)->get(route('logistics.sorting.index'))->assertOk()->getContent();
        $this->assertStringContainsString('NX-CBZ-SRT-01', $sortingHtml);
        $this->assertStringNotContainsString('NX-CBZ-ASN-01', $sortingHtml);
        $this->assertStringNotContainsString('NX-CBZ-HTH-01', $sortingHtml);
        $this->assertStringNotContainsString('NX-CBZ-LTL-01', $sortingHtml);

        // Assign deliveries unassigned queue for Laguna shows ASN-01/02, NOT HTH/LTL or Cavite ASN
        $assignHtml = $this->actingAs($laguna)->get(route('logistics.assignments.index'))->assertOk()->getContent();
        $this->assertStringContainsString('NX-CBZ-ASN-01', $assignHtml);
        $this->assertStringNotContainsString('NX-CBZ-HTH-01', $assignHtml);
        $this->assertStringNotContainsString('NX-CBZ-LTL-01', $assignHtml);
        $this->assertStringNotContainsString('NX-CBZ-ASN-03', $assignHtml);

        // Incoming hub receiving & transfer manifests endpoints return 200 and include Laguna draft manifests
        $this->actingAs($laguna)->get(route('logistics.incoming.index'))->assertOk();
        $transferHtml = $this->actingAs($laguna)->get(route('logistics.transfers.index'))->assertOk()->getContent();
        $hthManifest = TransferManifest::where('notes', 'like', '%SEED-CBZ-HTH-M1%')->firstOrFail();
        $ltlManifest = TransferManifest::where('notes', 'like', '%SEED-CBZ-LTL-M1%')->firstOrFail();
        $this->assertStringContainsString($hthManifest->manifest_number, $transferHtml);
        $this->assertStringContainsString($ltlManifest->manifest_number, $transferHtml);

        // Seller cannot mark ready_for_pickup seeded order as picked_up
        $pkpOrder = Order::where('order_number', 'NX-CBZ-PKP-01')->firstOrFail();
        $seller = User::findOrFail($pkpOrder->seller_id);
        $this->actingAs($seller)
            ->post(route('seller.orders.advance', $pkpOrder), ['status' => 'picked_up'])
            ->assertStatus(403);

        $this->assertSame('ready_for_pickup', $pkpOrder->fresh()->status);
    }

    private function seedOfflineCalabarzonFixture(): void
    {
        $category = Category::first() ?? Category::create([
            'name' => 'General Goods',
            'slug' => 'general-goods-' . uniqid(),
            'description' => 'Fixture category',
        ]);

        foreach (self::PROVINCES as $provinceCode => $meta) {
            $company = User::where('email', "logistics.{$provinceCode}@nexora.test")->first();
            if (! $company) {
                $company = User::create([
                    'first_name' => $meta['name'],
                    'last_name' => 'Logistics',
                    'sex' => 'other',
                    'birthday' => '1990-01-01',
                    'age' => 36,
                    'contact_number' => '0917' . substr($provinceCode, 0, 7),
                    'email' => "logistics.{$provinceCode}@nexora.test",
                    'password' => Hash::make('Password123!@#'),
                    'role' => 'logistics',
                    'approval_status' => 'approved',
                    'account_status' => 'active',
                    'email_verified_at' => now(),
                ]);

                LogisticsProfile::create([
                    'user_id' => $company->id,
                    'company_name' => "Nexora {$meta['name']} Logistics (TEST)",
                    'sec_registration_no' => 'SEC-' . $provinceCode,
                    'tin' => '900-' . substr($provinceCode, 0, 3) . '-' . substr($provinceCode, 1, 3) . '-000',
                    'region' => 'Region IV-A (CALABARZON)',
                    'province' => $meta['name'],
                    'city_municipality' => $meta['hubs'][0][1],
                    'barangay' => 'Poblacion',
                    'street_address' => 'Test Hub Ave',
                    'postal_code' => '4000',
                    'contact_person_first_name' => $meta['name'],
                    'contact_person_last_name' => 'Ops',
                    'position' => 'Manager',
                    'email' => $company->email,
                    'mobile_number' => $company->contact_number,
                    'service_areas' => [$provinceCode],
                    'status' => 'approved',
                ]);

                SortingCenterProfile::create([
                    'user_id' => $company->id,
                    'business_name' => "Nexora {$meta['name']} Logistics (TEST)",
                ]);
            }
            $this->companies[$provinceCode] = $company;

            foreach ($meta['hubs'] as $idx => [$cityCode, $cityName]) {
                $hubCode = 'NX-HUB-' . $provinceCode . '-' . ($idx + 1);
                $hub = Hub::where('code', $hubCode)->first();
                if (! $hub) {
                    $brgyCode = substr($cityCode, 0, 6) . '001';
                    $hub = Hub::create([
                        'logistics_company_id' => $company->id,
                        'name' => "{$meta['name']} {$cityName} Hub",
                        'code' => $hubCode,
                        'province_code' => $provinceCode,
                        'province_name' => $meta['name'],
                        'city_code' => $cityCode,
                        'city_name' => $cityName,
                        'barangay_code' => $brgyCode,
                        'barangay_name' => 'Barangay I',
                        'street_address' => 'Hub Road',
                        'contact_number' => '0917111000' . ($idx + 1),
                        'is_active' => true,
                    ]);
                }

                Zone::firstOrCreate(
                    ['logistics_company_id' => $company->id, 'city_code' => $cityCode],
                    [
                        'hub_id' => $hub->id,
                        'name' => "{$cityName} Delivery Zone",
                        'code' => 'NX-Z-' . $cityCode,
                        'province_code' => $provinceCode,
                        'province_name' => $meta['name'],
                        'city_name' => $cityName,
                        'is_active' => true,
                    ]
                );

                // Ensure at least one seller + active product in each hub city
                $sellerEmail = "seller.test.{$cityCode}@nexora.test";
                if (! User::where('email', $sellerEmail)->exists()) {
                    $seller = User::create([
                        'first_name' => $cityName,
                        'last_name' => 'Seller',
                        'sex' => 'female',
                        'birthday' => '1992-01-01',
                        'age' => 34,
                        'contact_number' => '0918' . substr($cityCode, 2, 7),
                        'email' => $sellerEmail,
                        'password' => Hash::make('Password123!@#'),
                        'role' => 'seller',
                        'approval_status' => 'approved',
                        'account_status' => 'active',
                        'email_verified_at' => now(),
                    ]);

                    SellerProfile::create([
                        'user_id' => $seller->id,
                        'business_name' => "{$cityName} Test Store",
                        'category' => $category->slug,
                        'description' => 'Test store fixture',
                        'contact_phone' => $seller->contact_number,
                        'contact_email' => $seller->email,
                        'is_vacation_mode' => false,
                    ]);

                    Address::create([
                        'user_id' => $seller->id,
                        'region_code' => '040000000',
                        'region_name' => 'Region IV-A (CALABARZON)',
                        'province_code' => $provinceCode,
                        'province_name' => $meta['name'],
                        'municipality_code' => $cityCode,
                        'municipality_name' => $cityName,
                        'barangay_code' => substr($cityCode, 0, 6) . '001',
                        'barangay_name' => 'Barangay I',
                        'street' => 'Seller St',
                        'house_number' => '10',
                        'recipient_name' => "{$cityName} Seller",
                        'recipient_phone' => $seller->contact_number,
                        'is_default' => true,
                    ]);

                    Product::create([
                        'seller_id' => $seller->id,
                        'category_id' => $category->id,
                        'sku' => 'TEST-SKU-' . $cityCode . '-' . uniqid(),
                        'name' => "{$cityName} Sample Item",
                        'slug' => 'sample-' . $cityCode . '-' . uniqid(),
                        'description' => 'Test item',
                        'price' => 250.00,
                        'stock' => 25,
                        'weight' => 0.8,
                        'dimensions' => '20x15x10 cm',
                        'status' => 'active',
                    ]);
                }

                // Ensure at least one buyer in each hub zone city
                $buyerEmail = "buyer.test.{$cityCode}@nexora.test";
                if (! User::where('email', $buyerEmail)->exists()) {
                    $buyer = User::create([
                        'first_name' => $cityName,
                        'last_name' => 'Buyer',
                        'sex' => 'male',
                        'birthday' => '1994-05-05',
                        'age' => 32,
                        'contact_number' => '0919' . substr($cityCode, 2, 7),
                        'email' => $buyerEmail,
                        'password' => Hash::make('Password123!@#'),
                        'role' => 'buyer',
                        'approval_status' => 'approved',
                        'account_status' => 'active',
                        'email_verified_at' => now(),
                    ]);

                    Address::create([
                        'user_id' => $buyer->id,
                        'region_code' => '040000000',
                        'region_name' => 'Region IV-A (CALABARZON)',
                        'province_code' => $provinceCode,
                        'province_name' => $meta['name'],
                        'municipality_code' => $cityCode,
                        'municipality_name' => $cityName,
                        'barangay_code' => substr($cityCode, 0, 6) . '001',
                        'barangay_name' => 'Barangay I',
                        'street' => 'Buyer Ave',
                        'house_number' => '20',
                        'recipient_name' => "{$cityName} Buyer",
                        'recipient_phone' => $buyer->contact_number,
                        'is_default' => true,
                    ]);
                }
            }
        }

        // Pre-existing unrelated order + RiderEarning to verify CalabarzonLogisticsWorkflowSeeder never wipes genuine transactions
        $lagunaSeller = User::whereHas('address', fn ($q) => $q->where('province_code', '043400000'))
            ->where('role', 'seller')
            ->firstOrFail();
        $lagunaBuyer = User::whereHas('address', fn ($q) => $q->where('province_code', '043400000'))
            ->where('role', 'buyer')
            ->with('address')
            ->firstOrFail();
        $lagunaCompany = $this->companies['043400000'];

        $rider = User::create([
            'first_name' => 'Genuine',
            'last_name' => 'Rider',
            'sex' => 'male',
            'birthday' => '1993-03-03',
            'age' => 33,
            'contact_number' => '09179998877',
            'email' => 'genuine.rider.' . uniqid() . '@nexora.test',
            'password' => Hash::make('Password123!@#'),
            'role' => 'courier',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
        CourierProfile::create([
            'user_id' => $rider->id,
            'sorting_center_id' => $lagunaCompany->id,
            'logistics_company_id' => $lagunaCompany->id,
            'vehicle_type' => 'Motorcycle',
            'plate_number' => 'GEN-101',
            'license_number' => 'LIC-GEN-101',
            'license_expiry_date' => now()->addYear()->toDateString(),
        ]);

        $this->genuineOrder = Order::create([
            'order_number' => 'NX-GENUINE-' . uniqid(),
            'tracking_number' => 'NX-TRK-GEN-' . uniqid(),
            'buyer_id' => $lagunaBuyer->id,
            'seller_id' => $lagunaSeller->id,
            'address_id' => $lagunaBuyer->address->id,
            'address_snapshot' => ['full_address' => $lagunaBuyer->address->full_address],
            'status' => 'delivered',
            'payment_method' => 'cod',
            'payment_status' => 'paid',
            'subtotal' => 250,
            'discount' => 0,
            'shipping_fee' => 45,
            'total' => 295,
            'placed_at' => now()->subDays(2),
            'delivered_at' => now()->subDay(),
            'delivery_courier_id' => $rider->id,
        ]);

        OrderItem::create([
            'order_id' => $this->genuineOrder->id,
            'product_id' => Product::where('seller_id', $lagunaSeller->id)->firstOrFail()->id,
            'product_name' => 'Genuine Item',
            'price' => 250,
            'quantity' => 1,
        ]);

        RiderEarning::create([
            'courier_id' => $rider->id,
            'logistics_company_id' => $lagunaCompany->id,
            'order_id' => $this->genuineOrder->id,
            'earning_key' => 'gen:' . $this->genuineOrder->id,
            'type' => 'delivery',
            'amount' => 35.00,
            'status' => 'pending',
            'description' => 'Genuine pre-existing delivery earning',
            'earned_at' => now()->subDay(),
        ]);
    }
}
