<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionTierPrice extends Model
{
    protected $fillable = [
        'subscription_tier_id',
        'billing_cycle',
        'duration_months',
        'price',
        'strike_price',
        'discount_text',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'duration_months' => 'integer',
        'price' => 'decimal:2',
        'strike_price' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function tier()
    {
        return $this->belongsTo(SubscriptionTier::class, 'subscription_tier_id');
    }
}
