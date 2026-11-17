<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\PasswordResetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function __construct(
        protected PasswordResetService $resetService
    ) {}

    public function showForgotForm(Request $request): View
    {
        return view('buyer.auth.forgot-password', [
            'hideFooter' => true,
            'prefillEmail' => old('email', $request->query('email', $request->cookie('nexora_remember_email'))),
        ]);
    }

    public function sendResetOtp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'max:255'],
        ]);

        $res = $this->resetService->sendResetOtp($validated['email']);

        return redirect()
            ->route('password.reset', ['email' => $res['email']])
            ->with('status', "A 6-digit password reset code has been sent to {$res['email']}.");
    }

    public function showResetForm(Request $request): View
    {
        return view('buyer.auth.reset-password', [
            'hideFooter' => true,
            'email' => old('email', $request->query('email', session('reset_email', ''))),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'max:255'],
            'otp' => ['required', 'string', 'size:6'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $this->resetService->resetPassword(
            $validated['email'],
            $validated['otp'],
            $validated['password']
        );

        return redirect()
            ->route('login')
            ->with('status', 'Your password has been reset successfully. Please sign in with your new password.');
    }
}
