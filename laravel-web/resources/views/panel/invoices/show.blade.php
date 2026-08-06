@extends('panel.layouts.app')

@section('title', 'Invoice #' . $invoice->invoice_id)

@section('content')
@php
    $total = $invoice->items->sum('amount');
    $paid = $invoice->payments->sum('amount');
    $due = $total - $paid;
@endphp

<div class="row g-4">
    <!-- Invoice Details -->
    <div class="col-lg-8">
        {{-- FIX #6: Print Button --}}
        <div class="d-flex justify-content-between align-items-center mb-4">
            <a href="{{ route('panel.invoices.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2"></i> Back
            </a>
            <button class="btn btn-primary" onclick="window.print()">
                <i class="bi bi-printer me-2"></i> Print Invoice
            </button>
        </div>

        <!-- Invoice Header -->
        <div class="table-card mb-4" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border: none;">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div style="color: rgba(255,255,255,0.5); font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">Invoice</div>
                    <h2 style="color: white; font-family: 'Space Grotesk', sans-serif; font-weight: 700; margin: 8px 0 4px;">#{{ $invoice->invoice_id }}</h2>
                    <div style="color: rgba(255,255,255,0.6); font-size: 13px;">{{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d M Y') }}</div>
                </div>
                <div class="text-end">
                    @if($invoice->status === 'paid')
                        <span class="badge bg-success fs-6 px-3 py-2">✅ PAID</span>
                    @elseif($invoice->status === 'partial')
                        <span class="badge bg-warning fs-6 px-3 py-2">⏳ PARTIAL</span>
                    @else
                        <span class="badge bg-danger fs-6 px-3 py-2">❌ UNPAID</span>
                    @endif
                </div>
            </div>
        </div>

        <!-- Member Info -->
        <div class="table-card mb-4">
            <h6 class="mb-3"><i class="bi bi-person me-2"></i> Member Details</h6>
            <div class="row">
                <div class="col-md-6">
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span class="text-muted">Name</span>
                        <strong>{{ $invoice->user->name ?? 'Unknown' }}</strong>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span class="text-muted">Email</span>
                        <span>{{ $invoice->user->email ?? '-' }}</span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span class="text-muted">Phone</span>
                        <span>{{ $invoice->user->phone_number ?? '-' }}</span>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span class="text-muted">Due Date</span>
                        <span>{{ $invoice->invoice_due_date ? \Carbon\Carbon::parse($invoice->invoice_due_date)->format('d M Y') : '-' }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- FIX #6: Invoice Items with date range in description --}}
        <div class="table-card mb-4">
            <h6 class="mb-3"><i class="bi bi-list-check me-2"></i> Items</h6>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Description</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoice->items as $item)
                        <tr>
                            <td><strong>{{ $item->title }}</strong></td>
                            <td class="text-muted">
                                {{ $item->description ?? '-' }}
                                {{-- FIX #6: Show date range for renewal/classes --}}
                                @if($invoice->invoice_date && $invoice->invoice_due_date)
                                    <br><small style="color: var(--primary);">{{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d M Y') }} - {{ \Carbon\Carbon::parse($invoice->invoice_due_date)->format('d M Y') }}</small>
                                @endif
                            </td>
                            <td class="text-end fw-bold" style="font-family: 'Space Grotesk', sans-serif;">₹{{ number_format($item->amount) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr style="background: var(--bg);">
                            <td colspan="2" class="fw-bold">Total</td>
                            <td class="text-end fw-bold" style="font-family: 'Space Grotesk', sans-serif; font-size: 16px;">₹{{ number_format($total) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Payments -->
        <div class="table-card mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0"><i class="bi bi-credit-card me-2"></i> Payments</h6>
                @if($due > 0)
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addPaymentModal">
                        <i class="bi bi-plus-circle me-1"></i> Add Payment
                    </button>
                @endif
            </div>

            @if($invoice->payments->count() > 0)
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Notes</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoice->payments as $payment)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($payment->payment_date)->format('d M Y') }}</td>
                            <td><span class="badge bg-secondary">{{ ucfirst($payment->payment_type) }}</span></td>
                            <td class="text-muted">{{ $payment->notes ?? '-' }}</td>
                            <td class="text-end fw-bold text-success" style="font-family: 'Space Grotesk', sans-serif;">₹{{ number_format($payment->amount) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div class="text-center py-4">
                <i class="bi bi-credit-card fs-1 d-block mb-2" style="opacity: 0.2;"></i>
                <p class="text-muted mb-0">No payments recorded</p>
            </div>
            @endif
        </div>

        @if($invoice->notes)
        <div class="table-card">
            <h6 class="mb-2"><i class="bi bi-sticky me-2"></i> Notes</h6>
            <p class="text-muted mb-0">{{ $invoice->notes }}</p>
        </div>
        @endif
    </div>

    <!-- Sidebar -->
    <div class="col-lg-4">
        <!-- Payment Summary -->
        <div class="table-card mb-4" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border: none;">
            <h6 style="color: rgba(255,255,255,0.5); font-size: 12px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 16px;">Payment Summary</h6>
            <div class="d-flex justify-content-between mb-3">
                <span style="color: rgba(255,255,255,0.6);">Total Amount</span>
                <span style="color: white; font-family: 'Space Grotesk', sans-serif; font-weight: 700;">₹{{ number_format($total) }}</span>
            </div>
            <div class="d-flex justify-content-between mb-3">
                <span style="color: rgba(255,255,255,0.6);">Paid Amount</span>
                <span style="color: #16c784; font-family: 'Space Grotesk', sans-serif; font-weight: 700;">₹{{ number_format($paid) }}</span>
            </div>
            <div style="height: 1px; background: rgba(255,255,255,0.1); margin: 12px 0;"></div>
            <div class="d-flex justify-content-between">
                <span style="color: rgba(255,255,255,0.8); font-weight: 600;">Due Amount</span>
                <span style="color: {{ $due > 0 ? '#ff4d4f' : '#16c784' }}; font-family: 'Space Grotesk', sans-serif; font-weight: 700; font-size: 20px;">₹{{ number_format($due) }}</span>
            </div>
            @if($due > 0)
            <button class="btn btn-primary w-100 mt-3" data-bs-toggle="modal" data-bs-target="#addPaymentModal">
                <i class="bi bi-plus-circle me-2"></i> Add Payment
            </button>
            @endif
        </div>

        <!-- Status Card -->
        <div class="table-card mb-4">
            <div class="text-center">
                @if($due <= 0)
                    <div style="width: 64px; height: 64px; border-radius: 50%; background: rgba(22, 199, 132, 0.1); display: flex; align-items: center; justify-content: center; margin: 0 auto 12px;">
                        <i class="bi bi-check-circle" style="font-size: 32px; color: var(--success);"></i>
                    </div>
                    <h6 class="text-success">Fully Paid</h6>
                    <p class="text-muted mb-0" style="font-size: 13px;">All payments received</p>
                @else
                    <div style="width: 64px; height: 64px; border-radius: 50%; background: rgba(255, 77, 79, 0.1); display: flex; align-items: center; justify-content: center; margin: 0 auto 12px;">
                        <i class="bi bi-clock" style="font-size: 32px; color: var(--danger);"></i>
                    </div>
                    <h6 class="text-danger">Payment Pending</h6>
                    <p class="text-muted mb-0" style="font-size: 13px;">₹{{ number_format($due) }} remaining</p>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Add Payment Modal -->
@if($due > 0)
<div class="modal fade" id="addPaymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius: 20px;">
            <form action="{{ route('panel.invoices.addPayment', $invoice->id) }}" method="POST">
                @csrf
                <div style="background: linear-gradient(135deg, #0f172a, #1e293b); padding: 20px; border-radius: 20px 20px 0 0;">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0" style="color: white;"><i class="bi bi-credit-card me-2"></i> Add Payment</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info mb-3">
                        <i class="bi bi-info-circle me-2"></i>
                        Due amount: <strong>₹{{ number_format($due) }}</strong>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount (₹) *</label>
                        <div class="input-group">
                            <span class="input-group-text">₹</span>
                            <input type="number" name="amount" class="form-control" min="1" max="{{ $due }}" value="{{ $due }}" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment Method</label>
                        <select name="payment_type" class="form-select">
                            <option value="cash">Cash</option>
                            <option value="upi">UPI</option>
                            <option value="card">Card</option>
                            <option value="online">Online</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <input type="text" name="notes" class="form-control" placeholder="Optional notes...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-2"></i> Add Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

{{-- Print Styles --}}
<style>
    @media print {
        .sidebar, .top-bar, .btn, .toast-container { display: none !important; }
        .main-content { margin: 0 !important; padding: 20px !important; }
        .table-card { box-shadow: none !important; border: 1px solid #ddd !important; break-inside: avoid; }
    }
</style>
@endsection
