<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrainerDetail extends Model
{
    protected $fillable = [
        'user_id',
        'trainer_id',
        'address',
        'city',
        'state',
        'country',
        'zip_code',
        'dob',
        'document',
        'gender',
        'qualification',
        'specialization',
        'experience_years',
        'joining_date',
        'salary',
        'bio',
        'emergency_contact',
        'parent_id',
        'status',
    ];

    protected $casts = [
        'dob' => 'date',
        'joining_date' => 'date',
        'salary' => 'decimal:2',
        'experience_years' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
