@extends('panel.layouts.app')

@section('title', 'Staff Roles')

@push('styles')
<style>
    .roles-hero {
        background: linear-gradient(135deg, #0f172a, #1e293b 58%, #7c2d12);
        color: #fff;
        border-radius: 24px;
        padding: 24px;
        position: relative;
        overflow: hidden;
        box-shadow: 0 18px 45px rgba(15, 23, 42, .16);
    }
    .roles-hero::after {
        content: '';
        position: absolute;
        right: -70px;
        top: -80px;
        width: 230px;
        height: 230px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(255,255,255,.18), transparent 65%);
    }
    .role-card {
        background: var(--card-bg);
        border: 1px solid var(--border);
        border-radius: 20px;
        padding: 18px;
        height: 100%;
        box-shadow: 0 1px 3px rgba(0,0,0,.04);
        transition: all .18s ease;
    }
    .role-card:hover { transform: translateY(-2px); box-shadow: 0 14px 34px rgba(15,23,42,.08); }
    .role-icon {
        width: 48px; height: 48px; border-radius: 16px;
        background: rgba(255,107,44,.12); color: var(--primary);
        display: flex; align-items: center; justify-content: center;
        font-size: 23px;
    }
    #roleModal,
    #roleModal .modal-dialog,
    #roleModal .modal-content,
    #roleModal .modal-body,
    #roleForm {
        overflow-x: hidden !important;
    }
    #roleModal .modal-dialog {
        max-width: min(1140px, calc(100vw - 18px));
        margin-left: auto;
        margin-right: auto;
    }
    #roleModal .modal-body {
        max-width: 100%;
    }
    #roleModal .row {
        --bs-gutter-x: 1rem;
    }
    .permission-group {
        border: 1px solid var(--border);
        border-radius: 18px;
        overflow: hidden;
        background: #fff;
        margin-bottom: 14px;
        max-width: 100%;
    }
    .permission-group-header {
        padding: 13px 15px;
        background: #f8fafc;
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }
    .permission-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
        padding: 14px;
        max-width: 100%;
        overflow-x: hidden;
    }
    .permission-tile {
        border: 1px solid var(--border);
        border-radius: 13px;
        padding: 10px 12px;
        cursor: pointer;
        transition: all .15s ease;
        min-height: 50px;
        min-width: 0;
        max-width: 100%;
        overflow-wrap: anywhere;
        word-break: break-word;
    }
    .permission-tile:has(input:checked) {
        border-color: rgba(255,107,44,.42);
        background: rgba(255,107,44,.08);
    }
    .permission-tile .form-check {
        min-width: 0;
        display: grid;
        grid-template-columns: 20px minmax(0, 1fr);
        column-gap: 8px;
        align-items: start;
    }
    .permission-tile .form-check-input { margin-top: 2px; }
    .permission-tile .form-check-label {
        min-width: 0;
        overflow-wrap: anywhere;
        word-break: break-word;
        line-height: 1.25;
    }
    .permission-key {
        grid-column: 2;
        font-size: 10.5px;
        color: var(--text-secondary);
        display:block;
        margin-top: 2px;
        overflow-wrap: anywhere;
        word-break: break-all;
    }
    @media(max-width: 768px) { .permission-grid { grid-template-columns: 1fr; } }
    @media(max-width: 576px) {
        #roleModal .modal-body { padding: 16px !important; }
        .permission-grid { padding: 10px; }
        .permission-group-header { align-items: flex-start; flex-direction: column; }
    }
</style>
@endpush

