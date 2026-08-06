<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Health extends Model
{
    protected $fillable = [
        'user_id',
        'measurement_date',
        'result',
        'notes',
        'parent_id',
    ];

    protected $casts = [
        'measurement_date' => 'date',
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
