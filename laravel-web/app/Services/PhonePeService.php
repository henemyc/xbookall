<?php

namespace App\Services;

use App\Models\PaymentGatewaySetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PhonePeService
{
    private string $merchantId;
    private string $saltKey;
    private string $saltIndex;
    private bool $sandbox;
    private string $baseUrl;

    public function __construct(?PaymentGatewaySetting $gateway = null)
    {
        $credentials = $gateway ? $gateway->getCredentialsArray() : [];

        $this->merchantId = $credentials['merchant_id'] ?? env('PHONEPE_MERCHANT_ID', '');
        $this->saltKey = $credentials['salt_key'] ?? env('PHONEPE_SALT_KEY', '');
        $this->saltIndex = $credentials['salt_index'] ?? env('PHONEPE_SALT_INDEX', '1');
        $this->sandbox = $gateway ? $gateway->mode !== 'production' : filter_var(env('PHONEPE_SANDBOX', true), FILTER_VALIDATE_BOOLEAN);
        $this->baseUrl = $this->sandbox
            ? 'https://api-preprod.phonepe.com/apis/pg-sandbox'
            : 'https://api.phonepe.com/apis/hermes';
    }

    public function isConfigured(): bool
    {
        return !empty($this->merchantId) && !empty($this->saltKey) && !empty($this->saltIndex);
    }

    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'PhonePe merchant id, salt key or salt index is missing'];
        }

        return [
            'success' => true,
            'message' => 'PhonePe credentials saved. Live verification happens during checkout/status check.',
        ];
    }

    public function createPayPage(array $data): array
    {
        if (!$this->isConfigured()) {
            return ['status' => 503, 'data' => ['message' => 'PhonePe credentials are missing']];
        }

        $path = '/pg/v1/pay';
        $payload = [
            'merchantId' => $this->merchantId,
            'merchantTransactionId' => $data['transaction_id'],
            'merchantUserId' => $data['user_id'],
            'amount' => (int) $data['amount_paise'],
            'redirectUrl' => $data['redirect_url'],
            'redirectMode' => 'REDIRECT',
            'callbackUrl' => $data['callback_url'],
            'mobileNumber' => $data['phone'],
            'paymentInstrument' => ['type' => 'PAY_PAGE'],
        ];

        $encoded = base64_encode(json_encode($payload));
        $xVerify = $this->sign($encoded . $path);

        return $this->request('post', $path, [
            'request' => $encoded,
        ], [
            'X-VERIFY' => $xVerify,
        ]);
    }

    public function status(string $transactionId): array
    {
        if (!$this->isConfigured()) {
            return ['status' => 503, 'data' => ['message' => 'PhonePe credentials are missing']];
        }

        $path = '/pg/v1/status/' . $this->merchantId . '/' . $transactionId;
        $xVerify = $this->sign($path);

        return $this->request('get', $path, [], [
            'X-VERIFY' => $xVerify,
            'X-MERCHANT-ID' => $this->merchantId,
        ]);
    }

    public function isSuccessStatus(array $response): bool
    {
        $code = strtoupper((string)($response['code'] ?? ''));
        $state = strtoupper((string)($response['data']['state'] ?? ''));
        return in_array($code, ['PAYMENT_SUCCESS', 'SUCCESS']) || in_array($state, ['COMPLETED', 'SUCCESS']);
    }

    public function isFailedStatus(array $response): bool
    {
        $code = strtoupper((string)($response['code'] ?? ''));
        $state = strtoupper((string)($response['data']['state'] ?? ''));
        return str_contains($code, 'FAILED') || in_array($state, ['FAILED', 'CANCELLED', 'EXPIRED']);
    }

    private function sign(string $payloadPlusPath): string
    {
        return hash('sha256', $payloadPlusPath . $this->saltKey) . '###' . $this->saltIndex;
    }

    private function request(string $method, string $path, array $data = [], array $headers = []): array
    {
        try {
            $http = Http::withHeaders(array_merge([
                'Content-Type' => 'application/json',
                'accept' => 'application/json',
            ], $headers))->timeout(20)->retry(1, 500);

            $url = $this->baseUrl . $path;
            $response = strtolower($method) === 'post'
                ? $http->post($url, $data)
                : $http->get($url);

            return [
                'status' => $response->status(),
                'data' => $response->json() ?: ['raw' => $response->body()],
            ];
        } catch (\Throwable $e) {
            Log::warning('PhonePe API request failed', [
                'method' => $method,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 500,
                'data' => ['message' => 'PhonePe request failed: ' . $e->getMessage()],
            ];
        }
    }
}
