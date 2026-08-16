<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;

// UX Batch 2: non-owner users receive only own/private or public broadcast notifications.
class NotificationController extends BaseController
{
    /**
     * List notifications
     */
    public function index(Request $request): JsonResponse
    {
        $parentIds = $this->getGymParentIds();
        $currentUser = $this->currentUser();
        $userId = (int) ($currentUser?->id ?? 0);

        $scope = function ($q) use ($parentIds, $userId, $currentUser) {
            $q->where(function ($inner) use ($parentIds, $userId, $currentUser) {
                if ($currentUser && !in_array($currentUser->type, ['admin', 'owner'], true)) {
                    $inner->where('user_id', $userId)
                        ->orWhere(function ($broadcast) use ($parentIds) {
                            $broadcast->whereNull('user_id')->whereIn('parent_id', $parentIds);
                        });
                    return;
                }
                $inner->whereIn('parent_id', $parentIds);
                if ($userId > 0) $inner->orWhere('user_id', $userId);
            });
        };

        // Old production installs can lack timestamps on app_notifications.
        $orderColumn = Schema::hasColumn('app_notifications', 'created_at') ? 'created_at' : 'id';
        $notifications = AppNotification::where($scope)
            ->orderBy($orderColumn, 'desc')
            ->limit(50)
            ->get();

        $unreadQuery = AppNotification::where($scope);
        $unreadCount = Schema::hasColumn('app_notifications', 'is_read')
            ? $unreadQuery->where('is_read', false)->count()
            : $unreadQuery->count();

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
        $currentUser = $this->currentUser();
        $userId = (int) ($currentUser?->id ?? 0);

        $notification = AppNotification::where('id', $id)
            ->where(function ($q) use ($parentIds, $userId, $currentUser) {
                if ($currentUser && !in_array($currentUser->type, ['admin', 'owner'], true)) {
                    $q->where('user_id', $userId)
                        ->orWhere(function ($broadcast) use ($parentIds) {
                            $broadcast->whereNull('user_id')->whereIn('parent_id', $parentIds);
                        });
                    return;
                }
                $q->whereIn('parent_id', $parentIds);
                if ($userId > 0) $q->orWhere('user_id', $userId);
            })
            ->first();
        if (!$notification) {
            return $this->error('Notification not found', 404);
        }

        if (Schema::hasColumn('app_notifications', 'is_read')) {
            $notification->update(['is_read' => true]);
        }

        return $this->success([], 'Marked as read');
    }

    /**
     * Delete notification
     */
    public function destroy(int $id): JsonResponse
    {
        $parentIds = $this->getGymParentIds();
        $currentUser = $this->currentUser();
        $userId = (int) ($currentUser?->id ?? 0);

        $notification = AppNotification::where('id', $id)
            ->where(function ($q) use ($parentIds, $userId, $currentUser) {
                if ($currentUser && !in_array($currentUser->type, ['admin', 'owner'], true)) {
                    $q->where('user_id', $userId)
                        ->orWhere(function ($broadcast) use ($parentIds) {
                            $broadcast->whereNull('user_id')->whereIn('parent_id', $parentIds);
                        });
                    return;
                }
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

        $currentUser = $this->currentUser();
        AppNotification::where(function ($q) use ($parentIds, $userId, $currentUser) {
            if ($currentUser && !in_array($currentUser->type, ['admin', 'owner'], true)) {
                $q->where('user_id', $userId)
                    ->orWhere(function ($broadcast) use ($parentIds) {
                        $broadcast->whereNull('user_id')->whereIn('parent_id', $parentIds);
                    });
                return;
            }
            $q->whereIn('parent_id', $parentIds);
            if ($userId > 0) $q->orWhere('user_id', $userId);
        })->delete();

        return $this->success([], 'All notifications deleted');
    }
}
