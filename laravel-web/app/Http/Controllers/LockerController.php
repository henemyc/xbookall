<?php

namespace App\Http\Controllers;

use App\Models\Locker;
use App\Models\AssignLocker;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class LockerController extends BaseController
{
    // Phase 3: locker actions below require their exact locker permission.
    /**
     * List lockers
     */
    public function index(Request $request): JsonResponse
    {
        if (!$this->canPerformGymAction('lockers.view')) {
            return $this->error('Permission denied', 403);
        }

        if (!$this->planFeatureEnabled('lockers_enabled', true)) {
            return $this->error(\App\Services\SubscriptionFeatureService::featureLockedMessage('Locker management'), 402);
        }
        $parentIds = $this->getGymParentIds();

        $lockers = Locker::whereIn('parent_id', $parentIds)
            ->with(['currentAssignment.user'])
            ->orderBy('id')
            ->get();

        return $this->success(['lockers' => $lockers]);
    }

    /**
     * Create lockers
     */
    public function store(Request $request): JsonResponse
    {
        if (!$this->canPerformGymAction('lockers.create')) {
            return $this->error('Permission denied', 403);
        }

        if (!$this->planFeatureEnabled('lockers_enabled', true)) {
            return $this->error(\App\Services\SubscriptionFeatureService::featureLockedMessage('Locker management'), 402);
        }
        $parentIds = $this->getGymParentIds();
        $pid = $this->getParentId();
        $count = intval($request->count ?? 1);

        for ($i = 0; $i < $count; $i++) {
            Locker::create([
                'parent_id' => $pid,
                'status' => 1,
                'available' => true,
            ]);
        }

        return $this->success([
            'created' => $count,
        ], "{$count} locker(s) created", 201);
    }

    /**
     * Assign locker to member
     */
    public function assign(Request $request): JsonResponse
    {
        if (!$this->canPerformGymAction('lockers.assign')) {
            return $this->error('Permission denied', 403);
        }

        if (!$this->planFeatureEnabled('lockers_enabled', true)) {
            return $this->error(\App\Services\SubscriptionFeatureService::featureLockedMessage('Locker management'), 402);
        }
        $parentIds = $this->getGymParentIds();

        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'locker_id' => 'required|integer|exists:lockers,id',
        ]);

        $locker = Locker::where('id', $request->locker_id)->whereIn('parent_id', $parentIds)->first();
        if (!$locker) {
            return $this->error('Locker not found', 404);
        }

        if (!$locker->available) {
            return $this->error('Locker is already assigned', 400);
        }

        DB::beginTransaction();
        try {
            AssignLocker::create([
                'user_id' => $request->user_id,
                'locker_id' => $request->locker_id,
                'assign_date' => $request->assign_date ?? now()->toDateString(),
                'end_date' => $request->end_date,
            ]);

            $locker->update(['available' => false]);

            DB::commit();

            return $this->success([], 'Locker assigned successfully', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to assign locker: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Unassign locker
     */
    public function unassign(Request $request): JsonResponse
    {
        if (!$this->canPerformGymAction('lockers.assign')) {
            return $this->error('Permission denied', 403);
        }

        $parentIds = $this->getGymParentIds();

        $request->validate([
            'locker_id' => 'required|integer|exists:lockers,id',
        ]);

        $locker = Locker::where('id', $request->locker_id)->whereIn('parent_id', $parentIds)->first();
        if (!$locker) {
            return $this->error('Locker not found', 404);
        }

        DB::beginTransaction();
        try {
            AssignLocker::where('locker_id', $request->locker_id)
                ->whereNull('end_date')
                ->update(['end_date' => now()->toDateString()]);

            $locker->update(['available' => true]);

            DB::commit();

            return $this->success([], 'Locker unassigned successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to unassign locker: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete locker
     */
    public function destroy(int $id): JsonResponse
    {
        if (!$this->canPerformGymAction('lockers.delete')) {
            return $this->error('Permission denied', 403);
        }

        $parentIds = $this->getGymParentIds();

        $locker = Locker::where('id', $id)->whereIn('parent_id', $parentIds)->first();
        if (!$locker) {
            return $this->error('Locker not found', 404);
        }

        DB::beginTransaction();
        try {
            AssignLocker::where('locker_id', $id)->delete();
            $locker->delete();

            DB::commit();

            return $this->success([], 'Locker deleted');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to delete locker: ' . $e->getMessage(), 500);
        }
    }
}
