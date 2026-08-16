<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// D2: meal belonging to a reusable diet template.
class DietTemplateMeal extends Model
{
    protected $fillable = [
        'diet_template_id', 'sort_order', 'meal_time', 'meal_name', 'food_items',
        'quantity', 'calories', 'protein', 'carbs', 'fats', 'notes',
    ];

    public function template()
    {
        return $this->belongsTo(DietTemplate::class, 'diet_template_id');
    }
}
