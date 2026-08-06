<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\BaseController;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Http\Request;

class PanelAttendanceController extends BaseController
{
    /**
     * List attendance
     */
    public function index(Request $request)
    {
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();
        $date = $request->get('date', date('Y-m-d'));

        $attendances = Attendance::whereIn('parent_id', $parentIds)
            ->where('date', $date)
            ->with('user')
            ->orderBy('checked_in_time', 'desc')
            ->paginate(50);

        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return view('panel.attendance.index', compact('attendances', 'date'))->render();
        }

        return view('panel.attendance.index', compact('attendances', 'date'));
    }

    /**
     * Attendance calendar
     */
    public function calendar(Request $request)
    {
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();
        $month = intval($request->get('month', date('m')));
        $year = intval($request->get('year', date('Y')));

        // Get attendance counts per day
        $counts = Attendance::whereIn('parent_id', $parentIds)
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->selectRaw('date, COUNT(*) as present_count')
            ->groupBy('date')
            ->get()
            ->keyBy('date');

        // Build calendar
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $calendar = [];

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dateStr = sprintf("%04d-%02d-%02d", $year, $month, $d);
            $calendar[] = [
                'date' => $dateStr,
                'day' => $d,
                'present' => isset($counts[$dateStr]) ? $counts[$dateStr]->present_count : 0,
                'is_today' => $dateStr === date('Y-m-d'),
                'is_future' => $dateStr > date('Y-m-d'),
            ];
        }

        $isAjax = $request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest' || $request->expectsJson();

        if ($isAjax) {
            // Return JSON for robust AJAX handling (html + metadata)
            $html = view('panel.attendance._calendar_rows', compact('calendar', 'year', 'month'))->render();
            $label = \Carbon\Carbon::create($year, $month, 1)->format('F Y');

            return response()->json([
                'success' => true,
                'html' => $html,
                'month' => $month,
                'year' => $year,
                'label' => $label,
            ]);
        }

        return view('panel.attendance.calendar', compact('calendar', 'month', 'year'));
    }
}
