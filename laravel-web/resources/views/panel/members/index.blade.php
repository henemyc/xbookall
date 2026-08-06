@extends('panel.layouts.app')

@section('title', 'Members')

@section('content')
<style>
    .members-table {
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        border: 1px solid #e5e7eb;
    }
    .members-table thead {
        background: #f8fafc;
        font-size: 13px;
        font-weight: 600;
        color: #475569;
    }
    .members-table tbody tr {
        cursor: pointer;
        transition: all 0.2s ease;
        border-bottom: 1px solid #f1f5f9;
    }
    .members-table tbody tr:hover {
        background: #f8fafc;
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    }
    .members-table tbody tr:last-child {
        border-bottom: none;
    }
    .member-avatar {
        width: 42px;
        height: 42px;
        border-radius: 9999px;
        background: linear-gradient(135deg, #ff8a3d, #ff6b2c);
        color: white;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 15px;
        box-shadow: 0 2px 4px rgba(255, 107, 44, 0.3);
    }
    .member-name {
        font-weight: 600;
        color: #1e2937;
        font-size: 15px;
    }
    .status-badge {
        padding: 4px 12px;
        border-radius: 9999px;
        font-size: 12px;
        font-weight: 600;
    }
    .status-active {
        background: #dcfce7;
        color: #166534;
    }
    .status-expired {
        background: #fee2e2;
        color: #991b1b;
    }
    .modern-pagination {
        display: flex;
        justify-content: center;
        gap: 4px;
        flex-wrap: wrap;
    }
    .modern-pagination .page-link {
        border-radius: 8px !important;
        border: 1px solid #e2e8f0;
        color: #475569;
        font-weight: 500;
        padding: 6px 13px;
        min-width: 38px;
        text-align: center;
        transition: all 0.2s;
    }
    .modern-pagination .page-item.active .page-link {
        background: linear-gradient(135deg, #ff8a3d, #ff6b2c);
        border-color: #ff6b2c;
        color: white;
        box-shadow: 0 2px 6px rgba(255,107,44,0.3);
    }
    .modern-pagination .page-link:hover {
        background: #f1f5f9;
        border-color: #cbd5e1;
    }
    .table-header-bar {
        background: #fff;
        border-radius: 12px;
        padding: 12px 18px;
        margin-bottom: 16px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        border: 1px solid #e5e7eb;
    }

    /* Loading Spinner Styles - robust version */
    #membersTableContainer {
        position: relative;
        min-height: 160px;
    }
    .members-loading-overlay {
        position: absolute;
        inset: 0;
        background: rgba(255,255,255,0.88);
        backdrop-filter: blur(2px);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        z-index: 999;
        border-radius: 12px;
        pointer-events: none;
    }
    .members-loading-spinner {
        width: 36px;
        height: 36px;
        border: 4px solid #e2e8f0;
        border-top-color: #ff8a3d;
        border-radius: 50%;
        animation: members-spin 0.7s linear infinite;
    }
    .members-loading-text {
        margin-top: 8px;
        font-size: 12px;
        color: #64748b;
        font-weight: 500;
    }
    @keyframes members-spin {
        to { transform: rotate(360deg); }
    }

    /* Optional: subtle disabled state for search */
    #memberSearch.loading {
        background: #f8fafc;
        pointer-events: none;
    }

    /* Loading state for filter buttons */
    .btn-group .btn.loading {
        opacity: 0.65;
        pointer-events: none;
    }
    .import-dropzone {
        border: 2px dashed #cbd5e1;
        border-radius: 18px;
        padding: 28px;
        text-align: center;
        background: linear-gradient(180deg, #fff 0%, #f8fafc 100%);
        transition: .2s ease;
    }
    .import-dropzone:hover {
        border-color: #ff8a3d;
        background: rgba(255, 107, 44, 0.04);
    }
    .import-result-card {
        border-radius: 16px;
        border: 1px solid #e5e7eb;
        padding: 14px;
        background: #fff;
    }
    .import-error-list {
        max-height: 260px;
        overflow: auto;
        border-radius: 12px;
        border: 1px solid #fee2e2;
        background: #fff7f7;
    }
</style>

@php
    $gymOwnerIdForPlan = in_array(auth()->user()->type ?? '', ['admin','owner']) ? auth()->id() : (auth()->user()->parent_id ?? 0);
    $bulkImportAllowed = \App\Services\SubscriptionFeatureService::enabled((int) $gymOwnerIdForPlan, 'bulk_import_enabled', true);
    $bulkImportLimit = \App\Services\SubscriptionFeatureService::limit((int) $gymOwnerIdForPlan, 'bulk_import_limit', 0);
@endphp

<div class="card border-0 shadow-sm" style="border-radius: 16px; overflow: hidden;">
    <!-- Header -->
    <div class="card-header bg-white py-3 px-4" style="border-bottom: 1px solid #e5e7eb;">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-0 fw-bold" style="color:#1e2937;">Members</h5>
                <small class="text-muted" id="totalCount">{{ $members->total() }} total</small>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                @if($bulkImportAllowed)
                <a href="{{ route('panel.members.import.template') }}" class="btn btn-outline-secondary px-3" style="border-radius:10px; font-weight:600;">
                    <i class="bi bi-download me-2"></i> CSV Template
                </a>
                <button class="btn btn-outline-primary px-3" style="border-radius:10px; font-weight:600;" data-bs-toggle="modal" data-bs-target="#importMembersModal">
                    <i class="bi bi-upload me-2"></i> Bulk Import{{ $bulkImportLimit > 0 ? ' (Max '.$bulkImportLimit.')' : '' }}
                </button>
                @endif
                <button class="btn btn-primary px-4" style="border-radius:10px; font-weight:600;" data-bs-toggle="modal" data-bs-target="#addMemberModal">
                    <i class="bi bi-plus-lg me-2"></i> Add Member
                </button>
            </div>
        </div>
    </div>

    <div class="card-body p-4">

        <!-- Search + Filters -->
        <div class="table-header-bar d-flex flex-column flex-md-row align-items-md-center gap-3">
            <div class="flex-grow-1">
                <div class="input-group" style="max-width: 340px;">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" id="memberSearch" class="form-control border-start-0" placeholder="Search by name, phone or email..." style="box-shadow:none;">
                </div>
            </div>

            <div class="d-flex gap-2">
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-sm btn-primary active" onclick="filterMembers('all', this)">All</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="filterMembers('active', this)">Active</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="filterMembers('expired', this)">Expired</button>
                </div>
            </div>
        </div>

        <!-- AJAX-powered Table + Pagination Container -->
        <div id="membersTableContainer">
            <!-- Initial table content (inline for reliability) -->
            <div class="table-responsive">
                <table class="table members-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width: 34%; padding-left: 20px;">Member</th>
                            <th style="width: 18%;">Phone</th>
                            <th style="width: 20%;">Plan</th>
                            <th style="width: 16%;">Expiry</th>
                            <th style="width: 12%;">Status</th>
                        </tr>
                    </thead>
                    <tbody id="membersTableBody">
                        @forelse($members as $member)
                        @php
                            $isActive = $member->traineeDetails && 
                                       $member->traineeDetails->membership_expiry_date && 
                                       \Carbon\Carbon::parse($member->traineeDetails->membership_expiry_date)->isFuture();
                            $expiryDate = $member->traineeDetails && $member->traineeDetails->membership_expiry_date 
                                        ? \Carbon\Carbon::parse($member->traineeDetails->membership_expiry_date)->format('d M Y') 
                                        : '-';
                            $planTitle = ($member->traineeDetails && $member->traineeDetails->membership) 
                                        ? $member->traineeDetails->membership->title 
                                        : 'No Plan';
                        @endphp

                        <tr class="member-row" 
                            data-name="{{ strtolower($member->name) }}" 
                            data-phone="{{ $member->phone_number ?? '' }}"
                            data-email="{{ strtolower($member->email ?? '') }}"
                            data-status="{{ $isActive ? 'active' : 'expired' }}"
                            onclick="window.location='{{ route('panel.members.show', $member->id) }}'">
                            
                            <td style="padding-left: 20px;">
                                <div class="d-flex align-items-center">
                                    <div class="member-avatar me-3">
                                        {{ strtoupper(substr($member->name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <div class="member-name">{{ $member->name }}</div>
                                        <div class="text-muted" style="font-size:12.5px;">{{ $member->email }}</div>
                                    </div>
                                </div>
                            </td>
                            
                            <td>
                                <span class="text-dark fw-medium">{{ $member->phone_number ?? '—' }}</span>
                            </td>
                            
                            <td>
                                <span class="badge px-3 py-1" style="background:#e0f2fe;color:#0369a1;font-weight:600;font-size:12px;">
                                    {{ $planTitle }}
                                </span>
                            </td>
                            
                            <td>
                                <span class="text-secondary" style="font-size:14px;">{{ $expiryDate }}</span>
                            </td>
                            
                            <td>
                                @if($isActive)
                                    <span class="status-badge status-active">Active</span>
                                @else
                                    <span class="status-badge status-expired">Expired</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="bi bi-people fs-1 d-block mb-2"></i>
                                    No members found
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($members->hasPages())
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mt-4 gap-2">
                <div class="text-muted small">
                    Showing <strong>{{ $members->firstItem() }}</strong> to <strong>{{ $members->lastItem() }}</strong> of <strong>{{ $members->total() }}</strong> members
                </div>
                
                <div class="modern-pagination">
                    {{ $members->links('pagination::bootstrap-5') }}
                </div>
            </div>
            @endif
        </div>

    </div>
</div>

<!-- Add Member Modal -->
<div class="modal fade" id="addMemberModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title">➕ Add New Member</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Personal Info -->
                <h6 class="mb-3">👤 Personal Information</h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" id="memberName" class="form-control" placeholder="Full name">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Phone <span class="text-danger">*</span></label>
                        <input type="text" id="memberPhone" class="form-control" placeholder="10 digits" maxlength="10">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Email</label>
                        <input type="email" id="memberEmail" class="form-control" placeholder="Optional">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Gender</label>
                        <select id="memberGender" class="form-select">
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">DOB</label>
                        <input type="date" id="memberDob" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Address</label>
                        <input type="text" id="memberAddress" class="form-control" placeholder="Optional">
                    </div>
                </div>

                <!-- Membership -->
                <h6 class="mb-3">📋 Membership</h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label">Plan <span class="text-danger">*</span></label>
                        <select id="memberPlan" class="form-select" onchange="calculateMember()">
                            <option value="">Select Plan</option>
                            @foreach($plans as $plan)
                                <option value="{{ $plan->id }}" data-amount="{{ $plan->amount }}" data-package="{{ $plan->package }}">{{ $plan->title }} ₹{{ number_format($plan->amount) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Class</label>
                        <select id="memberClass" class="form-select" onchange="calculateMember()">
                            <option value="0" data-fees="0">No Class</option>
                            @foreach($classes as $class)
                                <option value="{{ $class->id }}" data-fees="{{ $class->fees }}">{{ $class->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Trainer</label>
                        <select id="memberTrainer" class="form-select">
                            <option value="0">No Trainer</option>
                            @foreach($trainers as $trainer)
                                <option value="{{ $trainer->id }}">{{ $trainer->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Start Date</label>
                        <input type="date" id="memberStartDate" class="form-control" value="{{ date('Y-m-d') }}" onchange="calculateMember()">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Expiry Date</label>
                        <input type="date" id="memberExpiryDate" class="form-control" readonly style="background:#f5f5f5;">
                    </div>
                </div>

                <!-- Payment -->
                <h6 class="mb-3">💰 Payment</h6>
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Reg. Fee (₹)</label>
                        <input type="number" id="memberRegFee" class="form-control" value="0" min="0" onchange="calculateMember()">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Paid (₹)</label>
                        <input type="number" id="memberPaid" class="form-control" value="0" min="0" oninput="calculateMember()">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Method</label>
                        <select id="memberMethod" class="form-select">
                            <option value="cash">Cash</option>
                            <option value="upi">UPI</option>
                            <option value="card">Card</option>
                        </select>
                    </div>
                </div>

                <!-- Summary -->
                <div class="bg-dark text-white p-3 rounded">
                    <div class="row text-center">
                        <div class="col-3">
                            <div style="opacity:0.7;font-size:11px;">Plan</div>
                            <div class="fw-bold" id="sPlan">₹0</div>
                        </div>
                        <div class="col-3">
                            <div style="opacity:0.7;font-size:11px;">Class</div>
                            <div class="fw-bold" id="sClass">₹0</div>
                        </div>
                        <div class="col-3">
                            <div style="opacity:0.7;font-size:11px;">Paid</div>
                            <div class="fw-bold text-success" id="sPaid">-₹0</div>
                        </div>
                        <div class="col-3">
                            <div style="opacity:0.7;font-size:11px;">Due</div>
                            <div class="fw-bold text-danger" id="sDue">₹0</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="addMemberBtn" onclick="submitMember()">
                    <i class="bi bi-person-plus me-1"></i> Add Member
                </button>
            </div>
        </div>
    </div>
</div>

@if($bulkImportAllowed)
<!-- Bulk Import Modal -->
<div class="modal fade" id="importMembersModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <div>
                    <h5 class="modal-title mb-1"><i class="bi bi-upload me-2"></i> Bulk Import Members</h5>
                    <small style="opacity:.7;">Upload CSV, validate rows, create members and invoices automatically.</small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info d-flex align-items-start gap-2">
                    <i class="bi bi-info-circle mt-1"></i>
                    <div>
                        <strong>Required columns:</strong>
                        <code>name, phone_number, gender, address, membership_plan, start_date, registration_fee, paid_amount, payment_method</code>
                        <div class="small mt-1">Membership plan can be exact plan title or plan ID. Payment method: cash, upi, card, online, bank.</div>
                        @if($bulkImportLimit > 0)<div class="small mt-1 fw-bold text-primary">Your plan allows up to {{ $bulkImportLimit }} rows per import.</div>@endif
                    </div>
                </div>

                <form id="bulkImportForm" enctype="multipart/form-data">
                    @csrf
                    <div class="import-dropzone mb-3">
                        <i class="bi bi-filetype-csv" style="font-size:42px;color:#ff6b2c;"></i>
                        <h6 class="mt-2 mb-1">Choose CSV file</h6>
                        <p class="text-muted small mb-3">Download the template first to avoid format errors.</p>
                        <input type="file" name="csv_file" id="csvFileInput" class="form-control" accept=".csv,.txt" required>
                        <div class="mt-3">
                            <a href="{{ route('panel.members.import.template') }}" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-download me-1"></i> Download Template
                            </a>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" id="bulkImportBtn">
                        <i class="bi bi-cloud-arrow-up me-2"></i> Validate & Import
                    </button>
                </form>

                <div id="bulkImportProgress" class="d-none mt-4">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <div class="spinner-border spinner-border-sm text-primary"></div>
                        <strong>Processing CSV...</strong>
                    </div>
                    <div class="progress" style="height:10px;border-radius:20px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" style="width:100%;background:linear-gradient(135deg,#ff8a3d,#ff6b2c);"></div>
                    </div>
                </div>

                <div id="bulkImportResult" class="d-none mt-4"></div>
            </div>
        </div>
    </div>
</div>
@endif
@endsection
@push('scripts')
<script>
    // Show nice spinner overlay
    function showMembersLoading(container) {
        if (!container) return;
        hideMembersLoading(container);

        const overlay = document.createElement('div');
        overlay.className = 'members-loading-overlay';
        overlay.innerHTML = `
            <div class="members-loading-spinner"></div>
            <div class="members-loading-text">Loading members...</div>
        `;
        container.appendChild(overlay);
        container.dataset.loading = 'true';

        const search = document.getElementById('memberSearch');
        if (search) search.classList.add('loading');
    }

    function hideMembersLoading(container) {
        if (!container) return;
        const ex = container.querySelector('.members-loading-overlay');
        if (ex) ex.remove();
        delete container.dataset.loading;

        const search = document.getElementById('memberSearch');
        if (search) search.classList.remove('loading');

        document.querySelectorAll('.btn-group .btn.loading').forEach(b => b.classList.remove('loading'));
    }

    // Core AJAX loader
    async function loadMembers(url = null, search = '', status = 'all') {
        const container = document.getElementById('membersTableContainer');
        if (!container) return;

        let fetchUrl = url || '/panel/members';
        const params = new URLSearchParams();

        if (!url) {
            if (search) params.append('search', search);
            if (status && status !== 'all') params.append('status', status);
            if (params.toString()) fetchUrl += '?' + params.toString();
        }

        showMembersLoading(container);

        try {
            const res = await fetch(fetchUrl, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'text/html'
                }
            });

            if (!res.ok) throw new Error('HTTP ' + res.status);

            const html = await res.text();

            hideMembersLoading(container);
            container.innerHTML = html;

            // Re-attach everything for the new content
            attachRowClickHandlers();
            updateTotalCountFromContainer();
            bindPaginationLinks();

        } catch (e) {
            console.error('AJAX error:', e);
            hideMembersLoading(container);
            container.innerHTML = `
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-exclamation-triangle fs-3 d-block mb-2 text-warning"></i>
                    Failed to load members.<br>
                    <button onclick="loadMembers()" class="btn btn-sm btn-outline-primary mt-2">Try again</button>
                </div>`;
        }
    }

    function updateTotalCountFromContainer() {
        const c = document.getElementById('membersTableContainer');
        if (!c) return;
        const showing = c.querySelector('.text-muted.small');
        const total = document.getElementById('totalCount');
        if (showing && total) {
            const m = showing.textContent.match(/of\s+(\d+)/i);
            if (m) total.textContent = m[1] + ' total';
        }
    }

    function attachRowClickHandlers() {
        document.querySelectorAll('#membersTableContainer .member-row').forEach(row => {
            const clone = row.cloneNode(true);
            row.parentNode.replaceChild(clone, row);
            clone.onclick = () => {
                const oc = clone.getAttribute('onclick');
                if (oc) {
                    const m = oc.match(/window\.location='([^']+)'/);
                    if (m) window.location = m[1];
                }
            };
        });
    }

    // PAGINATION - Use event delegation on the container (most reliable after AJAX)
    function bindPaginationLinks() {
        const container = document.getElementById('membersTableContainer');
        if (!container) return;

        // Remove any previous handler
        if (container._paginationHandler) {
            container.removeEventListener('click', container._paginationHandler);
        }

        container._paginationHandler = function(e) {
            const link = e.target.closest('.modern-pagination a');
            if (!link) return;

            e.preventDefault();
            showMembersLoading(container);
            loadMembers(link.getAttribute('href'));
        };

        container.addEventListener('click', container._paginationHandler);
    }

    // SEARCH
    let searchTimeout;
    const searchInput = document.getElementById('memberSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const q = this.value.trim();
            if (q.length > 0) this.classList.add('loading');

            searchTimeout = setTimeout(() => {
                const active = document.querySelector('.btn-group .btn-primary');
                const st = active ? active.textContent.toLowerCase() : 'all';
                loadMembers(null, q, st === 'all' ? '' : st);
            }, 240);
        });
    }

    // FILTERS
    window.filterMembers = function(status, btn) {
        document.querySelectorAll('.btn-group .btn').forEach(b => {
            b.classList.remove('btn-primary');
            b.classList.add('btn-outline-secondary');
        });
        btn.classList.remove('btn-outline-secondary');
        btn.classList.add('btn-primary');

        const q = document.getElementById('memberSearch')?.value.trim() || '';
        loadMembers(null, q, status);
    };

    function initMembersPage() {
        attachRowClickHandlers();
        bindPaginationLinks();

        const active = document.querySelector('.btn-group .btn-primary');
        if (!active) {
            const first = document.querySelector('.btn-group .btn');
            if (first) first.classList.add('btn-primary');
        }

        const usp = new URLSearchParams(window.location.search);
        if (usp.get('search') && searchInput) {
            searchInput.value = usp.get('search');
        }
    }

    function memberSelectedOption(selectId) {
        const el = document.getElementById(selectId);
        return el ? el.options[el.selectedIndex] : null;
    }

    function memberMoney(value) {
        const n = parseFloat(String(value ?? '0').replace(/[^0-9.]/g, ''));
        return Number.isFinite(n) ? n : 0;
    }

    function memberPackageMonths(packageValue) {
        const p = String(packageValue || '').toLowerCase().trim();
        if (!p) return 1;
        const numberMatch = p.match(/(\d+)/);
        if (p.includes('year') || p.includes('annual') || p.includes('12')) return numberMatch ? parseInt(numberMatch[1], 10) : 12;
        if (p.includes('quarter') || p.includes('3')) return numberMatch ? parseInt(numberMatch[1], 10) : 3;
        if (p.includes('half') || p.includes('6')) return numberMatch ? parseInt(numberMatch[1], 10) : 6;
        if (p.includes('week')) return 0;
        if (p.includes('day')) return 0;
        if (numberMatch && (p.includes('month') || p.includes('mon'))) return Math.max(1, parseInt(numberMatch[1], 10));
        return 1;
    }

    function addMonthsNoOverflow(dateText, months) {
        if (!dateText) return '';
        const parts = dateText.split('-').map(Number);
        if (parts.length !== 3 || !parts[0] || !parts[1] || !parts[2]) return '';
        const y = parts[0], m = parts[1] - 1, d = parts[2];
        const targetMonth = m + Math.max(0, months);
        const lastDay = new Date(y, targetMonth + 1, 0).getDate();
        const result = new Date(y, targetMonth, Math.min(d, lastDay));
        const yy = result.getFullYear();
        const mm = String(result.getMonth() + 1).padStart(2, '0');
        const dd = String(result.getDate()).padStart(2, '0');
        return `${yy}-${mm}-${dd}`;
    }

    function calculateMember() {
        const planOption = memberSelectedOption('memberPlan');
        const classOption = memberSelectedOption('memberClass');
        const startDate = document.getElementById('memberStartDate')?.value || new Date().toISOString().slice(0, 10);

        const planAmount = memberMoney(planOption?.dataset?.amount || 0);
        const classAmount = memberMoney(classOption?.dataset?.fees || 0);
        const regFee = memberMoney(document.getElementById('memberRegFee')?.value || 0);
        let paid = memberMoney(document.getElementById('memberPaid')?.value || 0);
        const total = planAmount + classAmount + regFee;

        if (paid > total) {
            paid = total;
            const paidInput = document.getElementById('memberPaid');
            if (paidInput) paidInput.value = String(total);
        }

        const expiryInput = document.getElementById('memberExpiryDate');
        const pkg = planOption?.dataset?.package || '';
        const months = memberPackageMonths(pkg);
        if (expiryInput) {
            if (String(pkg).toLowerCase().includes('week')) {
                const dt = new Date(startDate);
                const weeks = parseInt(String(pkg).match(/(\d+)/)?.[1] || '1', 10);
                dt.setDate(dt.getDate() + (weeks * 7));
                expiryInput.value = dt.toISOString().slice(0, 10);
            } else if (String(pkg).toLowerCase().includes('day')) {
                const dt = new Date(startDate);
                const days = parseInt(String(pkg).match(/(\d+)/)?.[1] || '1', 10);
                dt.setDate(dt.getDate() + days);
                expiryInput.value = dt.toISOString().slice(0, 10);
            } else {
                expiryInput.value = addMonthsNoOverflow(startDate, months);
            }
        }

        const due = Math.max(0, total - paid);
        const setText = (id, text) => { const el = document.getElementById(id); if (el) el.textContent = text; };
        setText('sPlan', `₹${planAmount.toFixed(2)}`);
        setText('sClass', `₹${classAmount.toFixed(2)}`);
        setText('sPaid', `-₹${paid.toFixed(2)}`);
        setText('sDue', `₹${due.toFixed(2)}`);
    }

    function memberSetInvalid(id, invalid = true) {
        const el = document.getElementById(id);
        if (el) el.classList.toggle('is-invalid', invalid);
    }

    async function submitMember() {
        ['memberName','memberPhone','memberPlan'].forEach(id => memberSetInvalid(id, false));
        calculateMember();

        const name = (document.getElementById('memberName')?.value || '').trim();
        const phone = (document.getElementById('memberPhone')?.value || '').replace(/[^0-9]/g, '');
        const planId = document.getElementById('memberPlan')?.value || '';

        let hasError = false;
        if (!name) { memberSetInvalid('memberName'); hasError = true; }
        if (!/^[6-9][0-9]{9}$/.test(phone)) { memberSetInvalid('memberPhone'); hasError = true; }
        if (!planId) { memberSetInvalid('memberPlan'); hasError = true; }
        if (hasError) {
            showToast('Please enter name, valid phone and membership plan.', 'error');
            return;
        }

        const btn = document.getElementById('addMemberBtn');
        const oldHtml = btn ? btn.innerHTML : '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Adding...';
        }

        const payload = {
            name,
            phone_number: phone,
            email: (document.getElementById('memberEmail')?.value || '').trim(),
            gender: document.getElementById('memberGender')?.value || 'male',
            dob: document.getElementById('memberDob')?.value || '',
            address: (document.getElementById('memberAddress')?.value || '').trim(),
            membership_plan: planId,
            class_id: document.getElementById('memberClass')?.value || 0,
            trainer_assign: document.getElementById('memberTrainer')?.value || 0,
            membership_start_date: document.getElementById('memberStartDate')?.value || '',
            membership_expiry_date: document.getElementById('memberExpiryDate')?.value || '',
            registration_fee: document.getElementById('memberRegFee')?.value || 0,
            paid_amount: document.getElementById('memberPaid')?.value || 0,
            payment_method: document.getElementById('memberMethod')?.value || 'cash',
        };

        try {
            const res = await fetch('{{ route('panel.members.store') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify(payload),
            });

            const data = await res.json().catch(() => ({}));
            if (!res.ok || data.success === false) {
                const errors = data.errors ? Object.values(data.errors).flat().join(' ') : '';
                throw new Error(data.error || data.message || errors || 'Failed to add member');
            }

            showToast(data.message || 'Member added successfully', 'success');
            const modalEl = document.getElementById('addMemberModal');
            const modal = modalEl && window.bootstrap ? bootstrap.Modal.getInstance(modalEl) : null;
            if (modal) modal.hide();
            resetMemberModal();
            loadMembers();
        } catch (e) {
            showToast(e.message || 'Failed to add member', 'error');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = oldHtml;
            }
        }
    }

    function resetMemberModal() {
        ['memberName','memberPhone','memberEmail','memberDob','memberAddress'].forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
        const plan = document.getElementById('memberPlan'); if (plan) plan.value = '';
        const cls = document.getElementById('memberClass'); if (cls) cls.value = '0';
        const trainer = document.getElementById('memberTrainer'); if (trainer) trainer.value = '0';
        const gender = document.getElementById('memberGender'); if (gender) gender.value = 'male';
        const method = document.getElementById('memberMethod'); if (method) method.value = 'cash';
        const reg = document.getElementById('memberRegFee'); if (reg) reg.value = '0';
        const paid = document.getElementById('memberPaid'); if (paid) paid.value = '0';
        const start = document.getElementById('memberStartDate'); if (start) start.value = new Date().toISOString().slice(0, 10);
        const expiry = document.getElementById('memberExpiryDate'); if (expiry) expiry.value = '';
        ['memberName','memberPhone','memberPlan'].forEach(id => memberSetInvalid(id, false));
        calculateMember();
    }

    document.addEventListener('DOMContentLoaded', function () {
        initMembersPage();
        calculateMember();
        const modalEl = document.getElementById('addMemberModal');
        if (modalEl) modalEl.addEventListener('shown.bs.modal', calculateMember);
    });
    window.loadMembers = loadMembers;
    window.calculateMember = calculateMember;
    window.submitMember = submitMember;

    // ═══════════════════════════════════════════════════════
    // BULK CSV IMPORT (AJAX)
    // ═══════════════════════════════════════════════════════
    const bulkForm = document.getElementById('bulkImportForm');
    if (bulkForm) {
        bulkForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            const fileInput = document.getElementById('csvFileInput');
            const btn = document.getElementById('bulkImportBtn');
            const progress = document.getElementById('bulkImportProgress');
            const result = document.getElementById('bulkImportResult');

            if (!fileInput.files.length) {
                showToast('Please select a CSV file', 'error');
                return;
            }

            const formData = new FormData(bulkForm);
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Importing...';
            progress.classList.remove('d-none');
            result.classList.add('d-none');
            result.innerHTML = '';

            try {
                const res = await fetch('{{ route('panel.members.import') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: formData
                });

                const data = await res.json();
                if (!res.ok || !data.success) {
                    throw data;
                }

                renderBulkImportResult(data);
                showToast(data.message || 'Import completed', data.failed_count > 0 ? 'error' : 'success');
                if (data.imported_count > 0) {
                    loadMembers();
                }
            } catch (err) {
                const msg = err?.error || err?.message || 'Import failed. Please check CSV format.';
                result.classList.remove('d-none');
                result.innerHTML = `<div class="alert alert-danger mb-0"><i class="bi bi-exclamation-triangle me-2"></i>${escapeHtml(msg)}</div>`;
                showToast(msg, 'error');
            } finally {
                progress.classList.add('d-none');
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-cloud-arrow-up me-2"></i> Validate & Import';
            }
        });
    }

    function renderBulkImportResult(data) {
        const result = document.getElementById('bulkImportResult');
        result.classList.remove('d-none');

        const failedRows = data.failed || [];
        const importedRows = data.imported || [];
        const failedCsv = data.failed_csv || '';

        let failedHtml = '';
        if (failedRows.length) {
            failedHtml = `
                <div class="mt-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0 text-danger"><i class="bi bi-x-circle me-1"></i> Failed Rows (${failedRows.length})</h6>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="downloadFailedRowsCsv()">
                            <i class="bi bi-download me-1"></i> Download Failed Rows CSV
                        </button>
                    </div>
                    <div class="import-error-list">
                        ${failedRows.slice(0, 80).map(r => `
                            <div class="p-3 border-bottom">
                                <div class="fw-bold">Row ${r.row}: ${escapeHtml(r.data?.name || '-')} (${escapeHtml(r.data?.phone_number || '-')})</div>
                                <div class="small text-danger">${(r.errors || []).map(escapeHtml).join('<br>')}</div>
                            </div>
                        `).join('')}
                        ${failedRows.length > 80 ? `<div class="p-3 text-muted small">Showing first 80 errors. Download CSV for all failed rows.</div>` : ''}
                    </div>
                </div>`;
        }

        result.dataset.failedCsv = failedCsv;
        result.innerHTML = `
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="import-result-card text-center">
                        <div class="text-muted small">Total Rows</div>
                        <div class="fs-3 fw-bold">${data.total_rows || 0}</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="import-result-card text-center" style="border-color:#bbf7d0;background:#f0fdf4;">
                        <div class="text-success small fw-bold">Imported</div>
                        <div class="fs-3 fw-bold text-success">${data.imported_count || 0}</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="import-result-card text-center" style="border-color:#fecaca;background:#fff7f7;">
                        <div class="text-danger small fw-bold">Failed</div>
                        <div class="fs-3 fw-bold text-danger">${data.failed_count || 0}</div>
                    </div>
                </div>
            </div>
            ${importedRows.length ? `<div class="alert alert-success mt-3 mb-0"><i class="bi bi-check-circle me-2"></i>${importedRows.length} members imported with invoice creation.</div>` : ''}
            ${failedHtml}
        `;
    }

    function downloadFailedRowsCsv() {
        const result = document.getElementById('bulkImportResult');
        const csv = result?.dataset?.failedCsv || '';
        if (!csv) {
            showToast('No failed rows CSV available', 'error');
            return;
        }
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'gymxbook_failed_import_rows.csv';
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>'"]/g, function(c) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c];
        });
    }
</script>
@endpush