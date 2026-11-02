<?php

namespace App\Http\Controllers\Api\Mobile\Buyer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Mobile\BuyerPasswordUpdateRequest;
use App\Http\Requests\Api\Mobile\BuyerProfileUpdateRequest;
use App\Http\Resources\Api\Mobile\AddressResource;
use App\Http\Resources\Api\Mobile\UserResource;
use App\Notifications\AccountChangeOtpNotification;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    /**
     * Buyer profile & account summary
     * GET /api/mobile/v1/buyer/account
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load(['addresses' => fn ($q) => $q->orderByDesc('is_default')->latest()]);

        return response()->json([
            'data' => [
                'user' => new UserResource($user),
                'addresses' => AddressResource::collection($user->addresses),
                'default_address' => $user->addresses->firstWhere('is_default', true)
                    ? new AddressResource($user->addresses->firstWhere('is_default', true))
                    : null,
            ],
            'message' => 'Account details retrieved successfully.',
        ]);
    }

    /**
     * B1: Update profile (names, avatar, birthday)
     * PUT /api/mobile/v1/buyer/account/profile
     */
    public function updateProfile(BuyerProfileUpdateRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $validated['age'] = Carbon::parse($validated['birthday'])->age;

        if ($request->hasFile('avatar')) {
            if ($user->avatar_path && Storage::disk('public')->exists($user->avatar_path)) {
                Storage::disk('public')->delete($user->avatar_path);
            }
            $validated['avatar_path'] = $request->file('avatar')->store('avatars', 'public');
        }

        unset($validated['avatar']);

        $user->update($validated);

        return response()->json([
            'data' => [
                'user' => new UserResource($user->fresh()),
            ],
            'message' => 'Your profile has been updated.',
        ]);
    }

    /**
     * B1: Update password
     * PUT /api/mobile/v1/buyer/account/password
     */
    public function updatePassword(BuyerPasswordUpdateRequest $request): JsonResponse
    {
        $request->user()->update([
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'data' => null,
            'message' => 'Your password has been changed successfully.',
        ]);
    }

    /**
     * B1: Request Email Change via 6-pin OTP
     * POST /api/mobile/v1/buyer/account/email/request-otp
     */
    public function requestEmailChange(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'new_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        if (strtolower($validated['new_email']) === strtolower($user->email)) {
            return response()->json([
                'message' => 'The new email is the same as your current email.',
                'errors' => ['new_email' => ['The new email is the same as your current email.']],
            ], 422);
        }

        $otp = $user->generatePendingEmailOtp($validated['new_email']);

        try {
            $user->notify(new AccountChangeOtpNotification($otp, 'email'));
        } catch (\Throwable $e) {
            Log::error("Failed to send email OTP: " . $e->getMessage());
        }

        return response()->json([
            'data' => [
                'new_email' => $validated['new_email'],
            ],
            'message' => "A 6-digit verification code has been sent to {$validated['new_email']}. Please enter the OTP to confirm.",
        ]);
    }

    /**
     * B1: Verify Email Change OTP
     * POST /api/mobile/v1/buyer/account/email/verify-otp
     */
    public function verifyEmailChange(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'otp' => ['required', 'string', 'size:6'],
        ]);

        if (! $user->pending_email || ! $user->pending_email_otp) {
            return response()->json([
                'message' => 'No pending email change request found. Please request a new code.',
                'errors' => ['otp' => ['No pending email change request found.']],
            ], 422);
        }

        if ($user->pending_email_otp_expires_at && $user->pending_email_otp_expires_at->isPast()) {
            return response()->json([
                'message' => 'The verification code has expired. Please request a new code.',
                'errors' => ['otp' => ['The verification code has expired.']],
            ], 422);
        }

        if ($user->pending_email_otp !== $validated['otp']) {
            return response()->json([
                'message' => 'The verification code you entered is invalid.',
                'errors' => ['otp' => ['The verification code you entered is invalid.']],
            ], 422);
        }

        $newEmail = $user->pending_email;
        $user->forceFill([
            'email' => $newEmail,
            'pending_email' => null,
            'pending_email_otp' => null,
            'pending_email_otp_expires_at' => null,
        ])->save();

        return response()->json([
            'data' => [
                'user' => new UserResource($user->fresh()),
            ],
            'message' => "Your email address has been successfully changed to {$newEmail}.",
        ]);
    }

    /**
     * B1: Request Phone Change via 6-pin OTP
     * POST /api/mobile/v1/buyer/account/phone/request-otp
     */
    public function requestPhoneChange(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'new_phone' => ['required', 'string', 'regex:/^(09|\+639)\d{9}$/', Rule::unique('users', 'contact_number')->ignore($user->id)],
        ], [
            'new_phone.regex' => 'Mobile number must be a valid Philippine number (e.g. 09123456789 or +639123456789).',
            'new_phone.unique' => 'This mobile number is already registered to another account.',
        ]);

        if ($validated['new_phone'] === $user->contact_number) {
            return response()->json([
                'message' => 'The new phone number is the same as your current phone number.',
                'errors' => ['new_phone' => ['The new phone number is the same as your current phone number.']],
            ], 422);
        }

        $otp = $user->generatePendingPhoneOtp($validated['new_phone']);

        try {
            $user->notify(new AccountChangeOtpNotification($otp, 'phone'));
        } catch (\Throwable $e) {
            Log::error("Failed to send phone OTP: " . $e->getMessage());
        }

        return response()->json([
            'data' => [
                'new_phone' => $validated['new_phone'],
            ],
            'message' => "A 6-digit verification code has been sent to {$validated['new_phone']}.",
        ]);
    }

    /**
     * B1: Verify Phone Change OTP
     * POST /api/mobile/v1/buyer/account/phone/verify-otp
     */
    public function verifyPhoneChange(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'otp' => ['required', 'string', 'size:6'],
        ]);

        if (! $user->pending_phone || ! $user->pending_phone_otp) {
            return response()->json([
                'message' => 'No pending phone change request found. Please request a new code.',
                'errors' => ['otp' => ['No pending phone change request found.']],
            ], 422);
        }

        if ($user->pending_phone_otp_expires_at && $user->pending_phone_otp_expires_at->isPast()) {
            return response()->json([
                'message' => 'The verification code has expired. Please request a new code.',
                'errors' => ['otp' => ['The verification code has expired.']],
            ], 422);
        }

        if ($user->pending_phone_otp !== $validated['otp']) {
            return response()->json([
                'message' => 'The verification code you entered is invalid.',
                'errors' => ['otp' => ['The verification code you entered is invalid.']],
            ], 422);
        }

        $newPhone = $user->pending_phone;
        $user->forceFill([
            'contact_number' => $newPhone,
            'pending_phone' => null,
            'pending_phone_otp' => null,
            'pending_phone_otp_expires_at' => null,
        ])->save();

        return response()->json([
            'data' => [
                'user' => new UserResource($user->fresh()),
            ],
            'message' => "Your phone number has been successfully changed to {$newPhone}.",
        ]);
    }
}

