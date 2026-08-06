@extends('admin.layouts.app')

@section('title', 'Bug Report #' . $report->id)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="{{ route('admin.bugs.index') }}" class="btn btn-sm btn-outline-secondary me-2">
            <i class="bi bi-arrow-left"></i> Back to List
        </a>
        <span class="h5 mb-0">Bug Report #{{ $report->id }}</span>
    </div>
    <span class="badge {{ $report->status === 'resolved' ? 'bg-success' : ($report->status === 'in_progress' ? 'bg-info' : 'bg-warning') }} fs-6">
        {{ ucfirst(str_replace('_', ' ', $report->status)) }}
    </span>
</div>

<div class="row">
    <!-- Report Details -->
    <div class="col-lg-7">
        <div class="table-card mb-4">
            <h6 class="mb-3"><i class="bi bi-info-circle me-2"></i>Report Details</h6>
            
            <div class="mb-3">
                <label class="form-label text-muted small">Title</label>
                <div class="fs-5 fw-semibold">{{ $report->title }}</div>
            </div>

            <div class="mb-3">
                <label class="form-label text-muted small">Description</label>
                <div style="white-space: pre-wrap; background: #f8fafc; padding: 12px; border-radius: 8px;">
                    {{ $report->description }}
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted small">Gym Name</label>
                    <div><strong>{{ $report->gym_name ?? '—' }}</strong></div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label text-muted small">Email</label>
                    <div>{{ $report->email ?? '—' }}</div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <label class="form-label text-muted small">Submitted By</label>
                    <div>{{ $report->user?->name ?? 'Unknown' }} (ID: {{ $report->user_id }})</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label text-muted small">Date</label>
                    <div>{{ $report->created_at->format('d M Y, h:i A') }}</div>
                </div>
            </div>

            @if($report->has_screenshot && $report->screenshot_path)
            @php
                $shotPath = ltrim($report->screenshot_path, '/');
                $shotUrl = \Illuminate\Support\Str::startsWith($shotPath, 'uploads/')
                    ? asset($shotPath)
                    : asset('storage/' . $shotPath);
            @endphp
            <div class="mt-3">
                <label class="form-label text-muted small">Screenshot</label>
                <div class="border rounded-3 p-2 bg-light">
                    <a href="{{ $shotUrl }}" target="_blank">
                        <img src="{{ $shotUrl }}" alt="Bug screenshot" style="max-width:100%;max-height:360px;border-radius:10px;object-fit:contain;">
                    </a>
                    <div class="mt-2">
                        <a href="{{ $shotUrl }}" target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-image me-1"></i> Open Full Screenshot
                        </a>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>

    <!-- Update Form -->
    <div class="col-lg-5">
        <div class="table-card">
            <h6 class="mb-3"><i class="bi bi-pencil-square me-2"></i>Update & Reply</h6>

            <form action="{{ route('admin.bugs.update', $report->id) }}" method="POST">
                @csrf

                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select" required>
                        <option value="open" {{ $report->status == 'open' ? 'selected' : '' }}>Open</option>
                        <option value="in_progress" {{ $report->status == 'in_progress' ? 'selected' : '' }}>In Progress</option>
                        <option value="resolved" {{ $report->status == 'resolved' ? 'selected' : '' }}>Resolved</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Admin Notes / Reply <small class="text-muted">(sent to gym owner)</small></label>
                    <textarea name="admin_notes" class="form-control" rows="6" placeholder="Write your response here...">{{ $report->admin_notes }}</textarea>
                </div>

                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-check-circle me-2"></i> Save & Notify Gym Owner
                </button>
            </form>

            <div class="mt-3 small text-muted">
                Updating will automatically send a notification to the gym owner.
            </div>
        </div>
    </div>
</div>
@endsection
