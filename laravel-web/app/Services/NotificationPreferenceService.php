<?php

namespace App\Services;

use App\Models\NotificationPreference;

class NotificationPreferenceService
{
    public static function enabledFor(int $userId, string $category): bool
    {
        $preference = NotificationPreference::firstOrCreate(['user_id' => $userId]);
        $column = match ($category) {
            'notice', 'notices' => 'notices_enabled',
            'super_admin' => 'super_admin_enabled',
            'payment', 'payments' => 'payments_enabled',
            'membership' => 'membership_enabled',
            'workout', 'workouts' => 'workouts_enabled',
            default => null,
        };
        if ($column === null) return true;
        $value = $preference->{$column};
        // Missing/NULL (legacy rows) means "enabled by default".
        return $value === null ? true : (bool) $value;
    }
}
