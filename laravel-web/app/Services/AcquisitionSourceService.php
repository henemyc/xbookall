<?php

namespace App\Services;

use App\Models\Setting;

class AcquisitionSourceService
{
    public static function defaults(): array
    {
        return [
            ['key' => 'google_search', 'label' => 'Google Search', 'enabled' => true],
            ['key' => 'play_store', 'label' => 'Google Play Store', 'enabled' => true],
            ['key' => 'social_media', 'label' => 'Instagram / Facebook', 'enabled' => true],
            ['key' => 'youtube', 'label' => 'YouTube', 'enabled' => true],
            ['key' => 'chatgpt_ai', 'label' => 'ChatGPT / AI', 'enabled' => true],
            ['key' => 'referral', 'label' => 'Friend / Gym Owner Referral', 'enabled' => true],
            ['key' => 'sales_team', 'label' => 'GymXBook Team / Sales Person', 'enabled' => true],
            ['key' => 'other', 'label' => 'Other', 'enabled' => true],
        ];
    }

    public static function all(): array
    {
        $raw = Setting::getValue('acquisition_sources', 1, '');
        $decoded = json_decode((string) $raw, true);
        return is_array($decoded) && count($decoded) ? $decoded : self::defaults();
    }

    public static function enabled(): array
    {
        return array_values(array_filter(self::all(), fn ($source) => !empty($source['enabled']) && !empty($source['key']) && !empty($source['label'])));
    }

    public static function keys(): array
    {
        return array_column(self::enabled(), 'key');
    }

    public static function save(array $sources): void
    {
        Setting::setValue('acquisition_sources', json_encode(array_values($sources)), 1);
    }
}
