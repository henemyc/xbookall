<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Models\Setting;
use App\Services\WhatsAppService;
use App\Support\PlatformMaintenance;
use Illuminate\Http\Request;

class AdminSettingsController extends BaseController
{
    /**
     * Platform settings page
     */
    public function index()
    {
        // SMTP settings
        $smtp = [
            'SERVER_HOST' => Setting::getValue('SERVER_HOST', 1, ''),
            'SERVER_PORT' => Setting::getValue('SERVER_PORT', 1, ''),
            'SERVER_USERNAME' => Setting::getValue('SERVER_USERNAME', 1, ''),
            'SERVER_PASSWORD' => Setting::getValue('SERVER_PASSWORD', 1, '') ? '••••••••' : '',
            'SERVER_DRIVER' => Setting::getValue('SERVER_DRIVER', 1, ''),
            'FROM_EMAIL' => Setting::getValue('FROM_EMAIL', 1, ''),
            'FROM_NAME' => Setting::getValue('FROM_NAME', 1, ''),
        ];

        // WhatsApp settings
        $whatsapp = [
            'api_url' => config('services.whatsapp.api_url', ''),
            'api_token' => config('services.whatsapp.api_token', ''),
            'phone_number_id' => config('services.whatsapp.phone_number_id', ''),
        ];

        // Platform settings
        $platform = [
            'app_name' => Setting::getValue('app_name', 1, 'GymXBook'),
            'app_version' => Setting::getValue('app_version', 1, '1.1.0'),
            'app_download_url' => Setting::getValue('app_download_url', 1, ''),
            'force_update' => Setting::getValue('force_update', 1, '0'),
            'update_message' => Setting::getValue('update_message', 1, 'A new version of GymXBook is available.'),
            'support_email' => Setting::getValue('support_email', 1, 'support@gymxbook.com'),
            'support_phone' => Setting::getValue('support_phone', 1, ''),
            'website' => Setting::getValue('website', 1, 'https://gymxbook.com'),
        ];

        $maintenance = [
            'enabled' => Setting::getValue('maintenance_enabled', 1, '0'),
            'title' => Setting::getValue('maintenance_title', 1, 'GymXBook is under maintenance'),
            'message' => Setting::getValue('maintenance_message', 1, 'We are upgrading GymXBook to serve you better. Please wait until maintenance is complete.'),
            'start_at' => PlatformMaintenance::toInputValue(Setting::getValue('maintenance_start_at', 1, '')),
            'end_at' => PlatformMaintenance::toInputValue(Setting::getValue('maintenance_end_at', 1, '')),
            'status' => PlatformMaintenance::status(),
        ];

        $superAdmin = auth()->user();
        $security = [
            'super_admin_2fa_enabled' => Setting::getValue('super_admin_2fa_enabled', 1, '0'),
            'super_admin_phone' => $superAdmin?->phone_number ?? '',
        ];

        return view('admin.settings.index', compact('smtp', 'whatsapp', 'platform', 'maintenance', 'security'));
    }

    /**
     * Update SMTP settings
     */
    public function updateSmtp(Request $request)
    {
        $fields = ['SERVER_HOST', 'SERVER_PORT', 'SERVER_USERNAME', 'SERVER_PASSWORD', 'SERVER_DRIVER', 'FROM_EMAIL', 'FROM_NAME'];

        foreach ($fields as $field) {
            if ($request->has($field)) {
                $value = $request->$field;
                if ($field === 'SERVER_PASSWORD' && ($value === '••••••••' || $value === '')) continue;
                Setting::setValue($field, $value, 1);
            }
        }

        return redirect()->route('admin.settings.index')->with('success', 'SMTP settings updated');
    }

