<?php

namespace App\Http\Controllers;

use App\Models\ClassAssign;
use App\Models\ClassSchedule;
use App\Models\GymClass;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClassController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        if (!$this->isAdmin()) return $this->error('Admin access required', 403);
        if (!$this->planFeatureEnabled('classes_enabled', true)) {
            return $this->error(\App\Services\SubscriptionFeatureService::featureLockedMessage('Classes'), 402);
        }

        $parentIds = $this->getGymParentIds();

        $classes = GymClass::whereIn('parent_id', $parentIds)
            ->with(['schedules', 'assigns.user'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($class) => $this->classPayload($class));

        return $this->success(['classes' => $classes]);
    }

    public function store(Request $request): JsonResponse
    {
        if (!$this->isAdmin()) return $this->error('Admin access required', 403);
        if (!$this->planFeatureEnabled('classes_enabled', true)) {
            return $this->error(\App\Services\SubscriptionFeatureService::featureLockedMessage('Classes'), 402);
        }

        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'fees' => 'nullable|numeric|min:0',
            'address' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:2000',
            'days' => 'nullable|string|max:255',
            'start_time' => 'nullable|string|max:20',
            'end_time' => 'nullable|string|max:20',
            'trainer_ids' => 'nullable',
        ]);

        $exists = GymClass::whereIn('parent_id', $parentIds)
            ->whereRaw('LOWER(title) = ?', [strtolower(trim($data['title']))])
            ->where('fees', $data['fees'] ?? 0)
            ->exists();

        if ($exists) {
            return $this->error('A class with the same name and fees already exists', 422);
        }

        $trainerIds = $this->parseIdList($request->input('trainer_ids', []));
        $validTrainerIds = $this->validTrainerIds($trainerIds, $parentIds);
        if (count($validTrainerIds) !== count($trainerIds)) {
            return $this->error('One or more selected trainers are invalid for this gym', 422);
        }

        DB::beginTransaction();
        try {
            $class = GymClass::create([
                'title' => trim($data['title']),
                'fees' => $data['fees'] ?? 0,
                'address' => $data['address'] ?? '',
                'notes' => $data['notes'] ?? '',
                'parent_id' => $pid,
            ]);

            $this->syncSchedule($class->id, $pid, $request);
            $this->syncTrainers($class->id, $validTrainerIds);

            DB::commit();

            $class->load(['schedules', 'assigns.user']);
            return $this->success([
                'id' => $class->id,
                'class' => $this->classPayload($class),
            ], 'Class created', 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->error('Failed to create class', 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        if (!$this->isAdmin()) return $this->error('Admin access required', 403);

        $class = $this->findScopedClass($id);
        if (!$class) return $this->error('Class not found', 404);

        $class->load(['schedules', 'assigns.user']);
        return $this->success(['class' => $this->classPayload($class)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if (!$this->isAdmin()) return $this->error('Admin access required', 403);

        $class = $this->findScopedClass($id);
        if (!$class) return $this->error('Class not found', 404);

        $parentIds = $this->getGymParentIds();
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'fees' => 'nullable|numeric|min:0',
            'address' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:2000',
            'days' => 'nullable|string|max:255',
            'start_time' => 'nullable|string|max:20',
            'end_time' => 'nullable|string|max:20',
            'trainer_ids' => 'nullable',
        ]);

        $exists = GymClass::whereIn('parent_id', $parentIds)
            ->whereRaw('LOWER(title) = ?', [strtolower(trim($data['title']))])
            ->where('fees', $data['fees'] ?? 0)
            ->where('id', '!=', $class->id)
            ->exists();

        if ($exists) {
            return $this->error('Another class with the same name and fees already exists', 422);
        }

        $trainerIds = $this->parseIdList($request->input('trainer_ids', []));
        $validTrainerIds = $this->validTrainerIds($trainerIds, $parentIds);
        if (count($validTrainerIds) !== count($trainerIds)) {
            return $this->error('One or more selected trainers are invalid for this gym', 422);
        }

        DB::beginTransaction();
        try {
            $class->update([
                'title' => trim($data['title']),
                'fees' => $data['fees'] ?? 0,
                'address' => $data['address'] ?? '',
                'notes' => $data['notes'] ?? '',
            ]);

            if ($request->has('days') || $request->has('start_time') || $request->has('end_time')) {
                $this->syncSchedule($class->id, $class->parent_id ?: $this->getParentId(), $request, true);
            }
            if ($request->has('trainer_ids')) {
                $this->syncTrainers($class->id, $validTrainerIds);
            }

            DB::commit();

            $class->refresh()->load(['schedules', 'assigns.user']);
            return $this->success(['class' => $this->classPayload($class)], 'Class updated');
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->error('Failed to update class', 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        if (!$this->isAdmin()) return $this->error('Admin access required', 403);

        $class = $this->findScopedClass($id);
        if (!$class) return $this->error('Class not found', 404);

        $memberCount = ClassAssign::where('classes_id', $class->id)
            ->where('assign_type', 'member')
            ->count();

        if ($memberCount > 0) {
            return $this->error('Cannot delete class with enrolled members', 422);
        }

        DB::beginTransaction();
        try {
            ClassSchedule::where('classes_id', $class->id)->delete();
            ClassAssign::where('classes_id', $class->id)->delete();
            $class->delete();
            DB::commit();

            return $this->success([], 'Class deleted');
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->error('Failed to delete class', 500);
        }
    }

    private function findScopedClass(int $id): ?GymClass
    {
        $parentIds = $this->getGymParentIds();

        return GymClass::where('id', $id)
            ->whereIn('parent_id', $parentIds)
            ->first();
    }

    private function classPayload(GymClass $class): array
    {
        $class->loadMissing(['schedules', 'assigns.user']);

        $trainerAssigns = $class->assigns
            ->where('assign_type', 'trainer')
            ->filter(fn($assign) => $assign->user);
        $memberAssigns = $class->assigns->where('assign_type', 'member');

        $trainers = $trainerAssigns->map(function ($assign) {
            return [
                'id' => $assign->user->id,
                'name' => $assign->user->name,
                'phone_number' => $assign->user->phone_number,
            ];
        })->values();

        $schedules = $class->schedules->map(function ($schedule) {
            return [
                'id' => $schedule->id,
                'days' => $schedule->days,
                'start_time' => $this->cleanTime($schedule->start_time ?? ''),
                'end_time' => $this->cleanTime($schedule->end_time ?? ''),
            ];
        })->values();

        return [
            'id' => $class->id,
            'title' => $class->title,
            'fees' => $class->fees,
            'address' => $class->address,
            'notes' => $class->notes,
            'parent_id' => $class->parent_id,
            'assigned_count' => $memberAssigns->count(),
            'member_count' => $memberAssigns->count(),
            'trainer_count' => $trainers->count(),
            'trainer_ids' => $trainers->pluck('id')->values(),
            'trainer_names' => $trainers->pluck('name')->values(),
            'trainers' => $trainers,
            'schedules' => $schedules,
            'created_at' => $class->created_at,
            'updated_at' => $class->updated_at,
        ];
    }

    private function parseIdList($value): array
    {
        if ($value === null || $value === '') return [];

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') return [];
            $decoded = json_decode($trimmed, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $value = $decoded;
            } else {
                $value = explode(',', $trimmed);
            }
        }

        if (!is_array($value)) $value = [$value];

        $ids = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                $item = $item['id'] ?? $item['value'] ?? null;
            }
            $id = (int) $item;
            if ($id > 0) $ids[] = $id;
        }

        return array_values(array_unique($ids));
    }

    private function validTrainerIds(array $trainerIds, array $parentIds): array
    {
        if (empty($trainerIds)) return [];

        return User::whereIn('id', $trainerIds)
            ->where('type', 'trainer')
            ->whereIn('parent_id', $parentIds)
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->values()
            ->all();
    }

    private function syncTrainers(int $classId, array $trainerIds): void
    {
        ClassAssign::where('classes_id', $classId)
            ->where('assign_type', 'trainer')
            ->delete();

        foreach ($trainerIds as $trainerId) {
            ClassAssign::create([
                'classes_id' => $classId,
                'assign_id' => $trainerId,
                'assign_type' => 'trainer',
            ]);
        }
    }

    private function syncSchedule(int $classId, int $parentId, Request $request, bool $replace = false): void
    {
        if ($replace) {
            ClassSchedule::where('classes_id', $classId)->delete();
        }

        if (!$request->filled('days') || !$request->filled('start_time') || !$request->filled('end_time')) {
            return;
        }

        ClassSchedule::create([
            'classes_id' => $classId,
            'days' => $request->input('days'),
            'start_time' => $this->cleanTime($request->input('start_time')),
            'end_time' => $this->cleanTime($request->input('end_time')),
            'parent_id' => $parentId,
        ]);
    }

    private function cleanTime($time): string
    {
        if (!$time) return '';
        return substr((string) $time, 0, 5);
    }
}
