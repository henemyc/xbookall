<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FreezeMembershipLog extends Model
{
    protected $fillable = [
        'trainee_id',
        'plan',
        'membership_start_date',
        'membership_expiry_date',
        'freeze_start_date',
        'freeze_end_date',
        'freeze_days',
        'remarks',
        'created_by',
    ];

    protected $casts = [
        'membership_start_date' => 'date',
        'membership_expiry_date' => 'date',
        'freeze_start_date' => 'date',
        'freeze_end_date' => 'date',
    ];

    public function trainee()
    {
        return $this->belongsTo(User::class, 'trainee_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