    /**
     * Update platform settings
     */
    public function updatePlatform(Request $request)
    {
        if ($request->has('run_system_update')) {
            return $this->runSystemUpdateFromSettings();
        }

        $fields = ['app_name', 'app_version', 'app_download_url', 'force_update', 'update_message', 'support_email', 'support_phone', 'website'];

        foreach ($fields as $field) {
            if ($request->has($field)) {
                $value = $request->input($field);

                // Empty optional fields arrive as null because of Laravel middleware.
                // settings.value is not nullable, so save empty string instead.
                if ($value === null) {
                    $value = '';
                }

                if ($field === 'force_update') {
                    $value = $request->boolean('force_update') ? '1' : '0';
                }

                Setting::setValue($field, $value, 1);
            }
        }

        return redirect()->route('admin.settings.index')->with('success', 'Platform settings updated');
    }

    private function runSystemUpdateFromSettings()
    {
        @set_time_limit(300);
        $output = [];
        $ok = true;

        try {
            \Artisan::call('migrate', ['--force' => true]);
            $output[] = trim(\Artisan::output()) ?: 'php artisan migrate completed.';

            $repair = [];
            if (!\Schema::hasTable('staff_roles')) {
                \Schema::create('staff_roles', function ($table) {
                    $table->id();
                    $table->unsignedBigInteger('parent_id');
                    $table->string('name');
                    $table->text('description')->nullable();
                    $table->boolean('is_system')->default(false);
                    $table->tinyInteger('status')->default(1);
                    $table->timestamps();
                    $table->unique(['parent_id', 'name']);
                    $table->index('parent_id');
                    $table->index('status');
                });
                $repair[] = 'Created staff_roles table.';
            }
            if (!\Schema::hasTable('staff_role_permissions')) {
                \Schema::create('staff_role_permissions', function ($table) {
                    $table->id();
                    $table->unsignedBigInteger('staff_role_id');
                    $table->string('permission_key', 120);
                    $table->timestamps();
                    $table->unique(['staff_role_id', 'permission_key'], 'staff_role_permissions_role_permission_unique');
                    $table->index('staff_role_id');
                    $table->index('permission_key');
                });
                $repair[] = 'Created staff_role_permissions table.';
            }
            if (!\Schema::hasTable('activity_logs')) {
                \Schema::create('activity_logs', function ($table) {
                    $table->id();
                    $table->unsignedBigInteger('parent_id')->default(0);
                    $table->unsignedBigInteger('user_id')->nullable();
                    $table->string('user_type', 30)->nullable();
                    $table->string('module', 80);
                    $table->string('action', 80);
                    $table->string('record_type', 120)->nullable();
                    $table->unsignedBigInteger('record_id')->nullable();
                    $table->text('description')->nullable();
                    $table->json('before_json')->nullable();
                    $table->json('after_json')->nullable();
                    $table->string('ip', 60)->nullable();
                    $table->text('user_agent')->nullable();
                    $table->timestamps();
                    $table->index('parent_id');
                    $table->index('user_id');
                    $table->index(['module', 'action']);
                    $table->index(['record_type', 'record_id']);
                    $table->index('created_at');
                });
                $repair[] = 'Created activity_logs table.';
            }
            if (\Schema::hasTable('users')) {
                $columns = [
                    'staff_role_id' => function ($table) { $table->unsignedBigInteger('staff_role_id')->nullable()->after('parent_id')->index(); },
                    'last_login_at' => function ($table) { $table->timestamp('last_login_at')->nullable()->after('email_verified_at'); },
                    'last_login_ip' => function ($table) { $table->string('last_login_ip', 60)->nullable()->after('last_login_at'); },
                    'last_login_user_agent' => function ($table) { $table->text('last_login_user_agent')->nullable()->after('last_login_ip'); },
                    'password_changed_at' => function ($table) { $table->timestamp('password_changed_at')->nullable()->after('last_login_user_agent'); },
                ];
                foreach ($columns as $column => $callback) {
                    if (!\Schema::hasColumn('users', $column)) {
                        \Schema::table('users', function ($table) use ($callback) { $callback($table); });
                        $repair[] = 'Added users.' . $column . ' column.';
                    }
                }
            }

            foreach ($repair as $line) $output[] = $line;
            \Artisan::call('optimize:clear');
            $output[] = 'Cache cleared.';
        } catch (\Throwable $e) {
            $ok = false;
            $output[] = 'Update failed: ' . $e->getMessage();
        }

        return redirect()
            ->route('admin.settings.index')
            ->with($ok ? 'success' : 'error', $ok ? 'Database update completed.' : 'Database update failed.')
            ->with('system_update_output', implode("\n\n", array_filter($output)));
    }

