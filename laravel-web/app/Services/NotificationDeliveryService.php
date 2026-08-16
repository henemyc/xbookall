<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class NotificationDeliveryService
{
    /**
     * Deliver a private in-app/FCM notification to exactly one user.
     * Used for member-specific events such as an assigned diet plan.
     */
    public function notifyUser(int $userId, int $gymOwnerId, string $title, string $message, string $type = 'info', array $data = []): void
    {
        try {
            $notificationId = DB::table('app_notifications')->insertGetId([
                'parent_id' => $gymOwnerId,
                'user_id' => $userId,
                'title' => $title,
                'message' => $message,
                'type' => $type,
            ]);

            app(FcmPushService::class)->sendToUser($userId, $title, $message, array_merge($data, [
                'notification_id' => $notificationId,
                'type' => $type,
                'route' => 'my_diet',
            ]));
        } catch (\Throwable $e) {
            \Log::warning('User notification delivery failed', ['user_id' => $userId, 'error' => $e->getMessage()]);
        }
    }

    public function notifyGymOwner(int $gymOwnerId, string $title, string $message, string $type = 'info', array $data = []): void
    {
        try {
            // User-specific Super Admin notification. parent_id must be 0 so
            // members scoped to this gym do not see Gym Owner-only messages.
            $notificationId = DB::table('app_notifications')->insertGetId([
                'parent_id' => 0,
                'user_id' => $gymOwnerId,
                'title' => $title,
                'message' => $message,
                'type' => $type,
            ]);
            app(FcmPushService::class)->sendToUser($gymOwnerId, $title, $message, array_merge($data, [
                'notification_id' => $notificationId,
                'type' => $type,
                'route' => 'notifications',
            ]));
        } catch (\Throwable $e) {
            \Log::warning('Notification delivery failed', ['gym_id' => $gymOwnerId, 'error' => $e->getMessage()]);
        }
    }
}
