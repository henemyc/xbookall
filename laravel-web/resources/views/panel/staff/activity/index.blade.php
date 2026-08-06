@extends('panel.layouts.app')

@section('title', 'Staff Activity')

@push('styles')
<style>
    .activity-hero {
        background: linear-gradient(135deg, #0f172a, #1e293b 58%, #7c2d12);
        color: #fff;
        border-radius: 24px;
        padding: 24px;
        position: relative;
        overflow: hidden;
        box-shadow: 0 18px 45px rgba(15, 23, 42, .16);
    }
    .activity-hero::after {
        content: '';
        position: absolute;
        right: -70px;
        top: -80px;
        width: 230px;
        height: 230px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(255,255,255,.18), transparent 65%);
    }
    .activity-icon {
        width: 46px;
        height: 46px;
        border-radius: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        background: rgba(255,107,44,.12);
        color: var(--primary);
    }
    .timeline-dot {
        width: 11px;
        height: 11px;
        border-radius: 50%;
        background: var(--primary);
        box-shadow: 0 0 0 5px rgba(255,107,44,.12);
    }
    .json-box {
        background: #0f172a;
        color: #e5e7eb;
        border-radius: 12px;
        padding: 12px;
        white-space: pre-wrap;
        font-size: 12px;
        max-height: 220px;
        overflow: auto;
    }
</style>
@endpush

@section('content')
<div class="activity-hero mb-4">
    <div class="position-relative" style="z-index:1">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <div style="width:58px;height:58px;border-radius:18px;background:rgba(255,255,255,.14);display:flex;align-items:center;justify-content:center;font-size:28px;">
                    <i class="bi bi-clock-history"></i>
                </div>
                <div>
                    <h4 class="mb-1" style="font-family:'Space Grotesk';font-weight:800;">Staff Activity</h4>
                    <div style="opacity:.72;font-size:13px;">Track staff logins, password changes and operational actions across your gym.</div>
                </div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('panel.staff.users.index') }}" class="btn btn-light"><i class="bi bi-people me-2"></i>Staff Users</a>
                <a href="{{ route('panel.staff.roles.index') }}" class="btn btn-outline-light"><i class="bi bi-shield-lock me-2"></i>Roles</a>
            </div>
        </div>
    </div>
</div>

@if(!empty($missingTable))
    <div class="alert alert-warning rounded-4">
        <strong>Activity log table is missing.</strong> Open Super Admin → System Update → Update Now, or run migrations, then refresh this page.
    </div>
@endif

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="table-card h-100"><div class="d-flex align-items-center gap-3"><div class="activity-icon"><i class="bi bi-list-check"></i></div><div><div class="text-muted small">Total Activity</div><h3 class="mb-0">{{ $stats['total'] ?? 0 }}</h3></div></div></div></div>
    <div class="col-md-3"><div class="table-card h-100"><div class="d-flex align-items-center gap-3"><div class="activity-icon" style="background:rgba(22,199,132,.12);color:var(--success);"><i class="bi bi-calendar-day"></i></div><div><div class="text-muted small">Today</div><h3 class="mb-0 text-success">{{ $stats['today'] ?? 0 }}</h3></div></div></div></div>
    <div class="col-md-3"><div class="table-card h-100"><div class="d-flex align-items-center gap-3"><div class="activity-icon" style="background:rgba(59,158,255,.12);color:var(--info);"><i class="bi bi-person-badge"></i></div><div><div class="text-muted small">Staff Actions</div><h3 class="mb-0 text-info">{{ $stats['staff_actions'] ?? 0 }}</h3></div></div></div></div>
    <div class="col-md-3"><div class="table-card h-100"><div class="d-flex align-items-center gap-3"><div class="activity-icon" style="background:rgba(255,167,38,.12);color:var(--warning);"><i class="bi bi-box-arrow-in-right"></i></div><div><div class="text-muted small">Logins</div><h3 class="mb-0 text-warning">{{ $stats['login_count'] ?? 0 }}</h3></div></div></div></div>
</div>