    public function updateSecurity(Request $request)
    {
        Setting::setValue('super_admin_2fa_enabled', $request->boolean('super_admin_2fa_enabled') ? '1' : '0', 1);

        $phone = preg_replace('/[^0-9]/', '', (string) $request->input('super_admin_phone', ''));
        if (strlen($phone) === 12 && str_starts_with($phone, '91')) {
            $phone = substr($phone, 2);
        }

        if ($request->boolean('super_admin_2fa_enabled')) {
            if (strlen($phone) !== 10 || !preg_match('/^[6-9][0-9]{9}$/', $phone)) {
                return redirect()->route('admin.settings.index')
                    ->with('error', 'Super Admin 2FA requires a valid 10-digit WhatsApp phone number.');
            }
        }

        $user = auth()->user();
        if ($user && $phone !== '') {
            $user->update(['phone_number' => $phone]);
        }

        return redirect()->route('admin.settings.index')->with('success', 'Security settings updated');
    }

    public function updateMaintenance(Request $request)
    {
        Setting::setValue('maintenance_enabled', $request->boolean('maintenance_enabled') ? '1' : '0', 1);
        Setting::setValue('maintenance_title', $request->input('maintenance_title', 'GymXBook is under maintenance'), 1);
        Setting::setValue('maintenance_message', $request->input('maintenance_message', 'We are upgrading GymXBook to serve you better. Please wait until maintenance is complete.'), 1);
        Setting::setValue('maintenance_start_at', PlatformMaintenance::normalizeInputDate($request->input('maintenance_start_at', '')), 1);
        Setting::setValue('maintenance_end_at', PlatformMaintenance::normalizeInputDate($request->input('maintenance_end_at', '')), 1);

        return redirect()->route('admin.settings.index')->with('success', 'Maintenance settings updated');
    }

    /**
     * Test SMTP connection
     */
    public function testSmtp(Request $request)
    {
        $request->validate([
            'test_email' => 'required|email|max:255',
        ]);

        $host = Setting::getValue('SERVER_HOST', 1, '');
        $port = (int) Setting::getValue('SERVER_PORT', 1, 587);
        $username = Setting::getValue('SERVER_USERNAME', 1, '');
        $password = Setting::getValue('SERVER_PASSWORD', 1, '');
        $driver = Setting::getValue('SERVER_DRIVER', 1, 'smtp') ?: 'smtp';
        $fromEmail = Setting::getValue('FROM_EMAIL', 1, $username ?: 'no-reply@gymxbook.com');
        $fromName = Setting::getValue('FROM_NAME', 1, 'GymXBook');
        $encryption = $port === 465 ? 'ssl' : ($port === 587 ? 'tls' : null);

        if ($driver === 'smtp' && ($host === '' || $port <= 0 || $fromEmail === '')) {
            return redirect()->route('admin.settings.index')->with('error', 'SMTP host, port and from email are required before testing.');
        }

        try {
            config([
                'mail.default' => $driver,
                'mail.mailers.smtp' => [
                    'transport' => 'smtp',
                    'host' => $host,
                    'port' => $port,
                    'encryption' => $encryption,
                    'username' => $username ?: null,
                    'password' => $password ?: null,
                    'timeout' => 20,
                    'auth_mode' => null,
                ],
                'mail.from.address' => $fromEmail,
                'mail.from.name' => $fromName,
            ]);

            \Illuminate\Support\Facades\Mail::raw(
                "SMTP test successful.\n\nThis email was sent from GymXBook Super Admin at " . now('Asia/Kolkata')->format('d M Y, h:i A') . '.',
                function ($message) use ($request, $fromEmail, $fromName) {
                    $message->from($fromEmail, $fromName)
                        ->to($request->input('test_email'))
                        ->subject('GymXBook SMTP Test');
                }
            );

            return redirect()->route('admin.settings.index')->with('success', 'SMTP test email sent successfully to ' . $request->input('test_email'));
        } catch (\Throwable $e) {
            return redirect()->route('admin.settings.index')->with('error', 'SMTP test failed: ' . $e->getMessage());
        }
    }

