<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Membership extends Model
{
    protected $fillable = [
        'title',
        'package',
        'amount',
        'classes_id',
        'parent_id',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function traineeDetails()
    {
        return $this->hasMany(TraineeDetail::class, 'membership_plan');
    }

    public function memberCount()
    {
        return $this->traineeDetails()->count();
    }
}
