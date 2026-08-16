<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\TraineeDetail;
use App\Models\Attendance;
use App\Models\InvoicePayment;
use App\Models\Expense;
use App\Models\Membership;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ReportController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $parentIds = $this->getGymParentIds();
        $strictParentIds = $this->strictReportParentIds($request);

        // Legacy-compatible scope for member/finance records.
        $scope = function ($q) use ($parentIds) {
            $q->whereIn('parent_id', $parentIds);
        };

        // New members this month — return flattened plan + expiry fields for Flutter UI
        $newMembers = User::where('type', 'trainee')
            ->where($scope)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->with(['traineeDetails.membership'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($member) {
                $detail = $member->traineeDetails;
                return [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'phone_number' => $member->phone_number,
                    'created_at' => $member->created_at,
                    'membership_start_date' => $detail ? $detail->membership_start_date : null,
                    'membership_expiry_date' => $detail ? $detail->membership_expiry_date : null,
                    'plan_name' => ($detail && $detail->membership) ? $detail->membership->title : 'No Plan',
                    'plan_amount' => ($detail && $detail->membership) ? $detail->membership->amount : 0,
                ];
            });

        // Expiring in next 7 days
        $expiring7Days = TraineeDetail::where($scope)
            ->whereBetween('membership_expiry_date', [now()->toDateString(), now()->addDays(7)->toDateString()])
            ->with(['user', 'membership'])
            ->orderBy('membership_expiry_date', 'asc')
            ->get()
            ->map(function ($detail) {
                return [
                    'id' => $detail->user_id,
                    'name' => $detail->user ? $detail->user->name : '',
                    'membership_expiry_date' => $detail->membership_expiry_date,
                    'plan_name' => $detail->membership ? $detail->membership->title : '',
                    'plan_amount' => $detail->membership ? $detail->membership->amount : 0,
                ];
            });

        // Expired members
        $expired = TraineeDetail::where($scope)
            ->where('membership_expiry_date', '<', now()->toDateString())
            ->whereNotNull('membership_expiry_date')
            ->with(['user', 'membership'])
            ->orderBy('membership_expiry_date', 'desc')
            ->get()
            ->map(function ($detail) {
                return [
                    'id' => $detail->user_id,
                    'name' => $detail->user ? $detail->user->name : '',
                    'membership_expiry_date' => $detail->membership_expiry_date,
                    'plan_name' => $detail->membership ? $detail->membership->title : '',
                ];
            });

        // Monthly income
        $monthlyIncome = InvoicePayment::where($scope)
            ->whereMonth('payment_date', now()->month)
            ->whereYear('payment_date', now()->year)
            ->sum('amount');

        // Monthly expense
        $monthlyExpense = Expense::where($scope)
            ->whereMonth('date', now()->month)
            ->whereYear('date', now()->year)
            ->sum('amount');

        // Active count
        $activeCount = User::where('type', 'trainee')
            ->where($scope)
            ->whereHas('traineeDetails', function ($q) {
                $q->where('membership_expiry_date', '>=', now()->toDateString());
            })
            ->count();

        $expiredCount = TraineeDetail::where($scope)
            ->where('membership_expiry_date', '<', now()->toDateString())
            ->whereNotNull('membership_expiry_date')
            ->count();

        $frozenCount = TraineeDetail::where($scope)
            ->where('status', 3)
            ->count();

        // Attendance chart (last 7 days)
        $attendanceChart = Attendance::where($scope)
            ->where('date', '>=', now()->subDays(7)->toDateString())
            ->selectRaw('date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        // Plan distribution
        // STRICT for Flutter gym login: this must match the Plans page exactly.
        // Do not include parent_id=1/root/global or older used plan IDs here,
        // otherwise reports can show more plans than the Plans screen.
        $planDistribution = Membership::whereIn('parent_id', $strictParentIds)
            ->withCount(['traineeDetails as member_count' => function ($q) use ($strictParentIds) {
                $q->whereIn('parent_id', $strictParentIds);
            }])
            ->orderBy('amount', 'asc')
            ->get()
            ->map(function ($plan) use ($strictParentIds) {
                $members = TraineeDetail::whereIn('parent_id', $strictParentIds)
                    ->where('membership_plan', $plan->id)
                    ->with('user:id,name,phone_number,email,created_at')
                    ->orderBy('membership_expiry_date', 'asc')
                    ->get()
                    ->map(function ($detail) use ($plan) {
                        return [
                            'id' => $detail->user_id,
                            'name' => $detail->user?->name ?? '',
                            'phone_number' => $detail->user?->phone_number ?? '',
                            'email' => $detail->user?->email ?? '',
                            'created_at' => $detail->user?->created_at,
                            'membership_expiry_date' => $detail->membership_expiry_date,
                            'plan_name' => $plan->title,
                        ];
                    })
                    ->values();

                return [
                    'id' => $plan->id,
                    'title' => $plan->title,
                    'package' => $plan->package,
                    'amount' => $plan->amount,
                    'member_count' => $plan->member_count,
                    'members' => $members,
                ];
            });

        // Due amount is calculated from this gym's invoices only. Paid invoices
        // contribute zero; partial/unpaid invoices contribute their remaining balance.
        $dueAmount = Invoice::whereIn('parent_id', $parentIds)
            ->with(['items', 'payments'])
            ->get()
            ->sum(function ($invoice) {
                return max(0, (float) $invoice->items->sum('amount') - (float) $invoice->payments->sum('amount'));
            });

        return $this->success([
            'due_amount' => $dueAmount,
            'new_members' => $newMembers,
            'new_members_count' => $newMembers->count(),
            'expiring_7days' => $expiring7Days,
            'expired' => $expired,
            'monthly_income' => $monthlyIncome,
            'monthly_expense' => $monthlyExpense,
            'active_count' => $activeCount,
            'expired_count' => $expiredCount,
            'frozen_count' => $frozenCount,
            'attendance_chart' => $attendanceChart,
            'plan_distribution' => $planDistribution,
        ]);
    }

    /**
     * Transactions list for TransactionsScreen (Income + Expenses combined)
     * Supports month/year filtering
     */
    public function transactions(Request $request): JsonResponse
    {
        if (!$this->canPerformGymAction('transactions.view')) {
            return $this->error('Permission denied', 403);
        }

        $parentIds = $this->getGymParentIds();
        $month = (int) $request->get('month', now()->month);
        $year = (int) $request->get('year', now()->year);

        // Validate
        if ($month < 1 || $month > 12) $month = now()->month;
        if ($year < 2020 || $year > 2035) $year = now()->year;

        $transactions = [];

        // === INCOME: Payments from invoices ===
        $payments = InvoicePayment::whereIn('parent_id', $parentIds)
            ->whereMonth('payment_date', $month)
            ->whereYear('payment_date', $year)
            ->with(['invoice.user'])
            ->orderBy('payment_date', 'desc')
            ->get();

        foreach ($payments as $p) {
            $invoice = $p->invoice;
            $user = $invoice ? $invoice->user : null;

            $transactions[] = [
                'id' => 'pay_' . $p->id,
                'date' => $p->payment_date ? $p->payment_date->toDateString() : now()->toDateString(),
                'sort_time' => $p->created_at ? $p->created_at->format('H:i:s') : '00:00:00',
                'type' => 'income',
                'amount' => (float) $p->amount,
                'description' => $p->notes ?: ($invoice ? 'Invoice Payment #' . ($invoice->invoice_id ?? $invoice->id) : 'Payment'),
                'member_name' => $user ? $user->name : '',
                'method' => $p->payment_type ?: 'cash',
                'invoice_id' => $invoice ? $invoice->invoice_id : null,
                'invoice_db_id' => $invoice ? $invoice->id : null,
            ];
        }

        // === EXPENSES ===
        $expenses = Expense::whereIn('parent_id', $parentIds)
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->orderBy('date', 'desc')
            ->get();

        foreach ($expenses as $e) {
            $transactions[] = [
                'id' => 'exp_' . $e->id,
                'date' => $e->date ? $e->date->toDateString() : now()->toDateString(),
                'sort_time' => $e->created_at ? $e->created_at->format('H:i:s') : '00:00:00',
                'type' => 'expense',
                'amount' => (float) $e->amount,
                'description' => $e->title ?: ($e->expense_type ?: 'Expense'),
                'member_name' => '',
                'method' => $e->expense_type ?: 'cash',
                'invoice_id' => null,
                'invoice_db_id' => null,
            ];
        }

        // Sort by date desc + time desc (most recent first)
        usort($transactions, function ($a, $b) {
            $dateCmp = strcmp($b['date'], $a['date']);
            if ($dateCmp !== 0) return $dateCmp;
            return strcmp($b['sort_time'], $a['sort_time']);
        });

        // Calculate totals
        $totalIncome = 0;
        $totalExpense = 0;

        foreach ($transactions as $t) {
            if ($t['type'] === 'income') {
                $totalIncome += $t['amount'];
            } else {
                $totalExpense += $t['amount'];
            }
        }

        return $this->success([
            'transactions' => $transactions,
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'month' => $month,
            'year' => $year,
            'count' => count($transactions),
        ]);
    }

    /**
     * Member-specific transactions (used in member detail / invoices)
     */
    public function memberTransactions(Request $request): JsonResponse
    {
        if (!$this->canPerformGymAction('transactions.view')) {
            return $this->error('Permission denied', 403);
        }

        $parentIds = $this->getGymParentIds();
        $userId = (int) $request->get('user_id');

        if ($userId <= 0) {
            return $this->error('user_id is required', 422);
        }

        // Only show transactions for members that belong to this gym
        $member = User::where('id', $userId)
            ->whereIn('parent_id', $parentIds)
            ->first();

        if (!$member) {
            return $this->error('Member not found in your gym', 404);
        }

        // Get payments for this member's invoices
        $payments = InvoicePayment::whereIn('parent_id', $parentIds)
            ->whereHas('invoice', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->with(['invoice'])
            ->orderBy('payment_date', 'desc')
            ->get();

        $txns = [];
        foreach ($payments as $p) {
            $invoice = $p->invoice;
            $txns[] = [
                'id' => 'pay_' . $p->id,
                'date' => $p->payment_date ? $p->payment_date->toDateString() : null,
                'type' => 'income',
                'amount' => (float) $p->amount,
                'description' => $p->notes ?: 'Payment received',
                'method' => $p->payment_type ?: 'cash',
                'invoice_id' => $invoice ? $invoice->invoice_id : null,
                'invoice_db_id' => $invoice ? $invoice->id : null,
            ];
        }

        return $this->success([
            'transactions' => $txns,
            'member_id' => $userId,
            'total' => collect($txns)->sum('amount'),
        ]);
    }

    private function strictReportParentIds(Request $request): array
    {
        return $this->getGymParentIds();
    }
}
