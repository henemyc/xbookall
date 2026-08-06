<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionTierFeature extends Model
{
    protected $fillable = [
        'subscription_tier_id',
        'feature_key',
        'feature_label',
        'value_type',
        'value',
        'is_highlighted',
        'sort_order',
    ];

    protected $casts = [
        'is_highlighted' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function tier()
    {
        return $this->belongsTo(SubscriptionTier::class, 'subscription_tier_id');
    }

    public function castValue()
    {
        return match ($this->value_type) {
            'bool' => in_array(strtolower((string) $this->value), ['1', 'true', 'yes', 'enabled'], true),
            'number' => is_numeric($this->value) ? (int) $this->value : 0,
            default => $this->value,
        };
    }
}
