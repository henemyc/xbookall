@extends('panel.layouts.app')

@section('title', 'Attendance')

@section('content')
<div class="table-card">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="mb-0">
            <i class="bi bi-fingerprint me-2" style="color: var(--primary);"></i> 
            Attendance <span id="currentDateLabel">- {{ \Carbon\Carbon::parse($date)->format('d M Y') }}</span>
        </h5>
        <div>
            <a href="{{ route('panel.attendance.calendar') }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-calendar3 me-2"></i> Calendar View
            </a>
        </div>
    </div>

    <!-- Date Selector + AJAX Search -->
    <div class="mb-4">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small text-muted">Select Date</label>
                <input type="date" id="attendanceDate" class="form-control" value="{{ $date }}">
            </div>
            <div class="col-md-3">
                <button type="button" id="searchDateBtn" class="btn btn-primary w-100">
                    <i class="bi bi-search me-1"></i> Search
                </button>
            </div>
            <div class="col-md-5 text-end">
                <div class="d-flex gap-2 justify-content-end">
                    <button class="btn btn-outline-secondary btn-sm" id="prevDayBtn">
                        <i class="bi bi-chevron-left"></i> Prev
                    </button>
                    <button class="btn btn-outline-secondary btn-sm" id="todayBtn">Today</button>
                    <button class="btn btn-outline-secondary btn-sm" id="nextDayBtn">
                        Next <i class="bi bi-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Spinner -->
    <div id="attendanceLoading" class="text-center py-4 d-none">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <div class="mt-2 text-muted small">Loading attendance...</div>
    </div>

    <!-- Attendance Table -->
    <div class="table-responsive" id="attendanceTableWrapper">
        <table class="table table-hover">
            <thead class="table-light">
                <tr>
                    <th>Member</th>
                    <th>Check In</th>
                    <th>Check Out</th>
                    <th>Status</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody id="attendanceTableBody">
                @forelse($attendances as $att)
                <tr>
                    <td><strong>{{ $att->user->name ?? 'Unknown' }}</strong></td>
                    <td>{{ \Carbon\Carbon::parse($att->checked_in_time)->format('h:i A') }}</td>
                    <td>
                        @if($att->checked_out_time)
                            {{ \Carbon\Carbon::parse($att->checked_out_time)->format('h:i A') }}
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    <td>
                        @if($att->checked_out_time)
                            <span class="badge" style="background: rgba(59, 158, 255, 0.1); color: #3b9eff;">Checked Out</span>
                        @else
                            <span class="badge" style="background: rgba(22, 199, 132, 0.1); color: #16c784;">Checked In</span>
                        @endif
                    </td>
                    <td>{{ $att->notes ?? '-' }}</td>
                </tr>
                @empty
                <tr id="noRecordsRow">
                    <td colspan="5" class="text-center py-4 text-muted">No attendance records for this date</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="d-flex justify-content-between align-items-center mt-2">
        <small class="text-muted" id="attendanceCount">
            Showing {{ $attendances->total() ?? count($attendances) }} records
        </small>
    </div>
</div>
@endsection

@push('scripts')
<script>
    let currentDate = '{{ $date }}';

    // Update date label
    function updateDateLabel(dateStr) {
        const label = document.getElementById('currentDateLabel');
        if (label) {
            const d = new Date(dateStr);
            label.textContent = '- ' + d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
        }
    }

    // Show / hide loading
    function setLoading(isLoading) {
        const loader = document.getElementById('attendanceLoading');
        const wrapper = document.getElementById('attendanceTableWrapper');
        
        if (isLoading) {
            loader.classList.remove('d-none');
            wrapper.style.opacity = '0.4';
        } else {
            loader.classList.add('d-none');
            wrapper.style.opacity = '1';
        }
    }

    // Load attendance via AJAX
    async function loadAttendance(dateStr) {
        setLoading(true);

        try {
            const res = await fetch(`/panel/attendance?date=${dateStr}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            const html = await res.text();
            
            // Parse the returned HTML and extract the table body
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            
            const newBody = doc.querySelector('#attendanceTableBody');
            const newCount = doc.querySelector('#attendanceCount');
            
            const currentBody = document.getElementById('attendanceTableBody');
            const currentCount = document.getElementById('attendanceCount');

            if (newBody && currentBody) {
                currentBody.innerHTML = newBody.innerHTML;
            }
            
            if (newCount && currentCount) {
                currentCount.innerHTML = newCount.innerHTML;
            }

            currentDate = dateStr;
            updateDateLabel(dateStr);
            
            // Update URL without reload
            window.history.replaceState({}, '', `/panel/attendance?date=${dateStr}`);

            // Re-bind any future listeners if needed
        } catch (err) {
            console.error(err);
            window.showToast('Failed to load attendance', 'error');
        } finally {
            setLoading(false);
        }
    }

    // Date picker search
    document.getElementById('searchDateBtn').addEventListener('click', () => {
        const dateInput = document.getElementById('attendanceDate').value;
        if (dateInput) {
            loadAttendance(dateInput);
        }
    });

    // Allow pressing Enter in date input
    document.getElementById('attendanceDate').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const dateInput = this.value;
            if (dateInput) loadAttendance(dateInput);
        }
    });

    // Quick navigation
    document.getElementById('prevDayBtn').addEventListener('click', () => {
        const d = new Date(currentDate);
        d.setDate(d.getDate() - 1);
        const newDate = d.toISOString().split('T')[0];
        document.getElementById('attendanceDate').value = newDate;
        loadAttendance(newDate);
    });

    document.getElementById('nextDayBtn').addEventListener('click', () => {
        const d = new Date(currentDate);
        d.setDate(d.getDate() + 1);
        const newDate = d.toISOString().split('T')[0];
        document.getElementById('attendanceDate').value = newDate;
        loadAttendance(newDate);
    });

    document.getElementById('todayBtn').addEventListener('click', () => {
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('attendanceDate').value = today;
        loadAttendance(today);
    });

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('attendanceDate').value = currentDate;
        updateDateLabel(currentDate);
    });
</script>
@endpush