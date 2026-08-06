<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\BaseController;
use App\Models\InvoicePayment;
use App\Models\Expense;
use Illuminate\Http\Request;

class PanelTransactionController extends BaseController
{
    /**
     * Transactions page
     */
    public function index(Request $request)
    {
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();
        $month = intval($request->get('month', date('m')));
        $year = intval($request->get('year', date('Y')));

        // Get all months that have transactions
        $months = collect();
        for ($i = 0; $i < 12; $i++) {
            $m = date('m', strtotime("-{$i} months"));
            $y = date('Y', strtotime("-{$i} months"));
            $months->push(['month' => intval($m), 'year' => intval($y)]);
        }

        // Income from payments
        $income = InvoicePayment::whereIn('parent_id', $parentIds)
            ->whereMonth('payment_date', $month)
            ->whereYear('payment_date', $year)
            ->with('invoice.user')
            ->orderBy('payment_date', 'desc')
            ->get()
            ->map(function ($p) {
                return [
                    'type' => 'income',
                    'date' => $p->payment_date,
                    'amount' => $p->amount,
                    'method' => ucfirst($p->payment_type),
                    'member' => $p->invoice && $p->invoice->user ? $p->invoice->user->name : 'Unknown',
                    'description' => 'Payment - Invoice #' . ($p->invoice ? $p->invoice->invoice_id : ''),
                    'icon' => 'arrow-up-circle',
                    'color' => 'success',
                ];
            });

        // Expenses
        $expenses = Expense::whereIn('parent_id', $parentIds)
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->orderBy('date', 'desc')
            ->get()
            ->map(function ($e) {
                return [
                    'type' => 'expense',
                    'date' => $e->date,
                    'amount' => $e->amount,
                    'method' => '-',
                    'member' => '-',
                    'description' => $e->title ?? 'Expense',
                    'icon' => 'arrow-down-circle',
                    'color' => 'danger',
                ];
            });

        // Merge and sort
        $transactions = $income->merge($expenses)->sortByDesc('date')->values();

        $totalIncome = $income->sum('amount');
        $totalExpense = $expenses->sum('amount');
        $netBalance = $totalIncome - $totalExpense;

        $transactions = $transactions->values(); // ensure collection

        // AJAX response
        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            $perPage = 25;
            $currentPage = (int) $request->get('page', 1);
            $paginated = $transactions->forPage($currentPage, $perPage);

            $html = '';
            foreach ($paginated as $txn) {
                $html .= view('panel.transactions._row', compact('txn'))->render();
            }

            return response()->json([
                'success' => true,
                'html' => $html,
                'current_page' => $currentPage,
                'has_more' => $transactions->count() > ($currentPage * $perPage),
                'summary' => [
                    'income' => $totalIncome,
                    'expense' => $totalExpense,
                    'net' => $netBalance,
                ],
                'total' => $transactions->count(),
            ]);
        }

        // Paginate for initial view (optional, but keep compatibility)
        $transactions = $transactions->values();

        return view('panel.transactions.index', compact(
            'transactions', 'totalIncome', 'totalExpense', 'netBalance',
            'month', 'year', 'months'
        ));
    }
}
