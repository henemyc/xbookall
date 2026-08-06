<?php

namespace App\Http\Controllers;

use App\Models\Workout;
use App\Models\WorkoutActivity;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class WorkoutController extends BaseController
{
    /**
     * List workouts
     */
    public function index(Request $request): JsonResponse
    {
        $parentIds = $this->getGymParentIds();
        $user = $this->currentUser();

        if ($user->type === 'trainee') {
            $workouts = Workout::where('assign_id', $user->id)
                ->whereIn('parent_id', $parentIds)
                ->orderBy('created_at', 'desc')
                ->limit(1)
                ->get();
        } else {
            $assignId = $request->get('user_id', 0);
            $workouts = Workout::where('assign_id', $assignId)
                ->whereIn('parent_id', $parentIds)
                ->orderBy('created_at', 'desc')
                ->get();
        }

        return $this->success(['workouts' => $workouts]);
    }

    /**
     * Create workout (used from MemberDetailScreen)
     */
    public function store(Request $request): JsonResponse
    {
        $pid = $this->getParentId();

        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'workout_plan' => 'required|string',
        ]);

        $workout = Workout::create([
            'assign_to' => 'member',
            'assign_id' => $request->user_id,
            'start_date' => $request->start_date ?? now()->toDateString(),
            'end_date' => $request->end_date,
            'workout_history' => $request->workout_plan,   // JSON string
            'notes' => $request->notes ?? '',
            'parent_id' => $pid,
        ]);

        return $this->success([
            'id' => $workout->id,
            'workout' => $workout,
        ], 'Workout plan created', 201);
    }

    /**
     * Update workout
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $parentIds = $this->getGymParentIds();

        $workout = Workout::where('id', $id)->whereIn('parent_id', $parentIds)->first();
        if (!$workout) {
            return $this->error('Workout not found', 404);
        }

        $workout->update([
            'start_date' => $request->start_date ?? $workout->start_date,
            'end_date' => $request->end_date ?? $workout->end_date,
            'workout_history' => $request->workout_plan ?? $workout->workout_history,
            'notes' => $request->notes ?? $workout->notes,
        ]);

        return $this->success([], 'Workout updated');
    }

    /**
     * Delete workout
     */
    public function destroy(int $id): JsonResponse
    {
        $parentIds = $this->getGymParentIds();

        $workout = Workout::where('id', $id)->whereIn('parent_id', $parentIds)->first();
        if (!$workout) {
            return $this->error('Workout not found', 404);
        }

        $workout->delete();

        return $this->success([], 'Workout deleted');
    }

    /**
     * List workout activities
     */
    public function activities(Request $request): JsonResponse
    {
        $parentIds = $this->getGymParentIds();

        $activities = WorkoutActivity::whereIn('parent_id', $parentIds)
            ->orderBy('title')
            ->get();

        return $this->success(['activities' => $activities]);
    }

    /**
     * Create workout activity
     */
    public function storeActivity(Request $request): JsonResponse
    {
        $pid = $this->getParentId();

        $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $activity = WorkoutActivity::create([
            'title' => $request->title,
            'parent_id' => $pid,
        ]);

        return $this->success([
            'id' => $activity->id,
            'activity' => $activity,
        ], 'Activity created', 201);
    }

    /**
     * Delete workout activity
     */
    public function destroyActivity(int $id): JsonResponse
    {
        $parentIds = $this->getGymParentIds();

        $activity = WorkoutActivity::where('id', $id)->whereIn('parent_id', $parentIds)->first();
        if (!$activity) {
            return $this->error('Activity not found', 404);
        }

        $activity->delete();

        return $this->success([], 'Activity deleted');
    }
}
