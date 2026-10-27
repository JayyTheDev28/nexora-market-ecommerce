<?php

namespace Tests\Feature;

use App\Models\CourierProfile;
use App\Models\Order;
use App\Models\User;
use App\Notifications\RiderActivationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RiderManagementAndActivationTest extends TestCase
{
    use RefreshDatabase;

    private function createLogisticsUser(): User
    {
        return User::factory()->create([
            'role' => 'sorting_center',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function createBuyerUser(): User
    {
        return User::factory()->create([
            'role' => 'buyer',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function createSellerUser(): User
    {
        return User::factory()->create([
            'role' => 'seller',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    public function test_only_approved_active_logistics_user_can_access_add_rider_form(): void
    {
        // 1. Guest is redirected to login
        $this->get(route('logistics.riders.create'))
            ->assertRedirect(route('login'));

        // 2. Buyer is blocked/redirected away from logistics
        $buyer = $this->createBuyerUser();
        $this->actingAs($buyer)
            ->get(route('logistics.riders.create'))
            ->assertRedirect($buyer->homeRoute());

        // 3. Seller is blocked/redirected away from logistics
        $seller = $this->createSellerUser();
        $this->actingAs($seller)
            ->get(route('logistics.riders.create'))
            ->assertRedirect($seller->homeRoute());

        // 4. Pending logistics user is redirected to pending screen
        $pendingLogistics = User::factory()->create([
            'role' => 'sorting_center',
            'approval_status' => 'pending',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
        $this->actingAs($pendingLogistics)
            ->get(route('logistics.riders.create'))
            ->assertRedirect(route('buyer.pending'));

        // 5. Approved, active logistics operations user can view form
        $logistics = $this->createLogisticsUser();
        $this->actingAs($logistics)
            ->get(route('logistics.riders.create'))
            ->assertOk()
            ->assertSee('Add New Courier Rider');
    }

    public function test_public_self_service_registration_is_blocked_for_couriers(): void
    {
        $response = $this->post(route('register.store'), [
            'role' => 'courier',
            'first_name' => 'Carlos',
            'last_name' => 'Rider',
            'email' => 'carlos.rider@example.com',
            'contact_number' => '09170009999',
            'birthday' => '1995-05-10',
            'password' => 'Password123!@#',
            'password_confirmation' => 'Password123!@#',
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'carlos.rider@example.com']);
    }

    public function test_add_rider_validates_required_fields_and_rules(): void
    {
        Storage::fake('public');
        $logistics = $this->createLogisticsUser();

        $existingBuyer = $this->createBuyerUser();

        // Invalid submission: non-alpha name, underage (<18), duplicate email, invalid phone, invalid license format, past expiry
        $response = $this->actingAs($logistics)->post(route('logistics.riders.store'), [
            'first_name' => 'Juan123',
            'last_name' => 'Dela Cruz!',
            'birthday' => now()->subYears(16)->format('Y-m-d'), // 16 years old
            'email' => $existingBuyer->email, // Duplicate email across roles
            'contact_number' => '08123456789', // Invalid prefix, not 09
            'license_number' => 'INVALID-123', // Invalid format
            'license_expiry_date' => now()->subDay()->format('Y-m-d'), // Past date
            'vehicle_type' => 'Spaceship', // Invalid vehicle type
            'plate_number' => '',
            'license_file' => UploadedFile::fake()->create('license.exe', 100), // Disallowed extension
            'or_cr_file' => UploadedFile::fake()->create('orcr.pdf', 6000), // > 5MB
        ]);

        $response->assertSessionHasErrors([
            'first_name',
            'last_name',
            'birthday',
            'email',
            'contact_number',
            'license_number',
            'license_expiry_date',
            'vehicle_type',
            'plate_number',
            'license_file',
            'or_cr_file',
        ]);
    }

    public function test_logistics_user_can_successfully_create_rider_with_activation_token(): void
    {
        Storage::fake('public');
        Notification::fake();

        $logistics = $this->createLogisticsUser();

        $licenseFile = UploadedFile::fake()->create('driver_license.pdf', 1024, 'application/pdf');
        $orCrFile = UploadedFile::fake()->create('or_cr.jpg', 1024, 'image/jpeg');

        $response = $this->actingAs($logistics)->post(route('logistics.riders.store'), [
            'first_name' => 'Danilo',
            'last_name' => 'Reyes',
            'birthday' => '1996-04-15',
            'email' => 'danilo.reyes@example.com',
            'contact_number' => '09171239876',
            'license_number' => 'N01-23-987654',
            'license_expiry_date' => now()->addYears(3)->format('Y-m-d'),
            'vehicle_type' => 'Motorcycle',
            'plate_number' => 'MC-9876',
            'license_file' => $licenseFile,
            'or_cr_file' => $orCrFile,
        ]);

        $response->assertRedirect(route('logistics.riders.index', ['tab' => 'pending_activation']));

        $rider = User::where('email', 'danilo.reyes@example.com')->first();
        $this->assertNotNull($rider);
        $this->assertEquals('courier', $rider->role);
        $this->assertEquals('pending_activation', $rider->account_status);
        $this->assertEquals('approved', $rider->approval_status);
        $this->assertNull($rider->email_verified_at);
        $this->assertNotNull($rider->activation_token);
        $this->assertTrue($rider->activation_token_expires_at->isFuture());

        // Courier profile asserts
        $profile = CourierProfile::where('user_id', $rider->id)->first();
        $this->assertNotNull($profile);
        $this->assertEquals($logistics->id, $profile->sorting_center_id);
        $this->assertEquals('N01-23-987654', $profile->license_number);
        $this->assertEquals('MC-9876', $profile->plate_number);
        $this->assertEquals('Motorcycle', $profile->vehicle_type);
        $this->assertNotNull($profile->license_path);
        $this->assertNotNull($profile->or_cr_path);

        // Assert activation email sent
        Notification::assertSentTo($rider, RiderActivationNotification::class, function ($notification) use ($rider) {
            return $notification->token === $rider->activation_token;
        });
    }

    public function test_tenant_isolation_riders_belong_strictly_to_creating_logistics_company(): void
    {
        $logistics1 = $this->createLogisticsUser();
        $logistics2 = $this->createLogisticsUser();

        // Create rider for logistics 1
        $rider1 = User::factory()->create([
            'role' => 'courier',
            'approval_status' => 'approved',
            'account_status' => 'active',
        ]);
        CourierProfile::create([
            'user_id' => $rider1->id,
            'sorting_center_id' => $logistics1->id,
            'vehicle_type' => 'Motorcycle',
            'plate_number' => 'L1-001',
            'license_number' => 'N01-23-000001',
        ]);

        // Logistics 1 sees rider 1
        $response1 = $this->actingAs($logistics1)->get(route('logistics.riders.index'));
        $response1->assertOk()->assertSee($rider1->first_name);

        // Logistics 2 cannot see rider 1 in their index
        $response2 = $this->actingAs($logistics2)->get(route('logistics.riders.index'));
        $response2->assertOk()->assertDontSee($rider1->first_name);

        // Logistics 2 cannot view rider 1's profile directly (403)
        $this->actingAs($logistics2)->get(route('logistics.riders.show', $rider1))
            ->assertForbidden();
    }

    public function test_unactivated_rider_cannot_log_in(): void
    {
        $rider = User::factory()->create([
            'role' => 'courier',
            'approval_status' => 'approved',
            'account_status' => 'pending_activation',
            'email' => 'unactivated.rider@example.com',
            'password' => Hash::make('Password123!@#'),
        ]);

        $response = $this->post(route('login.store'), [
            'email' => 'unactivated.rider@example.com',
            'password' => 'Password123!@#',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_activation_flow_with_token_validation_and_password_strength(): void
    {
        $token = 'valid-activation-token-50-chars-long-test-string';
        $rider = User::factory()->create([
            'role' => 'courier',
            'approval_status' => 'approved',
            'account_status' => 'pending_activation',
            'email_verified_at' => null,
            'activation_token' => $token,
            'activation_token_expires_at' => now()->addHours(24),
        ]);

        // 1. Invalid token display
        $this->get(route('rider.activate', ['token' => 'non-existent-token']))
            ->assertOk()
            ->assertSee('Invalid or Used Activation Link');

        // 2. Expired token display
        $rider->update(['activation_token_expires_at' => now()->subHour()]);
        $this->get(route('rider.activate', ['token' => $token]))
            ->assertOk()
            ->assertSee('Activation Link Expired');

        // 3. Valid token displays set password form
        $rider->update(['activation_token_expires_at' => now()->addHours(24)]);
        $this->get(route('rider.activate', ['token' => $token]))
            ->assertOk()
            ->assertSee('New Password')
            ->assertSee('Confirm Password');

        // 4. Submitting weak password fails
        $weakResponse = $this->post(route('rider.activate.post', ['token' => $token]), [
            'password' => 'simple',
            'password_confirmation' => 'simple',
        ]);
        $weakResponse->assertSessionHasErrors('password');
        $this->assertEquals('pending_activation', $rider->fresh()->account_status);

        // 5. Submitting strong password activates account and logs in
        $strongResponse = $this->post(route('rider.activate.post', ['token' => $token]), [
            'password' => 'SecurePass123!@#',
            'password_confirmation' => 'SecurePass123!@#',
        ]);

        $strongResponse->assertRedirect(route('courier.dashboard'));
        $this->assertAuthenticatedAs($rider);

        $rider->refresh();
        $this->assertEquals('active', $rider->account_status);
        $this->assertNotNull($rider->email_verified_at);
        $this->assertNull($rider->activation_token);
        $this->assertNull($rider->activation_token_expires_at);

        // 6. Token is single-use and cannot be used again
        auth()->logout();
        $this->get(route('rider.activate', ['token' => $token]))
            ->assertOk()
            ->assertSee('Invalid or Used Activation Link');
    }

    public function test_logistics_can_resend_activation_email(): void
    {
        Notification::fake();
        $logistics = $this->createLogisticsUser();

        $oldToken = 'old-activation-token';
        $rider = User::factory()->create([
            'role' => 'courier',
            'approval_status' => 'approved',
            'account_status' => 'pending_activation',
            'activation_token' => $oldToken,
            'activation_token_expires_at' => now()->addHours(1),
        ]);
        CourierProfile::create([
            'user_id' => $rider->id,
            'sorting_center_id' => $logistics->id,
            'vehicle_type' => 'Motorcycle',
            'plate_number' => 'MC-7777',
            'license_number' => 'N01-23-777777',
        ]);

        $response = $this->actingAs($logistics)->post(route('logistics.riders.resend-activation', $rider));
        $response->assertSessionHas('status');

        $rider->refresh();
        $this->assertNotEquals($oldToken, $rider->activation_token);
        $this->assertTrue($rider->activation_token_expires_at->isFuture());

        Notification::assertSentTo($rider, RiderActivationNotification::class);
    }

    public function test_rider_can_access_rider_dashboard_and_is_blocked_from_other_roles(): void
    {
        $rider = User::factory()->create([
            'role' => 'courier',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
        CourierProfile::create([
            'user_id' => $rider->id,
            'vehicle_type' => 'Motorcycle',
            'plate_number' => 'MC-9999',
            'license_number' => 'N01-23-999999',
        ]);

        // Rider can access courier dashboard
        $this->actingAs($rider)
            ->get(route('courier.dashboard'))
            ->assertOk()
            ->assertSee('Active Courier');

        // Rider blocked from buyer dashboard / account
        $this->actingAs($rider)
            ->get('/buyer/dashboard')
            ->assertRedirect('/courier/dashboard');

        // Rider blocked from seller dashboard
        $this->actingAs($rider)
            ->get('/seller/dashboard')
            ->assertRedirect('/courier/dashboard');

        // Rider blocked from admin dashboard
        $this->actingAs($rider)
            ->get('/admin/dashboard')
            ->assertRedirect('/courier/dashboard');

        // Rider blocked from logistics dashboard
        $this->actingAs($rider)
            ->get('/logistics/dashboard')
            ->assertRedirect('/courier/dashboard');
    }
}
