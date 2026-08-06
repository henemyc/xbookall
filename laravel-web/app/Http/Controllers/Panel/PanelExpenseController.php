<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\BaseController;
use App\Models\Expense;
use App\Models\Type;
use Illuminate\Http\Request;

class PanelExpenseController extends BaseController
{
    /**
     * List expenses
     */
    public function index(Request $request)
    {
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();
        $month = $request->get('month', date('m'));
        $year = $request->get('year', date('Y'));

        $expenses = Expense::whereIn('parent_id', $parentIds)
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->with('type')
            ->orderBy('date', 'desc')
            ->get();   // use collection for dynamic AJAX

        $total = $expenses->sum('amount');

        $types = \App\Models\Type::whereIn('parent_id', $this->getGymAndGlobalParentIds())->orderBy('title')->get();

        return view('panel.expenses.index', compact('expenses', 'total', 'month', 'year', 'types'));
    }

    /**
     * Create expense form
     */
    public function create()
    {
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();
        $types = Type::whereIn('parent_id', $this->getGymAndGlobalParentIds())->orderBy('title')->get();

        return view('panel.expenses.create', compact('types'));
    }

    /**
     * Store expense (AJAX supported)
     */
    public function store(Request $request)
    {
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();

        $request->validate([
            'title' => 'required|string|max:255',
            'date' => 'required|date',
            'amount' => 'required|numeric|min:0',
        ]);

        $expense = Expense::create([
            'title' => $request->title,
            'expense_type' => $request->expense_type ?? 0,
            'date' => $request->date,
            'amount' => $request->amount,
            'notes' => $request->notes ?? '',
            'parent_id' => $pid,
        ]);

        $isAjax = $request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest';

        if ($isAjax) {
            return response()->json([
                'success' => true,
                'message' => 'Expense added successfully',
                'expense' => [
                    'id' => $expense->id,
                    'title' => $expense->title,
                    'amount' => $expense->amount,
                    'date' => $expense->date->format('Y-m-d'),
                    'formatted_date' => $expense->date->format('d M Y'),
                    'notes' => $expense->notes,
                    'type_title' => optional($expense->type)->title,
                ]
            ]);
        }

        return redirect()->route('panel.expenses.index')->with('success', 'Expense added');
    }

    /**
     * Edit expense
     */
    public function edit(int $id)
    {
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();

        $expense = Expense::where('id', $id)->whereIn('parent_id', $parentIds)->firstOrFail();
        $types = Type::whereIn('parent_id', $this->getGymAndGlobalParentIds())->orderBy('title')->get();

        return view('panel.expenses.edit', compact('expense', 'types'));
    }

    /**
     * Update expense (AJAX supported)
     */
    public function update(Request $request, int $id)
    {
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();

        $expense = Expense::where('id', $id)->whereIn('parent_id', $parentIds)->firstOrFail();

        $expense->update([
            'title' => $request->title ?? $expense->title,
            'expense_type' => $request->expense_type ?? $expense->expense_type,
            'date' => $request->date ?? $expense->date,
            'amount' => $request->amount ?? $expense->amount,
            'notes' => $request->notes ?? $expense->notes,
        ]);

        $isAjax = $request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest';

        if ($isAjax) {
            return response()->json([
                'success' => true,
                'message' => 'Expense updated successfully',
                'expense' => [
                    'id' => $expense->id,
                    'title' => $expense->title,
                    'amount' => $expense->amount,
                    'date' => $expense->date->format('Y-m-d'),
                    'formatted_date' => $expense->date->format('d M Y'),
                    'notes' => $expense->notes,
                    'type_title' => optional($expense->type)->title,
                ]
            ]);
        }

        return redirect()->route('panel.expenses.index')->with('success', 'Expense updated');
    }

    /**
     * Delete expense (AJAX supported)
     */
    public function destroy(int $id)
    {
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();

        $expense = Expense::where('id', $id)->whereIn('parent_id', $parentIds)->firstOrFail();
        $expense->delete();

        $isAjax = request()->ajax() || request()->wantsJson() || request()->header('X-Requested-With') === 'XMLHttpRequest';

        if ($isAjax) {
            return response()->json(['success' => true, 'message' => 'Expense deleted']);
        }

        return redirect()->route('panel.expenses.index')->with('success', 'Expense deleted');
    }
}
