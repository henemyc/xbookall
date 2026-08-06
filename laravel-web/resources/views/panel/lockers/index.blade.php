@extends('panel.layouts.app')

@section('title', 'Lockers')

@section('content')
<div class="table-card">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h5 class="mb-1"><i class="bi bi-lock me-2" style="color: var(--primary);"></i> Lockers</h5>
            <small class="text-muted" id="lockerCount">{{ $lockers->count() }} total</small>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLockerModal">
                <i class="bi bi-plus-circle me-2"></i> Add Lockers
            </button>
            <button class="btn btn-outline-danger" id="deleteAllBtn" onclick="deleteAllLockers()">
                <i class="bi bi-trash3 me-1"></i> Delete All
            </button>
        </div>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-4">
            <div class="p-3 rounded text-center" style="background: rgba(22, 199, 132, 0.1); border: 1px solid rgba(22, 199, 132, 0.2);">
                <div class="fw-bold text-success" style="font-family: 'Space Grotesk', sans-serif; font-size: 24px;" id="availableCount">{{ $availableCount }}</div>
                <small class="text-muted">Available</small>
            </div>
        </div>
        <div class="col-4">
            <div class="p-3 rounded text-center" style="background: rgba(255, 167, 38, 0.1); border: 1px solid rgba(255, 167, 38, 0.2);">
                <div class="fw-bold text-warning" style="font-family: 'Space Grotesk', sans-serif; font-size: 24px;" id="occupiedCount">{{ $occupiedCount }}</div>
                <small class="text-muted">Occupied</small>
            </div>
        </div>
        <div class="col-4">
            <div class="p-3 rounded text-center" style="background: rgba(59, 158, 255, 0.1); border: 1px solid rgba(59, 158, 255, 0.2);">
                <div class="fw-bold text-info" style="font-family: 'Space Grotesk', sans-serif; font-size: 24px;" id="totalCount">{{ $lockers->count() }}</div>
                <small class="text-muted">Total</small>
            </div>
        </div>
    </div>

    <!-- Search -->
    <div class="mb-4">
        <div class="search-input">
            <i class="bi bi-search search-icon"></i>
            <input type="text" id="lockerSearch" class="form-control" placeholder="Search by locker name or member...">
        </div>
    </div>

    <!-- Lockers Grid -->
    <div class="row g-3" id="lockerGrid">
        @foreach($lockers as $locker)
        <div class="col-md-3 col-6 locker-card" data-id="{{ $locker->id }}" 
             data-name="{{ strtolower($locker->name ?? 'locker ' . $locker->id) }}"
             data-member="{{ strtolower($locker->currentAssignment && $locker->currentAssignment->user ? $locker->currentAssignment->user->name : '') }}">
            <div class="p-3 rounded" style="background: {{ $locker->available ? 'rgba(22, 199, 132, 0.05)' : 'rgba(255, 167, 38, 0.05)' }}; border: 1px solid {{ $locker->available ? 'rgba(22, 199, 132, 0.2)' : 'rgba(255, 167, 38, 0.2)' }};">
                <div class="fw-bold mb-1" style="font-family: 'Space Grotesk', sans-serif; font-size: 16px;">{{ $locker->name ?? 'Locker ' . $locker->id }}</div>
                
                @if($locker->available)
                    <span class="badge bg-success mb-2">Available</span>
                    <button class="btn btn-sm btn-outline-primary w-100 assign-btn" data-id="{{ $locker->id }}">
                        <i class="bi bi-person-plus me-1"></i> Assign
                    </button>
                @else
                    <a href="{{ route('panel.members.show', $locker->currentAssignment->user_id ?? 0) }}" class="text-decoration-none">
                        <span class="badge bg-warning mb-2 d-inline-block" style="cursor: pointer;">
                            <i class="bi bi-person me-1"></i>{{ $locker->currentAssignment->user->name ?? 'Occupied' }}
                        </span>
                    </a>
                    <div class="d-flex gap-1 mt-2">
                        <button class="btn btn-sm btn-outline-warning flex-grow-1 unassign-btn" data-id="{{ $locker->id }}">
                            <i class="bi bi-unlock"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger flex-grow-1 delete-locker-btn" data-id="{{ $locker->id }}">
                            <i class="bi bi-trash3"></i>
                        </button>
                    </div>
                @endif
            </div>
        </div>
        @endforeach
    </div>
</div>

<!-- Add Locker Modal -->
<div class="modal fade" id="addLockerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius: 20px;">
            <div style="background: linear-gradient(135deg, #0f172a, #1e293b); padding: 20px; border-radius: 20px 20px 0 0;">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0" style="color: white;">Add Lockers</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    Enter locker names separated by commas: <strong>1, 2, 3, A1, A2</strong>
                </div>
                <div class="mb-3">
                    <label class="form-label">Locker Names *</label>
                    <input type="text" id="lockerNames" class="form-control" placeholder="e.g., 1, 2, 3, A1, B1" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitLockers()">Create</button>
            </div>
        </div>
    </div>
</div>

