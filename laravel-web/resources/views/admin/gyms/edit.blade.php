@extends('admin.layouts.app')

@section('title', 'Edit Gym')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="table-card">
            <h5 class="mb-4">Edit Gym: {{ $gym->name }}</h5>
            
            <form action="{{ route('admin.gyms.update', $gym->id) }}" method="POST">
                @csrf
                @method('PUT')
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Owner Name *</label>
                        <input type="text" name="name" class="form-control" value="{{ $gym->name }}" required>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-control" value="{{ $gym->email }}" required>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone_number" class="form-control" value="{{ $gym->phone_number }}">
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Gym Name</label>
                        <input type="text" name="company_name" class="form-control" value="{{ $companyName }}">
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Gym Phone</label>
                        <input type="text" name="company_phone" class="form-control" value="{{ $companyPhone }}">
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Gym Email</label>
                        <input type="email" name="company_email" class="form-control" value="{{ $companyEmail }}">
                    </div>
                    
                    <div class="col-12">
                        <label class="form-label">Address</label>
                        <textarea name="company_address" class="form-control" rows="3">{{ $companyAddress }}</textarea>
                    </div>
                    
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-2"></i> Save Changes
                        </button>
                        <a href="{{ route('admin.gyms.show', $gym->id) }}" class="btn btn-secondary">
                            Cancel
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
