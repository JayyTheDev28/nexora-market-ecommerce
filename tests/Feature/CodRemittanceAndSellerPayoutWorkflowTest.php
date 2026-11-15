<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Category;
use App\Models\CodRemittance;
use App\Models\Commission;
use App\Models\LogisticsAdminRemittance;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SellerPayoutRequest;
use App\Models\SellerProfile;
use App\Models\SellerSettlementAllocation;
use App\Models\SortingCenterProfile;
use App\Models\User;
use App\Services\CodFinancialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CodRemittanceAndSellerPayoutWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $logistics;
    protected User $seller;
    protected User $buyer;
    protected User $courier;
    protected Address $address;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Storage::fake('public');

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $this->logistics = User::factory()->create([
            'role' => 'sorting_center',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
        SortingCenterProfile::create([
            'user_id' => $this->logistics->id,
            'business_name' => 'Metro Express Logistics',
            'contact_number' => '09171234567',
            'facility_address' => 'Pasig City Hub',
            'operating_hours' => '24/7',
        ]);

        $this->seller = User::factory()->create([
            'role' => 'seller',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
        SellerProfile::create([
            'user_id' => $this->seller->id,
            'business_name' => 'Apex Electronics',
            'category' => 'Retail',
            'contact_phone' => '09181234567',
        ]);

        $this->buyer = User::factory()->create([
            'role' => 'buyer',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $this->courier = User::factory()->create([
            'role' => 'courier',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $this->address = Address::create([
            'user_id' => $this->buyer->id,
            'province_code' => '043400000',
            'province_name' => 'Laguna',
            'municipality_code' => '043426000',
            'municipality_name' => 'Santa Cruz',
            'barangay_code' => '043426001',
            'barangay_name' => 'Poblacion I',
            'street' => '100 Rizal Ave',
            'house_number' => 'Unit 10',
            'recipient_name' => 'John Buyer',
            'recipient_phone' => '09191234567',
            'is_default' => true,
        ]);

        $this->category = Category::firstOrCreate(
            ['slug' => 'electronics'],
            ['name' => 'Electronics', 'is_active' => true]
        );
    }

    protected function createDeliveredCodOrder(User $seller, float $subtotal, float $shippingFee): Order
    {
        $product = Product::create([
            'seller_id' => $seller->id,
            'category_id' => $this->category->id,
            'name' => 'Audio Headset ' . uniqid(),
            'slug' => 'audio-headset-' . uniqid(),
            'price' => $subtotal,
            'stock' => 50,
            'status' => 'active',
        ]);

        $order = Order::create([
            'buyer_id' => $this->buyer->id,
            'seller_id' => $seller->id,
            'sorting_center_id' => $this->logistics->id,
            'delivery_logistics_id' => $this->logistics->id,
            'delivery_courier_id' => $this->courier->id,
            'address_id' => $this->address->id,
            'order_number' => 'ORD-' . strtoupper(uniqid()),
            'subtotal' => $subtotal,
            'shipping_fee' => $shippingFee,
            'discount' => 0,
            'total' => $subtotal + $shippingFee,
            'payment_method' => 'cod',
            'payment_status' => 'paid',
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => $subtotal,
            'quantity' => 1,
        ]);

        Commission::create([
            'order_id' => $order->id,
            'seller_id' => $seller->id,
            'order_total' => $subtotal,
            'rate' => 0.10,
            'commission_amount' => round($subtotal * 0.10, 2),
            'seller_payout' => round($subtotal * 0.90, 2),
            'status' => 'pending',
        ]);

        // Simulating Rider remittance to logistics hub
        CodRemittance::create([
            'remittance_number' => 'REMIT-' . strtoupper(uniqid()),
            'courier_id' => $this->courier->id,
            'sorting_center_id' => $this->logistics->id,
            'amount' => $subtotal + $shippingFee,
            'expected_amount' => $subtotal + $shippingFee,
            'status' => 'approved',
            'remitted_at' => now(),
            'reviewed_at' => now(),
        ]);

        return $order;
    }

    public function test_full_end_to_end_cod_remittance_and_seller_payout_lifecycle(): void
    {
        // 1. Order Setup: Product = ₱1,000, Shipping = ₱50, Total COD = ₱1,050
        $order = $this->createDeliveredCodOrder($this->seller, 1000.00, 50.00);

        // 2. Logistics views Remittance Dashboard & eligible orders
        $service = app(CodFinancialService::class);
        $summary = $service->getLogisticsCodSummary($this->logistics);
        $this->assertEquals(1050.00, $summary['totalCodReceived']);
        $this->assertEquals(50.00, $summary['shippingRetained']);
        $this->assertEquals(1000.00, $summary['merchandisePayable']);
        $this->assertEquals(1000.00, $summary['availableMerchandiseAmount']);

        // 3. Logistics remits ₱1,000 merchandise to Nexora Admin
        $proof = UploadedFile::fake()->create('bank_receipt.pdf', 100);
        $remitResponse = $this->actingAs($this->logistics)->post(route('logistics.admin-remittances.store'), [
            'order_ids' => [$order->id],
            'transfer_method' => 'bank_transfer',
            'transfer_reference' => 'BDO-REF-998877',
            'proof_file' => $proof,
            'notes' => 'Remitting merchandise funds for completed batch',
        ]);

        $remitResponse->assertRedirect();
        $remittance = LogisticsAdminRemittance::first();
        $this->assertNotNull($remittance);
        $this->assertEquals(1050.00, (float) $remittance->total_cod_amount);
        $this->assertEquals(50.00, (float) $remittance->shipping_retained);
        $this->assertEquals(1000.00, (float) $remittance->merchandise_amount);
        $this->assertEquals('submitted', $remittance->status);

        // 4. Admin verifies remittance receipt
        $verifyResponse = $this->actingAs($this->admin)->post(
            route('admin.logistics-remittances.verify', $remittance),
            ['admin_notes' => 'Funds received in Nexora bank account']
        );
        $verifyResponse->assertRedirect();
        $remittance->refresh();
        $this->assertEquals('verified', $remittance->status);
        $this->assertNotNull($remittance->reviewed_at);

        // Verify seller allocation is created and settled
        $allocation = SellerSettlementAllocation::where('order_id', $order->id)->first();
        $this->assertNotNull($allocation);
        $this->assertEquals('available', $allocation->status);
        $this->assertEquals(1000.00, (float) $allocation->eligible_merchandise_amount);
        $this->assertEquals(100.00, (float) $allocation->platform_commission); // 10%
        $this->assertEquals(900.00, (float) $allocation->seller_payable); // 90%

        // 5. Seller financial overview reflects verified ₱900 available for withdrawal
        $sellerOverview = $service->getSellerFinancialOverview($this->seller);
        $this->assertEquals(900.00, $sellerOverview['verifiedEarningsPayable']);
        $this->assertEquals(900.00, $sellerOverview['availableBalance']);
        $this->assertEquals(100.00, $sellerOverview['platformCommissionDeducted']);

        // 6. Seller requests payout of ₱900
        $payoutReqResponse = $this->actingAs($this->seller)->post(route('seller.payouts.request'), [
            'amount' => 900.00,
            'payout_method' => 'bank_transfer',
            'account_name' => 'Apex Electronics Inc',
            'account_number' => '1234567890',
        ]);
        $payoutReqResponse->assertRedirect();

        $payoutRequest = SellerPayoutRequest::first();
        $this->assertNotNull($payoutRequest);
        $this->assertEquals('pending', $payoutRequest->status);
        $this->assertEquals(900.00, (float) $payoutRequest->amount);

        // Seller's available balance is locked while payout is pending
        $sellerOverviewAfter = $service->getSellerFinancialOverview($this->seller);
        $this->assertEquals(0.00, $sellerOverviewAfter['availableBalance']);

        // 7. Admin approves payout (Step 1)
        $approveResponse = $this->actingAs($this->admin)->post(
            route('admin.seller-payouts.approve', $payoutRequest),
            ['admin_notes' => 'Payout approved for dispatch']
        );
        $approveResponse->assertRedirect();
        $payoutRequest->refresh();
        $this->assertEquals('approved', $payoutRequest->status);

        // 8. Admin disburses payout with transaction proof (Step 2)
        $disburseProof = UploadedFile::fake()->create('transfer_confirmation.pdf', 100);
        $disburseResponse = $this->actingAs($this->admin)->post(
            route('admin.seller-payouts.disburse', $payoutRequest),
            [
                'transaction_reference' => 'TXN-BANK-11223344',
                'proof_file' => $disburseProof,
                'admin_notes' => 'Disbursed via corporate online banking',
            ]
        );
        $disburseResponse->assertRedirect();
        $payoutRequest->refresh();
        $this->assertEquals('paid', $payoutRequest->status);
        $this->assertEquals('TXN-BANK-11223344', $payoutRequest->transaction_reference);
        $this->assertNotNull($payoutRequest->disbursed_at);

        // Final distribution reconciliation:
        // Logistics retained = ₱50
        // Nexora Admin commission = ₱100
        // Seller paid = ₱900
        // Total = ₱1,050
        $this->assertEquals(50.00, (float) $remittance->shipping_retained);
        $this->assertEquals(100.00, (float) $allocation->platform_commission);
        $this->assertEquals(900.00, (float) $payoutRequest->amount);
        $this->assertEquals(1050.00, $remittance->shipping_retained + $allocation->platform_commission + $payoutRequest->amount);
    }

    public function test_multi_seller_batch_reconciles_itemized_allocations(): void
    {
        $sellerB = User::factory()->create([
            'role' => 'seller',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
        SellerProfile::create([
            'user_id' => $sellerB->id,
            'business_name' => 'Beta Goods',
            'category' => 'Retail',
        ]);

        // Seller A: ₱1,000 (ship ₱50) & ₱2,000 (ship ₱60)
        $orderA1 = $this->createDeliveredCodOrder($this->seller, 1000.00, 50.00);
        $orderA2 = $this->createDeliveredCodOrder($this->seller, 2000.00, 60.00);

        // Seller B: ₱500 (ship ₱40)
        $orderB1 = $this->createDeliveredCodOrder($sellerB, 500.00, 40.00);

        // Total COD = 1050 + 2060 + 540 = 3650
        // Shipping retained = 50 + 60 + 40 = 150
        // Merchandise payable = 1000 + 2000 + 500 = 3500

        $service = app(CodFinancialService::class);
        $proof = UploadedFile::fake()->create('multi_seller_slip.pdf', 100);
        $remittance = $service->createLogisticsAdminRemittance(
            $this->logistics,
            [$orderA1->id, $orderA2->id, $orderB1->id],
            [
                'transfer_method' => 'bank_deposit',
                'transfer_reference' => 'REF-MULTI-123',
                'notes' => 'Multi-seller batch',
            ],
            $proof
        );

        $this->assertEquals(3650.00, (float) $remittance->total_cod_amount);
        $this->assertEquals(150.00, (float) $remittance->shipping_retained);
        $this->assertEquals(3500.00, (float) $remittance->merchandise_amount);

        // Admin verifies
        $service->verifyLogisticsAdminRemittance($remittance, $this->admin);

        // Check Seller A totals:
        // Merchandise: 3,000 -> 10% commission = 300, net = 2,700
        $overviewA = $service->getSellerFinancialOverview($this->seller);
        $this->assertEquals(2700.00, $overviewA['verifiedEarningsPayable']);
        $this->assertEquals(300.00, $overviewA['platformCommissionDeducted']);

        // Check Seller B totals:
        // Merchandise: 500 -> 10% commission = 50, net = 450
        $overviewB = $service->getSellerFinancialOverview($sellerB);
        $this->assertEquals(450.00, $overviewB['verifiedEarningsPayable']);
        $this->assertEquals(50.00, $overviewB['platformCommissionDeducted']);
    }

    public function test_duplicate_order_remittance_prevented(): void
    {
        $order = $this->createDeliveredCodOrder($this->seller, 1000.00, 50.00);
        $service = app(CodFinancialService::class);

        $proof = UploadedFile::fake()->create('proof1.pdf', 100);
        $service->createLogisticsAdminRemittance(
            $this->logistics,
            [$order->id],
            [
                'transfer_method' => 'bank_deposit',
                'transfer_reference' => 'REF-DUP-1',
            ],
            $proof
        );

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->createLogisticsAdminRemittance(
            $this->logistics,
            [$order->id],
            [
                'transfer_method' => 'bank_deposit',
                'transfer_reference' => 'REF-DUP-2',
            ],
            $proof
        );
    }

    public function test_rejected_remittance_releases_orders_for_resubmission(): void
    {
        $order = $this->createDeliveredCodOrder($this->seller, 1000.00, 50.00);
        $service = app(CodFinancialService::class);

        $proof = UploadedFile::fake()->create('proof.pdf', 100);
        $remittance = $service->createLogisticsAdminRemittance(
            $this->logistics,
            [$order->id],
            [
                'transfer_method' => 'bank_deposit',
                'transfer_reference' => 'REF-REJECT-TEST',
            ],
            $proof
        );

        $this->assertEquals('submitted', $remittance->status);

        // Admin rejects
        $service->rejectLogisticsAdminRemittance($remittance, $this->admin, 'Bank deposit slip blurred and unreadable');
        $remittance->refresh();
        $this->assertEquals('rejected', $remittance->status);
        $this->assertEquals('Bank deposit slip blurred and unreadable', $remittance->rejection_reason);

        // Eligible orders should include the order again
        $eligibleOrders = $service->getEligibleOrdersForRemittance($this->logistics);
        $this->assertTrue($eligibleOrders->contains('id', $order->id));
    }

    public function test_seller_cannot_withdraw_unverified_funds_or_exceed_balance(): void
    {
        // Order delivered, but logistics hasn't remitted to admin yet
        $this->createDeliveredCodOrder($this->seller, 1000.00, 50.00);

        $service = app(CodFinancialService::class);
        $overview = $service->getSellerFinancialOverview($this->seller);
        $this->assertEquals(0.00, $overview['availableBalance']);
        $this->assertEquals(900.00, $overview['earningsWaitingLogistics']);

        // Seller attempts to withdraw
        $response = $this->actingAs($this->seller)->post(route('seller.payouts.request'), [
            'amount' => 900.00,
            'payout_method' => 'gcash',
            'account_name' => 'Apex Electronics',
            'account_number' => '09181234567',
        ]);

        $response->assertSessionHasErrors(['amount']);
        $this->assertEquals(0, SellerPayoutRequest::count());
    }

    public function test_rejected_seller_payout_restores_available_balance(): void
    {
        $order = $this->createDeliveredCodOrder($this->seller, 1000.00, 50.00);
        $service = app(CodFinancialService::class);

        $proof = UploadedFile::fake()->create('proof.pdf', 100);
        $remittance = $service->createLogisticsAdminRemittance(
            $this->logistics,
            [$order->id],
            ['transfer_method' => 'bank_deposit', 'transfer_reference' => 'REF-RESTORE'],
            $proof
        );
        $service->verifyLogisticsAdminRemittance($remittance, $this->admin);

        // Seller requests payout
        $payoutRequest = $service->requestSellerPayout($this->seller, 900.00, 'bank_transfer', 'Apex', '111222');
        $overviewPending = $service->getSellerFinancialOverview($this->seller);
        $this->assertEquals(0.00, $overviewPending['availableBalance']);

        // Admin rejects payout
        $service->rejectSellerPayout($payoutRequest, $this->admin, 'Invalid bank account number');
        $payoutRequest->refresh();
        $this->assertEquals('rejected', $payoutRequest->status);

        // Available balance is restored
        $overviewRestored = $service->getSellerFinancialOverview($this->seller);
        $this->assertEquals(900.00, $overviewRestored['availableBalance']);
    }

    public function test_carrier_data_isolation(): void
    {
        $logisticsB = User::factory()->create([
            'role' => 'sorting_center',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
        SortingCenterProfile::create([
            'user_id' => $logisticsB->id,
            'business_name' => 'Speedy Couriers',
            'contact_number' => '09179998877',
            'facility_address' => 'Quezon City Hub',
            'operating_hours' => '24/7',
        ]);

        // Order assigned to Logistics A
        $orderA = $this->createDeliveredCodOrder($this->seller, 1000.00, 50.00);

        // Logistics B cannot see order A in eligible orders
        $service = app(CodFinancialService::class);
        $eligibleForB = $service->getEligibleOrdersForRemittance($logisticsB);
        $this->assertFalse($eligibleForB->contains('id', $orderA->id));

        // Logistics B cannot remit order A
        $proof = UploadedFile::fake()->create('proof.pdf', 100);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->createLogisticsAdminRemittance(
            $logisticsB,
            [$orderA->id],
            ['transfer_method' => 'bank_deposit', 'transfer_reference' => 'REF-ISOLATION'],
            $proof
        );
    }
}
