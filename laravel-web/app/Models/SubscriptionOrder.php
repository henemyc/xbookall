<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionOrder extends Model
{
    protected $fillable = [
        'order_id',
        'parent_id',
        'plan_id',
        'subscription_tier_id',
        'subscription_tier_price_id',
        'order_type',
        'billing_cycle',
        'duration_months',
        'amount',
        'gateway',
        'gateway_order_id',
        'gateway_payment_id',
        'gateway_status',
        'status',
        'link_id',
        'link_url',
        'cf_order_id',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'duration_months' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function parent()
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    public function plan()
    {
        return $this->belongsTo(Subscription::class, 'plan_id');
    }

    public function tier()
    {
        return $this->belongsTo(SubscriptionTier::class, 'subscription_tier_id');
    }

    public function tierPrice()
    {
        return $this->belongsTo(SubscriptionTierPrice::class, 'subscription_tier_price_id');
    }

    public function scopeForGym($query, int $parentId)
    {
        return $query->where('parent_id', $parentId);
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'PAID');
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', ['CREATED', 'ACTIVE']);
    }
}
