<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Commission;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\SellerWarning;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $buyer;
    protected User $seller;
    protected \App\Models\Address $address;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $this->buyer = User::factory()->create([
            'role' => 'buyer',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $this->address = \App\Models\Address::create([
            'user_id' => $this->buyer->id,
            'province_code' => '043400000',
            'province_name' => 'Laguna',
            'municipality_code' => '043426000',
            'municipality_name' => 'Santa Cruz',
            'barangay_code' => '043426001',
            'barangay_name' => 'Poblacion I',
            'street' => '123 High St',
            'house_number' => 'Unit 4',
            'recipient_name' => $this->buyer->full_name,
            'recipient_phone' => '09170001234',
            'is_default' => true,
        ]);

        $this->seller = User::factory()->create([
            'role' => 'seller',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        SellerProfile::create([
            'user_id' => $this->seller->id,
            'business_name' => 'Demo Artisan Store',
            'category' => 'Retail',
        ]);
    }

    public function test_non_admin_cannot_access_admin_dashboard(): void
    {
        $response = $this->actingAs($this->buyer)->get(route('admin.dashboard'));
        $response->assertRedirect($this->buyer->homeRoute());
    }

    public function test_admin_can_access_dashboard_and_metrics(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Platform Administration');
        $response->assertSee('Platform GMV');
        $response->assertSee('Platform Commission');
    }

    public function test_admin_can_view_applications_and_approve(): void
    {
        $applicant = User::factory()->create([
            'role' => 'buyer',
            'approval_status' => 'pending',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.applications.index'));
        $response->assertStatus(200);
        $response->assertSee($applicant->full_name);

        $approveResponse = $this->actingAs($this->admin)->post(route('admin.applications.approve', $applicant));
        $approveResponse->assertSessionHas('status');

        $applicant->refresh();
        $this->assertEquals('approved', $applicant->approval_status);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'application_approved',
            'auditable_type' => User::class,
            'auditable_id' => $applicant->id,
        ]);
    }

    public function test_admin_can_disapprove_application_with_reason(): void
    {
        $applicant = User::factory()->create([
            'role' => 'seller',
            'approval_status' => 'pending',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $declineResponse = $this->actingAs($this->admin)->post(route('admin.applications.disapprove', $applicant), [
            'reason' => 'Invalid business permit submitted.',
        ]);

        $declineResponse->assertSessionHas('status');

        $applicant->refresh();
        $this->assertEquals('disapproved', $applicant->approval_status);
        $this->assertEquals('Invalid business permit submitted.', $applicant->disapproval_reason);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'application_declined',
            'auditable_type' => User::class,
            'auditable_id' => $applicant->id,
            'reason' => 'Invalid business permit submitted.',
        ]);
    }

    public function test_admin_can_manage_user_accounts_and_suspend_user(): void
    {
        $user = User::factory()->create([
            'role' => 'buyer',
            'account_status' => 'active',
            'approval_status' => 'approved',
            'email_verified_at' => now(),
        ]);

        $indexResponse = $this->actingAs($this->admin)->get(route('admin.users.index'));
        $indexResponse->assertStatus(200);
        $indexResponse->assertSee($user->full_name);

        $suspendResponse = $this->actingAs($this->admin)->post(route('admin.users.status', $user), [
            'status' => 'suspended',
            'reason' => 'Violation of community purchasing rules.',
        ]);

        $suspendResponse->assertSessionHas('status');

        $user->refresh();
        $this->assertEquals('suspended', $user->account_status);
        $this->assertEquals('Violation of community purchasing rules.', $user->status_reason);

        // Suspended user should now be blocked from authenticated platform routes
        $buyerDashboard = $this->actingAs($user)->get(route('buyer.dashboard'));
        $buyerDashboard->assertRedirect(route('login'));

        // Check audit log
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user_status_changed',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'reason' => 'Violation of community purchasing rules.',
        ]);
    }

    public function test_admin_cannot_suspend_themselves(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.users.status', $this->admin), [
            'status' => 'suspended',
            'reason' => 'Self suspension test',
        ]);

        $response->assertSessionHasErrors('status');
        $this->admin->refresh();
        $this->assertEquals('active', $this->admin->account_status);
    }

    public function test_admin_can_issue_compliance_warning_and_archive_product(): void
    {
        $category = Category::firstOrCreate(['slug' => 'electronics'], [
            'name' => 'Electronics',
        ]);

        $product = Product::create([
            'seller_id' => $this->seller->id,
            'category_id' => $category->id,
            'name' => 'Restricted Drone 4K',
            'slug' => 'restricted-drone-4k',
            'price' => 5000,
            'stock' => 10,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.compliance.warnings.store'), [
            'seller_id' => $this->seller->id,
            'product_id' => $product->id,
            'violation_type' => 'prohibited_item',
            'details' => 'Drones with uncertified transmitters are restricted on the marketplace.',
            'archive_product' => 1,
        ]);

        $response->assertSessionHas('status');

        $this->assertDatabaseHas('seller_warnings', [
            'seller_id' => $this->seller->id,
            'product_id' => $product->id,
            'violation_type' => 'prohibited_item',
        ]);

        $product->refresh();
        $this->assertEquals('archived', $product->status);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'seller_warning_issued',
            'auditable_type' => SellerWarning::class,
        ]);
    }

    public function test_admin_can_review_and_resolve_disputes(): void
    {
        $order = Order::create([
            'order_number' => 'NEX-TEST-DISP-001',
            'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'address_id' => $this->address->id,
            'subtotal' => 1500,
            'shipping_fee' => 100,
            'total' => 1600,
            'payment_method' => 'cod',
            'status' => 'delivered',
        ]);

        $dispute = Dispute::create([
            'order_id' => $order->id,
            'raised_by' => $this->buyer->id,
            'reason' => 'Item arrived damaged',
            'details' => 'The product screen was cracked upon unboxing.',
            'status' => 'open',
        ]);

        $showResponse = $this->actingAs($this->admin)->get(route('admin.disputes.show', $dispute));
        $showResponse->assertStatus(200);
        $showResponse->assertSee('Item arrived damaged');

        $resolveResponse = $this->actingAs($this->admin)->post(route('admin.disputes.resolve', $dispute), [
            'status' => 'resolved',
            'action_type' => 'mark_returned',
            'resolution_note' => 'Photographic evidence confirmed transit damage. Return & full refund granted.',
        ]);

        $resolveResponse->assertRedirect(route('admin.disputes.show', $dispute));

        $dispute->refresh();
        $this->assertEquals('resolved', $dispute->status);
        $this->assertEquals('Photographic evidence confirmed transit damage. Return & full refund granted.', $dispute->resolution_note);

        $order->refresh();
        $this->assertEquals('returned', $order->status);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'dispute_resolved',
            'auditable_type' => Dispute::class,
            'auditable_id' => $dispute->id,
        ]);
    }

    public function test_admin_can_manage_and_settle_commissions(): void
    {
        $order = Order::create([
            'order_number' => 'NEX-TEST-COMM-001',
            'buyer_id' => $this->buyer->id,
            'seller_id' => $this->seller->id,
            'address_id' => $this->address->id,
            'subtotal' => 2000,
            'shipping_fee' => 100,
            'total' => 2100,
            'payment_method' => 'cod',
            'status' => 'completed',
        ]);

        $commission = Commission::create([
            'order_id' => $order->id,
            'seller_id' => $this->seller->id,
            'order_total' => 2000,
            'rate' => 0.10,
            'commission_amount' => 200,
            'seller_payout' => 1800,
            'status' => 'pending',
        ]);

        $indexResponse = $this->actingAs($this->admin)->get(route('admin.commissions.index'));
        $indexResponse->assertStatus(200);
        $indexResponse->assertSee('200.00');

        $settleResponse = $this->actingAs($this->admin)->post(route('admin.commissions.settle', $commission));
        $settleResponse->assertSessionHas('status');

        $commission->refresh();
        $this->assertEquals('settled', $commission->status);
        $this->assertNotNull($commission->settled_at);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'commission_settled',
            'auditable_type' => Commission::class,
            'auditable_id' => $commission->id,
        ]);
    }

    public function test_admin_can_update_platform_settings_and_post_announcements(): void
    {
        $settingsResponse = $this->actingAs($this->admin)->post(route('admin.settings.platform.update'), [
            'platform_name' => 'Nexora Marketplace Enterprise',
            'commission_rate' => 12.50,
            'return_window_days' => 14,
            'support_email' => 'customercare@nexoramarket.store',
            'support_phone' => '09181234567',
            'maintenance_mode' => 1,
            'maintenance_message' => 'Routine maintenance tonight at midnight.',
        ]);

        $settingsResponse->assertSessionHas('status');

        $this->assertDatabaseHas('platform_settings', [
            'key' => 'commission_rate',
            'value' => '12.5',
        ]);

        $announcementResponse = $this->actingAs($this->admin)->post(route('admin.settings.announcements.store'), [
            'title' => 'Important: Holiday Delivery Cutoff Times',
            'body' => 'All sorting hubs and sellers are requested to dispatch priority packages by 5:00 PM.',
            'target_roles' => ['seller', 'sorting_center', 'courier'],
        ]);

        $announcementResponse->assertSessionHas('status');

        $this->assertDatabaseHas('announcements', [
            'title' => 'Important: Holiday Delivery Cutoff Times',
            'posted_by' => $this->admin->id,
        ]);
    }

    public function test_audit_logs_are_strictly_read_only(): void
    {
        $log = AuditLog::create([
            'operator_id' => $this->admin->id,
            'action' => 'manual_test_event',
            'auditable_type' => User::class,
            'auditable_id' => $this->admin->id,
            'reason' => 'Initial test log',
        ]);

        $this->expectException(RuntimeException::class);
        $log->update(['reason' => 'Tampered reason']);
    }
}
