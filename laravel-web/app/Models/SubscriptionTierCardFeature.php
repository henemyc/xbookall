<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionTierCardFeature extends Model
{
    protected $fillable = [
        'subscription_tier_id',
        'feature_label',
        'is_included',
        'tooltip_text',
        'is_visible',
        'sort_order',
    ];

    protected $casts = [
        'is_included' => 'boolean',
        'is_visible' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function tier()
    {
        return $this->belongsTo(SubscriptionTier::class, 'subscription_tier_id');
    }
}
