<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * FINAL BULLETPROOF VERSION (2026)
     * Completely bypass Laravel's default behavior.
     * We NEVER resolve any named route called "login".
     */
    public function handle($request, \Closure $next, ...$guards)
    {
        if ($this->isLoggedIn($guards)) {
            return $next($request);
        }

        // Save intended for "redirect back after login"
        $intended = $request->fullUrl();

        if ($request->is('panel') || $request->is('panel/*') || str_starts_with($request->path(), 'panel')) {
            session()->put('url.intended', $intended);
            return redirect('/panel/login');   // RAW PATH ONLY
        }

        return redirect('/login');
    }

    private function isLoggedIn(array $guards): bool
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if ($this->auth->guard($guard)->check()) {
                $this->auth->shouldUse($guard);
                return true;
            }
        }
        return false;
    }

    // Safety net - always return raw paths, never names
    protected function redirectTo(Request $request): ?string
    {
        if ($request->is('panel') || $request->is('panel/*')) {
            return '/panel/login';
        }
        return '/login';
    }
}
