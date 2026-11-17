<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\CompensationPolicy;
use App\Models\CourierProfile;
use App\Models\Hub;
use App\Models\HubTransfer;
use App\Models\InterCompanyShippingAllocation;
use App\Models\Order;
use App\Models\Product;
use App\Models\RiderEarning;
use App\Models\ShippingRatePolicy;
use App\Models\TransferManifest;
use App\Models\User;
use App\Models\UserVoucher;
use App\Models\Voucher;
use App\Models\Zone;
use App\Models\ZoneBarangay;
use App\Services\CompensationPolicyService;
use App\Services\FinancialReconciliationService;
use App\Services\LogisticsProfitabilityService;
use App\Services\ShippingPricingService;
use App\Services\TransferManifestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SmartShippingAndProfitabilityTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role, string $prefix, ?int $logisticsCompanyId = null): User
    {
        $user = User::create([
            'first_name' => $prefix,
            'last_name' => ucfirst($role),
            'email' => strtolower($prefix) . '.' . uniqid() . '@nexora.test',
            'contact_number' => '0917' . rand(1000000, 9999999),
            'birthday' => '1994-05-10',
            'age' => 32,
            'sex' => 'other',
            'password' => Hash::make('Password123!@#'),
            'role' => $role,
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        if (in_array($role, ['rider', 'courier'], true) && $logisticsCompanyId) {
            CourierProfile::create([
                'user_id' => $user->id,
                'logistics_company_id' => $logisticsCompanyId,
                'sorting_center_id' => $logisticsCompanyId,
                'vehicle_type' => $role === 'courier' ? 'van' : 'motorcycle',
                'plate_number' => 'NX-' . rand(100, 999),
                'license_number' => 'LIC-' . rand(1000, 9999),
            ]);
        }

        return $user;
    }

    private function makeHubWithZone(
        User $company,
        string $name,
        string $provinceCode,
        string $provinceName,
        string $cityCode,
        string $cityName,
        string $barangayCode,
        string $barangayName
    ): Hub {
        $hub = Hub::create([
            'logistics_company_id' => $company->id,
            'name' => $name,
            'code' => strtoupper(substr(preg_replace('/\W+/', '', $name), 0, 5)) . '-' . rand(10, 99),
            'province_code' => $provinceCode,
            'province_name' => $provinceName,
            'city_code' => $cityCode,
            'city_name' => $cityName,
            'barangay_code' => $barangayCode,
            'barangay_name' => $barangayName,
            'is_active' => true,
        ]);

        $zone = Zone::create([
            'logistics_company_id' => $company->id,
            'hub_id' => $hub->id,
            'name' => "{$cityName} Zone",
            'code' => 'ZN-' . rand(100, 999),
            'province_code' => $provinceCode,
            'province_name' => $provinceName,
            'city_code' => $cityCode,
            'city_name' => $cityName,
            'is_active' => true,
        ]);

        ZoneBarangay::create([
            'zone_id' => $zone->id,
            'logistics_company_id' => $company->id,
            'barangay_code' => $barangayCode,
            'barangay_name' => $barangayName,
            'city_code' => $cityCode,
            'city_name' => $cityName,
            'province_code' => $provinceCode,
            'province_name' => $provinceName,
        ]);

        return $hub;
    }

    private function makeAddress(User $user, string $provCode, string $provName, string $cityCode, string $cityName, string $brgyCode, string $brgyName, bool $default = true): Address
    {
        return Address::create([
            'user_id' => $user->id,
            'recipient_name' => $user->full_name,
            'recipient_phone' => '09171234567',
            'region_code' => '040000000',
            'region_name' => 'Region IV-A',
            'province_code' => $provCode,
            'province_name' => $provName,
            'municipality_code' => $cityCode,
            'municipality_name' => $cityName,
            'barangay_code' => $brgyCode,
            'barangay_name' => $brgyName,
            'street' => 'Main St',
            'is_default' => $default,
        ]);
    }

    public function test_smart_shipping_cost_margin_formula_route_classification_volumetric_weight_and_serviceability(): void
    {
        $lagunaCo = $this->makeUser('logistics', 'LagunaLog');
        $caviteCo = $this->makeUser('logistics', 'CaviteLog');
        $seller = $this->makeUser('seller', 'SanPabloStore');
        $buyer = $this->makeUser('buyer', 'SmartBuyer');

        $this->makeHubWithZone($lagunaCo, 'San Pablo Hub', '0434', 'Laguna', '043424', 'San Pablo City', '043424001', 'Bagong Pook');
        $this->makeHubWithZone($lagunaCo, 'Calamba Hub', '0434', 'Laguna', '043405', 'Calamba City', '043405001', 'Real');
        $this->makeHubWithZone($caviteCo, 'Cavite Hub', '0421', 'Cavite', '042103', 'Bacoor City', '042103001', 'Alima');

        $this->makeAddress($seller, '0434', 'Laguna', '043424', 'San Pablo City', '043424001', 'Bagong Pook');
        $sanPabloBuyerAddr = $this->makeAddress($buyer, '0434', 'Laguna', '043424', 'San Pablo City', '043424001', 'Bagong Pook', true);
        $calambaBuyerAddr = $this->makeAddress($buyer, '0434', 'Laguna', '043405', 'Calamba City', '043405001', 'Real', false);
        $caviteBuyerAddr = $this->makeAddress($buyer, '0421', 'Cavite', '042103', 'Bacoor City', '042103001', 'Alima', false);
        $uncoveredBuyerAddr = $this->makeAddress($buyer, '0505', 'Albay', '050506', 'Legazpi City', '050506001', 'Bano', false);

        $cat = Category::create(['name' => 'Gadgets', 'slug' => 'gadgets', 'is_active' => true]);
        $standardProduct = Product::create([
            'seller_id' => $seller->id,
            'category_id' => $cat->id,
            'name' => 'Compact Mouse',
            'slug' => 'compact-mouse',
            'description' => '1kg parcel',
            'price' => 350.00,
            'weight' => 1.0,
            'stock' => 20,
            'status' => 'active',
        ]);
        $bulkyProduct = Product::create([
            'seller_id' => $seller->id,
            'category_id' => $cat->id,
            'name' => 'Large Box Fan',
            'slug' => 'large-box-fan',
            'description' => 'Volumetric bulky item',
            'price' => 1500.00,
            'weight' => 1.0,
            'dimensions' => '50x40x30', // 60000 / 5000 = 12 kg volumetric
            'stock' => 10,
            'status' => 'active',
        ]);

        // Policy 1: Exact cost/margin example: ₱10 + ₱30 + ₱20 + ₱10 = ₱70, 20% target margin => ₱87.50
        $exactPolicy = ShippingRatePolicy::create([
            'name' => 'Exact Formula Test Policy',
            'version' => 2,
            'status' => ShippingRatePolicy::STATUS_ACTIVE,
            'pricing_mode' => ShippingRatePolicy::MODE_SMART_COST_MARGIN,
            'review_status' => ShippingRatePolicy::REVIEW_APPROVED,
            'contract_reference' => 'EXACT-2026',
            'base_fee' => 50.00,
            'base_weight_kg' => 1.00,
            'per_additional_kg_fee' => 10.00,
            'minimum_fee' => 50.00,
            'target_profit_margin_pct' => 20.00,
            'rounding_increment' => 0.00,
            'estimated_pickup_cost' => 10.00,
            'estimated_delivery_cost' => 30.00,
            'estimated_transfer_cost_per_parcel' => 20.00,
            'estimated_hub_operating_cost' => 10.00,
            'oversized_surcharge' => 15.00,
            'origin_logistics_share_pct' => 35.00,
            'transfer_carrier_share_pct' => 20.00,
            'delivery_logistics_share_pct' => 45.00,
            'effective_from' => now(),
        ]);

        $pricing = app(ShippingPricingService::class);

        // Interprovincial (San Pablo -> Cavite): ₱70 / 0.80 = ₱87.50
        $interQuote = $pricing->calculateSmartQuote([$standardProduct], $seller->id, $caviteBuyerAddr, null, $exactPolicy);
        $this->assertTrue($interQuote['serviceable']);
        $this->assertSame('interprovincial', $interQuote['route_type']);
        $this->assertEquals(70.00, $interQuote['breakdown']['total_estimated_cost']);
        $this->assertEquals(87.50, $interQuote['shipping_fee']);

        // With ₱5 rounding increment: ₱87.50 rounds to ₱90.00
        $exactPolicy->update(['rounding_increment' => 5.00]);
        $roundedQuote = $pricing->calculateSmartQuote([$standardProduct], $seller->id, $caviteBuyerAddr, null, $exactPolicy);
        $this->assertEquals(87.50, $roundedQuote['breakdown']['raw_formula_fee']);
        $this->assertEquals(90.00, $roundedQuote['shipping_fee']);

        // Same-City (San Pablo -> San Pablo): zero transfer cost => cost ₱10 + ₱30 + ₱0 + ₱10 = ₱50; ₱50 / 0.80 = ₱62.50 -> ₱65.00
        $localQuote = $pricing->calculateSmartQuote([$standardProduct], $seller->id, $sanPabloBuyerAddr, null, $exactPolicy);
        $this->assertSame('same_city', $localQuote['route_type']);
        $this->assertEquals(0.00, $localQuote['breakdown']['estimated_transfer_cost']);
        $this->assertEquals(65.00, $localQuote['shipping_fee']);

        // Same-Province (San Pablo -> Calamba): same_province route type with transfer cost
        $provQuote = $pricing->calculateSmartQuote([$standardProduct], $seller->id, $calambaBuyerAddr, null, $exactPolicy);
        $this->assertSame('same_province', $provQuote['route_type']);
        $this->assertEquals(90.00, $provQuote['shipping_fee']);

        // Volumetric / oversized parcel check (12kg billed + ₱15 oversized surcharge)
        $bulkyQuote = $pricing->calculateSmartQuote([$bulkyProduct], $seller->id, $caviteBuyerAddr, null, $exactPolicy);
        $this->assertTrue($bulkyQuote['is_oversized']);
        $this->assertEquals(12, $bulkyQuote['billed_weight_kg']);
        $this->assertGreaterThan($roundedQuote['shipping_fee'], $bulkyQuote['shipping_fee']);

        // Unserviceable destination rejected cleanly
        $uncoveredQuote = $pricing->calculateSmartQuote([$standardProduct], $seller->id, $uncoveredBuyerAddr, null, $exactPolicy);
        $this->assertFalse($uncoveredQuote['serviceable']);
        $this->assertNotEmpty($uncoveredQuote['serviceability_error']);
    }

    public function test_web_and_mobile_checkout_synchronize_with_shipping_policy_and_voucher_funding(): void
    {
        $admin = $this->makeUser('admin', 'PricingAdmin');
        $lagunaCo = $this->makeUser('logistics', 'LagunaCo');
        $caviteCo = $this->makeUser('logistics', 'CaviteCo');
        $seller = $this->makeUser('seller', 'LagunaMerchant');
        $buyer = $this->makeUser('buyer', 'SyncedBuyer');

        $this->makeHubWithZone($lagunaCo, 'San Pablo Hub', '0434', 'Laguna', '043424', 'San Pablo City', '043424001', 'Bagong Pook');
        $this->makeHubWithZone($caviteCo, 'Cavite Hub', '0421', 'Cavite', '042103', 'Bacoor City', '042103001', 'Alima');

        $this->makeAddress($seller, '0434', 'Laguna', '043424', 'San Pablo City', '043424001', 'Bagong Pook');
        $caviteAddr = $this->makeAddress($buyer, '0421', 'Cavite', '042103', 'Bacoor City', '042103001', 'Alima', true);

        // Admin configures and activates Smart Cost + 20% Margin + ₱5 rounding => ₱90.00 interprovincial shipping
        $this->actingAs($admin)->post(route('admin.shipping-policies.store'), [
            'name' => 'Admin Smart Interprovincial Policy',
            'pricing_mode' => 'smart_cost_margin',
            'contract_reference' => 'ADM-SMART-2026',
            'base_fee' => 50.00,
            'base_weight_kg' => 1.00,
            'per_additional_kg_fee' => 20.00,
            'minimum_fee' => 50.00,
            'target_profit_margin_pct' => 20.00,
            'rounding_increment' => 5.00,
            'estimated_pickup_cost' => 10.00,
            'estimated_delivery_cost' => 30.00,
            'estimated_transfer_cost_per_parcel' => 20.00,
            'estimated_hub_operating_cost' => 10.00,
            'origin_logistics_share_pct' => 35.00,
            'transfer_carrier_share_pct' => 20.00,
            'delivery_logistics_share_pct' => 45.00,
            'activate_now' => 1,
        ])->assertRedirect();

        $cat = Category::create(['name' => 'Apparel', 'slug' => 'apparel', 'is_active' => true]);
        $product = Product::create([
            'seller_id' => $seller->id,
            'category_id' => $cat->id,
            'name' => 'Cotton Polo',
            'slug' => 'cotton-polo',
            'description' => '1kg shirt',
            'price' => 500.00,
            'weight' => 1.0,
            'stock' => 50,
            'status' => 'active',
        ]);

        // Platform-funded shipping voucher (₱25 off shipping)
        $shippingVoucher = Voucher::create([
            'code' => 'PLATSHIP25',
            'type' => 'flat',
            'voucher_scope' => 'shipping',
            'funding_source' => 'platform',
            'value' => 25.00,
            'min_spend' => 100.00,
            'is_active' => true,
        ]);
        UserVoucher::create([
            'user_id' => $buyer->id,
            'voucher_id' => $shippingVoucher->id,
            'claimed_at' => now(),
        ]);

        $cartItem = CartItem::create([
            'user_id' => $buyer->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 500.00,
            'selected' => true,
        ]);

        // 1. Web Checkout Quote & Index both return ₱90.00 (no hardcoded ₱50 mismatch!)
        $this->actingAs($buyer)->get(route('checkout.index'))
            ->assertOk()
            ->assertSee('90.00');

        $this->actingAs($buyer)->postJson(route('checkout.quote'), ['address_id' => $caviteAddr->id])
            ->assertOk()
            ->assertJsonPath('total_shipping', 90)
            ->assertJsonPath('serviceable', true);

        // 2. Mobile Checkout Summary API returns exact same ₱90.00 + voucher preview
        Sanctum::actingAs($buyer);
        $this->getJson('/api/mobile/v1/buyer/checkout/summary?address_id=' . $caviteAddr->id . '&shipping_voucher_id=' . $shippingVoucher->id)
            ->assertOk()
            ->assertJsonPath('data.total_shipping', 90)
            ->assertJsonPath('data.shipping_discount', 25)
            ->assertJsonPath('data.all_serviceable', true);

        // 3. Place Order via Mobile API -> Buyer pays ₱65 shipping, Platform funds ₱25, base_shipping_fee = ₱90
        $placeResp = $this->postJson('/api/mobile/v1/buyer/checkout', [
            'address_id' => $caviteAddr->id,
            'payment_method' => 'cod',
            'cart_item_ids' => [$cartItem->id],
            'shipping_voucher_id' => $shippingVoucher->id,
        ])->assertStatus(201);

        $orderId = $placeResp->json('data.orders.0.id');
        $order = Order::findOrFail($orderId);

        $this->assertEquals(90.00, (float) $order->base_shipping_fee);
        $this->assertEquals(65.00, (float) $order->shipping_fee);
        $this->assertEquals(25.00, (float) $order->platform_shipping_subsidy);
        $this->assertSame('platform', $order->shipping_voucher_funder);
        $this->assertSame('interprovincial', $order->shipping_route_type);
        $this->assertIsArray($order->shipping_quote_breakdown);

        // Logistics recognized shipping revenue remains ₱90.00 despite platform-funded ₱25 voucher
        $alloc = app(ShippingPricingService::class)->allocateInterCompanyShipping($order);
        $this->assertEquals(90.00, (float) $alloc->total_shipping_fee);
        $this->assertEquals(90.00, round((float) $alloc->pickup_share_amount + (float) $alloc->delivery_share_amount + (float) $alloc->transfer_share_amount, 2));
    }

    public function test_transfer_courier_consolidation_low_volume_and_three_company_profitability_reconciliation(): void
    {
        Storage::fake('local');

        $lagunaCo = $this->makeUser('logistics', 'LagunaOps');
        $transferCo = $this->makeUser('logistics', 'LinehaulCorp');
        $caviteCo = $this->makeUser('logistics', 'CaviteOps');
        $admin = $this->makeUser('admin', 'ProfitAdmin');

        $pickupRider = $this->makeUser('rider', 'LagunaRider', $lagunaCo->id);
        $transferCourier = $this->makeUser('courier', 'LinehaulDriver', $transferCo->id);
        $deliveryRider = $this->makeUser('rider', 'CaviteRider', $caviteCo->id);
        $seller = $this->makeUser('seller', 'LagunaSeller');
        $buyer = $this->makeUser('buyer', 'CaviteBuyer');

        $lagunaHub = $this->makeHubWithZone($lagunaCo, 'San Pablo Hub', '0434', 'Laguna', '043424', 'San Pablo City', '043424001', 'Bagong Pook');
        $caviteHub = $this->makeHubWithZone($caviteCo, 'Cavite Hub', '0421', 'Cavite', '042103', 'Bacoor City', '042103001', 'Alima');
        $transferHub = $this->makeHubWithZone($transferCo, 'Linehaul Relay', '0434', 'Laguna', '043404', 'Cabuyao', '043404001', 'Sala');

        $buyerAddr = $this->makeAddress($buyer, '0421', 'Cavite', '042103', 'Bacoor City', '042103001', 'Alima', true);

        // Admin approves multi-carrier 35/20/45 split policy
        $policy = app(ShippingPricingService::class)->ensureDefaultPolicy();
        $this->actingAs($admin)->post(route('admin.shipping-policies.approve', $policy), [
            'contract_reference' => 'CONTRACT-PROFIT-2026',
            'activate' => 1,
        ])->assertRedirect();

        // Set explicit Transfer Courier Policy (Base ₱150 + ₱50 fuel + ₱5/parcel + ₱2.5/km, expected capacity 20 parcels)
        CompensationPolicy::create([
            'logistics_company_id' => $transferCo->id,
            'role' => 'courier',
            'name' => 'Linehaul Consolidation Policy',
            'version' => 2,
            'status' => 'active',
            'transfer_trip_base_rate' => 150.00,
            'transfer_per_km_rate' => 2.50,
            'transfer_per_parcel_rate' => 5.00,
            'transfer_fuel_allowance' => 50.00,
            'expected_manifest_parcels' => 20,
            'effective_from' => now(),
        ]);

        $order = Order::create([
            'order_number' => 'NX-PROFIT-01',
            'tracking_number' => 'NX-TRK-PROFIT01',
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'address_id' => $buyerAddr->id,
            'status' => 'delivered',
            'payment_method' => 'cod',
            'payment_status' => 'paid',
            'subtotal' => 1000.00,
            'gross_merchandise_subtotal' => 1000.00,
            'shipping_fee' => 90.00,
            'base_shipping_fee' => 90.00,
            'shipping_weight' => 1.0,
            'shipping_rate_policy_id' => $policy->id,
            'shipping_route_type' => 'interprovincial',
            'total' => 1090.00,
            'pickup_logistics_id' => $lagunaCo->id,
            'delivery_logistics_id' => $caviteCo->id,
            'sorting_center_id' => $caviteCo->id,
            'origin_hub_id' => $lagunaHub->id,
            'destination_hub_id' => $caviteHub->id,
            'current_hub_id' => $caviteHub->id,
            'pickup_courier_id' => $pickupRider->id,
            'delivery_courier_id' => $deliveryRider->id,
            'placed_at' => now()->subDay(),
            'delivered_at' => now(),
        ]);

        // Create low-volume transfer manifest (1 parcel out of 20 expected)
        $manifest = TransferManifest::create([
            'manifest_number' => 'TM-LOWVOL-01',
            'sender_logistics_id' => $transferCo->id,
            'origin_hub_id' => $transferHub->id,
            'receiver_logistics_id' => $caviteCo->id,
            'destination_hub_id' => $caviteHub->id,
            'assigned_courier_id' => $transferCourier->id,
            'distance_km' => 40.00,
            'status' => TransferManifest::STATUS_COMPLETED,
            'total_parcels' => 1,
            'expected_parcel_count' => 20,
            'estimated_trip_cost' => 400.00, // 150 + 100(km) + 100(20*5) + 50
            'expected_cost_per_parcel' => 20.00,
            'completed_at' => now(),
        ]);
        \App\Models\TransferManifestItem::create([
            'manifest_id' => $manifest->id,
            'order_id' => $order->id,
            'tracking_number' => $order->tracking_number,
            'loading_status' => 'verified',
            'receiving_status' => 'received',
        ]);

        $compService = app(CompensationPolicyService::class);
        $tripEarning = $compService->creditTransferCourierForManifest($manifest);
        $this->assertNotNull($tripEarning);

        // Idempotent duplicate check: calling again never creates duplicate pay
        $duplicate = $compService->creditTransferCourierForManifest($manifest);
        $this->assertSame($tripEarning->id, $duplicate->id);
        $this->assertSame(1, RiderEarning::where('manifest_id', $manifest->id)->count());

        // Low-volume check: 1 parcel received => actual trip cost = 150 + (40*2.5) + 5 + 50 = ₱305.00 -> ₱305/parcel vs ₱20/parcel expected!
        $this->assertEquals(305.00, (float) $tripEarning->amount);
        $this->assertTrue((bool) ($tripEarning->rate_snapshot['low_volume_warning'] ?? false));

        // 3-Company allocation check (35% Laguna ₱31.50, 20% Transfer ₱18.00, 45% Cavite ₱40.50)
        $alloc = app(ShippingPricingService::class)->allocateInterCompanyShipping($order);
        $this->assertEquals('three_company_contract', $alloc->allocation_mode);
        $this->assertEquals(31.50, (float) $alloc->pickup_share_amount);
        $this->assertEquals(18.00, (float) $alloc->transfer_share_amount);
        $this->assertEquals(40.50, (float) $alloc->delivery_share_amount);

        // Record Rider earnings under historical rate snapshot, then switch policy to ₱10/₱30 and verify historical earnings NEVER mutate!
        $pickupEarning = RiderEarning::create([
            'courier_id' => $pickupRider->id,
            'logistics_company_id' => $lagunaCo->id,
            'order_id' => $order->id,
            'earning_key' => "order_{$order->id}_pickup_{$pickupRider->id}",
            'type' => 'pickup',
            'amount' => 30.00,
            'status' => RiderEarning::STATUS_EARNED,
            'description' => 'Pickup earned under legacy policy',
            'earned_at' => now(),
        ]);

        // Activate ₱10/₱30 draft for future orders
        $draft = CompensationPolicy::whereNull('logistics_company_id')->where('role', 'rider')->where('pickup_rate', 10.00)->first();
        if ($draft) {
            $this->actingAs($admin)->post(route('admin.compensation-policies.approve', $draft))->assertRedirect();
        }
        $this->assertEquals(30.00, (float) $pickupEarning->fresh()->amount);

        // Profitability report for TransferCo shows low-volume warning & realized loss (₱18 revenue - ₱305 trip cost = -₱287)
        $transferProfit = app(LogisticsProfitabilityService::class)
            ->getProfitabilityReport($transferCo->id, now()->subDays(2), now()->addDay());
        $this->assertNotEmpty($transferProfit['missing_data_warnings']);
        $this->assertEquals(305.00, $transferProfit['transfer_courier_comp']);
        $this->assertTrue($transferProfit['manifest_costs'][0]['low_volume_warning']);
        $this->assertLessThan(0, $transferProfit['net_operating_contribution']);

        // Cancelled order must not inflate realized profit
        $cancelled = Order::create([
            'order_number' => 'NX-CANCEL-01',
            'tracking_number' => 'NX-TRK-CANCEL01',
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'address_id' => $buyerAddr->id,
            'status' => 'cancelled',
            'payment_method' => 'cod',
            'subtotal' => 400.00,
            'shipping_fee' => 90.00,
            'base_shipping_fee' => 90.00,
            'total' => 490.00,
            'delivery_logistics_id' => $caviteCo->id,
            'sorting_center_id' => $caviteCo->id,
            'placed_at' => now(),
        ]);
        $caviteProfit = app(LogisticsProfitabilityService::class)
            ->getProfitabilityReport($caviteCo->id, now()->subDays(2), now()->addDay());
        $this->assertEquals(40.50, $caviteProfit['recognized_shipping_revenue']);

        // Reconciliation remains free of duplicate earnings or phantom errors
        $recon = app(FinancialReconciliationService::class)->runReconciliationReport($transferCo->id);
        $this->assertEquals(0, $recon['critical_count']);
    }
}
