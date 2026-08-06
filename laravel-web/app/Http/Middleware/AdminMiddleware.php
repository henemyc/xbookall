<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     * Super Admin type = 'super_admin'
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            return redirect()->route('admin.login');
        }

        // Only allow 'super_admin'
        // IMPORTANT: Never leak account type. Use generic error.
        $user = auth()->user();
        if ($user->type !== 'super_admin') {
            auth()->logout();
            return redirect()->route('admin.login')->withErrors(['email' => 'Invalid email or password.']);
        }

        return $next($request);
    }
}
