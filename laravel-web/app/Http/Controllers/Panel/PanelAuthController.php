<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\BaseController;
use App\Models\User;
use App\Models\Attendance;
use App\Services\PhoneIdentityService;
use App\Models\TraineeDetail;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;


class PanelAuthController extends BaseController
{
    public function showLogin()
    {
        return view('panel.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string',
            'password' => 'required|string',
        ]);

        $login = trim((string) $request->email);
        $loginDigits = preg_replace('/[^0-9]/', '', $login);
        if (strlen($loginDigits) === 12 && str_starts_with($loginDigits, '91')) {
            $loginDigits = substr($loginDigits, 2);
        }

        if (strlen($loginDigits) !== 10 || !preg_match('/^[6-9][0-9]{9}$/', $loginDigits)) {
            return back()->withErrors(['email' => 'Enter a valid 10-digit phone number.'])->withInput();
        }

        $matches = app(PhoneIdentityService::class)
            ->matchingUsers($loginDigits)
            ->where('is_active', true)
            ->get();
        // Never select the first migrated duplicate. A panel login is valid
        // only when one exact global phone identity exists.
        $user = $matches->count() === 1 ? $matches->first() : null;

        // IMPORTANT: Always return generic error.
        // Even if credentials are correct but user is super_admin (or any other type),
        // we must NOT reveal that. Just say "Invalid email or password."
        $isValidOwner = $user 
            && Hash::check($request->password, $user->password) 
            && in_array($user->type, ['admin', 'owner', 'staff']);

        if (!$isValidOwner) {
            if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid phone or password.'
                ], 401);
            }

            return back()->withErrors(['email' => 'Invalid phone or password.'])->withInput();
        }

        // Login successful
        try {
            $updates = [];
            if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'last_login_at')) $updates['last_login_at'] = now('Asia/Kolkata');
            if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'last_login_ip')) $updates['last_login_ip'] = $request->ip();
            if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'last_login_user_agent')) $updates['last_login_user_agent'] = substr((string) $request->userAgent(), 0, 1000);
            if (!empty($updates)) $user->forceFill($updates)->save();
        } catch (\Throwable $e) {}

        Auth::login($user);
        $request->session()->regenerate();

        $isAjax = $request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest';

        if ($isAjax) {
            $intended = session()->pull('url.intended', '/panel');
            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'redirect' => $intended
            ]);
        }

        $intended = session()->pull('url.intended', '/panel');
        return redirect($intended);
    }

    public function dashboard()
    {
        $user = auth()->user();
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();

        // Stats
        $stats = [
            'members' => User::where('type', 'trainee')->whereIn('parent_id', $parentIds)->count(),
            'trainers' => User::where('type', 'trainer')->whereIn('parent_id', $parentIds)->count(),
            'attendance_today' => Attendance::whereIn('parent_id', $parentIds)->where('date', date('Y-m-d'))->count(),
            'active_members' => TraineeDetail::whereIn('parent_id', $parentIds)->where('membership_expiry_date', '>=', date('Y-m-d'))->count(),
        ];

        // Recent members
        $recentMembers = User::where('type', 'trainee')
            ->whereIn('parent_id', $parentIds)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Today's check-ins
        $todayCheckins = Attendance::whereIn('parent_id', $parentIds)
            ->where('date', date('Y-m-d'))
            ->with('user')
            ->orderBy('checked_in_time', 'desc')
            ->limit(10)
            ->get();

        return view('panel.dashboard', compact('stats', 'recentMembers', 'todayCheckins'));
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Support AJAX logout as well
        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully',
                'redirect' => route('panel.login')
            ]);
        }

        return redirect()->route('panel.login');
    }
}
