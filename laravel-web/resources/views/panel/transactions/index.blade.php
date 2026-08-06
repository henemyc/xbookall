@extends('panel.layouts.app')

@section('title', 'Transactions')

@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="bi bi-arrow-left-right me-2"></i> Transactions
        </h5>
        <div class="d-flex gap-2">
            <div class="d-flex gap-2" id="monthYearFilter">
                <select id="filterMonth" class="form-select form-select-sm" style="width: 120px;">
                    @for($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" {{ $m == ($month ?? date('m')) ? 'selected' : '' }}>
                            {{ \Carbon\Carbon::create(2024, $m, 1)->format('F') }}
                        </option>
                    @endfor
                </select>
                <select id="filterYear" class="form-select form-select-sm" style="width: 90px;">
                    @for($y = date('Y'); $y >= 2020; $y--)
                        <option value="{{ $y }}" {{ $y == ($year ?? date('Y')) ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </div>
        </div>
    </div>

    <div class="card-body">
        <!-- Summary -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="p-3 rounded border" style="background:#f0fdf4;">
                    <div class="text-muted small">Total Income</div>
                    <div class="fw-bold fs-4 text-success" id="totalIncome">₹0</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3 rounded border" style="background:#fef2f2;">
                    <div class="text-muted small">Total Expense</div>
                    <div class="fw-bold fs-4 text-danger" id="totalExpense">₹0</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3 rounded border" style="background:#f8fafc;">
                    <div class="text-muted small">Net Balance</div>
                    <div class="fw-bold fs-4" id="netBalance">₹0</div>
                </div>
            </div>
        </div>

        <!-- Search -->
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <div class="position-relative">
                    <input type="text" id="transactionSearch" class="form-control" placeholder="🔍 Search transactions...">
                    <div id="searchSpinner" class="position-absolute top-50 end-0 translate-middle-y me-3 d-none">
                        <span class="spinner-border spinner-border-sm text-secondary"></span>
                    </div>
                </div>
            </div>
            <div class="col-md-6 text-end">
                <span class="badge bg-light text-dark" id="transactionCount">0 transactions</span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Type</th>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Member</th>
                        <th>Method</th>
                        <th class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody id="transactionsTableBody">
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">Loading transactions...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-center mt-3" id="transactionPagination" style="display:none;">
            <button id="loadMoreTransactionsBtn" class="btn btn-outline-primary btn-sm">Load More</button>
            <div id="loadTransactionsSpinner" class="d-none ms-2">
                <span class="spinner-border spinner-border-sm text-primary"></span>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    let currentPage = 1;
    let isLoadingTransactions = false;

    const searchInput = document.getElementById('transactionSearch');
    const searchSpinner = document.getElementById('searchSpinner');
    const tableBody = document.getElementById('transactionsTableBody');

    function filterTransactionsLive() {
        const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
        document.querySelectorAll('#transactionsTableBody .transaction-row').forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(query) ? '' : 'none';
        });
        updateTransactionCount();
    }

    function updateTransactionCount() {
        const visible = document.querySelectorAll('#transactionsTableBody .transaction-row:not([style*="none"])').length;
        const countEl = document.getElementById('transactionCount');
        if (countEl) countEl.textContent = `${visible} transactions`;
    }

    async function loadTransactions(page = 1, append = false) {
        if (isLoadingTransactions) return;
        isLoadingTransactions = true;

        const month = document.getElementById('filterMonth')?.value || '1';
        const year = document.getElementById('filterYear')?.value || new Date().getFullYear();

        const spinner = document.getElementById('loadTransactionsSpinner');
        const loadBtn = document.getElementById('loadMoreTransactionsBtn');

        if (spinner) spinner.classList.remove('d-none');
        if (loadBtn) loadBtn.disabled = true;

        try {
            const res = await fetch(`/panel/transactions?page=${page}&month=${month}&year=${year}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            });
            const data = await res.json();

            if (data.success && tableBody) {
                if (!append) tableBody.innerHTML = '';

                if (data.html) tableBody.insertAdjacentHTML('beforeend', data.html);

                if (data.summary) {
                    const incomeEl = document.getElementById('totalIncome');
                    const expenseEl = document.getElementById('totalExpense');
                    const netEl = document.getElementById('netBalance');

                    if (incomeEl) incomeEl.textContent = '₹' + Number(data.summary.income || 0).toLocaleString();
                    if (expenseEl) expenseEl.textContent = '₹' + Number(data.summary.expense || 0).toLocaleString();
                    if (netEl) {
                        netEl.textContent = '₹' + Math.abs(data.summary.net || 0).toLocaleString();
                        netEl.style.color = (data.summary.net || 0) >= 0 ? '#16c784' : '#ff4d4f';
                    }
                }

                const countEl = document.getElementById('transactionCount');
                if (countEl) countEl.textContent = `${data.total || 0} transactions`;

                currentPage = data.current_page || page;

                const pag = document.getElementById('transactionPagination');
                if (pag) pag.style.display = data.has_more ? '' : 'none';
            }
        } catch (e) {
            console.error(e);
            if (window.showToast) window.showToast('Failed to load transactions', 'error');
        } finally {
            isLoadingTransactions = false;
            if (spinner) spinner.classList.add('d-none');
            if (loadBtn) loadBtn.disabled = false;
        }
    }

    function attachLiveFilterToNewRows() {
        const q = searchInput ? searchInput.value.toLowerCase().trim() : '';
        if (!q) return;
        document.querySelectorAll('#transactionsTableBody .transaction-row').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    }

    function attachFilterListeners() {
        ['filterMonth', 'filterYear'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.addEventListener('change', () => { currentPage = 1; loadTransactions(1, false); });
        });
    }

    const loadMore = document.getElementById('loadMoreTransactionsBtn');
    if (loadMore) loadMore.addEventListener('click', () => loadTransactions(currentPage + 1, true));

    if (searchInput) {
        searchInput.addEventListener('input', () => {
            clearTimeout(searchTimeout);
            if (searchSpinner) searchSpinner.classList.remove('d-none');
            searchTimeout = setTimeout(() => {
                filterTransactionsLive();
                if (searchSpinner) searchSpinner.classList.add('d-none');
            }, 280);
        });
    }

    window.addEventListener('scroll', () => {
        const pag = document.getElementById('transactionPagination');
        if (!pag || pag.style.display === 'none' || isLoadingTransactions) return;
        const rect = pag.getBoundingClientRect();
        if (rect.top < window.innerHeight + 120) {
            loadTransactions(currentPage + 1, true);
        }
    });

    document.addEventListener('DOMContentLoaded', () => {
        attachFilterListeners();
        loadTransactions(1, false);
    });
</script>
@endpush