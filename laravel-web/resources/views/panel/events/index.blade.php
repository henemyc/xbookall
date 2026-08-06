@extends('panel.layouts.app')

@section('title', 'Events')

@section('content')
<div class="table-card">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="mb-0"><i class="bi bi-calendar-event me-2"></i> Events</h5>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEventModal">
            <i class="bi bi-plus-circle me-2"></i> Add Event
        </button>
    </div>

    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Event</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Status</th>
                    <th style="width: 100px;">Actions</th>
                </tr>
            </thead>
            <tbody id="eventsTableBody">
                @forelse($events as $event)
                @php
                    $start = \Carbon\Carbon::parse($event->start_date);
                    $end = \Carbon\Carbon::parse($event->end_date);
                    $isPast = $end->isPast();
                    $isOngoing = $start->isPast() && !$isPast;
                @endphp
                <tr class="event-row" data-id="{{ $event->id }}">
                    <td>
                        <strong class="event-title">{{ $event->title }}</strong>
                        @if($event->description)
                            <br><small class="text-muted event-desc">{{ Str::limit($event->description, 45) }}</small>
                        @endif
                    </td>
                    <td class="event-start">{{ $start->format('d M Y') }}</td>
                    <td class="event-end">{{ $end->format('d M Y') }}</td>
                    <td>
                        @if($isOngoing)
                            <span class="badge bg-success">Ongoing</span>
                        @elseif($isPast)
                            <span class="badge bg-secondary">Completed</span>
                        @else
                            <span class="badge bg-primary">Upcoming</span>
                        @endif
                    </td>
                    <td>
                        <button type="button" class="btn btn-sm btn-outline-primary me-1 edit-event-btn"
                                data-id="{{ $event->id }}"
                                data-title="{{ $event->title }}"
                                data-start="{{ $event->start_date->format('Y-m-d') }}"
                                data-end="{{ $event->end_date->format('Y-m-d') }}"
                                data-description="{{ $event->description }}">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger delete-event-btn"
                                data-id="{{ $event->id }}">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
                @empty
                <tr id="noEventsRow">
                    <td colspan="5" class="text-center py-4">No events created yet</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Add Event Modal -->
<div class="modal fade" id="addEventModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Event</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Event Title *</label>
                    <input type="text" id="eventTitle" class="form-control" required>
                </div>
                <div class="row mb-3">
                    <div class="col">
                        <label class="form-label">Start Date *</label>
                        <input type="date" id="eventStart" class="form-control" required>
                    </div>
                    <div class="col">
                        <label class="form-label">End Date *</label>
                        <input type="date" id="eventEnd" class="form-control" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea id="eventDesc" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitEvent()">Create Event</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Event Modal -->
