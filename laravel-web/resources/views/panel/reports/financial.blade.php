@extends('panel.layouts.app')

@section('title', 'Financial Report')

@section('content')
<div class="table-card mb-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="mb-0">Financial Report</h5>
        <a href="{{ route('panel.reports.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-2"></i> Back to Reports
        </a>
    </div>

    <!-- Month Selector -->
    <form action="{{ route('panel.reports.financial') }}" method="GET" class="mb-4">
        <div class="row">
            <div class="col-md-3">
                <select name="month" class="form-select">
                    @for($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" {{ $m == $month ? 'selected' : '' }}>
                            {{ \Carbon\Carbon::create(2024, $m)->format('F') }}
                        </option>
                    @endfor
                </select>
            </div>
            <div class="col-md-3">
                <select name="year" class="form-select">
                    @for($y = date('Y'); $y >= 2020; $y--)
                        <option value="{{ $y }}" {{ $y == $year ? 'selected' : '' }}">{{ $y }}</option>
                    @endfor
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-filter me-2"></i> Filter
                </button>
            </div>
        </div>
    </form>

    <!-- Summary Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="icon" style="background: rgba(22, 199, 132, 0.1); color: #16c784;">
                    <i class="bi bi-arrow-up"></i>
                </div>
                <div class="value">₹{{ number_format($income->sum('amount')) }}</div>
                <div class="label">Total Income</div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="stat-card">
                <div class="icon" style="background: rgba(255, 77, 79, 0.1); color: #ff4d4f;">
                    <i class="bi bi-arrow-down"></i>
                </div>
                <div class="value">₹{{ number_format($expenses->sum('amount')) }}</div>
                <div class="label">Total Expenses</div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="stat-card">
                <div class="icon" style="background: rgba(59, 158, 255, 0.1); color: #3b9eff;">
                    <i class="bi bi-wallet"></i>
                </div>
                <div class="value">₹{{ number_format($income->sum('amount') - $expenses->sum('amount')) }}</div>
                <div class="label">Net Balance</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Income List -->
    <div class="col-md-6">
        <div class="table-card">
            <h6 class="mb-3 text-success">
                <i class="bi bi-arrow-up-circle me-2"></i> Income ({{ $income->count() }})
            </h6>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Invoice</th>
                            <th>Amount</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($income as $payment)
                        <tr>
                            <td>{{ $payment->invoice->user->name ?? 'Unknown' }}</td>
                            <td>INV #{{ $payment->invoice->invoice_id ?? '-' }}</td>
                            <td class="text-success fw-bold">₹{{ number_format($payment->amount) }}</td>
                            <td>{{ \Carbon\Carbon::parse($payment->payment_date)->format('d M') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center py-3">No income this month</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Expenses List -->
    <div class="col-md-6">
        <div class="table-card">
            <h6 class="mb-3 text-danger">
                <i class="bi bi-arrow-down-circle me-2"></i> Expenses ({{ $expenses->count() }})
            </h6>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Amount</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($expenses as $expense)
                        <tr>
                            <td>{{ $expense->title ?? 'Expense' }}</td>
                            <td class="text-danger fw-bold">₹{{ number_format($expense->amount) }}</td>
                            <td>{{ \Carbon\Carbon::parse($expense->date)->format('d M') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="3" class="text-center py-3">No expenses this month</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