<div class="table-card mb-4">
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label">Staff User</label>
            <select name="staff_user_id" class="form-select">
                <option value="0">All users</option>
                @foreach($staffUsers as $staff)
                    <option value="{{ $staff->id }}" @selected((int) request('staff_user_id') === $staff->id)>{{ $staff->name }}{{ $staff->phone_number ? ' - '.$staff->phone_number : '' }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Module</label>
            <select name="module" class="form-select">
                <option value="">All modules</option>
                @foreach($modules as $module)
                    <option value="{{ $module }}" @selected(request('module') === $module)>{{ ucfirst(str_replace('_', ' ', $module)) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">From</label>
            <input type="date" name="from" class="form-control" value="{{ request('from') }}">
        </div>
        <div class="col-md-2">
            <label class="form-label">To</label>
            <input type="date" name="to" class="form-control" value="{{ request('to') }}">
        </div>
        <div class="col-md-3">
            <label class="form-label">Search</label>
            <div class="d-flex gap-2">
                <input type="text" name="search" class="form-control" value="{{ request('search') }}" placeholder="Description, IP, action...">
                <button class="btn btn-primary"><i class="bi bi-search"></i></button>
                <a href="{{ route('panel.staff.activity.index') }}" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
            </div>
        </div>
    </form>
</div>

<div class="table-card">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h6 class="mb-1"><i class="bi bi-activity me-2" style="color:var(--primary)"></i> Activity Timeline</h6>
            <small class="text-muted">Latest actions first.</small>
        </div>
        <span class="badge bg-primary">{{ method_exists($logs, 'total') ? $logs->total() : 0 }} records</span>
    </div>

    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th style="width:34px;"></th>
                    <th>Activity</th>
                    <th>User</th>
                    <th>Module</th>
                    <th>Record</th>
                    <th>IP / Device</th>
                    <th>Date</th>
                    <th width="90"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    @php
                        $actionColor = match($log->action) {
                            'login', 'created', 'activated' => 'success',
                            'deleted', 'deactivated' => 'danger',
                            'updated', 'password_changed' => 'warning',
                            default => 'primary',
                        };
                    @endphp
                    <tr>
                        <td><div class="timeline-dot"></div></td>
                        <td>
                            <div class="fw-semibold">{{ $log->description ?: ucfirst(str_replace('_', ' ', $log->action)) }}</div>
                            <small class="text-muted">{{ $log->action }}</small>
                        </td>
                        <td>
                            <div>{{ $log->user?->name ?? 'System/Unknown' }}</div>
                            <small class="text-muted">{{ $log->user_type ?: ($log->user?->type ?? '-') }}</small>
                        </td>
                        <td><span class="badge bg-{{ $actionColor }}">{{ ucfirst($log->module) }}</span></td>
                        <td>
                            <small>{{ $log->record_type ?: '-' }}</small>
                            @if($log->record_id)<br><code>#{{ $log->record_id }}</code>@endif
                        </td>
                        <td>
                            <div><small>{{ $log->ip ?: '-' }}</small></div>
                            <small class="text-muted">{{ \Illuminate\Support\Str::limit($log->user_agent ?: '', 36) }}</small>
                        </td>
                        <td><small>{{ $log->created_at ? $log->created_at->format('d M Y, h:i A') : '-' }}</small></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#activityModal{{ $log->id }}">View</button>
                        </td>
                    </tr>

                    <div class="modal fade" id="activityModal{{ $log->id }}" tabindex="-1">
                        <div class="modal-dialog modal-lg modal-dialog-scrollable">
                            <div class="modal-content" style="border-radius:20px;">
                                <div class="modal-header">
                                    <h5 class="modal-title">Activity #{{ $log->id }}</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-6"><div class="text-muted small">User</div><strong>{{ $log->user?->name ?? 'Unknown' }}</strong></div>
                                        <div class="col-md-6"><div class="text-muted small">Date</div><strong>{{ $log->created_at ? $log->created_at->format('d M Y, h:i A') : '-' }}</strong></div>
                                        <div class="col-md-6"><div class="text-muted small">Module / Action</div><strong>{{ $log->module }}.{{ $log->action }}</strong></div>
                                        <div class="col-md-6"><div class="text-muted small">IP</div><strong>{{ $log->ip ?: '-' }}</strong></div>
                                    </div>
                                    <div class="mb-3"><div class="text-muted small">Description</div><div>{{ $log->description ?: '-' }}</div></div>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="text-muted small mb-1">Before</div>
                                            <div class="json-box">{{ $log->before_json ? json_encode($log->before_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : 'No before data' }}</div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="text-muted small mb-1">After</div>
                                            <div class="json-box">{{ $log->after_json ? json_encode($log->after_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : 'No after data' }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <tr><td colspan="8" class="text-center py-5 text-muted">No activity found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $logs->links() }}</div>
</div>
@endsection
