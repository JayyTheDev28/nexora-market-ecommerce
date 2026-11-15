<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class EnforceSingleSession
{
    /**
     * Handle an incoming request.
     * Enforce single active session per account across all user types.
     * If logged in on another device, terminate previous session and notify user.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('testing') && ! $request->hasHeader('X-Test-Enforce-Single-Session')) {
            return $next($request);
        }

        if (Auth::check()) {
            $user = Auth::user();
            $currentSessionId = $request->session()->getId();
            $activeSessionId = Cache::get("user_active_session_{$user->id}");

            if (! $activeSessionId) {
                // If not cached yet, record current session as the active one
                Cache::forever("user_active_session_{$user->id}", $currentSessionId);
            } elseif ($activeSessionId !== $currentSessionId) {
                // Active session belongs to a different device/login
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => 'This account has been logged in on a different device. You have been logged out for security.',
                    ], 401);
                }

                return redirect()->route('login')->with('warning', 'This account has been logged in on a different device. You have been logged out for security.');
            }
        }

        return $next($request);
    }
}