    /**
     * SUPERADMIN: Test WhatsApp API (Meta Cloud API)
     * Supports selecting real approved templates + parameters.
     */
    public function testWhatsApp(Request $request)
    {
        $request->validate([
            'test_phone' => 'required|string',
            'template'   => 'required|string',
        ]);

        $phone    = $request->input('test_phone');
        $template = $request->input('template');
        $pid      = 1; // superadmin

        $whatsapp = new WhatsAppService();

        if (!$whatsapp->isConfigured()) {
            return redirect()->route('admin.settings.index')
                ->with('error', 'WhatsApp is not configured. Check WHATSAPP_PHONE_NUMBER_ID and WHATSAPP_API_TOKEN in .env');
        }

        $result = [];

        switch ($template) {
            case 'gymxbook_member_welcome':
                $result = $whatsapp->sendMemberWelcome(
                    $phone,
                    $request->input('member_name', 'Test Member'),
                    $request->input('gym_name', 'Test Gym'),
                    $request->input('expiry', now()->addDays(30)->format('d-m-Y')),
                    $pid
                );
                break;

            case 'gymxbook_member_renew':
                $result = $whatsapp->sendMemberRenew(
                    $phone,
                    $request->input('member_name', 'Test Member'),
                    $request->input('gym_name', 'Test Gym'),
                    $request->input('expiry', now()->addDays(30)->format('d-m-Y')),
                    $request->input('amount', 999),
                    $pid
                );
                break;

            case 'gymxbook_member_expired':
                $result = $whatsapp->sendMemberExpired(
                    $phone,
                    $request->input('member_name', 'Test Member'),
                    $request->input('expiry', now()->subDays(5)->format('d-m-Y')),
                    $pid
                );
                break;

            case 'otp':
            case 'gymxbook_otp':
                // === EXACT MATCH FOR YOUR APPROVED TEMPLATE ===
                // Using configurable OTP template (WHATSAPP_OTP_TEMPLATE_NAME)
                // Supported: 'otp' (legacy) OR 'gymxbook_otp' (new approved template)
                //
                // Template details (from user):
                //   name: 'gymxbook_otp'  (or 'otp')
                //   description: 'OTP verification - {{1}} is your verification code'
                //   Has Copy Code button (coupon_code)
                //
                // We use the dedicated sendOtp() method (it uses $this->otpTemplateName).
                // This builds the EXACT payload: body + button.copy_code (index '0' as string)
                $otp = $request->input('otp_code') ?: $request->input('otp') ?: '123456';
                $result = $whatsapp->sendOtp($phone, $otp, $pid);
                break;

            default: // custom fallback
                $message = $request->input('test_message', 'Hello from GymXBook SuperAdmin!');
                $result = $whatsapp->sendTemplate(
                    $phone,
                    'custom',
                    'en',
                    [$message],
                    $pid,
                    'SuperAdmin Test'
                );
                break;
        }

        if (!empty($result['success'])) {
            return redirect()->route('admin.settings.index')->with('success', 
                '✅ Template "' . $template . '" sent successfully to ' . $phone . 
                ' (Message ID: ' . ($result['message_id'] ?? 'N/A') . ')'
            );
        }

        $error = $result['error'] ?? 'Unknown error';
        $fullMsg = '❌ WhatsApp test failed (' . $template . '): ' . $error;

        if (!empty($result['http_status'])) {
            $fullMsg .= ' (HTTP ' . $result['http_status'] . ')';
        }

        // Special hint for the very common 132001 error
        if (str_contains($error, '132001') || str_contains($error, 'Template name does not exist')) {
            $fullMsg .= "\n\n🔧 Most common cause: Template ('otp' or 'gymxbook_otp') is approved but for a DIFFERENT Phone Number ID than WHATSAPP_PHONE_NUMBER_ID in .env.\n";
            $fullMsg .= "→ In Meta Business Suite → WhatsApp → Message Templates, check the EXACT template name ('gymxbook_otp' preferred) and the Phone Number it is approved for.\n";
            $fullMsg .= "→ Update .env WHATSAPP_OTP_TEMPLATE_NAME=gymxbook_otp (or 'otp') + WHATSAPP_PHONE_NUMBER_ID=...\n";
            $fullMsg .= "→ Then run: php artisan config:clear\n";
        }

        return redirect()->route('admin.settings.index')->with('error', $fullMsg);
    }

