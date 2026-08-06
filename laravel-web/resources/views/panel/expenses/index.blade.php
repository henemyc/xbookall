@extends('panel.layouts.app')

@section('title', 'Expenses')

@section('content')
<div class="table-card">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h5 class="mb-1"><i class="bi bi-wallet2 me-2" style="color: var(--danger);"></i> Expenses</h5>
            <small class="text-muted"><span id="expenseCount">{{ $expenses->count() }}</span> expenses this month</small>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
            <i class="bi bi-plus-circle me-2"></i> Add Expense
        </button>
    </div>

    <!-- Search + Month Filter -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="search-input position-relative">
                <i class="bi bi-search search-icon"></i>
                <input type="text" id="expenseSearch" class="form-control" placeholder="Search expenses...">
                <div id="expenseSearchSpinner" class="position-absolute top-50 end-0 translate-middle-y me-2 d-none">
                    <span class="spinner-border spinner-border-sm text-secondary"></span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <select id="expenseTypeFilter" class="form-select">
                <option value="">All Types</option>
                @foreach($types ?? [] as $type)
                    <option value="{{ strtolower($type->title) }}">{{ $type->title }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <form action="{{ route('panel.expenses.index') }}" method="GET" id="monthForm">
                <div class="d-flex gap-2">
                    <select name="month" class="form-select" onchange="submitMonthFilter(this)">
                        @for($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}" {{ $m == $month ? 'selected' : '' }}>{{ \Carbon\Carbon::create(2024, $m, 1)->format('F') }}</option>
                        @endfor
                    </select>
                    <select name="year" class="form-select" onchange="submitMonthFilter(this)">
                        @for($y = date('Y'); $y >= 2020; $y--)
                            <option value="{{ $y }}" {{ $y == $year ? 'selected' : '' }}>{{ $y }}</option>
                        @endfor
                    </select>
                </div>
            </form>
        </div>
        <div class="col-md-2">
            <div class="p-2 rounded text-center" style="background: rgba(255, 77, 79, 0.1); border: 1px solid rgba(255, 77, 79, 0.2);">
                <div style="font-size: 11px; color: var(--text-secondary);">Total</div>
                <div class="fw-bold text-danger" style="font-family: 'Space Grotesk', sans-serif; font-size: 18px;">₹{{ number_format($total) }}</div>
            </div>
        </div>
    </div>

    <!-- Expenses List -->
    <div id="expenseList">
        @forelse($expenses as $expense)
        <div class="expense-item mb-3 p-3 rounded" style="background: var(--bg); border: 1px solid var(--border);" 
             data-id="{{ $expense->id }}"
             data-title="{{ strtolower($expense->title ?? '') }}" 
             data-type="{{ $expense->type ? strtolower($expense->type->title) : '' }}">
            <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center">
                    <div style="width: 44px; height: 44px; background: rgba(255, 77, 79, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-arrow-down-circle text-danger" style="font-size: 20px;"></i>
                    </div>
                    <div class="ms-3">
                        <div class="fw-bold" style="font-size: 14px;">{{ $expense->title }}</div>
                        <small class="text-muted">
                            {{ \Carbon\Carbon::parse($expense->date)->format('d M Y') }}
                            @if($expense->type) • {{ $expense->type->title }} @endif
                            @if($expense->notes) • {{ Str::limit($expense->notes, 30) }} @endif
                        </small>
                    </div>
                </div>
                <div class="d-flex align-items-center">
                    <span class="fw-bold me-3 expense-amount" style="font-family: 'Space Grotesk', sans-serif; font-size: 16px; color: var(--danger);">₹{{ number_format($expense->amount) }}</span>
                    
                    <button class="btn btn-sm btn-outline-primary me-1 edit-expense-btn"
                            data-id="{{ $expense->id }}"
                            data-title="{{ $expense->title }}"
                            data-amount="{{ $expense->amount }}"
                            data-date="{{ $expense->date->format('Y-m-d') }}"
                            data-notes="{{ $expense->notes }}"
                            data-type="{{ $expense->expense_type }}">
                        <i class="bi bi-pencil"></i>
                    </button>
                    
                    <button class="btn btn-sm btn-outline-danger delete-expense-btn" data-id="{{ $expense->id }}">
                        <i class="bi bi-trash3"></i>
                    </button>
                </div>
            </div>
        </div>
        @empty
        <div class="text-center py-5" id="noExpensesMsg">
            <i class="bi bi-wallet2 fs-1 d-block mb-3" style="opacity: 0.2;"></i>
            <p class="text-muted">No expenses this month</p>
        </div>
        @endforelse
    </div>
</div>

<!-- Add Expense Modal -->
<div class="modal fade" id="addExpenseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i> Add Expense</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Title *</label>
                        <input type="text" id="expTitle" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Amount (₹) *</label>
                        <input type="number" id="expAmount" class="form-control" min="0" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Date *</label>
                        <input type="date" id="expDate" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Type</label>
                        <select id="expType" class="form-select">
                            <option value="">Select</option>
                            @foreach($types ?? [] as $type)
                                <option value="{{ $type->id }}">{{ $type->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea id="expNotes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitExpense()">
                    <i class="bi bi-check-circle me-2"></i> Add Expense
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Expense Modal -->
<div class="modal fade" id="editExpenseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i> Edit Expense</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editExpId">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Title *</label>
                        <input type="text" id="editExpTitle" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Amount (₹) *</label>
                        <input type="number" id="editExpAmount" class="form-control" min="0" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Date *</label>
                        <input type="date" id="editExpDate" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Type</label>
                        <select id="editExpType" class="form-select">
                            <option value="">Select</option>
                            @foreach($types ?? [] as $type)
                                <option value="{{ $type->id }}">{{ $type->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea id="editExpNotes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="updateExpense()">
                    <i class="bi bi-save me-2"></i> Update Expense
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // ============================================
    // EXPENSE FILTER FUNCTIONS (must be global)
    // ============================================

    function filterExpenses() {
        const list = document.getElementById('expenseList');
        const search = document.getElementById('expenseSearch');
        const typeSel = document.getElementById('expenseTypeFilter');

        if (!list) return;

        list.style.opacity = '0.6';

        const q = search ? search.value.toLowerCase().trim() : '';
        const t = typeSel ? typeSel.value.toLowerCase().trim() : '';

        document.querySelectorAll('.expense-item').forEach(el => {
            const title = (el.dataset.title || '').toLowerCase();
            const type  = (el.dataset.type || '').toLowerCase();

            const okSearch = !q || title.includes(q);
            const okType   = !t || type === t;

            if (okSearch && okType) {
                el.style.display = '';
            } else {
                el.style.display = 'none';
            }
        });

        // Update both count and visible total to match current filter state
        if (typeof updateExpenseCountAndTotal === 'function') {
            updateExpenseCountAndTotal();
        } else {
            const countEl = document.getElementById('expenseCount');
            const visibleNow = document.querySelectorAll('.expense-item:not([style*="none"])').length;
            if (countEl) countEl.textContent = visibleNow;
        }

        setTimeout(() => {
            if (list) list.style.opacity = '1';
        }, 80);
    }

    // ============================================
    // INITIALIZE FILTERS
    // ============================================
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('expenseSearch');
        const searchSpinner = document.getElementById('expenseSearchSpinner');
        const typeFilter = document.getElementById('expenseTypeFilter');

        // Search input listener
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                if (searchSpinner) searchSpinner.classList.remove('d-none');
                searchInput.classList.add('is-loading');

                filterExpenses();

                setTimeout(function() {
                    if (searchSpinner) searchSpinner.classList.add('d-none');
                    searchInput.classList.remove('is-loading');
                }, 280);
            });
        }

        // Type filter listener - more reliable
        if (typeFilter) {
            // Remove any possible old inline handler
            typeFilter.removeAttribute('onchange');

            typeFilter.addEventListener('change', function() {
                this.disabled = true;
                this.style.opacity = '0.6';

                // Call filter
                filterExpenses();

                setTimeout(() => {
                    this.disabled = false;
                    this.style.opacity = '1';
                }, 180);
            });
        }

        // Run filter on initial load
        setTimeout(filterExpenses, 60);
    });

    // Month/Year filter (full reload)
    function submitMonthFilter(selectEl) {
        const form = document.getElementById('monthForm');
        if (!form) return;

        selectEl.disabled = true;
        const wrap = selectEl.closest('.d-flex') || form;
        if (wrap) wrap.style.opacity = '0.6';

        form.submit();
    }

    // ===================== AJAX EXPENSE =====================

    // Submit new expense (AJAX) + Spinner
    async function submitExpense() {
        const btn = document.querySelector('#addExpenseModal .btn-primary');
        const origText = btn ? btn.innerHTML : '';
        
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Adding...`;
        }

        const title = document.getElementById('expTitle').value.trim();
        const amount = document.getElementById('expAmount').value;
        const date = document.getElementById('expDate').value;
        const type = document.getElementById('expType').value;
        const notes = document.getElementById('expNotes').value.trim();

        if (!title || !amount || !date) {
            window.showToast('Title, Amount and Date are required', 'error');
            if (btn) { btn.disabled = false; btn.innerHTML = origText; }
            return;
        }

        const fd = new FormData();
        fd.append('title', title);
        fd.append('amount', amount);
        fd.append('date', date);
        if (type) fd.append('expense_type', type);
        if (notes) fd.append('notes', notes);

        try {
            const res = await fetch('/panel/expenses', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: fd
            });

            const data = await res.json();

            if (res.ok && data.success) {
                window.showToast(data.message || 'Expense added!', 'success');
                bootstrap.Modal.getInstance(document.getElementById('addExpenseModal')).hide();
                resetExpenseForm();

                if (data.expense) {
                    addExpenseCard(data.expense);
                } else {
                    location.reload();
                }
            } else {
                window.showToast(data.error || 'Failed to add expense', 'error');
            }
        } catch (e) {
            console.error(e);
            window.showToast('Network error', 'error');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = origText || `<i class="bi bi-check-circle me-2"></i> Add Expense`;
            }
        }
    }

    function resetExpenseForm() {
        document.getElementById('expTitle').value = '';
        document.getElementById('expAmount').value = '';
        document.getElementById('expDate').value = '{{ date('Y-m-d') }}';
        document.getElementById('expType').value = '';
        document.getElementById('expNotes').value = '';
    }

    // Open Edit Modal
    document.addEventListener('click', function(e) {
        const editBtn = e.target.closest('.edit-expense-btn');
        if (editBtn) {
            document.getElementById('editExpId').value = editBtn.dataset.id;
            document.getElementById('editExpTitle').value = editBtn.dataset.title;
            document.getElementById('editExpAmount').value = editBtn.dataset.amount;
            document.getElementById('editExpDate').value = editBtn.dataset.date;
            document.getElementById('editExpNotes').value = editBtn.dataset.notes || '';
            document.getElementById('editExpType').value = editBtn.dataset.type || '';

            new bootstrap.Modal(document.getElementById('editExpenseModal')).show();
        }
    });

    // Update Expense (AJAX) + Spinner
    async function updateExpense() {
        const btn = document.querySelector('#editExpenseModal .btn-primary');
        const origText = btn ? btn.innerHTML : '';
        
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span> Updating...`;
        }

        const id = document.getElementById('editExpId').value;
        const title = document.getElementById('editExpTitle').value.trim();
        const amount = document.getElementById('editExpAmount').value;
        const date = document.getElementById('editExpDate').value;
        const type = document.getElementById('editExpType').value;
        const notes = document.getElementById('editExpNotes').value.trim();

        if (!title || !amount || !date) {
            window.showToast('Title, Amount and Date are required', 'error');
            if (btn) { btn.disabled = false; btn.innerHTML = origText; }
            return;
        }

        const fd = new FormData();
        fd.append('_method', 'PUT');
        fd.append('title', title);
        fd.append('amount', amount);
        fd.append('date', date);
        if (type) fd.append('expense_type', type);
        if (notes) fd.append('notes', notes);

        try {
            const res = await fetch(`/panel/expenses/${id}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: fd
            });

            const data = await res.json();

            if (res.ok && data.success) {
                window.showToast('Expense updated!', 'success');
                bootstrap.Modal.getInstance(document.getElementById('editExpenseModal')).hide();

                updateExpenseCard(id, data.expense);
            } else {
                window.showToast(data.error || 'Update failed', 'error');
            }
        } catch (e) {
            window.showToast('Network error', 'error');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = origText || `<i class="bi bi-save me-2"></i> Update Expense`;
            }
        }
    }

    // Delete Expense (using global modal)
    function attachExpenseDeleteListeners() {
        document.querySelectorAll('.delete-expense-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.dataset.id;
                const card = this.closest('.expense-item');
                const title = card?.querySelector('.fw-bold')?.textContent || 'this expense';

                document.getElementById('deleteConfirmMessage').textContent = `Delete "${title}"?`;

                const modalEl = document.getElementById('deleteConfirmModal');
                const modal = new bootstrap.Modal(modalEl);
                const confirmBtn = document.getElementById('deleteConfirmBtn');

                const freshBtn = confirmBtn.cloneNode(true);
                confirmBtn.parentNode.replaceChild(freshBtn, confirmBtn);

                freshBtn.onclick = async () => {
                    modal.hide();

                    // Spinner on delete button
                    const origBtnHtml = this.innerHTML;
                    this.disabled = true;
                    this.innerHTML = `<span class="spinner-border spinner-border-sm"></span>`;

                    try {
                        const res = await fetch(`/panel/expenses/${id}`, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json'
                            }
                        });

                        const data = await res.json();

                        if (data.success) {
                            if (card) card.remove();
                            window.showToast('Expense deleted', 'success');
                            updateExpenseCountAndTotal();
                            // Re-apply filters after removal
                            if (typeof filterExpenses === 'function') setTimeout(filterExpenses, 20);
                        } else {
                            window.showToast(data.error || 'Delete failed', 'error');
                            this.disabled = false;
                            this.innerHTML = origBtnHtml;
                        }
                    } catch (e) {
                        window.showToast('Network error', 'error');
                        this.disabled = false;
                        this.innerHTML = origBtnHtml;
                    }
                };

                modal.show();
            });
        });
    }

    function addExpenseCard(exp) {
        const list = document.getElementById('expenseList');
        const noMsg = document.getElementById('noExpensesMsg');
        if (noMsg) noMsg.remove();

        const div = document.createElement('div');
        div.className = 'expense-item mb-3 p-3 rounded';
        div.style.cssText = 'background: var(--bg); border: 1px solid var(--border);';
        div.dataset.id = exp.id;
        div.dataset.title = (exp.title || '').toLowerCase();
        div.dataset.type = (exp.type_title || '').toLowerCase();

        div.innerHTML = `
            <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center">
                    <div style="width: 44px; height: 44px; background: rgba(255, 77, 79, 0.1); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-arrow-down-circle text-danger" style="font-size: 20px;"></i>
                    </div>
                    <div class="ms-3">
                        <div class="fw-bold" style="font-size: 14px;">${exp.title}</div>
                        <small class="text-muted">
                            ${exp.formatted_date || exp.date} 
                            ${exp.type_title ? '• ' + exp.type_title : ''}
                            ${exp.notes ? '• ' + exp.notes.substring(0,30) : ''}
                        </small>
                    </div>
                </div>
                <div class="d-flex align-items-center">
                    <span class="fw-bold me-3 expense-amount" style="font-family: 'Space Grotesk', sans-serif; font-size: 16px; color: var(--danger);">₹${Number(exp.amount).toLocaleString()}</span>
                    
                    <button class="btn btn-sm btn-outline-primary me-1 edit-expense-btn"
                            data-id="${exp.id}"
                            data-title="${exp.title}"
                            data-amount="${exp.amount}"
                            data-date="${exp.date}"
                            data-notes="${exp.notes || ''}"
                            data-type="${exp.expense_type || ''}">
                        <i class="bi bi-pencil"></i>
                    </button>
                    
                    <button class="btn btn-sm btn-outline-danger delete-expense-btn" data-id="${exp.id}">
                        <i class="bi bi-trash3"></i>
                    </button>
                </div>
            </div>
        `;

        list.prepend(div);
        attachExpenseDeleteListeners();
        updateExpenseCountAndTotal();

        // Re-apply current filter after adding new expense
        if (typeof filterExpenses === 'function') {
            setTimeout(filterExpenses, 50);
        }
    }

    function updateExpenseCard(id, exp) {
        const card = document.querySelector(`.expense-item[data-id="${id}"]`);
        if (!card) return;

        card.querySelector('.fw-bold').textContent = exp.title;
        card.querySelector('.expense-amount').textContent = '₹' + Number(exp.amount).toLocaleString();

        const small = card.querySelector('small');
        if (small) {
            small.innerHTML = `
                ${exp.formatted_date || exp.date} 
                ${exp.type_title ? '• ' + exp.type_title : ''}
                ${exp.notes ? '• ' + exp.notes.substring(0,30) : ''}
            `;
        }

        // Update dataset
        card.dataset.title = (exp.title || '').toLowerCase();
        card.dataset.type = (exp.type_title || '').toLowerCase();

        // Update edit button data
        const editBtn = card.querySelector('.edit-expense-btn');
        if (editBtn) {
            editBtn.dataset.title = exp.title;
            editBtn.dataset.amount = exp.amount;
            editBtn.dataset.date = exp.date;
            editBtn.dataset.notes = exp.notes || '';
            editBtn.dataset.type = exp.expense_type || '';
        }

        // Re-apply current filters after edit (in case type/title changed and no longer matches)
        if (typeof filterExpenses === 'function') {
            setTimeout(filterExpenses, 30);
        }
    }

    function updateExpenseCountAndTotal() {
        const visible = document.querySelectorAll('.expense-item:not([style*="none"])').length;
        const countEl = document.getElementById('expenseCount');
        if (countEl) countEl.textContent = visible;

        // Recalculate total from visible cards
        let newTotal = 0;
        document.querySelectorAll('.expense-item:not([style*="display: none"]) .expense-amount').forEach(el => {
            const val = parseFloat(el.textContent.replace(/[^0-9.]/g, '')) || 0;
            newTotal += val;
        });
        // If you have a total element
        const totalEl = document.querySelector('.text-danger[style*="18px"]');
        if (totalEl) totalEl.textContent = '₹' + newTotal.toLocaleString();
    }

    // Init
    document.addEventListener('DOMContentLoaded', function() {
        attachExpenseDeleteListeners();

        // Auto open add modal logic if needed
        const addModal = document.getElementById('addExpenseModal');
        if (addModal) {
            addModal.addEventListener('shown.bs.modal', function() {
                // optional prefill
            });
        }
    });
</script>
@endpush
@endsection
