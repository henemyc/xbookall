<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassSchedule extends Model
{
    protected $fillable = [
        'classes_id',
        'days',
        'start_time',
        'end_time',
        'parent_id',
    ];

    public function gymClass()
    {
        return $this->belongsTo(GymClass::class, 'classes_id');
    }
}
