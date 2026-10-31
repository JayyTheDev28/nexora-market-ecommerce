<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Notifications\AccountChangeOtpNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user()->load(['addresses' => fn ($q) => $q->orderByDesc('is_default')->latest()]);

        return view('buyer.account.index', [
            'user' => $user,
            'addresses' => $user->addresses,
            'defaultAddress' => $user->addresses->firstWhere('is_default', true),
        ]);
    }

    /**
     * B1: Profile updates (first/last name, photo)
     */
    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'min:2', 'max:50', 'regex:/^[a-zA-Z\s\-]+$/'],
            'last_name' => ['required', 'string', 'min:2', 'max:50', 'regex:/^[a-zA-Z\s\-]+$/'],
            'middle_initial' => ['nullable', 'string', 'max:10', 'regex:/^[a-zA-Z]+$/'],
            'sex' => ['required', 'in:male,female,other'],
            'birthday' => ['required', 'date', 'before:today'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ], [
            'first_name.regex' => 'First name must contain only letters, spaces, and hyphens.',
            'last_name.regex' => 'Last name must contain only letters, spaces, and hyphens.',
            'middle_initial.regex' => 'Middle initial must contain only letters.',
        ]);

        $validated['age'] = \Carbon\Carbon::parse($validated['birthday'])->age;

        if ($request->hasFile('avatar')) {
            if ($user->avatar_path && Storage::disk('public')->exists($user->avatar_path)) {
                Storage::disk('public')->delete($user->avatar_path);
            }
            $validated['avatar_path'] = $request->file('avatar')->store('avatars', 'public');
        }

        unset($validated['avatar']);

        $user->update($validated);

        return back()->with('status', 'Your profile has been updated.');
    }

    /**
     * B1: Password changes (requires old password, strict complex password validation)
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => [
                'required',
                'confirmed',
                Password::min(8)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],
        ], [
            'password.min' => 'Password must be at least 8 characters.',
            'password.letters' => 'Password must include at least one letter.',
            'password.mixed' => 'Password must include both uppercase and lowercase letters.',
            'password.numbers' => 'Password must include at least one number.',
            'password.symbols' => 'Password must include at least one special character symbol.',
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('status', 'Your password has been changed successfully.');
    }

    /**
     * B1: Request Email Change via 6-pin OTP
     */
    public function requestEmailChange(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'new_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        if (strtolower($validated['new_email']) === strtolower($user->email)) {
            return back()->withErrors(['new_email' => 'The new email is the same as your current email.']);
        }

        $otp = $user->generatePendingEmailOtp($validated['new_email']);

        try {
            $user->notify(new AccountChangeOtpNotification($otp, 'email'));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Failed to send email OTP: " . $e->getMessage());
        }

        return back()->with('status', "A 6-digit verification code has been sent to {$validated['new_email']}. Please enter the OTP to confirm.")
            ->with('email_otp_requested', true);
    }

    /**
     * B1: Verify Email Change OTP
     */
    public function verifyEmailChange(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'otp' => ['required', 'string', 'size:6'],
        ]);

        if (! $user->pending_email || ! $user->pending_email_otp) {
            return back()->withErrors(['otp' => 'No pending email change request found. Please request a new code.']);
        }

        if ($user->pending_email_otp_expires_at && $user->pending_email_otp_expires_at->isPast()) {
            return back()->withErrors(['otp' => 'The verification code has expired. Please request a new code.']);
        }

        if ($user->pending_email_otp !== $validated['otp']) {
            return back()->withErrors(['otp' => 'The verification code you entered is invalid.']);
        }

        $newEmail = $user->pending_email;
        $user->forceFill([
            'email' => $newEmail,
            'pending_email' => null,
            'pending_email_otp' => null,
            'pending_email_otp_expires_at' => null,
        ])->save();

        return back()->with('status', "Your email address has been successfully changed to {$newEmail}.");
    }

    /**
     * B1: Request Phone Change via 6-pin OTP
     */
    public function requestPhoneChange(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'new_phone' => ['required', 'string', 'regex:/^(09|\+639)\d{9}$/', Rule::unique('users', 'contact_number')->ignore($user->id)],
        ], [
            'new_phone.regex' => 'Mobile number must be a valid Philippine number (e.g. 09123456789 or +639123456789).',
            'new_phone.unique' => 'This mobile number is already registered to another account.',
        ]);

        if ($validated['new_phone'] === $user->contact_number) {
            return back()->withErrors(['new_phone' => 'The new phone number is the same as your current phone number.']);
        }

        $otp = $user->generatePendingPhoneOtp($validated['new_phone']);

        try {
            $user->notify(new AccountChangeOtpNotification($otp, 'phone'));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Failed to send phone OTP: " . $e->getMessage());
        }

        return back()->with('status', "A 6-digit verification code has been generated for {$validated['new_phone']}. (Demo OTP: {$otp})")
            ->with('phone_otp_requested', true);
    }

    /**
     * B1: Verify Phone Change OTP
     */
    public function verifyPhoneChange(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'otp' => ['required', 'string', 'size:6'],
        ]);

        if (! $user->pending_phone || ! $user->pending_phone_otp) {
            return back()->withErrors(['otp' => 'No pending phone change request found. Please request a new code.']);
        }

        if ($user->pending_phone_otp_expires_at && $user->pending_phone_otp_expires_at->isPast()) {
            return back()->withErrors(['otp' => 'The verification code has expired. Please request a new code.']);
        }

        if ($user->pending_phone_otp !== $validated['otp']) {
            return back()->withErrors(['otp' => 'The verification code you entered is invalid.']);
        }

        $newPhone = $user->pending_phone;
        $user->forceFill([
            'contact_number' => $newPhone,
            'pending_phone' => null,
            'pending_phone_otp' => null,
            'pending_phone_otp_expires_at' => null,
        ])->save();

        return back()->with('status', "Your phone number has been successfully changed to {$newPhone}.");
    }
}
