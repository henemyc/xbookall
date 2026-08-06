<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Models\User;
use App\Models\Subscription;
use App\Models\SubscriptionOrder;
use App\Models\Setting;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AdminAuthController extends BaseController
{
    /**
     * Admin login page
     */
    public function showLogin()
    {
        return view('admin.login');
    }

    /**
     * Admin login - type must be 'super_admin'
     * AJAX supported + clean error handling (no rate limiting / account lock)
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        // Secure approach: Never reveal account type or whether email exists.
        // Only allow super_admin type.
        $user = User::where('email', $request->email)->first();

        $isValidSuperAdmin = $user 
            && Hash::check($request->password, $user->password) 
            && $user->type === 'super_admin';

        if (!$isValidSuperAdmin) {
            if ($this->isAjaxRequest($request)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid email or password.'
                ], 401);
            }

            return back()->withErrors(['email' => 'Invalid email or password.'])->withInput();
        }

        // Super Admin WhatsApp 2FA (optional, controlled from Super Admin settings)
        if ($this->isTwoFactorEnabled()) {
            $otpResult = $this->sendTwoFactorOtp($request, $user);
            if (!$otpResult['success']) {
                if ($this->isAjaxRequest($request)) {
                    return response()->json([
                        'success' => false,
                        'error' => $otpResult['error'] ?? 'Could not send verification OTP.'
                    ], 400);
                }
                return back()->withErrors(['email' => $otpResult['error'] ?? 'Could not send verification OTP.'])->withInput();
            }

            if ($this->isAjaxRequest($request)) {
                return response()->json([
                    'success' => true,
                    'requires_2fa' => true,
                    'message' => 'Verification OTP sent to WhatsApp.',
                ]);
            }

            return back()->with('info', 'Verification OTP sent to WhatsApp. Please enter the OTP.')->withInput();
        }

        return $this->completeLogin($request, $user);
    }

    public function verifyTwoFactor(Request $request)
    {
        $otp = trim((string) $request->input('otp', ''));
        if (!preg_match('/^[0-9]{6}$/', $otp)) {
            return $this->twoFactorError($request, 'Enter the 6-digit OTP.', 422);
        }

        $adminId = (int) session('admin_2fa_user_id', 0);
        $hash = session('admin_2fa_hash');
        $expiresAt = session('admin_2fa_expires_at');
        $attempts = (int) session('admin_2fa_attempts', 0);

        if (!$adminId || !$hash || !$expiresAt) {
            return $this->twoFactorError($request, 'OTP session expired. Please login again.', 401);
        }

        if (now()->greaterThan(\Carbon\Carbon::parse($expiresAt))) {
            $this->clearTwoFactorSession();
            return $this->twoFactorError($request, 'OTP expired. Please login again.', 401);
        }

        if ($attempts >= 5) {
            $this->clearTwoFactorSession();
            return $this->twoFactorError($request, 'Too many OTP attempts. Please login again.', 429);
        }

        session(['admin_2fa_attempts' => $attempts + 1]);

        if (!Hash::check($otp, $hash)) {
            return $this->twoFactorError($request, 'Invalid OTP.', 401);
        }

        $user = User::where('id', $adminId)->where('type', 'super_admin')->first();
        if (!$user) {
            $this->clearTwoFactorSession();
            return $this->twoFactorError($request, 'Admin account not found. Please login again.', 401);
        }

        $this->clearTwoFactorSession();
        return $this->completeLogin($request, $user);
    }

    private function completeLogin(Request $request, User $user)
    {
        Auth::login($user);
        $request->session()->regenerate();

        try {
            if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'last_login_at')) {
                $updates = ['last_login_at' => now('Asia/Kolkata')];
                if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'last_login_ip')) $updates['last_login_ip'] = $request->ip();
                if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'last_login_user_agent')) $updates['last_login_user_agent'] = substr((string) $request->userAgent(), 0, 1000);
                $user->forceFill($updates)->save();
            }
        } catch (\Throwable $e) {}

        if ($this->isAjaxRequest($request)) {
            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'redirect' => route('admin.dashboard')
            ]);
        }

        return redirect()->route('admin.dashboard');
    }

    private function isTwoFactorEnabled(): bool
    {
        return Setting::getValue('super_admin_2fa_enabled', 1, '0') === '1';
    }

    private function sendTwoFactorOtp(Request $request, User $user): array
    {
        $phone = trim((string) $user->phone_number);
        if ($phone === '') {
            return ['success' => false, 'error' => 'Super Admin phone number is missing. Add phone number before enabling 2FA.'];
        }

        $otp = (string) random_int(100000, 999999);
        session([
            'admin_2fa_user_id' => $user->id,
            'admin_2fa_hash' => Hash::make($otp),
            'admin_2fa_expires_at' => now()->addMinutes(5)->toDateTimeString(),
            'admin_2fa_attempts' => 0,
        ]);

        try {
            $whatsapp = new WhatsAppService();
            if (!$whatsapp->isConfigured()) {
                $this->clearTwoFactorSession();
                return ['success' => false, 'error' => 'WhatsApp is not configured.'];
            }

            $result = $whatsapp->sendOtp($phone, $otp, 1);
            if (!empty($result['success'])) {
                return ['success' => true];
            }

            $this->clearTwoFactorSession();
            return ['success' => false, 'error' => 'Failed to send WhatsApp OTP: ' . ($result['error'] ?? 'Unknown error')];
        } catch (\Throwable $e) {
            $this->clearTwoFactorSession();
            return ['success' => false, 'error' => 'Failed to send WhatsApp OTP: ' . $e->getMessage()];
        }
    }

    private function clearTwoFactorSession(): void
    {
        session()->forget(['admin_2fa_user_id', 'admin_2fa_hash', 'admin_2fa_expires_at', 'admin_2fa_attempts']);
    }

    private function twoFactorError(Request $request, string $message, int $status)
    {
        if ($this->isAjaxRequest($request)) {
            return response()->json(['success' => false, 'error' => $message], $status);
        }
        return back()->withErrors(['otp' => $message])->withInput();
    }

    private function maskPhone(?string $phone): string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        if (strlen($digits) <= 4) return '******';
        return str_repeat('*', max(0, strlen($digits) - 4)) . substr($digits, -4);
    }

    /**
     * Admin dashboard
     */
    public function dashboard()
    {
        $today = now('Asia/Kolkata')->toDateString();
        $monthStart = now('Asia/Kolkata')->startOfMonth();

        $stats = [
            'total_gyms' => User::where('type', 'admin')->count(),
            'new_gyms_month' => User::where('type', 'admin')
                ->whereMonth('created_at', $monthStart->month)
                ->whereYear('created_at', $monthStart->year)
                ->count(),
            'active_gyms' => User::where('type', 'admin')->where('is_active', true)->count(),
            'expired_gyms' => User::where('type', 'admin')
                ->whereNotNull('subscription_expire_date')
                ->whereDate('subscription_expire_date', '<', $today)
                ->count(),
            'total_members' => User::where('type', 'trainee')->count(),
            'total_trainers' => User::where('type', 'trainer')->count(),
            'paid_orders' => SubscriptionOrder::where('status', 'PAID')->count(),
            'total_revenue' => SubscriptionOrder::where('status', 'PAID')->sum('amount'),
        ];

        $recentGyms = User::where('type', 'admin')
            ->with(['subscriptionPlan', 'subscriptionTier'])
            ->orderByRaw('CASE WHEN COALESCE(last_app_opened_at, last_login_at) IS NULL THEN 1 ELSE 0 END')
            ->orderByRaw('COALESCE(last_app_opened_at, last_login_at) DESC')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->each(function ($gym) {
                $gym->business_name = Setting::getValue('company_name', $gym->id, $gym->name);
            });

        $recentPayments = SubscriptionOrder::where('status', 'PAID')
            ->with(['parent', 'plan'])
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get()
            ->each(function ($payment) {
                if ($payment->parent) {
                    $payment->gym_business_name = Setting::getValue('company_name', $payment->parent->id, $payment->parent->name);
                } else {
                    $payment->gym_business_name = 'Unknown Gym';
                }
            });

        $monthlyGyms = collect(range(5, 0))->map(function ($i) {
            $date = now('Asia/Kolkata')->startOfMonth()->subMonths($i);
            return [
                'label' => $date->format('M'),
                'date' => $date->format('Y-m'),
                'count' => User::where('type', 'admin')
                    ->whereMonth('created_at', $date->month)
                    ->whereYear('created_at', $date->year)
                    ->count(),
            ];
        })->values();

        $dailyGyms = collect(range(29, 0))->map(function ($i) {
            $date = now('Asia/Kolkata')->startOfDay()->subDays($i);
            return [
                'label' => $date->format('d M'),
                'date' => $date->format('Y-m-d'),
                'count' => User::where('type', 'admin')
                    ->whereDate('created_at', $date->toDateString())
                    ->count(),
            ];
        })->values();

        return view('admin.dashboard', compact('stats', 'recentGyms', 'recentPayments', 'monthlyGyms', 'dailyGyms'));
    }

    /**
     * Admin logout
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    /**
     * Helper to detect AJAX requests
     */
    private function isAjaxRequest(Request $request): bool
    {
        return $request->ajax() ||
               $request->wantsJson() ||
               $request->header('X-Requested-With') === 'XMLHttpRequest';
    }
}
