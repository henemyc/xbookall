<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// D2: customized meal belonging only to one member diet.
class MemberDietMeal extends Model
{
    protected $fillable = [
        'member_diet_id', 'sort_order', 'meal_time', 'meal_name', 'food_items',
        'quantity', 'calories', 'protein', 'carbs', 'fats', 'notes',
    ];

    public function memberDiet()
    {
        return $this->belongsTo(MemberDiet::class, 'member_diet_id');
    }
}