<div class="modal fade" id="editEventModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <input type="hidden" id="editEventId">
            <div class="modal-header">
                <h5 class="modal-title">Edit Event</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Event Title *</label>
                    <input type="text" id="editEventTitle" class="form-control" required>
                </div>
                <div class="row mb-3">
                    <div class="col">
                        <label class="form-label">Start Date *</label>
                        <input type="date" id="editEventStart" class="form-control" required>
                    </div>
                    <div class="col">
                        <label class="form-label">End Date *</label>
                        <input type="date" id="editEventEnd" class="form-control" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea id="editEventDesc" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="updateEvent()">Update Event</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Add Event (AJAX)
    async function submitEvent() {
        const btn = document.querySelector('#addEventModal .btn-primary');
        const orig = btn ? btn.innerHTML : '';

        if (btn) {
            btn.disabled = true;
            btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span> Creating...`;
        }

        const title = document.getElementById('eventTitle').value.trim();
        const start = document.getElementById('eventStart').value;
        const end = document.getElementById('eventEnd').value;
        const desc = document.getElementById('eventDesc').value.trim();

        if (!title || !start || !end) {
            window.showToast('Title, Start and End date are required', 'error');
            if (btn) { btn.disabled = false; btn.innerHTML = orig; }
            return;
        }

        const fd = new FormData();
        fd.append('title', title);
        fd.append('start_date', start);
        fd.append('end_date', end);
        if (desc) fd.append('description', desc);

        try {
            const res = await fetch('/panel/events', {
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
                window.showToast(data.message || 'Event created!', 'success');
                bootstrap.Modal.getInstance(document.getElementById('addEventModal')).hide();
                resetEventForm();

                if (data.event) {
                    addEventRow(data.event);
                }
            } else {
                window.showToast(data.error || 'Failed to create event', 'error');
            }
        } catch (e) {
            window.showToast('Network error', 'error');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = orig || 'Create Event';
            }
        }
    }

    function resetEventForm() {
        document.getElementById('eventTitle').value = '';
        document.getElementById('eventStart').value = '';
        document.getElementById('eventEnd').value = '';
        document.getElementById('eventDesc').value = '';
    }

    function addEventRow(ev) {
        const tbody = document.getElementById('eventsTableBody');
        const noRow = document.getElementById('noEventsRow');
        if (noRow) noRow.remove();

        const start = new Date(ev.start_date);
        const end = new Date(ev.end_date);
        const isPast = end < new Date();
        const isOngoing = start < new Date() && !isPast;

        let statusHtml = '';
        if (isOngoing) statusHtml = '<span class="badge bg-success">Ongoing</span>';
        else if (isPast) statusHtml = '<span class="badge bg-secondary">Completed</span>';
        else statusHtml = '<span class="badge bg-primary">Upcoming</span>';

        const tr = document.createElement('tr');
        tr.className = 'event-row';
        tr.dataset.id = ev.id;

        tr.innerHTML = `
            <td>
                <strong class="event-title">${ev.title}</strong>
                ${ev.description ? `<br><small class="text-muted event-desc">${ev.description.substring(0,45)}</small>` : ''}
            </td>
            <td class="event-start">${start.toLocaleDateString('en-GB', {day:'2-digit', month:'short', year:'numeric'})}</td>
            <td class="event-end">${end.toLocaleDateString('en-GB', {day:'2-digit', month:'short', year:'numeric'})}</td>
            <td>${statusHtml}</td>
            <td>
                <button type="button" class="btn btn-sm btn-outline-primary me-1 edit-event-btn"
                        data-id="${ev.id}"
                        data-title="${ev.title}"
                        data-start="${ev.start_date}"
                        data-end="${ev.end_date}"
                        data-description="${ev.description || ''}">
                    <i class="bi bi-pencil"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger delete-event-btn" data-id="${ev.id}">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        `;

        tbody.prepend(tr);
        attachEventListeners();
    }

    // Open Edit Modal
    document.addEventListener('click', function(e) {
        const editBtn = e.target.closest('.edit-event-btn');
        if (editBtn) {
            document.getElementById('editEventId').value = editBtn.dataset.id;
            document.getElementById('editEventTitle').value = editBtn.dataset.title;
            document.getElementById('editEventStart').value = editBtn.dataset.start;
            document.getElementById('editEventEnd').value = editBtn.dataset.end;
            document.getElementById('editEventDesc').value = editBtn.dataset.description || '';

            new bootstrap.Modal(document.getElementById('editEventModal')).show();
        }
    });

    // Update Event (AJAX)
    async function updateEvent() {
        const btn = document.querySelector('#editEventModal .btn-primary');
        const orig = btn ? btn.innerHTML : '';

        if (btn) {
            btn.disabled = true;
            btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span> Updating...`;
        }

        const id = document.getElementById('editEventId').value;
        const title = document.getElementById('editEventTitle').value.trim();
        const start = document.getElementById('editEventStart').value;
        const end = document.getElementById('editEventEnd').value;
        const desc = document.getElementById('editEventDesc').value.trim();

        if (!title || !start || !end) {
            window.showToast('Title, Start and End date required', 'error');
            if (btn) { btn.disabled = false; btn.innerHTML = orig; }
            return;
        }

        const fd = new FormData();
        fd.append('_method', 'PUT');
        fd.append('title', title);
        fd.append('start_date', start);
        fd.append('end_date', end);
        if (desc) fd.append('description', desc);

        try {
            const res = await fetch(`/panel/events/${id}`, {
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
                window.showToast('Event updated!', 'success');
                bootstrap.Modal.getInstance(document.getElementById('editEventModal')).hide();
                updateEventRow(id, data.event);
            } else {
                window.showToast(data.error || 'Update failed', 'error');
            }
        } catch (e) {
            window.showToast('Network error', 'error');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = orig || 'Update Event';
            }
        }
    }

    function updateEventRow(id, ev) {
        const row = document.querySelector(`.event-row[data-id="${id}"]`);
        if (!row) return;

        row.querySelector('.event-title').textContent = ev.title;

        const descEl = row.querySelector('.event-desc');
        if (descEl) descEl.textContent = ev.description ? ev.description.substring(0,45) : '';

        row.querySelector('.event-start').textContent = new Date(ev.start_date).toLocaleDateString('en-GB', {day:'2-digit', month:'short', year:'numeric'});
        row.querySelector('.event-end').textContent = new Date(ev.end_date).toLocaleDateString('en-GB', {day:'2-digit', month:'short', year:'numeric'});

        // Update edit button data
        const editBtn = row.querySelector('.edit-event-btn');
        if (editBtn) {
            editBtn.dataset.title = ev.title;
            editBtn.dataset.start = ev.start_date;
            editBtn.dataset.end = ev.end_date;
            editBtn.dataset.description = ev.description || '';
        }
    }

    // Delete using custom modal (same as products)
    function attachEventListeners() {
        document.querySelectorAll('.delete-event-btn').forEach(btn => {
            btn.replaceWith(btn.cloneNode(true));
        });

        document.querySelectorAll('.delete-event-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.dataset.id;
                const row = this.closest('.event-row');
                const title = row ? row.querySelector('.event-title')?.textContent : 'this event';

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
                        const res = await fetch(`/panel/events/${id}`, {
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
                            window.showToast('Event deleted', 'success');

                            const tbody = document.getElementById('eventsTableBody');
                            if (tbody && tbody.children.length === 0) {
                                tbody.innerHTML = `<tr id="noEventsRow"><td colspan="5" class="text-center py-4">No events created yet</td></tr>`;
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

    // Edit listener for events (already handled via global click above)

    document.addEventListener('DOMContentLoaded', function() {
        attachEventListeners();
    });
</script>
@endpush
