<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('buyer.auth.login', ['hideFooter' => true]);
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember');

        if (! Auth::attempt($credentials, $remember)) {
            throw ValidationException::withMessages([
                'email' => 'Those credentials don\'t match our records.',
            ]);
        }

        $request->session()->regenerate();

        $user = Auth::user();

        if ($user->isDisapproved()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'Your application was not approved.'
                    . ($user->disapproval_reason ? ' Reason: ' . $user->disapproval_reason : ''),
            ]);
        }

        // An already-approved account the admin has since suspended or
        // deactivated is blocked here, separately from the application gate.
        if (! $user->isActive()) {
            $label = $user->isSuspended() ? 'suspended' : 'deactivated';

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => "Your account has been {$label}."
                    . ($user->status_reason ? ' Reason: ' . $user->status_reason : '')
                    . ' Please contact support if you believe this is an error.',
            ]);
        }

        if (! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        if ($user->isPending()) {
            return redirect()->route('buyer.pending');
        }

        return redirect()->intended($user->homeRoute());
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
