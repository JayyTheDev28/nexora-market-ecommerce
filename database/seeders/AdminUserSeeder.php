<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Seeds one admin account for local development/testing.
     * CHANGE THIS PASSWORD before deploying anywhere real.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@nexora.test'],
            [
                'first_name' => 'Nexora',
                'last_name' => 'Admin',
                'sex' => 'other',
                'birthday' => '1990-01-01',
                'age' => \Carbon\Carbon::parse('1990-01-01')->age,
                'contact_number' => '09000000000',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'approval_status' => 'approved',
                'email_verified_at' => now(),
            ]
        );
    }
}
