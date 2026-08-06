@extends('panel.layouts.app')

@section('title', 'Trainer Details')

@section('content')
@php
    $detail = $trainer->trainerDetails;
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h5 class="mb-1"><i class="bi bi-person-badge me-2" style="color: var(--primary);"></i> Trainer Details</h5>
        <small class="text-muted">Profile, assignments and trainer account settings</small>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('panel.trainers.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-2"></i>Back</a>
        <form action="{{ route('panel.trainers.toggle', $trainer->id) }}" method="POST" class="d-inline">
            @csrf
            @method('PUT')
            <button class="btn {{ $trainer->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}" type="submit">
                <i class="bi bi-toggle-{{ $trainer->is_active ? 'on' : 'off' }} me-2"></i>{{ $trainer->is_active ? 'Deactivate' : 'Activate' }}
            </button>
        </form>
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
                <div class="mx-auto d-flex align-items-center justify-content-center mb-3" style="width:92px;height:92px;background:linear-gradient(135deg,#16c784,#0d9c5f);border-radius:28px;color:white;font-size:40px;font-weight:900;">
                    {{ strtoupper(substr($trainer->name, 0, 1)) }}
                </div>
                <h4 class="mb-1">{{ $trainer->name }}</h4>
                <p class="text-muted mb-2">{{ ($detail->specialization ?? '') ?: 'Fitness Trainer' }}</p>
                <span class="badge {{ $trainer->is_active ? 'bg-success' : 'bg-danger' }} px-3 py-2">{{ $trainer->is_active ? 'Active' : 'Inactive' }}</span>
            </div>

            <hr>
            <div class="d-flex justify-content-between mb-2">
                <span class="text-muted">Phone</span>
                <span class="fw-semibold">{{ $trainer->phone_number ?? '-' }}</span>
            </div>
            <div class="d-flex justify-content-between mb-2">
                <span class="text-muted">Email</span>
                <span class="fw-semibold text-end">{{ $trainer->email }}</span>
            </div>
            <div class="d-flex justify-content-between mb-2">
                <span class="text-muted">Qualification</span>
                <span class="fw-semibold text-end">{{ $detail->qualification ?: '-' }}</span>
            </div>
            <div class="d-flex justify-content-between mb-2">
                <span class="text-muted">Experience</span>
                <span class="fw-semibold">{{ $detail && $detail->experience_years ? $detail->experience_years.' years' : '-' }}</span>
            </div>
            <div class="d-flex justify-content-between mb-2">
                <span class="text-muted">Joining</span>
                <span class="fw-semibold">{{ $detail && $detail->joining_date ? $detail->joining_date->format('d-m-Y') : '-' }}</span>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-6">
                <div class="stat-card text-center h-100">
                    <div class="icon mx-auto mb-2" style="background:rgba(13,110,253,.1);color:#0d6efd;"><i class="bi bi-people"></i></div>
                    <h3 class="mb-0">{{ $assignedMembers->count() }}</h3>
                    <small class="text-muted">Assigned Members</small>
                </div>
            </div>
            <div class="col-6">
                <div class="stat-card text-center h-100">
                    <div class="icon mx-auto mb-2" style="background:rgba(22,199,132,.1);color:#16c784;"><i class="bi bi-calendar-week"></i></div>
                    <h3 class="mb-0">{{ $assignedClasses->count() }}</h3>
                    <small class="text-muted">Assigned Classes</small>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="table-card mb-4">
            <h6 class="mb-3"><i class="bi bi-pencil-square me-2"></i> Edit Trainer Profile</h6>
            <form action="{{ route('panel.trainers.update', $trainer->id) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $trainer->name) }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                        <input type="text" name="phone_number" class="form-control" value="{{ old('phone_number', $trainer->phone_number) }}" maxlength="10" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="{{ old('email', $trainer->email) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Gender</label>
                        <select name="gender" class="form-select">
                            <option value="male" @selected(old('gender', $detail->gender ?? 'male') === 'male')>Male</option>
                            <option value="female" @selected(old('gender', $detail->gender ?? '') === 'female')>Female</option>
                            <option value="other" @selected(old('gender', $detail->gender ?? '') === 'other')>Other</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">DOB</label>
                        <input type="date" name="dob" class="form-control" value="{{ old('dob', $detail && $detail->dob ? $detail->dob->format('Y-m-d') : '') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Qualification</label>
                        <input type="text" name="qualification" class="form-control" value="{{ old('qualification', $detail->qualification ?? '') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Specialization</label>
                        <input type="text" name="specialization" class="form-control" value="{{ old('specialization', $detail->specialization ?? '') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Experience Years</label>
                        <input type="number" name="experience_years" class="form-control" min="0" max="80" value="{{ old('experience_years', $detail->experience_years ?? 0) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Joining Date</label>
                        <input type="date" name="joining_date" class="form-control" value="{{ old('joining_date', $detail && $detail->joining_date ? $detail->joining_date->format('Y-m-d') : '') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Salary</label>
                        <input type="number" name="salary" class="form-control" min="0" step="0.01" value="{{ old('salary', $detail->salary ?? 0) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">City</label>
                        <input type="text" name="city" class="form-control" value="{{ old('city', $detail->city ?? '') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Emergency Contact</label>
                        <input type="text" name="emergency_contact" class="form-control" value="{{ old('emergency_contact', $detail->emergency_contact ?? '') }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Address</label>
                        <input type="text" name="address" class="form-control" value="{{ old('address', $detail->address ?? '') }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Bio / Notes</label>
                        <textarea name="bio" class="form-control" rows="3">{{ old('bio', $detail->bio ?? '') }}</textarea>
                    </div>
                </div>

                <div class="text-end mt-4">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-2"></i>Save Changes</button>
                </div>
            </form>
        </div>

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="table-card h-100">
                    <h6 class="mb-3"><i class="bi bi-people me-2"></i> Assigned Members</h6>
                    @forelse($assignedMembers->take(8) as $member)
                        @php($memberDetail = $member->traineeDetails)
                        <div class="d-flex align-items-center py-2 border-bottom">
                            <div class="rounded-3 d-flex align-items-center justify-content-center me-2" style="width:36px;height:36px;background:rgba(13,110,253,.1);color:#0d6efd;font-weight:700;">{{ strtoupper(substr($member->name,0,1)) }}</div>
                            <div class="flex-grow-1">
                                <div class="fw-semibold">{{ $member->name }}</div>
                                <small class="text-muted">{{ $member->phone_number }}{{ $memberDetail && $memberDetail->membership ? ' • '.$memberDetail->membership->title : '' }}</small>
                            </div>
                        </div>
                    @empty
                        <p class="text-muted mb-0">No members assigned yet.</p>
                    @endforelse
                    @if($assignedMembers->count() > 8)
                        <small class="text-muted d-block mt-2">+ {{ $assignedMembers->count() - 8 }} more members</small>
                    @endif
                </div>
            </div>
            <div class="col-lg-6">
                <div class="table-card h-100">
                    <h6 class="mb-3"><i class="bi bi-calendar-week me-2"></i> Assigned Classes</h6>
                    @forelse($assignedClasses as $class)
                        <div class="py-2 border-bottom">
                            <div class="fw-semibold">{{ $class->title }}</div>
                            @if($class->schedules->count())
                                @foreach($class->schedules as $schedule)
                                    <small class="text-muted d-block"><i class="bi bi-clock me-1"></i>{{ $schedule->days }} • {{ substr($schedule->start_time,0,5) }} - {{ substr($schedule->end_time,0,5) }}</small>
                                @endforeach
                            @else
                                <small class="text-muted">No schedule</small>
                            @endif
                        </div>
                    @empty
                        <p class="text-muted mb-0">No classes assigned yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
