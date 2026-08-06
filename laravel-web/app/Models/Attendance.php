<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $fillable = [
        'user_id',
        'date',
        'checked_in_time',
        'checked_out_time',
        'status',
        'parent_id',
        'notes',
    ];

    protected $casts = [
        // Serialize as plain date string. Without this, Laravel can JSON encode
        // DATE columns as UTC ISO timestamps, which shows previous day in Flutter.
        'date' => 'date:Y-m-d',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeForGym($query, int $parentId)
    {
        return $query->where('parent_id', $parentId);
    }

    public function scopeForDate($query, string $date)
    {
        return $query->where('date', $date);
    }

    public function getIsAutoCheckoutAttribute(): bool
    {
        return str_contains($this->notes ?? '', 'Auto checkout');
    }
}
