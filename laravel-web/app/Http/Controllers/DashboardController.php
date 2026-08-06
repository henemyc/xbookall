<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\TraineeDetail;
use App\Models\Attendance;
use App\Models\InvoicePayment;
use App\Models\Expense;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DashboardController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $currentUser = $this->currentUser();

        if (!$currentUser) {
            return $this->error('No authenticated user', 401);
        }

        $gymOwnerId = $this->getParentId();
        $parentIds = $this->getGymParentIds();

        // Use the same tenant scope as lists. For staff this resolves to the
        // gym owner's parent scope, not the staff user's own ID.
        $memberCount = User::where('type', 'trainee')
            ->whereIn('parent_id', $parentIds)
            ->count();

        $trainerCount = User::where('type', 'trainer')
            ->whereIn('parent_id', $parentIds)
            ->count();

        $attendanceCount = Attendance::whereIn('parent_id', $parentIds)
            ->where('date', now()->toDateString())
            ->count();

        $activeCount = User::where('type', 'trainee')
            ->whereIn('parent_id', $parentIds)
            ->whereHas('traineeDetails', function ($q) {
                $q->where('membership_expiry_date', '>=', now()->toDateString());
            })
            ->count();

        $expiringCount = User::where('type', 'trainee')
            ->whereIn('parent_id', $parentIds)
            ->whereHas('traineeDetails', function ($q) {
                $q->whereBetween('membership_expiry_date', [now()->toDateString(), now()->addDays(7)->toDateString()]);
            })
            ->count();

        $revenue = InvoicePayment::whereIn('parent_id', $parentIds)
            ->whereMonth('payment_date', now()->month)
            ->whereYear('payment_date', now()->year)
            ->sum('amount');

        $expenses = Expense::whereIn('parent_id', $parentIds)
            ->whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->sum('amount');

        $recentMembers = User::where('type', 'trainee')
            ->whereIn('parent_id', $parentIds)
            ->with(['traineeDetails.membership'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($member) {
                return [
                    'id' => $member->id,
                    'name' => $member->name,
                    'plan_name' => $member->traineeDetails && $member->traineeDetails->membership
                        ? $member->traineeDetails->membership->title : 'No Plan',
                    'joined_at' => $member->created_at,
                    'created_at' => $member->created_at,
                ];
            });

        $showRevenueExpenseCard = Setting::getValue('show_revenue_expense_card', $gymOwnerId, '0') === '1';

        $todayCheckins = Attendance::whereIn('parent_id', $parentIds)
            ->where('date', now()->toDateString())
            ->with('user')
            ->orderBy('checked_in_time', 'desc')
            ->get()
            ->map(function ($att) {
                return [
                    'id' => $att->id,
                    'name' => $att->user ? $att->user->name : '',
                    'checked_in_time' => $att->checked_in_time,
                    'checked_out_time' => $att->checked_out_time,
                    'notes' => $att->notes,
                ];
            });

        $gymInfo = [
            'name' => Setting::getValue('company_name', $gymOwnerId, $currentUser->name ?? 'GymXBook'),
            'phone' => Setting::getValue('company_phone', $gymOwnerId, ''),
            'email' => Setting::getValue('company_email', $gymOwnerId, ''),
            'address' => Setting::getValue('company_address', $gymOwnerId, ''),
            'owner_id' => $gymOwnerId,
            'auth_user_id' => (int) $currentUser->id,
            'auth_user_type' => $currentUser->type,
        ];

        return $this->success([
            'gym_info' => $gymInfo,
            'stats' => [
                'members' => $memberCount,
                'trainers' => $trainerCount,
                'expiring_members' => $expiringCount,
                'attendance_today' => $attendanceCount,
                'active_memberships' => $activeCount,
                'active_members' => $activeCount,
                'revenue' => $revenue,
                'expenses' => $expenses,
            ],
            'show_revenue_expense_card' => $showRevenueExpenseCard,
            'recent_members' => $recentMembers,
            'today_checkins' => $todayCheckins,
        ]);
    }
}
