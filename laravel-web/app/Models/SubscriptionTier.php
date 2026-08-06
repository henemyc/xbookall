<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionTier extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'badge_text',
        'sort_order',
        'is_active',
        'is_coming_soon',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_coming_soon' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function features()
    {
        return $this->hasMany(SubscriptionTierFeature::class, 'subscription_tier_id')->orderBy('sort_order');
    }

    public function prices()
    {
        return $this->hasMany(SubscriptionTierPrice::class, 'subscription_tier_id')->orderBy('sort_order');
    }

    public function activePrices()
    {
        return $this->prices()->where('is_active', true);
    }

    public function cardFeatures()
    {
        return $this->hasMany(SubscriptionTierCardFeature::class, 'subscription_tier_id')->orderBy('sort_order');
    }

    public function visibleCardFeatures()
    {
        return $this->cardFeatures()->where('is_visible', true);
    }

    public function users()
    {
        return $this->hasMany(User::class, 'subscription_tier_id');
    }

    public function featureValue(string $key, $default = null)
    {
        $feature = $this->features->firstWhere('feature_key', $key);
        return $feature ? $feature->castValue() : $default;
    }

    public function hasFeature(string $key): bool
    {
        return (bool) $this->featureValue($key, false);
    }
}
