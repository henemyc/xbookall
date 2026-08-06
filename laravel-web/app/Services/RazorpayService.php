<?php

namespace App\Services;

use App\Models\PaymentGatewaySetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RazorpayService
{
    private string $keyId;
    private string $keySecret;
    private string $webhookSecret;
    private bool $sandbox;
    private string $baseUrl = 'https://api.razorpay.com/v1';

    public function __construct(?PaymentGatewaySetting $gateway = null)
    {
        $credentials = $gateway ? $gateway->getCredentialsArray() : [];

        $this->keyId = $credentials['key_id'] ?? env('RAZORPAY_KEY_ID', '');
        $this->keySecret = $credentials['key_secret'] ?? env('RAZORPAY_KEY_SECRET', '');
        $this->webhookSecret = $credentials['webhook_secret'] ?? env('RAZORPAY_WEBHOOK_SECRET', '');
        $this->sandbox = $gateway ? $gateway->mode !== 'production' : filter_var(env('RAZORPAY_SANDBOX', true), FILTER_VALIDATE_BOOLEAN);
    }

    public function isConfigured(): bool
    {
        return !empty($this->keyId) && !empty($this->keySecret);
    }

    public function createPaymentLink(array $data): array
    {
        return $this->request('post', '/payment_links', $data);
    }

    public function getPaymentLink(string $paymentLinkId): array
    {
        return $this->request('get', '/payment_links/' . urlencode($paymentLinkId));
    }

    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'Razorpay credentials are missing'];
        }

        $res = $this->request('get', '/payment_links?count=1');
        $status = (int) ($res['status'] ?? 0);

        if ($status >= 200 && $status < 300) {
            return ['success' => true, 'message' => 'Razorpay credentials accepted'];
        }

        if (in_array($status, [401, 403])) {
            return ['success' => false, 'message' => 'Razorpay authentication failed. Check Key ID, Key Secret and mode.', 'response' => $res['data'] ?? null];
        }

        return ['success' => false, 'message' => $res['data']['error']['description'] ?? $res['data']['message'] ?? 'Razorpay connection test failed', 'response' => $res['data'] ?? null];
    }

    public function verifyWebhookSignature(string $rawBody, ?string $signature): bool
    {
        if (empty($this->webhookSecret)) {
            // If not configured, do not trust webhook for activation. Caller should
            // still do server-to-server verification before activation.
            return false;
        }
        if (!$signature) return false;

        $expected = hash_hmac('sha256', $rawBody, $this->webhookSecret);
        return hash_equals($expected, $signature);
    }

    private function request(string $method, string $path, array $data = []): array
    {
        if (!$this->isConfigured()) {
            return ['status' => 503, 'data' => ['message' => 'Razorpay credentials are missing']];
        }

        try {
            $http = Http::withBasicAuth($this->keyId, $this->keySecret)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->timeout(20)
                ->retry(1, 500);

            $url = $this->baseUrl . $path;
            $response = strtolower($method) === 'post'
                ? $http->post($url, $data)
                : $http->get($url);

            return [
                'status' => $response->status(),
                'data' => $response->json() ?: ['raw' => $response->body()],
            ];
        } catch (\Throwable $e) {
            Log::warning('Razorpay API request failed', [
                'method' => $method,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 500,
                'data' => ['message' => 'Razorpay request failed: ' . $e->getMessage()],
            ];
        }
    }
}
