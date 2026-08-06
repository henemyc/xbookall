@extends('panel.layouts.app')

@section('title', 'Invoices')

@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">📄 Invoices ({{ $invoices->total() ?? count($invoices) }})</h5>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addInvoiceModal">
            <i class="bi bi-plus-circle"></i> Create Invoice
        </button>
    </div>
    <div class="card-body">
        <!-- Summary Cards -->
        @php
            $totalAmount = 0; $totalPaid = 0; $totalDue = 0;
            $paidCount = 0; $partialCount = 0; $unpaidCount = 0;
            foreach($invoices as $inv) {
                $t = $inv->items->sum('amount');
                $p = $inv->payments->sum('amount');
                $totalAmount += $t;
                $totalPaid += $p;
                $totalDue += max(0, $t - $p);
                if($inv->status === 'paid') $paidCount++;
                elseif($inv->status === 'partial') $partialCount++;
                else $unpaidCount++;
            }
        @endphp

        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6">
                <div class="p-3 rounded border" style="background:#f8fafc;">
                    <div class="text-muted small">Total Invoiced</div>
                    <div class="fw-bold fs-5">₹{{ number_format($totalAmount) }}</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="p-3 rounded border" style="background:#f0fdf4;">
                    <div class="text-muted small">Total Paid</div>
                    <div class="fw-bold fs-5 text-success">₹{{ number_format($totalPaid) }}</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="p-3 rounded border" style="background:#fef2f2;">
                    <div class="text-muted small">Total Due</div>
                    <div class="fw-bold fs-5 text-danger">₹{{ number_format($totalDue) }}</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="p-3 rounded border" style="background:#f8fafc;">
                    <div class="text-muted small mb-1">Status</div>
                    <div class="d-flex gap-1 flex-wrap">
                        <span class="badge bg-success">{{ $paidCount }} Paid</span>
                        <span class="badge bg-warning">{{ $partialCount }} Partial</span>
                        <span class="badge bg-danger">{{ $unpaidCount }} Unpaid</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search & Filters -->
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <div class="position-relative">
                    <input type="text" id="invoiceSearch" class="form-control" placeholder="🔍 Search by member or invoice #">
                    <div id="invoiceSearchSpinner" class="position-absolute top-50 end-0 translate-middle-y me-3 d-none">
                        <span class="spinner-border spinner-border-sm text-secondary"></span>
                    </div>
                </div>
            </div>
            <div class="col-md-6 text-end">
                <div class="btn-group" id="invoiceFilterGroup">
                    <button class="btn btn-sm btn-primary" onclick="filterInvoices('all', this)">All</button>
                    <button class="btn btn-sm btn-outline-secondary" onclick="filterInvoices('paid', this)">Paid</button>
                    <button class="btn btn-sm btn-outline-secondary" onclick="filterInvoices('partial', this)">Partial</button>
                    <button class="btn btn-sm btn-outline-secondary" onclick="filterInvoices('unpaid', this)">Unpaid</button>
                </div>
            </div>
        </div>

        <!-- Invoice List -->
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Invoice</th>
                        <th>Member</th>
                        <th>Date</th>
                        <th>Total</th>
                        <th>Paid</th>
                        <th>Due</th>
                        <th>Status</th>
                        <th class="text-end" style="width: 110px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="invoicesTableBody">
                    @forelse($invoices as $invoice)
                        @include('panel.invoices._row', [
                            'invoice' => $invoice,
                            'total' => $invoice->items->sum('amount'),
                            'paid' => $invoice->payments->sum('amount'),
                            'due' => max(0, $invoice->items->sum('amount') - $invoice->payments->sum('amount'))
                        ])
                    @empty
                    <tr><td colspan="8" class="text-center py-4">No invoices found</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Load More / Infinite Scroll -->
        @if($invoices->hasMorePages())
        <div class="text-center mt-3" id="invoiceLoadMoreContainer">
            <button id="loadMoreInvoicesBtn" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-arrow-down-circle me-1"></i> Load More
            </button>
            <div id="invoiceLoadSpinner" class="d-none mt-2">
                <span class="spinner-border spinner-border-sm text-primary me-1"></span>
                <small class="text-muted">Loading more...</small>
            </div>
        </div>
        @endif
    </div>
