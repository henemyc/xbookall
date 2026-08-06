@extends('panel.layouts.app')

@section('title', 'Workout Activities')

@section('content')
<div class="table-card">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h5 class="mb-1">
                <i class="bi bi-lightning me-2" style="color: var(--warning);"></i>
                Workout Activities
            </h5>
            <small class="text-muted">Manage exercise activities for workout plans</small>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addActivityModal">
            <i class="bi bi-plus-circle me-2"></i> Add Activity
        </button>
    </div>

    <!-- Search -->
    <div class="mb-4">
        <div class="search-input">
            <i class="bi bi-search search-icon"></i>
            <input type="text" id="activitySearch" class="form-control" placeholder="Search activities...">
        </div>
    </div>

    <!-- Activities Grid -->
    <div class="row g-3" id="activityGrid">
        @forelse($activities as $activity)
        <div class="col-md-3 col-6 activity-card" data-id="{{ $activity->id }}" data-title="{{ strtolower($activity->title) }}">
            <div class="p-3 rounded d-flex align-items-center justify-content-between" style="background: var(--bg); border: 1px solid var(--border);">
                <div class="d-flex align-items-center">
                    <div style="width: 36px; height: 36px; background: rgba(255, 167, 38, 0.1); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-lightning text-warning" style="font-size: 14px;"></i>
                    </div>
                    <span class="ms-2 fw-bold activity-title" style="font-size: 13px;">{{ $activity->title }}</span>
                </div>
                <div>
                    <button type="button" class="btn btn-sm btn-outline-primary me-1 edit-activity-btn"
                            data-id="{{ $activity->id }}"
                            data-title="{{ $activity->title }}">
                        <i class="bi bi-pencil" style="font-size: 11px;"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger delete-activity-btn"
                            data-id="{{ $activity->id }}">
                        <i class="bi bi-trash3" style="font-size: 11px;"></i>
                    </button>
                </div>
            </div>
        </div>
        @empty
        <div class="col-12 text-center py-5" id="noActivitiesRow">
            <i class="bi bi-lightning fs-1 d-block mb-3" style="opacity: 0.2;"></i>
            <p class="text-muted">No activities yet. Add exercises like Push Ups, Squats, etc.</p>
        </div>
        @endforelse
    </div>
</div>

<!-- Add Activity Modal -->
<div class="modal fade" id="addActivityModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i> Add Activity</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Activity Name *</label>
                    <input type="text" id="activityTitle" class="form-control" placeholder="e.g., Push Ups, Squats, Bench Press" required>
                </div>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    These activities will appear in workout plan dropdowns.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitActivity()">Add Activity</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Activity Modal -->