    /**
     * SUPERADMIN: Quick connection/auth test for WhatsApp
     */
    public function testWhatsAppConnection()
    {
        $whatsapp = new WhatsAppService();

        if (!$whatsapp->isConfigured()) {
            return redirect()->route('admin.settings.index')
                ->with('error', 'WhatsApp is not configured in .env');
        }

        $result = $whatsapp->testConnection();

        if (!empty($result['success'])) {
            return redirect()->route('admin.settings.index')->with('success', 
                '✅ WhatsApp API Connection OK — Phone: ' . ($result['display_phone_number'] ?? $result['phone_number_id'])
            );
        }

        // Build rich error message with hints from service
        $msg = '❌ WhatsApp Connection Failed: ' . ($result['error'] ?? 'Unknown');

        if (!empty($result['hint'])) {
            $msg .= "\n\n💡 " . $result['hint'];
        }
        if (!empty($result['next_step'])) {
            $msg .= "\n\n➡️ " . $result['next_step'];
        }
        if (!empty($result['full_error'])) {
            $msg .= "\n\nRaw: " . json_encode($result['full_error']);
        }

        // Always append the standard fix for this common error
        $msg .= "\n\n🔧 **Standard Fix for this error:**\n";
        $msg .= "1. Go to https://business.facebook.com/settings/system-users\n";
        $msg .= "2. Select your app's System User\n";
        $msg .= "3. Click 'Generate New Token'\n";
        $msg .= "4. Choose 'Never expire'\n";
        $msg .= "5. Select the WhatsApp Business account permissions\n";
        $msg .= "6. Copy the new token\n";
        $msg .= "7. Update WHATSAPP_API_TOKEN in .env\n";
        $msg .= "8. Run: php artisan config:clear";

        return redirect()->route('admin.settings.index')->with('error', $msg);
    }

    /**
     * SUPERADMIN: Full WhatsApp diagnostic (calls diagnose())
     */
    public function diagnoseWhatsApp()
    {
        $whatsapp = new WhatsAppService();

        $result = $whatsapp->diagnose();

        $message = "WhatsApp Diagnosis:\n\n";
        $message .= "Configured: " . ($result['service']['isConfigured'] ? 'YES' : 'NO') . "\n";
        $message .= "Phone ID: " . ($result['service']['phoneNumberId'] ?? 'MISSING') . "\n";
        $message .= "Token length: " . ($result['service']['accessToken_length'] ?? 0) . "\n\n";

        if (!empty($result['connection_test'])) {
            $ct = $result['connection_test'];
            $message .= "Connection Test: " . ($ct['success'] ? 'SUCCESS' : 'FAILED') . "\n";
            if (!empty($ct['error'])) $message .= "Error: " . $ct['error'] . "\n";
            if (!empty($ct['hint'])) $message .= "Hint: " . $ct['hint'] . "\n";
        }

        return redirect()->route('admin.settings.index')->with('info', nl2br($message));
    }
}
