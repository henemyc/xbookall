@extends('panel.layouts.app')

@section('title', 'Add Trainer')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h5 class="mb-1"><i class="bi bi-person-plus me-2" style="color: var(--primary);"></i> Add Trainer</h5>
        <small class="text-muted">Create a trainer login and detailed trainer profile</small>
    </div>
    <a href="{{ route('panel.trainers.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-2"></i> Back to Trainers
    </a>
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
    <div class="col-lg-4">
        <div class="stat-card h-100">
            <div class="icon mb-3" style="background: linear-gradient(135deg,#16c784,#0d9c5f); color:white;">
                <i class="bi bi-shield-lock"></i>
            </div>
            <h5>Trainer Login</h5>
            <p class="text-muted mb-3">After creation, the trainer can login in the Flutter app with phone/email and default password.</p>
            <div class="p-3 rounded-4" style="background: rgba(22,199,132,.1); border: 1px solid rgba(22,199,132,.18);">
                <small class="text-muted d-block">Default password</small>
                <div class="fw-bold fs-5">1234@paas</div>
            </div>
            <hr>
            <ul class="text-muted small mb-0 ps-3">
                <li>Phone number must be unique inside this gym.</li>
                <li>Email is optional. A safe temporary email is generated if blank.</li>
                <li>You can update trainer details anytime from detail page.</li>
            </ul>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="table-card">
            <form action="{{ route('panel.trainers.store') }}" method="POST" id="trainerCreateForm">
                @csrf

                <h6 class="mb-3"><i class="bi bi-person-lines-fill me-2"></i> Basic Information</h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="{{ old('name') }}" placeholder="Trainer full name" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                        <input type="text" name="phone_number" class="form-control" value="{{ old('phone_number') }}" placeholder="10-digit mobile" maxlength="10" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="{{ old('email') }}" placeholder="Optional">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Gender</label>
                        <select name="gender" class="form-select">
                            <option value="male" @selected(old('gender', 'male') === 'male')>Male</option>
                            <option value="female" @selected(old('gender') === 'female')>Female</option>
                            <option value="other" @selected(old('gender') === 'other')>Other</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">DOB</label>
                        <input type="date" name="dob" class="form-control" value="{{ old('dob') }}">
                    </div>
                </div>

                <hr class="my-4">
                <h6 class="mb-3"><i class="bi bi-award me-2"></i> Professional Details</h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Qualification</label>
                        <input type="text" name="qualification" class="form-control" value="{{ old('qualification') }}" placeholder="Certified Fitness Trainer">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Specialization</label>
                        <input type="text" name="specialization" class="form-control" value="{{ old('specialization') }}" placeholder="Strength, Yoga, CrossFit, Cardio">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Experience Years</label>
                        <input type="number" name="experience_years" class="form-control" value="{{ old('experience_years', 0) }}" min="0" max="80">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Joining Date</label>
                        <input type="date" name="joining_date" class="form-control" value="{{ old('joining_date', now('Asia/Kolkata')->toDateString()) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Salary</label>
                        <input type="number" name="salary" class="form-control" value="{{ old('salary', 0) }}" min="0" step="0.01">
                    </div>
                </div>

                <hr class="my-4">
                <h6 class="mb-3"><i class="bi bi-geo-alt me-2"></i> Contact & Notes</h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">City</label>
                        <input type="text" name="city" class="form-control" value="{{ old('city') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Emergency Contact</label>
                        <input type="text" name="emergency_contact" class="form-control" value="{{ old('emergency_contact') }}" maxlength="30">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Address</label>
                        <input type="text" name="address" class="form-control" value="{{ old('address') }}" placeholder="Full address">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Bio / Notes</label>
                        <textarea name="bio" class="form-control" rows="3" placeholder="Trainer bio, certifications, notes...">{{ old('bio') }}</textarea>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a href="{{ route('panel.trainers.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-person-plus me-2"></i> Add Trainer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.getElementById('trainerCreateForm').addEventListener('submit', function(e) {
        const phone = this.querySelector('[name="phone_number"]').value.replace(/[^0-9]/g, '');
        if (!/^[6-9][0-9]{9}$/.test(phone)) {
            e.preventDefault();
            if (typeof window.showToast === 'function') {
                window.showToast('Enter a valid 10-digit Indian mobile number', 'error');
            } else {
                alert('Enter a valid 10-digit Indian mobile number');
            }
        }
    });
</script>
@endpush
