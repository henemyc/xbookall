<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssignLocker extends Model
{
    protected $fillable = [
        'user_id',
        'locker_id',
        'assign_date',
        'end_date',
    ];

    protected $casts = [
        'assign_date' => 'date',
        'end_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function locker()
    {
        return $this->belongsTo(Locker::class, 'locker_id');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('end_date');
    }
}
