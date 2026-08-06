<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Type extends Model
{
    protected $fillable = [
        'title',
        'parent_id',
    ];

    public function expenses()
    {
        return $this->hasMany(Expense::class, 'expense_type');
    }

    public function scopeForGym($query, int $parentId)
    {
        return $query->where('parent_id', $parentId);
    }
}
