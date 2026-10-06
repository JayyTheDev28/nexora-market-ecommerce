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
 *
 * IMPORTANT: every failure path branches on $request->expectsJson(). A
 * plain redirect() works fine for normal page loads, but fetch() calls
 * (cart/checkout AJAX) silently *follow* an HTML redirect instead of
 * surfacing it — the caller ends up trying to JSON-parse a login page and
 * crashes with "Unexpected token '<'". AJAX requests need an actual JSON
 * error response so window.api() in app.js can detect the 401/403 and
 * redirect the browser itself.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
            return redirect()->route('login');
        }

        if (! $user->hasVerifiedEmail()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Please verify your email first.'], 403);
            }
            return redirect()->route('verification.notice');
        }

        if ($user->isDisapproved()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Your application was not approved.'], 403);
            }
            return redirect()->route('login');
        }

        if ($user->isPending()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Your application is still pending approval.'], 403);
            }
            return redirect()->route('buyer.pending');
        }

        // Catches a user whose account was suspended mid-session — without
        // this they'd keep browsing on an already-issued session cookie.
        if (! $user->isActive()) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Your account is no longer active.'], 403);
            }
            return redirect()->route('login')->withErrors([
                'email' => 'Your account is no longer active. Please contact support.',
            ]);
        }

        // Approved, but trying to open another role's dashboard —
        // send them to their own instead of showing a 403.
        if (! empty($roles) && ! in_array($user->role, $roles, true)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }
            return redirect($user->homeRoute());
        }

        return $next($request);
    }
}
