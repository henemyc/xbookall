<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtpVerification extends Model
{
    // The existing/live otp_verifications table has created_at only and no
    // updated_at column, same as old api.php. Disable Eloquent timestamps so
    // Laravel does not try to insert updated_at.
    public $timestamps = false;

    protected $fillable = [
        'identifier',
        'otp_hash',
        'otp_plain',
        'channel',
        'expires_at',
        'verified',
        'attempts',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified' => 'boolean',
    ];

    public function scopeValid($query, string $identifier)
    {
        return $query->where('identifier', $identifier)
                     ->where('expires_at', '>', now())
                     ->where('verified', false);
    }

    public function scopeVerified($query, string $identifier)
    {
        return $query->where('identifier', $identifier)
                     ->where('verified', true)
                     ->where('created_at', '>', now()->subMinutes(10));
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at->isPast();
    }
}
