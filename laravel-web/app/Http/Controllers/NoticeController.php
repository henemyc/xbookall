<?php

namespace App\Http\Controllers;

use App\Models\NoticeBoard;
use App\Services\FcmPushService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class NoticeController extends BaseController
{
    // Phase 3: staff actions below require their exact notices permission.
    public function index(Request $request): JsonResponse
    {
        // Members (trainees) can always view notices scoped to their gym —
        // they are the audience the notices are posted for.
        if (!$this->canPerformGymAction('notices.view') && !$this->isTrainee()) {
            return $this->error('Permission denied', 403);
        }

        $parentIds = $this->getGymParentIds();

        $notices = NoticeBoard::whereIn('parent_id', $parentIds)
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->success(['notices' => $notices]);
    }

    public function store(Request $request): JsonResponse
    {
        if (!$this->canPerformGymAction('notices.create')) {
            return $this->error('Permission denied', 403);
        }

        $pid = $this->getParentId();

        $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $notice = NoticeBoard::create([
            'title' => $request->title,
            'description' => $request->description ?? '',
            'attachment' => $request->attachment ?? '',
            'parent_id' => $pid,
        ]);

        // FCM is best-effort only. A provider/config/database failure must
        // never prevent the notice itself from being created and visible.
        try {
            // Notice push is for gym members only. The owner created the
            // notice and staff/trainers manage it, so they do not receive it.
            app(FcmPushService::class)->sendToGymMembers($pid, 'New notice', $notice->title, [
                'type' => 'notice',
                'category' => 'notices',
                'notice_id' => $notice->id,
                'route' => 'notices',
            ]);
        } catch (\Throwable $e) {
            \Log::warning('Notice FCM delivery skipped', ['notice_id' => $notice->id, 'error' => $e->getMessage()]);
        }

        return $this->success([
            'id' => $notice->id,
            'notice' => $notice,
        ], 'Notice created', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if (!$this->canPerformGymAction('notices.edit')) {
            return $this->error('Permission denied', 403);
        }

        $parentIds = $this->getGymParentIds();

        $notice = NoticeBoard::where('id', $id)->whereIn('parent_id', $parentIds)->first();
        if (!$notice) {
            return $this->error('Notice not found', 404);
        }

        $notice->update([
            'title' => $request->title ?? $notice->title,
            'description' => $request->description ?? $notice->description,
            'attachment' => $request->attachment ?? $notice->attachment,
        ]);

        return $this->success([], 'Notice updated');
    }

    public function destroy(int $id): JsonResponse
    {
        if (!$this->canPerformGymAction('notices.delete')) {
            return $this->error('Permission denied', 403);
        }

        $parentIds = $this->getGymParentIds();

        $notice = NoticeBoard::where('id', $id)->whereIn('parent_id', $parentIds)->first();
        if (!$notice) {
            return $this->error('Notice not found', 404);
        }

        $notice->delete();

        return $this->success([], 'Notice deleted');
    }
}
