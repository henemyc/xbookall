@extends('admin.layouts.app')

@section('title', 'Notifications')

@section('content')
<div class="table-card">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="mb-0">Notifications</h5>
        <div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#broadcastModal">
                <i class="bi bi-megaphone me-2"></i> Broadcast
            </button>
            <form action="{{ route('admin.notifications.destroyAll') }}" method="POST" class="d-inline" onsubmit="return confirm('Delete ALL notifications?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger">
                    <i class="bi bi-trash me-2"></i> Clear All
                </button>
            </form>
        </div>
    </div>

    <!-- Filters -->
    <div class="row mb-4">
        <div class="col-md-6">
            <form action="{{ route('admin.notifications.index') }}" method="GET">
                <div class="input-group">
                    <input type="text" name="search" class="form-control" placeholder="Search notifications..." value="{{ $search }}">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </form>
        </div>
        <div class="col-md-6 text-end">
            <div class="btn-group">
                <a href="{{ route('admin.notifications.index') }}" class="btn btn-outline-secondary {{ $type === '' ? 'active' : '' }}">All</a>
                <a href="{{ route('admin.notifications.index', ['type' => 'info']) }}" class="btn btn-outline-info {{ $type === 'info' ? 'active' : '' }}">Info</a>
                <a href="{{ route('admin.notifications.index', ['type' => 'warning']) }}" class="btn btn-outline-warning {{ $type === 'warning' ? 'active' : '' }}">Warning</a>
                <a href="{{ route('admin.notifications.index', ['type' => 'error']) }}" class="btn btn-outline-danger {{ $type === 'error' ? 'active' : '' }}">Error</a>
                <a href="{{ route('admin.notifications.index', ['type' => 'success']) }}" class="btn btn-outline-success {{ $type === 'success' ? 'active' : '' }}">Success</a>
            </div>
        </div>
    </div>

    <!-- Notifications List -->
    @forelse($notifications as $notification)
    <div class="d-flex align-items-start p-3 mb-2 bg-light rounded">
        <div class="me-3">
            @if($notification->type === 'info')
                <i class="bi bi-info-circle text-info fs-4"></i>
            @elseif($notification->type === 'warning')
                <i class="bi bi-exclamation-triangle text-warning fs-4"></i>
            @elseif($notification->type === 'error')
                <i class="bi bi-x-circle text-danger fs-4"></i>
            @else
                <i class="bi bi-check-circle text-success fs-4"></i>
            @endif
        </div>
        <div class="flex-grow-1">
            <div class="d-flex justify-content-between">
                <strong>{{ $notification->title }}</strong>
                <small class="text-muted">{{ $notification->created_at ? $notification->created_at->diffForHumans() : 'Recently' }}</small>
            </div>
            <p class="mb-1 text-muted">{{ $notification->message }}</p>
            @if($notification->parent)
                <small class="text-muted">
                    <i class="bi bi-building"></i> {{ $notification->parent->name ?? 'Unknown Gym' }}
                </small>
            @endif
        </div>
        <form action="{{ route('admin.notifications.destroy', $notification->id) }}" method="POST" class="ms-3">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-sm btn-outline-danger">
                <i class="bi bi-trash"></i>
            </button>
        </form>
    </div>
    @empty
    <div class="text-center py-5">
        <i class="bi bi-bell text-muted" style="font-size: 48px;"></i>
        <p class="text-muted mt-3">No notifications</p>
    </div>
    @endforelse

    <!-- Pagination -->
    <div class="d-flex justify-content-center mt-4">
        {{ $notifications->appends(['search' => $search, 'type' => $type])->links() }}
    </div>
</div>

<!-- Broadcast Modal -->
<div class="modal fade" id="broadcastModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('admin.notifications.broadcast') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Broadcast Notification</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        This will send a notification to ALL active gyms.
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Title</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Message</label>
                        <textarea name="message" class="form-control" rows="4" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select name="type" class="form-select">
                            <option value="info">Info</option>
                            <option value="success">Success</option>
                            <option value="warning">Warning</option>
                            <option value="error">Error</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send me-2"></i> Send to All Gyms
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
