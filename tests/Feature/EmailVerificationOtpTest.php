<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\EmailVerificationOtpNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmailVerificationOtpTest extends TestCase
{
    use DatabaseTransactions;

    private function createUnverifiedUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'first_name' => 'Otp',
            'last_name' => 'Tester',
            'sex' => 'female',
            'birthday' => '1998-05-15',
            'age' => 28,
            'email' => 'otp_user_' . uniqid() . '@example.com',
            'contact_number' => '0917' . substr(str_pad((string) mt_rand(1000000, 9999999), 7, '0', STR_PAD_LEFT), 0, 7),
            'password' => Hash::make('StrongP@ss123!'),
            'role' => 'buyer',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => null,
            'email_verification_otp' => null,
            'email_verification_otp_expires_at' => null,
        ], $overrides));
    }

    public function test_unverified_user_accessing_verify_screen_gets_otp_generated_and_sent(): void
    {
        Notification::fake();

        $user = $this->createUnverifiedUser();

        $response = $this->actingAs($user)->get('/email/verify');

        $response->assertStatus(200);
        $response->assertSee('Enter Verification Code');
        $response->assertSee('Code expires in 15 minutes');

        $user->refresh();
        $this->assertNotNull($user->email_verification_otp);
        $this->assertEquals(6, strlen($user->email_verification_otp));
        $this->assertNotNull($user->email_verification_otp_expires_at);

        Notification::assertSentTo($user, EmailVerificationOtpNotification::class, function ($notification) use ($user) {
            return $notification->otp === $user->email_verification_otp;
        });
    }

    public function test_submitting_valid_otp_verifies_email(): void
    {
        $user = $this->createUnverifiedUser();
        $otp = $user->generateEmailVerificationOtp();

        $response = $this->actingAs($user)->post('/email/verify', [
            'otp' => $otp,
        ]);

        $user->refresh();
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertNull($user->email_verification_otp);
        $this->assertNull($user->email_verification_otp_expires_at);

        $response->assertRedirect($user->homeRoute());
    }

    public function test_submitting_invalid_otp_is_rejected(): void
    {
        $user = $this->createUnverifiedUser();
        $user->generateEmailVerificationOtp();

        $response = $this->actingAs($user)->post('/email/verify', [
            'otp' => '000000',
        ]);

        $response->assertSessionHasErrors(['otp']);
        $user->refresh();
        $this->assertFalse($user->hasVerifiedEmail());
    }

    public function test_submitting_expired_otp_is_rejected(): void
    {
        $user = $this->createUnverifiedUser([
            'email_verification_otp' => '123456',
            'email_verification_otp_expires_at' => now()->subMinutes(1),
        ]);

        $response = $this->actingAs($user)->post('/email/verify', [
            'otp' => '123456',
        ]);

        $response->assertSessionHasErrors(['otp']);
        $user->refresh();
        $this->assertFalse($user->hasVerifiedEmail());
    }

    public function test_submitting_non_6_digit_otp_is_rejected(): void
    {
        $user = $this->createUnverifiedUser();
        $user->generateEmailVerificationOtp();

        // 5 digits
        $this->actingAs($user)->post('/email/verify', ['otp' => '12345'])
            ->assertSessionHasErrors(['otp']);

        // non-numeric
        $this->actingAs($user)->post('/email/verify', ['otp' => 'abcdef'])
            ->assertSessionHasErrors(['otp']);
    }

    public function test_resending_otp_generates_fresh_code_and_resets_timer(): void
    {
        Notification::fake();

        $user = $this->createUnverifiedUser([
            'email_verification_otp' => '111111',
            'email_verification_otp_expires_at' => now()->addMinutes(2),
        ]);

        $response = $this->actingAs($user)->post('/email/verification-notification');

        $response->assertSessionHas('status', 'otp-sent');
        $user->refresh();
        $this->assertNotNull($user->email_verification_otp);
        $this->assertTrue($user->email_verification_otp_expires_at->gt(now()->addMinutes(14)));

        Notification::assertSentTo($user, EmailVerificationOtpNotification::class);
    }
}
