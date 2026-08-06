@php
    $type = $txn['type'] ?? 'expense';
    $isIncome = $type === 'income';
@endphp

<tr class="transaction-row">
    <td>
        @if($isIncome)
            <span class="badge bg-success"><i class="bi bi-arrow-up me-1"></i> Income</span>
        @else
            <span class="badge bg-danger"><i class="bi bi-arrow-down me-1"></i> Expense</span>
        @endif
    </td>
    <td>{{ \Carbon\Carbon::parse($txn['date'])->format('d M Y') }}</td>
    <td>{{ $txn['description'] ?? '-' }}</td>
    <td>{{ $txn['member'] ?? '-' }}</td>
    <td><span class="text-muted">{{ $txn['method'] ?? '-' }}</span></td>
    <td class="text-end fw-bold {{ $isIncome ? 'text-success' : 'text-danger' }}">
        {{ $isIncome ? '+' : '-' }}₹{{ number_format($txn['amount'] ?? 0) }}
    </td>
</tr>