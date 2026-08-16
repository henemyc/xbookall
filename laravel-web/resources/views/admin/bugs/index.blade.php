@extends('admin.layouts.app')

@section('title', 'Bug Reports')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="mb-0">Bug Reports</h5>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.bugs.index') }}" class="btn btn-outline-secondary btn-sm">All</a>
        <a href="{{ route('admin.bugs.index', ['status' => 'open']) }}" class="btn btn-sm {{ request('status') == 'open' ? 'btn-warning' : 'btn-outline-warning' }}">Open</a>
        <a href="{{ route('admin.bugs.index', ['status' => 'in_progress']) }}" class="btn btn-sm {{ request('status') == 'in_progress' ? 'btn-info' : 'btn-outline-info' }}">In Progress</a>
        <a href="{{ route('admin.bugs.index', ['status' => 'resolved']) }}" class="btn btn-sm {{ request('status') == 'resolved' ? 'btn-success' : 'btn-outline-success' }}">Resolved</a>
    </div>
</div>

<div class="table-card">
    <form id="bulkBugForm" action="{{ route('admin.bugs.bulk') }}" method="POST">
        @csrf
    </form>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <form class="d-flex" method="GET">
            <input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Search title, gym or email..." style="width: 280px;">
            <button type="submit" class="btn btn-primary btn-sm ms-2">
                <i class="bi bi-search"></i>
            </button>
        </form>
        <div class="d-flex align-items-center gap-2">
            <select class="form-select form-select-sm" form="bulkBugForm" name="action" id="bulkBugAction" style="width:170px">
                <option value="">Bulk action...</option>
                <option value="open">Mark Open</option>
                <option value="in_progress">Mark In Progress</option>
                <option value="resolved">Mark Resolved</option>
                <option value="delete">Delete Selected</option>
            </select>
            <button class="btn btn-sm btn-outline-primary" form="bulkBugForm" id="applyBulkBugs" type="submit">Apply</button>
            <span class="text-muted small">{{ $reports->total() }} reports</span>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th width="35"><input type="checkbox" id="selectAllBugs"></th>
                    <th>ID</th>
                    <th>Gym / User</th>
                    <th>Title</th>
                    <th>Status</th>
                    <th>Has Screenshot</th>
                    <th>Date</th>
                    <th width="80"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($reports as $report)
                <tr>
                    <td><input class="form-check-input bug-checkbox" form="bulkBugForm" type="checkbox" name="ids[]" value="{{ $report->id }}"></td>
                    <td>#{{ $report->id }}</td>
                    <td>
                        <div>
                            <strong>{{ $report->gym_name ?? '—' }}</strong><br>
                            <small class="text-muted">{{ $report->email ?? $report->user?->email }}</small>
                        </div>
                    </td>
                    <td>
                        <div style="max-width: 280px;">
                            {{ Str::limit($report->title, 60) }}
                        </div>
                    </td>
                    <td>
                        @php
                            $statusClass = match($report->status) {
                                'open' => 'bg-warning',
                                'in_progress' => 'bg-info',
                                'resolved' => 'bg-success',
                                default => 'bg-secondary'
                            };
                        @endphp
                        <span class="badge {{ $statusClass }}">{{ ucfirst(str_replace('_', ' ', $report->status)) }}</span>
                    </td>
                    <td>
                        @if($report->has_screenshot)
                            <span class="badge bg-primary"><i class="bi bi-image me-1"></i> Yes</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        <small>{{ $report->created_at->format('d M Y, h:i A') }}</small>
                    </td>
                    <td>
                        <a href="{{ route('admin.bugs.show', $report->id) }}" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-eye"></i> View
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center py-5 text-muted">
                        No bug reports found.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $reports->links() }}
    </div>
</div>

@push('scripts')
<script>
const selectAllBugs = document.getElementById('selectAllBugs');
selectAllBugs?.addEventListener('change', function () {
    document.querySelectorAll('.bug-checkbox').forEach(box => box.checked = this.checked);
});
document.getElementById('bulkBugForm')?.addEventListener('submit', function (event) {
    const selected = document.querySelectorAll('.bug-checkbox:checked').length;
    const action = document.getElementById('bulkBugAction').value;
    if (!selected || !action) { event.preventDefault(); alert('Select at least one bug report and a bulk action.'); return; }
    if (action === 'delete' && !confirm('Delete selected bug reports permanently?')) event.preventDefault();
});
</script>
@endpush
@endsection
