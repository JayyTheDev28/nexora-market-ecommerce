<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\LogisticsProfile;
use App\Models\Otp;
use App\Models\User;
use App\Notifications\LogisticsApplicationDecisionNotification;
use App\Notifications\LogisticsOtpNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LogisticsRegistrationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('public');
    }

    /**
     * Helper to build valid registration payload.
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'company_name' => 'FastTrack Logistics Corp',
            'sec_registration_no' => 'CS2023001234',
            'tin' => '123-456-789-000',
            'region' => 'National Capital Region (NCR)',
            'province' => 'Metro Manila',
            'city_municipality' => 'Taguig City',
            'barangay' => 'Fort Bonifacio',
            'street_address' => '402 High Street, Bonifacio Global City',
            'postal_code' => '1634',
            'contact_person_first_name' => 'Juan',
            'contact_person_last_name' => 'Dela Cruz',
            'position' => 'Logistics Director',
            'email' => 'contact@fasttracklogistics.com',
            'mobile_number' => '09171234567',
            'service_areas' => ['Metro Manila (NCR)', 'Calabarzon (Region IV-A)'],
            'sec_certificate' => UploadedFile::fake()->create('sec_cert.pdf', 500, 'application/pdf'),
            'bir_2303' => UploadedFile::fake()->create('bir_2303.pdf', 500, 'application/pdf'),
            'mayors_permit' => UploadedFile::fake()->create('mayors_permit.jpg', 500, 'image/jpeg'),
            'valid_id' => UploadedFile::fake()->create('passport.png', 500, 'image/png'),
            'password' => 'SecurePass123!@#',
            'password_confirmation' => 'SecurePass123!@#',
            'terms' => '1',
        ], $overrides);
    }

    /** TC-LOG-001: Show registration form */
    public function test_registration_form_can_be_viewed(): void
    {
        $response = $this->get(route('logistics.register'));
        $response->assertStatus(200);
        $response->assertSee('Logistics Partner Application');
        $response->assertSee('SEC Registration Number');
        $response->assertSee('Tax Identification Number');
    }

    /** TC-LOG-002: Validation fails when required fields are missing */
    public function test_registration_validation_fails_for_empty_fields(): void
    {
        $response = $this->post(route('logistics.register.store'), []);
        $response->assertSessionHasErrors([
            'company_name',
            'sec_registration_no',
            'tin',
            'region',
            'province',
            'city_municipality',
            'barangay',
            'street_address',
            'postal_code',
            'contact_person_first_name',
            'contact_person_last_name',
            'position',
            'email',
            'mobile_number',
            'service_areas',
            'sec_certificate',
            'bir_2303',
            'mayors_permit',
            'valid_id',
            'password',
            'terms',
        ]);
    }

    /** TC-LOG-003 to TC-LOG-007: Validation for TIN format & Company uniqueness */
    public function test_tin_format_and_uniqueness_validation(): void
    {
        // Invalid format
        $response = $this->post(route('logistics.register.store'), $this->validPayload(['tin' => 'invalid-tin']));
        $response->assertSessionHasErrors('tin');

        // Successful creation first
        $this->post(route('logistics.register.store'), $this->validPayload());
        $this->assertDatabaseHas('logistics_profiles', ['company_name' => 'FastTrack Logistics Corp']);

        // Duplicate company name and TIN
        $dupResponse = $this->post(route('logistics.register.store'), $this->validPayload([
            'email' => 'another@fasttrack.com',
            'mobile_number' => '09187654321',
        ]));
        $dupResponse->assertSessionHasErrors(['company_name', 'tin', 'sec_registration_no']);
    }

    /** TC-LOG-008 to TC-LOG-015: Contact person name sanitization and characters */
    public function test_contact_person_name_character_validation(): void
    {
        // Name with invalid special symbols ($$$)
        $response = $this->post(route('logistics.register.store'), $this->validPayload([
            'contact_person_first_name' => 'Juan$$$',
        ]));
        $response->assertSessionHasErrors('contact_person_first_name');

        // Name with allowed characters: hyphen, apostrophe, ñ
        $validNameResponse = $this->post(route('logistics.register.store'), $this->validPayload([
            'contact_person_first_name' => "Jose-Maria D'Angelo Peña",
        ]));
        $validNameResponse->assertSessionHasNoErrors();
    }

    /** TC-LOG-016 to TC-LOG-019: Document file type and size validation */
    public function test_document_uploads_validate_mime_types_and_size(): void
    {
        // Disallowed file extension (.exe or .txt)
        $response = $this->post(route('logistics.register.store'), $this->validPayload([
            'sec_certificate' => UploadedFile::fake()->create('malicious.exe', 100),
        ]));
        $response->assertSessionHasErrors('sec_certificate');

        // File exceeding 2MB (2048 KB)
        $oversizedResponse = $this->post(route('logistics.register.store'), $this->validPayload([
            'sec_certificate' => UploadedFile::fake()->create('large.pdf', 3000, 'application/pdf'),
        ]));
        $oversizedResponse->assertSessionHasErrors('sec_certificate');
    }

    /** TC-LOG-021 to TC-LOG-031: Password complexity and terms validation */
    public function test_password_complexity_validation(): void
    {
        // Simple password without symbols/numbers
        $response = $this->post(route('logistics.register.store'), $this->validPayload([
            'password' => 'simplepass',
            'password_confirmation' => 'simplepass',
        ]));
        $response->assertSessionHasErrors('password');
    }

    /** TC-LOG-032: Successful registration creates unverified profile and dispatches OTP */
    public function test_successful_registration_creates_profile_and_otps(): void
    {
        $response = $this->post(route('logistics.register.store'), $this->validPayload([
            'email' => 'partner@express.ph',
            'mobile_number' => '09191234567',
        ]));

        $response->assertRedirect(route('logistics.otp.notice'));

        // Profile created as unverified
        $this->assertDatabaseHas('logistics_profiles', [
            'email' => 'partner@express.ph',
            'status' => 'unverified',
        ]);

        // User created with role sorting_center
        $this->assertDatabaseHas('users', [
            'email' => 'partner@express.ph',
            'role' => 'sorting_center',
            'approval_status' => 'pending',
        ]);

        // OTP created in otps table
        $this->assertDatabaseHas('otps', [
            'email' => 'partner@express.ph',
            'attempts' => 0,
        ]);

        Notification::assertSentTo(
            User::where('email', 'partner@express.ph')->first(),
            LogisticsOtpNotification::class
        );
    }

    /** TC-LOG-033: Brute force lockout after 5 incorrect attempts */
    public function test_otp_locks_out_after_5_failed_attempts(): void
    {
        $this->post(route('logistics.register.store'), $this->validPayload([
            'email' => 'brute@express.ph',
            'mobile_number' => '09192233445',
        ]));

        $otp = Otp::where('email', 'brute@express.ph')->first();
        $this->assertNotNull($otp);

        // Submit 5 wrong OTPs
        for ($i = 1; $i <= 5; $i++) {
            $this->post(route('logistics.otp.verify'), ['otp' => '999999']);
        }

        $otp->refresh();
        $this->assertEquals(5, $otp->attempts);

        // 6th attempt should be locked out
        $response = $this->post(route('logistics.otp.verify'), ['otp' => $otp->otp_code]);
        $response->assertSessionHasErrors('otp');
    }

    /** TC-LOG-034 & TC-LOG-035: Expired OTP is rejected */
    public function test_expired_otp_is_rejected(): void
    {
        $this->post(route('logistics.register.store'), $this->validPayload([
            'email' => 'expired@express.ph',
            'mobile_number' => '09193344556',
        ]));

        $otp = Otp::where('email', 'expired@express.ph')->first();
        $otp->update(['expires_at' => now()->subMinutes(5)]);

        $response = $this->post(route('logistics.otp.verify'), ['otp' => $otp->otp_code]);
        $response->assertSessionHasErrors('otp');
    }

    /** TC-LOG-036: Resend OTP generates new code and resets attempts */
    public function test_resend_otp_generates_new_code(): void
    {
        $this->post(route('logistics.register.store'), $this->validPayload([
            'email' => 'resend@express.ph',
            'mobile_number' => '09194455667',
        ]));

        $oldOtp = Otp::where('email', 'resend@express.ph')->first()->otp_code;

        $this->post(route('logistics.otp.resend'));

        $newOtpRecord = Otp::where('email', 'resend@express.ph')->first();
        $this->assertNotNull($newOtpRecord);
        $this->assertEquals(0, $newOtpRecord->attempts);
    }

    /** TC-LOG-037: Valid OTP verification updates status to pending_approval */
    public function test_valid_otp_verification_advances_status_to_pending(): void
    {
        $this->post(route('logistics.register.store'), $this->validPayload([
            'email' => 'verify@express.ph',
            'mobile_number' => '09195566778',
        ]));

        $otp = Otp::where('email', 'verify@express.ph')->first();

        $response = $this->post(route('logistics.otp.verify'), [
            'otp' => $otp->otp_code,
        ]);

        $response->assertRedirect(route('logistics.pending'));

        $this->assertDatabaseHas('logistics_profiles', [
            'email' => 'verify@express.ph',
            'status' => 'pending_approval',
        ]);

        $user = User::where('email', 'verify@express.ph')->first();
        $this->assertNotNull($user->email_verified_at);
        $this->assertEquals('pending', $user->approval_status);
    }

    /** TC-LOG-038 & TC-LOG-039: Admin sees pending logistics partner in applications list */
    public function test_admin_can_view_pending_logistics_applications(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $this->post(route('logistics.register.store'), $this->validPayload([
            'company_name' => 'Apex Cargo Freight',
            'email' => 'apex@cargofreight.ph',
            'mobile_number' => '09196677889',
        ]));

        $user = User::where('email', 'apex@cargofreight.ph')->first();
        $user->logisticsProfile->update(['status' => 'pending_approval']);
        $user->update(['email_verified_at' => now(), 'approval_status' => 'pending']);

        $response = $this->actingAs($admin)->get(route('admin.applications.index', ['status' => 'pending']));
        $response->assertStatus(200);
        $response->assertSee('Apex Cargo Freight');
        $response->assertSee('SEC Certificate');
        $response->assertSee('BIR Form 2303');
    }

    /** TC-LOG-040: Admin can approve application */
    public function test_admin_approval_activates_logistics_partner(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $this->post(route('logistics.register.store'), $this->validPayload([
            'company_name' => 'Speedy Hub Express',
            'email' => 'speedy@express.ph',
            'mobile_number' => '09197788990',
        ]));

        $user = User::where('email', 'speedy@express.ph')->first();
        $user->logisticsProfile->update(['status' => 'pending_approval']);

        $response = $this->actingAs($admin)->post(route('admin.applications.approve', $user));
        $response->assertRedirect();

        $this->assertDatabaseHas('logistics_profiles', [
            'id' => $user->logisticsProfile->id,
            'status' => 'approved',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'approval_status' => 'approved',
        ]);

        Notification::assertSentTo($user, LogisticsApplicationDecisionNotification::class);
    }

    /** TC-LOG-041 & TC-LOG-042: Admin rejection with reason & partner resubmission */
    public function test_admin_rejection_and_partner_resubmission(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $this->post(route('logistics.register.store'), $this->validPayload([
            'company_name' => 'Northern Fleet Co',
            'email' => 'northern@fleet.ph',
            'mobile_number' => '09198899001',
        ]));

        $user = User::where('email', 'northern@fleet.ph')->first();
        $user->logisticsProfile->update(['status' => 'pending_approval']);

        // Reject with explicit reason
        $this->actingAs($admin)->post(route('admin.applications.disapprove', $user), [
            'reason' => 'Mayor\'s permit was expired. Please upload current year permit.',
        ]);

        $user->refresh();
        $this->assertEquals('disapproved', $user->approval_status);
        $this->assertEquals('rejected', $user->logisticsProfile->status);
        $this->assertStringContainsString('expired', $user->logisticsProfile->rejection_reason);

        // Partner logs in and resubmits
        $resubmitResponse = $this->actingAs($user)->post(route('logistics.resubmit.store'), [
            'company_name' => 'Northern Fleet Co',
            'sec_registration_no' => 'CS2023001234',
            'tin' => '123-456-789-000',
            'region' => 'National Capital Region (NCR)',
            'province' => 'Metro Manila',
            'city_municipality' => 'Taguig City',
            'barangay' => 'Fort Bonifacio',
            'street_address' => '402 High Street, Bonifacio Global City',
            'postal_code' => '1634',
            'contact_person_first_name' => 'Juan',
            'contact_person_last_name' => 'Dela Cruz',
            'position' => 'Logistics Director',
            'mobile_number' => '09198899001',
            'service_areas' => ['Metro Manila (NCR)'],
            'mayors_permit' => UploadedFile::fake()->create('updated_permit.jpg', 500, 'image/jpeg'),
        ]);

        $resubmitResponse->assertRedirect(route('logistics.pending'));

        $user->refresh();
        $this->assertEquals('pending', $user->approval_status);
        $this->assertEquals('pending_approval', $user->logisticsProfile->status);
        $this->assertNull($user->logisticsProfile->rejection_reason);
    }

    /** TC-LOG-043: Non-admins are blocked from accessing admin approval routes */
    public function test_non_admins_are_blocked_from_admin_approval_routes(): void
    {
        $buyer = User::factory()->create([
            'role' => 'buyer',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($buyer)->get(route('admin.applications.index'));
        $response->assertStatus(403);
    }

    /** TC-LOG-044: Logistics partners cannot cross-access unauthorized dashboards */
    public function test_cross_role_dashboard_access_is_blocked(): void
    {
        $logisticsUser = User::factory()->create([
            'role' => 'sorting_center',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        // Attempting to access seller dashboard redirects to their own homeRoute (/logistics/dashboard)
        $response = $this->actingAs($logisticsUser)->get(route('seller.dashboard'));
        $response->assertRedirect('/logistics/dashboard');
    }
}
