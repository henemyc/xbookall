<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\BaseController;
use App\Models\Locker;
use App\Models\AssignLocker;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PanelLockerController extends BaseController
{
    public function index()
    {
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();
        if (!$this->planFeatureEnabled('lockers_enabled', true)) {
            return redirect()->route('panel.dashboard')->with('error', \App\Services\SubscriptionFeatureService::featureLockedMessage('Locker management'));
        }

        $lockers = Locker::whereIn('parent_id', $parentIds)
            ->with('currentAssignment.user')
            ->orderBy('id')
            ->get();

        $availableCount = $lockers->where('available', true)->count();
        $occupiedCount = $lockers->where('available', false)->count();

        // Get members for assign dropdown
        $members = User::where('type', 'trainee')
            ->whereIn('parent_id', $parentIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('panel.lockers.index', compact('lockers', 'availableCount', 'occupiedCount', 'members'));
    }

    /**
     * Create lockers with custom names (AJAX supported)
     */
    public function store(Request $request)
    {
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();
        if (!$this->planFeatureEnabled('lockers_enabled', true)) {
            return $this->subscriptionDenied($request, \App\Services\SubscriptionFeatureService::featureLockedMessage('Locker management'));
        }

        $names = $request->input('locker_names', '');
        $nameList = array_filter(array_map('trim', explode(',', $names)));

        if (empty($nameList)) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'error' => 'Please enter at least one locker name'], 422);
            }
            return back()->with('error', 'Please enter at least one locker name');
        }

        $created = 0;
        foreach ($nameList as $name) {
            if (empty($name)) continue;
            Locker::create([
                'parent_id' => $pid,
                'name' => $name,
                'status' => 1,
                'available' => true,
            ]);
            $created++;
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "{$created} locker(s) created"
            ]);
        }

        return back()->with('success', "{$created} locker(s) created");
    }

    /**
     * Assign locker to member (AJAX supported)
     */
    public function assign(Request $request)
    {
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();
        if (!$this->planFeatureEnabled('lockers_enabled', true)) {
            return $this->subscriptionDenied($request, \App\Services\SubscriptionFeatureService::featureLockedMessage('Locker management'));
        }

        $request->validate([
            'user_id' => 'required|integer',
            'locker_id' => 'required|integer',
        ]);

        $locker = Locker::where('id', $request->locker_id)->whereIn('parent_id', $parentIds)->firstOrFail();

        if (!$locker->available) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'error' => 'Locker is already assigned'], 422);
            }
            return back()->with('error', 'Locker is already assigned');
        }

        DB::beginTransaction();
        try {
            AssignLocker::create([
                'user_id' => $request->user_id,
                'locker_id' => $request->locker_id,
                'assign_date' => date('Y-m-d'),
            ]);

            $locker->update(['available' => false]);

            DB::commit();

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => true, 'message' => 'Locker assigned successfully']);
            }
            return back()->with('success', 'Locker assigned successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'error' => 'Failed to assign locker'], 500);
            }
            return back()->with('error', 'Failed to assign locker');
        }
    }

    /**
     * Unassign locker (AJAX supported)
     */
    public function unassign(int $id)
    {
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();

        $locker = Locker::where('id', $id)->whereIn('parent_id', $parentIds)->firstOrFail();

        DB::beginTransaction();
        try {
            AssignLocker::where('locker_id', $id)
                ->whereNull('end_date')
                ->update(['end_date' => date('Y-m-d')]);

            $locker->update(['available' => true]);

            DB::commit();

            if (request()->ajax() || request()->wantsJson()) {
                return response()->json(['success' => true, 'message' => 'Locker released']);
            }
            return back()->with('success', 'Locker released');
        } catch (\Exception $e) {
            DB::rollBack();
            if (request()->ajax() || request()->wantsJson()) {
                return response()->json(['success' => false, 'error' => 'Failed to release locker'], 500);
            }
            return back()->with('error', 'Failed to release locker');
        }
    }

    /**
     * Delete locker (AJAX supported)
     */
    public function destroy(int $id)
    {
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();

        $locker = Locker::where('id', $id)->whereIn('parent_id', $parentIds)->firstOrFail();

        DB::beginTransaction();
        try {
            AssignLocker::where('locker_id', $id)->delete();
            $locker->delete();

            DB::commit();

            if (request()->ajax() || request()->wantsJson()) {
                return response()->json(['success' => true, 'message' => 'Locker deleted']);
            }
            return back()->with('success', 'Locker deleted');
        } catch (\Exception $e) {
            DB::rollBack();
            if (request()->ajax() || request()->wantsJson()) {
                return response()->json(['success' => false, 'error' => 'Failed to delete locker'], 500);
            }
            return back()->with('error', 'Failed to delete locker');
        }
    }

    /**
     * Delete all lockers (AJAX supported)
     */
    public function deleteAll()
    {
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();

        DB::beginTransaction();
        try {
            $lockerIds = Locker::whereIn('parent_id', $parentIds)->pluck('id')->toArray();
            if (!empty($lockerIds)) {
                AssignLocker::whereIn('locker_id', $lockerIds)->delete();
            }
            Locker::whereIn('parent_id', $parentIds)->delete();

            DB::commit();

            if (request()->ajax() || request()->wantsJson()) {
                return response()->json(['success' => true, 'message' => 'All lockers deleted']);
            }
            return redirect()->route('panel.lockers.index')->with('success', 'All lockers deleted');
        } catch (\Exception $e) {
            DB::rollBack();
            if (request()->ajax() || request()->wantsJson()) {
                return response()->json(['success' => false, 'error' => 'Failed to delete lockers'], 500);
            }
            return back()->with('error', 'Failed to delete lockers');
        }
    }
}
