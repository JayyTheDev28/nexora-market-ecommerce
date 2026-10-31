<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogisticsMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
            return redirect()->route('login');
        }

        // Must be sorting_center (Logistics partner)
        if (! $user->isSortingCenter()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Forbidden. Logistics partner access required.'], 403);
            }
            return redirect($user->homeRoute());
        }

        // Email verified check
        if (! $user->hasVerifiedEmail()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Please verify your email address via OTP first.'], 403);
            }
            return redirect()->route('logistics.otp.notice');
        }

        // Application disapproved / rejected check
        if ($user->isDisapproved() || ($user->logisticsProfile && $user->logisticsProfile->isRejected())) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Your application was rejected. Please review feedback and resubmit.'], 403);
            }
            return redirect()->route('logistics.resubmit');
        }

        // Application pending check
        if ($user->isPending() || ($user->logisticsProfile && $user->logisticsProfile->isPendingApproval())) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Your application is pending admin compliance approval.'], 403);
            }
            return redirect()->route('logistics.pending');
        }

        // Account active check
        if (! $user->isActive()) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Your account is suspended.'], 403);
            }
            return redirect()->route('login')->withErrors(['email' => 'Your logistics account has been suspended. Please contact support.']);
        }

        return $next($request);
    }
}

