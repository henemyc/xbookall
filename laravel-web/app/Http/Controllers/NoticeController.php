<?php

namespace App\Http\Controllers;

use App\Models\NoticeBoard;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class NoticeController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $parentIds = $this->getGymParentIds();

        $notices = NoticeBoard::whereIn('parent_id', $parentIds)
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->success(['notices' => $notices]);
    }

    public function store(Request $request): JsonResponse
    {
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

        return $this->success([
            'id' => $notice->id,
            'notice' => $notice,
        ], 'Notice created', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
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
        $parentIds = $this->getGymParentIds();

        $notice = NoticeBoard::where('id', $id)->whereIn('parent_id', $parentIds)->first();
        if (!$notice) {
            return $this->error('Notice not found', 404);
        }

        $notice->delete();

        return $this->success([], 'Notice deleted');
    }
}
