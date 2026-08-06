<?php

namespace App\Services;

use App\Models\SubscriptionTier;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SubscriptionFeatureService
{
    /**
     * If a gym owner has a new subscription_tier_id, use it.
     * If not, but the old subscriptions.title is Bronze/Silver/Gold, map it to
     * the new tier so plan restrictions still work for gyms created before the
     * tier migration.
     */
    public static function tier(int $gymOwnerId): ?SubscriptionTier
    {
        if (!self::schemaReady()) {
            return null;
        }

        $user = User::find($gymOwnerId);
        if (!$user) {
            return null;
        }

        return self::resolveTierForUser($user);
    }

    /**
     * Existing gyms without any Bronze/Silver/Gold mapping remain unlocked until
     * they are assigned a tier. Gyms mapped to legacy Bronze/Silver/Gold are NOT
     * treated as unlocked; their mapped tier features are enforced.
     */
    public static function isLegacyUnlocked(int $gymOwnerId): bool
    {
        if (!self::schemaReady()) {
            return true;
        }

        $user = User::find($gymOwnerId);
        if (!$user) {
            return true;
        }

        return self::resolveTierForUser($user) === null;
    }

    public static function enabled(int $gymOwnerId, string $featureKey, bool $defaultForLegacy = true): bool
    {
        $tier = self::tier($gymOwnerId);
        if (!$tier) {
            return $defaultForLegacy;
        }

        if (!$tier->is_active || $tier->is_coming_soon) {
            return false;
        }

        $feature = $tier->features->firstWhere('feature_key', $featureKey);
        if (!$feature) {
            return self::missingFeatureDefault($featureKey, $defaultForLegacy);
        }

        if ($feature->value_type === 'bool') {
            return (bool) $feature->castValue();
        }

        if ($feature->value_type === 'number') {
            return ((int) $feature->castValue()) !== 0;
        }

        $value = strtolower((string) $feature->value);
        return !in_array($value, ['0', 'false', 'no', 'disabled', 'coming_soon'], true);
    }

    public static function limit(int $gymOwnerId, string $featureKey, int $defaultForLegacy = 0): int
    {
        $tier = self::tier($gymOwnerId);
        if (!$tier) {
            return $defaultForLegacy;
        }

        $feature = $tier->features->firstWhere('feature_key', $featureKey);
        if (!$feature) {
            return self::missingLimitDefault($featureKey, $defaultForLegacy);
        }

        if ($feature->value_type === 'number') {
            return max(0, (int) $feature->castValue());
        }

        return $defaultForLegacy;
    }

    public static function tierName(int $gymOwnerId): string
    {
        $tier = self::tier($gymOwnerId);
        return $tier ? $tier->name : 'Legacy Plan';
    }

    public static function tierCode(int $gymOwnerId): string
    {
        $tier = self::tier($gymOwnerId);
        return $tier ? strtolower((string) $tier->code) : 'legacy';
    }

    public static function tierColor(?string $code): string
    {
        return match (strtolower((string) $code)) {
            'bronze' => '#b45309',
            'silver' => '#2563eb',
            'gold' => '#d97706',
            default => '#64748b',
        };
    }

    public static function featureLockedMessage(string $label, string $requiredPlan = 'Silver'): string
    {
        return $label . ' is not available on your current plan. Upgrade to ' . $requiredPlan . ' or higher to unlock it.';
    }

    public static function limitReachedMessage(string $label, int $limit, string $requiredPlan = 'Silver'): string
    {
        return $label . ' limit reached (' . $limit . '). Upgrade to ' . $requiredPlan . ' or higher to add more.';
    }

    private static function schemaReady(): bool
    {
        return Schema::hasTable('users')
            && Schema::hasTable('subscription_tiers')
            && Schema::hasTable('subscription_tier_features')
            && Schema::hasColumn('users', 'subscription_tier_id');
    }

    private static function resolveTierForUser(User $user): ?SubscriptionTier
    {
        if (!empty($user->subscription_tier_id)) {
            $tier = SubscriptionTier::with('features')->find($user->subscription_tier_id);
            if ($tier) {
                return $tier;
            }
        }

        $legacyCode = self::legacyTierCode($user);
        if (!$legacyCode) {
            return null;
        }

        return SubscriptionTier::with('features')->where('code', $legacyCode)->first();
    }

    private static function legacyTierCode(User $user): ?string
    {
        if (empty($user->subscription) || !Schema::hasTable('subscriptions')) {
            return null;
        }

        $title = DB::table('subscriptions')
            ->where('id', $user->subscription)
            ->value('title');

        return self::normaliseTierCode((string) $title);
    }

    private static function normaliseTierCode(string $value): ?string
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return null;
        }

        $plain = preg_replace('/[^a-z0-9]+/', ' ', $value);
        foreach (['bronze', 'silver', 'gold'] as $code) {
            if ($plain === $code || str_contains($plain, $code)) {
                return $code;
            }
        }

        return null;
    }

    private static function missingFeatureDefault(string $featureKey, bool $defaultForLegacy): bool
    {
        $lockedIfMissing = [
            'trainers_enabled',
            'staff_enabled',
            'lockers_enabled',
            'products_enabled',
            'advanced_reports_enabled',
            'bulk_import_enabled',
            'payment_gateway_enabled',
            'biometric_attendance_enabled',
            'multi_branch_enabled',
        ];

        return in_array($featureKey, $lockedIfMissing, true) ? false : $defaultForLegacy;
    }

    private static function missingLimitDefault(string $featureKey, int $defaultForLegacy): int
    {
        $zeroIfMissing = [
            'members_limit',
            'trainers_limit',
            'staff_limit',
            'bulk_import_limit',
        ];

        return in_array($featureKey, $zeroIfMissing, true) ? 0 : $defaultForLegacy;
    }
}
