<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckStaffPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = auth()->user();

        if (!$user) {
            return redirect('/panel/login');
        }

        // Gym owner/admin always has full access.
        if (in_array($user->type, ['admin', 'owner'])) {
            return $next($request);
        }

        if ($user->type !== 'staff') {
            return $this->deny($request, $permissions);
        }

        // Support route strings like staff.permission:members.view|members.create
        $required = [];
        foreach ($permissions as $permission) {
            foreach (preg_split('/[|,]/', $permission) ?: [] as $part) {
                $part = trim($part);
                if ($part !== '') $required[] = $part;
            }
        }
        $required = array_values(array_unique($required));

        if (empty($required) || $user->hasAnyStaffPermission($required)) {
            return $next($request);
        }

        return $this->deny($request, $required);
    }

    private function deny(Request $request, array $permissions): Response
    {
        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'success' => false,
                'error' => 'Permission denied',
                'required_permissions' => array_values($permissions),
            ], 403);
        }

        return response()->view('panel.access-denied', [
            'requiredPermissions' => array_values($permissions),
        ], 403);
    }
}
