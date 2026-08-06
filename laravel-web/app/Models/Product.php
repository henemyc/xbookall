<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'parent_id',
        'title',
        'description',
        'price',
        'discount',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'discount' => 'decimal:2',
    ];

    public function scopeForGym($query, int $parentId)
    {
        return $query->where('parent_id', $parentId);
    }
}
