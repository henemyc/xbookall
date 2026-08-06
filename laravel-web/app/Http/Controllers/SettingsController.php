<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;
use App\Support\PlatformMaintenance;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SettingsController extends BaseController
{
    public function appUpdate(Request $request): JsonResponse
    {
        $currentVersion = $this->cleanVersion($request->get('current_version', '0.0.0'));
        $latestVersion = $this->cleanVersion(Setting::getValue('app_version', 1, '1.1.0'));
        $downloadUrl = Setting::getValue('app_download_url', 1, '');
        $forceUpdate = Setting::getValue('force_update', 1, '0') == '1';
        $message = Setting::getValue('update_message', 1, 'A new version of GymXBook is available.');

        return $this->success([
            'current_version' => $currentVersion,
            'latest_version' => $latestVersion,
            'update_available' => version_compare($currentVersion, $latestVersion, '<'),
            'force_update' => $forceUpdate,
            'update_url' => $downloadUrl,
            'message' => $message,
            'maintenance' => PlatformMaintenance::status(),
        ]);
    }

    public function systemStatus(Request $request): JsonResponse
    {
        return $this->success([
            'maintenance' => PlatformMaintenance::status(),
        ]);
    }

    private function cleanVersion($version): string
    {
        $version = trim((string) $version);
        $version = explode('+', $version)[0];
        $version = preg_replace('/[^0-9.]/', '', $version);
        return $version ?: '0.0.0';
    }

    public function index(Request $request): JsonResponse
    {
        $parentIds = $this->getGymParentIds();
        $currentUser = $this->currentUser();
        $pid = $this->getParentId();

        // Merge settings by priority so root/legacy parent_id=1 never
        // overwrites this gym owner's own settings. This is important for staff
        // logins because their scope can include legacy fallback IDs.
        $settings = [];
        foreach (array_reverse($parentIds) as $scopeId) {
            $rows = Setting::where('parent_id', (int) $scopeId)->get();
            foreach ($rows as $row) {
                $settings[$row->name] = $row->value;
            }
        }

        $admin = User::find($pid) ?: $currentUser;

        $gymInfo = [
            'name'    => $settings['company_name'] ?? $settings['gym_name'] ?? ($admin && isset($admin->name) ? $admin->name : 'GymXBook'),
            'phone'   => $settings['company_phone'] ?? $settings['phone'] ?? ($admin && isset($admin->phone_number) ? $admin->phone_number : ''),
            'email'   => $settings['company_email'] ?? $settings['email'] ?? ($admin && isset($admin->email) ? $admin->email : ''),
            'address' => $settings['company_address'] ?? $settings['address'] ?? '',
        ];

        // Attendance QR must be gym-specific. Never reuse a root/global
        // parent_id=1 QR secret returned by the broad settings query, otherwise
        // members can scan a QR that the attendance API cannot map back to the
        // actual gym owner.
        $qrSecret = Setting::getValue('attendance_qr_secret', $pid);
        if (empty($qrSecret)) {
            $qrSecret = bin2hex(random_bytes(16));
            Setting::setValue('attendance_qr_secret', $qrSecret, $pid);
        }
        $settings['attendance_qr_secret'] = $qrSecret;

        return $this->success([
            'settings' => $settings,
            'gym_info' => $gymInfo,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $pid = $this->getParentId();

        $input = $request->all();

        foreach ($input as $name => $value) {
            if (in_array($name, ['api_token', 'password'])) continue;

            Setting::setValue($name, $value, $pid);
        }

        return $this->success([], 'Settings updated');
    }

    public function gymProfile(Request $request): JsonResponse
    {
        $currentUser = $this->currentUser();
        $pid = $currentUser ? (int)$currentUser->id : 0;

        $profile = [
            'company_name'    => Setting::getValue('company_name', $pid, ''),
            'company_phone'   => Setting::getValue('company_phone', $pid, ''),
            'company_email'   => Setting::getValue('company_email', $pid, ''),
            'company_address' => Setting::getValue('company_address', $pid, ''),
            'company_website' => Setting::getValue('company_website', $pid, ''),
            'company_logo'    => Setting::getValue('company_logo', $pid, ''),
        ];

        return $this->success(['profile' => $profile]);
    }

    public function updateGymProfile(Request $request): JsonResponse
    {
        $currentUser = $this->currentUser();
        $pid = $currentUser ? (int)$currentUser->id : 0;

        $fields = ['company_name', 'company_phone', 'company_email', 'company_address', 'company_website', 'company_logo'];

        foreach ($fields as $field) {
            if ($request->has($field)) {
                Setting::setValue($field, $request->$field, $pid);
            }
        }

        return $this->success([], 'Gym profile updated');
    }

    // ==================== SMTP SETTINGS ====================

    public function smtpSettings(Request $request): JsonResponse
    {
        $currentUser = $this->currentUser();
        $pid = $currentUser ? (int)$currentUser->id : 0;

        $smtp = [
            'mail_mailer'       => Setting::getValue('mail_mailer', $pid, env('MAIL_MAILER', 'smtp')),
            'mail_host'         => Setting::getValue('mail_host', $pid, env('MAIL_HOST', '')),
            'mail_port'         => Setting::getValue('mail_port', $pid, env('MAIL_PORT', '587')),
            'mail_username'     => Setting::getValue('mail_username', $pid, env('MAIL_USERNAME', '')),
            'mail_password'     => Setting::getValue('mail_password', $pid, ''), // never return actual password
            'mail_encryption'   => Setting::getValue('mail_encryption', $pid, env('MAIL_ENCRYPTION', 'tls')),
            'mail_from_address' => Setting::getValue('mail_from_address', $pid, env('MAIL_FROM_ADDRESS', '')),
            'mail_from_name'    => Setting::getValue('mail_from_name', $pid, env('MAIL_FROM_NAME', 'GymXBook')),
        ];

        return $this->success(['smtp' => $smtp]);
    }

    public function updateSmtpSettings(Request $request): JsonResponse
    {
        $currentUser = $this->currentUser();
        $pid = $currentUser ? (int)$currentUser->id : 0;

        $fields = [
            'mail_mailer', 'mail_host', 'mail_port', 'mail_username',
            'mail_encryption', 'mail_from_address', 'mail_from_name'
        ];

        foreach ($fields as $field) {
            if ($request->has($field)) {
                Setting::setValue($field, $request->$field, $pid);
            }
        }

        // Handle password separately (only update if provided and non-empty)
        if ($request->filled('mail_password')) {
            Setting::setValue('mail_password', $request->mail_password, $pid);
        }

        // Clear config cache so new mail settings take effect immediately
        \Artisan::call('config:clear');

        return $this->success([], 'SMTP settings updated');
    }

    /**
     * Test SMTP configuration by sending a test email
     */
    public function testSmtp(Request $request): JsonResponse
    {
        $request->validate([
            'test_email' => 'required|email',
        ]);

        $testEmail = $request->test_email;

        try {
            // Use current config (or dynamically load from settings if needed)
            \Illuminate\Support\Facades\Mail::raw(
                "This is a test email from GymXBook.\n\nTimestamp: " . now()->toDateTimeString() . "\n\nIf you received this, SMTP is working correctly!",
                function ($message) use ($testEmail) {
                    $message->to($testEmail)
                        ->subject('GymXBook SMTP Test - ' . now()->format('Y-m-d H:i'));
                }
            );

            return $this->success([
                'message' => 'Test email sent successfully',
                'sent_to' => $testEmail,
            ]);

        } catch (\Exception $e) {
            return $this->error('SMTP Test failed: ' . $e->getMessage(), 500);
        }
    }
}