</div>

<!-- Create Invoice Modal -->
<div class="modal fade" id="addInvoiceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title">➕ Create New Invoice</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <!-- Member -->
                    <div class="col-12">
                        <label class="form-label">Member <span class="text-danger">*</span></label>
                        <select id="invoiceMember" class="form-select">
                            <option value="">Select member...</option>
                            @foreach($members ?? [] as $member)
                                <option value="{{ $member->id }}">{{ $member->name }} ({{ $member->phone_number }})</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Items -->
                    <div class="col-12">
                        <label class="form-label d-flex justify-content-between">
                            <span>Invoice Items</span>
                            <div>
                                <button type="button" class="btn btn-sm btn-outline-success me-1" onclick="addProductItem()">
                                    <i class="bi bi-box"></i> Product
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-info me-1" onclick="addClassItem()">
                                    <i class="bi bi-book"></i> Class
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="addInvoiceItemRow()">
                                    <i class="bi bi-plus"></i> Custom
                                </button>
                            </div>
                        </label>
                        <div id="invoiceItemsContainer">
                            <!-- Dynamic rows added here -->
                        </div>
                    </div>

                    <!-- Payment -->
                    <div class="col-md-4">
                        <label class="form-label">Paid Amount (₹)</label>
                        <input type="number" id="invoicePaidAmount" class="form-control" value="0" min="0" oninput="calculateInvoiceTotals()">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Payment Method</label>
                        <select id="invoicePaymentMethod" class="form-select">
                            <option value="cash">Cash</option>
                            <option value="upi">UPI</option>
                            <option value="card">Card</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Invoice Date</label>
                        <input type="date" id="invoiceDate" class="form-control" value="{{ date('Y-m-d') }}">
                    </div>
                </div>

                <!-- Summary -->
                <div class="mt-4 p-3 bg-light rounded">
                    <div class="row text-center">
                        <div class="col-4">
                            <div class="text-muted small">Total</div>
                            <div class="fw-bold fs-5" id="invTotal">₹0</div>
                        </div>
                        <div class="col-4">
                            <div class="text-muted small">Paid</div>
                            <div class="fw-bold fs-5 text-success" id="invPaid">₹0</div>
                        </div>
                        <div class="col-4">
                            <div class="text-muted small">Due</div>
                            <div class="fw-bold fs-5 text-danger" id="invDue">₹0</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="createInvoiceBtn" onclick="submitInvoice()">
                    <i class="bi bi-receipt me-1"></i> Create Invoice
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    let itemRowIndex = 0;

    function addInvoiceItemRow(title = '', amount = '') {
        const container = document.getElementById('invoiceItemsContainer');
        const rowId = `item-row-${itemRowIndex++}`;

        const html = `
            <div class="row g-2 mb-2 align-items-end ${rowId}">
                <div class="col-md-5">
                    <input type="text" class="form-control form-control-sm item-title" placeholder="Item title (e.g. Monthly Plan)" value="${title}" required>
                </div>
                <div class="col-md-3">
                    <input type="number" class="form-control form-control-sm item-amount" placeholder="Amount" value="${amount}" min="0" step="1" oninput="calculateInvoiceTotals()" required>
                </div>
                <div class="col-md-3">
                    <input type="text" class="form-control form-control-sm item-desc" placeholder="Description">
                </div>
                <div class="col-md-1">
                    <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="removeInvoiceItemRow(this)">
                        <i class="bi bi-x"></i>
                    </button>
                </div>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', html);
        calculateInvoiceTotals();
    }

    // Add a Product from dropdown
    function addProductItem() {
        const products = @json($products ?? []);
        if (!products.length) {
            window.showToast('No products available', 'error');
            return;
        }

        let options = products.map(p => `<option value="${p.id}" data-price="${p.price}">${p.title} (₹${p.price})</option>`).join('');

        const container = document.getElementById('invoiceItemsContainer');
        const rowId = `item-row-${itemRowIndex++}`;

        const html = `
            <div class="row g-2 mb-2 align-items-end ${rowId}" data-is-product="1">
                <div class="col-md-5">
                    <select class="form-control form-control-sm item-product-select" onchange="selectProduct(this)">
                        <option value="">Select Product...</option>
                        ${options}
                    </select>
                    <input type="hidden" class="item-product-id">
                </div>
                <div class="col-md-5">
                    <input type="text" class="form-control form-control-sm item-title" placeholder="Product name" required>
                </div>
                <div class="col-md-2">
                    <input type="number" class="form-control form-control-sm item-amount" placeholder="Amount" min="0" step="1" oninput="calculateInvoiceTotals()" required>
                </div>
                <div class="col-md-1">
                    <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="removeInvoiceItemRow(this)">
                        <i class="bi bi-x"></i>
                    </button>
                </div>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', html);
        calculateInvoiceTotals();
    }

    function selectProduct(selectEl) {
        const row = selectEl.closest('.row');
        const price = selectEl.selectedOptions[0]?.dataset.price || 0;
        const title = selectEl.selectedOptions[0]?.textContent.split(' (₹')[0] || '';

        row.querySelector('.item-title').value = title;
        row.querySelector('.item-amount').value = parseFloat(price);
        row.querySelector('.item-product-id').value = selectEl.value;

        calculateInvoiceTotals();
    }

    // Add a Class from dropdown
    function addClassItem() {
        const classes = @json($classes ?? []);
        if (!classes.length) {
            window.showToast('No classes available', 'error');
            return;
        }

        let options = classes.map(c => `<option value="${c.id}" data-price="${c.fees}">${c.title} (₹${c.fees})</option>`).join('');

        const container = document.getElementById('invoiceItemsContainer');
        const rowId = `item-row-${itemRowIndex++}`;

        const html = `
            <div class="row g-2 mb-2 align-items-end ${rowId}" data-is-class="1">
                <div class="col-md-5">
                    <select class="form-control form-control-sm item-class-select" onchange="selectClass(this)">
                        <option value="">Select Class...</option>
                        ${options}
                    </select>
                    <input type="hidden" class="item-class-id">
                </div>
                <div class="col-md-5">
                    <input type="text" class="form-control form-control-sm item-title" placeholder="Class name" required>
                </div>
                <div class="col-md-2">
                    <input type="number" class="form-control form-control-sm item-amount" placeholder="Amount" min="0" step="1" oninput="calculateInvoiceTotals()" required>
                </div>
                <div class="col-md-1">
                    <button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="removeInvoiceItemRow(this)">
                        <i class="bi bi-x"></i>
                    </button>
                </div>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', html);
        calculateInvoiceTotals();
    }

    function selectClass(selectEl) {
        const row = selectEl.closest('.row');
        const price = selectEl.selectedOptions[0]?.dataset.price || 0;
        const title = selectEl.selectedOptions[0]?.textContent.split(' (₹')[0] || '';

        row.querySelector('.item-title').value = title;
        row.querySelector('.item-amount').value = parseFloat(price);
        row.querySelector('.item-class-id').value = selectEl.value;

        calculateInvoiceTotals();
    }

    function removeInvoiceItemRow(el) {
        el.closest('.row').remove();
        calculateInvoiceTotals();
    }

    function calculateInvoiceTotals() {
        let total = 0;
        document.querySelectorAll('#invoiceItemsContainer .item-amount').forEach(input => {
            total += parseFloat(input.value) || 0;
        });

        const paid = parseFloat(document.getElementById('invoicePaidAmount').value) || 0;
        const due = Math.max(0, total - paid);

        document.getElementById('invTotal').textContent = '₹' + total.toFixed(0);
        document.getElementById('invPaid').textContent = '₹' + paid.toFixed(0);
        document.getElementById('invDue').textContent = '₹' + due.toFixed(0);
    }

    // AJAX Create Invoice (full AJAX + toast + dynamic UI) + Spinner
    async function submitInvoice() {
        const btn = document.getElementById('createInvoiceBtn');
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Creating...`;

        const memberId = document.getElementById('invoiceMember').value;
        if (!memberId) {
            window.showToast('Please select a member', 'error');
            resetInvoiceBtn(btn, orig);
            return;
        }

        const items = [];
        let hasValidItem = false;

        document.querySelectorAll('#invoiceItemsContainer .row').forEach(row => {
            const title = row.querySelector('.item-title')?.value.trim();
            const amount = parseFloat(row.querySelector('.item-amount')?.value) || 0;
            const desc = row.querySelector('.item-desc')?.value.trim() || '';

            if (title && amount > 0) {
                const itemData = { title, amount, description: desc };

                const prodId = row.querySelector('.item-product-id')?.value;
                if (prodId) itemData.product_id = prodId;

                const classId = row.querySelector('.item-class-id')?.value;
                if (classId) itemData.class_id = classId;

                items.push(itemData);
                hasValidItem = true;
            }
        });

        if (!hasValidItem) {
            window.showToast('Please add at least one item with title and amount', 'error');
            resetInvoiceBtn(btn, orig);
            return;
        }

        const fd = new FormData();
        fd.append('user_id', memberId);
        fd.append('paid_amount', document.getElementById('invoicePaidAmount').value || '0');
        fd.append('payment_method', document.getElementById('invoicePaymentMethod').value);
        fd.append('invoice_date', document.getElementById('invoiceDate').value);

        items.forEach((item, index) => {
            fd.append(`items[${index}][title]`, item.title);
            fd.append(`items[${index}][amount]`, item.amount);
            fd.append(`items[${index}][description]`, item.description);
            if (item.product_id) fd.append(`items[${index}][product_id]`, item.product_id);
            if (item.class_id) fd.append(`items[${index}][class_id]`, item.class_id);
        });

        try {
            const res = await fetch('/panel/invoices', {
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
                window.showToast(data.message || 'Invoice created successfully!', 'success');
                
                const modal = bootstrap.Modal.getInstance(document.getElementById('addInvoiceModal'));
                if (modal) modal.hide();

                resetInvoiceForm();

                if (data.invoice) {
                    addInvoiceRow(data.invoice);
                } else {
                    setTimeout(() => location.reload(), 800);
                }
            } else {
                const err = data.error || (data.errors ? Object.values(data.errors).flat().join(', ') : 'Failed to create invoice');
                window.showToast(err, 'error');
            }
        } catch (e) {
            console.error(e);
            window.showToast('Network error while creating invoice', 'error');
        } finally {
            resetInvoiceBtn(btn, orig);
        }
    }

    function resetInvoiceBtn(btn, text) {
        btn.disabled = false;
        btn.innerHTML = text;
    }

    // Helper: show loading on any action button
    function setButtonLoading(btn, loadingText = '⏳') {
        if (!btn) return '';
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span> ${loadingText}`;
        return orig;
    }

    function resetInvoiceForm() {
        document.getElementById('invoiceMember').value = '';
        document.getElementById('invoicePaidAmount').value = '0';
        document.getElementById('invoiceItemsContainer').innerHTML = '';
        document.getElementById('invTotal').textContent = '₹0';
        document.getElementById('invPaid').textContent = '₹0';
        document.getElementById('invDue').textContent = '₹0';
        itemRowIndex = 0;
    }

    // Add initial empty row when modal opens
    document.getElementById('addInvoiceModal').addEventListener('shown.bs.modal', function () {
        const container = document.getElementById('invoiceItemsContainer');
        if (container.children.length === 0) {
            addInvoiceItemRow();
        }
        calculateInvoiceTotals();
    });

    // Make sure product/class selects work even on initial custom rows
    // (already handled in add functions)

    // Search + Filter with loading state
    const invoiceSearchInput = document.getElementById('invoiceSearch');
    const invoiceSearchSpinner = document.getElementById('invoiceSearchSpinner');

    invoiceSearchInput.addEventListener('input', () => {
        if (invoiceSearchSpinner) invoiceSearchSpinner.classList.remove('d-none');
        filterInvoiceList();

        setTimeout(() => {
            if (invoiceSearchSpinner) invoiceSearchSpinner.classList.add('d-none');
        }, 280);
    });

    // Make sure newly loaded rows are filterable
    // (filterInvoiceList already runs on every search input)

    function filterInvoices(status, btn) {
        const group = document.getElementById('invoiceFilterGroup');

        // Disable all + show loading on clicked button
        document.querySelectorAll('#invoiceFilterGroup .btn').forEach(b => {
            b.classList.remove('btn-primary');
            b.classList.add('btn-outline-secondary');
            b.disabled = true;
        });

        if (btn) {
            const origBtnText = btn.innerHTML;
            btn.classList.remove('btn-outline-secondary');
            btn.classList.add('btn-primary');
            btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span> ${btn.textContent.trim()}`;

            // Restore after filtering
            setTimeout(() => {
                btn.innerHTML = origBtnText;
            }, 600);
        }

        if (group) group.style.opacity = '0.75';

        setTimeout(() => {
            filterInvoiceList();

            document.querySelectorAll('#invoiceFilterGroup .btn').forEach(b => {
                b.disabled = false;
            });
            if (group) group.style.opacity = '1';
        }, 180);
    }

    function filterInvoiceList() {
        const query = invoiceSearchInput.value.toLowerCase();
        const activeBtn = document.querySelector('#invoiceFilterGroup .btn-primary') || 
                          document.querySelector('.btn-group .btn-primary');
        const statusFilter = activeBtn ? activeBtn.textContent.trim().toLowerCase() : 'all';

        // Quick visual feedback during filtering
        const rows = document.querySelectorAll('.invoice-row');
        rows.forEach(r => r.style.opacity = '0.35');

        rows.forEach(row => {
            const member = row.dataset.member || '';
            const invNum = row.dataset.invoice || '';
            const status = row.dataset.status || '';

            const matchesSearch = !query || member.includes(query) || invNum.includes(query);
            const matchesStatus = statusFilter === 'all' || status === statusFilter;

            row.style.display = (matchesSearch && matchesStatus) ? '' : 'none';
            row.style.opacity = '1';
        });
    }

    function addInvoiceRow(inv) {
        const tbody = document.getElementById('invoicesTableBody');
        if (!tbody) return;

        const tr = document.createElement('tr');
        tr.className = 'invoice-row';
        tr.dataset.member = (inv.member_name || '').toLowerCase();
        tr.dataset.invoice = inv.invoice_id;
        tr.dataset.status = inv.status || 'unpaid';

        const total = parseFloat(inv.total || 0);
        const paid = parseFloat(inv.paid || 0);
        const due = Math.max(0, total - paid);

        let statusHtml = '';
        if (inv.status === 'paid') statusHtml = '<span class="badge bg-success">PAID</span>';
        else if (inv.status === 'partial') statusHtml = '<span class="badge bg-warning">PARTIAL</span>';
        else statusHtml = '<span class="badge bg-danger">UNPAID</span>';

        tr.innerHTML = `
            <td><strong>#${inv.invoice_id}</strong></td>
            <td>
                <div class="fw-semibold">${inv.member_name || 'N/A'}</div>
                <small class="text-muted">${inv.member_phone || ''}</small>
            </td>
            <td>${inv.invoice_date || ''}</td>
            <td>₹${total.toFixed(0)}</td>
            <td class="text-success">₹${paid.toFixed(0)}</td>
            <td class="${due > 0 ? 'text-danger' : 'text-muted'}">₹${due.toFixed(0)}</td>
            <td>${statusHtml}</td>
            <td class="text-end" style="width: 110px; white-space: nowrap;">
                <div class="btn-group btn-group-sm" role="group">
                    <a href="/panel/invoices/${inv.id}" class="btn btn-outline-primary">
                        <i class="bi bi-eye"></i>
                    </a>
                    <!-- Temporarily commented: Delete option -->
                    <!--
                    <button class="btn btn-outline-danger delete-invoice-btn" 
                            data-id="${inv.id}" 
                            data-invoice="#${inv.invoice_id}">
                        <i class="bi bi-trash3"></i>
                    </button>
                    -->
                </div>
            </td>
        `;
        tbody.insertBefore(tr, tbody.firstChild);

        // Temporarily commented: delete listener (delete option disabled)
        // attachInvoiceDeleteListeners(tr);
    }

    // =============================================
    // TEMPORARILY DISABLED: Invoice Delete option
    // Buttons commented out in table + this function fully disabled.
    /*
    // Delete invoice (DISABLED)
    function attachInvoiceDeleteListeners(container = document) {
        container.querySelectorAll('.delete-invoice-btn').forEach(btn => {
            btn.addEventListener('click', async function() {
                const id = this.dataset.id;
                const invNum = this.dataset.invoice || 'this invoice';

                document.getElementById('deleteConfirmMessage').textContent = `Delete ${invNum}? This cannot be undone.`;

                const modal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
                const confirmBtn = document.getElementById('deleteConfirmBtn');

                const fresh = confirmBtn.cloneNode(true);
                confirmBtn.parentNode.replaceChild(fresh, confirmBtn);

                fresh.onclick = async () => {
                    modal.hide();

                    try {
                        const res = await fetch(`/panel/invoices/${id}`, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json'
                            }
                        });

                        const data = await res.json();

                        if (data.success) {
                            const row = this.closest('tr');
                            if (row) row.remove();
                            window.showToast('Invoice deleted', 'success');
                        } else {
                            window.showToast(data.error || 'Failed to delete', 'error');
                        }
                    } catch (e) {
                        window.showToast('Network error', 'error');
                    }
                };
                modal.show();
            });
        });
    }
    */
    // =============================================
    // End of temporarily disabled Invoice Delete code
    // =============================================

    // ================ INVOICE LOAD MORE / PAGINATION ================
    let currentInvoicePage = {{ $invoices->currentPage() ?? 1 }};
    let isLoadingInvoices = false;

    const loadMoreBtn = document.getElementById('loadMoreInvoicesBtn');
    const loadSpinner = document.getElementById('invoiceLoadSpinner');
    const loadMoreContainer = document.getElementById('invoiceLoadMoreContainer');

    async function loadMoreInvoices() {
        if (isLoadingInvoices || !loadMoreBtn) return;

        isLoadingInvoices = true;

        const origBtnText = loadMoreBtn.innerHTML;
        loadMoreBtn.disabled = true;
        loadMoreBtn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span> Loading...`;

        if (loadSpinner) loadSpinner.classList.remove('d-none');

        // Table loading state
        const tbody = document.getElementById('invoicesTableBody');
        const tableContainer = tbody ? tbody.closest('.table-responsive') : null;
        if (tableContainer) tableContainer.style.opacity = '0.7';

        try {
            const nextPage = currentInvoicePage + 1;
            const res = await fetch(`/panel/invoices?page=${nextPage}`, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            const data = await res.json();

            if (data.success && data.html) {
                if (tbody) tbody.insertAdjacentHTML('beforeend', data.html);

                currentInvoicePage = data.current_page || nextPage;

                // Hide load more if no more pages
                if (!data.has_more && loadMoreContainer) {
                    loadMoreContainer.style.display = 'none';
                }
            } else {
                window.showToast('No more invoices', 'error');
            }
        } catch (e) {
            console.error(e);
            window.showToast('Failed to load more invoices', 'error');
            loadMoreBtn.disabled = false;
            loadMoreBtn.innerHTML = origBtnText;
        } finally {
            isLoadingInvoices = false;
            if (loadSpinner) loadSpinner.classList.add('d-none');
            if (tableContainer) tableContainer.style.opacity = '1';
        }
    }

    if (loadMoreBtn) {
        loadMoreBtn.addEventListener('click', loadMoreInvoices);
    }

    // Optional: Infinite scroll
    let invoiceScrollTimeout = null;
    window.addEventListener('scroll', () => {
        if (!loadMoreContainer || loadMoreContainer.style.display === 'none') return;

        clearTimeout(invoiceScrollTimeout);
        invoiceScrollTimeout = setTimeout(() => {
            const rect = loadMoreContainer.getBoundingClientRect();
            if (rect.top < window.innerHeight + 100 && !isLoadingInvoices) {
                loadMoreInvoices();
            }
        }, 150);
    });

    // Init
    document.addEventListener('DOMContentLoaded', () => {
        // TEMPORARILY DISABLED: Delete option completely turned off (HTML buttons + JS)
        // attachInvoiceDeleteListeners();

        // Optional: add one row by default when page loads (for convenience)
        // document.getElementById('addInvoiceModal')?.addEventListener('shown.bs.modal', ... already handled

        // If there is a load more container, make sure initial state is correct
        if (loadMoreContainer && !{{ $invoices->hasMorePages() ? 'true' : 'false' }}) {
            loadMoreContainer.style.display = 'none';
        }
    });
</script>
@endpush