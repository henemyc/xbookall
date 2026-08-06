<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = [
        'parent_id',
        'user_id',
        'user_type',
        'module',
        'action',
        'record_type',
        'record_id',
        'description',
        'before_json',
        'after_json',
        'ip',
        'user_agent',
    ];

    protected $casts = [
        'before_json' => 'array',
        'after_json' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeForGym($query, int $parentId)
    {
        return $query->where('parent_id', $parentId);
    }
}
