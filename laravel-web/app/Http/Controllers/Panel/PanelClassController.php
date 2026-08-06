<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\BaseController;
use App\Models\GymClass;
use App\Models\ClassAssign;
use App\Models\ClassSchedule;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PanelClassController extends BaseController
{
    public function index()
    {
        $parentIds = $this->getGymParentIds();
        if (!$this->planFeatureEnabled('classes_enabled', true)) {
            return redirect()->route('panel.dashboard')->with('error', \App\Services\SubscriptionFeatureService::featureLockedMessage('Classes'));
        }

        $classes = GymClass::whereIn('parent_id', $parentIds)
            ->withCount(['assignedMembers as member_count'])
            ->with(['schedules', 'assigns.user'])
            ->orderBy('title')
            ->get();

        $trainers = User::where('type', 'trainer')
            ->whereIn('parent_id', $parentIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'phone_number']);

        return view('panel.classes.index', compact('classes', 'trainers'));
    }

    public function store(Request $request)
    {
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();
        if (!$this->planFeatureEnabled('classes_enabled', true)) {
            return $this->subscriptionDenied($request, \App\Services\SubscriptionFeatureService::featureLockedMessage('Classes'));
        }

        $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $isAjax = $request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest' || $request->expectsJson();

        // Prevent duplicate class (same title + fees)
        $exists = GymClass::whereIn('parent_id', $parentIds)
            ->where('title', $request->title)
            ->where('fees', $request->fees ?? 0)
            ->exists();

        if ($exists) {
            if ($isAjax) {
                return response()->json(['success' => false, 'error' => 'A class with same name and fees already exists'], 422);
            }
            return back()->with('error', 'A class with same name and fees already exists');
        }

        DB::beginTransaction();
        try {
            $class = GymClass::create([
                'title' => $request->title,
                'fees' => $request->fees ?? 0,
                'address' => $request->address ?? '',
                'notes' => $request->notes ?? '',
                'parent_id' => $pid,
            ]);

            if ($request->days && $request->start_time && $request->end_time) {
                ClassSchedule::create([
                    'classes_id' => $class->id,
                    'days' => $request->days,
                    'start_time' => substr((string) $request->start_time, 0, 5),
                    'end_time' => substr((string) $request->end_time, 0, 5),
                    'parent_id' => $pid,
                ]);
            }

            $this->syncTrainerAssignments($class->id, $this->validTrainerIds($request->input('trainer_ids', []), $parentIds));

            DB::commit();

            if ($isAjax) {
                return response()->json([
                    'success' => true,
                    'message' => 'Class created successfully',
                    'class' => [
                        'id' => $class->id,
                        'title' => $class->title,
                        'fees' => $class->fees,
                        'address' => $class->address,
                        'notes' => $class->notes,
                        'member_count' => 0,
                        'trainer_ids' => $this->assignedTrainerIds($class->id),
                        'trainer_names' => $this->assignedTrainerNames($class->id),
                    ]
                ]);
            }

            return redirect()->route('panel.classes.index')->with('success', 'Class created successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            if ($isAjax) {
                return response()->json(['success' => false, 'error' => 'Failed to create class'], 500);
            }
            return back()->with('error', 'Failed to create class');
        }
    }

    public function update(Request $request, int $id)
    {
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();

        $class = GymClass::where('id', $id)->whereIn('parent_id', $parentIds)->firstOrFail();

        $isAjax = $request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest' || $request->expectsJson();

        // Prevent duplicate
        $exists = GymClass::whereIn('parent_id', $parentIds)
            ->where('title', $request->title)
            ->where('fees', $request->fees ?? 0)
            ->where('id', '!=', $id)
            ->exists();

        if ($exists) {
            if ($isAjax) {
                return response()->json(['success' => false, 'error' => 'Another class with same name and fees exists'], 422);
            }
            return back()->with('error', 'Another class with same name and fees exists');
        }

        $class->update([
            'title' => $request->title ?? $class->title,
            'fees' => $request->fees ?? $class->fees,
            'address' => $request->address ?? $class->address,
            'notes' => $request->notes ?? $class->notes,
        ]);

        if ($request->has('days') || $request->has('start_time') || $request->has('end_time')) {
            ClassSchedule::where('classes_id', $class->id)->delete();
            if ($request->filled('days') && $request->filled('start_time') && $request->filled('end_time')) {
                ClassSchedule::create([
                    'classes_id' => $class->id,
                    'days' => $request->days,
                    'start_time' => substr((string) $request->start_time, 0, 5),
                    'end_time' => substr((string) $request->end_time, 0, 5),
                    'parent_id' => $pid,
                ]);
            }
        }

        if ($request->has('trainer_ids')) {
            $this->syncTrainerAssignments($class->id, $this->validTrainerIds($request->input('trainer_ids', []), $parentIds));
        }

        if ($isAjax) {
            return response()->json([
                'success' => true,
                'message' => 'Class updated successfully',
                'class' => [
                    'id' => $class->id,
                    'title' => $class->title,
                    'fees' => $class->fees,
                    'address' => $class->address,
                    'notes' => $class->notes,
                    'trainer_ids' => $this->assignedTrainerIds($class->id),
                    'trainer_names' => $this->assignedTrainerNames($class->id),
                ]
            ]);
        }

        return redirect()->route('panel.classes.index')->with('success', 'Class updated');
    }

    public function destroy(int $id)
    {
        $parentIds = $this->getGymParentIds();
        $class = GymClass::where('id', $id)->whereIn('parent_id', $parentIds)->firstOrFail();

        $isAjax = request()->ajax() || request()->wantsJson() || request()->header('X-Requested-With') === 'XMLHttpRequest' || request()->expectsJson();

        if ($class->assignedMembers()->count() > 0) {
            if ($isAjax) {
                return response()->json(['success' => false, 'error' => 'Cannot delete class with enrolled members'], 422);
            }
            return back()->with('error', 'Cannot delete class with enrolled members');
        }

        DB::beginTransaction();
        try {
            ClassSchedule::where('classes_id', $id)->delete();
            ClassAssign::where('classes_id', $id)->delete();
            $class->delete();
            DB::commit();

            if ($isAjax) {
                return response()->json(['success' => true, 'message' => 'Class deleted successfully']);
            }
            return redirect()->route('panel.classes.index')->with('success', 'Class deleted');
        } catch (\Exception $e) {
            DB::rollBack();
            if ($isAjax) {
                return response()->json(['success' => false, 'error' => 'Failed to delete class'], 500);
            }
            return back()->with('error', 'Failed to delete class');
        }
    }


    private function parseIdList($value): array
    {
        if ($value === null || $value === '') return [];
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : explode(',', $value);
        }
        if (!is_array($value)) $value = [$value];

        $ids = [];
        foreach ($value as $item) {
            $id = is_array($item) ? (int) ($item['id'] ?? 0) : (int) $item;
            if ($id > 0) $ids[] = $id;
        }
        return array_values(array_unique($ids));
    }

    private function validTrainerIds($value, array $parentIds): array
    {
        $ids = $this->parseIdList($value);
        if (empty($ids)) return [];

        return User::whereIn('id', $ids)
            ->where('type', 'trainer')
            ->whereIn('parent_id', $parentIds)
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->values()
            ->all();
    }

    private function syncTrainerAssignments(int $classId, array $trainerIds): void
    {
        ClassAssign::where('classes_id', $classId)->where('assign_type', 'trainer')->delete();
        foreach ($trainerIds as $trainerId) {
            ClassAssign::create([
                'classes_id' => $classId,
                'assign_id' => $trainerId,
                'assign_type' => 'trainer',
            ]);
        }
    }

    private function assignedTrainerIds(int $classId): array
    {
        return ClassAssign::where('classes_id', $classId)
            ->where('assign_type', 'trainer')
            ->pluck('assign_id')
            ->map(fn($id) => (int) $id)
            ->values()
            ->all();
    }

    private function assignedTrainerNames(int $classId): array
    {
        return User::whereIn('id', $this->assignedTrainerIds($classId))
            ->orderBy('name')
            ->pluck('name')
            ->values()
            ->all();
    }

}
