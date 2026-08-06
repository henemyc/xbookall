<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Support\ActivityLogger;
use App\Services\SubscriptionFeatureService;

class BaseController extends Controller
{
    /**
     * Success response
     */
    protected function success($data = [], string $message = 'Success', int $code = 200): JsonResponse
    {
        return response()->json(array_merge(['success' => true, 'message' => $message], $data), $code);
    }

    /**
     * Error response
     */
    protected function error(string $message = 'Error', int $code = 400, $errors = null): JsonResponse
    {
        $response = ['success' => false, 'error' => $message];
        if ($errors) {
            $response['errors'] = $errors;
        }
        return response()->json($response, $code);
    }

    /**
     * Get current authenticated user
     */
    protected function currentUser(): ?\App\Models\User
    {
        return auth()->user();
    }

    /**
     * Tenant write scope.
     *
     * IMPORTANT:
     * - Super Admin user id is 1.
     * - Gym owner/admin users can have users.parent_id = 1 because their parent
     *   is Super Admin. That does NOT mean their gym data belongs to parent_id=1.
     * - Gym data belongs to the gym owner's own users.id.
     * - Staff/trainer/member data belongs to their gym owner in users.parent_id.
     */
    protected function getParentId(): int
    {
        $user = $this->currentUser();
        if (!$user) return 0;

        if (in_array($user->type, ['admin', 'owner'])) {
            return $this->resolveGymOwnerIdForLegacyDuplicates($user);
        }

        return (int) ($user->parent_id ?: 0);
    }

    /**
     * Old migrated DBs can contain duplicate gym-owner rows with same phone/email.
     * Web login may hit the correct row while old app tokens may point to another
     * owner row with no business data. Resolve owner scope to the matching owner
     * record that actually has gym data, without changing stored data.
     */
    protected function resolveGymOwnerIdForLegacyDuplicates(\App\Models\User $user): int
    {
        $ownId = (int) $user->id;
        if ($this->gymBusinessDataScore($ownId) > 0) {
            return $ownId;
        }

        $phone = preg_replace('/[^0-9]/', '', (string) $user->phone_number);
        if (strlen($phone) === 12 && str_starts_with($phone, '91')) {
            $phone = substr($phone, 2);
        }

        if (!$phone && empty($user->email)) {
            return $ownId;
        }

        $candidates = \App\Models\User::whereIn('type', ['admin', 'owner'])
            ->where('id', '!=', $ownId)
            ->where(function ($q) use ($user, $phone) {
                if ($phone) {
                    $q->where('phone_number', $phone)
                      ->orWhere('phone_number', 'like', '%' . $phone)
                      ->orWhere('phone_number', 'like', '%91' . $phone);
                }
                if (!empty($user->email)) {
                    $q->orWhere('email', $user->email);
                }
            })
            ->get();

        $bestId = $ownId;
        $bestScore = 0;
        foreach ($candidates as $candidate) {
            $score = $this->gymBusinessDataScore((int) $candidate->id);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestId = (int) $candidate->id;
            }
        }

