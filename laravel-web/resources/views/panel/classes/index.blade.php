@extends('panel.layouts.app')

@section('title', 'Classes')

@section('content')
<div class="table-card">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h5 class="mb-1"><i class="bi bi-book me-2" style="color: var(--primary);"></i> Classes</h5>
            <small class="text-muted" id="classesCount">{{ $classes->count() }} classes</small>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addClassModal">
            <i class="bi bi-plus-circle me-2"></i> Add Class
        </button>
    </div>

    <!-- Search -->
    <div class="mb-4">
        <div class="search-input">
            <i class="bi bi-search search-icon"></i>
            <input type="text" id="classSearch" class="form-control" placeholder="Search classes...">
        </div>
    </div>

    <!-- Classes Grid -->
    <div class="row g-3" id="classGrid">
        @forelse($classes as $class)
        @php
            $assignedTrainers = $class->assigns->where('assign_type', 'trainer')->filter(fn($assign) => $assign->user);
        @endphp
        <div class="col-md-4 col-6 class-card" data-id="{{ $class->id }}" data-name="{{ strtolower($class->title) }}">
            <div class="p-3 rounded" style="background: var(--bg); border: 1px solid var(--border);">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <h6 class="mb-1">{{ $class->title }}</h6>
                        <span class="badge bg-primary">₹{{ number_format($class->fees) }}</span>
                    </div>
                    <span class="badge bg-secondary">{{ $class->member_count ?? 0 }} members</span>
                </div>

                @if($class->schedules->count() > 0)
                <div class="mb-2">
                    @foreach($class->schedules as $schedule)
                        <small class="text-muted d-block"><i class="bi bi-calendar-week me-1"></i> {{ $schedule->days }} {{ substr($schedule->start_time, 0, 5) }}-{{ substr($schedule->end_time, 0, 5) }}</small>
                    @endforeach
                </div>
                @endif

                @if($assignedTrainers->count() > 0)
                <div class="assigned-trainers mb-2">
                    @foreach($assignedTrainers as $assign)
                        <span class="badge bg-success-subtle text-success border border-success-subtle me-1 mb-1"><i class="bi bi-person-badge me-1"></i>{{ $assign->user->name }}</span>
                    @endforeach
                </div>
                @else
                <div class="assigned-trainers mb-2 d-none"></div>
                @endif

                <div class="d-flex gap-2 mt-3">
                    <button class="btn btn-sm btn-outline-primary flex-grow-1 edit-class-btn"
                            data-id="{{ $class->id }}"
                            data-title="{{ $class->title }}"
                            data-fees="{{ $class->fees }}"
                            data-address="{{ $class->address }}"
                            data-notes="{{ $class->notes }}"
                            data-trainer-ids="{{ $assignedTrainers->pluck('assign_id')->implode(',') }}">
                        <i class="bi bi-pencil me-1"></i> Edit
                    </button>

                    @if(($class->member_count ?? 0) > 0)
                        <button class="btn btn-sm btn-outline-secondary flex-grow-1" disabled title="Cannot delete - members enrolled">
                            <i class="bi bi-lock me-1"></i> In Use
                        </button>
                    @else
                        <button class="btn btn-sm btn-outline-danger flex-grow-1 delete-class-btn"
                                data-id="{{ $class->id }}"
                                data-title="{{ $class->title }}">
                            <i class="bi bi-trash3 me-1"></i> Delete
                        </button>
                    @endif
                </div>
            </div>
        </div>
        @empty
        <div class="col-12 text-center py-5" id="noClassesMessage">
            <i class="bi bi-book fs-1 d-block mb-3" style="opacity: 0.2;"></i>
            <p class="text-muted">No classes created yet</p>
        </div>
        @endforelse
    </div>
</div>

