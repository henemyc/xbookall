<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PanelMiddleware
{
    /**
     * Handle an incoming request.
     * Gym Owner type = 'admin'
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            // Save the exact page the user wanted
            session()->put('url.intended', $request->fullUrl());

            // Use raw direct path — this is the only redirect we ever do for panel
            // This completely avoids "Route [login] not defined"
            return redirect('/panel/login');
        }

        // Only allow gym owner types: 'admin' or 'owner'
        // IMPORTANT: Always return generic "Invalid email or password"
        // Never reveal that the user is a super_admin or any other type.
        $user = auth()->user();
        if (!in_array($user->type, ['admin', 'owner', 'staff'])) {
            auth()->logout();
            return redirect('/panel/login')->withErrors(['email' => 'Invalid phone or password.']);
        }

        return $next($request);
    }
}
