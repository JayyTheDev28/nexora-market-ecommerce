<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SingleSessionEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_concurrent_session_is_terminated_with_notification(): void
    {
        $user = User::factory()->create([
            'role' => 'buyer',
            'approval_status' => 'approved',
            'account_status' => 'active',
        ]);

        // Device 1 session
        $device1SessionId = 'device_session_1';
        Cache::forever("user_active_session_{$user->id}", $device1SessionId);

        // Device 2 logs in -> cache becomes device_session_2
        $device2SessionId = 'device_session_2';
        Cache::forever("user_active_session_{$user->id}", $device2SessionId);

        // Device 1 makes a request with device_session_1
        $this->actingAs($user)
            ->withSession(['_token' => 'dummy'])
            ->withHeaders(['X-Test-Enforce-Single-Session' => '1'])
            ->get('/')
            ->assertRedirect(route('login'))
            ->assertSessionHas('warning', 'This account has been logged in on a different device. You have been logged out for security.');

        // User is logged out
        $this->assertGuest();
    }
}

