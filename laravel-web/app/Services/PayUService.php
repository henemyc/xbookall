<?php

namespace App\Services;

use App\Models\PaymentGatewaySetting;
use App\Models\Subscription;
use App\Models\SubscriptionOrder;
use App\Models\User;

class PayUService
{
    private string $merchantKey;
    private string $merchantSalt;
    private bool $sandbox;
    private string $baseUrl;

    public function __construct(?PaymentGatewaySetting $gateway = null)
    {
        $credentials = $gateway ? $gateway->getCredentialsArray() : [];

        $this->merchantKey = $credentials['merchant_key'] ?? env('PAYU_MERCHANT_KEY', '');
        $this->merchantSalt = $credentials['merchant_salt'] ?? env('PAYU_MERCHANT_SALT', '');
        $this->sandbox = $gateway ? $gateway->mode !== 'production' : filter_var(env('PAYU_SANDBOX', true), FILTER_VALIDATE_BOOLEAN);
        $this->baseUrl = $this->sandbox ? 'https://test.payu.in/_payment' : 'https://secure.payu.in/_payment';
    }

    public function isConfigured(): bool
    {
        return !empty($this->merchantKey) && !empty($this->merchantSalt);
    }

    public function paymentUrl(): string
    {
        return $this->baseUrl;
    }

    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'PayU merchant key/salt are missing'];
        }

        return [
            'success' => true,
            'message' => 'PayU credentials saved. Live verification happens during checkout because classic PayU uses browser form-post checkout.',
        ];
    }

    public function buildPaymentFields(SubscriptionOrder $order, User $user, Subscription $plan, string $successUrl, string $failureUrl): array
    {
        $txnid = $order->order_id;
        $amount = number_format((float) $order->amount, 2, '.', '');
        $productInfo = 'GymXBook Subscription - ' . ($plan->title ?? 'Plan');
        $firstName = $user->name ?: 'Gym Owner';
        $email = filter_var($user->email, FILTER_VALIDATE_EMAIL) ? $user->email : 'support@gymxbook.com';
        $phone = $this->cleanPhone($user->phone_number ?: '9999999999');

        $udf1 = (string) $order->id;
        $udf2 = (string) $order->plan_id;
        $udf3 = (string) $order->parent_id;
        $udf4 = 'subscription';
        $udf5 = 'gymxbook';

        $hash = $this->requestHash($txnid, $amount, $productInfo, $firstName, $email, $udf1, $udf2, $udf3, $udf4, $udf5);

        return [
            'key' => $this->merchantKey,
            'txnid' => $txnid,
            'amount' => $amount,
            'productinfo' => $productInfo,
            'firstname' => $firstName,
            'email' => $email,
            'phone' => $phone,
            'surl' => $successUrl,
            'furl' => $failureUrl,
            'hash' => $hash,
            'udf1' => $udf1,
            'udf2' => $udf2,
            'udf3' => $udf3,
            'udf4' => $udf4,
            'udf5' => $udf5,
            'service_provider' => 'payu_paisa',
        ];
    }

    public function verifyResponse(array $data): bool
    {
        $postedHash = $data['hash'] ?? '';
        if (!$postedHash) return false;

        $status = $data['status'] ?? '';
        $txnid = $data['txnid'] ?? '';
        $amount = $data['amount'] ?? '';
        $productInfo = $data['productinfo'] ?? '';
        $firstName = $data['firstname'] ?? '';
        $email = $data['email'] ?? '';
        $udf1 = $data['udf1'] ?? '';
        $udf2 = $data['udf2'] ?? '';
        $udf3 = $data['udf3'] ?? '';
        $udf4 = $data['udf4'] ?? '';
        $udf5 = $data['udf5'] ?? '';

        $expected = hash('sha512', $this->merchantSalt . '|' . $status . '||||||' . $udf5 . '|' . $udf4 . '|' . $udf3 . '|' . $udf2 . '|' . $udf1 . '|' . $email . '|' . $firstName . '|' . $productInfo . '|' . $amount . '|' . $txnid . '|' . $this->merchantKey);

        return hash_equals(strtolower($expected), strtolower($postedHash));
    }

    private function requestHash(string $txnid, string $amount, string $productInfo, string $firstName, string $email, string $udf1, string $udf2, string $udf3, string $udf4, string $udf5): string
    {
        $hashString = $this->merchantKey . '|' . $txnid . '|' . $amount . '|' . $productInfo . '|' . $firstName . '|' . $email . '|' . $udf1 . '|' . $udf2 . '|' . $udf3 . '|' . $udf4 . '|' . $udf5 . '||||||' . $this->merchantSalt;
        return hash('sha512', $hashString);
    }

    private function cleanPhone(?string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', (string) $phone);
        if (strlen($digits) === 10) return $digits;
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) return substr($digits, 2);
        if (strlen($digits) >= 10) return substr($digits, -10);
        return '9999999999';
    }
}
