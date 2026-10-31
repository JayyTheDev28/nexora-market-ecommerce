<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationNotificationController extends Controller
{
    public function notice(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return $user->isPending()
                ? redirect()->route('buyer.pending')
                : redirect($user->homeRoute());
        }

        // If no OTP exists or current OTP has expired, generate and send one
        if (! $user->email_verification_otp || ! $user->email_verification_otp_expires_at || $user->email_verification_otp_expires_at->isPast()) {
            $user->sendEmailVerificationOtp();
        }

        return view('buyer.auth.verify-email');
    }

    public function send(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect($user->homeRoute());
        }

        $user->sendEmailVerificationOtp(force: true);

        return back()->with('status', 'otp-sent');
    }
}
