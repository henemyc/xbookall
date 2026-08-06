<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppNotification extends Model
{
    protected $table = 'app_notifications';

    protected $fillable = [
        'parent_id',
        'user_id',
        'title',
        'message',
        'type',
        'is_read',
    ];

    // ULTIMATE FIX: NEVER use timestamps on this model or table.
    // Production DB does not have created_at/updated_at (old migration or manual table).
    // ALL inserts MUST use raw DB insert with ONLY these 5 columns.
    public $timestamps = false;

    // DEPRECATED: never call this. Use raw inserts in controllers.
    public static function safeCreate(array $data)
    {
        try {
            \DB::table('app_notifications')->insert([
                'parent_id' => $data['parent_id'] ?? 0,
                'user_id'   => $data['user_id'] ?? null,
                'title'     => $data['title'] ?? 'Notification',
                'message'   => $data['message'] ?? '',
                'type'      => $data['type'] ?? 'info',
            ]);
        } catch (\Throwable $e) {
            \Log::warning('AppNotification safeCreate skipped: ' . $e->getMessage());
        }
    }

    protected $casts = [
        'is_read' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function parent()
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeForGym($query, int $parentId)
    {
        return $query->where('parent_id', $parentId);
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }
}
