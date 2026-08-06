<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone_number',
        'type',
        'password',
        'profile',
        'lang',
        'subscription',
        'subscription_tier_id',
        'subscription_price_id',
        'subscription_status',
        'subscription_started_at',
        'subscription_ends_at',
        'subscription_expire_date',
        'parent_id',
        'staff_role_id',
        'is_active',
        'api_token',
        'email_verification_token',
        'email_verified_at',
        'last_login_at',
        'last_login_ip',
        'last_login_user_agent',
        'password_changed_at',
        'last_app_opened_at',
        'last_app_platform',
        'last_app_version',
        'last_app_ip',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'twofa_secret',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'subscription_expire_date' => 'date',
            'subscription_started_at' => 'datetime',
            'subscription_ends_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'last_app_opened_at' => 'datetime',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────

    public function traineeDetails()
    {
        return $this->hasOne(TraineeDetail::class, 'user_id');
    }

    public function trainerDetails()
    {
        return $this->hasOne(TrainerDetail::class, 'user_id');
    }

    public function parent()
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    public function staffRole()
    {
        return $this->belongsTo(StaffRole::class, 'staff_role_id');
    }

    public function children()
    {
        return $this->hasMany(User::class, 'parent_id');
    }

    public function subscriptionPlan()
    {
        return $this->belongsTo(Subscription::class, 'subscription');
    }

    public function subscriptionTier()
    {
        return $this->belongsTo(SubscriptionTier::class, 'subscription_tier_id');
    }

    public function subscriptionPrice()
    {
        return $this->belongsTo(SubscriptionTierPrice::class, 'subscription_price_id');
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'user_id');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'user_id');
    }

    public function workouts()
    {
        return $this->hasMany(Workout::class, 'assign_id');
    }

    public function healthRecords()
    {
        return $this->hasMany(Health::class, 'user_id');
    }

    // ── Scopes ────────────────────────────────────────────────────

    public function scopeTrainees($query)
    {
        return $query->where('type', 'trainee');
    }

    public function scopeTrainers($query)
    {
        return $query->where('type', 'trainer');
    }

    public function scopeStaff($query)
    {
        return $query->where('type', 'staff');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForGym($query, int $parentId)
    {
        return $query->where('parent_id', $parentId);
    }

    // ── Accessors ─────────────────────────────────────────────────

    public function getIsAdminAttribute(): bool
    {
        return in_array($this->type, ['admin', 'owner']);
    }

    public function getIsTraineeAttribute(): bool
    {
        return $this->type === 'trainee';
    }

    public function getIsTrainerAttribute(): bool
    {
        return $this->type === 'trainer';
    }

    public function getIsStaffAttribute(): bool
    {
        return $this->type === 'staff';
    }

    // ── Helper Methods ────────────────────────────────────────────

    public function gymId(): int
    {
        if (in_array($this->type, ['admin', 'owner'])) {
            return $this->id;
        }
        return $this->parent_id ?: 0;
    }

    public function staffPermissionKeys(): array
    {
        if (in_array($this->type, ['admin', 'owner'])) {
            return StaffRole::allPermissionKeys();
        }

        if ($this->type !== 'staff' || empty($this->staff_role_id)) {
            return [];
        }

        $role = $this->relationLoaded('staffRole') ? $this->staffRole : $this->staffRole()->with('permissions')->first();
        if (!$role || (int) $role->status !== 1) {
            return [];
        }

        if ($role->relationLoaded('permissions')) {
            return $role->permissions
                ->pluck('permission_key')
                ->filter()
                ->values()
                ->all();
        }

        return $role->permissionKeys();
    }

    public function hasStaffPermission(string $permission): bool
    {
        if (in_array($this->type, ['admin', 'owner'])) {
            return true;
        }

        return in_array($permission, $this->staffPermissionKeys(), true);
    }

    public function hasAnyStaffPermission(array $permissions): bool
    {
        if (in_array($this->type, ['admin', 'owner'])) {
            return true;
        }

        $owned = $this->staffPermissionKeys();
        foreach ($permissions as $permission) {
            if (in_array($permission, $owned, true)) {
                return true;
            }
        }

        return false;
    }

    public function subscriptionExpired(): bool
    {
        if (!$this->subscription_expire_date) return false;
        return $this->subscription_expire_date->isPast();
    }

    public function subscriptionDaysLeft(): ?int
    {
        if (!$this->subscription_expire_date) return null;
        return (int) now()->diffInDays($this->subscription_expire_date, false);
    }
}