<!-- Assign Locker Modal -->
<div class="modal fade" id="assignLockerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius: 20px;">
            <div style="background: linear-gradient(135deg, #0f172a, #1e293b); padding: 20px; border-radius: 20px 20px 0 0;">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0" style="color: white;">Assign Locker</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
            </div>
            <div class="modal-body">
                <input type="hidden" id="assignLockerId">
                <div class="mb-3">
                    <label class="form-label">Select Member *</label>
                    <select id="assignMemberId" class="form-select" required>
                        <option value="">Choose member...</option>
                        @foreach($members as $member)
                            <option value="{{ $member->id }}">{{ $member->name }} - {{ $member->phone_number ?? '' }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="assignLocker()">Assign</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Add multiple lockers (AJAX)
    async function submitLockers() {
        const input = document.getElementById('lockerNames').value.trim();
        if (!input) {
            window.showToast('Please enter locker names', 'error');
            return;
        }

        const btns = document.querySelectorAll('#addLockerModal .btn-primary');
        if (btns.length) btns[0].disabled = true;

        const fd = new FormData();
        fd.append('locker_names', input);

        try {
            const res = await fetch('/panel/lockers', {
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
                window.showToast(data.message || 'Lockers added!', 'success');
                bootstrap.Modal.getInstance(document.getElementById('addLockerModal')).hide();
                document.getElementById('lockerNames').value = '';
                location.reload(); // Simple refresh for stats
            } else {
                window.showToast(data.error || 'Failed to add lockers', 'error');
            }
        } catch (e) {
            window.showToast('Network error', 'error');
        } finally {
            if (btns.length) btns[0].disabled = false;
        }
    }

    // Assign locker
    async function assignLocker() {
        const lockerId = document.getElementById('assignLockerId').value;
        const userId = document.getElementById('assignMemberId').value;

        if (!userId) {
            window.showToast('Please select a member', 'error');
            return;
        }

        const fd = new FormData();
        fd.append('locker_id', lockerId);
        fd.append('user_id', userId);

        try {
            const res = await fetch('/panel/lockers/assign', {
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
                window.showToast('Locker assigned!', 'success');
                bootstrap.Modal.getInstance(document.getElementById('assignLockerModal')).hide();
                location.reload();
            } else {
                window.showToast(data.error || 'Failed to assign', 'error');
            }
        } catch (e) {
            window.showToast('Network error', 'error');
        }
    }

    // Unassign
    async function unassignLocker(lockerId, btn) {
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '⏳';

        try {
            const res = await fetch(`/panel/lockers/${lockerId}/unassign`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            const data = await res.json();

            if (data.success) {
                window.showToast('Locker released', 'success');
                location.reload();
            } else {
                window.showToast(data.error || 'Failed', 'error');
                btn.innerHTML = orig;
                btn.disabled = false;
            }
        } catch (e) {
            window.showToast('Error', 'error');
            btn.innerHTML = orig;
            btn.disabled = false;
        }
    }

    // Delete single locker
    async function deleteLocker(lockerId, btn) {
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '⏳';

        try {
            const res = await fetch(`/panel/lockers/${lockerId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            const data = await res.json();

            if (data.success) {
                const card = btn.closest('.locker-card');
                if (card) card.remove();
                window.showToast('Locker deleted', 'success');
                updateLockerStats();
            } else {
                window.showToast(data.error || 'Failed', 'error');
                btn.innerHTML = orig;
                btn.disabled = false;
            }
        } catch (e) {
            window.showToast('Error', 'error');
            btn.innerHTML = orig;
            btn.disabled = false;
        }
    }

    // Delete all (use global modal)
    async function deleteAllLockers() {
        document.getElementById('deleteConfirmMessage').textContent = 'Delete ALL lockers? This cannot be undone.';

        const modal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
        const confirmBtn = document.getElementById('deleteConfirmBtn');

        const fresh = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(fresh, confirmBtn);

        fresh.onclick = async () => {
            modal.hide();

            try {
                const res = await fetch('/panel/lockers/delete-all', {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                });

                let data = {};
                const ct = res.headers.get('content-type') || '';
                if (ct.includes('application/json')) {
                    data = await res.json();
                } else {
                    data = { success: res.ok };
                }

                if (data.success) {
                    window.showToast('All lockers deleted', 'success');
                    document.getElementById('lockerGrid').innerHTML = '';
                    updateLockerStats(0, 0);
                } else {
                    window.showToast(data.error || 'Failed', 'error');
                }
            } catch (e) {
                window.showToast('Error', 'error');
            }
        };

        modal.show();
    }

    function updateLockerStats(available = null, occupied = null) {
        // Simple full reload for accuracy after bulk operations
        if (available === null) location.reload();
    }

    // Attach listeners
    function attachLockerListeners() {
        document.querySelectorAll('.assign-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('assignLockerId').value = this.dataset.id;
                new bootstrap.Modal(document.getElementById('assignLockerModal')).show();
            });
        });

        document.querySelectorAll('.unassign-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                unassignLocker(this.dataset.id, this);
            });
        });

        document.querySelectorAll('.delete-locker-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.dataset.id;
                const lockerCard = this.closest('.locker-card');
                const lockerName = lockerCard ? (lockerCard.dataset.name || 'this locker') : 'this locker';

                document.getElementById('deleteConfirmMessage').textContent = `Delete "${lockerName}"?`;

                const modal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
                const confirmBtn = document.getElementById('deleteConfirmBtn');

                const fresh = confirmBtn.cloneNode(true);
                confirmBtn.parentNode.replaceChild(fresh, confirmBtn);

                fresh.onclick = () => {
                    modal.hide();
                    deleteLocker(id, this);
                };
                modal.show();
            });
        });
    }

    // Search
    document.getElementById('lockerSearch').addEventListener('input', function() {
        const q = this.value.toLowerCase();
        document.querySelectorAll('.locker-card').forEach(c => {
            const match = (c.dataset.name || '') + ' ' + (c.dataset.member || '');
            c.style.display = match.includes(q) ? '' : 'none';
        });
    });

    // Init
    document.addEventListener('DOMContentLoaded', function() {
        attachLockerListeners();
    });
</script>
@endpush