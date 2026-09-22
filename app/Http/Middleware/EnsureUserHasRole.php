<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates a route to one or more roles, and enforces the two checks that
 * come before role even matters: email verified, then admin-approved.
 *
 * Usage: ->middleware('role:seller') or ->middleware('role:seller,admin')
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        if ($user->isDisapproved()) {
            return redirect()->route('login');
        }

        if ($user->isPending()) {
            return redirect()->route('buyer.pending');
        }

        // Catches a user whose account was suspended mid-session — without
        // this they'd keep browsing on an already-issued session cookie.
        if (! $user->isActive()) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Your account is no longer active. Please contact support.',
            ]);
        }

        // Approved, but trying to open another role's dashboard —
        // send them to their own instead of showing a 403.
        if (! empty($roles) && ! in_array($user->role, $roles, true)) {
            return redirect($user->homeRoute());
        }

        return $next($request);
    }
}
