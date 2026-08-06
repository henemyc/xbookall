<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GymClass extends Model
{
    protected $table = 'classes';

    protected $fillable = [
        'title',
        'fees',
        'address',
        'notes',
        'parent_id',
    ];

    protected $casts = [
        'fees' => 'decimal:2',
    ];

    public function schedules()
    {
        return $this->hasMany(ClassSchedule::class, 'classes_id');
    }

    public function assigns()
    {
        return $this->hasMany(ClassAssign::class, 'classes_id');
    }

    public function assignedMembers()
    {
        return $this->assigns()->where('assign_type', 'member');
    }

    public function scopeForGym($query, int $parentId)
    {
        return $query->where('parent_id', $parentId);
    }
}
