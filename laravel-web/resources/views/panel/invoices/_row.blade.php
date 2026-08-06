@php
    if (!isset($total)) $total = $invoice->items->sum('amount');
    if (!isset($paid)) $paid = $invoice->payments->sum('amount');
    if (!isset($due)) $due = max(0, $total - $paid);
    if (!isset($statusHtml)) {
        $statusHtml = match($invoice->status ?? 'unpaid') {
            'paid' => '<span class="badge bg-success">PAID</span>',
            'partial' => '<span class="badge bg-warning">PARTIAL</span>',
            default => '<span class="badge bg-danger">UNPAID</span>',
        };
    }
@endphp

<tr class="invoice-row" 
    data-member="{{ strtolower($invoice->user->name ?? '') }}"
    data-invoice="{{ $invoice->invoice_id }}"
    data-status="{{ $invoice->status }}">
    <td><strong>#{{ $invoice->invoice_id }}</strong></td>
    <td>
        <div>
            <div class="fw-semibold">{{ $invoice->user->name ?? 'N/A' }}</div>
            <small class="text-muted">{{ $invoice->user->phone_number ?? '' }}</small>
        </div>
    </td>
    <td>{{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d M Y') }}</td>
    <td>₹{{ number_format($total) }}</td>
    <td class="text-success">₹{{ number_format($paid) }}</td>
    <td class="{{ $due > 0 ? 'text-danger' : 'text-muted' }}">₹{{ number_format($due) }}</td>
    <td>{!! $statusHtml !!}</td>
    <td class="text-end" style="width: 110px; white-space: nowrap;">
        <div class="btn-group btn-group-sm" role="group">
            <a href="{{ route('panel.invoices.show', $invoice->id) }}" 
               class="btn btn-outline-primary">
                <i class="bi bi-eye"></i>
            </a>
            <!-- Temporarily commented: Delete option -->
            <!--
            <button class="btn btn-outline-danger delete-invoice-btn" 
                    data-id="{{ $invoice->id }}" 
                    data-invoice="#{{ $invoice->invoice_id }}">
                <i class="bi bi-trash3"></i>
            </button>
            -->
        </div>
    </td>
</tr>