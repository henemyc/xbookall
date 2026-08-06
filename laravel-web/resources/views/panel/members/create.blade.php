@extends('panel.layouts.app')

@section('title', 'Add Member')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-10">
        <div class="table-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="mb-0">
                    <i class="bi bi-person-plus me-2 text-primary"></i> Add New Member
                </h5>
                <a href="{{ route('panel.members.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i> Back to List
                </a>
            </div>
            
            <form action="{{ route('panel.members.store') }}" method="POST">
                @csrf
                
                <!-- Personal Information -->
                <div class="mb-4">
                    <h6 class="mb-3 pb-2 border-bottom">
                        <i class="bi bi-person me-2"></i> Personal Information
                    </h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" value="{{ old('name') }}" placeholder="Enter full name" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" value="{{ old('email') }}" placeholder="email@example.com" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Phone Number</label>
                            <input type="text" name="phone_number" class="form-control" value="{{ old('phone_number') }}" placeholder="10 digit number">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" name="dob" class="form-control" value="{{ old('dob') }}">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Gender</label>
                            <select name="gender" class="form-select">
                                <option value="">Select Gender</option>
                                <option value="male" {{ old('gender') == 'male' ? 'selected' : '' }}>Male</option>
                                <option value="female" {{ old('gender') == 'female' ? 'selected' : '' }}>Female</option>
                                <option value="other" {{ old('gender') == 'other' ? 'selected' : '' }}>Other</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Address</label>
                            <input type="text" name="address" class="form-control" value="{{ old('address') }}" placeholder="Street address">
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">City</label>
                            <input type="text" name="city" class="form-control" value="{{ old('city') }}" placeholder="City">
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">State</label>
                            <input type="text" name="state" class="form-control" value="{{ old('state') }}" placeholder="State">
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Pincode</label>
                            <input type="text" name="zip_code" class="form-control" value="{{ old('zip_code') }}" placeholder="Pincode">
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
                            <label class="form-label">Membership Plan <span class="text-danger">*</span></label>
                            <select name="membership_plan" class="form-select" required>
                                <option value="">Select Plan</option>
                                @foreach($plans as $plan)
                                    <option value="{{ $plan->id }}" {{ old('membership_plan') == $plan->id ? 'selected' : '' }}>
                                        {{ $plan->title }} - ₹{{ number_format($plan->amount) }} / {{ $plan->package }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="membership_start_date" class="form-control" value="{{ old('membership_start_date', date('Y-m-d')) }}">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Expiry Date</label>
                            <input type="date" name="membership_expiry_date" class="form-control" value="{{ old('membership_expiry_date') }}">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Fitness Goal</label>
                            <input type="text" name="fitness_goal" class="form-control" value="{{ old('fitness_goal') }}" placeholder="e.g., Weight loss, Muscle gain">
                        </div>
                    </div>
                </div>

                <!-- Payment Details -->
                <div class="mb-4">
                    <h6 class="mb-3 pb-2 border-bottom">
                        <i class="bi bi-wallet2 me-2"></i> Payment Details
                    </h6>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Registration Fee</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" name="registration_fee" class="form-control" value="{{ old('registration_fee', 0) }}" min="0">
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Paid Amount</label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" name="paid_amount" class="form-control" value="{{ old('paid_amount', 0) }}" min="0">
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Payment Method</label>
                            <select name="payment_method" class="form-select">
                                <option value="cash">Cash</option>
                                <option value="upi">UPI</option>
                                <option value="card">Card</option>
                                <option value="online">Online</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Submit -->
                <div class="d-flex justify-content-between align-items-center">
                    <a href="{{ route('panel.members.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-x-circle me-2"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-person-plus me-2"></i> Add Member
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
