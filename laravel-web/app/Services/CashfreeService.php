<?php

namespace App\Services;

use App\Models\PaymentGatewaySetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CashfreeService
{
    private string $appId;
    private string $secretKey;
    private bool $sandbox;
    private string $apiVersion;
    private string $baseUrl;

    public function __construct(?PaymentGatewaySetting $gateway = null)
    {
        $credentials = $gateway ? $gateway->getCredentialsArray() : [];

        $this->appId = $credentials['app_id'] ?? env('CASHFREE_APP_ID', '');
        $this->secretKey = $credentials['secret_key'] ?? env('CASHFREE_SECRET_KEY', '');
        $this->sandbox = $gateway
            ? $gateway->mode !== 'production'
            : filter_var(env('CASHFREE_SANDBOX', true), FILTER_VALIDATE_BOOLEAN);
        $this->apiVersion = $credentials['api_version'] ?? env('CASHFREE_API_VERSION', '2023-08-01');
        $this->baseUrl = $this->sandbox
            ? 'https://sandbox.cashfree.com'
            : 'https://api.cashfree.com';
    }

    public function createPaymentLink(array $data): array
    {
        return $this->request('post', '/pg/links', $data);
    }

    public function getPaymentLink(string $linkId): array
    {
        return $this->request('get', '/pg/links/' . urlencode($linkId));
    }

    public function getLinkOrders(string $linkId): array
    {
        return $this->request('get', '/pg/links/' . urlencode($linkId) . '/orders');
    }

    public function getOrder(string $orderId): array
    {
        return $this->request('get', '/pg/orders/' . urlencode($orderId));
    }

    public function getOrderPayments(string $orderId): array
    {
        return $this->request('get', '/pg/orders/' . urlencode($orderId) . '/payments');
    }

    public function createOrder(array $data): array
    {
        return $this->request('post', '/pg/orders', $data);
    }

    public function isConfigured(): bool
    {
        return !empty($this->appId) && !empty($this->secretKey);
    }

    public function isSandbox(): bool
    {
        return $this->sandbox;
    }

    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'Cashfree credentials are missing'];
        }

        // Cashfree returns 404 for a non-existing link when credentials are valid.
        // 401/403 means credentials or mode are wrong.
        $res = $this->getPaymentLink('gymxbook_connection_test_' . time());
        $status = (int) ($res['status'] ?? 0);

        if (in_array($status, [404, 422])) {
            return ['success' => true, 'message' => 'Cashfree credentials accepted (' . ($this->sandbox ? 'Sandbox' : 'Production') . ')'];
        }

        if (in_array($status, [401, 403])) {
            return ['success' => false, 'message' => 'Cashfree authentication failed. Check App ID, Secret Key and mode.', 'response' => $res['data'] ?? null];
        }

        if ($status >= 200 && $status < 300) {
            return ['success' => true, 'message' => 'Cashfree connection successful'];
        }

        return ['success' => false, 'message' => $res['data']['message'] ?? 'Cashfree connection test failed', 'response' => $res['data'] ?? null];
    }

    private function request(string $method, string $path, array $data = []): array
    {
        if (!$this->isConfigured()) {
            return [
                'status' => 503,
                'data' => ['message' => 'Cashfree credentials are missing'],
            ];
        }

        try {
            $http = Http::withHeaders([
                'Content-Type' => 'application/json',
                'x-client-id' => $this->appId,
                'x-client-secret' => $this->secretKey,
                'x-api-version' => $this->apiVersion,
            ])->timeout(20)->retry(1, 500);

            $url = $this->baseUrl . $path;
            $response = strtolower($method) === 'post'
                ? $http->post($url, $data)
                : $http->get($url);

            return [
                'status' => $response->status(),
                'data' => $response->json() ?: ['raw' => $response->body()],
            ];
        } catch (\Throwable $e) {
            Log::warning('Cashfree API request failed', [
                'method' => $method,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 500,
                'data' => ['message' => 'Payment gateway request failed: ' . $e->getMessage()],
            ];
        }
    }
}
