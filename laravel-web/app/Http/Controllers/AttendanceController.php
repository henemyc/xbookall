<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\TraineeDetail;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AttendanceController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $parentIds = $this->getGymParentIds();
        $user = $this->currentUser();
        $today = now('Asia/Kolkata')->toDateString();
        $date = $request->get('date', $today);

        if ($date !== $today) {
            $this->autoCheckoutOldRecordsOnly($parentIds, $date);
        }

        $query = Attendance::where('date', $date)
            ->whereIn('parent_id', $parentIds)
            ->with('user');

        if ($user && $user->type === 'trainee') {
            $query->where('user_id', $user->id);
        }

        $list = $query->orderBy('checked_in_time', 'desc')->get();

        $list->each(function ($att) {
            if ($att->user) {
                $att->setAttribute('name', $att->user->name);
                $att->setAttribute('phone_number', $att->user->phone_number ?? '');
            }
            $att->is_auto_checkout = str_contains($att->notes ?? '', 'Auto checkout');
        });

        return $this->success(['attendance' => $list, 'date' => $date]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->currentUser();
        $parentIds = $this->getGymParentIds();
        $pid = $this->getParentId();
        $userId = $request->user_id ?? ($user ? $user->id : 0);
        $type = $request->type ?? 'checkin';
        $today = now('Asia/Kolkata')->toDateString();
        $currentTime = now('Asia/Kolkata')->format('H:i:s');

        if ($type === 'checkin') {
            $existing = Attendance::where('user_id', $userId)
                ->where('date', $today)
                ->whereIn('parent_id', $parentIds)
                ->orderBy('id', 'desc')
                ->first();

            if ($existing) {
                if (empty($existing->checked_out_time)) {
                    return $this->error('Already checked in today. Please checkout first.', 400);
                }
                return $this->error('Member already checked out today. One visit is allowed per day.', 400);
            }

            $att = Attendance::create([
                'user_id' => $userId,
                'date' => $today,
                'checked_in_time' => $currentTime,
                'status' => 1,
                'parent_id' => $pid,
                'notes' => $request->notes ?? 'Check-in',
            ]);

            $att->load('user');
            if ($att->user) $att->setAttribute('name', $att->user->name);

            return $this->success(['type' => 'checkin', 'attendance' => $att], 'Checked in', 201);
        }

        // Manual checkout
        $att = Attendance::where('user_id', $userId)
            ->where('date', $today)
            ->whereIn('parent_id', $parentIds)
            ->whereNull('checked_out_time')
            ->first();

        if (!$att) {
            return $this->error('No active check-in found', 400);
        }

        $att->update([
            'checked_out_time' => $currentTime,
            'status' => 2,
            'notes' => ($att->notes ?? '') . ' | Manual checkout',
        ]);

        return $this->success([], 'Checked out successfully');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $parentIds = $this->getGymParentIds();

        $attendance = Attendance::where('id', $id)
            ->whereIn('parent_id', $parentIds)
            ->first();

        if (!$attendance) {
            return $this->error('Attendance not found', 404);
        }

        $data = [];
        if ($request->has('date')) $data['date'] = $request->input('date');
        if ($request->has('checked_in_time')) $data['checked_in_time'] = $request->input('checked_in_time');
        if ($request->has('checked_out_time')) $data['checked_out_time'] = $request->input('checked_out_time');
        if ($request->has('notes')) $data['notes'] = $request->input('notes');

        if (empty($data)) {
            return $this->error('No fields to update', 400);
        }

        $attendance->update($data);
        $attendance->refresh()->load('user');
        if ($attendance->user) {
            $attendance->setAttribute('name', $attendance->user->name);
            $attendance->setAttribute('phone_number', $attendance->user->phone_number ?? '');
        }

        return $this->success(['attendance' => $attendance], 'Attendance updated');
    }

    public function destroy(int $id): JsonResponse
    {
        $parentIds = $this->getGymParentIds();

        $attendance = Attendance::where('id', $id)
            ->whereIn('parent_id', $parentIds)
            ->first();

        if (!$attendance) {
            return $this->error('Attendance not found', 404);
        }

        $attendance->delete();

        return $this->success([], 'Attendance deleted');
    }

    /**
     * QR Scan for check-in / check-out
     * 
     * ROBUST version that fixes "Invalid QR Code" for BOTH gym owners and members.
     * 
     * The QR secret is stored under the gym's parent_id (usually the gym owner's user id).
     * We search every possible ID that getGymParentIds() returns + the user's own ID + common legacy IDs.
     */
    /**
     * QR scan check-in/check-out.
     * This mirrors the working PWA/api.php flow:
     * - $pid = getParentId() (admin id for gym owners, parent_id for members)
     * - validate qr_token against settings.attendance_qr_secret for that $pid
     * - type=checkin toggles check-in/check-out for today's record
     */
    public function scan(Request $request): JsonResponse
    {
        $user = $this->currentUser();
        if (!$user) {
            return $this->error('Authentication required', 401);
        }

        $pid = $this->getParentId();
        $today = now('Asia/Kolkata')->toDateString();
        $currentTime = now('Asia/Kolkata')->format('H:i:s');

        if ($pid <= 0) {
            return $this->error('Gym account not found for this member. Please contact gym admin.', 400);
        }

        $qrToken = $this->normalizeQrToken((string) $request->input('qr_token', ''));
        if ($qrToken !== '') {
            $secret = Setting::getValue('attendance_qr_secret', $pid);
            if (!$secret || trim((string) $secret) !== $qrToken) {
                return $this->error('Invalid QR Code for this gym', 400);
            }
        }

        $userId = (int) ($request->input('user_id') ?: $user->id);
        $type = $request->input('type', 'checkin');

        // Members can only mark their own attendance and cannot enter with
        // inactive/expired memberships. Expiry date itself is still valid.
        if ($user->type === 'trainee') {
            $traineeDetail = TraineeDetail::where('user_id', $user->id)->first();

            if (!$user->is_active) {
                return $this->error('Account is inactive', 403);
            }

            if ($traineeDetail && $traineeDetail->membership_expiry_date && $traineeDetail->membership_expiry_date->toDateString() < $today) {
                try {
                    DB::table('app_notifications')->insert([
                        'parent_id' => $pid,
                        'user_id' => $user->id,
                        'title' => 'Entry Denied: Expired',
                        'message' => $user->name . ' tried to check-in but membership is expired.',
                        'type' => 'error',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } catch (\Throwable $e) {}
                return $this->error('Membership expired. Please renew to check-in.', 403);
            }

            $userId = (int) $user->id;
        }

        if ($type === 'checkin') {
            $last = Attendance::where('user_id', $userId)
                ->where('date', $today)
                ->where('parent_id', $pid)
                ->with('user')
                ->orderBy('id', 'desc')
                ->first();

            if ($last) {
                if (empty($last->checked_out_time)) {
                    $last->update([
                        'checked_out_time' => $currentTime,
                        'status' => 2,
                        'notes' => trim(($last->notes ?? '') . ' | QR Checkout'),
                    ]);
                    $last->refresh()->load('user');
                    if ($last->user) {
                        $last->setAttribute('name', $last->user->name);
                    }

                    return $this->success([
                        'type' => 'checkout',
                        'attendance' => $last,
                    ], 'Checked out successfully');
                }

                try {
                    DB::table('app_notifications')->insert([
                        'parent_id' => $pid,
                        'user_id' => $userId,
                        'title' => 'Double Entry Attempt',
                        'message' => ($last->user->name ?? 'Member') . ' tried to check-in twice today.',
                        'type' => 'warning',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } catch (\Throwable $e) {}

                return $this->error('Already visited today', 400);
            }

            $attendance = Attendance::create([
                'user_id' => $userId,
                'date' => $today,
                'checked_in_time' => $currentTime,
                'status' => 1,
                'parent_id' => $pid,
                'notes' => $request->input('notes', 'QR Scan'),
            ]);

            $attendance->load('user');
            if ($attendance->user) {
                $attendance->setAttribute('name', $attendance->user->name);
            }

            return $this->success([
                'id' => $attendance->id,
                'type' => 'checkin',
                'attendance' => $attendance,
            ], 'Checked in successfully', 201);
        }

        // Explicit checkout request
        $attendance = Attendance::where('user_id', $userId)
            ->where('date', $today)
            ->where('parent_id', $pid)
            ->whereNull('checked_out_time')
            ->orderBy('id', 'desc')
            ->first();

        if (!$attendance) {
            return $this->error('No active check-in found', 400);
        }

        $attendance->update([
            'checked_out_time' => $currentTime,
            'status' => 2,
            'notes' => trim(($attendance->notes ?? '') . ' | QR Checkout'),
        ]);

        return $this->success(['type' => 'checkout', 'attendance' => $attendance], 'Checked out successfully');
    }

    public function search(Request $request): JsonResponse
    {
        $parentIds = $this->getGymParentIds();
        $q = trim($request->get('q', ''));

        if (empty($q)) {
            return $this->success(['users' => []]);
        }

        $today = now('Asia/Kolkata')->toDateString();

        $users = User::where('type', 'trainee')
            ->whereIn('parent_id', $parentIds)
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                      ->orWhere('phone_number', 'like', "%{$q}%");
            })
            ->select('id', 'name', 'phone_number')
            ->limit(20)
            ->get()
            ->map(function ($member) use ($parentIds, $today) {
                $attendance = Attendance::where('user_id', $member->id)
                    ->where('date', $today)
                    ->whereIn('parent_id', $parentIds)
                    ->orderBy('id', 'desc')
                    ->first();

                $status = 'not_checked_in';
                if ($attendance && empty($attendance->checked_out_time)) {
                    $status = 'inside';
                } elseif ($attendance) {
                    $status = 'completed';
                }

                return [
                    'id' => $member->id,
                    'name' => $member->name,
                    'phone_number' => $member->phone_number,
                    'attendance_id' => $attendance?->id,
                    'attendance_status' => $status,
                    'checked_in_time' => $attendance?->checked_in_time,
                    'checked_out_time' => $attendance?->checked_out_time,
                    'can_check_in' => $status === 'not_checked_in',
                    'can_check_out' => $status === 'inside',
                ];
            });

        return $this->success(['users' => $users]);
    }

    public function calendar(Request $request): JsonResponse
    {
        $parentIds = $this->getGymParentIds();
        $istNow = now('Asia/Kolkata');
        $month = intval($request->get('month', $istNow->month));
        $year = intval($request->get('year', $istNow->year));

        if ($month < 1 || $month > 12) $month = $istNow->month;
        if ($year < 2020 || $year > 2030) $year = $istNow->year;

        $counts = Attendance::whereIn('parent_id', $parentIds)
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->selectRaw('DATE(date) as attendance_date, COUNT(DISTINCT user_id) as present_count')
            ->groupBy('attendance_date')
            ->get()
            ->keyBy(function ($row) {
                return \Carbon\Carbon::parse($row->attendance_date)->format('Y-m-d');
            });

        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $calendar = [];

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dateStr = sprintf("%04d-%02d-%02d", $year, $month, $d);
            $row = $counts->get($dateStr);
            $presentCount = $row ? intval($row->present_count) : 0;
            $calendar[] = [
                'date' => $dateStr,
                'day' => $d,
                'present' => $presentCount,
                'present_count' => $presentCount,
                'is_today' => $dateStr === $istNow->toDateString(),
                'is_future' => $dateStr > $istNow->toDateString(),
            ];
        }

        return $this->success([
            'month' => $month,
            'year' => $year,
            'calendar' => $calendar,
            'counts_map' => $counts->mapWithKeys(fn($row) => [$row->attendance_date => intval($row->present_count)]),
            'total_present_month' => $counts->sum('present_count'),
        ]);
    }

    private function normalizeQrToken(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') return '';

        // JSON QR support: {"qr_token":"..."}, {"token":"..."}, etc.
        if (str_starts_with($raw, '{') || str_starts_with($raw, '[')) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                foreach (['qr_token', 'token', 'secret', 'attendance_qr_secret'] as $key) {
                    if (!empty($decoded[$key])) {
                        return trim((string) $decoded[$key]);
                    }
                }
            }
        }

        // URL QR support: https://...?...qr_token=SECRET or token=SECRET
        if (filter_var($raw, FILTER_VALIDATE_URL)) {
            $query = parse_url($raw, PHP_URL_QUERY);
            if ($query) {
                parse_str($query, $params);
                foreach (['qr_token', 'token', 'secret', 'attendance_qr_secret'] as $key) {
                    if (!empty($params[$key])) {
                        return trim((string) $params[$key]);
                    }
                }
            }
        }

        return $raw;
    }

    private function uniqueIds(array $ids): array
    {
        $clean = [];
        foreach ($ids as $id) {
            if ($id === null || $id === '') continue;
            $clean[] = (int) $id;
        }
        $clean = array_values(array_unique($clean));
        return $clean ?: [0];
    }

    private function autoCheckoutOldRecordsOnly($parentIds, $date): void
    {
        try {
            Attendance::whereIn('parent_id', $parentIds)
                ->where('date', $date)
                ->whereNull('checked_out_time')
                ->whereRaw("TIMESTAMPDIFF(HOUR, CONCAT(date, ' ', checked_in_time), NOW()) >= 4")
                ->update([
                    'checked_out_time' => DB::raw("ADDTIME(checked_in_time, '04:00:00')"),
                    'notes' => DB::raw("CONCAT(COALESCE(notes, ''), ' | Auto checkout after 4h')"),
                    'status' => 2,
                ]);
        } catch (\Exception $e) {}
    }
}
