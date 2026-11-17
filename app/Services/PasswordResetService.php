<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\PasswordResetOtpNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordResetService
{
    public const EXPIRY_MINUTES = 15;
    public const RESEND_COOLDOWN_SECONDS = 60;

    public function findUserByIdentifier(string $identifier, ?string $scope = null): User
    {
        $clean = trim($identifier);
        $user = User::whereRaw('LOWER(email) = ?', [strtolower($clean)])
            ->orWhere('contact_number', $clean)
            ->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => ['No Nexora account was found matching that email address or mobile number.'],
            ]);
        }

        if ($scope === 'buyer' && ! $user->isBuyer()) {
            throw ValidationException::withMessages([
                'email' => ['That account is not registered as a Buyer account.'],
            ]);
        }

        if ($scope === 'rider' && ! $user->isMobileDeliveryPersonnel()) {
            throw ValidationException::withMessages([
                'email' => ['That account is not registered as a Delivery Rider or Transfer Courier account.'],
            ]);
        }

        if ($user->isDisapproved()) {
            throw ValidationException::withMessages([
                'email' => ['This account application was not approved and cannot reset its password.'],
            ]);
        }

        if ($user->account_status === 'pending_activation') {
            throw ValidationException::withMessages([
                'email' => ['Account is pending activation. Please check your activation link email first.'],
            ]);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages([
                'email' => ['This account is suspended or deactivated. Please contact support.'],
            ]);
        }

        return $user;
    }

    public function sendResetOtp(string $identifier, ?string $scope = null): array
    {
        $user = $this->findUserByIdentifier($identifier, $scope);
        $emailKey = strtolower($user->email);

        $existing = DB::table('password_reset_tokens')->where('email', $emailKey)->first();
        if ($existing && $existing->created_at) {
            $secondsSince = Carbon::parse($existing->created_at)->diffInSeconds(now(), false);
            if ($secondsSince >= 0 && $secondsSince < self::RESEND_COOLDOWN_SECONDS) {
                $wait = self::RESEND_COOLDOWN_SECONDS - $secondsSince;
                throw ValidationException::withMessages([
                    'email' => ["Please wait {$wait} second(s) before requesting another reset code."],
                ]);
            }
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $emailKey],
            [
                'token' => Hash::make($otp),
                'created_at' => now(),
            ]
        );

        try {
            $user->notify(new PasswordResetOtpNotification($otp));
        } catch (\Throwable $e) {
            Log::warning('Password reset OTP mail failed: ' . $e->getMessage());
        }

        AuditLog::log(
            'password_reset_otp_requested',
            $user,
            null,
            ['email' => $user->email, 'scope' => $scope ?? 'web'],
            'User requested password reset OTP',
            $user->id
        );

        return [
            'user' => $user,
            'email' => $user->email,
            'expires_in_minutes' => self::EXPIRY_MINUTES,
        ];
    }

    public function verifyResetOtp(string $identifier, string $otp, ?string $scope = null): User
    {
        $user = $this->findUserByIdentifier($identifier, $scope);
        $emailKey = strtolower($user->email);

        $record = DB::table('password_reset_tokens')->where('email', $emailKey)->first();
        if (! $record) {
            throw ValidationException::withMessages([
                'otp' => ['No active password reset code was found. Please request a new code.'],
            ]);
        }

        if (! $record->created_at || Carbon::parse($record->created_at)->addMinutes(self::EXPIRY_MINUTES)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $emailKey)->delete();
            throw ValidationException::withMessages([
                'otp' => ['This password reset code has expired. Please request a new one.'],
            ]);
        }

        if (! Hash::check(trim($otp), $record->token)) {
            throw ValidationException::withMessages([
                'otp' => ['Invalid 6-digit password reset code.'],
            ]);
        }

        return $user;
    }

    public function resetPassword(string $identifier, string $otp, string $newPassword, ?string $scope = null): User
    {
        return DB::transaction(function () use ($identifier, $otp, $newPassword, $scope) {
            $user = $this->verifyResetOtp($identifier, $otp, $scope);
            $emailKey = strtolower($user->email);

            $user->forceFill([
                'password' => Hash::make($newPassword),
                'remember_token' => Str::random(60),
            ])->save();

            DB::table('password_reset_tokens')->where('email', $emailKey)->delete();

            // Invalidate active mobile Sanctum tokens and active single-session cache on password change
            $user->tokens()->delete();
            Cache::forget("user_active_session_{$user->id}");

            AuditLog::log(
                'password_reset_completed',
                $user,
                null,
                ['email' => $user->email, 'scope' => $scope ?? 'web'],
                'User completed password reset via OTP',
                $user->id
            );

            return $user;
        });
    }
}
