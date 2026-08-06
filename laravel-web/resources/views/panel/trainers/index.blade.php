@extends('panel.layouts.app')

@section('title', 'Trainers')

@section('content')
<div class="table-card">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h5 class="mb-1"><i class="bi bi-person-badge me-2" style="color: var(--primary);"></i> Trainers</h5>
            <small class="text-muted">{{ $trainers->total() ?? $trainers->count() }} trainers in this gym</small>
        </div>
        <a href="{{ route('panel.trainers.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle me-2"></i> Add Trainer
        </a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-7">
            <div class="search-input">
                <i class="bi bi-search search-icon"></i>
                <input type="text" id="trainerSearch" class="form-control" placeholder="Search trainers by name or phone...">
            </div>
        </div>
        <div class="col-md-5 text-md-end">
            <div class="btn-group">
                <button class="btn btn-sm btn-primary" onclick="filterTrainers('all', this)">All</button>
                <button class="btn btn-sm btn-outline-secondary" onclick="filterTrainers('active', this)">Active</button>
                <button class="btn btn-sm btn-outline-secondary" onclick="filterTrainers('inactive', this)">Inactive</button>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Trainer</th>
                    <th>Phone</th>
                    <th>Specialization</th>
                    <th>Experience</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody id="trainersTableBody">
                @forelse($trainers as $trainer)
                    @php($detail = $trainer->trainerDetails)
                    <tr class="trainer-row" data-id="{{ $trainer->id }}" data-name="{{ strtolower($trainer->name) }}" data-phone="{{ $trainer->phone_number ?? '' }}" data-status="{{ $trainer->is_active ? 'active' : 'inactive' }}">
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="rounded-4 d-flex align-items-center justify-content-center me-3" style="width:44px;height:44px;background:linear-gradient(135deg,#16c784,#0d9c5f);color:white;font-weight:800;">
                                    {{ strtoupper(substr($trainer->name, 0, 1)) }}
                                </div>
                                <div>
                                    <div class="fw-bold">{{ $trainer->name }}</div>
                                    <small class="text-muted">{{ $trainer->email }}</small>
                                </div>
                            </div>
                        </td>
                        <td>{{ $trainer->phone_number ?? '-' }}</td>
                        <td>
                            <div>{{ $detail->specialization ?? '-' }}</div>
                            <small class="text-muted">{{ $detail->qualification ?? '' }}</small>
                        </td>
                        <td>{{ $detail && $detail->experience_years ? $detail->experience_years.' yrs' : '-' }}</td>
                        <td>
                            <span class="badge {{ $trainer->is_active ? 'bg-success' : 'bg-danger' }} trainer-status-badge">
                                {{ $trainer->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1">
                                <button type="button"
                                        class="btn btn-sm {{ $trainer->is_active ? 'btn-outline-warning' : 'btn-outline-success' }} toggle-btn"
                                        data-id="{{ $trainer->id }}"
                                        data-active="{{ $trainer->is_active ? '1' : '0' }}"
                                        title="{{ $trainer->is_active ? 'Deactivate' : 'Activate' }}">
                                    <i class="bi bi-toggle-{{ $trainer->is_active ? 'on' : 'off' }}"></i>
                                </button>
                                <a href="{{ route('panel.trainers.show', $trainer->id) }}" class="btn btn-sm btn-outline-primary" title="View details">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-danger delete-trainer-btn" data-id="{{ $trainer->id }}" data-name="{{ $trainer->name }}" title="Delete">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr id="emptyTrainerRow"><td colspan="6" class="text-center py-5 text-muted">No trainers found. Click Add Trainer to create one.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if(method_exists($trainers, 'hasPages') && $trainers->hasPages())
        <div class="d-flex justify-content-center mt-3">
            {{ $trainers->links() }}
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
    function toast(msg, type = 'success') {
        if (typeof window.showToast === 'function') return window.showToast(msg, type);
        alert(msg);
    }

    document.getElementById('trainerSearch').addEventListener('input', function() {
        const q = this.value.toLowerCase();
        document.querySelectorAll('.trainer-row').forEach(row => {
            const name = (row.dataset.name || '').toLowerCase();
            const phone = (row.dataset.phone || '').toLowerCase();
            row.style.display = (name.includes(q) || phone.includes(q)) ? '' : 'none';
        });
    });

    function filterTrainers(status, btn) {
        document.querySelectorAll('.btn-group .btn').forEach(b => {
            b.classList.remove('btn-primary');
            b.classList.add('btn-outline-secondary');
        });
        btn.classList.remove('btn-outline-secondary');
        btn.classList.add('btn-primary');

        document.querySelectorAll('.trainer-row').forEach(row => {
            row.style.display = status === 'all' || row.dataset.status === status ? '' : 'none';
        });
    }

    document.addEventListener('click', function(e) {
        const toggleBtn = e.target.closest('.toggle-btn');
        if (toggleBtn) toggleTrainer(toggleBtn);

        const deleteBtn = e.target.closest('.delete-trainer-btn');
        if (deleteBtn) confirmDeleteTrainer(deleteBtn);
    });

    async function toggleTrainer(btn) {
        const id = btn.dataset.id;
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '⏳';
        try {
            const res = await fetch(`/panel/trainers/${id}/toggle`, {
                method: 'PUT',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });
            const data = await res.json();
            if (!res.ok || !data.success) throw new Error(data.error || data.message || 'Failed');

            const active = data.is_active === true || data.is_active === 1 || data.is_active === '1';
            const row = btn.closest('.trainer-row');
            row.dataset.status = active ? 'active' : 'inactive';
            const badge = row.querySelector('.trainer-status-badge');
            badge.className = `badge ${active ? 'bg-success' : 'bg-danger'} trainer-status-badge`;
            badge.textContent = active ? 'Active' : 'Inactive';
            btn.className = `btn btn-sm ${active ? 'btn-outline-warning' : 'btn-outline-success'} toggle-btn`;
            btn.dataset.active = active ? '1' : '0';
            btn.title = active ? 'Deactivate' : 'Activate';
            btn.innerHTML = `<i class="bi bi-toggle-${active ? 'on' : 'off'}"></i>`;
            toast(data.message || 'Trainer status updated', 'success');
        } catch (err) {
            btn.innerHTML = orig;
            toast(err.message || 'Failed to update status', 'error');
        } finally {
            btn.disabled = false;
        }
    }

    function confirmDeleteTrainer(btn) {
        const id = btn.dataset.id;
        const name = btn.dataset.name;
        document.getElementById('deleteConfirmMessage').textContent = `Delete trainer "${name}"? Assigned members will be unassigned.`;
        const modal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
        const confirmBtn = document.getElementById('deleteConfirmBtn');
        const fresh = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(fresh, confirmBtn);
        fresh.onclick = () => {
            modal.hide();
            deleteTrainer(id, btn);
        };
        modal.show();
    }

    async function deleteTrainer(id, btn) {
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '⏳';
        try {
            const res = await fetch(`/panel/trainers/${id}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });
            const data = await res.json();
            if (!res.ok || !data.success) throw new Error(data.error || data.message || 'Delete failed');
            btn.closest('.trainer-row')?.remove();
            toast(data.message || 'Trainer deleted', 'success');
        } catch (err) {
            btn.innerHTML = orig;
            btn.disabled = false;
            toast(err.message || 'Failed to delete trainer', 'error');
        }
    }
</script>
@endpush