@section('content')
<div class="roles-hero mb-4">
    <div class="position-relative" style="z-index:1">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <div style="width:58px;height:58px;border-radius:18px;background:rgba(255,255,255,.14);display:flex;align-items:center;justify-content:center;font-size:28px;">
                    <i class="bi bi-shield-lock"></i>
                </div>
                <div>
                    <h4 class="mb-1" style="font-family:'Space Grotesk';font-weight:800;">Staff Roles & Permissions</h4>
                    <div style="opacity:.72;font-size:13px;">Create roles like Receptionist, Manager or Accountant and decide exactly what each role can access.</div>
                </div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('panel.staff.activity.index') }}" class="btn btn-outline-light"><i class="bi bi-clock-history me-2"></i>Activity</a>
                <a href="{{ route('panel.staff.users.index') }}" class="btn btn-outline-light"><i class="bi bi-people me-2"></i>Staff Users</a>
                <button class="btn btn-light" onclick="openRoleModal()"><i class="bi bi-plus-circle me-2"></i>Create Role</button>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="table-card h-100">
            <div class="d-flex align-items-center gap-3">
                <div class="role-icon"><i class="bi bi-person-gear"></i></div>
                <div>
                    <div class="text-muted small">Total Roles</div>
                    <h3 class="mb-0">{{ $roles->count() }}</h3>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="table-card h-100">
            <div class="d-flex align-items-center gap-3">
                <div class="role-icon" style="background:rgba(22,199,132,.12);color:var(--success);"><i class="bi bi-check2-circle"></i></div>
                <div>
                    <div class="text-muted small">Active Roles</div>
                    <h3 class="mb-0">{{ $roles->where('status', 1)->count() }}</h3>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="table-card h-100">
            <div class="d-flex align-items-center gap-3">
                <div class="role-icon" style="background:rgba(59,158,255,.12);color:var(--info);"><i class="bi bi-key"></i></div>
                <div>
                    <div class="text-muted small">Permission Keys</div>
                    <h3 class="mb-0">{{ count($allPermissionKeys) }}</h3>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="table-card">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h5 class="mb-1"><i class="bi bi-shield-check me-2" style="color:var(--primary)"></i> Roles</h5>
            <small class="text-muted">Unused roles can be deleted. Roles assigned to staff are protected.</small>
        </div>
        <button class="btn btn-primary" onclick="openRoleModal()"><i class="bi bi-plus-circle me-2"></i>Add Role</button>
    </div>

    @if($roles->isEmpty())
        <div class="text-center py-5">
            <i class="bi bi-shield-lock fs-1 d-block mb-3" style="opacity:.22"></i>
            <h6>No staff roles yet</h6>
            <p class="text-muted mb-3">Create your first role and choose module permissions.</p>
            <button class="btn btn-primary" onclick="openRoleModal()"><i class="bi bi-plus-circle me-2"></i>Create Receptionist Role</button>
        </div>
    @else
        <div class="row g-3" id="rolesGrid">
            @foreach($roles as $role)
                @php
                    $permissionKeys = $role->permissions->pluck('permission_key')->values()->all();
                    $payload = [
                        'id' => $role->id,
                        'name' => $role->name,
                        'description' => $role->description,
                        'status' => (int) $role->status,
                        'permissions' => $permissionKeys,
                        'users_count' => (int) ($role->users_count ?? 0),
                    ];
                @endphp
                <div class="col-xl-4 col-md-6 role-card-wrap" data-id="{{ $role->id }}">
                    <div class="role-card">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="role-icon"><i class="bi bi-person-badge"></i></div>
                                <div>
                                    <h6 class="mb-1 role-title">{{ $role->name }}</h6>
                                    <span class="badge {{ $role->status ? 'bg-success' : 'bg-secondary' }} role-status">{{ $role->status ? 'Active' : 'Inactive' }}</span>
                                </div>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><button type="button" class="dropdown-item" onclick='openRoleModal(@json($payload))'><i class="bi bi-pencil me-2"></i>Edit</button></li>
                                    <li><button type="button" class="dropdown-item text-danger" onclick="confirmDeleteRole({{ $role->id }}, '{{ addslashes($role->name) }}', {{ (int) ($role->users_count ?? 0) }})"><i class="bi bi-trash3 me-2"></i>Delete</button></li>
                                </ul>
                            </div>
                        </div>
                        <p class="text-muted small mb-3 role-desc" style="min-height:36px;">{{ $role->description ?: 'No description added.' }}</p>
                        <div class="d-flex gap-2 flex-wrap">
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><i class="bi bi-key me-1"></i>{{ count($permissionKeys) }} permissions</span>
                            <span class="badge bg-info-subtle text-info border border-info-subtle"><i class="bi bi-people me-1"></i>{{ $role->users_count ?? 0 }} users</span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

