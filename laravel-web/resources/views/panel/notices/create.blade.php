@extends('panel.layouts.app')

@section('title', 'Add Notice')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="table-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="mb-0">Add New Notice</h5>
                <a href="{{ route('panel.notices.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i> Back
                </a>
            </div>
            
            <form action="{{ route('panel.notices.store') }}" method="POST">
                @csrf
                
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Title *</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="5"></textarea>
                    </div>
                    
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-2"></i> Create Notice
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
