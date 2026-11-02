<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\CourierProfile;
use App\Models\SortingCenterProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MobileAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_buyer_can_login_with_valid_credentials_and_receives_sanctum_token(): void
    {
        $buyer = User::factory()->create([
            'email' => 'buyer.test@example.com',
            'password' => Hash::make('Password123!@#'),
            'role' => 'buyer',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/mobile/v1/auth/buyer/login', [
            'email' => 'buyer.test@example.com',
            'password' => 'Password123!@#',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'user' => ['id', 'email', 'role', 'full_name'],
                ],
                'message',
            ]);

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertEquals($buyer->id, $response->json('data.user.id'));
    }

    public function test_buyer_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'buyer.test@example.com',
            'password' => Hash::make('Password123!@#'),
            'role' => 'buyer',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/mobile/v1/auth/buyer/login', [
            'email' => 'buyer.test@example.com',
            'password' => 'WrongPassword',
        ]);

        $response->assertStatus(401)
            ->assertJsonFragment([
                'message' => "Those credentials don't match our records.",
            ]);
    }

    public function test_buyer_login_fails_if_role_is_not_buyer(): void
    {
        User::factory()->create([
            'email' => 'seller.test@example.com',
            'password' => Hash::make('Password123!@#'),
            'role' => 'seller',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/mobile/v1/auth/buyer/login', [
            'email' => 'seller.test@example.com',
            'password' => 'Password123!@#',
        ]);

        $response->assertStatus(403)
            ->assertJsonFragment([
                'message' => 'Access denied. Account is not a registered buyer.',
            ]);
    }

    public function test_buyer_login_fails_if_disapproved(): void
    {
        User::factory()->create([
            'email' => 'rejected.buyer@example.com',
            'password' => Hash::make('Password123!@#'),
            'role' => 'buyer',
            'approval_status' => 'disapproved',
            'disapproval_reason' => 'Invalid government ID provided.',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/mobile/v1/auth/buyer/login', [
            'email' => 'rejected.buyer@example.com',
            'password' => 'Password123!@#',
        ]);

        $response->assertStatus(403)
            ->assertJsonFragment([
                'message' => 'Your application was not approved. Reason: Invalid government ID provided.',
            ]);
    }

    public function test_buyer_login_fails_if_suspended(): void
    {
        User::factory()->create([
            'email' => 'suspended.buyer@example.com',
            'password' => Hash::make('Password123!@#'),
            'role' => 'buyer',
            'approval_status' => 'approved',
            'account_status' => 'suspended',
            'status_reason' => 'Violation of terms.',
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/mobile/v1/auth/buyer/login', [
            'email' => 'suspended.buyer@example.com',
            'password' => 'Password123!@#',
        ]);

        $response->assertStatus(403)
            ->assertJsonFragment([
                'message' => 'Your account has been suspended. Reason: Violation of terms. Please contact support.',
            ]);
    }

    public function test_buyer_login_requires_email_verification(): void
    {
        User::factory()->create([
            'email' => 'unverified.buyer@example.com',
            'password' => Hash::make('Password123!@#'),
            'role' => 'buyer',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => null,
        ]);

        $response = $this->postJson('/api/mobile/v1/auth/buyer/login', [
            'email' => 'unverified.buyer@example.com',
            'password' => 'Password123!@#',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('data.email_verified', false);
    }

    public function test_buyer_login_requires_admin_approval(): void
    {
        User::factory()->create([
            'email' => 'pending.buyer@example.com',
            'password' => Hash::make('Password123!@#'),
            'role' => 'buyer',
            'approval_status' => 'pending',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/mobile/v1/auth/buyer/login', [
            'email' => 'pending.buyer@example.com',
            'password' => 'Password123!@#',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('data.approval_status', 'pending');
    }

    public function test_buyer_registration_creates_account_and_address_and_sends_otp(): void
    {
        $idFile = UploadedFile::fake()->create('valid_id.jpg', 500, 'image/jpeg');

        $payload = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'middle_initial' => 'A',
            'sex' => 'male',
            'birthday' => '1998-05-15',
            'email' => 'newbuyer@example.com',
            'contact_number' => '09179876543',
            'password' => 'Password123!@#',
            'password_confirmation' => 'Password123!@#',
            'province_code' => '043400000',
            'province_name' => 'Laguna',
            'municipality_code' => '043426000',
            'municipality_name' => 'Santa Cruz',
            'barangay_code' => '043426001',
            'barangay_name' => 'Poblacion I',
            'street' => 'Rizal St.',
            'house_number' => '12',
            'valid_id' => $idFile,
            'terms' => true,
        ];

        $response = $this->postJson('/api/mobile/v1/auth/buyer/register', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.email_verification_required', true)
            ->assertJsonPath('data.user.email', 'newbuyer@example.com');

        $this->assertDatabaseHas('users', [
            'email' => 'newbuyer@example.com',
            'role' => 'buyer',
            'approval_status' => 'pending',
        ]);

        $user = User::where('email', 'newbuyer@example.com')->first();
        $this->assertNotNull($user->email_verification_otp);
        $this->assertDatabaseHas('addresses', [
            'user_id' => $user->id,
            'province_name' => 'Laguna',
            'municipality_name' => 'Santa Cruz',
        ]);
    }

    public function test_buyer_can_verify_email_with_valid_otp(): void
    {
        $buyer = User::factory()->create([
            'email' => 'verify.me@example.com',
            'role' => 'buyer',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => null,
            'email_verification_otp' => '654321',
            'email_verification_otp_expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/mobile/v1/auth/buyer/email/verify', [
            'email' => 'verify.me@example.com',
            'otp' => '654321',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.email_verified', true)
            ->assertJsonStructure(['data' => ['token', 'user']]);

        $this->assertNotNull($buyer->fresh()->email_verified_at);
        $this->assertNull($buyer->fresh()->email_verification_otp);
    }

    public function test_buyer_email_verification_fails_with_invalid_or_expired_otp(): void
    {
        User::factory()->create([
            'email' => 'verify.me@example.com',
            'role' => 'buyer',
            'email_verified_at' => null,
            'email_verification_otp' => '654321',
            'email_verification_otp_expires_at' => now()->subMinute(),
        ]);

        // Wrong code
        $wrongResponse = $this->postJson('/api/mobile/v1/auth/buyer/email/verify', [
            'email' => 'verify.me@example.com',
            'otp' => '111111',
        ]);
        $wrongResponse->assertStatus(422);

        // Expired code
        $expiredResponse = $this->postJson('/api/mobile/v1/auth/buyer/email/verify', [
            'email' => 'verify.me@example.com',
            'otp' => '654321',
        ]);
        $expiredResponse->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'The verification code has expired. Please request a new code.',
            ]);
    }

    public function test_rider_can_login_with_valid_credentials_and_receives_sanctum_token(): void
    {
        $hub = User::factory()->create([
            'role' => 'sorting_center',
            'approval_status' => 'approved',
            'account_status' => 'active',
        ]);
        SortingCenterProfile::create([
            'user_id' => $hub->id,
            'business_name' => 'Laguna Sorting Hub',
        ]);

        $rider = User::factory()->create([
            'email' => 'pedro.rider@example.com',
            'password' => Hash::make('RiderPass123!@#'),
            'role' => 'courier',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        CourierProfile::create([
            'user_id' => $rider->id,
            'sorting_center_id' => $hub->id,
            'vehicle_type' => 'Motorcycle',
            'plate_number' => 'MC-9999',
            'license_number' => 'N01-22-123456',
            'is_online' => true,
        ]);

        $response = $this->postJson('/api/mobile/v1/auth/rider/login', [
            'email' => 'pedro.rider@example.com',
            'password' => 'RiderPass123!@#',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'user' => ['id', 'email', 'role'],
                    'courier_profile' => ['vehicle_type', 'plate_number', 'sorting_center'],
                ],
                'message',
            ]);

        $this->assertEquals('Motorcycle', $response->json('data.courier_profile.vehicle_type'));
    }

    public function test_rider_login_blocked_if_pending_activation(): void
    {
        User::factory()->create([
            'email' => 'pending.rider@example.com',
            'password' => Hash::make('RiderPass123!@#'),
            'role' => 'courier',
            'approval_status' => 'approved',
            'account_status' => 'pending_activation',
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/mobile/v1/auth/rider/login', [
            'email' => 'pending.rider@example.com',
            'password' => 'RiderPass123!@#',
        ]);

        $response->assertStatus(403)
            ->assertJsonFragment([
                'message' => 'Account Pending Activation. Please complete your password setup via your activation email.',
            ]);
    }

    public function test_rider_login_blocked_if_suspended(): void
    {
        User::factory()->create([
            'email' => 'suspended.rider@example.com',
            'password' => Hash::make('RiderPass123!@#'),
            'role' => 'courier',
            'approval_status' => 'approved',
            'account_status' => 'suspended',
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/mobile/v1/auth/rider/login', [
            'email' => 'suspended.rider@example.com',
            'password' => 'RiderPass123!@#',
        ]);

        $response->assertStatus(403)
            ->assertJsonFragment([
                'message' => 'Account is suspended or deactivated. Contact your logistics operator.',
            ]);
    }

    public function test_rider_can_activate_account_with_token(): void
    {
        $rider = User::factory()->create([
            'email' => 'activate.rider@example.com',
            'role' => 'courier',
            'account_status' => 'pending_activation',
            'approval_status' => 'pending',
            'activation_token' => 'VALID_ACTIVATE_TOKEN_12345',
            'activation_token_expires_at' => now()->addDay(),
        ]);

        $response = $this->postJson('/api/mobile/v1/auth/rider/activate/VALID_ACTIVATE_TOKEN_12345', [
            'password' => 'NewSecurePassword123!@#',
            'password_confirmation' => 'NewSecurePassword123!@#',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['token', 'user']]);

        $this->assertEquals('active', $rider->fresh()->account_status);
        $this->assertEquals('approved', $rider->fresh()->approval_status);
        $this->assertNull($rider->fresh()->activation_token);
        $this->assertTrue(Hash::check('NewSecurePassword123!@#', $rider->fresh()->password));
    }

    public function test_authenticated_user_can_access_me_and_logout(): void
    {
        $buyer = User::factory()->create([
            'role' => 'buyer',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $token = $buyer->createToken('test-token')->plainTextToken;

        $meResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/mobile/v1/auth/me');

        $meResponse->assertStatus(200)
            ->assertJsonPath('data.user.id', $buyer->id);

        $logoutResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/mobile/v1/auth/logout');

        $logoutResponse->assertStatus(200)
            ->assertJsonFragment(['message' => 'Successfully logged out.']);

        $this->assertCount(0, $buyer->fresh()->tokens);
    }
}

