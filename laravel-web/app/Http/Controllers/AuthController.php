<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\OtpVerification;
use App\Models\Subscription;
use App\Models\Setting;
use App\Models\Membership;
use App\Models\SubscriptionTier;
use App\Models\Category;
use App\Models\Locker;
use App\Services\WhatsAppService;
use App\Services\PhoneIdentityService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;

class AuthController extends BaseController
{
    /**
     * Login - Email/Phone + Password
     * For mobile app: type = admin (gym owner)
     */
    public function login(Request $request): JsonResponse
    {
        $login = trim($request->input('email', ''));
        $password = $request->input('password', '');

        if (!$login || !$password) {
            return $this->error('Phone and password required', 400);
        }

        // Production login is phone + password only for gym owner, staff,
        // trainer and member app users. Keep the request key as `email` for
        // backward Flutter compatibility, but validate/use only phone digits.
        $loginDigits = preg_replace('/[^0-9]/', '', $login);
        if (strlen($loginDigits) === 12 && substr($loginDigits, 0, 2) === '91') {
            $loginDigits = substr($loginDigits, 2);
        }
        if (strlen($loginDigits) !== 10 || !preg_match('/^[6-9][0-9]{9}$/', $loginDigits)) {
            return $this->error('Enter a valid 10-digit phone number', 400);
        }

        // Strict account matching: compare the normalized last 10 digits, but
        // never score/guess between migrated duplicate rows. Selecting another
        // member just because a record has more data is a security violation.
        $candidates = User::where('is_active', true)
            ->whereIn('type', ['admin', 'owner', 'trainee', 'trainer', 'staff'])
            ->whereRaw("RIGHT(REPLACE(REPLACE(REPLACE(phone_number, '+', ''), ' ', ''), '-', ''), 10) = ?", [$loginDigits])
            ->orderBy('id')
            ->get();

        $validCandidates = collect();
        foreach ($candidates as $candidate) {
            $valid = Hash::check($password, $candidate->password);
            if (!$valid && md5($password) === $candidate->password) {
                $valid = true;
            }
            if ($valid) $validCandidates->push($candidate);
        }

        if ($validCandidates->isEmpty()) {
            return $this->error('Invalid phone or password', 401);
        }
        if ($validCandidates->count() > 1) {
            \Log::critical('Ambiguous login blocked: duplicate normalized phone and password match', [
                'phone_last4' => substr($loginDigits, -4),
                'user_ids' => $validCandidates->pluck('id')->all(),
            ]);
            return $this->error('Multiple accounts match this phone number. Please contact support.', 409);
        }

        $user = $validCandidates->first();

        if ($user->type === 'trainer' && !\App\Services\SubscriptionFeatureService::enabled((int) ($user->parent_id ?: 0), 'trainers_enabled', true)) {
            return $this->error(\App\Services\SubscriptionFeatureService::featureLockedMessage('Trainer app login'), 402);
        }
        if ($user->type === 'staff' && !\App\Services\SubscriptionFeatureService::enabled((int) ($user->parent_id ?: 0), 'staff_enabled', true)) {
            return $this->error(\App\Services\SubscriptionFeatureService::featureLockedMessage('Staff login'), 402);
        }

        $this->markLoggedIn($request, $user);

        // Staff subscription follows their gym owner account.
        $subscriptionUser = $user;
        if ($user->type === 'staff' && $user->parent_id) {
            $subscriptionUser = User::find($user->parent_id) ?: $user;
        }

        // Check subscription expiry (for gym owners/staff; for trainees we attach membership info)
        $subscriptionExpired = false;
        $subscriptionExpiringSoon = false;
        $subscriptionDaysLeft = null;

        if ($subscriptionUser->subscription_expire_date) {
            $daysLeft = (int) now()->diffInDays($subscriptionUser->subscription_expire_date, false);
            $subscriptionDaysLeft = $daysLeft;
            if ($daysLeft < 0) {
                $subscriptionExpired = true;
            } elseif ($daysLeft <= 7) {
                $subscriptionExpiringSoon = true;
            }
        }

        // Generate API token (Sanctum) - return both keys for full Flutter compatibility.
        // Some servers missed the Sanctum migration; ensure the table exists so
        // login never fails with "personal_access_tokens table not found".
        $this->ensureSanctumTokenTable();
        $token = $user->createToken('gymxbook-app')->plainTextToken;

        // Get subscription info
        $subscription = Subscription::find($subscriptionUser->subscription);

        $gymOwnerIdForLogin = in_array($user->type, ['admin', 'owner'])
            ? $this->resolveGymOwnerIdForLegacyDuplicates($user)
            : (int) ($user->parent_id ?: 0);
        $gymInfo = [
            'name' => Setting::getValue('company_name', $gymOwnerIdForLogin, $user->name ?? 'GymXBook'),
            'phone' => Setting::getValue('company_phone', $gymOwnerIdForLogin, ''),
            'email' => Setting::getValue('company_email', $gymOwnerIdForLogin, ''),
            'address' => Setting::getValue('company_address', $gymOwnerIdForLogin, ''),
            'owner_id' => $gymOwnerIdForLogin,
            'auth_user_id' => (int) $user->id,
            'auth_user_type' => $user->type,
        ];
        $planPayload = $this->currentPlanPayload($gymOwnerIdForLogin);

        // Remove sensitive data
        $userData = $user->toArray();
        unset($userData['password'], $userData['remember_token'], $userData['twofa_secret']);
        $userData['company_name'] = $gymInfo['name'];
        $userData['gym_owner_id'] = $gymOwnerIdForLogin;

        // Attach role-specific details for Flutter dashboards
        $extra = [];
        if ($user->type === 'trainee') {
            $traineeDetail = $user->traineeDetails()->with('membership')->first();
            if ($traineeDetail) {
                $extra['trainee_detail'] = $traineeDetail;
                $extra['membership'] = $traineeDetail->membership;
                $extra['membership_expiry'] = $traineeDetail->membership_expiry_date;
            }
        }
        if ($user->type === 'trainer') {
            $trainerDetail = $user->trainerDetails()->first();
            if ($trainerDetail) {
                $extra['trainer_detail'] = $trainerDetail;
                $extra['qualification'] = $trainerDetail->qualification;
                $extra['specialization'] = $trainerDetail->specialization ?? null;
                $extra['experience_years'] = $trainerDetail->experience_years ?? null;
            }
        }
        if ($user->type === 'staff') {
            $user->load('staffRole.permissions');
            $extra['staff_role'] = $user->staffRole;
            $extra['permissions'] = $user->staffPermissionKeys();
            $extra['gym_owner_id'] = (int) ($user->parent_id ?: 0);
        }

        return $this->success(array_merge([
            'user' => $userData,
            'token' => $token,           // primary for Flutter ApiClient
            'api_token' => $token,       // legacy key
            'access_token' => $token,    // extra safety
            'subscription' => $subscription,
            'gym_info' => $gymInfo,
            'current_tier' => $planPayload['current_tier'],
            'plan_features' => $planPayload['plan_features'],
            'subscription_expired' => $subscriptionExpired,
            'subscription_expiring_soon' => $subscriptionExpiringSoon,
            'subscription_days_left' => $subscriptionDaysLeft,
            'user_type' => $user->type,
        ], $extra));
    }

