<?php

namespace App\Support;

use App\Models\Setting;

class PlatformOperationMode
{
    public static function value(): string
    {
        $mode = strtolower(trim((string) Setting::getValue('platform_operation_mode', 1, 'production')));
        return $mode === 'debug' ? 'debug' : 'production';
    }

    public static function isDebug(): bool
    {
        return self::value() === 'debug';
    }
}