<div class="modal fade" id="editActivityModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <input type="hidden" id="editActivityId">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i> Edit Activity</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Activity Name *</label>
                    <input type="text" id="editActivityTitle" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="updateActivity()">Save Changes</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Add Activity (AJAX)
    async function submitActivity() {
        const btn = document.querySelector('#addActivityModal .btn-primary');
        const origText = btn ? btn.innerHTML : '';

        if (btn) {
            btn.disabled = true;
            btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span> Adding...`;
        }

        const title = document.getElementById('activityTitle').value.trim();

        if (!title) {
            window.showToast('Activity name is required', 'error');
            if (btn) { btn.disabled = false; btn.innerHTML = origText; }
            return;
        }

        const fd = new FormData();
        fd.append('title', title);

        try {
            const res = await fetch('/panel/workouts/activities', {
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
                window.showToast(data.message || 'Activity added!', 'success');
                bootstrap.Modal.getInstance(document.getElementById('addActivityModal')).hide();
                resetActivityForm();

                if (data.activity) {
                    addActivityCard(data.activity);
                }
            } else {
                window.showToast(data.error || 'Failed to add activity', 'error');
            }
        } catch (e) {
            window.showToast('Network error', 'error');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = origText || 'Add Activity';
            }
        }
    }

    function resetActivityForm() {
        document.getElementById('activityTitle').value = '';
    }

    // Add card to grid
    function addActivityCard(activity) {
        const grid = document.getElementById('activityGrid');
        const noRow = document.getElementById('noActivitiesRow');
        if (noRow) noRow.remove();

        const card = document.createElement('div');
        card.className = 'col-md-3 col-6 activity-card';
        card.dataset.id = activity.id;
        card.dataset.title = activity.title.toLowerCase();

        card.innerHTML = `
            <div class="p-3 rounded d-flex align-items-center justify-content-between" style="background: var(--bg); border: 1px solid var(--border);">
                <div class="d-flex align-items-center">
                    <div style="width: 36px; height: 36px; background: rgba(255, 167, 38, 0.1); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-lightning text-warning" style="font-size: 14px;"></i>
                    </div>
                    <span class="ms-2 fw-bold activity-title" style="font-size: 13px;">${activity.title}</span>
                </div>
                <div>
                    <button type="button" class="btn btn-sm btn-outline-primary me-1 edit-activity-btn"
                            data-id="${activity.id}"
                            data-title="${activity.title}">
                        <i class="bi bi-pencil" style="font-size: 11px;"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger delete-activity-btn"
                            data-id="${activity.id}">
                        <i class="bi bi-trash3" style="font-size: 11px;"></i>
                    </button>
                </div>
            </div>
        `;

        grid.prepend(card);
        attachActivityListeners();
    }

    // Open Edit Modal
    document.addEventListener('click', function(e) {
        const editBtn = e.target.closest('.edit-activity-btn');
        if (editBtn) {
            document.getElementById('editActivityId').value = editBtn.dataset.id;
            document.getElementById('editActivityTitle').value = editBtn.dataset.title || '';

            new bootstrap.Modal(document.getElementById('editActivityModal')).show();
        }
    });

    // Update Activity (AJAX)
    async function updateActivity() {
        const btn = document.querySelector('#editActivityModal .btn-primary');
        const origText = btn ? btn.innerHTML : '';

        if (btn) {
            btn.disabled = true;
            btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span> Saving...`;
        }

        const id = document.getElementById('editActivityId').value;
        const title = document.getElementById('editActivityTitle').value.trim();

        if (!title) {
            window.showToast('Activity name is required', 'error');
            if (btn) { btn.disabled = false; btn.innerHTML = origText; }
            return;
        }

        const fd = new FormData();
        fd.append('_method', 'PUT');
        fd.append('title', title);

        try {
            const res = await fetch(`/panel/workouts/activities/${id}`, {
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
                window.showToast('Activity updated!', 'success');
                bootstrap.Modal.getInstance(document.getElementById('editActivityModal')).hide();

                updateActivityCard(id, data.activity);
            } else {
                window.showToast(data.error || 'Update failed', 'error');
            }
        } catch (e) {
            window.showToast('Network error', 'error');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = origText || 'Save Changes';
            }
        }
    }

    function updateActivityCard(id, activity) {
        const card = document.querySelector(`.activity-card[data-id="${id}"]`);
        if (!card) return;

        const titleSpan = card.querySelector('.activity-title');
        if (titleSpan) titleSpan.textContent = activity.title;

        // Update dataset
        card.dataset.title = (activity.title || '').toLowerCase();

        // Update edit button data
        const editBtn = card.querySelector('.edit-activity-btn');
        if (editBtn) {
            editBtn.dataset.title = activity.title || '';
        }
    }

    // Delete using custom modal
    function attachActivityListeners() {
        // Clean up old listeners
        document.querySelectorAll('.delete-activity-btn').forEach(btn => {
            btn.replaceWith(btn.cloneNode(true));
        });

        document.querySelectorAll('.delete-activity-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.dataset.id;
                const card = this.closest('.activity-card');
                const title = card ? card.querySelector('.activity-title')?.textContent : 'this activity';

                document.getElementById('deleteConfirmMessage').textContent = `Delete "${title}"?`;

                const modalEl = document.getElementById('deleteConfirmModal');
                const modal = new bootstrap.Modal(modalEl);
                const confirmBtn = document.getElementById('deleteConfirmBtn');

                // Fresh button to avoid multiple handlers
                const freshBtn = confirmBtn.cloneNode(true);
                confirmBtn.parentNode.replaceChild(freshBtn, confirmBtn);

                freshBtn.onclick = async () => {
                    modal.hide();

                    const origHtml = this.innerHTML;
                    this.disabled = true;
                    this.innerHTML = `<span class="spinner-border spinner-border-sm"></span>`;

                    try {
                        const res = await fetch(`/panel/workouts/activities/${id}`, {
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
                            window.showToast('Activity deleted', 'success');

                            // Show empty state if needed
                            const grid = document.getElementById('activityGrid');
                            if (grid && grid.children.length === 0) {
                                grid.innerHTML = `
                                    <div class="col-12 text-center py-5" id="noActivitiesRow">
                                        <i class="bi bi-lightning fs-1 d-block mb-3" style="opacity: 0.2;"></i>
                                        <p class="text-muted">No activities yet. Add exercises like Push Ups, Squats, etc.</p>
                                    </div>
                                `;
                            }
                        } else {
                            window.showToast(data.error || 'Delete failed', 'error');
                            this.disabled = false;
                            this.innerHTML = origHtml;
                        }
                    } catch (e) {
                        window.showToast('Network error', 'error');
                        this.disabled = false;
                        this.innerHTML = origHtml;
                    }
                };

                modal.show();
            });
        });
    }

    // Live search for activities
    function filterActivities() {
        const searchInput = document.getElementById('activitySearch');
        if (!searchInput) return;

        const query = searchInput.value.toLowerCase().trim();

        document.querySelectorAll('#activityGrid .activity-card').forEach(card => {
            const title = (card.dataset.title || '').toLowerCase();
            const matches = title.includes(query);
            card.style.display = matches ? '' : 'none';
        });
    }

    // Initial setup
    document.addEventListener('DOMContentLoaded', function() {
        attachActivityListeners();

        const searchInput = document.getElementById('activitySearch');
        if (searchInput) {
            searchInput.addEventListener('input', filterActivities);
        }
    });
</script>
@endpush
