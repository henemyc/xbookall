<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class NotificationController extends BaseController
{
    /**
     * List notifications
     */
    public function index(Request $request): JsonResponse
    {
        $parentIds = $this->getGymParentIds();
        $userId = (int) ($this->currentUser()?->id ?? 0);

        $scope = function ($q) use ($parentIds, $userId) {
            $q->whereIn('parent_id', $parentIds);
            if ($userId > 0) {
                $q->orWhere('user_id', $userId);
            }
        };

        $notifications = AppNotification::where($scope)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        $unreadCount = AppNotification::where($scope)
            ->where('is_read', false)
            ->count();

        return $this->success([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * Mark as read
     */
    public function markRead(int $id): JsonResponse
    {
        $parentIds = $this->getGymParentIds();
        $userId = (int) ($this->currentUser()?->id ?? 0);

        $notification = AppNotification::where('id', $id)
            ->where(function ($q) use ($parentIds, $userId) {
                $q->whereIn('parent_id', $parentIds);
                if ($userId > 0) $q->orWhere('user_id', $userId);
            })
            ->first();
        if (!$notification) {
            return $this->error('Notification not found', 404);
        }

        $notification->update(['is_read' => true]);

        return $this->success([], 'Marked as read');
    }

    /**
     * Delete notification
     */
    public function destroy(int $id): JsonResponse
    {
        $parentIds = $this->getGymParentIds();
        $userId = (int) ($this->currentUser()?->id ?? 0);

        $notification = AppNotification::where('id', $id)
            ->where(function ($q) use ($parentIds, $userId) {
                $q->whereIn('parent_id', $parentIds);
                if ($userId > 0) $q->orWhere('user_id', $userId);
            })
            ->first();
        if (!$notification) {
            return $this->error('Notification not found', 404);
        }

        $notification->delete();

        return $this->success([], 'Notification deleted');
    }

    /**
     * Delete all notifications
     */
    public function destroyAll(Request $request): JsonResponse
    {
        $parentIds = $this->getGymParentIds();
        $userId = (int) ($this->currentUser()?->id ?? 0);

        AppNotification::where(function ($q) use ($parentIds, $userId) {
            $q->whereIn('parent_id', $parentIds);
            if ($userId > 0) $q->orWhere('user_id', $userId);
        })->delete();

        return $this->success([], 'All notifications deleted');
    }
}
