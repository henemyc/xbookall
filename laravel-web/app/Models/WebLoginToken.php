<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebLoginToken extends Model
{
    protected $fillable = [
        'token',
        'browser_session_id',
        'status',
        'user_id',
        'approved_at',
        'used_at',
        'expires_at',
        'browser_ip',
        'browser_user_agent',
        'approved_ip',
        'approved_user_agent',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isExpired(): bool
    {
        return !$this->expires_at || $this->expires_at->isPast();
    }
}
