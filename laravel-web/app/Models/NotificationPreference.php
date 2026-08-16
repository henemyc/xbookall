<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    protected $fillable = [
        'user_id',
        'notices_enabled',
        'super_admin_enabled',
        'payments_enabled',
        'membership_enabled',
        'workouts_enabled',
    ];

    protected $casts = [
        'notices_enabled' => 'boolean',
        'super_admin_enabled' => 'boolean',
        'payments_enabled' => 'boolean',
        'membership_enabled' => 'boolean',
        'workouts_enabled' => 'boolean',
    ];
}
