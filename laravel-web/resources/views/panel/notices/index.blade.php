@extends('panel.layouts.app')

@section('title', 'Notices')

@section('content')
<div class="table-card">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h5 class="mb-0"><i class="bi bi-megaphone me-2"></i> Notices</h5>
        <div class="d-flex gap-2">
            <div class="position-relative" style="width: 260px;">
                <input type="text" id="noticeSearch" class="form-control form-control-sm" placeholder="🔍 Search notices...">
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addNoticeModal">
                <i class="bi bi-plus-circle me-2"></i> Add Notice
            </button>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Description</th>
                    <th>Date</th>
                    <th style="width: 90px;">Actions</th>
                </tr>
            </thead>
            <tbody id="noticesTableBody">
                @forelse($notices as $notice)
                <tr class="notice-row" data-id="{{ $notice->id }}">
                    <td><strong class="notice-title">{{ $notice->title }}</strong></td>
                    <td class="notice-desc">{{ Str::limit($notice->description, 60) ?? '-' }}</td>
                    <td>{{ $notice->created_at->format('d M Y') }}</td>
                    <td>
                        <button type="button" class="btn btn-sm btn-outline-primary me-1 edit-notice-btn"
                                data-id="{{ $notice->id }}"
                                data-title="{{ $notice->title }}"
                                data-description="{{ $notice->description }}">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger delete-notice-btn"
                                data-id="{{ $notice->id }}">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
                @empty
                <tr id="noNoticesRow">
                    <td colspan="4" class="text-center py-4">No notices yet</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Add Notice Modal -->
<div class="modal fade" id="addNoticeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Notice</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Title *</label>
                    <input type="text" id="noticeTitle" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea id="noticeDesc" class="form-control" rows="4"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitNotice()">Create Notice</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Notice Modal -->