        return $bestScore > 0 ? $bestId : $ownId;
    }

    protected function gymBusinessDataScore(int $ownerId): int
    {
        if ($ownerId <= 0) return 0;

        $score = 0;
        try {
            $score += \App\Models\User::where('parent_id', $ownerId)->whereIn('type', ['trainee', 'trainer', 'staff'])->count();
            if (\Illuminate\Support\Facades\Schema::hasTable('memberships')) {
                $score += (int) \Illuminate\Support\Facades\DB::table('memberships')->where('parent_id', $ownerId)->count();
            }
            if (\Illuminate\Support\Facades\Schema::hasTable('invoices')) {
                $score += (int) \Illuminate\Support\Facades\DB::table('invoices')->where('parent_id', $ownerId)->count();
            }
            if (\Illuminate\Support\Facades\Schema::hasTable('settings')) {
                $score += (int) \Illuminate\Support\Facades\DB::table('settings')->where('parent_id', $ownerId)->count();
            }
        } catch (\Throwable $e) {
            return 0;
        }

        return $score;
    }

    /**
     * Strict tenant read scope.
     *
     * This intentionally does NOT include parent_id=1 or parent_id=0.
     * Include global/default records explicitly per-controller with
     * getGymAndGlobalParentIds() only when that module really supports global
     * defaults (e.g. Categories/Types).
     */
    protected function getGymParentIds(): array
    {
        $pid = $this->getParentId();
        return $pid > 0 ? [$pid] : [0];
    }

    /**
     * Tenant + global defaults scope.
     * Use this only for lookup/config tables where parent_id=0 means global.
     */
    protected function getGymAndGlobalParentIds(): array
    {
        return array_values(array_unique(array_filter(array_merge($this->getGymParentIds(), [0]), fn($id) => $id !== null)));
    }

    /**
     * Apply strict gym data scope to a query.
     */
    protected function scopeToGym($query)
    {
        return $query->whereIn('parent_id', $this->getGymParentIds());
    }

    /**
     * Check if user is admin/owner/staff for owner-style API access.
     * Staff action permissions are enforced by route middleware on web and by
     * Flutter permission UI; data still scopes to gym owner via getParentId().
     */
    protected function isAdmin(): bool
    {
        $user = $this->currentUser();
        return $user && in_array($user->type, ['admin', 'owner', 'staff']);
    }

    /**
     * Check if user is trainee
     */
    protected function isTrainee(): bool
    {
        $user = $this->currentUser();
        return $user && $user->type === 'trainee';
    }

    protected function isStaff(): bool
    {
        $user = $this->currentUser();
        return $user && $user->type === 'staff';
    }

    protected function staffPermissionKeys(): array
    {
        $user = $this->currentUser();
        return $user ? $user->staffPermissionKeys() : [];
    }

    protected function hasStaffPermission(string $permission): bool
    {
        $user = $this->currentUser();
        return $user ? $user->hasStaffPermission($permission) : false;
    }

    protected function hasAnyStaffPermission(array $permissions): bool
    {
        $user = $this->currentUser();
        return $user ? $user->hasAnyStaffPermission($permissions) : false;
    }

    protected function requireStaffPermission(string $permission): void
    {
        if (!$this->hasStaffPermission($permission)) {
            abort(403, 'Permission denied');
        }
    }

    protected function logActivity(string $module, string $action, ?string $recordType = null, $recordId = null, ?string $description = null, $before = null, $after = null): void
    {
        ActivityLogger::log($module, $action, $recordType, $recordId, $description, $before, $after, request(), $this->currentUser(), $this->getParentId());
    }

    protected function planFeatureEnabled(string $featureKey, bool $defaultForLegacy = true): bool
    {
        return SubscriptionFeatureService::enabled($this->getParentId(), $featureKey, $defaultForLegacy);
    }

    protected function planLimit(string $featureKey, int $defaultForLegacy = 0): int
    {
        return SubscriptionFeatureService::limit($this->getParentId(), $featureKey, $defaultForLegacy);
    }

    protected function subscriptionDenied(Request $request, string $message, int $status = 402)
    {
        if ($request->ajax() || $request->wantsJson() || $request->expectsJson() || str_starts_with($request->path(), 'api/')) {
            return response()->json(['success' => false, 'error' => $message], $status);
        }

        return back()->with('error', $message);
    }

    /**
     * Require admin access
     */
    protected function requireAdmin(): void
    {
        if (!$this->isAdmin()) {
            abort(403, 'Admin access required');
        }
    }

    /**
     * Paginate results
     */
    protected function paginate($query, Request $request, int $perPage = 20)
    {
        $page = max(1, intval($request->get('page', 1)));
        $results = $query->paginate($perPage, ['*'], 'page', $page);
        
        return [
            'data' => $results->items(),
            'total' => $results->total(),
            'page' => $results->currentPage(),
            'pages' => $results->lastPage(),
            'per_page' => $results->perPage(),
        ];
    }
}
