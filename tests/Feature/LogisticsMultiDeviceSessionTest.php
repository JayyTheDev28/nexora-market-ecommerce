<?php

namespace Tests\Feature;

use App\Models\LogisticsProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LogisticsMultiDeviceSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_logistics_account_remains_authenticated_when_active_session_cache_differs(): void
    {
        $logistics = User::create([
            'first_name' => 'Laguna',
            'last_name' => 'Logistics',
            'email' => 'logistics-multi@nexora.test',
            'contact_number' => '09171234567',
            'birthday' => '1990-01-01',
            'age' => 36,
            'sex' => 'other',
            'password' => Hash::make('Password123!@#'),
            'role' => 'logistics',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        LogisticsProfile::create([
            'user_id' => $logistics->id,
            'company_name' => 'Laguna Express Logistics',
            'sec_registration_no' => 'SEC-2026-MULTI',
            'tin' => '123-456-789-000',
            'region' => 'Region IV-A (CALABARZON)',
            'province' => 'Laguna',
            'city_municipality' => 'Santa Cruz',
            'barangay' => 'Poblacion I',
            'street_address' => 'Logistics Hwy',
            'postal_code' => '4009',
            'contact_person_first_name' => 'Laguna',
            'contact_person_last_name' => 'Ops',
            'position' => 'Manager',
            'email' => $logistics->email,
            'mobile_number' => $logistics->contact_number,
            'status' => 'approved',
        ]);

        // Simulate another device having a different cached active session ID
        Cache::forever("user_active_session_{$logistics->id}", 'other-device-session-id');

        $response = $this->actingAs($logistics)
            ->withHeader('X-Test-Enforce-Single-Session', '1')
            ->get(route('logistics.dashboard'));

        $response->assertOk();
        $this->assertAuthenticatedAs($logistics);
    }

    public function test_non_logistics_account_still_enforces_single_active_session(): void
    {
        $buyer = User::create([
            'first_name' => 'Test',
            'last_name' => 'Buyer',
            'email' => 'buyer-single@nexora.test',
            'contact_number' => '09179998888',
            'birthday' => '1995-01-01',
            'age' => 31,
            'sex' => 'male',
            'password' => Hash::make('Password123!@#'),
            'role' => 'buyer',
            'approval_status' => 'approved',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        Cache::forever("user_active_session_{$buyer->id}", 'other-device-session-id');

        $response = $this->actingAs($buyer)
            ->withHeader('X-Test-Enforce-Single-Session', '1')
            ->get('/');

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
