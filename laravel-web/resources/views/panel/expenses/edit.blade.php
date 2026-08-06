@extends('panel.layouts.app')

@section('title', 'Edit Expense')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="table-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="mb-0">Edit Expense</h5>
                <a href="{{ route('panel.expenses.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i> Back
                </a>
            </div>
            
            <form action="{{ route('panel.expenses.update', $expense->id) }}" method="POST">
                @csrf
                @method('PUT')
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Title *</label>
                        <input type="text" name="title" class="form-control" value="{{ $expense->title }}" required>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Amount *</label>
                        <input type="number" name="amount" class="form-control" value="{{ $expense->amount }}" min="0" required>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Date *</label>
                        <input type="date" name="date" class="form-control" value="{{ $expense->date }}" required>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Type</label>
                        <select name="expense_type" class="form-select">
                            <option value="">Select Type</option>
                            @foreach($types as $type)
                                <option value="{{ $type->id }}" {{ $expense->expense_type == $type->id ? 'selected' : '' }}>
                                    {{ $type->title }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="3">{{ $expense->notes }}</textarea>
                    </div>
                    
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-2"></i> Save Changes
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
