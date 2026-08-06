@extends('admin.layouts.app')

@section('title', 'All Payments')

@section('content')
<div class="table-card">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="mb-0">All Payments</h5>
        <a href="{{ route('admin.revenue.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-2"></i> Back to Revenue
        </a>
    </div>

    <!-- Filters -->
    <div class="row mb-4">
        <div class="col-md-6">
            <form action="{{ route('admin.revenue.payments') }}" method="GET">
                <div class="input-group">
                    <input type="text" name="search" class="form-control" placeholder="Search by gym name or email..." value="{{ $search }}">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </form>
        </div>
        <div class="col-md-6 text-end">
            <div class="btn-group">
                <a href="{{ route('admin.revenue.payments') }}" class="btn btn-outline-secondary {{ $status === '' ? 'active' : '' }}">All</a>
                <a href="{{ route('admin.revenue.payments', ['status' => 'PAID']) }}" class="btn btn-outline-success {{ $status === 'PAID' ? 'active' : '' }}">Paid</a>
                <a href="{{ route('admin.revenue.payments', ['status' => 'CREATED']) }}" class="btn btn-outline-warning {{ $status === 'CREATED' ? 'active' : '' }}">Pending</a>
                <a href="{{ route('admin.revenue.payments', ['status' => 'FAILED']) }}" class="btn btn-outline-danger {{ $status === 'FAILED' ? 'active' : '' }}">Failed</a>
            </div>
        </div>
    </div>

    <!-- Payments Table -->
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Gym</th>
                    <th>Email</th>
                    <th>Plan</th>
                    <th>Amount</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                @forelse($payments as $payment)
                <tr>
                    <td><code>{{ substr($payment->order_id, 0, 25) }}</code></td>
                    <td><strong>{{ $payment->parent->name ?? 'Unknown' }}</strong></td>
                    <td>{{ $payment->parent->email ?? '-' }}</td>
                    <td>{{ $payment->plan->title ?? '-' }}</td>
                    <td><strong>₹{{ number_format($payment->amount) }}</strong></td>
                    <td><span class="badge bg-secondary">{{ ucfirst($payment->order_type) }}</span></td>
                    <td>
                        @if($payment->status === 'PAID')
                            <span class="badge-status" style="background: rgba(22, 199, 132, 0.1); color: #16c784;">Paid</span>
                        @elseif($payment->status === 'CREATED')
                            <span class="badge-status" style="background: rgba(59, 158, 255, 0.1); color: #3b9eff;">Pending</span>
                        @elseif($payment->status === 'FAILED')
                            <span class="badge-status" style="background: rgba(255, 77, 79, 0.1); color: #ff4d4f;">Failed</span>
                        @else
                            <span class="badge-status" style="background: rgba(108, 117, 125, 0.1); color: #6c757d;">{{ $payment->status }}</span>
                        @endif
                    </td>
                    <td>{{ $payment->created_at->format('d M Y H:i') }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center py-4">No payments found</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="d-flex justify-content-center">
        {{ $payments->appends(['status' => $status, 'search' => $search])->links() }}
    </div>
</div>
@endsection
