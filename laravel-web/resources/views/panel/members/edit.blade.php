@extends('panel.layouts.app')

@section('title', 'Edit Member')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-10">
        <div class="table-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="mb-0">
                    <i class="bi bi-pencil-square me-2 text-primary"></i> Edit Member: {{ $member->name }}
                </h5>
                <a href="{{ route('panel.members.show', $member->id) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i> Back
                </a>
            </div>
            
            <form action="{{ route('panel.members.update', $member->id) }}" method="POST">
                @csrf
                @method('PUT')
                
                <!-- Personal Information -->
                <div class="mb-4">
                    <h6 class="mb-3 pb-2 border-bottom">
                        <i class="bi bi-person me-2"></i> Personal Information
                    </h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" value="{{ $member->name }}" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" value="{{ $member->email }}" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Phone Number</label>
                            <input type="text" name="phone_number" class="form-control" value="{{ $member->phone_number }}">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" name="dob" class="form-control" value="{{ $member->traineeDetails->dob ?? '' }}">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Gender</label>
                            <select name="gender" class="form-select">
                                <option value="">Select Gender</option>
                                <option value="male" {{ ($member->traineeDetails->gender ?? '') == 'male' ? 'selected' : '' }}>Male</option>
                                <option value="female" {{ ($member->traineeDetails->gender ?? '') == 'female' ? 'selected' : '' }}>Female</option>
                                <option value="other" {{ ($member->traineeDetails->gender ?? '') == 'other' ? 'selected' : '' }}>Other</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Address</label>
                            <input type="text" name="address" class="form-control" value="{{ $member->traineeDetails->address ?? '' }}">
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">City</label>
                            <input type="text" name="city" class="form-control" value="{{ $member->traineeDetails->city ?? '' }}">
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">State</label>
                            <input type="text" name="state" class="form-control" value="{{ $member->traineeDetails->state ?? '' }}">
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Pincode</label>
                            <input type="text" name="zip_code" class="form-control" value="{{ $member->traineeDetails->zip_code ?? '' }}">
                        </div>
                    </div>
                </div>

                <!-- Membership Details -->
                <div class="mb-4">
                    <h6 class="mb-3 pb-2 border-bottom">
                        <i class="bi bi-card-list me-2"></i> Membership Details
                    </h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Membership Plan</label>
                            <select name="membership_plan" class="form-select">
                                <option value="">Select Plan</option>
                                @foreach($plans as $plan)
                                    <option value="{{ $plan->id }}" {{ ($member->traineeDetails->membership_plan ?? '') == $plan->id ? 'selected' : '' }}>
                                        {{ $plan->title }} - ₹{{ number_format($plan->amount) }} / {{ $plan->package }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Fitness Goal</label>
                            <input type="text" name="fitness_goal" class="form-control" value="{{ $member->traineeDetails->fitness_goal ?? '' }}">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="membership_start_date" class="form-control" value="{{ $member->traineeDetails->membership_start_date ?? '' }}">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Expiry Date</label>
                            <input type="date" name="membership_expiry_date" class="form-control" value="{{ $member->traineeDetails->membership_expiry_date ?? '' }}">
                        </div>
                    </div>
                </div>

                <!-- Status -->
                <div class="mb-4">
                    <h6 class="mb-3 pb-2 border-bottom">
                        <i class="bi bi-toggle-on me-2"></i> Status
                    </h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="1" {{ ($member->traineeDetails->status ?? 1) == 1 ? 'selected' : '' }}>Active</option>
                                <option value="2" {{ ($member->traineeDetails->status ?? 1) == 2 ? 'selected' : '' }}>Expired</option>
                                <option value="3" {{ ($member->traineeDetails->status ?? 1) == 3 ? 'selected' : '' }}>Frozen</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Submit -->
                <div class="d-flex justify-content-between align-items-center">
                    <a href="{{ route('panel.members.show', $member->id) }}" class="btn btn-outline-secondary">
                        <i class="bi bi-x-circle me-2"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-save me-2"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
