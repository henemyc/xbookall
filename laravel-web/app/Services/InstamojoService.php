<?php

namespace App\Services;

use App\Models\PaymentGatewaySetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InstamojoService
{
    private string $apiKey;
    private string $authToken;
    private string $salt;
    private bool $sandbox;
    private string $baseUrl;

    public function __construct(?PaymentGatewaySetting $gateway = null)
    {
        $credentials = $gateway ? $gateway->getCredentialsArray() : [];

        $this->apiKey = $credentials['api_key'] ?? env('INSTAMOJO_API_KEY', '');
        $this->authToken = $credentials['auth_token'] ?? env('INSTAMOJO_AUTH_TOKEN', '');
        $this->salt = $credentials['salt'] ?? $credentials['webhook_secret'] ?? env('INSTAMOJO_SALT', '');
        $this->sandbox = $gateway ? $gateway->mode !== 'production' : filter_var(env('INSTAMOJO_SANDBOX', true), FILTER_VALIDATE_BOOLEAN);
        $this->baseUrl = $this->sandbox ? 'https://test.instamojo.com/api/1.1' : 'https://www.instamojo.com/api/1.1';
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && !empty($this->authToken);
    }

    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'Instamojo API key/auth token are missing'];
        }

        $res = $this->request('get', '/payment-requests/', ['limit' => 1]);
        $status = (int) ($res['status'] ?? 0);

        if ($status >= 200 && $status < 300) {
            return ['success' => true, 'message' => 'Instamojo credentials accepted (' . ($this->sandbox ? 'Sandbox' : 'Production') . ')'];
        }

        if (in_array($status, [401, 403])) {
            return ['success' => false, 'message' => 'Instamojo authentication failed. Check API Key, Auth Token and mode.', 'response' => $res['data'] ?? null];
        }

        return ['success' => false, 'message' => $res['data']['message'] ?? 'Instamojo connection test failed', 'response' => $res['data'] ?? null];
    }

    public function createPaymentRequest(array $data): array
    {
        return $this->request('post', '/payment-requests/', $data);
    }

    public function getPaymentRequest(string $paymentRequestId): array
    {
        return $this->request('get', '/payment-requests/' . urlencode($paymentRequestId) . '/');
    }

    public function isPaid(array $paymentRequest): bool
    {
        $status = strtolower((string)($paymentRequest['status'] ?? ''));
        if (in_array($status, ['completed', 'credit'])) return true;

        $payments = $paymentRequest['payments'] ?? [];
        if (is_array($payments)) {
            foreach ($payments as $payment) {
                $paymentStatus = strtolower((string)($payment['status'] ?? ''));
                if (in_array($paymentStatus, ['credit', 'completed', 'successful', 'success'])) return true;
            }
        }

        return false;
    }

    public function isFailed(array $paymentRequest): bool
    {
        $status = strtolower((string)($paymentRequest['status'] ?? ''));
        return in_array($status, ['failed', 'cancelled', 'expired']);
    }

    public function extractPaymentId(array $paymentRequest): ?string
    {
        $payments = $paymentRequest['payments'] ?? [];
        if (is_array($payments) && !empty($payments)) {
            $first = $payments[0] ?? [];
            return $first['payment_id'] ?? $first['id'] ?? null;
        }
        return null;
    }

    /**
     * Instamojo webhooks include a mac in classic integrations. If salt is not
     * configured or mac is absent, we do not trust webhook directly; caller will
     * still verify server-to-server before activation.
     */
    public function verifyWebhook(array $data): bool
    {
        if (empty($this->salt) || empty($data['mac'])) {
            return false;
        }

        $receivedMac = $data['mac'];
        unset($data['mac']);
        ksort($data, SORT_STRING | SORT_FLAG_CASE);

        $macString = '';
        foreach ($data as $key => $value) {
            $macString .= '|' . $value;
        }
        $macString = ltrim($macString, '|');

        $generatedMac = hash_hmac('sha1', $macString, $this->salt);
        return hash_equals(strtolower($generatedMac), strtolower($receivedMac));
    }

    private function request(string $method, string $path, array $data = []): array
    {
        if (!$this->isConfigured()) {
            return ['status' => 503, 'data' => ['message' => 'Instamojo credentials are missing']];
        }

        try {
            $http = Http::asForm()
                ->withHeaders([
                    'X-Api-Key' => $this->apiKey,
                    'X-Auth-Token' => $this->authToken,
                ])
                ->timeout(20)
                ->retry(1, 500);

            $url = $this->baseUrl . $path;
            $response = strtolower($method) === 'post'
                ? $http->post($url, $data)
                : $http->get($url, $data);

            return [
                'status' => $response->status(),
                'data' => $response->json() ?: ['raw' => $response->body()],
            ];
        } catch (\Throwable $e) {
            Log::warning('Instamojo API request failed', [
                'method' => $method,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 500,
                'data' => ['message' => 'Instamojo request failed: ' . $e->getMessage()],
            ];
        }
    }
}
