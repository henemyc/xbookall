@extends('panel.layouts.app')

@section('title', 'Attendance Calendar')

@section('content')
<div class="table-card">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="mb-0">
            <i class="bi bi-calendar3 me-2" style="color: var(--primary);"></i> 
            Attendance Calendar
        </h5>
        <a href="{{ route('panel.attendance.index') }}" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-list me-2"></i> List View
        </a>
    </div>

    <!-- Month Navigation (AJAX) -->
    <div class="d-flex justify-content-between align-items-center mb-4 p-3 rounded" style="background: var(--bg);">
        <button type="button" id="prevMonthBtn" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-chevron-left"></i>
        </button>
        
        <h5 class="mb-0" style="font-family: 'Space Grotesk', sans-serif;" id="monthLabel">
            {{ \Carbon\Carbon::create($year, $month, 1)->format('F Y') }}
        </h5>
        
        <button type="button" id="nextMonthBtn" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-chevron-right"></i>
        </button>
    </div>

    <!-- Loading Spinner -->
    <div id="calendarLoading" class="text-center py-3 d-none">
        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
        <span class="ms-2 small text-muted">Loading calendar...</span>
    </div>

    <!-- Calendar Grid -->
    <div id="calendarWrapper">
        <div class="table-responsive">
            <table class="table table-bordered" style="table-layout: fixed;" id="calendarTable">
                <thead>
                    <tr>
                        @foreach(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $day)
                        <th class="text-center" style="background: var(--bg); font-size: 12px; padding: 12px;">{{ $day }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody id="calendarBody">
                    @include('panel.attendance._calendar_rows', ['calendar' => $calendar, 'year' => $year, 'month' => $month])
                </tbody>
            </table>
        </div>
    </div>

    <!-- Legend -->
    <div class="mt-3 d-flex gap-3">
        <small class="text-muted d-flex align-items-center">
            <span style="width: 12px; height: 12px; background: var(--success); border-radius: 3px; display: inline-block; margin-right: 6px;"></span>
            Has attendance
        </small>
        <small class="text-muted d-flex align-items-center">
            <span style="width: 12px; height: 12px; background: linear-gradient(135deg, #ff8a3d, #ff6b2c); border-radius: 50%; display: inline-block; margin-right: 6px;"></span>
            Today
        </small>
    </div>
</div>
@endsection

@push('scripts')
<script>
    let currentYear = {{ $year }};
    let currentMonth = {{ $month }};

    function setCalendarLoading(isLoading) {
        const loader = document.getElementById('calendarLoading');
        const wrapper = document.getElementById('calendarWrapper');
        
        if (isLoading) {
            loader.classList.remove('d-none');
            wrapper.style.opacity = '0.5';
        } else {
            loader.classList.add('d-none');
            wrapper.style.opacity = '1';
        }
    }

    async function loadCalendar(year, month) {
        setCalendarLoading(true);

        try {
            const res = await fetch(`/panel/attendance/calendar?month=${month}&year=${year}`, {
                headers: { 
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            const data = await res.json();

            if (data.success && data.html) {
                // Update month label from server
                const labelEl = document.getElementById('monthLabel');
                if (labelEl && data.label) {
                    labelEl.innerHTML = data.label;
                }

                // Replace calendar body rows
                const bodyEl = document.getElementById('calendarBody');
                if (bodyEl) {
                    bodyEl.innerHTML = data.html;
                }

                currentYear = data.year || year;
                currentMonth = data.month || month;

                // Update browser URL
                window.history.replaceState({}, '', `/panel/attendance/calendar?month=${currentMonth}&year=${currentYear}`);
            } else {
                window.showToast('Failed to load calendar data', 'error');
            }

        } catch (err) {
            console.error(err);
            window.showToast('Failed to load calendar', 'error');
        } finally {
            setCalendarLoading(false);
        }
    }

    // Navigation
    document.getElementById('prevMonthBtn').addEventListener('click', () => {
        let m = currentMonth - 1;
        let y = currentYear;
        if (m < 1) { m = 12; y--; }
        loadCalendar(y, m);
    });

    document.getElementById('nextMonthBtn').addEventListener('click', () => {
        let m = currentMonth + 1;
        let y = currentYear;
        if (m > 12) { m = 1; y++; }
        loadCalendar(y, m);
    });

    // Initial setup
    document.addEventListener('DOMContentLoaded', function() {
        // Already rendered server-side
    });
</script>
@endpush