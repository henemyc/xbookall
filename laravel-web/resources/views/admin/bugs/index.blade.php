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
    <div class="d-flex justify-content-between align-items-center mb-3">
        <form class="d-flex" method="GET">
            <input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Search title, gym or email..." style="width: 280px;">
            <button type="submit" class="btn btn-primary btn-sm ms-2">
                <i class="bi bi-search"></i>
            </button>
        </form>
        <span class="text-muted small">{{ $reports->total() }} reports</span>
    </div>

    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
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
                    <td colspan="7" class="text-center py-5 text-muted">
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
@endsection
