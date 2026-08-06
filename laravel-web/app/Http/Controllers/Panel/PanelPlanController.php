<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\BaseController;
use App\Models\Membership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PanelPlanController extends BaseController
{
    /**
     * List only the logged-in gym's own plans.
     * Super Admin is user id 1; gym owner users may have parent_id=1, but their
     * membership plans must stay under parent_id = gym owner users.id.
     */
    public function index()
    {
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();

        $plans = Membership::where('parent_id', $pid)
            ->withCount(['traineeDetails as member_count' => function ($q) use ($pid) {
                $q->where('parent_id', $pid);
            }])
            ->orderBy('amount')
            ->orderBy('title')
            ->get();

        return view('panel.plans.index', compact('plans'));
    }

    /**
     * Create plan (AJAX + normal form supported)
     */
    public function store(Request $request)
    {
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();
        $isAjax = $this->isAjax($request);

        try {
            $data = $request->validate([
                'title' => 'required|string|max:255',
                'amount' => 'required|numeric|min:0',
                'package' => 'required|string|max:80',
                'notes' => 'nullable|string|max:2000',
            ]);
        } catch (ValidationException $e) {
            if ($isAjax) {
                return response()->json(['success' => false, 'error' => $this->firstValidationError($e), 'errors' => $e->errors()], 422);
            }
            throw $e;
        }

        $title = trim($data['title']);
        $package = trim($data['package']);
        $amount = round((float) $data['amount'], 2);

        $exists = Membership::where('parent_id', $pid)
            ->whereRaw('LOWER(title) = ?', [strtolower($title)])
            ->where('amount', $amount)
            ->where('package', $package)
            ->exists();

        if ($exists) {
            return $this->planError($request, 'A plan with same name, price and duration already exists', 422);
        }

        DB::beginTransaction();
        try {
            $plan = Membership::create([
                'title' => $title,
                'package' => $package,
                'amount' => $amount,
                'classes_id' => $request->classes_id ?? '',
                'notes' => $data['notes'] ?? '',
                'parent_id' => $pid,
            ]);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->planError($request, 'Failed to create plan', 500);
        }

        if ($isAjax) {
            return response()->json([
                'success' => true,
                'message' => 'Plan created successfully',
                'plan' => $this->planPayload($plan, 0),
            ]);
        }

        return redirect()->route('panel.plans.index')->with('success', 'Plan created successfully');
    }

    /**
     * Update plan (AJAX + normal form supported)
     */
    public function update(Request $request, int $id)
    {
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();
        $isAjax = $this->isAjax($request);

        $plan = Membership::where('id', $id)->where('parent_id', $pid)->first();
        if (!$plan) {
            return $this->planError($request, 'Plan not found for this gym', 404);
        }

        try {
            $data = $request->validate([
                'title' => 'required|string|max:255',
                'amount' => 'required|numeric|min:0',
                'package' => 'required|string|max:80',
                'notes' => 'nullable|string|max:2000',
            ]);
        } catch (ValidationException $e) {
            if ($isAjax) {
                return response()->json(['success' => false, 'error' => $this->firstValidationError($e), 'errors' => $e->errors()], 422);
            }
            throw $e;
        }

        $title = trim($data['title']);
        $package = trim($data['package']);
        $amount = round((float) $data['amount'], 2);

        $exists = Membership::where('parent_id', $pid)
            ->whereRaw('LOWER(title) = ?', [strtolower($title)])
            ->where('amount', $amount)
            ->where('package', $package)
            ->where('id', '!=', $id)
            ->exists();

        if ($exists) {
            return $this->planError($request, 'Another plan with same name, price and duration exists', 422);
        }

        $plan->update([
            'title' => $title,
            'package' => $package,
            'amount' => $amount,
            'notes' => $data['notes'] ?? '',
        ]);

        $memberCount = $plan->traineeDetails()->where('parent_id', $pid)->count();

        if ($isAjax) {
            return response()->json([
                'success' => true,
                'message' => 'Plan updated successfully',
                'plan' => $this->planPayload($plan->fresh(), $memberCount),
            ]);
        }

        return redirect()->route('panel.plans.index')->with('success', 'Plan updated successfully');
    }

    /**
     * Delete plan (AJAX + normal form supported)
     */
    public function destroy(Request $request, int $id)
    {
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();
        $plan = Membership::where('id', $id)->where('parent_id', $pid)->first();

        if (!$plan) {
            return $this->planError($request, 'Plan not found for this gym', 404);
        }

        if ($plan->traineeDetails()->where('parent_id', $pid)->count() > 0) {
            return $this->planError($request, 'Cannot delete plan with active members', 422);
        }

        $plan->delete();

        if ($this->isAjax($request)) {
            return response()->json(['success' => true, 'message' => 'Plan deleted successfully']);
        }

        return redirect()->route('panel.plans.index')->with('success', 'Plan deleted');
    }

    private function planPayload(Membership $plan, int $memberCount = 0): array
    {
        return [
            'id' => $plan->id,
            'title' => $plan->title,
            'amount' => $plan->amount,
            'package' => $plan->package,
            'notes' => $plan->notes,
            'member_count' => $memberCount,
        ];
    }

    private function planError(Request $request, string $message, int $status = 400)
    {
        if ($this->isAjax($request)) {
            return response()->json(['success' => false, 'error' => $message], $status);
        }

        return back()->withInput()->with('error', $message);
    }

    private function isAjax(Request $request): bool
    {
        return $request->ajax()
            || $request->wantsJson()
            || $request->header('X-Requested-With') === 'XMLHttpRequest'
            || $request->expectsJson();
    }

    private function firstValidationError(ValidationException $e): string
    {
        $errors = $e->errors();
        $first = reset($errors);
        return is_array($first) ? (string) ($first[0] ?? 'Validation failed') : 'Validation failed';
    }
}