<div class="modal fade" id="editNoticeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <input type="hidden" id="editNoticeId">
            <div class="modal-header">
                <h5 class="modal-title">Edit Notice</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Title *</label>
                    <input type="text" id="editNoticeTitle" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea id="editNoticeDesc" class="form-control" rows="4"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="updateNotice()">Update Notice</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Add Notice (AJAX)
    async function submitNotice() {
        const btn = document.querySelector('#addNoticeModal .btn-primary');
        const orig = btn ? btn.innerHTML : '';

        if (btn) {
            btn.disabled = true;
            btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span> Creating...`;
        }

        const title = document.getElementById('noticeTitle').value.trim();
        const description = document.getElementById('noticeDesc').value.trim();

        if (!title) {
            window.showToast('Title is required', 'error');
            if (btn) { btn.disabled = false; btn.innerHTML = orig; }
            return;
        }

        const fd = new FormData();
        fd.append('title', title);
        if (description) fd.append('description', description);

        try {
            const res = await fetch('/panel/notices', {
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
                window.showToast(data.message || 'Notice created!', 'success');
                bootstrap.Modal.getInstance(document.getElementById('addNoticeModal')).hide();
                resetNoticeForm();

                if (data.notice) {
                    addNoticeRow(data.notice);
                }
            } else {
                window.showToast(data.error || 'Failed to create notice', 'error');
            }
        } catch (e) {
            window.showToast('Network error', 'error');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = orig || 'Create Notice';
            }
        }
    }

    function resetNoticeForm() {
        document.getElementById('noticeTitle').value = '';
        document.getElementById('noticeDesc').value = '';
    }

    function addNoticeRow(notice) {
        const tbody = document.getElementById('noticesTableBody');
        const noRow = document.getElementById('noNoticesRow');
        if (noRow) noRow.remove();

        const tr = document.createElement('tr');
        tr.className = 'notice-row';
        tr.dataset.id = notice.id;

        tr.innerHTML = `
            <td><strong class="notice-title">${notice.title}</strong></td>
            <td class="notice-desc">${notice.description ? notice.description.substring(0,60) : '-'}</td>
            <td>${notice.created_at}</td>
            <td>
                <button type="button" class="btn btn-sm btn-outline-primary me-1 edit-notice-btn"
                        data-id="${notice.id}"
                        data-title="${notice.title}"
                        data-description="${notice.description || ''}">
                    <i class="bi bi-pencil"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger delete-notice-btn" data-id="${notice.id}">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        `;

        tbody.prepend(tr);
        attachNoticeListeners();
    }

    // Edit modal
    document.addEventListener('click', function(e) {
        const editBtn = e.target.closest('.edit-notice-btn');
        if (editBtn) {
            document.getElementById('editNoticeId').value = editBtn.dataset.id;
            document.getElementById('editNoticeTitle').value = editBtn.dataset.title;
            document.getElementById('editNoticeDesc').value = editBtn.dataset.description || '';

            new bootstrap.Modal(document.getElementById('editNoticeModal')).show();
        }
    });

    // Update Notice (AJAX)
    async function updateNotice() {
        const btn = document.querySelector('#editNoticeModal .btn-primary');
        const orig = btn ? btn.innerHTML : '';

        if (btn) {
            btn.disabled = true;
            btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span> Updating...`;
        }

        const id = document.getElementById('editNoticeId').value;
        const title = document.getElementById('editNoticeTitle').value.trim();
        const description = document.getElementById('editNoticeDesc').value.trim();

        if (!title) {
            window.showToast('Title is required', 'error');
            if (btn) { btn.disabled = false; btn.innerHTML = orig; }
            return;
        }

        const fd = new FormData();
        fd.append('_method', 'PUT');
        fd.append('title', title);
        fd.append('description', description);

        try {
            const res = await fetch(`/panel/notices/${id}`, {
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
                window.showToast('Notice updated!', 'success');
                bootstrap.Modal.getInstance(document.getElementById('editNoticeModal')).hide();
                updateNoticeRow(id, data.notice);
            } else {
                window.showToast(data.error || 'Update failed', 'error');
            }
        } catch (e) {
            window.showToast('Network error', 'error');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = orig || 'Update Notice';
            }
        }
    }

    function updateNoticeRow(id, notice) {
        const row = document.querySelector(`.notice-row[data-id="${id}"]`);
        if (!row) return;

        row.querySelector('.notice-title').textContent = notice.title;
        row.querySelector('.notice-desc').textContent = notice.description ? notice.description.substring(0,60) : '-';

        // Update edit data
        const editBtn = row.querySelector('.edit-notice-btn');
        if (editBtn) {
            editBtn.dataset.title = notice.title;
            editBtn.dataset.description = notice.description || '';
        }
    }

    // Custom delete modal
    function attachNoticeListeners() {
        document.querySelectorAll('.delete-notice-btn').forEach(btn => {
            btn.replaceWith(btn.cloneNode(true));
        });

        document.querySelectorAll('.delete-notice-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.dataset.id;
                const row = this.closest('.notice-row');
                const title = row ? row.querySelector('.notice-title')?.textContent : 'this notice';

                document.getElementById('deleteConfirmMessage').textContent = `Delete "${title}"?`;

                const modalEl = document.getElementById('deleteConfirmModal');
                const modal = new bootstrap.Modal(modalEl);
                const confirmBtn = document.getElementById('deleteConfirmBtn');

                const freshBtn = confirmBtn.cloneNode(true);
                confirmBtn.parentNode.replaceChild(freshBtn, confirmBtn);

                freshBtn.onclick = async () => {
                    modal.hide();

                    const origHtml = this.innerHTML;
                    this.disabled = true;
                    this.innerHTML = `<span class="spinner-border spinner-border-sm"></span>`;

                    try {
                        const res = await fetch(`/panel/notices/${id}`, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json'
                            }
                        });

                        const data = await res.json();

                        if (data.success) {
                            if (row) row.remove();
                            window.showToast('Notice deleted', 'success');

                            const tbody = document.getElementById('noticesTableBody');
                            if (tbody && tbody.children.length === 0) {
                                tbody.innerHTML = `<tr id="noNoticesRow"><td colspan="4" class="text-center py-4">No notices yet</td></tr>`;
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

    // Live search
    function filterNotices() {
        const searchInput = document.getElementById('noticeSearch');
        if (!searchInput) return;

        const query = searchInput.value.toLowerCase().trim();

        document.querySelectorAll('#noticesTableBody .notice-row').forEach(row => {
            const title = row.querySelector('.notice-title')?.textContent.toLowerCase() || '';
            const desc = row.querySelector('.notice-desc')?.textContent.toLowerCase() || '';
            const matches = title.includes(query) || desc.includes(query);
            row.style.display = matches ? '' : 'none';
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        attachNoticeListeners();

        const searchInput = document.getElementById('noticeSearch');
        if (searchInput) {
            searchInput.addEventListener('input', filterNotices);
        }
    });
</script>
@endpush
