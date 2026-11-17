<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PasswordResetOtpNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthRememberAndPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(string $role, string $email, string $contact = '09171234567'): User
    {
        return User::create([
            'first_name' => ucfirst($role),
            'last_name' => 'Tester',
            'email' => $email,
            'contact_number' => $contact,
            'birthday' => '1994-03-15',
            'age' => 32,
            'sex' => 'other',
            'password' => Hash::make('OldPassword123!'),
            'role' => $role,
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    public function test_web_login_supports_remember_me_and_links_to_forgot_password(): void
    {
        $seller = $this->createUser('seller', 'seller.remember@nexora.test', '09171110001');

        $loginView = $this->get(route('login'))->assertOk();
        $loginView->assertSee(route('password.request'), false);
        $loginView->assertDontSee('<a href="#"', false);

        // Log in with Remember Me checked
        $resp = $this->post(route('login.store'), [
            'email' => $seller->email,
            'password' => 'OldPassword123!',
            'remember' => '1',
        ]);
        $resp->assertRedirect();
        $resp->assertCookie('nexora_remember_email', $seller->email);
        $this->assertNotEmpty($seller->fresh()->remember_token);
    }

    public function test_web_forgot_and_reset_password_works_end_to_end_across_roles(): void
    {
        Notification::fake();

        $admin = $this->createUser('admin', 'admin.reset@nexora.test', '09172220002');

        $this->get(route('password.request'))->assertOk()->assertSee('Forgot your password');

        $this->post(route('password.email'), ['email' => $admin->email])
            ->assertRedirect(route('password.reset', ['email' => $admin->email]));

        $otp = null;
        Notification::assertSentTo($admin, PasswordResetOtpNotification::class, function ($notification) use (&$otp) {
            $otp = $notification->otp;

            return strlen($otp) === 6;
        });

        $this->assertNotNull($otp);

        // Wrong OTP rejected
        $this->post(route('password.update'), [
            'email' => $admin->email,
            'otp' => '000000',
            'password' => 'NewPassword456!',
            'password_confirmation' => 'NewPassword456!',
        ])->assertSessionHasErrors('otp');

        // Valid OTP resets password cleanly
        $this->post(route('password.update'), [
            'email' => $admin->email,
            'otp' => $otp,
            'password' => 'NewPassword456!',
            'password_confirmation' => 'NewPassword456!',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $admin->email]);

        // Old password fails, new password succeeds
        $this->post(route('login.store'), [
            'email' => $admin->email,
            'password' => 'OldPassword123!',
        ])->assertSessionHasErrors('email');

        $this->post(route('login.store'), [
            'email' => $admin->email,
            'password' => 'NewPassword456!',
        ])->assertRedirect(route('admin.dashboard'));
    }

    public function test_mobile_api_remember_me_and_forgot_password_work_for_buyer_and_rider(): void
    {
        Notification::fake();

        $buyer = $this->createUser('buyer', 'buyer.mobile@nexora.test', '09173330003');
        $rider = $this->createUser('rider', 'rider.mobile@nexora.test', '09174440004');
        $courier = $this->createUser('courier', 'courier.mobile@nexora.test', '09175550005');

        // 1. Mobile Buyer login honors remember=false (12h) vs remember=true (30d)
        $noRem = $this->postJson('/api/mobile/v1/auth/buyer/login', [
            'email' => $buyer->email,
            'password' => 'OldPassword123!',
            'remember' => false,
        ])->assertOk()->assertJsonPath('data.remember', false);

        $rem = $this->postJson('/api/mobile/v1/auth/buyer/login', [
            'email' => $buyer->email,
            'password' => 'OldPassword123!',
            'remember' => true,
        ])->assertOk()->assertJsonPath('data.remember', true);

        $this->assertGreaterThan(1, $buyer->tokens()->count());

        // 2. Role scope isolation: buyer forgot-password rejects rider email
        $this->postJson('/api/mobile/v1/auth/forgot-password', [
            'identifier' => $rider->email,
            'scope' => 'buyer',
        ])->assertStatus(422);

        // 3. Rider requests reset via mobile number, verifies OTP, resets password & revokes old tokens
        $riderToken = $rider->createToken('rider-app')->plainTextToken;

        $this->postJson('/api/mobile/v1/auth/forgot-password', [
            'identifier' => $rider->contact_number,
            'scope' => 'rider',
        ])->assertOk()->assertJsonPath('data.email', $rider->email);

        $riderOtp = null;
        Notification::assertSentTo($rider, PasswordResetOtpNotification::class, function ($n) use (&$riderOtp) {
            $riderOtp = $n->otp;

            return true;
        });

        // 60s resend cooldown enforced
        $this->postJson('/api/mobile/v1/auth/forgot-password', [
            'identifier' => $rider->email,
            'scope' => 'rider',
        ])->assertStatus(422);

        $this->postJson('/api/mobile/v1/auth/verify-reset-otp', [
            'identifier' => $rider->contact_number,
            'otp' => $riderOtp,
            'scope' => 'rider',
        ])->assertOk()->assertJsonPath('data.verified', true);

        $this->postJson('/api/mobile/v1/auth/reset-password', [
            'identifier' => $rider->email,
            'otp' => $riderOtp,
            'password' => 'RiderNewPass789!',
            'password_confirmation' => 'RiderNewPass789!',
            'scope' => 'rider',
        ])->assertOk();

        // Previous token invalidated
        $this->assertSame(0, $rider->tokens()->count());
        $this->assertNotNull($riderToken);

        // Login succeeds with new password + remember=true
        $this->postJson('/api/mobile/v1/auth/rider/login', [
            'email' => $rider->email,
            'password' => 'RiderNewPass789!',
            'remember' => true,
        ])->assertOk()->assertJsonPath('data.remember', true);

        // 4. Transfer courier also allowed under scope=rider
        $this->postJson('/api/mobile/v1/auth/forgot-password', [
            'identifier' => $courier->email,
            'scope' => 'rider',
        ])->assertOk();
        $this->assertDatabaseHas('password_reset_tokens', ['email' => strtolower($courier->email)]);
    }
}
