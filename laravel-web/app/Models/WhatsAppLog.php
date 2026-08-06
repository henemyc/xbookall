<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppLog extends Model
{
    // FIX: Specify correct table name
    protected $table = 'whatsapp_logs';

    protected $fillable = [
        'parent_id',
        'to_number',
        'template_name',
        'message',
        'message_id',
        'status',
        'response',
    ];

    public function scopeForGym($query, int $parentId)
    {
        return $query->where('parent_id', $parentId);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('created_at', now()->month)
                     ->whereYear('created_at', now()->year);
    }
}
