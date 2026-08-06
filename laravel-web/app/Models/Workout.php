<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Workout extends Model
{
    protected $fillable = [
        'assign_to',
        'assign_id',
        'start_date',
        'end_date',
        'workout_history',
        'notes',
        'parent_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'workout_history' => 'json',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'assign_id');
    }

    public function scopeForGym($query, int $parentId)
    {
        return $query->where('parent_id', $parentId);
    }
}
