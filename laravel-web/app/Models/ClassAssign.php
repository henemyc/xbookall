<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassAssign extends Model
{
    protected $fillable = [
        'classes_id',
        'assign_id',
        'assign_type',
    ];

    public function gymClass()
    {
        return $this->belongsTo(GymClass::class, 'classes_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'assign_id');
    }
}
