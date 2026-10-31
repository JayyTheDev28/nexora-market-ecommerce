<?php

namespace Tests\Feature;

use App\Models\SellerProfile;
use App\Models\SortingCenterProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RegistrationValidationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function validRegistrationData(array $overrides = []): array
    {
        $unique = uniqid();
        return array_merge([
            'role' => 'buyer',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'middle_initial' => 'C',
            'sex' => 'female',
            'birthday' => '1998-05-15',
            'email' => "maria_{$unique}@example.com",
            'contact_number' => '0917' . substr(str_pad((string) mt_rand(1000000, 9999999), 7, '0', STR_PAD_LEFT), 0, 7),
            'password' => 'StrongP@ss123!',
            'password_confirmation' => 'StrongP@ss123!',
            'province' => '133900000',
            'province_name' => 'NCR, Second District',
            'municipality' => '133900000',
            'municipality_name' => 'City of Mandaluyong',
            'barangay' => '133901001',
            'barangay_name' => 'Addition Hills',
            'street' => 'Shaw Blvd',
            'house_number' => '123',
            'valid_id' => UploadedFile::fake()->create('id.jpg', 500, 'image/jpeg'),
            'terms' => '1',
        ], $overrides);
    }

    public function test_privacy_and_terms_pages_are_accessible(): void
    {
        $responsePrivacy = $this->get('/privacy-policy');
        $responsePrivacy->assertStatus(200);
        $responsePrivacy->assertSee('Privacy Policy');
        $responsePrivacy->assertSee('Republic Act No. 10173');

        $responseTerms = $this->get('/terms-of-service');
        $responseTerms->assertStatus(200);
        $responseTerms->assertSee('Terms of Service');
        $responseTerms->assertSee('Republic Act No. 8792');

        $this->get('/privacy')->assertRedirect('/privacy-policy');
        $this->get('/terms')->assertRedirect('/terms-of-service');
    }

    public function test_registration_page_renders_multi_step_wizard(): void
    {
        $response = $this->get('/register');
        $response->assertStatus(200);
        $response->assertSee('Profile');
        $response->assertSee('Security');
        $response->assertSee('Address');
        $response->assertSee('Verify');
        $response->assertSee('registerForm(');
        $response->assertSee('Continue to Security');
    }

    public function test_valid_buyer_registration_succeeds(): void
    {
        $email = 'valid_test_' . uniqid() . '@example.com';
        $contact = '0917' . substr(str_pad((string) mt_rand(1000000, 9999999), 7, '0', STR_PAD_LEFT), 0, 7);

        $data = $this->validRegistrationData([
            'email' => $email,
            'contact_number' => $contact,
            'first_name' => 'Valida',
            'last_name' => 'Registor',
        ]);

        $response = $this->post(route('register.store'), $data);

        $response->assertRedirect(route('verification.notice'));
        $this->assertDatabaseHas('users', [
            'email' => $email,
            'first_name' => 'Valida',
            'last_name' => 'Registor',
            'contact_number' => $contact,
            'role' => 'buyer',
        ]);
    }

    public function test_names_reject_special_characters(): void
    {
        $data = $this->validRegistrationData([
            'first_name' => 'Maria@123',
            'last_name' => 'Santos#',
        ]);

        $response = $this->post(route('register.store'), $data);
        $response->assertSessionHasErrors(['first_name', 'last_name']);
    }

    public function test_names_reject_one_character_or_over_50_characters(): void
    {
        // 1-character name
        $dataMin = $this->validRegistrationData([
            'first_name' => 'M',
            'last_name' => 'S',
        ]);
        $responseMin = $this->post(route('register.store'), $dataMin);
        $responseMin->assertSessionHasErrors(['first_name', 'last_name']);

        // Over 50 characters name
        $dataMax = $this->validRegistrationData([
            'first_name' => str_repeat('A', 51),
            'last_name' => str_repeat('B', 51),
        ]);
        $responseMax = $this->post(route('register.store'), $dataMax);
        $responseMax->assertSessionHasErrors(['first_name', 'last_name']);
    }

    public function test_duplicate_user_name_is_rejected(): void
    {
        $firstName = 'DuplicatedFirst' . mt_rand(100, 999);
        $lastName = 'DuplicatedLast' . mt_rand(100, 999);

        User::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'sex' => 'male',
            'birthday' => '1990-01-01',
            'age' => 35,
            'email' => 'existing_' . uniqid() . '@example.com',
            'contact_number' => '0917' . substr(str_pad((string) mt_rand(1000000, 9999999), 7, '0', STR_PAD_LEFT), 0, 7),
            'password' => Hash::make('StrongP@ss123!'),
            'role' => 'buyer',
            'approval_status' => 'approved',
        ]);

        $data = $this->validRegistrationData([
            'first_name' => strtolower($firstName),
            'last_name' => strtolower($lastName),
        ]);

        $response = $this->post(route('register.store'), $data);
        $response->assertSessionHasErrors(['first_name']);
    }

    public function test_birthday_in_2026_or_later_is_rejected(): void
    {
        $data = $this->validRegistrationData([
            'birthday' => '2026-05-15',
        ]);

        $response = $this->post(route('register.store'), $data);
        $response->assertSessionHasErrors(['birthday']);
    }

    public function test_birthday_with_age_zero_is_rejected(): void
    {
        // A birthday very close to today (e.g., today is late 2026, or current date)
        $data = $this->validRegistrationData([
            'birthday' => now()->subMonths(3)->format('Y-m-d'),
        ]);

        $response = $this->post(route('register.store'), $data);
        $response->assertSessionHasErrors(['birthday']);
    }

    public function test_password_requires_uppercase_lowercase_number_and_symbol(): void
    {
        // Missing symbol
        $dataNoSymbol = $this->validRegistrationData([
            'password' => 'Password1234',
            'password_confirmation' => 'Password1234',
        ]);
        $this->post(route('register.store'), $dataNoSymbol)->assertSessionHasErrors(['password']);

        // Missing uppercase
        $dataNoUpper = $this->validRegistrationData([
            'password' => 'password1234!',
            'password_confirmation' => 'password1234!',
        ]);
        $this->post(route('register.store'), $dataNoUpper)->assertSessionHasErrors(['password']);

        // Missing lowercase
        $dataNoLower = $this->validRegistrationData([
            'password' => 'PASSWORD1234!',
            'password_confirmation' => 'PASSWORD1234!',
        ]);
        $this->post(route('register.store'), $dataNoLower)->assertSessionHasErrors(['password']);

        // Missing number
        $dataNoNumber = $this->validRegistrationData([
            'password' => 'Password!@#$',
            'password_confirmation' => 'Password!@#$',
        ]);
        $this->post(route('register.store'), $dataNoNumber)->assertSessionHasErrors(['password']);
    }

    public function test_contact_number_must_be_philippine_eleven_digits_with_no_special_chars(): void
    {
        // Special characters in contact number
        $dataSpecial = $this->validRegistrationData([
            'contact_number' => '0917-123-4567',
        ]);
        $this->post(route('register.store'), $dataSpecial)->assertSessionHasErrors(['contact_number']);

        // Less than 11 digits
        $dataShort = $this->validRegistrationData([
            'contact_number' => '091712345',
        ]);
        $this->post(route('register.store'), $dataShort)->assertSessionHasErrors(['contact_number']);

        // Non-Philippine prefix (does not start with 09)
        $dataInvalidPrefix = $this->validRegistrationData([
            'contact_number' => '08123456789',
        ]);
        $this->post(route('register.store'), $dataInvalidPrefix)->assertSessionHasErrors(['contact_number']);

        // Normalized from international +63 format
        $uniquePhone = '0917' . substr(str_pad((string) mt_rand(1000000, 9999999), 7, '0', STR_PAD_LEFT), 0, 7);
        $dataIntl = $this->validRegistrationData([
            'contact_number' => '+63' . substr($uniquePhone, 1),
            'first_name' => 'IntlUser',
            'last_name' => 'PhoneTest',
        ]);
        $response = $this->post(route('register.store'), $dataIntl);
        $response->assertSessionDoesntHaveErrors(['contact_number']);
        $this->assertDatabaseHas('users', [
            'contact_number' => $uniquePhone,
        ]);
    }

    public function test_contact_number_must_be_unique(): void
    {
        $contact = '0917' . substr(str_pad((string) mt_rand(1000000, 9999999), 7, '0', STR_PAD_LEFT), 0, 7);

        User::create([
            'first_name' => 'Existing',
            'last_name' => 'User',
            'sex' => 'male',
            'birthday' => '1992-02-02',
            'age' => 33,
            'email' => 'existing_phone_' . uniqid() . '@example.com',
            'contact_number' => $contact,
            'password' => Hash::make('StrongP@ss123!'),
            'role' => 'buyer',
            'approval_status' => 'approved',
        ]);

        $data = $this->validRegistrationData([
            'contact_number' => $contact,
        ]);

        $response = $this->post(route('register.store'), $data);
        $response->assertSessionHasErrors(['contact_number']);
    }

    public function test_seller_business_name_cannot_have_duplicates(): void
    {
        $bizName = 'Apex Tech Store ' . mt_rand(100, 999);

        $existingSellerUser = User::create([
            'first_name' => 'Seller',
            'last_name' => 'Owner',
            'sex' => 'female',
            'birthday' => '1990-03-03',
            'age' => 35,
            'email' => 'seller_' . uniqid() . '@example.com',
            'contact_number' => '0917' . substr(str_pad((string) mt_rand(1000000, 9999999), 7, '0', STR_PAD_LEFT), 0, 7),
            'password' => Hash::make('StrongP@ss123!'),
            'role' => 'seller',
            'approval_status' => 'approved',
        ]);

        SellerProfile::create([
            'user_id' => $existingSellerUser->id,
            'business_name' => $bizName,
            'category' => 'electronics',
            'business_permit_path' => 'permits/sample.pdf',
        ]);

        $data = $this->validRegistrationData([
            'role' => 'seller',
            'first_name' => 'NewSeller',
            'last_name' => 'OwnerTwo',
            'business_name' => strtolower($bizName), // case-insensitive duplicate
            'category' => 'electronics',
            'business_permit' => UploadedFile::fake()->create('permit.pdf', 500, 'application/pdf'),
        ]);

        $response = $this->post(route('register.store'), $data);
        $response->assertSessionHasErrors(['business_name']);
    }

    public function test_logistics_business_name_cannot_have_duplicates(): void
    {
        $hubName = 'Metro Manila Central Hub ' . mt_rand(100, 999);

        $existingLogisticsUser = User::create([
            'first_name' => 'Logistics',
            'last_name' => 'Manager',
            'sex' => 'male',
            'birthday' => '1988-04-04',
            'age' => 37,
            'email' => 'logistics_' . uniqid() . '@example.com',
            'contact_number' => '0917' . substr(str_pad((string) mt_rand(1000000, 9999999), 7, '0', STR_PAD_LEFT), 0, 7),
            'password' => Hash::make('StrongP@ss123!'),
            'role' => 'sorting_center',
            'approval_status' => 'approved',
        ]);

        SortingCenterProfile::create([
            'user_id' => $existingLogisticsUser->id,
            'business_name' => $hubName,
            'business_permit_path' => 'permits/sample.pdf',
        ]);

        $data = $this->validRegistrationData([
            'role' => 'sorting_center',
            'first_name' => 'NewHub',
            'last_name' => 'ManagerTwo',
            'business_name' => strtolower($hubName), // case-insensitive duplicate
            'business_permit' => UploadedFile::fake()->create('permit.pdf', 500, 'application/pdf'),
        ]);

        $response = $this->post(route('register.store'), $data);
        $response->assertSessionHasErrors(['business_name']);
    }
}
