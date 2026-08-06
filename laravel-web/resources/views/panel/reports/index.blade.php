@extends('panel.layouts.app')

@section('title', 'Reports')

@section('content')
<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-bar-chart-line me-2"></i> Reports & Analytics</h5>
        
        <!-- Month / Year Filters -->
        <div class="d-flex gap-2 align-items-center">
            <select id="reportMonth" class="form-select form-select-sm" style="width: 130px;">
                @for($m=1; $m<=12; $m++)
                    <option value="{{ $m }}" {{ $m == ($month ?? date('m')) ? 'selected' : '' }}>
                        {{ \Carbon\Carbon::create(2024, $m)->format('F') }}
                    </option>
                @endfor
            </select>
            <select id="reportYear" class="form-select form-select-sm" style="width: 90px;">
                @for($y = date('Y'); $y >= 2020; $y--)
                    <option value="{{ $y }}" {{ $y == ($year ?? date('Y')) ? 'selected' : '' }}>{{ $y }}</option>
                @endfor
            </select>
            <button id="applyReportFilters" class="btn btn-sm btn-primary">Apply</button>
        </div>
    </div>

    <div class="card-body">
        <!-- Loading overlay -->
        <div id="reportsLoading" class="d-none position-absolute w-100 h-100" style="background:rgba(255,255,255,0.7); z-index:10; top:0; left:0;">
            <div class="d-flex justify-content-center align-items-center h-100">
                <div class="text-center">
                    <div class="spinner-border text-primary"></div>
                    <div class="small mt-2">Loading reports...</div>
                </div>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="row g-3 mb-4" id="kpiCards">
            <div class="col-md-3">
                <div class="p-3 border rounded bg-white">
                    <div class="text-muted small">Active Members</div>
                    <div class="fw-bold fs-3" id="activeCount">{{ $activeCount ?? 0 }}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-3 border rounded bg-white">
                    <div class="text-muted small">New Members (This Month)</div>
                    <div class="fw-bold fs-3 text-success" id="newMembersCount">{{ $newMembersCount ?? 0 }}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-3 border rounded bg-white">
                    <div class="text-muted small">Expiring in 7 Days</div>
                    <div class="fw-bold fs-3 text-warning" id="expiringCount">{{ $expiringCount ?? 0 }}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-3 border rounded bg-white">
                    <div class="text-muted small">Expired</div>
                    <div class="fw-bold fs-3 text-danger" id="expiredCount">{{ $expiredCount ?? 0 }}</div>
                </div>
            </div>
        </div>

        <!-- Income vs Expense Summary + Graph -->
        <div class="row g-3 mb-4">
            <div class="col-md-5">
                <div class="card h-100">
                    <div class="card-body">
                        <h6 class="mb-3">Income vs Expense</h6>
                        <div class="row">
                            <div class="col-6">
                                <div class="text-success small">Income</div>
                                <div class="fw-bold fs-4 text-success" id="monthlyIncome">₹{{ number_format($monthlyIncome ?? 0) }}</div>
                            </div>
                            <div class="col-6">
                                <div class="text-danger small">Expense</div>
                                <div class="fw-bold fs-4 text-danger" id="monthlyExpense">₹{{ number_format($monthlyExpense ?? 0) }}</div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="small text-muted">Net</div>
                            <div class="fw-bold fs-5" id="netBalance" style="color: {{ ($monthlyIncome ?? 0) - ($monthlyExpense ?? 0) >= 0 ? '#16c784' : '#ff4d4f' }}">
                                ₹{{ number_format(abs(($monthlyIncome ?? 0) - ($monthlyExpense ?? 0))) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-7">
                <div class="card h-100">
                    <div class="card-body">
                        <h6 class="mb-2">Income vs Expense (Last 6 Months)</h6>
                        <canvas id="incomeExpenseChart" height="90"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <!-- New Members This Month -->
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0">New Members This Month</h6>
                            <span class="badge bg-success" id="newMembersBadge">{{ $newMembersCount ?? 0 }}</span>
                        </div>
                        <div id="newMembersList" class="small" style="max-height: 240px; overflow-y: auto;">
                            @forelse($newMembersList ?? [] as $member)
                                <div class="d-flex justify-content-between py-1 border-bottom">
                                    <div>
                                        <strong>{{ $member->name }}</strong><br>
                                        <small class="text-muted">{{ $member->phone_number }}</small>
                                    </div>
                                    <small class="text-muted">{{ \Carbon\Carbon::parse($member->created_at)->format('d M') }}</small>
                                </div>
                            @empty
                                <div class="text-muted py-3">No new members this month</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <!-- Upcoming Payments (Next 7 Days) -->
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-body">
                        <h6 class="mb-2">Upcoming Payments (Next 7 Days)</h6>
                        <div id="upcomingPaymentsList" style="max-height: 240px; overflow-y: auto;">
                            @forelse($upcomingPayments ?? [] as $inv)
                                <div class="d-flex justify-content-between align-items-center py-1 border-bottom small">
                                    <div>
                                        <strong>{{ $inv->user->name ?? 'N/A' }}</strong><br>
                                        <span class="text-muted">#{{ $inv->invoice_id }}</span>
                                    </div>
                                    <div class="text-end">
                                        <div class="text-danger fw-bold">₹{{ number_format($inv->items->sum('amount') - $inv->payments->sum('amount')) }}</div>
                                        <small class="text-muted">{{ \Carbon\Carbon::parse($inv->invoice_due_date)->format('d M') }}</small>
                                    </div>
                                </div>
                            @empty
                                <div class="text-muted py-3">No upcoming payments</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Attendance Graph -->
        <div class="row g-3 mt-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h6 class="mb-3">Attendance (Last 14 Days)</h6>
                        <canvas id="attendanceChart" height="70"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Plan Distribution -->
        <div class="row g-3 mt-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h6 class="mb-3">Plan Distribution</h6>
                        <div class="row" id="planDistribution">
                            @forelse($planDistribution ?? [] as $plan)
                                <div class="col-md-3 mb-2">
                                    <div class="p-2 border rounded">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <strong>{{ $plan->title }}</strong><br>
                                                <small class="text-muted">₹{{ $plan->amount }}</small>
                                            </div>
                                            <div class="text-end">
                                                <span class="badge bg-primary">{{ $plan->member_count }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="text-muted">No plans found</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    let incomeExpenseChartInstance = null;
    let attendanceChartInstance = null;

    async function loadReportsData(month = null, year = null) {
        const loading = document.getElementById('reportsLoading');
        loading.classList.remove('d-none');

        const params = new URLSearchParams();
        if (month) params.append('month', month);
        if (year) params.append('year', year);

        try {
            const res = await fetch(`/panel/reports?${params.toString()}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            });
            const json = await res.json();

            if (json.success && json.data) {
                updateReportsUI(json.data);
            }
        } catch (e) {
            console.error(e);
            window.showToast('Failed to load reports', 'error');
        } finally {
            loading.classList.add('d-none');
        }
    }

    function updateReportsUI(data) {
        // KPIs
        document.getElementById('activeCount').textContent = data.activeCount ?? 0;
        document.getElementById('newMembersCount').textContent = data.newMembersCount ?? 0;
        document.getElementById('expiringCount').textContent = data.expiringCount ?? 0;
        document.getElementById('expiredCount').textContent = data.expiredCount ?? 0;

        // Income/Expense
        document.getElementById('monthlyIncome').textContent = '₹' + Number(data.monthlyIncome).toLocaleString();
        document.getElementById('monthlyExpense').textContent = '₹' + Number(data.monthlyExpense).toLocaleString();
        
        const net = (data.monthlyIncome ?? 0) - (data.monthlyExpense ?? 0);
        const netEl = document.getElementById('netBalance');
        netEl.textContent = '₹' + Math.abs(net).toLocaleString();
        netEl.style.color = net >= 0 ? '#16c784' : '#ff4d4f';

        // New Members List
        const newList = document.getElementById('newMembersList');
        newList.innerHTML = '';
        if (data.newMembersList && data.newMembersList.length > 0) {
            data.newMembersList.forEach(m => {
                const div = document.createElement('div');
                div.className = 'd-flex justify-content-between py-1 border-bottom small';
                div.innerHTML = `
                    <div>
                        <strong>${m.name}</strong><br>
                        <small class="text-muted">${m.phone_number || ''}</small>
                    </div>
                    <small class="text-muted">${new Date(m.created_at).toLocaleDateString('en-GB', {day:'2-digit', month:'short'})}</small>
                `;
                newList.appendChild(div);
            });
        } else {
            newList.innerHTML = '<div class="text-muted py-2">No new members</div>';
        }

        // Upcoming Payments
        const upcoming = document.getElementById('upcomingPaymentsList');
        upcoming.innerHTML = '';
        if (data.upcomingPayments && data.upcomingPayments.length > 0) {
            data.upcomingPayments.forEach(inv => {
                const div = document.createElement('div');
                div.className = 'd-flex justify-content-between align-items-center py-1 border-bottom small';
                const due = inv.invoice_due_date ? new Date(inv.invoice_due_date).toLocaleDateString('en-GB', {day:'2-digit', month:'short'}) : '';
                const dueAmount = (inv.items?.reduce((s,i)=>s+i.amount,0) || 0) - (inv.payments?.reduce((s,p)=>s+p.amount,0) || 0);
                div.innerHTML = `
                    <div>
                        <strong>${inv.user?.name || 'N/A'}</strong><br>
                        <span class="text-muted">#${inv.invoice_id}</span>
                    </div>
                    <div class="text-end">
                        <div class="text-danger fw-bold">₹${dueAmount}</div>
                        <small class="text-muted">${due}</small>
                    </div>
                `;
                upcoming.appendChild(div);
            });
        } else {
            upcoming.innerHTML = '<div class="text-muted py-2">No upcoming payments</div>';
        }

        // Plan Distribution — replace old DOM so AJAX filter never leaves stale/all-gym plans visible
        const planBox = document.getElementById('planDistribution');
        if (planBox) {
            planBox.innerHTML = '';
            if (data.planDistribution && data.planDistribution.length > 0) {
                data.planDistribution.forEach(plan => {
                    const div = document.createElement('div');
                    div.className = 'col-md-3 mb-2';
                    div.innerHTML = `
                        <div class="p-2 border rounded">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <strong>${plan.title || ''}</strong><br>
                                    <small class="text-muted">₹${Number(plan.amount || 0).toLocaleString()}</small>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-primary">${plan.member_count || 0}</span>
                                </div>
                            </div>
                        </div>
                    `;
                    planBox.appendChild(div);
                });
            } else {
                planBox.innerHTML = '<div class="text-muted">No plans found</div>';
            }
        }

        // Charts
        renderIncomeExpenseChart(data.incomeExpenseGraph || []);
        renderAttendanceChart(data.attendanceGraph || []);
    }

    function renderIncomeExpenseChart(graphData) {
        const ctx = document.getElementById('incomeExpenseChart');
        if (incomeExpenseChartInstance) incomeExpenseChartInstance.destroy();

        const labels = graphData.map(d => d.label);
        const income = graphData.map(d => d.income);
        const expense = graphData.map(d => d.expense);

        incomeExpenseChartInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    { label: 'Income', data: income, backgroundColor: '#16c784' },
                    { label: 'Expense', data: expense, backgroundColor: '#ff4d4f' }
                ]
            },
            options: {
                responsive: true,
                scales: { y: { beginAtZero: true } }
            }
        });
    }

    function renderAttendanceChart(attData) {
        const ctx = document.getElementById('attendanceChart');
        if (attendanceChartInstance) attendanceChartInstance.destroy();

        const labels = attData.map(d => d.label);
        const counts = attData.map(d => d.count);

        attendanceChartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    label: 'Attendance',
                    data: counts,
                    borderColor: '#3b9eff',
                    backgroundColor: 'rgba(59, 158, 255, 0.1)',
                    tension: 0.3,
                    fill: true
                }]
            },
            options: { responsive: true, scales: { y: { beginAtZero: true } } }
        });
    }

    // Filter handler
    function attachReportFilters() {
        document.getElementById('applyReportFilters').addEventListener('click', () => {
            const month = document.getElementById('reportMonth').value;
            const year = document.getElementById('reportYear').value;
            loadReportsData(month, year);
        });

        // Auto reload on month/year change (optional)
        ['reportMonth', 'reportYear'].forEach(id => {
            document.getElementById(id).addEventListener('change', () => {
                const month = document.getElementById('reportMonth').value;
                const year = document.getElementById('reportYear').value;
                loadReportsData(month, year);
            });
        });
    }

    // Init
    document.addEventListener('DOMContentLoaded', () => {
        attachReportFilters();

        // If data already present from server, render charts
        const initialData = {
            incomeExpenseGraph: @json($incomeExpenseGraph ?? []),
            attendanceGraph: @json($attendanceGraph ?? [])
        };

        if (initialData.incomeExpenseGraph.length) {
            renderIncomeExpenseChart(initialData.incomeExpenseGraph);
        }
        if (initialData.attendanceGraph.length) {
            renderAttendanceChart(initialData.attendanceGraph);
        }
    });
</script>
@endpush
