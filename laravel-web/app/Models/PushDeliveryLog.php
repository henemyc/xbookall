<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PushDeliveryLog extends Model
{
    protected $fillable = [
        'user_id',
        'device_token_id',
        'category',
        'title',
        'status',
        'fcm_message_id',
        'error_message',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
