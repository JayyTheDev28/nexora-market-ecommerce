<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
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

        if (! $user->isAdmin()) {
            if ($request->expectsJson() || $request->is('admin/applications*')) {
                abort(403, 'Forbidden. Administrator credentials required.');
            }
            return redirect($user->homeRoute());
        }

        return $next($request);
    }
}

