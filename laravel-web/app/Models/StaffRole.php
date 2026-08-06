<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffRole extends Model
{
    protected $fillable = [
        'parent_id',
        'name',
        'description',
        'is_system',
        'status',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'status' => 'integer',
    ];

    public function permissions()
    {
        return $this->hasMany(StaffRolePermission::class, 'staff_role_id');
    }

    public function users()
    {
        return $this->hasMany(User::class, 'staff_role_id');
    }

    public function scopeForGym($query, int $parentId)
    {
        return $query->where('parent_id', $parentId);
    }

    public function permissionKeys(): array
    {
        return $this->permissions()
            ->pluck('permission_key')
            ->filter()
            ->values()
            ->all();
    }

    public static function permissionCatalog(): array
    {
        return [
            'Dashboard' => [
                'dashboard.view' => 'View dashboard',
            ],
            'Members' => [
                'members.view' => 'View members',
                'members.create' => 'Add members',
                'members.edit' => 'Edit members',
                'members.delete' => 'Delete members',
                'members.renew' => 'Renew memberships',
                'members.freeze' => 'Freeze/unfreeze memberships',
            ],
            'Attendance' => [
                'attendance.view' => 'View attendance',
                'attendance.mark' => 'Mark check-in/check-out',
                'attendance.edit' => 'Edit attendance',
                'attendance.delete' => 'Delete attendance',
                'attendance.qr' => 'View/share attendance QR',
            ],
            'Trainers' => [
                'trainers.view' => 'View trainers',
                'trainers.create' => 'Add trainers',
                'trainers.edit' => 'Edit trainers',
                'trainers.delete' => 'Delete trainers',
            ],
            'Plans' => [
                'plans.view' => 'View membership plans',
                'plans.create' => 'Add membership plans',
                'plans.edit' => 'Edit membership plans',
                'plans.delete' => 'Delete membership plans',
            ],
            'Classes' => [
                'classes.view' => 'View classes',
                'classes.create' => 'Add classes',
                'classes.edit' => 'Edit classes',
                'classes.delete' => 'Delete classes',
            ],
            'Workouts' => [
                'workouts.view' => 'View workout plans/activities',
                'workouts.create' => 'Create workout plans/activities',
                'workouts.edit' => 'Edit workout plans/activities',
                'workouts.delete' => 'Delete workout plans/activities',
            ],
            'Invoices' => [
                'invoices.view' => 'View invoices',
                'invoices.create' => 'Create invoices',
                'invoices.edit' => 'Edit invoices',
                'invoices.delete' => 'Delete invoices',
                'invoices.payment' => 'Add invoice payments',
            ],
            'Transactions' => [
                'transactions.view' => 'View transactions/history',
            ],
            'Expenses' => [
                'expenses.view' => 'View expenses',
                'expenses.create' => 'Add expenses',
                'expenses.edit' => 'Edit expenses',
                'expenses.delete' => 'Delete expenses',
            ],
            'Products' => [
                'products.view' => 'View products',
                'products.create' => 'Add products',
                'products.edit' => 'Edit products',
                'products.delete' => 'Delete products',
            ],
            'Lockers' => [
                'lockers.view' => 'View lockers',
                'lockers.create' => 'Add lockers',
                'lockers.assign' => 'Assign/unassign lockers',
                'lockers.delete' => 'Delete lockers',
            ],
            'Events' => [
                'events.view' => 'View events',
                'events.create' => 'Add events',
                'events.edit' => 'Edit events',
                'events.delete' => 'Delete events',
            ],
            'Notices' => [
                'notices.view' => 'View notices',
                'notices.create' => 'Create notices',
                'notices.edit' => 'Edit notices',
                'notices.delete' => 'Delete notices',
            ],
            'Reports' => [
                'reports.view' => 'View reports',
            ],
            'Settings' => [
                'settings.view' => 'View settings',
                'settings.edit' => 'Edit settings',
            ],
            'Subscription' => [
                'subscription.view' => 'View subscription',
                'subscription.pay' => 'Pay/renew subscription',
            ],
            'Web Login' => [
                'web_login.view' => 'Use QR web login',
            ],
        ];
    }

    public static function allPermissionKeys(): array
    {
        $keys = [];
        foreach (self::permissionCatalog() as $permissions) {
            $keys = array_merge($keys, array_keys($permissions));
        }
        return array_values(array_unique($keys));
    }

    public static function isValidPermission(string $permission): bool
    {
        return in_array($permission, self::allPermissionKeys(), true);
    }
}
