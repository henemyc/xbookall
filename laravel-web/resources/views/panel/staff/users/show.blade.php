@extends('panel.layouts.app')

@section('title', 'Staff Details')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h5 class="mb-1"><i class="bi bi-person-badge me-2" style="color:var(--primary)"></i> Staff Details</h5>
        <small class="text-muted">Manage profile, role, password and access status.</small>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('panel.staff.activity.index', ['staff_user_id' => $staff->id]) }}" class="btn btn-outline-info"><i class="bi bi-clock-history me-2"></i>Activity</a>
        <a href="{{ route('panel.staff.roles.index') }}" class="btn btn-outline-primary"><i class="bi bi-shield-lock me-2"></i>Roles</a>
        <a href="{{ route('panel.staff.users.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-2"></i>Back</a>
    </div>
</div>

@if ($errors->any())
    <div class="alert alert-danger rounded-4">
        <div class="fw-bold mb-1">Please fix these errors:</div>
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="row g-4">
    <div class="col-xl-4">
        <div class="stat-card mb-4">
            <div class="text-center">
                <div class="mx-auto d-flex align-items-center justify-content-center mb-3" style="width:88px;height:88px;background:linear-gradient(135deg,#ff8a3d,#ff6b2c);border-radius:28px;color:white;font-size:38px;font-weight:900;">
                    {{ strtoupper(substr($staff->name, 0, 1)) }}
                </div>
                <h4 class="mb-1">{{ $staff->name }}</h4>
                <p class="text-muted mb-2">{{ $staff->staffRole->name ?? 'No Role' }}</p>
                <span class="badge {{ $staff->is_active ? 'bg-success' : 'bg-danger' }} px-3 py-2">{{ $staff->is_active ? 'Active' : 'Inactive' }}</span>
            </div>
            <hr>
            <div class="d-flex justify-content-between mb-2"><span class="text-muted">Phone</span><span class="fw-semibold">{{ $staff->phone_number }}</span></div>
            <div class="d-flex justify-content-between mb-2"><span class="text-muted">Email</span><span class="fw-semibold text-end">{{ $staff->email }}</span></div>
            <div class="d-flex justify-content-between mb-2"><span class="text-muted">Created</span><span class="fw-semibold">{{ $staff->created_at->format('d M Y') }}</span></div>
            <div class="d-flex justify-content-between mb-2"><span class="text-muted">Password Changed</span><span class="fw-semibold">{{ $staff->password_changed_at ? $staff->password_changed_at->format('d M Y') : '-' }}</span></div>
        </div>

        <div class="table-card mb-4">
            <h6 class="mb-3"><i class="bi bi-clock-history me-2"></i>Login Activity</h6>
            <div class="d-flex justify-content-between py-2 border-bottom">
                <span class="text-muted">Last Web Login</span>
                <strong class="text-end">{{ $staff->last_login_at ? $staff->last_login_at->format('h:i A') . ' (web)' : 'Never' }}</strong>
            </div>
            @if($staff->last_login_at)
                <small class="text-muted d-block mb-2">{{ $staff->last_login_at->format('d M Y') }} • IP: {{ $staff->last_login_ip ?: '-' }}</small>
            @endif
            <div class="d-flex justify-content-between py-2 border-bottom">
                <span class="text-muted">Last App Open</span>
                <strong class="text-end">{{ $staff->last_app_opened_at ? $staff->last_app_opened_at->format('h:i A') . ' (app)' : 'Never' }}</strong>
            </div>
            @if($staff->last_app_opened_at)
                <small class="text-muted d-block mb-2">{{ $staff->last_app_opened_at->format('d M Y') }} • IP: {{ $staff->last_app_ip ?: '-' }}{{ $staff->last_app_platform ? ' • '.$staff->last_app_platform : '' }}{{ $staff->last_app_version ? ' v'.$staff->last_app_version : '' }}</small>
            @endif
            <div class="d-flex justify-content-between py-2">
                <span class="text-muted">Recent Access</span>
                <strong class="text-end">
                    @php
                        $lastWeb = $staff->last_login_at;
                        $lastApp = $staff->last_app_opened_at;
                    @endphp
                    @if($lastWeb && (!$lastApp || $lastWeb->gte($lastApp)))
                        {{ $lastWeb->format('h:i A') }} (web)
                    @elseif($lastApp)
                        {{ $lastApp->format('h:i A') }} (app)
                    @else
                        Never
                    @endif
                </strong>
            </div>
        </div>

        <div class="d-grid gap-2">
            <form action="{{ route('panel.staff.users.toggle', $staff->id) }}" method="POST">
                @csrf
                @method('PUT')
                <button type="submit" class="btn {{ $staff->is_active ? 'btn-outline-warning' : 'btn-outline-success' }} w-100">
                    <i class="bi bi-toggle-{{ $staff->is_active ? 'on' : 'off' }} me-2"></i>{{ $staff->is_active ? 'Deactivate Staff' : 'Activate Staff' }}
                </button>
            </form>
            <button type="button" class="btn btn-outline-danger" onclick="confirmDeleteStaff()"><i class="bi bi-trash3 me-2"></i>Delete Staff</button>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="table-card mb-4">
            <h6 class="mb-3"><i class="bi bi-pencil-square me-2"></i>Edit Staff User</h6>
            <form action="{{ route('panel.staff.users.update', $staff->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Full Name</label><input type="text" name="name" class="form-control" value="{{ old('name', $staff->name) }}" required></div>
                    <div class="col-md-6"><label class="form-label">Phone</label><input type="text" name="phone_number" class="form-control" value="{{ old('phone_number', $staff->phone_number) }}" maxlength="10" required></div>
                    <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="{{ old('email', $staff->email) }}"></div>
                    <div class="col-md-6">
                        <label class="form-label">Role</label>
                        <select name="staff_role_id" class="form-select" required>
                            @foreach($roles as $role)
                                <option value="{{ $role->id }}" @selected(old('staff_role_id', $staff->staff_role_id) == $role->id)>{{ $role->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select name="is_active" class="form-select">
                            <option value="1" @selected(old('is_active', $staff->is_active ? '1' : '0') == '1')>Active</option>
                            <option value="0" @selected(old('is_active', $staff->is_active ? '1' : '0') == '0')>Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="text-end mt-4"><button class="btn btn-primary"><i class="bi bi-save me-2"></i>Save Changes</button></div>
            </form>
        </div>

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="table-card h-100">
                    <h6 class="mb-3"><i class="bi bi-key me-2"></i>Change Password</h6>
                    <form action="{{ route('panel.staff.users.password', $staff->id) }}" method="POST">
                        @csrf
                        <div class="mb-3"><label class="form-label">New Password</label><input type="password" name="password" class="form-control" minlength="6" required></div>
                        <div class="mb-3"><label class="form-label">Confirm Password</label><input type="password" name="password_confirmation" class="form-control" minlength="6" required></div>
                        <button class="btn btn-outline-primary w-100"><i class="bi bi-shield-lock me-2"></i>Update Password</button>
                    </form>
                    <small class="text-muted d-block mt-2">Changing password revokes existing app tokens.</small>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="table-card h-100">
                    <h6 class="mb-3"><i class="bi bi-shield-check me-2"></i>Role Permissions</h6>
                    @if(!$staff->staffRole)
                        <p class="text-muted">No role assigned.</p>
                    @else
                        <div class="mb-2"><strong>{{ $staff->staffRole->name }}</strong></div>
                        <div style="max-height: 280px; overflow:auto;">
                            @foreach($permissionCatalog as $module => $permissions)
                                @php($owned = array_intersect(array_keys($permissions), $permissionKeys))
                                @if(count($owned))
                                    <div class="mb-3">
                                        <div class="fw-semibold small text-muted mb-1">{{ $module }}</div>
                                        <div class="d-flex flex-wrap gap-1">
                                            @foreach($owned as $key)
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">{{ $permissions[$key] }}</span>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<form id="deleteStaffForm" action="{{ route('panel.staff.users.destroy', $staff->id) }}" method="POST" class="d-none">
    @csrf
    @method('DELETE')
</form>
@endsection

@push('scripts')
<script>
function confirmDeleteStaff() {
    document.getElementById('deleteConfirmMessage').textContent = 'Delete staff user "{{ addslashes($staff->name) }}"? This will revoke access.';
    const modal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
    const confirmBtn = document.getElementById('deleteConfirmBtn');
    const fresh = confirmBtn.cloneNode(true);
    confirmBtn.parentNode.replaceChild(fresh, confirmBtn);
    fresh.onclick = () => document.getElementById('deleteStaffForm').submit();
    modal.show();
}
</script>
@endpush
