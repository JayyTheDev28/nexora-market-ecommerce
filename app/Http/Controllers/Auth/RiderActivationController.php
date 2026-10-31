<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RiderActivationController extends Controller
{
    /**
     * Show rider password setup and activation form or status error.
     */
    public function show(string $token): View
    {
        $rider = User::where('role', 'courier')
            ->where('activation_token', $token)
            ->first();

        if (! $rider) {
            return view('auth.rider-activate', [
                'state' => 'invalid',
                'title' => 'Invalid or Used Activation Link',
                'message' => 'This activation link is invalid or has already been used. If you have already activated your account, you can proceed to log in. Otherwise, please contact your logistics operations manager for a new link.',
                'token' => $token,
                'rider' => null,
            ]);
        }

        if ($rider->activation_token_expires_at && $rider->activation_token_expires_at->isPast()) {
            return view('auth.rider-activate', [
                'state' => 'expired',
                'title' => 'Activation Link Expired',
                'message' => 'This activation link expired on ' . $rider->activation_token_expires_at->format('M d, Y h:i A') . '. Please contact your logistics operations manager to resend a new activation link.',
                'token' => $token,
                'rider' => $rider,
            ]);
        }

        return view('auth.rider-activate', [
            'state' => 'valid',
            'title' => 'Activate Rider Account',
            'message' => null,
            'token' => $token,
            'rider' => $rider,
        ]);
    }

    /**
     * Set password and activate the rider account.
     */
    public function activate(Request $request, string $token): RedirectResponse
    {
        $rider = User::where('role', 'courier')
            ->where('activation_token', $token)
            ->first();

        if (! $rider) {
            return redirect()->route('rider.activate', ['token' => $token])
                ->withErrors(['password' => 'This activation link is invalid or has already been used.']);
        }

        if ($rider->activation_token_expires_at && $rider->activation_token_expires_at->isPast()) {
            return redirect()->route('rider.activate', ['token' => $token])
                ->withErrors(['password' => 'This activation link has expired. Please contact your logistics administrator.']);
        }

        $validated = $request->validate([
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
            'password.required' => 'Password is required.',
            'password.confirmed' => 'Password confirmation does not match.',
            'password.min' => 'Password must be at least 8 characters long.',
            'password.letters' => 'Password must include at least one letter.',
            'password.mixed' => 'Password must include both uppercase and lowercase letters.',
            'password.numbers' => 'Password must include at least one number.',
            'password.symbols' => 'Password must include at least one special character symbol.',
        ]);

        $rider->update([
            'password' => Hash::make($validated['password']),
            'account_status' => 'active',
            'approval_status' => 'approved',
            'email_verified_at' => now(),
            'activation_token' => null,
            'activation_token_expires_at' => null,
        ]);

        Auth::login($rider);
        $request->session()->regenerate();

        return redirect()->route('courier.dashboard')->with('status', 'Account activated successfully! Welcome to your rider dashboard.');
    }
}
