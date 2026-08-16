<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// D2: master reusable diet template.
class DietTemplate extends Model
{
    protected $fillable = [
        'parent_id', 'created_by_user_id', 'created_by_type', 'title', 'goal',
        'diet_type', 'daily_calories', 'protein_target', 'water_target',
        'general_instructions', 'is_shared', 'is_active',
    ];

    protected $casts = [
        'is_shared' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function meals()
    {
        return $this->hasMany(DietTemplateMeal::class)->orderBy('sort_order');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
