<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\BaseController;
use App\Models\Workout;
use App\Models\WorkoutActivity;
use Illuminate\Http\Request;

class PanelWorkoutController extends BaseController
{
    /**
     * Store workout
     */
    public function store(Request $request)
    {
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();

        $request->validate([
            'user_id' => 'required|integer',
        ]);

        // Build workout plan from form data
        $workoutPlan = [];
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        
        foreach ($days as $day) {
            $exercises = $request->input("workout.{$day}", []);
            if (!empty($exercises)) {
                $dayExercises = [];
                foreach ($exercises as $ex) {
                    if (!empty($ex['exercise'])) {
                        $dayExercises[] = [
                            'exercise' => $ex['exercise'],
                            'sets' => $ex['sets'] ?? 0,
                            'reps' => $ex['reps'] ?? '',
                        ];
                    }
                }
                if (!empty($dayExercises)) {
                    $workoutPlan[$day] = $dayExercises;
                }
            }
        }

        if (empty($workoutPlan)) {
            return back()->with('error', 'Please add at least one exercise');
        }

        Workout::create([
            'assign_to' => 'member',
            'assign_id' => $request->user_id,
            'start_date' => $request->start_date ?? date('Y-m-d'),
            'end_date' => $request->end_date,
            'workout_history' => json_encode($workoutPlan),
            'notes' => $request->notes ?? '',
            'parent_id' => $pid,
        ]);

        return back()->with('success', 'Workout plan added');
    }

    /**
     * Update workout
     */
    public function update(Request $request, int $id)
    {
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();

        $workout = Workout::where('id', $id)->whereIn('parent_id', $parentIds)->firstOrFail();

        // Build workout plan from form data
        $workoutPlan = [];
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        
        foreach ($days as $day) {
            $exercises = $request->input("workout.{$day}", []);
            if (!empty($exercises)) {
                $dayExercises = [];
                foreach ($exercises as $ex) {
                    if (!empty($ex['exercise'])) {
                        $dayExercises[] = [
                            'exercise' => $ex['exercise'],
                            'sets' => $ex['sets'] ?? 0,
                            'reps' => $ex['reps'] ?? '',
                        ];
                    }
                }
                if (!empty($dayExercises)) {
                    $workoutPlan[$day] = $dayExercises;
                }
            }
        }

        $workout->update([
            'workout_history' => json_encode($workoutPlan),
            'notes' => $request->notes ?? $workout->notes,
        ]);

        return back()->with('success', 'Workout plan updated');
    }

    /**
     * Delete workout
     */
    public function destroy(int $id)
    {
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();

        $workout = Workout::where('id', $id)->whereIn('parent_id', $parentIds)->firstOrFail();
        $workout->delete();

        return back()->with('success', 'Workout deleted');
    }

    /**
     * List activities
     */
    public function activities()
    {
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();

        $activities = WorkoutActivity::whereIn('parent_id', $parentIds)
            ->orderBy('title')
            ->get();

        return view('panel.workouts.activities', compact('activities'));
    }

    /**
     * Store activity (AJAX supported)
     */
    public function storeActivity(Request $request)
    {
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();

        $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $activity = WorkoutActivity::create([
            'title' => $request->title,
            'parent_id' => $pid,
        ]);

        $isAjax = $request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest';

        if ($isAjax) {
            return response()->json([
                'success' => true,
                'message' => 'Activity added successfully',
                'activity' => [
                    'id' => $activity->id,
                    'title' => $activity->title,
                ]
            ]);
        }

        return redirect()->route('panel.workouts.activities')->with('success', 'Activity added');
    }

    /**
     * Update activity (AJAX supported)
     */
    public function updateActivity(Request $request, int $id)
    {
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();

        $activity = WorkoutActivity::where('id', $id)->whereIn('parent_id', $parentIds)->firstOrFail();

        $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $activity->update(['title' => $request->title]);

        $isAjax = $request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest';

        if ($isAjax) {
            return response()->json([
                'success' => true,
                'message' => 'Activity updated successfully',
                'activity' => [
                    'id' => $activity->id,
                    'title' => $activity->title,
                ]
            ]);
        }

        return redirect()->route('panel.workouts.activities')->with('success', 'Activity updated');
    }

    /**
     * Delete activity (AJAX supported)
     */
    public function destroyActivity(int $id)
    {
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();

        $activity = WorkoutActivity::where('id', $id)->whereIn('parent_id', $parentIds)->firstOrFail();
        $activity->delete();

        $isAjax = request()->ajax() || request()->wantsJson() || request()->header('X-Requested-With') === 'XMLHttpRequest';

        if ($isAjax) {
            return response()->json(['success' => true, 'message' => 'Activity deleted']);
        }

        return redirect()->route('panel.workouts.activities')->with('success', 'Activity deleted');
    }
}
