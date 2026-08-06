<?php

namespace App\Http\Middleware;

use App\Support\PlatformMaintenance;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PlatformMaintenanceMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $path = trim($request->path(), '/');

        // Super Admin must always stay accessible so maintenance can be turned
        // off/edited. Public API status is handled by api.php and not blocked.
        if (str_starts_with($path, 'admin')) {
            return $next($request);
        }

        $status = PlatformMaintenance::status();
        if (!empty($status['active'])) {
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => 'maintenance',
                    'message' => $status['message'],
                    'maintenance' => $status,
                ], 503)->header('Retry-After', (string) ($status['retry_after'] ?? 60));
            }

            return response()
                ->view('maintenance', ['maintenance' => $status], 503)
                ->header('Retry-After', (string) ($status['retry_after'] ?? 60));
        }

        return $next($request);
    }
}
