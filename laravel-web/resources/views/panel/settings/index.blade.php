@extends('panel.layouts.app')

@section('title', 'Settings')

@section('content')
<div class="row g-4">
    <!-- Header -->
    <div class="col-12">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <h4 class="mb-1 fw-bold"><i class="bi bi-gear-fill me-2"></i> Settings</h4>
                <p class="text-muted mb-0">Manage your account and gym business details</p>
            </div>
            <div class="d-flex align-items-center">
                <div class="text-end me-3">
                    <div class="fw-semibold">{{ auth()->user()->name }}</div>
                    <small class="text-muted">{{ auth()->user()->email }}</small>
                </div>
                <div class="avatar" style="width: 52px; height: 52px; background: linear-gradient(135deg, #ff8a3d, #ff6b2c); color: white; font-size: 20px; font-weight: 700;">
                    {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                </div>
            </div>
        </div>
    </div>

    <!-- PERSONAL PROFILE -->
    <div class="col-lg-5">
        <div class="table-card h-100">
            <div class="d-flex align-items-center mb-3">
                <div>
                    <h6 class="mb-1"><i class="bi bi-person-fill me-2 text-primary"></i> Personal Profile</h6>
                    <small class="text-muted">Your login details and contact info</small>
                </div>
            </div>

            <form id="personalProfileForm">
                @csrf
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="personalName" class="form-control" value="{{ $user->name }}" required>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="email" id="personalEmail" class="form-control" value="{{ $user->email }}" required>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" name="phone_number" id="personalPhone" class="form-control" value="{{ $user->phone_number ?? '' }}" placeholder="e.g. +91 98765 43210">
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill" id="savePersonalBtn">
                        <i class="bi bi-check-circle me-2"></i> Save Personal Info
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- GYM / BUSINESS PROFILE -->
    <div class="col-lg-7">
        <div class="table-card h-100">
            <div class="d-flex align-items-center mb-3">
                <div>
                    <h6 class="mb-1"><i class="bi bi-building me-2 text-success"></i> Gym / Business Profile</h6>
                    <small class="text-muted">This information appears on invoices, receipts &amp; member portal</small>
                </div>
            </div>

            <form id="gymProfileForm">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Gym / Business Name</label>
                        <input type="text" name="company_name" id="gymName" class="form-control" value="{{ $gymProfile['company_name'] }}" placeholder="Your Gym Name">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Business Phone</label>
                        <input type="tel" name="company_phone" id="gymPhone" class="form-control" value="{{ $gymProfile['company_phone'] }}" placeholder="+91 98765 43210">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Business Email</label>
                        <input type="email" name="company_email" id="gymEmail" class="form-control" value="{{ $gymProfile['company_email'] }}" placeholder="gym@example.com">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Website</label>
                        <input type="url" name="company_website" id="gymWebsite" class="form-control" value="{{ $gymProfile['company_website'] }}" placeholder="https://yourgym.com">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Address</label>
                        <textarea name="company_address" id="gymAddress" class="form-control" rows="2" placeholder="123 Fitness Street, City, State">{{ $gymProfile['company_address'] }}</textarea>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-success px-4" id="saveGymBtn">
                        <i class="bi bi-save me-2"></i> Save Gym Profile
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- SECURITY & PASSWORD -->
    <div class="col-12">
        <div class="table-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h6 class="mb-1"><i class="bi bi-shield-lock me-2 text-danger"></i> Security</h6>
                    <small class="text-muted">Keep your account safe</small>
                </div>
                <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                    <i class="bi bi-key me-2"></i> Change Password
                </button>
            </div>

            <div class="row">
                <div class="col-md-8">
                    <div class="d-flex align-items-center">
                        <div class="flex-grow-1">
                            <div class="fw-medium">Password</div>
                            <div class="text-muted small">Last changed: <span class="fw-medium">{{ $user->updated_at ? $user->updated_at->format('d M Y') : 'Unknown' }}</span></div>
                        </div>
                        <div>
                            <span class="badge bg-light text-dark border px-3 py-1">Strong</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Account Meta -->
    <div class="col-12">
        <div class="table-card">
            <div class="row align-items-center">
                <div class="col-md-4">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <i class="bi bi-info-circle fs-4 text-muted"></i>
                        </div>
                        <div>
                            <div class="fw-semibold">Account Details</div>
                            <small class="text-muted">Role: <span class="badge bg-primary">{{ ucfirst($user->type) }}</span></small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="small">
                        <span class="text-muted">Member since:</span><br>
                        <strong>{{ $user->created_at->format('d M Y') }}</strong>
                    </div>
                </div>
                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <div class="small text-muted">
                        App version: <strong>1.0.0</strong><br>
                        Platform: <strong>GymXBook</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Change Password Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-key me-2"></i> Change Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2">
                <form id="passwordForm">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" id="currentPassword" class="form-control" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">New Password</label>
                                <input type="password" name="new_password" id="newPassword" class="form-control" minlength="6" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" name="new_password_confirmation" id="confirmPassword" class="form-control" minlength="6" required>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info py-2 small mb-0">
                        Password must be at least 6 characters.
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="savePasswordBtn" onclick="changePassword()">
                    <i class="bi bi-key me-2"></i> Update Password
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // =====================
    // PERSONAL PROFILE AJAX
    // =====================
    document.getElementById('personalProfileForm').addEventListener('submit', async function(e) {
        e.preventDefault();

        const btn = document.getElementById('savePersonalBtn');
        const origText = btn.innerHTML;

        btn.disabled = true;
        btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span> Saving...`;

        const formData = new FormData(this);

        try {
            const res = await fetch('{{ route("panel.settings.updatePersonalProfile") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: formData
            });

            const data = await res.json();

            if (res.ok && data.success) {
                window.showToast(data.message || 'Personal profile updated!', 'success');

                // Update header avatar name
                const headerName = document.querySelector('.fw-semibold');
                if (headerName) headerName.textContent = data.user.name;

                // Update header email
                const headerEmail = document.querySelector('.text-muted');
                if (headerEmail && headerEmail.parentElement) {
                    headerEmail.textContent = data.user.email;
                }
            } else {
                window.showToast(data.error || 'Failed to update personal profile', 'error');
            }
        } catch (err) {
            window.showToast('Network error. Please try again.', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = origText;
        }
    });

    // =====================
    // GYM PROFILE AJAX
    // =====================
    document.getElementById('gymProfileForm').addEventListener('submit', async function(e) {
        e.preventDefault();

        const btn = document.getElementById('saveGymBtn');
        const origText = btn.innerHTML;

        btn.disabled = true;
        btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span> Saving...`;

        const formData = new FormData(this);

        try {
            const res = await fetch('{{ route("panel.settings.updateProfile") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: formData
            });

            const data = await res.json();

            if (res.ok && data.success) {
                window.showToast(data.message || 'Gym profile updated successfully!', 'success');
            } else {
                window.showToast(data.error || 'Failed to update gym profile', 'error');
            }
        } catch (err) {
            window.showToast('Network error. Please try again.', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = origText;
        }
    });

    // =====================
    // CHANGE PASSWORD (AJAX)
    // =====================
    async function changePassword() {
        const btn = document.getElementById('savePasswordBtn');
        const origText = btn.innerHTML;

        btn.disabled = true;
        btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span> Updating...`;

        const form = document.getElementById('passwordForm');
        const formData = new FormData(form);

        try {
            const res = await fetch('{{ route("panel.settings.updatePassword") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: formData
            });

            const data = await res.json();

            if (res.ok && data.success) {
                window.showToast(data.message || 'Password changed successfully!', 'success');
                bootstrap.Modal.getInstance(document.getElementById('changePasswordModal')).hide();
                form.reset();
            } else {
                window.showToast(data.error || 'Failed to change password', 'error');
            }
        } catch (err) {
            window.showToast('Network error while changing password', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = origText;
        }
    }

    // Optional: Keyboard support for password modal
    document.addEventListener('DOMContentLoaded', function() {
        const passwordModal = document.getElementById('changePasswordModal');
        if (passwordModal) {
            passwordModal.addEventListener('shown.bs.modal', function() {
                document.getElementById('currentPassword')?.focus();
            });
        }

        // Clear password modal on hide
        passwordModal.addEventListener('hidden.bs.modal', function() {
            document.getElementById('passwordForm').reset();
        });
    });
</script>
@endpush
