<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Type;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ExpenseController extends BaseController
{
    /**
     * List expenses
     */
    public function index(Request $request): JsonResponse
    {
        $parentIds = $this->getGymParentIds();
        $month = $request->get('month', now()->month);
        $year = $request->get('year', now()->year);

        $expenses = Expense::whereIn('parent_id', $parentIds)
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->with('type')
            ->orderBy('date', 'desc')
            ->get();

        $total = $expenses->sum('amount');

        return $this->success([
            'expenses' => $expenses,
            'total' => $total,
        ]);
    }

    /**
     * Create expense
     */
    public function store(Request $request): JsonResponse
    {
        $parentIds = $this->getGymParentIds();

        $request->validate([
            'title' => 'required|string|max:255',
            'date' => 'required|date',
            'amount' => 'required|numeric|min:0',
        ]);

        $pid = $this->getParentId();

        $expense = Expense::create([
            'title' => trim($request->title),
            'expense_id' => $request->expense_id ?? 0,
            'expense_type' => $request->expense_type ?? 0,
            'date' => \Carbon\Carbon::parse($request->date)->toDateString(),
            'amount' => $request->amount,
            'notes' => $request->notes ?? '',
            'parent_id' => $pid,
        ]);

        return $this->success([
            'id' => $expense->id,
            'expense' => $expense,
        ], 'Expense added', 201);
    }

    /**
     * Update expense
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $parentIds = $this->getGymParentIds();

        $expense = Expense::where('id', $id)->whereIn('parent_id', $parentIds)->first();
        if (!$expense) {
            return $this->error('Expense not found', 404);
        }

        $request->validate([
            'title' => 'nullable|string|max:255',
            'date' => 'nullable|date',
            'amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:2000',
        ]);

        $expense->update([
            'title' => $request->has('title') ? trim((string) $request->title) : $expense->title,
            'expense_type' => $request->expense_type ?? $expense->expense_type,
            'date' => $request->has('date') ? \Carbon\Carbon::parse($request->date)->toDateString() : $expense->date,
            'amount' => $request->amount ?? $expense->amount,
            'notes' => $request->has('notes') ? (string) $request->notes : $expense->notes,
        ]);

        return $this->success([], 'Expense updated');
    }

    /**
     * Delete expense
     */
    public function destroy(int $id): JsonResponse
    {
        $parentIds = $this->getGymParentIds();

        $expense = Expense::where('id', $id)->whereIn('parent_id', $parentIds)->first();
        if (!$expense) {
            return $this->error('Expense not found', 404);
        }

        $expense->delete();

        return $this->success([], 'Expense deleted');
    }

    /**
     * List expense types
     */
    public function types(Request $request): JsonResponse
    {
        $parentIds = $this->getGymAndGlobalParentIds();

        $types = Type::whereIn('parent_id', $parentIds)
            ->orderBy('title')
            ->get();

        return $this->success(['types' => $types]);
    }
}
