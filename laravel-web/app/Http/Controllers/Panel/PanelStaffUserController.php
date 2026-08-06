<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\BaseController;
use App\Models\StaffRole;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PanelStaffUserController extends BaseController
{
    public function index(Request $request)
    {
        $this->requireGymOwner();
        $pid = (int) auth()->id();
        $search = trim((string) $request->get('search', ''));
        $status = $request->get('status', 'all');

        $query = User::where('type', 'staff')
            ->where('parent_id', $pid)
            ->with('staffRole');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $staffUsers = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();
        $roles = StaffRole::where('parent_id', $pid)->orderBy('name')->get();
        $stats = [
            'total' => User::where('type', 'staff')->where('parent_id', $pid)->count(),
            'active' => User::where('type', 'staff')->where('parent_id', $pid)->where('is_active', true)->count(),
            'inactive' => User::where('type', 'staff')->where('parent_id', $pid)->where('is_active', false)->count(),
            'roles' => $roles->count(),
        ];

        return view('panel.staff.users.index', compact('staffUsers', 'roles', 'stats', 'search', 'status'));
    }

    public function create()
    {
        $this->requireGymOwner();
        $roles = StaffRole::where('parent_id', (int) auth()->id())
            ->where('status', 1)
            ->orderBy('name')
            ->get();

        return view('panel.staff.users.create', compact('roles'));
    }

    public function store(Request $request)
    {
        $this->requireGymOwner();
        $pid = (int) auth()->id();
        if (!$this->planFeatureEnabled('staff_enabled', true)) {
            return $this->subscriptionDenied($request, \App\Services\SubscriptionFeatureService::featureLockedMessage('Staff users'));
        }
        $staffLimit = $this->planLimit('staff_limit', 0);
        if ($staffLimit > 0 && User::where('type', 'staff')->where('parent_id', $pid)->count() >= $staffLimit) {
            return $this->subscriptionDenied($request, \App\Services\SubscriptionFeatureService::limitReachedMessage('Staff user', $staffLimit));
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone_number' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'staff_role_id' => 'required|integer',
            'password' => 'required|string|min:6|confirmed',
            'is_active' => 'nullable|in:0,1',
        ]);

        $role = StaffRole::where('id', (int) $data['staff_role_id'])
            ->where('parent_id', $pid)
            ->where('status', 1)
            ->first();
        if (!$role) {
            return back()->withInput()->with('error', 'Please select a valid active role');
        }

        $phone = $this->normalizePhone($data['phone_number']);
        if (!$phone) {
            return back()->withInput()->with('error', 'Phone must be a valid 10-digit Indian mobile number');
        }

        if (User::where('type', 'staff')->where('parent_id', $pid)->where('phone_number', $phone)->exists()) {
            return back()->withInput()->with('error', 'A staff user with this phone number already exists');
        }

        $email = trim((string) ($data['email'] ?? ''));
        if ($email !== '' && User::where('email', $email)->exists()) {
            return back()->withInput()->with('error', 'Email already exists');
        }
        if ($email === '') {
            $email = 'staff_' . $pid . '_' . $phone . '@gymxbook.temp';
        }

        $staff = User::create([
            'name' => trim($data['name']),
            'email' => $email,
            'phone_number' => $phone,
            'type' => 'staff',
            'password' => Hash::make($data['password']),
            'parent_id' => $pid,
            'staff_role_id' => $role->id,
            'is_active' => (int) ($data['is_active'] ?? 1) === 1,
            'password_changed_at' => now('Asia/Kolkata'),
        ]);

        $this->logActivity('staff', 'created', 'users', $staff->id, 'Created staff user ' . $staff->name, null, $staff);

        return redirect()->route('panel.staff.users.show', $staff->id)->with('success', 'Staff user created successfully');
    }

    public function show(int $id)
    {
        $this->requireGymOwner();
        $staff = $this->findStaff($id);
        if (!$staff) abort(404, 'Staff user not found');

        $staff->load('staffRole.permissions');
        $roles = StaffRole::where('parent_id', (int) auth()->id())
            ->where('status', 1)
            ->orderBy('name')
            ->get();

        $permissionCatalog = StaffRole::permissionCatalog();
        $permissionKeys = $staff->staffPermissionKeys();

        return view('panel.staff.users.show', compact('staff', 'roles', 'permissionCatalog', 'permissionKeys'));
    }

    public function update(Request $request, int $id)
    {
        $this->requireGymOwner();
        $pid = (int) auth()->id();
        $staff = $this->findStaff($id);
        if (!$staff) abort(404, 'Staff user not found');

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone_number' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'staff_role_id' => 'required|integer',
            'is_active' => 'nullable|in:0,1',
        ]);

        $role = StaffRole::where('id', (int) $data['staff_role_id'])
            ->where('parent_id', $pid)
            ->where('status', 1)
            ->first();
        if (!$role) {
            return back()->withInput()->with('error', 'Please select a valid active role');
        }

        $phone = $this->normalizePhone($data['phone_number']);
        if (!$phone) {
            return back()->withInput()->with('error', 'Phone must be a valid 10-digit Indian mobile number');
        }

        if (User::where('type', 'staff')->where('parent_id', $pid)->where('phone_number', $phone)->where('id', '!=', $staff->id)->exists()) {
            return back()->withInput()->with('error', 'A staff user with this phone number already exists');
        }

        $email = trim((string) ($data['email'] ?? ''));
        if ($email !== '' && User::where('email', $email)->where('id', '!=', $staff->id)->exists()) {
            return back()->withInput()->with('error', 'Email already exists');
        }
        if ($email === '') $email = $staff->email;

        $before = $staff->toArray();

        $staff->update([
            'name' => trim($data['name']),
            'email' => $email,
            'phone_number' => $phone,
            'staff_role_id' => $role->id,
            'is_active' => (int) ($data['is_active'] ?? 1) === 1,
        ]);

        $this->logActivity('staff', 'updated', 'users', $staff->id, 'Updated staff user ' . $staff->name, $before, $staff->fresh());

        return redirect()->route('panel.staff.users.show', $staff->id)->with('success', 'Staff user updated successfully');
    }

    public function updatePassword(Request $request, int $id)
    {
        $this->requireGymOwner();
        $staff = $this->findStaff($id);
        if (!$staff) abort(404, 'Staff user not found');

        $data = $request->validate([
            'password' => 'required|string|min:6|confirmed',
        ]);

        $staff->tokens()->delete();
        $staff->update([
            'password' => Hash::make($data['password']),
            'password_changed_at' => now('Asia/Kolkata'),
        ]);

        $this->logActivity('staff', 'password_changed', 'users', $staff->id, 'Changed password for staff user ' . $staff->name);

        return redirect()->route('panel.staff.users.show', $staff->id)->with('success', 'Staff password changed successfully');
    }

    public function toggle(Request $request, int $id)
    {
        $this->requireGymOwner();
        $staff = $this->findStaff($id);
        if (!$staff) {
            return $this->staffError($request, 'Staff user not found', 404);
        }

        $oldActive = (bool) $staff->is_active;
        $staff->update(['is_active' => !$staff->is_active]);
        if (!$staff->is_active) {
            $staff->tokens()->delete();
        }

        $this->logActivity('staff', $staff->is_active ? 'activated' : 'deactivated', 'users', $staff->id, ($staff->is_active ? 'Activated' : 'Deactivated') . ' staff user ' . $staff->name, ['is_active' => $oldActive], ['is_active' => (bool) $staff->is_active]);

        if ($this->isAjax($request)) {
            return response()->json([
                'success' => true,
                'message' => 'Staff status updated',
                'is_active' => (bool) $staff->is_active,
            ]);
        }

        return redirect()->route('panel.staff.users.show', $staff->id)->with('success', 'Staff status updated');
    }

    public function destroy(Request $request, int $id)
    {
        $this->requireGymOwner();
        $staff = $this->findStaff($id);
        if (!$staff) {
            return $this->staffError($request, 'Staff user not found', 404);
        }

        $before = $staff->toArray();
        $staffName = $staff->name;
        $staffId = $staff->id;
        $staff->tokens()->delete();
        $staff->delete();
        $this->logActivity('staff', 'deleted', 'users', $staffId, 'Deleted staff user ' . $staffName, $before, null);

        if ($this->isAjax($request)) {
            return response()->json(['success' => true, 'message' => 'Staff user deleted successfully']);
        }

        return redirect()->route('panel.staff.users.index')->with('success', 'Staff user deleted successfully');
    }

    private function requireGymOwner(): void
    {
        $user = auth()->user();
        if (!$this->planFeatureEnabled('staff_enabled', true)) {
            abort(402, \App\Services\SubscriptionFeatureService::featureLockedMessage('Staff & Roles'));
        }
        if (!$user || !in_array($user->type, ['admin', 'owner'])) {
            abort(403, 'Only gym owner can manage staff users');
        }
    }

    private function findStaff(int $id): ?User
    {
        return User::where('id', $id)
            ->where('type', 'staff')
            ->where('parent_id', (int) auth()->id())
            ->first();
    }

    private function normalizePhone(string $phone): ?string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) $digits = substr($digits, 2);
        if (strlen($digits) !== 10 || !preg_match('/^[6-9][0-9]{9}$/', $digits)) return null;
        return $digits;
    }

    private function staffError(Request $request, string $message, int $status = 400)
    {
        if ($this->isAjax($request)) {
            return response()->json(['success' => false, 'error' => $message], $status);
        }
        return back()->with('error', $message);
    }

    private function isAjax(Request $request): bool
    {
        return $request->ajax()
            || $request->wantsJson()
            || $request->header('X-Requested-With') === 'XMLHttpRequest'
            || $request->expectsJson();
    }
}
