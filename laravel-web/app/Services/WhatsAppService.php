<?php

namespace App\Services;

use App\Models\WhatsAppLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    private string $phoneNumberId;
    private string $accessToken;
    private string $apiVersion;
    private string $otpTemplateName;

    public function __construct()
    {
        $this->phoneNumberId = env('WHATSAPP_PHONE_NUMBER_ID', config('services.whatsapp.phone_number_id', ''));
        $this->accessToken   = env('WHATSAPP_API_TOKEN', config('services.whatsapp.api_token', ''));
        $this->apiVersion    = env('WHATSAPP_API_VERSION', 'v20.0');

        // MUST be the EXACT template name approved in Meta Business Suite.
        // Use the NEW 'gymxbook_otp' template (or fallback to 'otp' if still using the old one).
        // Add WHATSAPP_OTP_TEMPLATE_NAME=gymxbook_otp in .env to be explicit.
        $this->otpTemplateName = env('WHATSAPP_OTP_TEMPLATE_NAME', 'gymxbook_otp');
    }

    public function isConfigured(): bool
    {
        return !empty($this->phoneNumberId) && !empty($this->accessToken);
    }

    /**
     * Format phone number exactly like the old WhatsAppHelper
     */
    public function formatPhone(string $phone): ?string
    {
        if (empty($phone)) return null;

        $digits = preg_replace('/\D/', '', $phone);

        if (strlen($digits) < 10) return null;

        if (strlen($digits) == 10) {
            $digits = '91' . $digits;
        } elseif (strlen($digits) == 11 && $digits[0] == '0') {
            $digits = '91' . substr($digits, 1);
        } elseif (strlen($digits) == 12 && substr($digits, 0, 3) == '091') {
            $digits = '91' . substr($digits, 3);
        }

        return (strlen($digits) >= 11 && strlen($digits) <= 15) ? $digits : null;
    }

    /**
     * Send approved template message (core method)
     */
    public function sendTemplate(
        string $to,
        string $templateName,
        string $language = 'en',
        array $params = [],
        int $parentId = 0,
        string $toName = ''
    ): array {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'WhatsApp not configured'];
        }

        $formattedTo = $this->formatPhone($to);
        if (!$formattedTo) {
            $this->log($to, $toName, $templateName, $language, $params, 'failed', null, null, 'Invalid phone', $parentId);
            return ['success' => false, 'error' => 'Invalid phone number'];
        }

        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/messages";

        $components = [];
        if (!empty($params)) {
            $bodyParams = [];
            foreach ($params as $value) {
                $bodyParams[] = ['type' => 'text', 'text' => (string)$value];
            }
            $components[] = ['type' => 'body', 'parameters' => $bodyParams];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $formattedTo,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $language],
                'components' => $components
            ]
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json',
            ])->timeout(15)->post($url, $payload);

            $data = $response->json();
            $success = $response->successful() && isset($data['messages'][0]['id']);

            if ($success) {
                $messageId = $data['messages'][0]['id'];
                $this->log($formattedTo, $toName, $templateName, $language, $params, 'sent', $messageId, $data, null, $parentId);
                return ['success' => true, 'message_id' => $messageId, 'to' => $formattedTo];
            }

            $error = $this->extractError($response, $data);
            $this->log($formattedTo, $toName, $templateName, $language, $params, 'failed', null, $data, $error, $parentId);
            return [
                'success' => false, 
                'error' => $error,
                'http_status' => $response->status(),
                'raw' => $data,
            ];

        } catch (\Exception $e) {
            $this->log($formattedTo, $toName, $templateName, $language, $params, 'failed', null, null, $e->getMessage(), $parentId);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ==================== BUSINESS TEMPLATES (Exact names from old system) ====================

    public function sendMemberWelcome(string $phone, string $memberName, string $gymName, string $expiry, int $parentId = 0): array
    {
        $params = [
            $memberName ?: 'Member',
            $gymName ?: 'Your Gym',
            $this->formatDate($expiry)
        ];
        return $this->sendTemplate($phone, 'gymxbook_member_welcome', 'en', $params, $parentId, $memberName);
    }

    public function sendMemberRenew(string $phone, string $memberName, string $gymName, string $expiry, $amount, int $parentId = 0): array
    {
        $amountStr = is_numeric($amount) ? number_format((float)$amount) : (string)$amount;
        $params = [
            $memberName ?: 'Member',
            $gymName ?: 'Your Gym',
            $this->formatDate($expiry),
            $amountStr
        ];
        return $this->sendTemplate($phone, 'gymxbook_member_renew', 'en', $params, $parentId, $memberName);
    }

    public function sendMemberExpired(string $phone, string $memberName, string $expiry, int $parentId = 0): array
    {
        $params = [
            $memberName ?: 'Member',
            $this->formatDate($expiry)
        ];
        return $this->sendTemplate($phone, 'gymxbook_member_expired', 'en', $params, $parentId, $memberName);
    }

    /**
     * OTP — same payload flow as the working PWA
     * (gymxbook/api.php + lib/WhatsAppHelper.php::sendOtp)
     *
     * Now supports BOTH templates:
     *   - 'otp' (legacy approved)
     *   - 'gymxbook_otp' (NEW approved template)
     *
     * The template name is read from env('WHATSAPP_OTP_TEMPLATE_NAME', 'gymxbook_otp')
     * This replicates the payload + index as string '0' exactly.
     */
    public function sendOtp(string $phone, string $otp, int $parentId = 0): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'WhatsApp not configured'];
        }

        $formattedTo = $this->formatPhone($phone);
        if (!$formattedTo) {
            return ['success' => false, 'error' => 'Invalid phone number'];
        }

        $otp = str_pad((string)$otp, 6, '0', STR_PAD_LEFT);
        $templateName = $this->otpTemplateName;   // 'otp'
        $language = 'en';

        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/messages";

        // === EXACT payload from working PWA WhatsAppHelper::sendOtp ===
        // Attempt 1: Body + Copy Code button (the one succeeding from api.php)
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $formattedTo,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $language],
                'components' => [
                    [
                        'type' => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => $otp]
                        ]
                    ],
                    [
                        'type' => 'button',
                        'sub_type' => 'copy_code',
                        'index' => '0',                    // string '0' — critical match
                        'parameters' => [
                            ['type' => 'coupon_code', 'coupon_code' => $otp]
                        ]
                    ]
                ]
            ]
        ];

        $result = $this->sendRawPayload($url, $payload, $formattedTo, $templateName, $otp, $parentId);
        if ($result['success']) return $result;

        // Attempt 2: Body + URL (PWA fallback)
        $payload['template']['components'][1] = [
            'type' => 'button',
            'sub_type' => 'url',
            'index' => '0',
            'parameters' => [['type' => 'text', 'text' => $otp]]
        ];
        $result = $this->sendRawPayload($url, $payload, $formattedTo, $templateName, $otp, $parentId);
        if ($result['success']) return $result;

        // Attempt 3: Body only
        $payload['template']['components'] = [
            ['type' => 'body', 'parameters' => [['type' => 'text', 'text' => $otp]]]
        ];
        return $this->sendRawPayload($url, $payload, $formattedTo, $templateName, $otp, $parentId);
    }

    private function sendRawPayload(string $url, array $payload, string $to, string $template, string $otp, int $parentId): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json',
            ])->timeout(15)->post($url, $payload);

            $data = $response->json();

            if ($response->successful() && isset($data['messages'][0]['id'])) {
                $msgId = $data['messages'][0]['id'];
                $this->log($to, 'OTP', $template, 'en', [$otp], 'sent', $msgId, $data, null, $parentId);
                return ['success' => true, 'message_id' => $msgId, 'to' => $to, 'response' => $data];
            }

            $error = $this->extractError($response, $data);
            $this->log($to, 'OTP', $template, 'en', [$otp], 'failed', null, $data, $error, $parentId);
            return [
                'success' => false, 
                'error' => $error,
                'http_status' => $response->status(),
                'raw_response' => $data,
                'response' => $data,
            ];

        } catch (\Exception $e) {
            $this->log($to, 'OTP', $template, 'en', [$otp], 'failed', null, null, $e->getMessage(), $parentId);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ==================== LOGGING ====================

    private function log($to, $toName, $template, $language, $params, $status, $messageId = null, $response = null, $error = null, $parentId = 0)
    {
        try {
            WhatsAppLog::create([
                'parent_id'     => $parentId,
                'to_number'     => $to,
                'to_name'       => $toName,
                'template_name' => $template,
                'message'       => is_array($params) ? json_encode($params) : $params,
                'message_id'    => $messageId,
                'status'        => $status,
                'response'      => is_array($response) ? json_encode($response) : $response,
            ]);
        } catch (\Exception $e) {
            Log::error('WhatsApp log failed: ' . $e->getMessage());
        }
    }

    private function formatDate($date): string
    {
        if (empty($date)) return 'N/A';
        try {
            return \Carbon\Carbon::parse($date)->format('d-m-Y');
        } catch (\Exception $e) {
            return (string)$date;
        }
    }

    // ==================== STATS ====================

    public function getMonthlyStats(int $parentId, ?string $month = null): array
    {
        $month = $month ?? now()->format('Y-m');
        return WhatsAppLog::where('parent_id', $parentId)
            ->where('created_at', 'like', $month . '%')
            ->selectRaw('template_name, COUNT(*) as count')
            ->groupBy('template_name')
            ->pluck('count', 'template_name')
            ->toArray();
    }

    public function getTotalCount(int $parentId, ?string $month = null): int
    {
        $query = WhatsAppLog::where('parent_id', $parentId)->where('status', 'sent');
        if ($month) {
            $query->where('created_at', 'like', $month . '%');
        }
        return $query->count();
    }

    public function getConfig(): array
    {
        return [
            'configured'        => $this->isConfigured(),
            'phone_number_id'   => $this->phoneNumberId ?: 'NOT SET',
            'api_version'       => $this->apiVersion,
            'token_length'      => $this->accessToken ? strlen($this->accessToken) : 0,
            'token_preview'     => $this->accessToken ? substr($this->accessToken, 0, 15) . '...' : 'MISSING',
            'api_url_used'      => "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/messages",
            'otp_template_name' => $this->otpTemplateName,
        ];
    }

    /**
     * Test authentication + basic connectivity
     */
    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'WhatsApp not configured (missing phone_number_id or token)'];
        }

        // Simple test: get phone number info (this validates token + phone_number_id)
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}";

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
            ])->timeout(15)->get($url);

            $data = $response->json();

            if ($response->successful() && isset($data['id'])) {
                return [
                    'success' => true,
                    'message' => 'Authentication successful',
                    'phone_number_id' => $data['id'] ?? $this->phoneNumberId,
                    'display_phone_number' => $data['display_phone_number'] ?? null,
                    'verified_name' => $data['verified_name'] ?? null,
                ];
            }

            $error = $data['error']['message'] ?? 'Authentication failed';
            $result = [
                'success' => false,
                'error' => $error,
                'full_error' => $data['error'] ?? $data,
                'http_status' => $response->status(),
            ];

            // Very common case: token expired / user logged out
            if (str_contains(strtolower($error), 'logged out') || ($data['error']['code'] ?? 0) == 190) {
                $result['hint'] = 'TOKEN IS DEAD. Generate a fresh "Never expire" token in Meta Business Suite.';
                $result['next_step'] = '1. Meta Business Settings → System Users → Select user → Generate new token (Never expire)  2. Update .env  3. php artisan config:clear';
            }

            return $result;

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get detailed error from Meta response
     */
    private function extractError($response, $data): string
    {
        if (isset($data['error']['message'])) {
            $msg = $data['error']['message'];
            if (isset($data['error']['error_user_msg'])) {
                $msg .= ' | ' . $data['error']['error_user_msg'];
            }
            if (isset($data['error']['code'])) {
                $msg .= ' (code: ' . $data['error']['code'] . ')';
            }
            return $msg;
        }

        if (isset($data['error'])) {
            return is_string($data['error']) ? $data['error'] : json_encode($data['error']);
        }

        // Sometimes Meta returns error at top level
        if (isset($data['message'])) {
            return $data['message'];
        }

        return $response->successful() ? 'Unknown API error' : 'HTTP ' . $response->status();
    }

    /**
     * === DIAGNOSTIC METHOD ===
     * Run this in tinker to debug "Authentication Error"
     */
    public function diagnose(): array
    {
        $results = [
            'timestamp' => now()->toDateTimeString(),
            'env_values' => [
                'WHATSAPP_PHONE_NUMBER_ID' => env('WHATSAPP_PHONE_NUMBER_ID') ?: 'MISSING',
                'WHATSAPP_API_TOKEN_length' => env('WHATSAPP_API_TOKEN') ? strlen(env('WHATSAPP_API_TOKEN')) : 0,
                'WHATSAPP_API_VERSION' => env('WHATSAPP_API_VERSION') ?: 'MISSING (default v20.0)',
                'WHATSAPP_API_URL' => env('WHATSAPP_API_URL') ?: 'NOT SET',
                'WHATSAPP_OTP_TEMPLATE_NAME' => env('WHATSAPP_OTP_TEMPLATE_NAME') ?: 'otp (default)',
            ],
            'service' => [
                'phoneNumberId' => $this->phoneNumberId ?: 'EMPTY',
                'accessToken_length' => strlen($this->accessToken ?? ''),
                'accessToken_preview' => $this->accessToken ? substr($this->accessToken, 0, 25) . '...' : 'MISSING',
                'apiVersion' => $this->apiVersion,
                'otpTemplateName' => $this->otpTemplateName,
                'isConfigured' => $this->isConfigured(),
            ],
        ];

        // Test 1: Basic connection to phone number endpoint (this often reveals auth problems)
        $results['connection_test'] = $this->testConnection();

        // Test 2: Show what the OTP send URL would be
        $results['send_url'] = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/messages";

        return $results;
    }

    /**
     * Debug helper for OTP (shows what would be sent)
     */
    public function debugSendOtp(string $phone, string $otp, int $parentId = 0): array
    {
        $formattedTo = $this->formatPhone($phone);
        $otp = str_pad($otp, 6, '0', STR_PAD_LEFT);

        return [
            'configured'      => $this->isConfigured(),
            'phone_number_id' => $this->phoneNumberId,
            'api_version'     => $this->apiVersion,
            'token_preview'   => $this->accessToken ? substr($this->accessToken, 0, 20) . '...' : 'MISSING',
            'formatted_phone' => $formattedTo,
            'url'             => "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/messages",
            'first_attempt'   => 'copy_code',
            'template_name'   => $this->otpTemplateName,
        ];
    }
}
