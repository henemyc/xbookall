<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = [
        'title',
        'package_amount',
        'interval',
        'user_limit',
        'trainer_limit',
        'trainee_limit',
        'enabled_logged_history',
    ];

    protected $casts = [
        'package_amount' => 'decimal:2',
        'enabled_logged_history' => 'boolean',
    ];

    public function users()
    {
        return $this->hasMany(User::class, 'subscription');
    }
}
