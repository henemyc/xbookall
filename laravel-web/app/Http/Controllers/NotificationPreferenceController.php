<?php

namespace App\Http\Controllers;

use App\Models\NotificationPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends BaseController
{
    public function show(Request $request): JsonResponse
    {
        $preference = NotificationPreference::firstOrCreate(['user_id' => $request->user()->id]);
        // Enforce "all ON" defaults for any legacy NULL columns and persist.
        foreach (['notices_enabled', 'super_admin_enabled', 'payments_enabled', 'membership_enabled', 'workouts_enabled'] as $column) {
            if ($preference->{$column} === null) {
                $preference->{$column} = true;
            }
        }
        if ($preference->isDirty()) $preference->save();
        return $this->success(['preferences' => $preference]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'notices_enabled' => 'nullable|boolean',
            'super_admin_enabled' => 'nullable|boolean',
            'payments_enabled' => 'nullable|boolean',
            'membership_enabled' => 'nullable|boolean',
            'workouts_enabled' => 'nullable|boolean',
        ]);
        $preference = NotificationPreference::updateOrCreate(['user_id' => $request->user()->id], $data);
        return $this->success(['preferences' => $preference], 'Notification preferences updated');
    }
}
