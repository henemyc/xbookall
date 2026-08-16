<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\PushDeliveryLog;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmPushService
{
    public function isConfigured(): bool
    {
        return is_file($this->credentialPath()) && !empty(config('fcm.project_id'));
    }

    public function diagnostics(): array
    {
        $path = $this->credentialPath();
        $json = is_file($path) ? json_decode((string) @file_get_contents($path), true) : null;

        return [
            'project_id' => config('fcm.project_id'),
            'credential_path' => $path,
            'credential_exists' => is_file($path),
            'credential_json_valid' => is_array($json),
            'service_account_email' => is_array($json) ? ($json['client_email'] ?? null) : null,
            'configured' => $this->isConfigured(),
        ];
    }

    public function sendToUser(int $userId, string $title, string $body, array $data = []): array
    {
        if (!$this->isConfigured()) {
            Log::warning('FCM skipped: configuration unavailable', [
                'project_id' => config('fcm.project_id'),
                'credential_path' => $this->credentialPath(),
                'credential_exists' => is_file($this->credentialPath()),
                'user_id' => $userId,
            ]);
            return ['sent' => 0, 'invalid' => 0, 'skipped' => true];
        }

        $sent = 0;
        $invalid = 0;
        $category = (string) ($data['category'] ?? $data['type'] ?? 'general');
        if (!NotificationPreferenceService::enabledFor($userId, $category)) {
            return ['sent' => 0, 'invalid' => 0, 'skipped' => true, 'preference_disabled' => true];
        }

        DeviceToken::where('user_id', $userId)->get()->each(function (DeviceToken $device) use ($userId, $title, $body, $data, $category, &$sent, &$invalid) {
            $result = $this->sendToToken($device->token, $title, $body, $data);
            PushDeliveryLog::create([
                'user_id' => $userId,
                'device_token_id' => $device->id,
                'category' => $category,
                'title' => $title,
                'status' => !empty($result['success']) ? 'sent' : (!empty($result['invalid']) ? 'invalid_token' : 'failed'),
                'fcm_message_id' => $result['message_id'] ?? null,
                'error_message' => $result['error'] ?? null,
            ]);
            if (!empty($result['success'])) {
                $sent++;
            }
            if (!empty($result['invalid'])) {
                $device->delete();
                $invalid++;
            }
        });

        return compact('sent', 'invalid');
    }

    public function sendToGymUsers(int $gymOwnerId, string $title, string $body, array $data = []): array
    {
        $userIds = User::where('is_active', true)
            ->where(function ($query) use ($gymOwnerId) {
                $query->where('id', $gymOwnerId)->orWhere('parent_id', $gymOwnerId);
            })
            ->pluck('id');

        $sent = 0;
        foreach ($userIds as $userId) {
            $result = $this->sendToUser((int) $userId, $title, $body, $data);
            $sent += (int) ($result['sent'] ?? 0);
        }

        return ['sent' => $sent];
    }

    public function sendToGymMembers(int $gymOwnerId, string $title, string $body, array $data = []): array
    {
        $memberIds = User::where('is_active', true)
            ->where('type', 'trainee')
            ->where('parent_id', $gymOwnerId)
            ->pluck('id');

        $sent = 0;
        foreach ($memberIds as $memberId) {
            $result = $this->sendToUser((int) $memberId, $title, $body, $data);
            $sent += (int) ($result['sent'] ?? 0);
        }

        return ['sent' => $sent];
    }

    public function sendToToken(string $token, string $title, string $body, array $data = []): array
    {
        try {
            $accessToken = $this->accessToken();
            if (!$accessToken) {
                return ['success' => false, 'error' => 'Could not obtain Firebase OAuth access token'];
            }

            $stringData = collect($data)
                ->mapWithKeys(fn ($value, $key) => [(string) $key => (string) $value])
                ->all();

            $response = Http::withToken($accessToken)
                ->timeout(15)
                ->post('https://fcm.googleapis.com/v1/projects/' . config('fcm.project_id') . '/messages:send', [
                    'message' => [
                        'token' => $token,
                        'notification' => [
                            'title' => $title,
                            'body' => $body,
                        ],
                        'data' => $stringData,
                        'android' => [
                            'priority' => 'HIGH',
                            'notification' => [
                                'channel_id' => 'gymxbook_general',
                            ],
                        ],
                    ],
                ]);

            if ($response->successful()) {
                return ['success' => true, 'message_id' => $response->json('name')];
            }

            $raw = $response->body();
            $invalid = $response->status() === 404
                || str_contains($raw, 'UNREGISTERED')
                || str_contains($raw, 'INVALID_ARGUMENT');

            Log::warning('FCM send failed', [
                'status' => $response->status(),
                'body' => $raw,
            ]);

            return [
                'success' => false,
                'invalid' => $invalid,
                'error' => 'FCM HTTP ' . $response->status(),
            ];
        } catch (\Throwable $e) {
            Log::warning('FCM push exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function accessToken(): ?string
    {
        return Cache::remember('fcm_http_v1_access_token', now()->addMinutes(50), function () {
            $credentials = json_decode((string) file_get_contents($this->credentialPath()), true);
            if (!is_array($credentials) || empty($credentials['client_email']) || empty($credentials['private_key'])) {
                return null;
            }

            $now = time();
            $header = $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $claims = $this->base64Url(json_encode([
                'iss' => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]));

            $unsigned = $header . '.' . $claims;
            if (!openssl_sign($unsigned, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
                return null;
            }

            $jwt = $unsigned . '.' . $this->base64Url($signature);
            $response = Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            return $response->successful() ? $response->json('access_token') : null;
        });
    }

    private function credentialPath(): string
    {
        $path = (string) config('fcm.service_account_path');
        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:\\\\/', $path)
            ? $path
            : base_path($path);
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
