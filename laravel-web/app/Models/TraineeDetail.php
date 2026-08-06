<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TraineeDetail extends Model
{
    protected $fillable = [
        'user_id',
        'trainee_id',
        'address',
        'city',
        'state',
        'country',
        'zip_code',
        'dob',
        'age',
        'document',
        'gender',
        'fitness_goal',
        'membership_plan',
        'trainer_assign',
        'membership_start_date',
        'membership_expiry_date',
        'category',
        'parent_id',
        'status',
    ];

    protected $casts = [
        'dob' => 'date',
        'membership_start_date' => 'date',
        'membership_expiry_date' => 'date',
        'age' => 'integer',
    ];

    // ── Relationships ─────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function membership()
    {
        return $this->belongsTo(Membership::class, 'membership_plan');
    }

    public function trainer()
    {
        return $this->belongsTo(User::class, 'trainer_assign');
    }

    // ── Scopes ────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('membership_expiry_date', '>=', now()->toDateString())
                     ->where('status', 1);
    }

    public function scopeExpired($query)
    {
        return $query->where('membership_expiry_date', '<', now()->toDateString())
                     ->whereNotNull('membership_expiry_date');
    }

    public function scopeFrozen($query)
    {
        return $query->where('status', 3);
    }

    public function scopeForGym($query, int $parentId)
    {
        return $query->where('parent_id', $parentId);
    }

    // ── Accessors ─────────────────────────────────────────────────

    public function getIsActiveAttribute(): bool
    {
        return $this->status === 1 && 
               $this->membership_expiry_date && 
               $this->membership_expiry_date->isFuture();
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->membership_expiry_date && 
               $this->membership_expiry_date->isPast();
    }

    public function getIsFrozenAttribute(): bool
    {
        return $this->status === 3;
    }

    public function getDaysLeftAttribute(): int
    {
        if (!$this->membership_expiry_date) return 0;
        return (int) now()->diffInDays($this->membership_expiry_date, false);
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            1 => 'ACTIVE',
            2 => 'EXPIRED',
            3 => 'FROZEN',
            default => 'INACTIVE',
        };
    }
}
