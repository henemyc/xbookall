<?php

namespace App\Services\Payments;

use App\Models\PaymentGatewaySetting;

class PaymentGatewayManager
{
    public static function all()
    {
        return PaymentGatewaySetting::orderBy('id')->get();
    }

    public static function default(): ?PaymentGatewaySetting
    {
        return PaymentGatewaySetting::where('enabled', true)
            ->where('is_default', true)
            ->first()
            ?: PaymentGatewaySetting::where('enabled', true)->first();
    }

    public static function find(string $gatewayKey): ?PaymentGatewaySetting
    {
        return PaymentGatewaySetting::where('gateway_key', $gatewayKey)->first();
    }

    public static function ensureDefaults(): void
    {
        $defaults = [
            'cashfree' => 'Cashfree',
            'razorpay' => 'Razorpay',
            'payu' => 'PayU',
            'phonepe' => 'PhonePe',
            'instamojo' => 'Instamojo',
        ];

        foreach ($defaults as $key => $name) {
            PaymentGatewaySetting::firstOrCreate(
                ['gateway_key' => $key],
                [
                    'name' => $name,
                    'enabled' => $key === 'cashfree',
                    'is_default' => $key === 'cashfree',
                    'mode' => 'sandbox',
                    'settings' => [],
                ]
            );
        }

        if (!PaymentGatewaySetting::where('is_default', true)->exists()) {
            $gateway = PaymentGatewaySetting::where('enabled', true)->first()
                ?: PaymentGatewaySetting::where('gateway_key', 'cashfree')->first();
            if ($gateway) {
                $gateway->update(['enabled' => true, 'is_default' => true]);
            }
        }
    }
}
