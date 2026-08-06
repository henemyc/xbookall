<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Locker extends Model
{
    protected $fillable = [
        'parent_id',
        'status',
        'available',
    ];

    protected $casts = [
        'available' => 'boolean',
    ];

    public function scopeForGym($query, int $parentId)
    {
        return $query->where('parent_id', $parentId);
    }

    public function scopeAvailable($query)
    {
        return $query->where('available', true);
    }

    public function currentAssignment()
    {
        return $this->hasOne(AssignLocker::class, 'locker_id')
                    ->whereNull('end_date');
    }

    public function assignedUser()
    {
        return $this->currentAssignment ? $this->currentAssignment->user : null;
    }
}
