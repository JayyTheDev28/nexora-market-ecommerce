<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class VerifyEmailController extends Controller
{
    /**
     * Verify the 6-pin OTP entered by the user.
     */
    public function verifyOtp(Request $request): RedirectResponse
    {
        $request->validate([
            'otp' => ['required', 'string', 'size:6', 'regex:/^\d{6}$/'],
        ], [
            'otp.required' => 'Please enter your 6-digit verification code.',
            'otp.size' => 'The verification code must be exactly 6 digits.',
            'otp.regex' => 'The verification code must contain only numbers.',
        ]);

        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return $user->isPending()
                ? redirect()->route('buyer.pending')->with('verified', true)
                : redirect($user->homeRoute())->with('verified', true);
        }

        if (! $user->email_verification_otp || $user->email_verification_otp !== $request->input('otp')) {
            throw ValidationException::withMessages([
                'otp' => 'The verification code you entered is invalid.',
            ]);
        }

        if (! $user->email_verification_otp_expires_at || $user->email_verification_otp_expires_at->isPast()) {
            throw ValidationException::withMessages([
                'otp' => 'The verification code has expired. Please request a new code.',
            ]);
        }

        $user->forceFill([
            'email_verification_otp' => null,
            'email_verification_otp_expires_at' => null,
        ])->save();

        $user->markEmailAsVerified();
        event(new Verified($user));

        if ($user->isPending()) {
            return redirect()->route('buyer.pending')->with('verified', true);
        }

        return redirect($user->homeRoute())->with('verified', true);
    }

    /**
     * Fallback for signed URL verification if accessed directly.
     */
    public function verifyLink(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        if ($user->isPending()) {
            return redirect()->route('buyer.pending')->with('verified', true);
        }

        return redirect($user->homeRoute())->with('verified', true);
    }
}