    /**
     * Register - Create new gym account
     */
    public function register(Request $request): JsonResponse
    {
        $businessName = trim($request->input('business_name', ''));
        $personalName = trim($request->input('name', ''));
        $email = trim($request->input('email', ''));
        $phone = trim($request->input('phone_number', ''));
        $password = $request->input('password', '');
        $source = trim($request->input('acquisition_source', ''));
        $sourceDetail = trim($request->input('acquisition_detail', ''));
        $address = trim($request->input('address', ''));
        $city = trim($request->input('city', ''));

        if (!$businessName) return $this->error('Business name is required', 400);
        if (!$personalName) return $this->error('Your name is required', 400);
        if (!$password || strlen($password) < 6) return $this->error('Password must be at least 6 characters', 400);
        if (!in_array($source, ['google_search', 'play_store', 'social_media', 'youtube', 'chatgpt_ai', 'referral', 'sales_team', 'other'], true)) {
            return $this->error('Please select how you discovered GymXBook', 400);
        }

        // Email is optional because the production account identity is phone.
        // A private placeholder keeps the users.email unique constraint intact.
        if ($email !== '' && User::where('email', $email)->exists()) {
            return $this->error('An account with this email already exists', 400);
        }

        // Validate phone
        $phoneDigits = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phoneDigits) == 12 && substr($phoneDigits, 0, 2) == '91') {
            $phoneDigits = substr($phoneDigits, 2);
        }
        if (strlen($phoneDigits) != 10 || !preg_match('/^[6-9][0-9]{9}$/', $phoneDigits)) {
            return $this->error('Phone must be exactly 10 digits, starting with 6-9', 400);
        }
        if ($email === '') {
            $email = 'gym_' . $phoneDigits . '@gymxbook.temp';
        }

        // Option 1 policy: this phone can belong to only one account globally.
        $phoneIdentity = app(PhoneIdentityService::class);
        if (!$phoneIdentity->isAvailable($phoneDigits)) {
            return $this->error(PhoneIdentityService::DUPLICATE_MESSAGE, 400);
        }

        // Verify OTP (required for registration)
        $otpCheck = OtpVerification::where('identifier', $phoneDigits)
            ->where('verified', true)
            ->where('created_at', '>', now()->subMinutes(10))
            ->first();

        if (!$otpCheck) {
            return $this->error('Phone number not verified via WhatsApp OTP. Please verify OTP first.', 400);
        }

        $verifyToken = Str::random(64);

        DB::beginTransaction();
        try {
            // Create admin user (type = admin for gym owner)
            // Gym owner belongs to Super Admin (user id 1), but gym data uses the new owner id
            $user = User::create([
                'name' => $personalName,
                'email' => $email,
                'phone_number' => $phoneDigits,
                'type' => 'admin',
                'password' => Hash::make($password),
                'parent_id' => 1,
                'is_active' => true,
                'email_verification_token' => $verifyToken,
                'acquisition_source' => $source,
                'acquisition_detail' => $sourceDetail !== '' ? $sourceDetail : null,
            ]);

            // Save business name (using the new user's id for settings)
            Setting::setValue('company_name', $businessName, $user->id);
            if ($phoneDigits) Setting::setValue('company_phone', $phoneDigits, $user->id);
            if (!str_ends_with($email, '@gymxbook.temp')) Setting::setValue('company_email', $email, $user->id);
            if ($address !== '' || $city !== '') Setting::setValue('company_address', trim($address . ($address !== '' && $city !== '' ? ', ' : '') . $city), $user->id);

            // All default gym data must use the new gym owner id, not Super Admin id 1.
            $gymParentId = (int) $user->id;

            // Do not seed membership plans. Every new gym starts with an
            // empty Plans module and creates plans according to its own pricing.

            // Create default categories
            foreach (['General', 'VIP'] as $cat) {
                Category::create(['title' => $cat, 'parent_id' => $gymParentId]);
            }

            // Create default lockers
            for ($i = 1; $i <= 20; $i++) {
                Locker::create(['parent_id' => $gymParentId, 'status' => 1, 'available' => true]);
            }

            // Assign 7-day free trial + Bronze tier restrictions for new gyms.
            $trialExpiry = now()->addDays(7);
            $trialPlan = Subscription::where('interval', 'weekly')->first();
            $updateSubscription = [
                'subscription_expire_date' => $trialExpiry->toDateString(),
            ];
            if ($trialPlan) {
                $updateSubscription['subscription'] = $trialPlan->id;
            }
            if (Schema::hasTable('subscription_tiers') && Schema::hasColumn('users', 'subscription_tier_id')) {
                $bronze = SubscriptionTier::where('code', 'bronze')->first();
                if ($bronze) {
                    $updateSubscription['subscription_tier_id'] = $bronze->id;
                    $updateSubscription['subscription_status'] = 'trial';
                    $updateSubscription['subscription_started_at'] = now('Asia/Kolkata');
                    $updateSubscription['subscription_ends_at'] = $trialExpiry->endOfDay();
                }
            }
            $user->update($updateSubscription);

            DB::commit();

            // Gym registration intentionally does not send the member welcome
            // WhatsApp template. Member welcome messages are sent only when a
            // Gym Owner creates a trainee/member account.

            $this->ensureSanctumTokenTable();
            $token = $user->createToken('gymxbook-app')->plainTextToken;
            $subscription = Subscription::find($user->subscription);

            $userData = $user->toArray();
            unset($userData['password'], $userData['remember_token'], $userData['twofa_secret']);

            return $this->success([
                'user' => $userData,
                'token' => $token,
                'api_token' => $token,
                'access_token' => $token,
                'subscription' => $subscription,
                'user_type' => $user->type,
                'subscription_expired' => false,
                'subscription_expiring_soon' => true,
                'subscription_days_left' => $user->subscription_expire_date
                    ? (int) now()->diffInDays($user->subscription_expire_date, false)
                    : null,
                'message' => 'Registration successful',
            ], 'Registration successful', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Registration failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Logout
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user) {
            $user->tokens()->delete();
        }
        return $this->success([], 'Logged out successfully');
    }

    /**
     * Get current user info
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->error('User not found', 401);
        }

        $pid = $this->getParentId();
        $subscriptionUser = $user;
        if ($user->type === 'staff' && $user->parent_id) {
            $subscriptionUser = User::find($user->parent_id) ?: $user;
        }

        // Get subscription info
        $subscription = Subscription::find($subscriptionUser->subscription);

        $subscriptionExpired = false;
        $subscriptionExpiringSoon = false;
        $subscriptionDaysLeft = null;

        if ($subscriptionUser->subscription_expire_date) {
            $daysLeft = (int) now()->diffInDays($subscriptionUser->subscription_expire_date, false);
            $subscriptionDaysLeft = $daysLeft;
            if ($daysLeft < 0) {
                $subscriptionExpired = true;
            } elseif ($daysLeft <= 7) {
                $subscriptionExpiringSoon = true;
            }
        }

        // Get gym info (for admin) or basic info for member
        $gymInfo = [
            'name' => Setting::getValue('company_name', $pid, 'GymXBook'),
            'phone' => Setting::getValue('company_phone', $pid, ''),
            'email' => Setting::getValue('company_email', $pid, ''),
            'address' => Setting::getValue('company_address', $pid, ''),
            'owner_id' => $pid,
            'auth_user_id' => (int) $user->id,
            'auth_user_type' => $user->type,
        ];
        $planPayload = $this->currentPlanPayload($pid);

        $userData = $user->toArray();
        unset($userData['password'], $userData['remember_token'], $userData['twofa_secret']);
        $userData['company_name'] = $gymInfo['name'] ?? ($userData['name'] ?? '');
        $userData['gym_owner_id'] = $pid;

        $extra = [
            'user_type' => $user->type,
            'gym_info' => $gymInfo,
            'current_tier' => $planPayload['current_tier'],
            'plan_features' => $planPayload['plan_features'],
        ];

        // For trainee (member) — attach rich membership data so dashboard shows real info
        if ($user->type === 'trainee') {
            $traineeDetail = $user->traineeDetails()->with('membership')->first();
            if ($traineeDetail) {
                $extra['trainee_detail'] = $traineeDetail;
                $extra['membership'] = $traineeDetail->membership;

                // Flatten common fields the member dashboard expects
                $extra['plan_name'] = $traineeDetail->membership ? $traineeDetail->membership->title : null;
                $extra['membership_start_date'] = $traineeDetail->membership_start_date;
                $extra['membership_expiry_date'] = $traineeDetail->membership_expiry_date;
                $extra['fitness_goal'] = $traineeDetail->fitness_goal;
                $extra['trainee_status'] = $traineeDetail->status;
            }
        }

        // For trainer — attach trainer profile fields for Trainer panel.
        if ($user->type === 'trainer') {
            $trainerDetail = $user->trainerDetails()->first();
            if ($trainerDetail) {
                $extra['trainer_detail'] = $trainerDetail;
                $extra['qualification'] = $trainerDetail->qualification;
                $extra['specialization'] = $trainerDetail->specialization ?? null;
                $extra['experience_years'] = $trainerDetail->experience_years ?? null;
            }
        }

        if ($user->type === 'staff') {
            $user->load('staffRole.permissions');
            $extra['staff_role'] = $user->staffRole;
            $extra['permissions'] = $user->staffPermissionKeys();
            $extra['gym_owner_id'] = (int) ($user->parent_id ?: 0);
        }

        return $this->success(array_merge([
            'user' => $userData,
            'subscription' => $subscription,
            'subscription_expired' => $subscriptionExpired,
            'subscription_expiring_soon' => $subscriptionExpiringSoon,
            'subscription_days_left' => $subscriptionDaysLeft,
        ], $extra));
    }

    /**
     * Update profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $name = trim($request->input('name', ''));
        $email = trim($request->input('email', ''));
        $phone = trim($request->input('phone_number', ''));

        if (!$name || !$email) {
            return $this->error('Name and email are required', 400);
        }

        $existing = User::where('email', $email)->where('id', '!=', $user->id)->first();
        if ($existing) {
            return $this->error('Email already in use', 400);
        }

        $digits = $this->validatePhone($phone);
        if (is_string($digits)) {
            return $this->error($digits, 400);
        }
        $digits = (string) $digits;
        $currentDigits = preg_replace('/[^0-9]/', '', (string) $user->phone_number);
        if (strlen($currentDigits) === 12 && str_starts_with($currentDigits, '91')) {
            $currentDigits = substr($currentDigits, 2);
        }

        // A changed login phone is security-sensitive. It must be a unique
        // number and have a recent WhatsApp OTP verification before saving.
        if ($digits !== $currentDigits) {
            $phoneIdentity = app(PhoneIdentityService::class);
            if (!$phoneIdentity->isAvailable($digits, (int) $user->id)) {
                return $this->error(PhoneIdentityService::DUPLICATE_MESSAGE, 400);
            }

            $verified = OtpVerification::where('identifier', $digits)
                ->where('verified', true)
                ->where('created_at', '>', now()->subMinutes(10))
                ->exists();
            if (!$verified) {
                return $this->error('Verify the new phone number with OTP before updating your profile.', 400);
            }
        }

        $user->update([
            'name' => $name,
            'email' => $email,
            'phone_number' => $digits,
        ]);

        return $this->success([], 'Profile updated successfully');
    }

    /**
     * Send a WhatsApp OTP to an existing, unambiguous account for passwordless login.
     */
    public function sendLoginOtp(Request $request): JsonResponse
    {
        $digits = $this->validatePhone(trim((string) $request->input('phone', '')));
        if (is_string($digits)) return $this->error($digits, 400);
        $digits = (string) $digits;

        $users = User::where('is_active', true)
            ->whereIn('type', ['admin', 'owner', 'trainee', 'trainer', 'staff'])
            ->whereRaw("RIGHT(REPLACE(REPLACE(REPLACE(phone_number, '+', ''), ' ', ''), '-', ''), 10) = ?", [$digits])
            ->get();
        if ($users->count() !== 1) {
            return $this->error('Unable to send login OTP. Please use password login or contact support.', 400);
        }

        $recent = OtpVerification::where('identifier', $digits)->where('channel', 'login')->where('created_at', '>', now()->subMinutes(15))->count();
        if ($recent >= 3) return $this->error('Too many OTP requests. Please wait 15 minutes.', 429);

        $otp = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        OtpVerification::where('identifier', $digits)->where('channel', 'login')->where('verified', false)->delete();
        OtpVerification::create([
            'identifier' => $digits,
            'otp_hash' => Hash::make($otp),
            'otp_plain' => $otp,
            'channel' => 'login',
            'expires_at' => now()->addMinutes(10),
            'verified' => false,
            'attempts' => 0,
        ]);

        try {
            $user = $users->first();
            $whatsapp = new WhatsAppService();
            $result = $whatsapp->sendOtp($digits, $otp, $user->gymId());
            if (empty($result['success'])) {
                OtpVerification::where('identifier', $digits)->where('channel', 'login')->where('otp_plain', $otp)->delete();
                return $this->error('Failed to send WhatsApp OTP', 400);
            }
        } catch (\Throwable $e) {
            OtpVerification::where('identifier', $digits)->where('channel', 'login')->where('otp_plain', $otp)->delete();
            \Log::warning('Login OTP send failed', ['error' => $e->getMessage()]);
            return $this->error('Failed to send WhatsApp OTP', 500);
        }

        return $this->success(['expires_in' => 600], 'OTP sent to WhatsApp');
    }

    /** Verify a login-only OTP and issue a normal Sanctum app token. */
    public function verifyLoginOtp(Request $request): JsonResponse
    {
        $digits = $this->validatePhone(trim((string) $request->input('phone', '')));
        $otp = trim((string) $request->input('otp', ''));
        if (is_string($digits)) return $this->error($digits, 400);
        $digits = (string) $digits;
        if (!preg_match('/^[0-9]{6}$/', $otp)) return $this->error('OTP must be 6 digits', 400);

        $record = OtpVerification::where('identifier', $digits)->where('channel', 'login')->where('expires_at', '>', now())->latest()->first();
        if (!$record || $record->verified) return $this->error('OTP expired or not found. Please request a new OTP.', 400);
        if ($record->attempts >= 5) { $record->delete(); return $this->error('Too many wrong attempts. Request a new OTP.', 400); }

        $valid = Hash::check($otp, $record->otp_hash) || $record->otp_plain === $otp;
        if (!$valid) { $record->increment('attempts'); return $this->error('Invalid OTP. Please try again.', 400); }

        $users = User::where('is_active', true)
            ->whereIn('type', ['admin', 'owner', 'trainee', 'trainer', 'staff'])
            ->whereRaw("RIGHT(REPLACE(REPLACE(REPLACE(phone_number, '+', ''), ' ', ''), '-', ''), 10) = ?", [$digits])
            ->get();
        if ($users->count() !== 1) return $this->error('Unable to verify this account. Please contact support.', 409);

        $user = $users->first();
        $record->update(['verified' => true, 'attempts' => $record->attempts + 1]);
        $this->ensureSanctumTokenTable();
        $this->markLoggedIn($request, $user);
        $token = $user->createToken('gymxbook-app')->plainTextToken;
        $userData = $user->toArray();
        unset($userData['password'], $userData['remember_token'], $userData['twofa_secret']);

        return $this->success([
            'token' => $token,
            'api_token' => $token,
            'access_token' => $token,
            'user' => $userData,
            'user_type' => $user->type,
        ], 'Login successful');
    }

    /**
     * Change password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentPassword = $request->input('current_password', '');
        $newPassword = $request->input('new_password', '');

        if (!$currentPassword || !$newPassword) {
            return $this->error('Both current and new password required', 400);
        }

        if (strlen($newPassword) < 6) {
            return $this->error('New password must be at least 6 characters', 400);
        }

        if (!Hash::check($currentPassword, $user->password)) {
            return $this->error('Current password is incorrect', 400);
        }

        if (Schema::hasColumn('users', 'password_changed_at')) {
            $user->update(['password' => Hash::make($newPassword), 'password_changed_at' => now('Asia/Kolkata')]);
        } else {
            $user->update(['password' => Hash::make($newPassword)]);
        }

        return $this->success([], 'Password changed successfully');
    }

    /**
     * Send OTP via WhatsApp (using Meta Cloud API + approved templates)
     * 
     * IMPORTANT: Uses dedicated sendOtp() which respects 
     * WHATSAPP_OTP_TEMPLATE_NAME (currently 'gymxbook_otp')
     * 
     * Payload follows working PWA flow (body + copy_code button)
     * Used by: Phone verification during Gym Registration
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $phone = trim($request->input('phone', ''));

        $digits = $this->validatePhone($phone);
        if (is_string($digits)) {
            return $this->error($digits, 400);
        }
        $digits = (string) $digits;

        // Registration phone must be globally unused across every role/gym.
        if (!app(PhoneIdentityService::class)->isAvailable($digits)) {
            return $this->error('This phone number is already registered. Please login instead.', 400);
        }

        $recentCount = OtpVerification::where('identifier', $digits)
            ->where('created_at', '>', now()->subMinutes(15))
            ->count();

        if ($recentCount >= 3) {
            return $this->error('Too many OTP requests. Please wait 15 minutes.', 429);
        }

        $otp = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

        OtpVerification::where('identifier', $digits)->where('verified', false)->delete();

        OtpVerification::create([
            'identifier' => $digits,
            'otp_hash' => Hash::make($otp),
            'otp_plain' => $otp,
            'channel' => 'whatsapp',
            'expires_at' => now()->addMinutes(10),
            'verified' => false,
            'attempts' => 0,
        ]);

        // Send via WhatsApp exactly like the working PWA/api.php flow.
        try {
            $whatsapp = new WhatsAppService();
            if (!$whatsapp->isConfigured()) {
                OtpVerification::where('identifier', $digits)->where('otp_plain', $otp)->delete();
                return $this->error('WhatsApp not configured', 500);
            }

            $result = $whatsapp->sendOtp($digits, $otp, 0);

            if (!empty($result['success'])) {
                return $this->success([
                    'message' => 'OTP sent to WhatsApp',
                    'expires_in' => 300,
                    'to' => $digits,
                    'template' => env('WHATSAPP_OTP_TEMPLATE_NAME', 'gymxbook_otp'),
                    'channel' => 'whatsapp',
                ]);
            }

            OtpVerification::where('identifier', $digits)->where('otp_plain', $otp)->delete();
            return $this->error('Failed to send WhatsApp OTP: ' . ($result['error'] ?? 'Unknown'), 400, $result['response'] ?? null);
        } catch (\Exception $e) {
            OtpVerification::where('identifier', $digits)->where('otp_plain', $otp)->delete();
            \Log::warning('Send OTP failed: ' . $e->getMessage());
            return $this->error('Failed to send OTP: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Verify OTP
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $phone = trim($request->input('phone', ''));
        $otp = trim($request->input('otp', ''));

        $digits = $this->validatePhone($phone);
        if (is_string($digits)) {
            return $this->error($digits, 400);
        }

        if (!preg_match('/^[0-9]{6}$/', $otp)) {
            return $this->error('OTP must be 6 digits', 400);
        }

        $otpRecord = OtpVerification::where('identifier', $digits)
            ->where('expires_at', '>', now())
            ->orderBy('verified', 'desc')
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$otpRecord) {
            $verified = OtpVerification::where('identifier', $digits)
                ->where('verified', true)
                ->where('created_at', '>', now()->subMinutes(10))
                ->first();

            if ($verified) {
                return $this->success(['message' => 'Phone already verified', 'phone' => $digits, 'already_verified' => true]);
            }

            return $this->error('OTP expired or not found. Please request new OTP.', 400);
        }

        if ($otpRecord->verified) {
            return $this->success(['message' => 'Phone already verified', 'phone' => $digits, 'already_verified' => true]);
        }

        if ($otpRecord->attempts >= 5) {
            $otpRecord->delete();
            return $this->error('Too many wrong attempts. Request new OTP.', 400);
        }

        $valid = Hash::check($otp, $otpRecord->otp_hash);
        if (!$valid && $otpRecord->otp_plain === $otp) {
            $valid = true;
        }

        if ($valid) {
            $otpRecord->update(['verified' => true, 'attempts' => $otpRecord->attempts + 1]);
            return $this->success(['message' => 'Phone verified successfully', 'phone' => $digits]);
        } else {
            $otpRecord->increment('attempts');
            return $this->error('Invalid OTP. Please try again.', 400);
        }
    }

    /**
     * Forgot Password - Send OTP
     */
    public function forgotPasswordSendOtp(Request $request): JsonResponse
    {
        $phone = trim($request->input('phone', ''));
        $digits = $this->validatePhone($phone);
        if (is_string($digits)) return $this->error($digits, 400);

        $matches = app(PhoneIdentityService::class)
            ->matchingUsers((string) $digits)
            ->where('is_active', true)
            ->get();
        if ($matches->isEmpty()) {
            return $this->error('Phone number not found. No account associated with this number.', 404);
        }
        if ($matches->count() > 1) {
            return $this->error('Multiple accounts match this phone number. Please contact support.', 409);
        }
        $user = $matches->first();

        $recentCount = OtpVerification::where('identifier', $digits)->where('channel', 'forgot_password')->where('created_at', '>', now()->subMinutes(15))->count();
        if ($recentCount >= 3) return $this->error('Too many OTP requests. Please wait 15 minutes.', 429);

        $otp = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        OtpVerification::where('identifier', $digits)->where('channel', 'forgot_password')->where('verified', false)->delete();
        OtpVerification::create([
            'identifier' => $digits, 'otp_hash' => Hash::make($otp), 'otp_plain' => $otp,
            'channel' => 'forgot_password', 'expires_at' => now()->addMinutes(10), 'verified' => false, 'attempts' => 0,
        ]);

        // Send via WhatsApp exactly like the working PWA/api.php flow.
        try {
            $whatsapp = new WhatsAppService();
            if (!$whatsapp->isConfigured()) {
                OtpVerification::where('identifier', $digits)->where('otp_plain', $otp)->delete();
                return $this->error('WhatsApp not configured', 500);
            }

            $result = $whatsapp->sendOtp($digits, $otp, 0);

            if (!empty($result['success'])) {
                return $this->success([
                    'message' => 'OTP sent to WhatsApp for password reset',
                    'to' => $digits,
                    'user_type' => $user->type,
                    'template' => env('WHATSAPP_OTP_TEMPLATE_NAME', 'gymxbook_otp'),
                    'channel' => 'whatsapp',
                ]);
            }

            OtpVerification::where('identifier', $digits)->where('otp_plain', $otp)->delete();
            return $this->error('Failed to send OTP: ' . ($result['error'] ?? 'Unknown'), 400, $result['response'] ?? null);
        } catch (\Exception $e) {
            OtpVerification::where('identifier', $digits)->where('otp_plain', $otp)->delete();
            \Log::warning('Forgot password send OTP failed: ' . $e->getMessage());
            return $this->error('Failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Forgot Password - Verify OTP
     */
    public function forgotPasswordVerifyOtp(Request $request): JsonResponse
    {
        $phone = trim($request->input('phone', ''));
        $otp = trim($request->input('otp', ''));
        $digits = $this->validatePhone($phone);
        if (is_string($digits)) return $this->error($digits, 400);
        if (!preg_match('/^[0-9]{6}$/', $otp)) return $this->error('OTP must be 6 digits', 400);

        $otpRecord = OtpVerification::where('identifier', $digits)->where('channel', 'forgot_password')->where('expires_at', '>', now())->orderBy('verified', 'desc')->orderBy('created_at', 'desc')->first();
        if (!$otpRecord) return $this->error('OTP expired or not found', 400);
        if ($otpRecord->verified) return $this->success(['message' => 'Already verified', 'phone' => $digits]);
        if ($otpRecord->attempts >= 5) { $otpRecord->delete(); return $this->error('Too many attempts', 400); }

        $valid = Hash::check($otp, $otpRecord->otp_hash);
        if (!$valid && $otpRecord->otp_plain === $otp) $valid = true;

        if ($valid) {
            $otpRecord->update(['verified' => true, 'attempts' => $otpRecord->attempts + 1]);
            return $this->success(['message' => 'OTP verified', 'phone' => $digits]);
        } else {
            $otpRecord->increment('attempts');
            return $this->error('Invalid OTP', 400);
        }
    }

    /**
     * Forgot Password - Reset
     */
    public function forgotPasswordReset(Request $request): JsonResponse
    {
        $phone = trim($request->input('phone', ''));
        $otp = trim($request->input('otp', ''));
        $newPassword = $request->input('new_password', '');
        $confirmPassword = $request->input('confirm_password', '');

        $digits = $this->validatePhone($phone);
        if (is_string($digits)) return $this->error($digits, 400);
        if (!preg_match('/^[0-9]{6}$/', $otp)) return $this->error('OTP must be 6 digits', 400);
        if (strlen($newPassword) < 6) return $this->error('Password must be at least 6 characters', 400);
        if ($newPassword !== $confirmPassword) return $this->error('Passwords do not match', 400);

        $otpRecord = OtpVerification::where('identifier', $digits)->where('channel', 'forgot_password')->where('verified', true)->where('created_at', '>', now()->subMinutes(10))->first();
        if (!$otpRecord) {
            $otpRecord = OtpVerification::where('identifier', $digits)->where('channel', 'forgot_password')->where('otp_plain', $otp)->where('expires_at', '>', now())->first();
            if (!$otpRecord) return $this->error('OTP not verified or expired.', 400);
        }

        $matches = app(PhoneIdentityService::class)
            ->matchingUsers((string) $digits)
            ->where('is_active', true)
            ->get();
        if ($matches->isEmpty()) return $this->error('Phone number not found', 404);
        if ($matches->count() > 1) return $this->error('Multiple accounts match this phone number. Please contact support.', 409);
        $user = $matches->first();

        $user->update(['password' => Hash::make($newPassword)]);
        OtpVerification::where('identifier', $digits)->delete();

        return $this->success(['message' => 'Password reset successfully.', 'user_type' => $user->type]);
    }

    /**
     * Protected OTP debug endpoint for gym settings.
     * Sends a real OTP template message and returns detailed diagnostics so we
     * can see whether registration/forgot-password OTP is failing due to config,
     * template, phone formatting, or Meta API response.
     */
    public function debugOtpTest(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !in_array($user->type, ['admin', 'owner', 'super_admin'])) {
            return $this->error('Admin access required', 403);
        }

        $phone = trim($request->input('phone', ''));
        $flow = trim($request->input('flow', 'register')) ?: 'register';
        $digits = $this->validatePhone($phone);
        if (is_string($digits)) {
            return $this->error($digits, 400);
        }

        $otp = trim((string) $request->input('otp', ''));
        if (!preg_match('/^[0-9]{6}$/', $otp)) {
            $otp = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        }

        $whatsapp = new WhatsAppService();
        $debug = $whatsapp->debugSendOtp($digits, $otp, $this->getParentId());

        if (!$whatsapp->isConfigured()) {
            return $this->success([
                'flow' => $flow,
                'phone' => $digits,
                'otp_used' => $otp,
                'configured' => false,
                'debug' => $debug,
                'send_result' => ['success' => false, 'error' => 'WhatsApp not configured'],
            ], 'OTP debug failed: WhatsApp not configured');
        }

        $result = $whatsapp->sendOtp($digits, $otp, $this->getParentId());

        return $this->success([
            'flow' => $flow,
            'phone' => $digits,
            'otp_used' => $otp,
            'configured' => true,
            'debug' => $debug,
            'send_result' => $result,
            'send_success' => !empty($result['success']),
        ], !empty($result['success']) ? 'OTP debug sent successfully' : 'OTP debug failed');
    }

    private function markLoggedIn(Request $request, User $user): void
    {
        try {
            $updates = [];
            $now = now('Asia/Kolkata');

            if (Schema::hasColumn('users', 'last_login_at')) {
                $updates['last_login_at'] = $now;
            }
            if (Schema::hasColumn('users', 'last_login_ip')) {
                $updates['last_login_ip'] = $request->ip();
            }
            if (Schema::hasColumn('users', 'last_login_user_agent')) {
                $updates['last_login_user_agent'] = substr((string) $request->userAgent(), 0, 1000);
            }

            // Flutter login itself must count as an app open. Earlier this was
            // updated only by a second silent /track-app-open request, so Super
            // Admin could show "Never" even after a successful app login.
            $platform = strtolower((string) $request->header('X-App-Platform', ''));
            $isFlutterLogin = $platform === 'flutter' || str_contains(strtolower((string) $request->userAgent()), 'dart');
            if ($isFlutterLogin && in_array($user->type, ['admin', 'owner', 'staff'], true)) {
                $this->ensureAppTrackingColumns();
                if (Schema::hasColumn('users', 'last_app_opened_at')) $updates['last_app_opened_at'] = $now;
                if (Schema::hasColumn('users', 'last_app_platform')) $updates['last_app_platform'] = $request->header('X-App-Platform', 'flutter');
                if (Schema::hasColumn('users', 'last_app_version')) $updates['last_app_version'] = $request->header('X-App-Version', '');
                if (Schema::hasColumn('users', 'last_app_ip')) $updates['last_app_ip'] = $request->ip();
            }

            if (!empty($updates)) {
                $user->forceFill($updates)->save();
            }
            \App\Support\ActivityLogger::log('auth', 'login', 'users', $user->id, $user->name . ' logged in', null, ['type' => $user->type, 'platform' => $request->header('X-App-Platform', 'web')], $request, $user, $user->type === 'staff' ? (int) $user->parent_id : (int) $user->id);
        } catch (\Throwable $e) {
            \Log::warning('Login tracking failed: ' . $e->getMessage());
        }
    }

    private function ensureSanctumTokenTable(): void
    {
        if (Schema::hasTable('personal_access_tokens')) {
            return;
        }

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function trackAppOpen(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        $this->markAppOpened($request, $user);

        return $this->success([], 'App open tracked');
    }

    private function markAppOpened(Request $request, User $user): void
    {
        if (!in_array($user->type, ['admin', 'owner', 'staff'])) {
            return;
        }

        try {
            $this->ensureAppTrackingColumns();
            $user->forceFill([
                'last_app_opened_at' => now('Asia/Kolkata'),
                'last_app_platform' => $request->header('X-App-Platform', 'flutter'),
                'last_app_version' => $request->header('X-App-Version', ''),
                'last_app_ip' => $request->ip(),
            ])->save();
        } catch (\Throwable $e) {
            \Log::warning('App open tracking failed: ' . $e->getMessage());
        }
    }

    private function ensureAppTrackingColumns(): void
    {
        if (!Schema::hasColumn('users', 'last_app_opened_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->timestamp('last_app_opened_at')->nullable()->after('email_verified_at');
            });
        }
        if (!Schema::hasColumn('users', 'last_app_platform')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('last_app_platform', 50)->nullable()->after('last_app_opened_at');
            });
        }
        if (!Schema::hasColumn('users', 'last_app_version')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('last_app_version', 30)->nullable()->after('last_app_platform');
            });
        }
        if (!Schema::hasColumn('users', 'last_app_ip')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('last_app_ip', 60)->nullable()->after('last_app_version');
            });
        }
    }

    private function currentPlanPayload(int $gymOwnerId): array
    {
        $tier = null;
        $features = [];

        try {
            $tier = \App\Services\SubscriptionFeatureService::tier($gymOwnerId);
            if ($tier) {
                $tier->loadMissing('features');
                foreach ($tier->features as $feature) {
                    $features[$feature->feature_key] = $feature->castValue();
                }
            }
        } catch (\Throwable $e) {
            $tier = null;
            $features = [];
        }

        return [
            'current_tier' => $tier ? [
                'id' => $tier->id,
                'code' => $tier->code,
                'name' => $tier->name,
            ] : null,
            'plan_features' => $features,
        ];
    }

    private function loginCandidateScore(User $user): int
    {
        $type = strtolower((string) $user->type);

        if (in_array($type, ['admin', 'owner'], true)) {
            $gymDataCount = 0;
            try {
                $gymDataCount += User::where('parent_id', $user->id)->whereIn('type', ['trainee', 'trainer', 'staff'])->limit(1)->count();
                $gymDataCount += Membership::where('parent_id', $user->id)->limit(1)->count();
                $gymDataCount += Setting::where('parent_id', $user->id)->limit(1)->count();
            } catch (\Throwable $e) {
                $gymDataCount = 0;
            }

            return 10000 + ($gymDataCount > 0 ? 1000 : 0) + max(0, 1000 - (int) $user->id);
        }

        if ($type === 'staff') return 7000 + max(0, 1000 - (int) $user->id);
        if ($type === 'trainer') return 5000 + max(0, 1000 - (int) $user->id);
        if ($type === 'trainee') return 3000 + max(0, 1000 - (int) $user->id);

        return 0;
    }

    /**
     * Validate phone number
     */
    private function validatePhone(string $phone): string|int
    {
        $digits = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($digits) == 12 && substr($digits, 0, 2) == '91') $digits = substr($digits, 2);
        if (strlen($digits) != 10) return 'Phone number must be exactly 10 digits';
        if (!preg_match('/^[6-9][0-9]{9}$/', $digits)) return 'Invalid Indian mobile number. Must start with 6-9';
        // IMPORTANT: callers use is_string($digits) to detect validation errors.
        // Therefore a valid phone must NOT be returned as a string, otherwise
        // Laravel returns the phone number itself as the error message.
        return (int) $digits;
    }
}
