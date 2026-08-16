<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\BaseController;
use App\Models\StaffRole;
use App\Models\StaffRolePermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PanelStaffRoleController extends BaseController
{
    public function index(Request $request)
    {
        $this->requireGymOwner();
        $pid = (int) auth()->id();

        $roles = StaffRole::where('parent_id', $pid)
            ->withCount(['users as users_count', 'permissions as permissions_count'])
            ->with('permissions')
            ->orderBy('name')
            ->get();

        $permissionCatalog = StaffRole::permissionCatalog();
        $allPermissionKeys = StaffRole::allPermissionKeys();

        return view('panel.staff.roles.index', compact('roles', 'permissionCatalog', 'allPermissionKeys'));
    }

    public function store(Request $request)
    {
        $this->requireGymOwner();
        $pid = (int) auth()->id();
        $isAjax = $this->isAjax($request);

        try {
            $data = $request->validate([
                'name' => 'required|string|max:120',
                'description' => 'nullable|string|max:1000',
                'status' => 'nullable|in:0,1',
                'permissions' => 'nullable|array',
                'permissions.*' => 'string|max:120',
            ]);
        } catch (ValidationException $e) {
            if ($isAjax) return response()->json(['success' => false, 'error' => $this->firstValidationError($e), 'errors' => $e->errors()], 422);
            throw $e;
        }

        $name = trim($data['name']);
        $exists = StaffRole::where('parent_id', $pid)
            ->whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->exists();

        if ($exists) {
            return $this->roleError($request, 'A role with this name already exists', 422);
        }

        $permissions = $this->cleanPermissions($request->input('permissions', []));
        if ($permissions === false) {
            return $this->roleError($request, 'One or more selected permissions are invalid', 422);
        }

        DB::beginTransaction();
        try {
            $role = StaffRole::create([
                'parent_id' => $pid,
                'name' => $name,
                'description' => $data['description'] ?? '',
                'is_system' => false,
                'status' => (int) ($data['status'] ?? 1),
            ]);

            $this->syncPermissions($role, $permissions);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->roleError($request, 'Failed to create role', 500);
        }

        if ($isAjax) {
            $role->load('permissions')->loadCount(['users as users_count', 'permissions as permissions_count']);
            return response()->json(['success' => true, 'message' => 'Role created successfully', 'role' => $this->rolePayload($role)]);
        }

        return redirect()->route('panel.staff.roles.index')->with('success', 'Role created successfully');
    }

    public function show(int $id)
    {
        $this->requireGymOwner();
        $role = $this->findRole($id);
        if (!$role) {
            return response()->json(['success' => false, 'error' => 'Role not found'], 404);
        }

        $role->load('permissions')->loadCount(['users as users_count', 'permissions as permissions_count']);
        return response()->json(['success' => true, 'role' => $this->rolePayload($role)]);
    }

    public function update(Request $request, int $id)
    {
        $this->requireGymOwner();
        $isAjax = $this->isAjax($request);
        $role = $this->findRole($id);
        if (!$role) {
            return $this->roleError($request, 'Role not found', 404);
        }

        try {
            $data = $request->validate([
                'name' => 'required|string|max:120',
                'description' => 'nullable|string|max:1000',
                'status' => 'nullable|in:0,1',
                'permissions' => 'nullable|array',
                'permissions.*' => 'string|max:120',
            ]);
        } catch (ValidationException $e) {
            if ($isAjax) return response()->json(['success' => false, 'error' => $this->firstValidationError($e), 'errors' => $e->errors()], 422);
            throw $e;
        }

        $name = trim($data['name']);
        $exists = StaffRole::where('parent_id', $role->parent_id)
            ->whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->where('id', '!=', $role->id)
            ->exists();

        if ($exists) {
            return $this->roleError($request, 'Another role with this name already exists', 422);
        }

        $permissions = $this->cleanPermissions($request->input('permissions', []));
        if ($permissions === false) {
            return $this->roleError($request, 'One or more selected permissions are invalid', 422);
        }

        DB::beginTransaction();
        try {
            $role->update([
                'name' => $name,
                'description' => $data['description'] ?? '',
                'status' => (int) ($data['status'] ?? 1),
            ]);
            $this->syncPermissions($role, $permissions);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->roleError($request, 'Failed to update role', 500);
        }

        if ($isAjax) {
            $role->refresh()->load('permissions')->loadCount(['users as users_count', 'permissions as permissions_count']);
            return response()->json(['success' => true, 'message' => 'Role updated successfully', 'role' => $this->rolePayload($role)]);
        }

        return redirect()->route('panel.staff.roles.index')->with('success', 'Role updated successfully');
    }

    public function destroy(Request $request, int $id)
    {
        $this->requireGymOwner();
        $role = $this->findRole($id);
        if (!$role) {
            return $this->roleError($request, 'Role not found', 404);
        }

        if ($role->users()->count() > 0) {
            return $this->roleError($request, 'Cannot delete role because staff users are assigned to it', 422);
        }

        DB::beginTransaction();
        try {
            StaffRolePermission::where('staff_role_id', $role->id)->delete();
            $role->delete();
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->roleError($request, 'Failed to delete role', 500);
        }

        if ($this->isAjax($request)) {
            return response()->json(['success' => true, 'message' => 'Role deleted successfully']);
        }

        return redirect()->route('panel.staff.roles.index')->with('success', 'Role deleted successfully');
    }

    private function requireGymOwner(): void
    {
        $user = auth()->user();
        if (!$this->planFeatureEnabled('staff_enabled', true)) {
            abort(402, \App\Services\SubscriptionFeatureService::featureLockedMessage('Staff & Roles'));
        }
        if (!$user || !in_array($user->type, ['admin', 'owner'])) {
            abort(403, 'Only gym owner can manage staff roles');
        }
    }

    private function findRole(int $id): ?StaffRole
    {
        return StaffRole::where('id', $id)
            ->where('parent_id', (int) auth()->id())
            ->first();
    }

    private function cleanPermissions($permissions): array|false
    {
        if (!is_array($permissions)) {
            $permissions = [];
        }

        $clean = [];
        foreach ($permissions as $permission) {
            $permission = trim((string) $permission);
            if ($permission === '') continue;
            if (!StaffRole::isValidPermission($permission)) {
                return false;
            }
            $clean[] = $permission;
        }

        return array_values(array_unique($clean));
    }

    private function syncPermissions(StaffRole $role, array $permissions): void
    {
        StaffRolePermission::where('staff_role_id', $role->id)->delete();

        foreach ($permissions as $permission) {
            StaffRolePermission::create([
                'staff_role_id' => $role->id,
                'permission_key' => $permission,
            ]);
        }

        // Existing app tokens contain old permission payloads. Revoke them so
        // staff must authenticate again and cannot keep stale access.
        $role->users()->each(function ($staff) {
            $staff->tokens()->delete();
        });
    }

    private function rolePayload(StaffRole $role): array
    {
        $permissionKeys = $role->relationLoaded('permissions')
            ? $role->permissions->pluck('permission_key')->values()->all()
            : $role->permissionKeys();

        return [
            'id' => $role->id,
            'name' => $role->name,
            'description' => $role->description,
            'status' => (int) $role->status,
            'is_system' => (bool) $role->is_system,
            'permissions' => $permissionKeys,
            'permissions_count' => count($permissionKeys),
            'users_count' => (int) ($role->users_count ?? $role->users()->count()),
        ];
    }

    private function roleError(Request $request, string $message, int $status = 400)
    {
        if ($this->isAjax($request)) {
            return response()->json(['success' => false, 'error' => $message], $status);
        }

        return back()->withInput()->with('error', $message);
    }

    private function isAjax(Request $request): bool
    {
        return $request->ajax()
            || $request->wantsJson()
            || $request->header('X-Requested-With') === 'XMLHttpRequest'
            || $request->expectsJson();
    }

    private function firstValidationError(ValidationException $e): string
    {
        $errors = $e->errors();
        $first = reset($errors);
        return is_array($first) ? (string) ($first[0] ?? 'Validation failed') : 'Validation failed';
    }
}
