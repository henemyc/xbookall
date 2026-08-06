@extends('admin.layouts.app')

@section('title', 'Subscription Plans')

@section('content')
<div class="table-card">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="mb-0">Subscription Plans</h5>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPlanModal">
            <i class="bi bi-plus-circle me-2"></i> Add Plan
        </button>
    </div>

    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Plan Name</th>
                    <th>Amount</th>
                    <th>Interval</th>
                    <th>User Limit</th>
                    <th>Trainer Limit</th>
                    <th>Member Limit</th>
                    <th>Gyms</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($plans as $plan)
                <tr>
                    <td>{{ $plan->id }}</td>
                    <td><strong>{{ $plan->title }}</strong></td>
                    <td>₹{{ number_format($plan->package_amount) }}</td>
                    <td><span class="badge bg-primary">{{ ucfirst($plan->interval) }}</span></td>
                    <td>{{ $plan->user_limit ?: 'Unlimited' }}</td>
                    <td>{{ $plan->trainer_limit ?: 'Unlimited' }}</td>
                    <td>{{ $plan->trainee_limit ?: 'Unlimited' }}</td>
                    <td><span class="badge bg-secondary">{{ $plan->gym_count }}</span></td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editPlanModal{{ $plan->id }}">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <form action="{{ route('admin.plans.destroy', $plan->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this plan?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>

                <!-- Edit Modal -->
                <div class="modal fade" id="editPlanModal{{ $plan->id }}" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form action="{{ route('admin.plans.update', $plan->id) }}" method="POST">
                                @csrf
                                @method('PUT')
                                <div class="modal-header">
                                    <h5 class="modal-title">Edit Plan</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label">Plan Name</label>
                                        <input type="text" name="title" class="form-control" value="{{ $plan->title }}" required>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col">
                                            <label class="form-label">Amount (₹)</label>
                                            <input type="number" name="package_amount" class="form-control" value="{{ $plan->package_amount }}" required>
                                        </div>
                                        <div class="col">
                                            <label class="form-label">Interval</label>
                                            <select name="interval" class="form-select">
                                                <option value="weekly" {{ $plan->interval === 'weekly' ? 'selected' : '' }}>Weekly</option>
                                                <option value="monthly" {{ $plan->interval === 'monthly' ? 'selected' : '' }}>Monthly</option>
                                                <option value="quarterly" {{ $plan->interval === 'quarterly' ? 'selected' : '' }}>Quarterly</option>
                                                <option value="half-yearly" {{ $plan->interval === 'half-yearly' ? 'selected' : '' }}>Half Yearly</option>
                                                <option value="yearly" {{ $plan->interval === 'yearly' ? 'selected' : '' }}>Yearly</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col">
                                            <label class="form-label">User Limit</label>
                                            <input type="number" name="user_limit" class="form-control" value="{{ $plan->user_limit }}" placeholder="0 = Unlimited">
                                        </div>
                                        <div class="col">
                                            <label class="form-label">Trainer Limit</label>
                                            <input type="number" name="trainer_limit" class="form-control" value="{{ $plan->trainer_limit }}" placeholder="0 = Unlimited">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Member Limit</label>
                                        <input type="number" name="trainee_limit" class="form-control" value="{{ $plan->trainee_limit }}" placeholder="0 = Unlimited">
                                    </div>
                                    <div class="form-check">
                                        <input type="checkbox" name="enabled_logged_history" class="form-check-input" {{ $plan->enabled_logged_history ? 'checked' : '' }}>
                                        <label class="form-check-label">Enable Activity History</label>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<!-- Add Plan Modal -->
<div class="modal fade" id="addPlanModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('admin.plans.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add New Plan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Plan Name</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="row mb-3">
                        <div class="col">
                            <label class="form-label">Amount (₹)</label>
                            <input type="number" name="package_amount" class="form-control" required>
                        </div>
                        <div class="col">
                            <label class="form-label">Interval</label>
                            <select name="interval" class="form-select">
                                <option value="monthly">Monthly</option>
                                <option value="quarterly">Quarterly</option>
                                <option value="half-yearly">Half Yearly</option>
                                <option value="yearly">Yearly</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col">
                            <label class="form-label">User Limit</label>
                            <input type="number" name="user_limit" class="form-control" value="0" placeholder="0 = Unlimited">
                        </div>
                        <div class="col">
                            <label class="form-label">Trainer Limit</label>
                            <input type="number" name="trainer_limit" class="form-control" value="0" placeholder="0 = Unlimited">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Member Limit</label>
                        <input type="number" name="trainee_limit" class="form-control" value="0" placeholder="0 = Unlimited">
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="enabled_logged_history" class="form-check-input">
                        <label class="form-check-label">Enable Activity History</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Plan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
