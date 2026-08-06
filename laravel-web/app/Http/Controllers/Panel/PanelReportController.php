<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\BaseController;
use App\Models\Attendance;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Membership;
use App\Models\TraineeDetail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PanelReportController extends BaseController
{
    /**
     * Reports dashboard (supports AJAX)
     *
     * IMPORTANT: Gym panel reports must be strictly scoped to the logged-in gym.
     * Do not use BaseController::getGymParentIds() here because that helper is
     * intentionally permissive for legacy Flutter/PWA reads and may include
     * parent/root IDs. That was causing Plan Distribution to show plans from
     * other gyms.
     */
    public function index(Request $request)
    {
        $parentIds = $this->strictPanelParentIds();
        $month = (int) $request->get('month', date('m'));
        $year = (int) $request->get('year', date('Y'));
        $today = now('Asia/Kolkata')->toDateString();
        $next7Days = now('Asia/Kolkata')->addDays(7)->toDateString();

        // ========== KPIs ==========
        $newMembersCount = User::where('type', 'trainee')
            ->whereIn('parent_id', $parentIds)
            ->whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->count();

        $expiringCount = TraineeDetail::whereIn('parent_id', $parentIds)
            ->whereBetween('membership_expiry_date', [$today, $next7Days])
            ->count();

        $expiredCount = TraineeDetail::whereIn('parent_id', $parentIds)
            ->where('membership_expiry_date', '<', $today)
            ->whereNotNull('membership_expiry_date')
            ->count();

        $activeCount = TraineeDetail::whereIn('parent_id', $parentIds)
            ->where('membership_expiry_date', '>=', $today)
            ->count();

        // ========== Income vs Expense ==========
        $monthlyIncome = InvoicePayment::whereIn('parent_id', $parentIds)
            ->whereMonth('payment_date', $month)
            ->whereYear('payment_date', $year)
            ->sum('amount');

        $monthlyExpense = Expense::whereIn('parent_id', $parentIds)
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->sum('amount');

        // ========== Income vs Expense for Graph (last 6 months) ==========
        $incomeExpenseGraph = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now('Asia/Kolkata')->subMonths($i);
            $m = (int) $date->format('m');
            $y = (int) $date->format('Y');

            $inc = InvoicePayment::whereIn('parent_id', $parentIds)
                ->whereMonth('payment_date', $m)
                ->whereYear('payment_date', $y)
                ->sum('amount');

            $exp = Expense::whereIn('parent_id', $parentIds)
                ->whereMonth('date', $m)
                ->whereYear('date', $y)
                ->sum('amount');

            $incomeExpenseGraph[] = [
                'label' => Carbon::create($y, $m)->format('M Y'),
                'income' => (float) $inc,
                'expense' => (float) $exp,
            ];
        }

        // ========== New Members This Month (List) ==========
        $newMembersList = User::where('type', 'trainee')
            ->whereIn('parent_id', $parentIds)
            ->whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->orderBy('created_at', 'desc')
            ->take(8)
            ->get(['id', 'name', 'phone_number', 'created_at']);

        // ========== Upcoming Payments (next 7 days) ==========
        $upcomingPayments = Invoice::whereIn('parent_id', $parentIds)
            ->with(['user', 'payments', 'items'])
            ->whereNotNull('invoice_due_date')
            ->whereBetween('invoice_due_date', [$today, $next7Days])
            ->whereRaw('(SELECT COALESCE(SUM(amount), 0) FROM invoice_payments WHERE invoice_id = invoices.id) < (SELECT COALESCE(SUM(amount), 0) FROM invoice_items WHERE invoice_id = invoices.id)')
            ->orderBy('invoice_due_date')
            ->take(6)
            ->get();

        // ========== Attendance Graph (Last 14 days) ==========
        $attendanceGraph = Attendance::whereIn('parent_id', $parentIds)
            ->where('date', '>=', now('Asia/Kolkata')->subDays(14)->toDateString())
            ->selectRaw('date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => $item->date,
                    'label' => Carbon::parse($item->date)->format('d M'),
                    'count' => $item->count,
                ];
            });

        // ========== Plan Distribution ==========
        // STRICT: only this logged-in gym's membership plans.
        $planDistribution = Membership::whereIn('parent_id', $parentIds)
            ->withCount(['traineeDetails as member_count' => function ($q) use ($parentIds) {
                $q->whereIn('parent_id', $parentIds);
            }])
            ->orderBy('amount')
            ->orderBy('title')
            ->get();

        $data = compact(
            'newMembersCount',
            'expiringCount',
            'expiredCount',
            'activeCount',
            'monthlyIncome',
            'monthlyExpense',
            'incomeExpenseGraph',
            'newMembersList',
            'upcomingPayments',
            'attendanceGraph',
            'planDistribution',
            'month',
            'year'
        );

        if ($this->isAjax($request)) {
            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        }

        return view('panel.reports.index', $data);
    }

    /**
     * Financial report (AJAX ready)
     */
    public function financial(Request $request)
    {
        $parentIds = $this->strictPanelParentIds();
        $month = (int) $request->get('month', date('m'));
        $year = (int) $request->get('year', date('Y'));

        $income = InvoicePayment::whereIn('parent_id', $parentIds)
            ->whereMonth('payment_date', $month)
            ->whereYear('payment_date', $year)
            ->with('invoice.user')
            ->orderBy('payment_date', 'desc')
            ->get();

        $expenses = Expense::whereIn('parent_id', $parentIds)
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->orderBy('date', 'desc')
            ->get();

        if ($this->isAjax($request)) {
            return response()->json([
                'success' => true,
                'income' => $income,
                'expenses' => $expenses,
                'summary' => [
                    'income' => $income->sum('amount'),
                    'expense' => $expenses->sum('amount'),
                ],
            ]);
        }

        return view('panel.reports.financial', compact('income', 'expenses', 'month', 'year'));
    }

    private function strictPanelParentIds(): array
    {
        return [(int) $this->getParentId()];
    }

    private function isAjax(Request $request): bool
    {
        return $request->ajax()
            || $request->wantsJson()
            || $request->header('X-Requested-With') === 'XMLHttpRequest'
            || $request->expectsJson();
    }
}
