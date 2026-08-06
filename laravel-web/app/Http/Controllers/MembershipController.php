<?php

namespace App\Http\Controllers;

use App\Models\Membership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MembershipController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $parentIds = $this->strictMembershipParentIds($request);

        $memberships = Membership::whereIn('parent_id', $parentIds)
            ->withCount(['traineeDetails as member_count' => function ($q) use ($parentIds) {
                $q->whereIn('parent_id', $parentIds);
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->success(['memberships' => $memberships]);
    }

    public function store(Request $request): JsonResponse
    {
        if (!$this->isAdmin()) return $this->error('Admin access required', 403);

        $pid = $this->getParentId();

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'package' => 'nullable|string|max:80',
            'classes_id' => 'nullable',
            'notes' => 'nullable|string|max:2000',
        ]);

        $title = trim($data['title']);
        $package = trim($data['package'] ?? '');
        $amount = round((float) $data['amount'], 2);

        $exists = Membership::where('parent_id', $pid)
            ->whereRaw('LOWER(title) = ?', [strtolower($title)])
            ->where('amount', $amount)
            ->where('package', $package)
            ->exists();

        if ($exists) {
            return $this->error('A plan with same name, price and duration already exists', 422);
        }

        $membership = Membership::create([
            'title' => $title,
            'package' => $package,
            'amount' => $amount,
            'classes_id' => $request->classes_id ?? '',
            'parent_id' => $pid,
            'notes' => $data['notes'] ?? '',
        ]);

        return $this->success([
            'id' => $membership->id,
            'membership' => $membership,
        ], 'Membership plan created', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if (!$this->isAdmin()) return $this->error('Admin access required', 403);

        $pid = $this->getParentId();
        $membership = Membership::where('id', $id)->where('parent_id', $pid)->first();
        if (!$membership) return $this->error('Membership plan not found for this gym', 404);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'package' => 'nullable|string|max:80',
            'classes_id' => 'nullable',
            'notes' => 'nullable|string|max:2000',
        ]);

        $title = trim($data['title']);
        $package = trim($data['package'] ?? '');
        $amount = round((float) $data['amount'], 2);

        $exists = Membership::where('parent_id', $pid)
            ->whereRaw('LOWER(title) = ?', [strtolower($title)])
            ->where('amount', $amount)
            ->where('package', $package)
            ->where('id', '!=', $id)
            ->exists();

        if ($exists) {
            return $this->error('Another plan with same name, price and duration exists', 422);
        }

        $membership->update([
            'title' => $title,
            'package' => $package,
            'amount' => $amount,
            'classes_id' => $request->classes_id ?? $membership->classes_id,
            'notes' => $data['notes'] ?? '',
        ]);

        return $this->success(['membership' => $membership->fresh()], 'Membership plan updated');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        if (!$this->isAdmin()) return $this->error('Admin access required', 403);

        $pid = $this->getParentId();
        $membership = Membership::where('id', $id)->where('parent_id', $pid)->first();
        if (!$membership) return $this->error('Membership plan not found for this gym', 404);

        if ($membership->traineeDetails()->where('parent_id', $pid)->count() > 0) {
            return $this->error('Cannot delete plan with active members', 422);
        }

        $membership->delete();
        return $this->success([], 'Membership plan deleted');
    }

    /**
     * Strict plan scope for app/gym panel. Avoid BaseController::getGymParentIds()
     * here because that helper may include root parent 1 for legacy reads, which
     * is why plans from other gyms were visible.
     */
    private function strictMembershipParentIds(Request $request): array
    {
        return $this->getGymParentIds();
    }
}