<div class="modal fade" id="roleModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content" style="border-radius:22px;overflow:hidden;">
            <div style="background:linear-gradient(135deg,#0f172a,#1e293b);padding:22px;color:white;">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-1" id="roleModalTitle">Create Staff Role</h5>
                        <div style="opacity:.68;font-size:13px;">Choose the exact modules and actions this role can access.</div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
            </div>
            <form id="roleForm" method="POST" action="{{ route('panel.staff.roles.store') }}">
                @csrf
                <input type="hidden" name="_method" id="roleFormMethod" value="PUT" disabled>
                <div class="modal-body p-4">
                    <div class="row g-3 mb-4">
                        <div class="col-md-5">
                            <label class="form-label">Role Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" id="roleName" placeholder="Receptionist" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Description</label>
                            <input type="text" class="form-control" name="description" id="roleDescription" placeholder="Front desk access for attendance and members">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" id="roleStatus">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <h6 class="mb-0"><i class="bi bi-key me-2"></i>Permissions</h6>
                        <div class="d-flex gap-2 flex-wrap">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="setAllPermissions(true)">Select All</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="setAllPermissions(false)">Clear All</button>
                        </div>
                    </div>

                    @foreach($permissionCatalog as $module => $permissions)
                        <div class="permission-group" data-module="{{ Str::slug($module) }}">
                            <div class="permission-group-header">
                                <div>
                                    <strong>{{ $module }}</strong>
                                    <div class="text-muted small">{{ count($permissions) }} permissions</div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleModule('{{ Str::slug($module) }}')">Toggle Module</button>
                            </div>
                            <div class="permission-grid">
                                @foreach($permissions as $key => $label)
                                    <label class="permission-tile">
                                        <div class="form-check mb-0">
                                            <input class="form-check-input permission-checkbox" type="checkbox" name="permissions[]" value="{{ $key }}" id="perm_{{ md5($key) }}">
                                            <span class="form-check-label fw-semibold">{{ $label }}</span>
                                            <span class="permission-key">{{ $key }}</span>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="modal-footer p-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="roleSubmitBtn"><i class="bi bi-save me-2"></i>Save Role</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    if (!window.CSS) window.CSS = {};
    if (!CSS.escape) CSS.escape = function(value) { return String(value).replace(/[^a-zA-Z0-9_-]/g, '\\$&'); };

    const roleModalEl = document.getElementById('roleModal');
    const roleModal = new bootstrap.Modal(roleModalEl);
    const roleForm = document.getElementById('roleForm');
    const roleFormMethod = document.getElementById('roleFormMethod');

    function toast(msg, type = 'success') {
        if (typeof window.showToast === 'function') return window.showToast(msg, type);
        alert(msg);
    }

    function setAllPermissions(checked) {
        document.querySelectorAll('.permission-checkbox').forEach(cb => cb.checked = checked);
    }

    function toggleModule(moduleKey) {
        const group = document.querySelector(`.permission-group[data-module="${moduleKey}"]`);
        if (!group) return;
        const boxes = Array.from(group.querySelectorAll('.permission-checkbox'));
        const shouldCheck = boxes.some(cb => !cb.checked);
        boxes.forEach(cb => cb.checked = shouldCheck);
    }

    function openRoleModal(role = null) {
        roleForm.reset();
        setAllPermissions(false);
        roleFormMethod.disabled = true;
        roleForm.action = @json(route('panel.staff.roles.store'));
        document.getElementById('roleModalTitle').textContent = 'Create Staff Role';
        document.getElementById('roleSubmitBtn').innerHTML = '<i class="bi bi-save me-2"></i>Save Role';

        if (role) {
            roleForm.action = `/panel/staff/roles/${role.id}`;
            roleFormMethod.disabled = false;
            document.getElementById('roleModalTitle').textContent = 'Edit Staff Role';
            document.getElementById('roleName').value = role.name || '';
            document.getElementById('roleDescription').value = role.description || '';
            document.getElementById('roleStatus').value = String(role.status ?? 1);
            (role.permissions || []).forEach(key => {
                const cb = document.querySelector(`.permission-checkbox[value="${CSS.escape(key)}"]`);
                if (cb) cb.checked = true;
            });
        }

        roleModal.show();
    }

    roleForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = document.getElementById('roleSubmitBtn');
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '⏳ Saving...';
        try {
            const fd = new FormData(roleForm);
            const res = await fetch(roleForm.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: fd
            });
            const data = await res.json();
            if (!res.ok || !data.success) throw new Error(data.error || data.message || 'Failed to save role');
            toast(data.message || 'Role saved', 'success');
            roleModal.hide();
            setTimeout(() => location.reload(), 500);
        } catch (err) {
            toast(err.message || 'Failed to save role', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = orig;
        }
    });

    function confirmDeleteRole(id, name, usersCount) {
        if (usersCount > 0) {
            toast('Cannot delete role because staff users are assigned to it', 'error');
            return;
        }
        document.getElementById('deleteConfirmMessage').textContent = `Delete role "${name}"?`;
        const modal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
        const confirmBtn = document.getElementById('deleteConfirmBtn');
        const fresh = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(fresh, confirmBtn);
        fresh.onclick = () => {
            modal.hide();
            deleteRole(id);
        };
        modal.show();
    }

    async function deleteRole(id) {
        try {
            const res = await fetch(`/panel/staff/roles/${id}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });
            const data = await res.json();
            if (!res.ok || !data.success) throw new Error(data.error || data.message || 'Delete failed');
            document.querySelector(`.role-card-wrap[data-id="${id}"]`)?.remove();
            toast(data.message || 'Role deleted', 'success');
        } catch (err) {
            toast(err.message || 'Failed to delete role', 'error');
        }
    }
</script>
@endpush
