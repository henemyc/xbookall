<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// D2: independent member-specific assigned diet.
class MemberDiet extends Model
{
    protected $fillable = [
        'parent_id', 'member_id', 'template_id', 'assigned_by_user_id',
        'assigned_by_type', 'title', 'goal', 'diet_type', 'daily_calories',
        'protein_target', 'water_target', 'general_instructions', 'start_date',
        'end_date', 'status', 'is_customized',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_customized' => 'boolean',
    ];

    public function meals()
    {
        return $this->hasMany(MemberDietMeal::class)->orderBy('sort_order');
    }

    public function member()
    {
        return $this->belongsTo(User::class, 'member_id');
    }

    public function template()
    {
        return $this->belongsTo(DietTemplate::class, 'template_id');
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }
}
