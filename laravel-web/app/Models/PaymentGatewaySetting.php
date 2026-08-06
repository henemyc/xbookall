<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class PaymentGatewaySetting extends Model
{
    protected $fillable = [
        'gateway_key',
        'name',
        'enabled',
        'is_default',
        'mode',
        'credentials',
        'settings',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'is_default' => 'boolean',
        'settings' => 'array',
    ];

    protected $hidden = [
        'credentials',
    ];

    public function getCredentialsArray(): array
    {
        if (empty($this->credentials)) return [];

        try {
            $json = Crypt::decryptString($this->credentials);
            $data = json_decode($json, true);
            return is_array($data) ? $data : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function setCredentialsArray(array $credentials): void
    {
        $this->credentials = Crypt::encryptString(json_encode($credentials));
    }

    public function hasCredentials(): bool
    {
        return !empty(array_filter($this->getCredentialsArray(), fn($v) => $v !== null && $v !== ''));
    }

    public function maskedCredentials(): array
    {
        $masked = [];
        foreach ($this->getCredentialsArray() as $key => $value) {
            if ($value === null || $value === '') {
                $masked[$key] = '';
                continue;
            }
            $str = (string) $value;
            $masked[$key] = strlen($str) <= 8
                ? str_repeat('•', max(4, strlen($str)))
                : substr($str, 0, 4) . '••••••' . substr($str, -4);
        }
        return $masked;
    }
}
