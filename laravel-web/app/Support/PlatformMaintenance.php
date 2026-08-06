<?php

namespace App\Support;

use App\Models\Setting;
use Carbon\Carbon;

class PlatformMaintenance
{
    public const TZ = 'Asia/Kolkata';

    public static function status(): array
    {
        $enabled = Setting::getValue('maintenance_enabled', 1, '0') === '1';
        $title = trim((string) Setting::getValue('maintenance_title', 1, 'GymXBook is under maintenance'));
        $message = trim((string) Setting::getValue('maintenance_message', 1, 'We are upgrading GymXBook to serve you better. Please wait until maintenance is complete.'));
        $start = self::parseSettingDate(Setting::getValue('maintenance_start_at', 1, ''));
        $end = self::parseSettingDate(Setting::getValue('maintenance_end_at', 1, ''));
        $now = now(self::TZ);

        $scheduled = $enabled && $start && $now->lt($start);
        $active = $enabled && (!$start || $now->gte($start)) && (!$end || $now->lt($end));
        $ended = $enabled && $end && $now->gte($end);

        $secondsUntilStart = $scheduled ? (int) ceil(max(0, $now->diffInSeconds($start, false))) : 0;
        $secondsRemaining = ($active && $end) ? (int) ceil(max(0, $now->diffInSeconds($end, false))) : null;

        return [
            'enabled' => $enabled,
            'active' => $active,
            'scheduled' => $scheduled,
            'ended' => $ended,
            'title' => $title !== '' ? $title : 'GymXBook is under maintenance',
            'message' => $message !== '' ? $message : 'We are upgrading GymXBook to serve you better. Please wait until maintenance is complete.',
            'timezone' => self::TZ,
            'now' => $now->toIso8601String(),
            'start_at' => $start ? $start->toIso8601String() : null,
            'end_at' => $end ? $end->toIso8601String() : null,
            'seconds_until_start' => $secondsUntilStart,
            'seconds_remaining' => $secondsRemaining,
            'retry_after' => $secondsRemaining ?? ($secondsUntilStart ?: 60),
        ];
    }

    public static function isActive(): bool
    {
        return self::status()['active'] === true;
    }

    public static function parseSettingDate($value): ?Carbon
    {
        $value = trim((string) $value);
        if ($value === '') return null;

        try {
            return Carbon::parse($value, self::TZ);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function toInputValue($value): string
    {
        $date = self::parseSettingDate($value);
        return $date ? $date->format('Y-m-d\TH:i') : '';
    }

    public static function normalizeInputDate($value): string
    {
        $value = trim((string) $value);
        if ($value === '') return '';

        try {
            return Carbon::parse($value, self::TZ)->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return '';
        }
    }
}
