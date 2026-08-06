@extends('panel.layouts.app')

@section('title', 'Add Staff User')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h5 class="mb-1"><i class="bi bi-person-plus me-2" style="color:var(--primary)"></i> Add Staff User</h5>
        <small class="text-muted">Create a receptionist, manager or other staff login.</small>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('panel.staff.roles.index') }}" class="btn btn-outline-primary"><i class="bi bi-shield-lock me-2"></i>Roles</a>
        <a href="{{ route('panel.staff.users.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-2"></i>Back</a>
    </div>
</div>

@if($roles->isEmpty())
    <div class="alert alert-warning rounded-4">
        <strong>No active roles found.</strong> Create a role first, then add staff users.
        <a href="{{ route('panel.staff.roles.index') }}" class="alert-link">Create role</a>
    </div>
@endif

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
    <div class="col-lg-4">
        <div class="stat-card h-100">
            <div class="icon mb-3" style="background:rgba(255,107,44,.12);color:var(--primary);"><i class="bi bi-shield-lock"></i></div>
            <h5>Staff Access</h5>
            <p class="text-muted">Staff users login with phone/email and only see modules allowed by their assigned role.</p>
            <div class="p-3 rounded-4" style="background:#f8fafc;border:1px solid var(--border);">
                <div class="fw-bold mb-1">Important</div>
                <small class="text-muted">Staff data always belongs to the gym owner account, not the staff user ID.</small>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="table-card">
            <form action="{{ route('panel.staff.users.store') }}" method="POST" id="staffCreateForm">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                        <input type="text" name="phone_number" class="form-control" value="{{ old('phone_number') }}" maxlength="10" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email <small class="text-muted">optional</small></label>
                        <input type="email" name="email" class="form-control" value="{{ old('email') }}" placeholder="Optional; phone login also works in app">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Role <span class="text-danger">*</span></label>
                        <select name="staff_role_id" class="form-select" required>
                            <option value="">Select role</option>
                            @foreach($roles as $role)
                                <option value="{{ $role->id }}" @selected(old('staff_role_id') == $role->id)>{{ $role->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" required minlength="6">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                        <input type="password" name="password_confirmation" class="form-control" required minlength="6">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select name="is_active" class="form-select">
                            <option value="1" @selected(old('is_active', '1') == '1')>Active</option>
                            <option value="0" @selected(old('is_active') == '0')>Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a href="{{ route('panel.staff.users.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary" {{ $roles->isEmpty() ? 'disabled' : '' }}><i class="bi bi-person-plus me-2"></i>Add Staff</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('staffCreateForm').addEventListener('submit', function(e) {
    const phone = this.querySelector('[name="phone_number"]').value.replace(/[^0-9]/g, '');
    if (!/^[6-9][0-9]{9}$/.test(phone)) {
        e.preventDefault();
        if (typeof window.showToast === 'function') window.showToast('Enter valid 10-digit Indian mobile number', 'error');
        else alert('Enter valid 10-digit Indian mobile number');
    }
});
</script>
@endpush
