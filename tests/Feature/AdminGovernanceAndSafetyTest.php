<?php

namespace Tests\Feature;

use App\Models\AccountRestriction;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\CodRemittance;
use App\Models\Dispute;
use App\Models\Hub;
use App\Models\LogisticsProfile;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RiskAlert;
use App\Models\SafetyReport;
use App\Models\SellerPayoutRequest;
use App\Models\SellerProfile;
use App\Models\User;
use App\Services\RiskDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class AdminGovernanceAndSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_report_admin_review_and_private_evidence_security(): void
    {
        Storage::fake('local');

        $admin = $this->createUser('admin', 'admin.gov@nexora.test');
        $buyer = $this->createUser('buyer', 'buyer.gov@nexora.test');
        $seller = $this->createUser('seller', 'seller.gov@nexora.test');
        SellerProfile::create(['user_id' => $seller->id, 'business_name' => 'Suspect Store', 'category' => 'electronics']);

        Sanctum::actingAs($buyer);
        $file = UploadedFile::fake()->create('proof.png', 50, 'image/png');

        $resp = $this->postJson('/api/mobile/v1/safety-reports', [
            'category' => 'fraud_scam',
            'title' => 'Counterfeit claim on store',
            'description' => 'Seller shipped empty box instead of item.',
            'reported_user_id' => $seller->id,
            'evidence' => [$file],
        ])->assertStatus(201);

        $reportId = $resp->json('data.id');
        $report = SafetyReport::findOrFail($reportId);
        $this->assertNotEmpty($report->evidence_paths);
        Storage::disk('local')->assertExists($report->evidence_paths[0]);

        // Security: Buyer & Seller cannot access Admin Safety Center or private evidence route
        $this->actingAs($buyer)->getJson('/admin/safety')->assertStatus(403);
        $this->actingAs($seller)->getJson(route('admin.safety.evidence', [$report, 0]))->assertStatus(403);

        // Admin views case, adds internal note (hidden from buyer), and resolves with public message
        $this->actingAs($admin)->get(route('admin.safety.show', $report))->assertOk();
        $this->actingAs($admin)->post(route('admin.safety.notes', $report), [
            'note' => 'Internal investigator check: verified courier scan.',
            'is_internal' => 1,
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('admin.safety.update', $report), [
            'status' => 'resolved',
            'priority' => 'high',
            'assigned_admin_id' => $admin->id,
            'resolution_summary' => 'Warning issued to seller.',
            'public_message' => 'We reviewed your report and took action.',
        ])->assertRedirect();

        // Buyer sees updated status & public update, NEVER internal note
        Sanctum::actingAs($buyer);
        $buyerView = $this->getJson("/api/mobile/v1/safety-reports/{$report->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved');

        $publicNotesText = json_encode($buyerView->json('data.public_updates'));
        $this->assertStringContainsString('We reviewed your report and took action.', $publicNotesText);
        $this->assertStringNotContainsString('Internal investigator check', $publicNotesText);
    }

    public function test_rules_based_fraud_scan_account_restriction_and_appeal_workflow(): void
    {
        $admin = $this->createUser('admin', 'admin.risk@nexora.test');
        $seller = $this->createUser('seller', 'seller.risk@nexora.test');

        // Create duplicate payout reference to trigger DUPLICATE_PAYOUT_REFERENCE rule
        SellerPayoutRequest::create([
            'seller_id' => $seller->id,
            'reference_number' => 'PAY-REQ-001',
            'amount' => 500.00,
            'payout_method' => 'gcash',
            'account_name' => 'Seller',
            'account_number' => '09171234567',
            'status' => 'paid',
            'transaction_reference' => 'GCASH-DUP-999',
        ]);
        SellerPayoutRequest::create([
            'seller_id' => $seller->id,
            'reference_number' => 'PAY-REQ-002',
            'amount' => 500.00,
            'payout_method' => 'gcash',
            'account_name' => 'Seller',
            'account_number' => '09171234567',
            'status' => 'paid',
            'transaction_reference' => 'GCASH-DUP-999',
        ]);

        $alerts = app(RiskDetectionService::class)->runRulesScan($admin);
        $this->assertNotEmpty($alerts);
        $this->assertSame('DUPLICATE_PAYOUT_REFERENCE', $alerts[0]->rule_code);
        // Must NOT auto-suspend account
        $this->assertSame('active', $seller->fresh()->account_status);

        // Admin reviews alert and applies granular payout_hold restriction
        $alert = RiskAlert::firstOrFail();
        $this->actingAs($admin)->post(route('admin.fraud-alerts.review', $alert), [
            'status' => 'investigating',
            'decision_notes' => 'Checking duplicate payout reference with finance.',
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('admin.users.restrict', $seller), [
            'restriction_type' => 'payout_hold',
            'reason' => 'Pending payout reference audit.',
            'duration_days' => 7,
        ])->assertRedirect();

        $this->assertTrue($seller->fresh()->hasRestriction('payout_hold'));
        $restriction = AccountRestriction::where('user_id', $seller->id)->firstOrFail();

        // Seller submits appeal & Admin approves -> lifts restriction
        $this->actingAs($seller)->post(route('restrictions.appeal', $restriction), [
            'reason' => 'Duplicate reference was bank batch retry; attaching proof.',
        ])->assertRedirect();

        $appeal = $restriction->appeals()->firstOrFail();
        $this->actingAs($admin)->post(route('admin.users.appeals.review', $appeal), [
            'decision' => 'approved',
            'admin_notes' => 'Verified with bank statement.',
        ])->assertRedirect();

        $this->assertFalse($seller->fresh()->hasRestriction('payout_hold'));
    }

    public function test_dispute_return_does_not_prematurely_mark_order_returned_before_inspection(): void
    {
        $admin = $this->createUser('admin', 'admin.disp@nexora.test');
        $buyer = $this->createUser('buyer', 'buyer.disp@nexora.test');
        $seller = $this->createUser('seller', 'seller.disp@nexora.test');
        $category = Category::firstOrCreate(['slug' => 'electronics'], ['name' => 'Electronics']);

        $product = Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'name' => 'Returnable Item',
            'slug' => 'returnable-item',
            'price' => 500.00,
            'stock' => 5,
            'weight' => 1.0,
            'status' => 'active',
        ]);

        $buyerAddress = \App\Models\Address::create([
            'user_id' => $buyer->id,
            'recipient_name' => 'Buyer Disp',
            'recipient_phone' => '09171112233',
            'region_code' => '040000000',
            'region_name' => 'Region IV-A',
            'province_code' => '043400000',
            'province_name' => 'Laguna',
            'municipality_code' => '043426000',
            'municipality_name' => 'Santa Cruz',
            'barangay_code' => '043426001',
            'barangay_name' => 'Poblacion I',
            'street' => 'Main St',
            'house_number' => '1',
            'is_default' => true,
        ]);

        $order = Order::create([
            'order_number' => 'ORD-RET-001',
            'tracking_number' => 'NX-RET-001',
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'address_id' => $buyerAddress->id,
            'status' => 'delivered',
            'payment_method' => 'cod',
            'payment_status' => 'paid',
            'subtotal' => 500.00,
            'shipping_fee' => 50.00,
            'total' => 550.00,
            'placed_at' => now(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 2,
            'price' => 500.00,
            'subtotal' => 1000.00,
        ]);

        $dispute = Dispute::create([
            'order_id' => $order->id,
            'raised_by' => $buyer->id,
            'reason' => 'defective',
            'details' => 'Item arrived damaged.',
            'status' => 'open',
        ]);

        // Step 1: Admin resolves dispute in favor of return -> MUST NOT mark order 'returned' or restock yet
        $this->actingAs($admin)->post(route('admin.disputes.resolve', $dispute), [
            'status' => 'resolved',
            'resolution_note' => 'Approved for physical return inspection.',
            'action_type' => 'mark_returned',
        ])->assertRedirect();

        $this->assertSame('delivered', $order->fresh()->status);
        $this->assertSame(5, $product->fresh()->stock);
        $this->assertSame(Dispute::RETURN_STAGE_APPROVED_AWAITING_RETURN, $dispute->fresh()->return_stage);

        // Step 2: Physical receipt + inspection with restock_approved = true
        $this->actingAs($admin)->post(route('admin.disputes.return-step', $dispute), [
            'step' => Dispute::RETURN_STAGE_RECEIVED_FOR_INSPECTION,
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('admin.disputes.return-step', $dispute), [
            'step' => Dispute::RETURN_STAGE_INSPECTED,
            'inspection_condition' => 'good_condition',
            'inspection_notes' => 'Verified intact packaging.',
            'restock_approved' => 1,
        ])->assertRedirect();

        $this->assertSame('returned', $order->fresh()->status);
        $this->assertSame(7, $product->fresh()->stock);
    }

    public function test_admin_monitoring_reports_system_health_and_audit_log_protections(): void
    {
        $admin = $this->createUser('admin', 'admin.ops@nexora.test');
        $logistics = $this->createUser('logistics', 'log.ops@nexora.test');
        LogisticsProfile::create([
            'user_id' => $logistics->id,
            'company_name' => 'Laguna Ops',
            'sec_registration_no' => 'SEC-OPS-1',
            'tin' => '111-222-333-444',
            'region' => 'Region IV-A',
            'province' => 'Laguna',
            'city_municipality' => 'Santa Cruz',
            'barangay' => 'Poblacion I',
            'street_address' => 'Hwy',
            'postal_code' => '4009',
            'contact_person_first_name' => 'Ops',
            'contact_person_last_name' => 'Mgr',
            'position' => 'Mgr',
            'email' => 'log.ops@nexora.test',
            'mobile_number' => '09175551234',
            'status' => 'approved',
        ]);
        Hub::create([
            'logistics_company_id' => $logistics->id,
            'code' => 'HUB-OPS-1',
            'name' => 'Ops Hub',
            'province_code' => '043400000',
            'province_name' => 'Laguna',
            'city_code' => '043426000',
            'city_name' => 'Santa Cruz',
            'barangay_code' => '043426001',
            'barangay_name' => 'Poblacion I',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->get(route('admin.logistics-monitoring.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.financial-monitoring.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.system-health.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.reports.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.reports.export', ['type' => 'safety']))->assertOk();

        // AuditLog redacts sensitive keys and blocks updates/deletes
        $log = AuditLog::log(
            action: 'security_check',
            auditable: $admin,
            oldValues: ['password' => 'Secret123!', 'note' => 'visible'],
            newValues: ['token' => 'tok_abc', 'status' => 'ok'],
            reason: 'Testing redaction',
            operatorId: $admin->id
        );

        $this->assertSame('[REDACTED]', $log->old_values['password']);
        $this->assertSame('[REDACTED]', $log->new_values['token']);
        $this->assertSame('visible', $log->old_values['note']);

        $this->expectException(RuntimeException::class);
        $log->update(['reason' => 'tampered']);
    }

    public function test_navigation_integration_across_admin_seller_logistics_and_buyer_websites(): void
    {
        Storage::fake('local');

        $admin = $this->createUser('admin', 'nav-admin@nexora.test');
        $seller = $this->createUser('seller', 'nav-seller@nexora.test');
        \App\Models\SellerProfile::create([
            'user_id' => $seller->id,
            'business_name' => 'Nav Test Store',
            'category' => 'General Merchandise',
            'description' => 'Store description for safety navigation testing.',
        ]);
        $logistics = $this->createUser('sorting_center', 'nav-logistics@nexora.test');
        $buyer = $this->createUser('buyer', 'nav-buyer@nexora.test');

        $category = \App\Models\Category::create([
            'name' => 'Safety Nav Category',
            'slug' => 'safety-nav-category',
            'is_active' => true,
        ]);

        $product = \App\Models\Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'name' => 'Test Widget Alpha',
            'slug' => 'test-widget-alpha',
            'description' => 'Sample product for safety report navigation testing.',
            'price' => 499.00,
            'stock' => 10,
            'status' => 'active',
        ]);

        // 1. Admin sidebar includes all required monitoring, safety, fraud, health & reports links
        $adminDash = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $adminDash->assertSee(route('admin.safety.index'), false);
        $adminDash->assertSee(route('admin.logistics-monitoring.index'), false);
        $adminDash->assertSee(route('admin.financial-monitoring.index'), false);
        $adminDash->assertSee(route('admin.fraud-alerts.index'), false);
        $adminDash->assertSee(route('admin.system-health.index'), false);
        $adminDash->assertSee(route('admin.reports.index'), false);

        // 2. Seller sidebar includes Safety & Reports and /safety-reports renders cleanly
        $this->actingAs($seller)->get(route('seller.dashboard'))
            ->assertOk()
            ->assertSee(route('safety-reports.index'), false);
        $this->actingAs($seller)->get(route('safety-reports.index'))
            ->assertOk()
            ->assertSee('Safety Reports');

        // 3. Logistics sidebar includes Safety & Reports and /safety-reports renders cleanly
        $this->actingAs($logistics)->get(route('logistics.dashboard'))
            ->assertOk()
            ->assertSee(route('safety-reports.index'), false);
        $this->actingAs($logistics)->get(route('safety-reports.index'))
            ->assertOk()
            ->assertSee('Safety Reports');

        // 4. Buyer Website navigation, contextual Report Product / Report Seller links, prefill, evidence upload & appeals
        $this->actingAs($buyer)->get(route('buyer.dashboard'))
            ->assertOk()
            ->assertSee(route('safety-reports.index'), false);

        $this->actingAs($buyer)->get(route('products.show', $product->id))
            ->assertOk()
            ->assertSee('Report Product');

        $this->actingAs($buyer)->get(route('stores.show', $seller->id))
            ->assertOk()
            ->assertSee('Report Seller');

        $this->actingAs($buyer)->get(route('safety-reports.index', [
            'category' => 'fake_misleading_product',
            'title' => 'Report product: Test Widget Alpha',
            'product_id' => $product->id,
            'reported_user_id' => $seller->id,
            'subject_type' => 'product',
            'subject_id' => $product->id,
        ]))->assertOk()->assertSee('Context linked');

        $evidence = UploadedFile::fake()->create('proof.pdf', 120, 'application/pdf');
        $this->actingAs($buyer)->post(route('safety-reports.store'), [
            'category' => 'fake_misleading_product',
            'title' => 'Report product: Test Widget Alpha',
            'description' => 'Misleading listing specs compared to delivered item.',
            'reported_user_id' => $seller->id,
            'product_id' => $product->id,
            'subject_type' => 'product',
            'subject_id' => $product->id,
            'evidence' => [$evidence],
        ])->assertRedirect();

        $submitted = SafetyReport::where('reporter_id', $buyer->id)->latest()->firstOrFail();
        $this->assertSame($product->id, $submitted->product_id);
        $this->assertNotEmpty($submitted->evidence_paths);
        Storage::disk('local')->assertExists($submitted->evidence_paths[0]);
    }

    private function createUser(string $role, string $email): User
    {
        return User::create([
            'first_name' => ucfirst($role),
            'last_name' => 'User',
            'email' => $email,
            'contact_number' => '0917' . rand(1000000, 9999999),
            'birthday' => '1992-01-01',
            'age' => 34,
            'sex' => 'other',
            'password' => Hash::make('Password123!@#'),
            'role' => $role,
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
    }
}
