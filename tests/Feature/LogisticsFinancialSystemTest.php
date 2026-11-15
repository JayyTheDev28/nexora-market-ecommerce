<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\CodCollection;
use App\Models\CodRemittance;
use App\Models\Commission;
use App\Models\CompensationPolicy;
use App\Models\CourierPayout;
use App\Models\CourierProfile;
use App\Models\FinancialAdjustment;
use App\Models\Hub;
use App\Models\InterCompanyShippingAllocation;
use App\Models\LogisticsAdminRemittance;
use App\Models\LogisticsExpense;
use App\Models\Order;
use App\Models\ProofOfDelivery;
use App\Models\RiderEarning;
use App\Models\SellerSettlementAllocation;
use App\Models\ShippingRatePolicy;
use App\Models\TransferManifest;
use App\Models\TransferManifestItem;
use App\Models\User;
use App\Services\CodFinancialService;
use App\Services\CompensationPolicyService;
use App\Services\CourierPayoutService;
use App\Services\FinancialReconciliationService;
use App\Services\LogisticsProfitabilityService;
use App\Services\ShippingPricingService;
use App\Services\TransferManifestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LogisticsFinancialSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function makeUser(string $role, string $firstName, ?User $company = null): User
    {
        $user = User::create([
            'first_name' => $firstName,
            'last_name' => ucfirst($role),
            'email' => strtolower($firstName) . '_' . uniqid() . '@nexora.test',
            'contact_number' => '0917' . random_int(1000000, 9999999),
            'birthday' => '1993-01-01',
            'age' => 32,
            'sex' => 'male',
            'password' => Hash::make('Password123!'),
            'role' => $role,
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        if (in_array($role, ['rider', 'courier'], true) && $company) {
            CourierProfile::create([
                'user_id' => $user->id,
                'sorting_center_id' => $company->id,
                'logistics_company_id' => $company->id,
                'vehicle_type' => $role === 'rider' ? 'Motorcycle' : null,
                'plate_number' => $role === 'rider' ? 'MC-' . random_int(1000, 9999) : null,
                'license_number' => 'N01-23-' . random_int(100000, 999999),
                'license_expiry_date' => now()->addYear(),
                'is_online' => true,
            ]);
        }

        return $user;
    }

    protected function makeHub(User $company, string $name, string $city = 'Santa Cruz'): Hub
    {
        return Hub::create([
            'logistics_company_id' => $company->id,
            'name' => $name,
            'code' => 'HUB-' . strtoupper(uniqid()),
            'province_code' => '043400000',
            'province_name' => 'Laguna',
            'city_code' => '043426000',
            'city_name' => $city,
            'barangay_code' => '043426001',
            'barangay_name' => 'Poblacion',
            'is_active' => true,
        ]);
    }

    protected function makeOrder(array $overrides = []): Order
    {
        $buyer = $overrides['buyer'] ?? $this->makeUser('buyer', 'Buyer');
        $seller = $overrides['seller'] ?? $this->makeUser('seller', 'Seller');
        $company = $overrides['company'] ?? $this->makeUser('logistics', 'Alpha');
        $address = Address::create([
            'user_id' => $buyer->id,
            'province_code' => '043400000',
            'province_name' => 'Laguna',
            'municipality_code' => '043426000',
            'municipality_name' => 'Santa Cruz',
            'barangay_code' => '043426001',
            'barangay_name' => 'Poblacion',
            'street' => '45 Rizal St',
            'recipient_name' => $buyer->first_name,
            'recipient_phone' => '09170001111',
            'is_default' => true,
        ]);

        unset($overrides['buyer'], $overrides['seller'], $overrides['company']);

        return Order::create(array_merge([
            'order_number' => 'NX-' . strtoupper(uniqid()),
            'tracking_number' => 'NX-TRK-' . strtoupper(uniqid()),
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'address_id' => $address->id,
            'subtotal' => 1000.00,
            'discount_amount' => 0.00,
            'merchandise_discount_amount' => 0.00,
            'shipping_discount_amount' => 0.00,
            'shipping_fee' => 50.00,
            'gross_shipping_fee' => 50.00,
            'buyer_paid_shipping_fee' => 50.00,
            'platform_shipping_subsidy' => 0.00,
            'total' => 1050.00,
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'status' => 'ready_for_pickup',
            'sorting_center_id' => $company->id,
            'pickup_logistics_id' => $company->id,
            'delivery_logistics_id' => $company->id,
            'current_custody_logistics_id' => $company->id,
            'shipping_name' => $buyer->first_name,
            'shipping_phone' => '09170001111',
            'shipping_address' => '45 Rizal St',
            'shipping_city' => 'Santa Cruz',
            'shipping_province' => 'Laguna',
            'delivery_otp' => '123456',
        ], $overrides));
    }

    public function test_compensation_policy_draft_is_inactive_until_activated_and_historical_rates_are_locked(): void
    {
        Storage::fake('public');

        $company = $this->makeUser('logistics', 'Alpha');
        $rider = $this->makeUser('rider', 'Juan', $company);
        $policyService = app(CompensationPolicyService::class);
        $policyService->ensureDefaultPolicies();

        // Draft ₱10 / ₱30 exists inactive; default active remains ₱30 / ₱50
        $draft = CompensationPolicy::whereNull('logistics_company_id')
            ->where('role', 'rider')
            ->where('status', 'draft')
            ->first();
        $this->assertNotNull($draft);
        $this->assertEquals(10.00, (float) $draft->pickup_rate);
        $this->assertEquals(30.00, (float) $draft->delivery_rate);

        // Order 1 picked up & delivered under active ₱30/₱50 policy
        $order1 = $this->makeOrder([
            'company' => $company,
            'status' => 'ready_for_pickup',
            'pickup_courier_id' => $rider->id,
            'delivery_courier_id' => $rider->id,
        ]);

        Sanctum::actingAs($rider);
        $this->postJson("/api/mobile/v1/rider/deliveries/{$order1->id}/pickup", [
            'tracking_number' => $order1->tracking_number,
        ])->assertOk()->assertJsonPath('data.earned_pay', 30);

        $order1->update(['status' => 'out_for_delivery', 'delivery_otp' => '654321']);
        $this->postJson("/api/mobile/v1/rider/deliveries/{$order1->id}/complete", [
            'delivery_otp' => '654321',
            'pod_photo' => UploadedFile::fake()->create('pod1.jpg', 100, 'image/jpeg'),
        ])->assertOk()->assertJsonPath('data.earned_pay', 50);

        // Logistics creates and activates custom ₱10 / ₱30 policy
        $this->actingAs($company)
            ->post(route('logistics.compensation.store'), [
                'role' => 'rider',
                'name' => 'Alpha Updated Rider Policy',
                'status' => 'draft',
                'pickup_rate' => 10.00,
                'delivery_rate' => 30.00,
                'failed_attempt_rate' => 5.00,
            ])
            ->assertRedirect();

        $newPolicy = CompensationPolicy::where('logistics_company_id', $company->id)
            ->where('role', 'rider')
            ->latest('id')
            ->firstOrFail();

        $this->actingAs($company)
            ->post(route('logistics.compensation.activate', $newPolicy))
            ->assertRedirect();

        // Order 2 uses new ₱10 / ₱30 + ₱5 failed attempt rate
        $order2 = $this->makeOrder([
            'company' => $company,
            'status' => 'ready_for_pickup',
            'pickup_courier_id' => $rider->id,
            'delivery_courier_id' => $rider->id,
        ]);

        Sanctum::actingAs($rider);
        $this->postJson("/api/mobile/v1/rider/deliveries/{$order2->id}/pickup", [
            'tracking_number' => $order2->tracking_number,
        ])->assertOk()->assertJsonPath('data.earned_pay', 10);

        $order2->update(['status' => 'out_for_delivery']);
        $this->postJson("/api/mobile/v1/rider/deliveries/{$order2->id}/fail", [
            'reason' => 'buyer_not_home',
            'notes' => 'Gate closed',
        ])->assertOk();

        // Assert historical earnings for order1 were NOT mutated
        $this->assertEquals(30.00, (float) RiderEarning::where('order_id', $order1->id)->where('type', 'pickup')->value('amount'));
        $this->assertEquals(50.00, (float) RiderEarning::where('order_id', $order1->id)->where('type', 'delivery')->value('amount'));
        $this->assertEquals(10.00, (float) RiderEarning::where('order_id', $order2->id)->where('type', 'pickup')->value('amount'));
        $this->assertEquals(5.00, (float) RiderEarning::where('order_id', $order2->id)->where('type', 'failed_delivery')->value('amount'));
    }

    public function test_rider_cod_collection_remittance_validation_partial_shortage_overpayment_and_rejection(): void
    {
        Storage::fake('public');

        $company = $this->makeUser('logistics', 'Beta');
        $rider = $this->makeUser('rider', 'Pedro', $company);

        $order1 = $this->makeOrder([
            'company' => $company,
            'subtotal' => 450.00,
            'shipping_fee' => 50.00,
            'total' => 500.00,
            'status' => 'out_for_delivery',
            'delivery_courier_id' => $rider->id,
            'delivery_otp' => '112233',
        ]);
        $order2 = $this->makeOrder([
            'company' => $company,
            'subtotal' => 250.00,
            'shipping_fee' => 50.00,
            'total' => 300.00,
            'status' => 'out_for_delivery',
            'delivery_courier_id' => $rider->id,
            'delivery_otp' => '445566',
        ]);

        Sanctum::actingAs($rider);
        $this->postJson("/api/mobile/v1/rider/deliveries/{$order1->id}/complete", [
            'delivery_otp' => '112233',
            'pod_photo' => UploadedFile::fake()->create('pod1.jpg', 100, 'image/jpeg'),
        ])->assertOk();
        $this->postJson("/api/mobile/v1/rider/deliveries/{$order2->id}/complete", [
            'delivery_otp' => '445566',
            'pod_photo' => UploadedFile::fake()->create('pod2.jpg', 100, 'image/jpeg'),
        ])->assertOk();

        // Check immutable per-order CodCollection records & separate wallet balances
        $this->assertEquals(2, CodCollection::where('courier_id', $rider->id)->count());
        $riderProfile = $rider->courierProfile->fresh();
        $this->assertEquals(800.00, (float) $riderProfile->unremitted_cod_balance);
        $this->assertEquals(100.00, (float) $riderProfile->earnings_balance); // 2 * 50 delivery comp

        // Non-cash without reference or proof is rejected with 422
        $this->postJson('/api/mobile/v1/rider/remittance', [
            'amount' => 500.00,
            'payment_method' => 'gcash',
            'order_ids' => [$order1->id],
        ])->assertStatus(422);

        // Valid GCash remittance with reference & proof succeeds as 'submitted' without clearing held cash yet
        $remit1Resp = $this->postJson('/api/mobile/v1/rider/remittance', [
            'amount' => 500.00,
            'payment_method' => 'gcash',
            'external_reference' => 'GCASH-REF-778899',
            'proof_file' => UploadedFile::fake()->create('gcash_proof.jpg', 100, 'image/jpeg'),
            'order_ids' => [$order1->id],
        ])->assertOk()->assertJsonPath('data.status', 'submitted');

        $remit1Id = (int) $remit1Resp->json('data.id');
        $this->assertEquals(800.00, (float) $riderProfile->fresh()->unremitted_cod_balance);

        // Duplicate/overlapping pending remittance for same order1 is rejected
        $this->postJson('/api/mobile/v1/rider/remittance', [
            'amount' => 500.00,
            'payment_method' => 'physical_cash',
            'order_ids' => [$order1->id],
        ])->assertStatus(422);

        // Logistics verifies remit1 as PARTIAL receipt (₱450 actual vs ₱500 expected -> ₱50 shortage)
        $remit1 = CodRemittance::findOrFail($remit1Id);
        $this->actingAs($company)
            ->post(route('logistics.remittances.review', $remit1), [
                'decision' => 'verify_partial',
                'actual_received_amount' => 450.00,
                'verification_reference' => 'FIN-VER-101',
                'discrepancy_reason' => 'Short by one ₱50 bill',
            ])->assertRedirect();

        $remit1->refresh();
        $this->assertEquals('partially_verified', $remit1->status);
        $this->assertEquals(50.00, (float) $remit1->shortage_amount);
        $this->assertEquals(350.00, (float) $riderProfile->fresh()->unremitted_cod_balance);
        $col1 = CodCollection::where('order_id', $order1->id)->firstOrFail();
        $this->assertEquals(CodCollection::STATUS_PARTIAL, $col1->status);
        $this->assertEquals(50.00, (float) $col1->shortage_amount);
        $this->assertEquals(50.00, (float) $col1->outstanding_amount);

        // Double approval on remit1 must fail cleanly
        $this->actingAs($company)
            ->post(route('logistics.remittances.review', $remit1), [
                'decision' => 'verify_full',
            ])->assertSessionHasErrors();

        // Rider submits order2 as Physical Cash, Logistics rejects -> order2 becomes available again
        Sanctum::actingAs($rider);
        $remit2Resp = $this->postJson('/api/mobile/v1/rider/remittance', [
            'amount' => 300.00,
            'payment_method' => 'physical_cash',
            'order_ids' => [$order2->id],
        ])->assertOk();
        $remit2 = CodRemittance::findOrFail((int) $remit2Resp->json('data.id'));

        $this->actingAs($company)
            ->post(route('logistics.remittances.review', $remit2), [
                'decision' => 'reject',
                'operator_notes' => 'Envelope not found at cashier desk',
            ])->assertRedirect();

        $this->assertEquals('rejected', $remit2->fresh()->status);
        $this->assertEquals(CodCollection::STATUS_COLLECTED, CodCollection::where('order_id', $order2->id)->value('status'));

        // Rider resubmits order2 and Logistics verifies with overpayment (₱320 actual vs ₱300 expected -> ₱20 overpayment)
        Sanctum::actingAs($rider);
        $remit3Resp = $this->postJson('/api/mobile/v1/rider/remittance', [
            'amount' => 320.00,
            'payment_method' => 'physical_cash',
            'order_ids' => [$order2->id],
        ])->assertOk();
        $remit3 = CodRemittance::findOrFail((int) $remit3Resp->json('data.id'));

        $this->actingAs($company)
            ->post(route('logistics.remittances.review', $remit3), [
                'decision' => 'verify_full',
                'actual_received_amount' => 320.00,
                'verification_reference' => 'CASH-VER-102',
            ])->assertRedirect();

        $remit3->refresh();
        $this->assertEquals('verified', $remit3->status);
        $this->assertEquals(20.00, (float) $remit3->overpayment_amount);
        $this->assertDatabaseHas('financial_adjustments', [
            'adjustment_type' => 'cod_overpayment_refund',
            'courier_id' => $rider->id,
            'amount' => 20.00,
        ]);
    }

    public function test_logistics_to_admin_requires_verified_rider_cod_fixes_shipping_discount_and_enforces_admin_verification(): void
    {
        Storage::fake('public');

        $admin = $this->makeUser('admin', 'Super');
        $company = $this->makeUser('logistics', 'Gamma');
        $otherCompany = $this->makeUser('logistics', 'Delta');
        $seller = $this->makeUser('seller', 'Merchant');
        $rider = $this->makeUser('rider', 'Mario', $company);

        // Order with ₱1000 merchandise + ₱70 gross shipping - ₱50 shipping voucher = ₱1020 COD total
        // Merchandise payable MUST be ₱1000.00 (no double-deduction of shipping discount!)
        $order = $this->makeOrder([
            'company' => $company,
            'seller' => $seller,
            'subtotal' => 1000.00,
            'gross_merchandise_subtotal' => 1000.00,
            'discount' => 50.00,
            'store_voucher_discount' => 0.00,
            'platform_voucher_discount' => 0.00,
            'shipping_voucher_discount' => 50.00,
            'base_shipping_fee' => 70.00,
            'shipping_fee' => 20.00,
            'platform_shipping_subsidy' => 50.00,
            'total' => 1020.00,
            'status' => 'out_for_delivery',
            'delivery_courier_id' => $rider->id,
            'delivery_otp' => '998877',
        ]);

        $this->assertEquals(1000.00, $order->merchandiseAmount());

        Sanctum::actingAs($rider);
        $this->postJson("/api/mobile/v1/rider/deliveries/{$order->id}/complete", [
            'delivery_otp' => '998877',
            'pod_photo' => UploadedFile::fake()->create('pod.jpg', 100, 'image/jpeg'),
        ])->assertOk();

        // While Rider COD cash is unremitted, order is NOT eligible for Logistics-to-Admin remittance!
        $financialService = app(CodFinancialService::class);
        $this->assertCount(0, $financialService->getEligibleOrdersForRemittance($company));

        // Carrier B cannot access Carrier A's eligible orders or remit them
        $this->assertCount(0, $financialService->getEligibleOrdersForRemittance($otherCompany));

        // Rider submits & Logistics verifies full COD remittance (₱1020)
        $remitResp = $this->postJson('/api/mobile/v1/rider/remittance', [
            'amount' => 1020.00,
            'payment_method' => 'physical_cash',
            'order_ids' => [$order->id],
        ])->assertOk();
        $riderRemit = CodRemittance::findOrFail((int) $remitResp->json('data.id'));

        $this->actingAs($company)
            ->post(route('logistics.remittances.review', $riderRemit), [
                'decision' => 'verify_full',
                'actual_received_amount' => 1020.00,
                'verification_reference' => 'CASH-OK-1',
            ])->assertRedirect();

        // Now order IS eligible for Admin remittance, and merchandise amount is ₱1000.00
        $eligible = $financialService->getEligibleOrdersForRemittance($company);
        $this->assertCount(1, $eligible);

        $this->actingAs($company)
            ->post(route('logistics.admin-remittances.store'), [
                'order_ids' => [$order->id],
                'transfer_method' => 'bank_transfer',
                'transfer_reference' => 'BPI-9001122',
                'proof_file' => UploadedFile::fake()->create('slip.pdf', 120),
            ])->assertRedirect();

        $adminRemit = LogisticsAdminRemittance::firstOrFail();
        $this->assertEquals(1020.00, (float) $adminRemit->total_cod_amount);
        $this->assertEquals(20.00, (float) $adminRemit->shipping_retained);
        $this->assertEquals(1000.00, (float) $adminRemit->merchandise_amount);

        // Other carrier gets 403 trying to view Gamma's Admin remittance
        $this->actingAs($otherCompany)
            ->get(route('logistics.admin-remittances.show', $adminRemit))
            ->assertStatus(403);

        // Admin cannot verify if actual_received_amount < merchandise_amount
        $this->actingAs($admin)
            ->post(route('admin.logistics-remittances.verify', $adminRemit), [
                'actual_received_amount' => 850.00,
                'verified_bank_reference' => 'BANK-SHORT',
            ])->assertSessionHasErrors('error');
        $this->assertEquals('submitted', $adminRemit->fresh()->status);

        // Admin can dispute if discrepancy found, still zero seller allocations
        $this->actingAs($admin)
            ->post(route('admin.logistics-remittances.dispute', $adminRemit), [
                'discrepancy_notes' => 'Deposit slip cropped, awaiting full bank validation',
            ])->assertRedirect();
        $this->assertEquals('disputed', $adminRemit->fresh()->status);
        $this->assertEquals(0, SellerSettlementAllocation::count());

        // Once corrected bank reference verified with full ₱1000.00, commission (10%=₱100) + single SellerSettlementAllocation (₱900) created
        $this->actingAs($admin)
            ->post(route('admin.logistics-remittances.verify', $adminRemit), [
                'actual_received_amount' => 1000.00,
                'verified_bank_reference' => 'BPI-VERIFIED-9001122',
            ])->assertRedirect();

        $this->assertEquals('verified', $adminRemit->fresh()->status);
        $this->assertEquals(1, SellerSettlementAllocation::where('order_id', $order->id)->count());
        $allocation = SellerSettlementAllocation::where('order_id', $order->id)->firstOrFail();
        $this->assertEquals(100.00, (float) $allocation->platform_commission);
        $this->assertEquals(900.00, (float) $allocation->seller_payable);
        $this->assertEquals(100.00, (float) Commission::where('order_id', $order->id)->value('commission_amount'));
    }

    public function test_transfer_courier_manifest_earnings_role_separation_and_payout_workflow(): void
    {
        $company = $this->makeUser('logistics', 'Omega');
        $rider = $this->makeUser('rider', 'Lito', $company);
        $transferCourier = $this->makeUser('courier', 'Rico', $company);

        $hub1 = $this->makeHub($company, 'Calamba Hub', 'Calamba');
        $hub2 = $this->makeHub($company, 'Sta Cruz Hub', 'Santa Cruz');

        // Configure deterministic courier policy: ₱150 base + ₱5/parcel + ₱2/km + ₱25 fuel
        $policy = CompensationPolicy::create([
            'logistics_company_id' => $company->id,
            'role' => 'courier',
            'name' => 'Omega Linehaul Policy',
            'version' => 1,
            'status' => 'active',
            'transfer_trip_base_rate' => 150.00,
            'transfer_per_parcel_rate' => 5.00,
            'transfer_per_km_rate' => 2.00,
            'transfer_fuel_allowance' => 25.00,
            'transfer_exception_rate' => 20.00,
            'pay_on_exception' => false,
            'effective_from' => now(),
        ]);

        // Transfer courier is strictly blocked from Rider COD endpoints; Rider blocked from Transfer endpoints
        Sanctum::actingAs($transferCourier);
        $this->getJson('/api/mobile/v1/rider/cod-collections')->assertStatus(403);
        $this->postJson('/api/mobile/v1/rider/remittance', ['amount' => 100])->assertStatus(403);

        Sanctum::actingAs($rider);
        $this->getJson('/api/mobile/v1/transfer-courier/earnings')->assertStatus(403);

        // Complete a real 2-parcel, 10km manifest using full lifecycle
        $manifestService = app(TransferManifestService::class);
        $orders = [
            $this->makeOrder(['company' => $company, 'origin_hub_id' => $hub1->id, 'current_hub_id' => $hub1->id, 'destination_hub_id' => $hub2->id, 'status' => 'received_at_hub']),
            $this->makeOrder(['company' => $company, 'origin_hub_id' => $hub1->id, 'current_hub_id' => $hub1->id, 'destination_hub_id' => $hub2->id, 'status' => 'received_at_hub']),
        ];

        $manifest = $manifestService->createManifest($company, [
            'origin_hub_id' => $hub1->id,
            'destination_hub_id' => $hub2->id,
            'order_ids' => array_map(fn ($o) => $o->id, $orders),
        ]);
        $manifest->update(['distance_km' => 10.00]);
        $manifestService->assignCourier($manifest->fresh(), $transferCourier->id, $company);

        foreach ($orders as $o) {
            $manifestService->scanLoadingParcel($manifest->fresh(), $o->tracking_number, $transferCourier);
        }
        $manifestService->confirmDeparture($manifest->fresh(), $transferCourier, 'TRK-888');
        $manifestService->markArrived($manifest->fresh(), $transferCourier);

        // Verify both parcels at destination hub so manifest completes
        foreach ($orders as $o) {
            $manifestService->verifyReceivingParcel($manifest->fresh(), $o->tracking_number, 'received', $company);
        }

        $manifest->refresh();
        $this->assertEquals('completed', $manifest->status);

        // Expected pay = 150 + (2 * 5) + (10 * 2) + 25 = 205.00
        $earned = RiderEarning::where('manifest_id', $manifest->id)
            ->where('courier_id', $transferCourier->id)
            ->first();
        $this->assertNotNull($earned);
        $this->assertEquals(205.00, (float) $earned->amount);
        $this->assertEquals(205.00, (float) $transferCourier->courierProfile->fresh()->earnings_balance);

        // Transfer courier views earnings via mobile API
        Sanctum::actingAs($transferCourier);
        $this->getJson('/api/mobile/v1/transfer-courier/earnings')
            ->assertOk()
            ->assertJsonPath('data.available_for_payout', 205)
            ->assertJsonMissingPath('data.cod_wallet');

        // Transfer courier requests ₱150 payout -> status='requested', earnings_balance STILL 205 until markPaid
        $payoutResp = $this->postJson('/api/mobile/v1/transfer-courier/payouts', [
            'amount' => 150.00,
            'payout_method' => 'gcash',
            'account_name' => 'Rico Courier',
            'account_number' => '09171234567',
        ])->assertStatus(201)->assertJsonPath('data.status', 'requested');

        $payoutId = (int) $payoutResp->json('data.id');
        $this->assertEquals(205.00, (float) $transferCourier->courierProfile->fresh()->earnings_balance);

        // Overdraft beyond remaining ₱55 available must fail cleanly
        $this->postJson('/api/mobile/v1/transfer-courier/payouts', [
            'amount' => 100.00,
            'payout_method' => 'gcash',
            'account_name' => 'Rico Courier',
            'account_number' => '09171234567',
        ])->assertStatus(422);

        // Logistics approves & marks payout paid with verified bank/ewallet reference
        $payout = CourierPayout::findOrFail($payoutId);
        $this->actingAs($company)
            ->post(route('logistics.payouts.approve', $payout))
            ->assertRedirect();
        $this->actingAs($company)
            ->post(route('logistics.payouts.mark-paid', $payout), [
                'external_reference' => 'GCASH-PAYOUT-5501',
            ])->assertRedirect();

        $this->assertEquals('paid', $payout->fresh()->status);
        $this->assertEquals(55.00, (float) $transferCourier->courierProfile->fresh()->earnings_balance);

        // Rejecting a second ₱55 payout restores availability back to ₱55
        Sanctum::actingAs($transferCourier);
        $p2Id = (int) $this->postJson('/api/mobile/v1/transfer-courier/payouts', [
            'amount' => 55.00,
            'payout_method' => 'maya',
            'account_name' => 'Rico Courier',
            'account_number' => '09171234567',
        ])->assertStatus(201)->json('data.id');

        $this->actingAs($company)
            ->post(route('logistics.payouts.reject', $p2Id), [
                'rejection_reason' => 'Invalid Maya account format provided',
            ])->assertRedirect();

        Sanctum::actingAs($transferCourier);
        $this->getJson('/api/mobile/v1/transfer-courier/earnings')
            ->assertOk()
            ->assertJsonPath('data.available_for_payout', 55);
    }

    public function test_shipping_inter_company_allocation_profitability_reconciliation_and_csv_export(): void
    {
        Storage::fake('public');

        $originCo = $this->makeUser('logistics', 'OriginCo');
        $transferCo = $this->makeUser('logistics', 'TransferCo');
        $deliveryCo = $this->makeUser('logistics', 'DeliveryCo');
        $admin = $this->makeUser('admin', 'AdminGov');
        $rider = $this->makeUser('rider', 'Dante', $deliveryCo);
        $hub = $this->makeHub($deliveryCo, 'Sta Cruz Hub', 'Santa Cruz');

        // 2.5 kg order -> billed 3kg: ₱50 base + 2*₱20 = ₱90 shipping fee
        $pricingService = app(ShippingPricingService::class);
        $calc = $pricingService->calculateForWeight(2.5, $deliveryCo->id);
        $this->assertEquals(3, $calc['billed_weight_kg']);
        $this->assertEquals(90.00, $calc['shipping_fee']);

        // Admin approves the 35%/20%/45% inter-company revenue-sharing contract before carrier split applies
        $this->actingAs($admin)
            ->post(route('admin.shipping-policies.approve', $calc['policy']), [
                'contract_reference' => 'CONTRACT-3WAY-2026',
                'review_notes' => 'Signed multi-carrier tariff approved',
            ])->assertRedirect();

        $order = $this->makeOrder([
            'company' => $deliveryCo,
            'subtotal' => 500.00,
            'gross_merchandise_subtotal' => 500.00,
            'shipping_fee' => 90.00,
            'base_shipping_fee' => 90.00,
            'total' => 590.00,
            'pickup_logistics_id' => $originCo->id,
            'delivery_logistics_id' => $deliveryCo->id,
            'sorting_center_id' => $deliveryCo->id,
            'destination_hub_id' => $hub->id,
            'status' => 'out_for_delivery',
            'delivery_courier_id' => $rider->id,
            'delivery_otp' => '135790',
        ]);

        // Record actual hub transfer leg performed by TransferCo so 3-way split (35% / 20% / 45%) applies
        \App\Models\HubTransfer::create([
            'order_id' => $order->id,
            'tracking_number' => $order->tracking_number,
            'sender_logistics_id' => $transferCo->id,
            'sender_hub_id' => $hub->id,
            'receiver_logistics_id' => $deliveryCo->id,
            'receiver_hub_id' => $hub->id,
            'status' => 'received',
            'dispatched_at' => now()->subHours(2),
            'received_at' => now()->subHour(),
        ]);

        // Set higher delivery compensation policy (₱120 delivery > ₱90 shipping fee) to trigger unprofitable route flag!
        CompensationPolicy::create([
            'logistics_company_id' => $deliveryCo->id,
            'role' => 'rider',
            'name' => 'High Delivery Test Rate',
            'version' => 2,
            'status' => 'active',
            'pickup_rate' => 30.00,
            'delivery_rate' => 120.00,
            'effective_from' => now(),
        ]);

        Sanctum::actingAs($rider);
        $this->postJson("/api/mobile/v1/rider/deliveries/{$order->id}/complete", [
            'delivery_otp' => '135790',
            'pod_photo' => UploadedFile::fake()->create('pod.jpg', 100, 'image/jpeg'),
        ])->assertOk();

        // Check exact centavo InterCompanyShippingAllocation (35% ₱31.50 + 20% ₱18.00 + 45% ₱40.50 = ₱90.00)
        $alloc = InterCompanyShippingAllocation::where('order_id', $order->id)->firstOrFail();
        $this->assertEquals(31.50, (float) $alloc->pickup_share_amount);
        $this->assertEquals(18.00, (float) $alloc->transfer_share_amount);
        $this->assertEquals(40.50, (float) $alloc->delivery_share_amount);
        $this->assertEquals(90.00, round(
            (float) $alloc->pickup_share_amount
            + (float) $alloc->transfer_share_amount
            + (float) $alloc->delivery_share_amount,
            2
        ));

        // Record operating expense (₱200 fuel)
        $this->actingAs($deliveryCo)
            ->post(route('logistics.expenses.store'), [
                'category' => 'fuel',
                'description' => 'Van diesel refill',
                'amount' => 200.00,
                'expense_date' => now()->toDateString(),
                'hub_id' => $hub->id,
                'reference_number' => 'FUEL-01',
            ])->assertRedirect();

        $profitability = app(LogisticsProfitabilityService::class)
            ->getProfitabilityReport($deliveryCo->id, now()->subDay(), now()->addDay());

        $this->assertEquals(40.50, $profitability['recognized_shipping_revenue']);
        $this->assertEquals(120.00, $profitability['total_courier_comp']);
        $this->assertEquals(200.00, $profitability['operating_expenses_total']);
        $this->assertEquals(-279.50, $profitability['net_operating_contribution']);
        $this->assertEquals(1, $profitability['unprofitable_routes_count']);

        // Run Reconciliation Integrity Engine (should report zero critical/warning discrepancies)
        $reconciliation = app(FinancialReconciliationService::class)
            ->runReconciliationReport($deliveryCo->id);
        $this->assertTrue($reconciliation['is_balanced']);
        $this->assertEquals(0, $reconciliation['critical_count']);

        // Verify CSV Export downloads cleanly with matching totals
        $csvResp = $this->actingAs($deliveryCo)
            ->get(route('logistics.reports.export', [
                'type' => 'profitability',
                'from' => now()->subDay()->toDateString(),
                'to' => now()->addDay()->toDateString(),
            ]));
        $csvResp->assertOk();
        $csvResp->assertHeader('content-type', 'text/csv; charset=utf-8');
        $this->assertStringContainsString('40.50', $csvResp->streamedContent());
        $this->assertStringContainsString('200.00', $csvResp->streamedContent());
    }

    public function test_inter_company_policy_governance_and_scenarios(): void
    {
        $originCo = $this->makeUser('logistics', 'OriginGov');
        $transferCo = $this->makeUser('logistics', 'TransferGov');
        $deliveryCo = $this->makeUser('logistics', 'DeliveryGov');
        $admin = $this->makeUser('admin', 'PolicyAdmin');
        $courier = $this->makeUser('courier', 'LinehaulCourier', $originCo);
        $originHub = $this->makeHub($originCo, 'Origin Hub', 'Calamba');
        $deliveryHub = $this->makeHub($deliveryCo, 'Delivery Hub', 'Santa Cruz');

        $pricing = app(ShippingPricingService::class);

        // Unapproved 35%/20%/45% default policy must hold Scenario C in pending_policy_approval
        $orderHeld = $this->makeOrder([
            'company' => $deliveryCo,
            'shipping_fee' => 100.00,
            'base_shipping_fee' => 100.00,
            'pickup_logistics_id' => $originCo->id,
            'delivery_logistics_id' => $deliveryCo->id,
            'sorting_center_id' => $deliveryCo->id,
            'delivered_at' => now(),
        ]);
        \App\Models\HubTransfer::create([
            'order_id' => $orderHeld->id,
            'tracking_number' => $orderHeld->tracking_number,
            'sender_logistics_id' => $transferCo->id,
            'sender_hub_id' => $originHub->id,
            'receiver_logistics_id' => $deliveryCo->id,
            'receiver_hub_id' => $deliveryHub->id,
            'status' => 'received',
            'dispatched_at' => now()->subHours(2),
            'received_at' => now()->subHour(),
        ]);

        $heldAlloc = $pricing->allocateInterCompanyShipping($orderHeld);
        $this->assertEquals('pending_policy_approval', $heldAlloc->settlement_status);
        $this->assertEquals('pending_contract_approval', $heldAlloc->allocation_mode);
        $this->assertEquals(0.00, (float) $heldAlloc->pickup_share_amount);
        $this->assertEquals(0.00, (float) $heldAlloc->transfer_share_amount);
        $this->assertEquals(100.00, (float) $heldAlloc->delivery_share_amount);

        // Logistics cannot settle a pending_policy_approval row
        $this->actingAs($deliveryCo)
            ->post(route('logistics.inter-company.settle', $heldAlloc))
            ->assertStatus(422);

        // Scenario A: Same company fulfills everything -> 100% self_retained with zero self-payables
        $orderSame = $this->makeOrder([
            'company' => $deliveryCo,
            'shipping_fee' => 80.00,
            'base_shipping_fee' => 80.00,
            'pickup_logistics_id' => $deliveryCo->id,
            'delivery_logistics_id' => $deliveryCo->id,
            'sorting_center_id' => $deliveryCo->id,
            'delivered_at' => now(),
        ]);
        $allocA = $pricing->allocateInterCompanyShipping($orderSame);
        $this->assertEquals('self_retained', $allocA->settlement_status);
        $this->assertEquals('single_carrier', $allocA->allocation_mode);
        $this->assertEquals(0.00, (float) $allocA->pickup_share_amount);
        $this->assertEquals(0.00, (float) $allocA->transfer_share_amount);
        $this->assertEquals(80.00, (float) $allocA->delivery_share_amount);

        // Admin approves policy with contract reference -> Scenario B & C unlock cleanly
        $defaultPolicy = $pricing->ensureDefaultPolicy();
        $this->actingAs($admin)
            ->post(route('admin.shipping-policies.approve', $defaultPolicy), [
                'contract_reference' => 'NEX-INTERCO-2026',
            ])->assertRedirect();

        // Scenario B: 2 companies (OriginCo operates transfer manifest to DeliveryCo) -> 100% split, ₱0 phantom transfer share
        $orderTwoCo = $this->makeOrder([
            'company' => $deliveryCo,
            'shipping_fee' => 80.00,
            'base_shipping_fee' => 80.00,
            'pickup_logistics_id' => $originCo->id,
            'delivery_logistics_id' => $deliveryCo->id,
            'sorting_center_id' => $deliveryCo->id,
            'delivered_at' => now(),
        ]);
        $manifest = TransferManifest::create([
            'manifest_number' => 'MAN-2CO-01',
            'sender_logistics_id' => $originCo->id,
            'origin_hub_id' => $originHub->id,
            'destination_hub_id' => $deliveryHub->id,
            'receiver_logistics_id' => $deliveryCo->id,
            'transfer_courier_id' => $courier->id,
            'status' => 'completed',
            'completed_at' => now()->subMinute(),
        ]);
        TransferManifestItem::create([
            'manifest_id' => $manifest->id,
            'order_id' => $orderTwoCo->id,
            'tracking_number' => $orderTwoCo->tracking_number,
            'loading_status' => TransferManifestItem::LOADING_LOADED,
            'receiving_status' => TransferManifestItem::RECEIVING_RECEIVED,
            'loaded_at' => now()->subHour(),
            'received_at' => now()->subMinute(),
        ]);

        $allocB = $pricing->allocateInterCompanyShipping($orderTwoCo);
        $this->assertEquals('two_company', $allocB->allocation_mode);
        $this->assertEquals('allocated', $allocB->settlement_status);
        $this->assertNull($allocB->transfer_logistics_id);
        $this->assertEquals(0.00, (float) $allocB->transfer_share_amount);
        // 35 / (35 + 45) * 80 = 35.00; 45 / (35 + 45) * 80 = 45.00
        $this->assertEquals(35.00, (float) $allocB->pickup_share_amount);
        $this->assertEquals(45.00, (float) $allocB->delivery_share_amount);

        // Settle Scenario B allocation and ensure re-evaluation/re-computation never mutates settled allocations
        $this->actingAs($originCo)
            ->post(route('logistics.inter-company.settle', $allocB))
            ->assertRedirect();
        $this->assertEquals('settled', $allocB->fresh()->settlement_status);
        $unmutated = $pricing->allocateInterCompanyShipping($orderTwoCo);
        $this->assertEquals('settled', $unmutated->settlement_status);
    }

    public function test_profitability_exact_unit_economics_and_company_isolation(): void
    {
        $companyA = $this->makeUser('logistics', 'AlphaLog');
        $companyB = $this->makeUser('logistics', 'BetaLog');
        $riderA = $this->makeUser('rider', 'RicoA', $companyA);
        $riderB = $this->makeUser('rider', 'RicoB', $companyB);
        $hubA = $this->makeHub($companyA, 'Alpha Hub');
        $hubB = $this->makeHub($companyB, 'Beta Hub');

        $orderA = $this->makeOrder([
            'company' => $companyA,
            'shipping_fee' => 50.00,
            'base_shipping_fee' => 50.00,
            'status' => 'delivered',
            'delivered_at' => now(),
            'destination_hub_id' => $hubA->id,
            'delivery_courier_id' => $riderA->id,
        ]);

        $policyA = CompensationPolicy::create([
            'logistics_company_id' => $companyA->id,
            'role' => 'rider',
            'name' => 'UnitEcon Policy',
            'version' => 1,
            'status' => 'active',
            'pickup_rate' => 10.00,
            'delivery_rate' => 30.00,
            'effective_from' => now(),
        ]);

        app(ShippingPricingService::class)->allocateInterCompanyShipping($orderA);

        RiderEarning::create([
            'courier_id' => $riderA->id,
            'logistics_company_id' => $companyA->id,
            'order_id' => $orderA->id,
            'policy_id' => $policyA->id,
            'earning_key' => 'pickup:test:' . $orderA->id,
            'type' => 'pickup',
            'amount' => 10.00,
            'status' => RiderEarning::STATUS_EARNED,
            'description' => 'Parcel pickup compensation',
            'earned_at' => now(),
        ]);
        RiderEarning::create([
            'courier_id' => $riderA->id,
            'logistics_company_id' => $companyA->id,
            'order_id' => $orderA->id,
            'policy_id' => $policyA->id,
            'earning_key' => 'delivery:test:' . $orderA->id,
            'type' => 'delivery',
            'amount' => 30.00,
            'status' => RiderEarning::STATUS_EARNED,
            'description' => 'Parcel delivery compensation',
            'earned_at' => now(),
        ]);
        LogisticsExpense::create([
            'expense_number' => 'EXP-UNIT-01',
            'logistics_company_id' => $companyA->id,
            'hub_id' => $hubA->id,
            'recorded_by' => $companyA->id,
            'category' => 'fuel',
            'description' => 'Parcel route fuel share',
            'amount' => 8.00,
            'expense_date' => now()->toDateString(),
        ]);

        // Add unrelated Company B activity to verify strict company isolation
        $orderB = $this->makeOrder([
            'company' => $companyB,
            'shipping_fee' => 200.00,
            'base_shipping_fee' => 200.00,
            'status' => 'delivered',
            'delivered_at' => now(),
            'destination_hub_id' => $hubB->id,
            'delivery_courier_id' => $riderB->id,
        ]);
        app(ShippingPricingService::class)->allocateInterCompanyShipping($orderB);

        $reportA = app(LogisticsProfitabilityService::class)
            ->getProfitabilityReport($companyA->id, now()->subDay(), now()->addDay());

        // Exact unit economics check: ₱50.00 revenue - (₱10.00 pickup + ₱30.00 delivery) - ₱8.00 expense = ₱2.00
        $this->assertEquals(50.00, $reportA['recognized_shipping_revenue']);
        $this->assertEquals(40.00, $reportA['total_courier_comp']);
        $this->assertEquals(8.00, $reportA['operating_expenses_total']);
        $this->assertEquals(2.00, $reportA['net_operating_contribution']);
        $this->assertStringContainsString('Operating Contribution', $reportA['contribution_note']);

        // Cross-company report isolation check
        $this->actingAs($companyB)
            ->get(route('logistics.reports.index', ['tab' => 'profitability']))
            ->assertOk()
            ->assertDontSee('Parcel route fuel share');
    }

    public function test_reconciliation_manual_review_for_incomplete_historical_order_and_artisan_command(): void
    {
        $company = $this->makeUser('logistics', 'ReconCo');

        // Legacy COD order marked paid/delivered without rider/logistics custody or COD/settlement evidence
        $legacyOrder = $this->makeOrder([
            'company' => $company,
            'order_number' => 'NEX-LEGACY-0001',
            'payment_method' => 'cod',
            'payment_status' => 'paid',
            'status' => 'delivered',
            'delivery_courier_id' => null,
            'delivery_logistics_id' => null,
            'sorting_center_id' => null,
            'delivered_at' => now()->subWeek(),
        ]);

        $report = app(FinancialReconciliationService::class)->runReconciliationReport();
        $issue = collect($report['issues'])->firstWhere('entity_id', $legacyOrder->id);

        $this->assertNotNull($issue);
        $this->assertEquals('missing_cod_collection_record', $issue['check']);
        $this->assertEquals('manual_review_required', $issue['classification']);
        $this->assertGreaterThanOrEqual(1, $report['manual_review_count']);

        // Ensure reconciliation never fabricates fake CodCollection records
        $this->assertDatabaseCount('cod_collections', 0);

        $this->artisan('finance:reconcile', ['--json' => true])
            ->assertExitCode(0);
    }

    public function test_minimum_seller_payout_threshold_and_cross_company_authorization_guards(): void
    {
        $companyA = $this->makeUser('logistics', 'AuthCoA');
        $companyB = $this->makeUser('logistics', 'AuthCoB');
        $seller = $this->makeUser('seller', 'SellerMin');
        $riderA = $this->makeUser('rider', 'RiderAuthA', $companyA);

        $smallOrder = $this->makeOrder([
            'company' => $companyA,
            'seller' => $seller,
            'subtotal' => 400.00,
            'shipping_fee' => 50.00,
            'total' => 450.00,
            'status' => 'delivered',
            'payment_status' => 'paid',
            'delivered_at' => now(),
        ]);
        Commission::create([
            'order_id' => $smallOrder->id,
            'seller_id' => $seller->id,
            'order_total' => 400.00,
            'rate' => 0.1000,
            'commission_amount' => 40.00,
            'seller_payout' => 360.00,
            'status' => 'settled',
            'settled_at' => now(),
        ]);
        SellerSettlementAllocation::create([
            'order_id' => $smallOrder->id,
            'seller_id' => $seller->id,
            'eligible_merchandise_amount' => 400.00,
            'commission_rate' => 0.1000,
            'platform_commission' => 40.00,
            'seller_payable' => 360.00,
            'status' => 'settled',
            'settled_at' => now(),
        ]);

        // Seller with ₱360 (< ₱500 minimum) cannot request payout
        $this->actingAs($seller)
            ->post(route('seller.payouts.request'), [
                'amount' => 360.00,
                'payout_method' => 'gcash',
                'account_name' => 'SellerMin',
                'account_number' => '09171112222',
            ])->assertSessionHasErrors('amount');

        // Cross-company security: Company B must get 403 trying to verify Company A's rider COD remittance or payout
        $remitA = CodRemittance::create([
            'remittance_number' => 'RMT-AUTH-01',
            'courier_id' => $riderA->id,
            'sorting_center_id' => $companyA->id,
            'amount' => 450.00,
            'claimed_amount' => 450.00,
            'expected_amount' => 450.00,
            'status' => CodRemittance::STATUS_SUBMITTED,
            'payment_method' => 'physical_cash',
            'remitted_at' => now(),
        ]);
        $this->actingAs($companyB)
            ->post(route('logistics.remittances.review', $remitA), [
                'action' => 'verify_full',
            ])->assertForbidden();

        $payoutA = CourierPayout::create([
            'payout_number' => 'CPY-AUTH-01',
            'courier_id' => $riderA->id,
            'logistics_company_id' => $companyA->id,
            'role' => 'rider',
            'amount' => 100.00,
            'payout_method' => 'gcash',
            'account_name' => 'RiderAuthA',
            'account_number' => '09170001111',
            'status' => CourierPayout::STATUS_REQUESTED,
            'requested_at' => now(),
        ]);
        $unauthResp = $this->actingAs($companyB)
            ->post(route('logistics.payouts.approve', $payoutA));
        $this->assertTrue(in_array($unauthResp->getStatusCode(), [302, 403], true));
        $this->assertEquals(CourierPayout::STATUS_REQUESTED, $payoutA->fresh()->status);
    }
}
