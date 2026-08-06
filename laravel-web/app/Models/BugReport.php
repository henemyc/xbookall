<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BugReport extends Model
{
    protected $fillable = [
        'user_id',
        'gym_name',
        'email',
        'title',
        'description',
        'screenshot_path',
        'has_screenshot',
        'status',
        'admin_notes',
    ];

    protected $casts = [
        'has_screenshot' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }
}