<!-- Add Class Modal -->
<div class="modal fade" id="addClassModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius: 20px;">
            <div style="background: linear-gradient(135deg, #0f172a, #1e293b); padding: 20px; border-radius: 20px 20px 0 0;">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0" style="color: white;">Add New Class</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Class Name <span class="text-danger">*</span></label>
                        <input type="text" id="classTitle" class="form-control" placeholder="e.g., Yoga, Zumba" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Fees (₹)</label>
                        <input type="number" id="classFees" class="form-control" min="0" value="0">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Location</label>
                        <input type="text" id="classAddress" class="form-control" placeholder="Optional">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Schedule Days</label>
                        <input type="text" id="classDays" class="form-control" placeholder="e.g., Mon,Wed,Fri">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Start Time</label>
                        <input type="time" id="classStartTime" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">End Time</label>
                        <input type="time" id="classEndTime" class="form-control">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Assign Trainers</label>
                        <select id="classTrainerIds" class="form-select" multiple size="{{ min(max(($trainers ?? collect())->count(), 2), 5) }}">
                            @foreach($trainers ?? [] as $trainer)
                                <option value="{{ $trainer->id }}">{{ $trainer->name }}{{ $trainer->phone_number ? ' - '.$trainer->phone_number : '' }}</option>
                            @endforeach
                        </select>
                        <small class="text-muted">Hold Ctrl/Cmd to select multiple trainers.</small>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea id="classNotes" class="form-control" rows="2" placeholder="Optional notes..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="addClassBtn" onclick="submitClass()">
                    <i class="bi bi-check-circle me-2"></i> Create Class
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Class Modal -->
<div class="modal fade" id="editClassModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius: 20px;">
            <div style="background: linear-gradient(135deg, #0f172a, #1e293b); padding: 20px; border-radius: 20px 20px 0 0;">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0" style="color: white;">Edit Class</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editClassId">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Class Name <span class="text-danger">*</span></label>
                        <input type="text" id="editClassTitle" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Fees (₹)</label>
                        <input type="number" id="editClassFees" class="form-control" min="0">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Location</label>
                        <input type="text" id="editClassAddress" class="form-control">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Assign Trainers</label>
                        <select id="editClassTrainerIds" class="form-select" multiple size="{{ min(max(($trainers ?? collect())->count(), 2), 5) }}">
                            @foreach($trainers ?? [] as $trainer)
                                <option value="{{ $trainer->id }}">{{ $trainer->name }}{{ $trainer->phone_number ? ' - '.$trainer->phone_number : '' }}</option>
                            @endforeach
                        </select>
                        <small class="text-muted">Hold Ctrl/Cmd to select multiple trainers.</small>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea id="editClassNotes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="updateClass()">
                    <i class="bi bi-save me-2"></i> Save Changes
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Duplicate check
    function isDuplicateClass(title, fees, excludeId = null) {
        const cards = document.querySelectorAll('.class-card');
        for (let card of cards) {
            const id = card.dataset.id;
            if (excludeId && id == excludeId) continue;

            const cardTitle = card.querySelector('h6')?.textContent?.trim().toLowerCase();
            const badge = card.querySelector('.badge.bg-primary');
            const cardFees = badge ? badge.textContent.replace(/[^0-9]/g, '') : '0';

            if (cardTitle === title.toLowerCase() && parseFloat(cardFees) === parseFloat(fees)) {
                return true;
            }
        }
        return false;
    }

    // Add Class (AJAX)
    async function submitClass() {
        const btn = document.getElementById('addClassBtn');
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '⏳ Creating...';

        const title = document.getElementById('classTitle').value.trim();
        const fees = document.getElementById('classFees').value || 0;
        const address = document.getElementById('classAddress').value.trim();
        const days = document.getElementById('classDays').value.trim();
        const start = document.getElementById('classStartTime').value;
        const end = document.getElementById('classEndTime').value;
        const notes = document.getElementById('classNotes').value.trim();

        if (!title) {
            window.showToast('Class name is required', 'error');
            btn.disabled = false; btn.innerHTML = orig; return;
        }

        if (isDuplicateClass(title, fees)) {
            window.showToast('A class with same name and fees already exists', 'error');
            btn.disabled = false; btn.innerHTML = orig; return;
        }

        const fd = new FormData();
        fd.append('title', title);
        fd.append('fees', fees);
        if (address) fd.append('address', address);
        if (days) fd.append('days', days);
        if (start) fd.append('start_time', start);
        if (end) fd.append('end_time', end);
        if (notes) fd.append('notes', notes);
        Array.from(document.getElementById('classTrainerIds').selectedOptions).forEach(opt => fd.append('trainer_ids[]', opt.value));

        try {
            const res = await fetch('/panel/classes', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: fd
            });

            let data = {};
            const contentType = res.headers.get('content-type') || '';
            if (contentType.includes('application/json')) {
                data = await res.json();
            } else {
                data = { success: res.ok };
            }

            if (res.ok && data.success) {
                window.showToast(data.message || 'Class created successfully!', 'success');
                bootstrap.Modal.getInstance(document.getElementById('addClassModal')).hide();
                document.getElementById('addClassModal').querySelectorAll('input, textarea').forEach(el => el.value = '');
                const trainerSelect = document.getElementById('classTrainerIds');
                if (trainerSelect) Array.from(trainerSelect.options).forEach(opt => opt.selected = false);
                if (data.class) {
                    addClassCard(data.class);
                } else {
                    location.reload();
                }
            } else {
                window.showToast(data.error || data.message || 'Failed to create class', 'error');
            }
        } catch (e) {
            console.error('Create class error:', e);
            window.showToast('Network error. Reload to verify if data was saved.', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = orig;
        }
    }

    // Open Edit
    document.addEventListener('click', function(e) {
        if (e.target.closest('.edit-class-btn')) {
            const btn = e.target.closest('.edit-class-btn');
            document.getElementById('editClassId').value = btn.dataset.id;
            document.getElementById('editClassTitle').value = btn.dataset.title;
            document.getElementById('editClassFees').value = btn.dataset.fees || 0;
            document.getElementById('editClassAddress').value = btn.dataset.address || '';
            document.getElementById('editClassNotes').value = btn.dataset.notes || '';
            const selectedTrainerIds = (btn.dataset.trainerIds || '').split(',').filter(Boolean);
            const trainerSelect = document.getElementById('editClassTrainerIds');
            if (trainerSelect) {
                Array.from(trainerSelect.options).forEach(opt => opt.selected = selectedTrainerIds.includes(opt.value));
            }

            new bootstrap.Modal(document.getElementById('editClassModal')).show();
        }
    });

    // Update Class
    async function updateClass() {
        const id = document.getElementById('editClassId').value;
        const title = document.getElementById('editClassTitle').value.trim();
        const fees = document.getElementById('editClassFees').value || 0;

        if (isDuplicateClass(title, fees, id)) {
            window.showToast('Another class with same name and fees exists', 'error');
            return;
        }

        const fd = new FormData();
        fd.append('_method', 'PUT');
        fd.append('title', title);
        fd.append('fees', fees);
        fd.append('address', document.getElementById('editClassAddress').value);
        fd.append('notes', document.getElementById('editClassNotes').value);
        Array.from(document.getElementById('editClassTrainerIds').selectedOptions).forEach(opt => fd.append('trainer_ids[]', opt.value));

        try {
            const res = await fetch(`/panel/classes/${id}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: fd
            });

            let data = {};
            const contentType = res.headers.get('content-type') || '';
            if (contentType.includes('application/json')) {
                data = await res.json();
            } else {
                data = { success: res.ok };
            }

            if (res.ok && data.success) {
                window.showToast('Class updated successfully!', 'success');
                bootstrap.Modal.getInstance(document.getElementById('editClassModal')).hide();
                if (data.class) {
                    updateClassCard(id, data.class);
                } else {
                    location.reload();
                }
            } else {
                window.showToast(data.error || data.message || 'Update failed', 'error');
            }
        } catch (e) {
            console.error('Update class error:', e);
            window.showToast('Network error. Reload to verify.', 'error');
        }
    }

    // Add card
    function addClassCard(cls) {
        const grid = document.getElementById('classGrid');
        const noMsg = document.getElementById('noClassesMessage');
        if (noMsg) noMsg.remove();

        const col = document.createElement('div');
        col.className = 'col-md-4 col-6 class-card';
        col.dataset.id = cls.id;
        col.dataset.name = (cls.title || '').toLowerCase();

        col.innerHTML = `
            <div class="p-3 rounded" style="background: var(--bg); border: 1px solid var(--border);">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <h6 class="mb-1">${cls.title}</h6>
                        <span class="badge bg-primary">₹${Number(cls.fees).toLocaleString()}</span>
                    </div>
                    <span class="badge bg-secondary">${cls.member_count || 0} members</span>
                </div>
                <div class="assigned-trainers mb-2 ${cls.trainer_names && cls.trainer_names.length ? '' : 'd-none'}">
                    ${(cls.trainer_names || []).map(name => `<span class="badge bg-success-subtle text-success border border-success-subtle me-1 mb-1"><i class="bi bi-person-badge me-1"></i>${name}</span>`).join('')}
                </div>
                <div class="d-flex gap-2 mt-3">
                    <button class="btn btn-sm btn-outline-primary flex-grow-1 edit-class-btn"
                            data-id="${cls.id}"
                            data-title="${cls.title}"
                            data-fees="${cls.fees}"
                            data-address="${cls.address || ''}"
                            data-notes="${cls.notes || ''}"
                            data-trainer-ids="${(cls.trainer_ids || []).join(',')}">
                        <i class="bi bi-pencil me-1"></i> Edit
                    </button>
                    <button class="btn btn-sm btn-outline-danger flex-grow-1 delete-class-btn"
                            data-id="${cls.id}" data-title="${cls.title}">
                        <i class="bi bi-trash3 me-1"></i> Delete
                    </button>
                </div>
            </div>
        `;

        grid.appendChild(col);
        attachClassListeners(col);
        updateClassCount();
    }

    function updateClassCard(id, cls) {
        const card = document.querySelector(`.class-card[data-id="${id}"]`);
        if (!card) return;

        card.querySelector('h6').textContent = cls.title;
        const feeBadge = card.querySelector('.badge.bg-primary');
        if (feeBadge) feeBadge.textContent = `₹${Number(cls.fees).toLocaleString()}`;

        const editBtn = card.querySelector('.edit-class-btn');
        if (editBtn) {
            editBtn.dataset.title = cls.title;
            editBtn.dataset.fees = cls.fees;
            editBtn.dataset.address = cls.address || '';
            editBtn.dataset.notes = cls.notes || '';
            editBtn.dataset.trainerIds = (cls.trainer_ids || []).join(',');
        }

        let trainersBox = card.querySelector('.assigned-trainers');
        if (!trainersBox) {
            trainersBox = document.createElement('div');
            trainersBox.className = 'assigned-trainers mb-2';
            const actions = card.querySelector('.d-flex.gap-2.mt-3');
            if (actions) actions.parentNode.insertBefore(trainersBox, actions);
        }
        const names = cls.trainer_names || [];
        trainersBox.classList.toggle('d-none', !names.length);
        trainersBox.innerHTML = names.map(name => `<span class="badge bg-success-subtle text-success border border-success-subtle me-1 mb-1"><i class="bi bi-person-badge me-1"></i>${name}</span>`).join('');
    }

    function attachClassListeners(container = document) {
        container.querySelectorAll('.delete-class-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.dataset.id;
                const title = this.dataset.title;

                document.getElementById('deleteConfirmMessage').textContent = `Delete "${title}"?`;

                const modal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
                const confirmBtn = document.getElementById('deleteConfirmBtn');

                const fresh = confirmBtn.cloneNode(true);
                confirmBtn.parentNode.replaceChild(fresh, confirmBtn);

                fresh.onclick = () => {
                    modal.hide();
                    deleteClass(id, this);
                };
                modal.show();
            });
        });
    }

    async function deleteClass(id, btn) {
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '⏳';

        try {
            const res = await fetch(`/panel/classes/${id}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            let data = {};
            const contentType = res.headers.get('content-type') || '';
            if (contentType.includes('application/json')) {
                data = await res.json();
            } else {
                data = { success: res.ok };
            }

            if (data.success) {
                const card = btn.closest('.class-card');
                if (card) card.remove();
                window.showToast('Class deleted successfully', 'success');
                updateClassCount();
            } else {
                window.showToast(data.error || data.message || 'Cannot delete class', 'error');
                btn.innerHTML = orig;
                btn.disabled = false;
            }
        } catch (e) {
            console.error('Delete class error:', e);
            window.showToast('Network error deleting class', 'error');
            btn.innerHTML = orig;
            btn.disabled = false;
        }
    }

    function updateClassCount() {
        const el = document.getElementById('classesCount');
        const count = document.querySelectorAll('.class-card').length;
        if (el) el.textContent = `${count} classes`;
    }

    // Search
    document.getElementById('classSearch').addEventListener('input', function() {
        const q = this.value.toLowerCase();
        document.querySelectorAll('.class-card').forEach(c => {
            c.style.display = (c.dataset.name || '').includes(q) ? '' : 'none';
        });
    });

    // Init
    document.addEventListener('DOMContentLoaded', function() {
        attachClassListeners();
    });
</script>
@endpush