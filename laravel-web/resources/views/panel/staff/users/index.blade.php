@extends('panel.layouts.app')

@section('title', 'Staff Users')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h5 class="mb-1"><i class="bi bi-people-fill me-2" style="color:var(--primary)"></i> Staff Users</h5>
        <small class="text-muted">Create staff users, assign roles and monitor login activity.</small>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('panel.staff.activity.index') }}" class="btn btn-outline-info"><i class="bi bi-clock-history me-2"></i>Activity</a>
        <a href="{{ route('panel.staff.roles.index') }}" class="btn btn-outline-primary"><i class="bi bi-shield-lock me-2"></i>Roles</a>
        <a href="{{ route('panel.staff.users.create') }}" class="btn btn-primary"><i class="bi bi-person-plus me-2"></i>Add Staff</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="table-card h-100"><div class="text-muted small">Total Staff</div><h3 class="mb-0">{{ $stats['total'] }}</h3></div></div>
    <div class="col-md-3"><div class="table-card h-100"><div class="text-muted small">Active</div><h3 class="mb-0 text-success">{{ $stats['active'] }}</h3></div></div>
    <div class="col-md-3"><div class="table-card h-100"><div class="text-muted small">Inactive</div><h3 class="mb-0 text-danger">{{ $stats['inactive'] }}</h3></div></div>
    <div class="col-md-3"><div class="table-card h-100"><div class="text-muted small">Roles</div><h3 class="mb-0 text-primary">{{ $stats['roles'] }}</h3></div></div>
</div>

<div class="table-card">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <form method="GET" class="d-flex flex-wrap gap-2">
            <input type="text" name="search" value="{{ $search }}" class="form-control" placeholder="Search staff..." style="min-width:260px;">
            <select name="status" class="form-select" style="width:150px;">
                <option value="all" @selected($status === 'all')>All Status</option>
                <option value="active" @selected($status === 'active')>Active</option>
                <option value="inactive" @selected($status === 'inactive')>Inactive</option>
            </select>
            <button class="btn btn-primary"><i class="bi bi-search"></i></button>
        </form>
        <span class="text-muted small">{{ $staffUsers->total() }} staff users</span>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Staff</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th>Last App Open</th>
                    <th>Last IP</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($staffUsers as $staff)
                    <tr class="staff-row" data-id="{{ $staff->id }}">
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="rounded-4 d-flex align-items-center justify-content-center me-3" style="width:44px;height:44px;background:linear-gradient(135deg,#ff8a3d,#ff6b2c);color:white;font-weight:800;">
                                    {{ strtoupper(substr($staff->name, 0, 1)) }}
                                </div>
                                <div>
                                    <div class="fw-bold">{{ $staff->name }}</div>
                                    <small class="text-muted">{{ $staff->phone_number }}{{ $staff->email ? ' • '.$staff->email : '' }}</small>
                                </div>
                            </div>
                        </td>
                        <td>
                            @if($staff->staffRole)
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">{{ $staff->staffRole->name }}</span>
                            @else
                                <span class="badge bg-danger">No Role</span>
                            @endif
                        </td>
                        <td><span class="badge {{ $staff->is_active ? 'bg-success' : 'bg-danger' }} staff-status">{{ $staff->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td><small>{{ $staff->last_login_at ? $staff->last_login_at->format('d M Y, h:i A') : 'Never' }}</small></td>
                        <td><small>{{ $staff->last_app_opened_at ? $staff->last_app_opened_at->format('d M Y, h:i A') : 'Never' }}</small></td>
                        <td><small>{{ $staff->last_login_ip ?: ($staff->last_app_ip ?: '-') }}</small></td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1">
                                <button class="btn btn-sm {{ $staff->is_active ? 'btn-outline-warning' : 'btn-outline-success' }} toggle-staff-btn" data-id="{{ $staff->id }}" title="{{ $staff->is_active ? 'Deactivate' : 'Activate' }}"><i class="bi bi-toggle-{{ $staff->is_active ? 'on' : 'off' }}"></i></button>
                                <a href="{{ route('panel.staff.users.show', $staff->id) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                                <button class="btn btn-sm btn-outline-danger delete-staff-btn" data-id="{{ $staff->id }}" data-name="{{ $staff->name }}"><i class="bi bi-trash3"></i></button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center py-5 text-muted">No staff users found. Add your first staff user.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $staffUsers->links() }}</div>
</div>
@endsection

@push('scripts')
<script>
function toast(msg, type = 'success') {
    if (typeof window.showToast === 'function') return window.showToast(msg, type);
    alert(msg);
}

document.addEventListener('click', function(e) {
    const toggleBtn = e.target.closest('.toggle-staff-btn');
    if (toggleBtn) toggleStaff(toggleBtn);

    const deleteBtn = e.target.closest('.delete-staff-btn');
    if (deleteBtn) confirmDeleteStaff(deleteBtn);
});

async function toggleStaff(btn) {
    const id = btn.dataset.id;
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '⏳';
    try {
        const res = await fetch(`/panel/staff/users/${id}/toggle`, {
            method: 'PUT',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        });
        const data = await res.json();
        if (!res.ok || !data.success) throw new Error(data.error || data.message || 'Failed');
        const active = data.is_active === true || data.is_active === 1 || data.is_active === '1';
        const row = btn.closest('.staff-row');
        const badge = row.querySelector('.staff-status');
        badge.className = `badge ${active ? 'bg-success' : 'bg-danger'} staff-status`;
        badge.textContent = active ? 'Active' : 'Inactive';
        btn.className = `btn btn-sm ${active ? 'btn-outline-warning' : 'btn-outline-success'} toggle-staff-btn`;
        btn.title = active ? 'Deactivate' : 'Activate';
        btn.innerHTML = `<i class="bi bi-toggle-${active ? 'on' : 'off'}"></i>`;
        toast(data.message || 'Staff status updated', 'success');
    } catch (err) {
        btn.innerHTML = orig;
        toast(err.message || 'Failed to update status', 'error');
    } finally {
        btn.disabled = false;
    }
}

function confirmDeleteStaff(btn) {
    const id = btn.dataset.id;
    const name = btn.dataset.name;
    document.getElementById('deleteConfirmMessage').textContent = `Delete staff user "${name}"? This will revoke app/web access.`;
    const modal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
    const confirmBtn = document.getElementById('deleteConfirmBtn');
    const fresh = confirmBtn.cloneNode(true);
    confirmBtn.parentNode.replaceChild(fresh, confirmBtn);
    fresh.onclick = () => { modal.hide(); deleteStaff(id, btn); };
    modal.show();
}

async function deleteStaff(id, btn) {
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '⏳';
    try {
        const res = await fetch(`/panel/staff/users/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        });
        const data = await res.json();
        if (!res.ok || !data.success) throw new Error(data.error || data.message || 'Delete failed');
        btn.closest('.staff-row')?.remove();
        toast(data.message || 'Staff deleted', 'success');
    } catch (err) {
        btn.innerHTML = orig;
        btn.disabled = false;
        toast(err.message || 'Failed to delete staff', 'error');
    }
}
</script>
@endpush
