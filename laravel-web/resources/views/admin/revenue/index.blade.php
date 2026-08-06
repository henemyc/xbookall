@extends('admin.layouts.app')

@section('title', 'Revenue')

@section('content')
<!-- Stats Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="icon" style="background: rgba(22, 199, 132, 0.1); color: #16c784;">
                <i class="bi bi-currency-rupee"></i>
            </div>
            <div class="value">₹{{ number_format($totalRevenue) }}</div>
            <div class="label">Total Revenue</div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="stat-card">
            <div class="icon" style="background: rgba(59, 158, 255, 0.1); color: #3b9eff;">
                <i class="bi bi-calendar-check"></i>
            </div>
            <div class="value">₹{{ number_format($thisMonth) }}</div>
            <div class="label">This Month</div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="stat-card">
            <div class="icon" style="background: rgba(255, 167, 38, 0.1); color: #ffa726;">
                <i class="bi bi-calendar-minus"></i>
            </div>
            <div class="value">₹{{ number_format($lastMonth) }}</div>
            <div class="label">Last Month</div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="stat-card">
            <div class="icon" style="background: rgba(139, 92, 246, 0.1); color: #8b5cf6;">
                <i class="bi bi-graph-up"></i>
            </div>
            <div class="value">
                @if($lastMonth > 0)
                    {{ round((($thisMonth - $lastMonth) / $lastMonth) * 100) }}%
                @else
                    N/A
                @endif
            </div>
            <div class="label">Growth</div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Monthly Revenue Chart -->
    <div class="col-md-8">
        <div class="table-card">
            <h6 class="mb-3">Monthly Revenue (Last 12 Months)</h6>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($monthlyRevenue as $month)
                        <tr>
                            <td>{{ \Carbon\Carbon::create($month->year, $month->month)->format('M Y') }}</td>
                            <td><strong>₹{{ number_format($month->total) }}</strong></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Orders by Status -->
    <div class="col-md-4">
        <div class="table-card">
            <h6 class="mb-3">Orders by Status</h6>
            @foreach($ordersByStatus as $status)
            <div class="d-flex justify-content-between align-items-center mb-3 p-3 bg-light rounded">
                <div>
                    @if($status->status === 'PAID')
                        <span class="badge-status" style="background: rgba(22, 199, 132, 0.1); color: #16c784;">{{ $status->status }}</span>
                    @elseif($status->status === 'CREATED')
                        <span class="badge-status" style="background: rgba(59, 158, 255, 0.1); color: #3b9eff;">{{ $status->status }}</span>
                    @else
                        <span class="badge-status" style="background: rgba(255, 77, 79, 0.1); color: #ff4d4f;">{{ $status->status }}</span>
                    @endif
                    <div class="text-muted small mt-1">{{ $status->count }} orders</div>
                </div>
                <div class="fw-bold">₹{{ number_format($status->total) }}</div>
            </div>
            @endforeach
        </div>
    </div>
</div>

<!-- Recent Payments -->
<div class="table-card mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="mb-0">Recent Payments</h6>
        <a href="{{ route('admin.revenue.payments') }}" class="btn btn-sm btn-outline-primary">View All</a>
    </div>
    
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Gym</th>
                    <th>Plan</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                @foreach($recentPayments as $payment)
                <tr>
                    <td><code>{{ substr($payment->order_id, 0, 20) }}...</code></td>
                    <td>{{ $payment->parent->name ?? 'Unknown' }}</td>
                    <td>{{ $payment->plan->title ?? '-' }}</td>
                    <td><strong>₹{{ number_format($payment->amount) }}</strong></td>
                    <td>
                        <span class="badge-status" style="background: rgba(22, 199, 132, 0.1); color: #16c784;">Paid</span>
                    </td>
                    <td>{{ $payment->updated_at->format('d M Y H:i') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
