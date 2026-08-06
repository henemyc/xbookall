<?php

namespace App\Http\Controllers;

use App\Models\Health;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class HealthRecordController extends BaseController
{
    /**
     * List health records
     */
    public function index(Request $request): JsonResponse
    {
        $parentIds = $this->getGymParentIds();
        $user = $this->currentUser();
        $userId = $request->get('user_id', $user->id);

        // Trainees can only see their own
        if ($user->type === 'trainee' && $user->id != $userId) {
            return $this->error('Forbidden', 403);
        }

        $records = Health::where('user_id', $userId)
            ->whereIn('parent_id', $parentIds)
            ->orderBy('measurement_date', 'desc')
            ->get();

        return $this->success(['records' => $records]);
    }

    /**
     * Create health record
     */
    public function store(Request $request): JsonResponse
    {
        $parentIds = $this->getGymParentIds();

        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'measurement_date' => 'required|date',
            'result' => 'required|string',
        ]);

        $record = Health::create([
            'user_id' => $request->user_id,
            'measurement_date' => $request->measurement_date,
            'result' => $request->result,
            'notes' => $request->notes ?? '',
            'parent_id' => $pid,
        ]);

        return $this->success([
            'id' => $record->id,
            'record' => $record,
        ], 'Health record added', 201);
    }

    /**
     * Update health record
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $parentIds = $this->getGymParentIds();

        $record = Health::where('id', $id)->whereIn('parent_id', $parentIds)->first();
        if (!$record) {
            return $this->error('Record not found', 404);
        }

        $record->update([
            'measurement_date' => $request->measurement_date ?? $record->measurement_date,
            'result' => $request->result ?? $record->result,
            'notes' => $request->notes ?? $record->notes,
        ]);

        return $this->success([], 'Health record updated');
    }

    /**
     * Delete health record
     */
    public function destroy(int $id): JsonResponse
    {
        $parentIds = $this->getGymParentIds();
        $user = $this->currentUser();

        $record = Health::where('id', $id)->whereIn('parent_id', $parentIds)->first();
        if (!$record) {
            return $this->error('Record not found', 404);
        }

        // Trainees can only delete their own
        if ($user->type === 'trainee' && $record->user_id != $user->id) {
            return $this->error('Forbidden', 403);
        }

        $record->delete();

        return $this->success([], 'Health record deleted');
    }
}
