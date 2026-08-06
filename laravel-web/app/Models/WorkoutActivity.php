<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkoutActivity extends Model
{
    protected $fillable = [
        'title',
        'parent_id',
    ];

    public function scopeForGym($query, int $parentId)
    {
        return $query->where('parent_id', $parentId);
    }
}
