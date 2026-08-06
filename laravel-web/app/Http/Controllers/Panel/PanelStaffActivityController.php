<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\BaseController;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;

class PanelStaffActivityController extends BaseController
{
    public function index(Request $request)
    {
        $this->requireGymOwner();
        $pid = (int) auth()->id();

        if (!Schema::hasTable('activity_logs')) {
            return view('panel.staff.activity.index', [
                'logs' => new LengthAwarePaginator([], 0, 20),
                'staffUsers' => collect(),
                'modules' => [],
                'filters' => $request->all(),
                'stats' => [
                    'total' => 0,
                    'today' => 0,
                    'staff_actions' => 0,
                    'login_count' => 0,
                ],
                'missingTable' => true,
            ]);
        }

        $staffUsers = User::where('type', 'staff')
            ->where('parent_id', $pid)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone_number']);

        $query = ActivityLog::where('parent_id', $pid)
            ->with('user:id,name,email,phone_number,type');

        $staffUserId = (int) $request->get('staff_user_id', 0);
        if ($staffUserId > 0) {
            $query->where('user_id', $staffUserId);
        }

        $module = trim((string) $request->get('module', ''));
        if ($module !== '') {
            $query->where('module', $module);
        }

        $action = trim((string) $request->get('action', ''));
        if ($action !== '') {
            $query->where('action', $action);
        }

        $from = trim((string) $request->get('from', ''));
        if ($from !== '') {
            $query->whereDate('created_at', '>=', $from);
        }

        $to = trim((string) $request->get('to', ''));
        if ($to !== '') {
            $query->whereDate('created_at', '<=', $to);
        }

        $search = trim((string) $request->get('search', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('module', 'like', "%{$search}%")
                  ->orWhere('action', 'like', "%{$search}%")
                  ->orWhere('record_type', 'like', "%{$search}%")
                  ->orWhere('ip', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%")
                         ->orWhere('phone_number', 'like', "%{$search}%");
                  });
            });
        }

        $logs = $query->orderBy('created_at', 'desc')->paginate(25)->withQueryString();

        $modules = ActivityLog::where('parent_id', $pid)
            ->whereNotNull('module')
            ->select('module')
            ->distinct()
            ->orderBy('module')
            ->pluck('module')
            ->values()
            ->all();

        $stats = [
            'total' => ActivityLog::where('parent_id', $pid)->count(),
            'today' => ActivityLog::where('parent_id', $pid)->whereDate('created_at', now('Asia/Kolkata')->toDateString())->count(),
            'staff_actions' => ActivityLog::where('parent_id', $pid)->where('user_type', 'staff')->count(),
            'login_count' => ActivityLog::where('parent_id', $pid)->where('module', 'auth')->where('action', 'login')->count(),
        ];

        return view('panel.staff.activity.index', [
            'logs' => $logs,
            'staffUsers' => $staffUsers,
            'modules' => $modules,
            'filters' => $request->all(),
            'stats' => $stats,
            'missingTable' => false,
        ]);
    }

    private function requireGymOwner(): void
    {
        $user = auth()->user();
        if (!$this->planFeatureEnabled('staff_enabled', true)) {
            abort(402, \App\Services\SubscriptionFeatureService::featureLockedMessage('Staff & Roles'));
        }
        if (!$user || !in_array($user->type, ['admin', 'owner'])) {
            abort(403, 'Only gym owner can view staff activity');
        }
    }
}
