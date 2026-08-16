<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use App\Models\User;
use App\Models\Subscription;
use App\Models\SubscriptionOrder;
use App\Models\SubscriptionTier;
use App\Models\SubscriptionTierPrice;
use App\Models\Setting;
use App\Models\TraineeDetail;
use App\Models\TrainerDetail;
use App\Models\Attendance;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\Expense;
use App\Models\Product;
use App\Models\NoticeBoard;
use App\Services\PhoneIdentityService;
use App\Models\AppNotification;
use App\Models\Locker;
use App\Models\AssignLocker;
use App\Models\Event;
use App\Models\Membership;
use App\Models\GymClass;
use App\Models\ClassSchedule;
use App\Models\ClassAssign;
use App\Models\Workout;
use App\Models\WorkoutActivity;
use App\Models\Health;
use App\Models\FreezeMembershipLog;
use App\Models\WhatsAppLog;
use App\Services\WhatsAppService;
use App\Support\PlatformOperationMode;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GymController extends BaseController
{
    /**
     * List all gyms
     */
    public function index(Request $request)
    {
        $search = $request->get('search', '');
        $status = $request->get('status', '');

        $query = User::where('type', 'admin')
            ->with(['subscriptionPlan', 'subscriptionTier', 'subscriptionPrice']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $gyms = $query->orderBy('created_at', 'desc')->paginate(20);
        $gyms->getCollection()->transform(function ($gym) {
            $gym->business_name = Setting::getValue('company_name', $gym->id, $gym->name);
            return $gym;
        });

        return view('admin.gyms.index', compact('gyms', 'search', 'status'));
    }

    /**
     * Show gym details
     */
    public function show(int $id)
    {
        $gym = User::where('id', $id)
            ->where('type', 'admin')
            ->with(['subscriptionPlan', 'subscriptionTier', 'subscriptionPrice'])
            ->firstOrFail();

        $memberCount = User::where('type', 'trainee')->where('parent_id', $id)->count();
        $trainerCount = User::where('type', 'trainer')->where('parent_id', $id)->count();

        $gymName = Setting::getValue('company_name', $id, $gym->name);
        $gymPhone = Setting::getValue('company_phone', $id, $gym->phone_number);
        $gymEmail = Setting::getValue('company_email', $id, $gym->email);
        $gymAddress = Setting::getValue('company_address', $id, '');

        $orders = SubscriptionOrder::where('parent_id', $id)
            ->with(['plan', 'tier', 'tierPrice'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $invoiceCount = Invoice::where('parent_id', $id)->count();
        $attendanceCount = Attendance::where('parent_id', $id)->count();
        $expenseCount = Expense::where('parent_id', $id)->count();

        $plans = Subscription::orderBy('package_amount')->get();
        $tiers = collect();
        if (Schema::hasTable('subscription_tiers') && Schema::hasTable('subscription_tier_prices')) {
            $tiers = SubscriptionTier::with(['prices' => fn($q) => $q->orderBy('sort_order')->orderBy('duration_months')])
                ->orderBy('sort_order')
                ->get();
        }

        return view('admin.gyms.show', compact(
            'gym', 'memberCount', 'trainerCount',
            'gymName', 'gymPhone', 'gymEmail', 'gymAddress', 'orders',
            'invoiceCount', 'attendanceCount', 'expenseCount', 'plans', 'tiers'
        ));
    }

    /**
     * Super Admin creates a Gym Owner account directly. This intentionally
     * bypasses WhatsApp OTP because it is a trusted back-office action.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'business_name' => 'required|string|max:255',
            'owner_name' => 'required|string|max:255',
            'phone_number' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'password' => 'required|string|min:6',
            'address' => 'nullable|string|max:1000',
            'acquisition_source' => 'nullable|in:google_search,play_store,social_media,youtube,chatgpt_ai,referral,sales_team,other,super_admin',
            'acquisition_detail' => 'nullable|string|max:255',
        ]);

        try {
            $phone = app(PhoneIdentityService::class)->requireAvailable($data['phone_number']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
        $email = trim((string) ($data['email'] ?? ''));
        if ($email !== '' && User::where('email', $email)->exists()) {
            return response()->json(['success' => false, 'error' => 'Email already exists'], 422);
        }
        if ($email === '') $email = 'gym_' . $phone . '@gymxbook.temp';

        try {
            $gym = DB::transaction(function () use ($data, $phone, $email) {
                $gym = User::create([
                    'name' => $data['owner_name'],
                    'email' => $email,
                    'phone_number' => $phone,
                    'type' => 'admin',
                    'password' => \Illuminate\Support\Facades\Hash::make($data['password']),
                    'parent_id' => 1,
                    'is_active' => true,
                    'acquisition_source' => $data['acquisition_source'] ?? 'super_admin',
                    'acquisition_detail' => $data['acquisition_detail'] ?? null,
                ]);

                Setting::setValue('company_name', $data['business_name'], $gym->id);
                Setting::setValue('company_phone', $phone, $gym->id);
                if (!str_ends_with($email, '@gymxbook.temp')) Setting::setValue('company_email', $email, $gym->id);
                if (!empty($data['address'])) Setting::setValue('company_address', $data['address'], $gym->id);

                // Deliberately no membership plan seed. New gym Plans starts empty.
                $bronze = Schema::hasTable('subscription_tiers')
                    ? SubscriptionTier::where('code', 'bronze')->first()
                    : null;
                $trialEnd = now('Asia/Kolkata')->addDays(7)->endOfDay();
                $updates = [
                    'subscription_status' => 'trial',
                    'subscription_started_at' => now('Asia/Kolkata'),
                    'subscription_ends_at' => $trialEnd,
                    'subscription_expire_date' => $trialEnd->toDateString(),
                ];
                if ($bronze) $updates['subscription_tier_id'] = $bronze->id;
                $gym->update($updates);
                return $gym;
            });

            return response()->json([
                'success' => true,
                'message' => 'Gym account created successfully',
                'redirect' => route('admin.gyms.show', $gym->id),
            ]);
        } catch (\Throwable $e) {
            \Log::error('Super Admin create gym failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Could not create gym account'], 500);
        }
    }

    public function updateAcquisition(Request $request, int $id)
    {
        $gym = User::where('id', $id)->where('type', 'admin')->firstOrFail();
        $data = $request->validate([
            'acquisition_source' => 'required|in:google_search,play_store,social_media,youtube,chatgpt_ai,referral,sales_team,other,super_admin',
            'acquisition_detail' => 'nullable|string|max:255',
        ]);
        $gym->update([
            'acquisition_source' => $data['acquisition_source'],
            'acquisition_detail' => trim((string) ($data['acquisition_detail'] ?? '')) ?: null,
        ]);
        return redirect()->route('admin.gyms.show', $id)->with('success', 'Gym acquisition information updated');
    }

    /**
     * Update gym
     */
    public function update(Request $request, int $id)
    {
        $gym = User::where('id', $id)->where('type', 'admin')->firstOrFail();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
        ]);

        try {
            $phone = $request->has('phone_number')
                ? app(PhoneIdentityService::class)->requireAvailable($request->phone_number, (int) $gym->id)
                : $gym->phone_number;
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $gym->update([
            'name' => $request->name,
            'email' => $request->email,
            'phone_number' => $phone,
        ]);

        if ($request->company_name) Setting::setValue('company_name', $request->company_name, $id);
        if ($request->company_phone) Setting::setValue('company_phone', $request->company_phone, $id);
        if ($request->company_address) Setting::setValue('company_address', $request->company_address, $id);

        return redirect()->route('admin.gyms.show', $id)->with('success', 'Gym updated successfully');
    }

    /**
     * Toggle gym active/inactive
     */
    public function toggle(int $id)
    {
        $gym = User::where('id', $id)->where('type', 'admin')->firstOrFail();
        $gym->update(['is_active' => !$gym->is_active]);
        $status = $gym->is_active ? 'activated' : 'deactivated';
        return redirect()->route('admin.gyms.show', $id)->with('success', "Gym {$status} successfully");
    }

    /**
     * Update gym subscription in either legacy format or new SaaS tier format.
     */
    public function updateSubscription(Request $request, int $id)
    {
        $gym = User::where('id', $id)->where('type', 'admin')->firstOrFail();

        $data = $request->validate([
            'subscription_format' => 'required|in:legacy,new',
            'plan_id' => 'nullable|integer|exists:subscriptions,id',
            'subscription_tier_id' => 'nullable|integer|exists:subscription_tiers,id',
            'subscription_tier_price_id' => 'nullable|integer|exists:subscription_tier_prices,id',
            'duration_months' => 'nullable|integer|min:1|max:120',
            'start_date' => 'nullable|date',
            'expiry_date' => 'nullable|date',
            'subscription_status' => 'nullable|in:active,pending,trial,expired,cancelled,inactive',
        ]);

        $format = $data['subscription_format'];
        $status = $data['subscription_status'] ?? 'active';
        $startDate = !empty($data['start_date'])
            ? Carbon::parse($data['start_date'], 'Asia/Kolkata')->startOfDay()
            : now('Asia/Kolkata')->startOfDay();

        if ($format === 'legacy') {
            if (empty($data['plan_id'])) {
                return back()->with('error', 'Please select a legacy subscription plan.')->withInput();
            }

            $plan = Subscription::findOrFail((int) $data['plan_id']);
            $durationMonths = !empty($data['duration_months'])
                ? (int) $data['duration_months']
                : $this->legacyIntervalMonths((string) ($plan->interval ?? 'monthly'));
            $expiryDate = $this->adminSubscriptionExpiry($data['expiry_date'] ?? null, $startDate, $durationMonths);

            $payload = [
                'subscription' => $plan->id,
                'subscription_expire_date' => $expiryDate->toDateString(),
            ];

            foreach ([
                'subscription_tier_id' => null,
                'subscription_price_id' => null,
                'subscription_status' => $status,
                'subscription_started_at' => $startDate,
                'subscription_ends_at' => null,
            ] as $column => $value) {
                if (Schema::hasColumn('users', $column)) {
                    $payload[$column] = $value;
                }
            }

            $gym->update($payload);

            return redirect()
                ->route('admin.gyms.show', $id)
                ->with('success', 'Legacy subscription updated to ' . $plan->title . ' until ' . $expiryDate->format('d M Y'));
        }

        if (!Schema::hasTable('subscription_tiers') || !Schema::hasTable('subscription_tier_prices') || !Schema::hasColumn('users', 'subscription_tier_id')) {
            return back()->with('error', 'New SaaS subscription tables/columns are missing. Run System Update first.')->withInput();
        }

        $tier = null;
        $tierPrice = null;
        if (!empty($data['subscription_tier_price_id'])) {
            $tierPrice = SubscriptionTierPrice::with('tier')->findOrFail((int) $data['subscription_tier_price_id']);
            $tier = $tierPrice->tier;
            if (!$tier) {
                return back()->with('error', 'Selected plan duration is invalid.')->withInput();
            }
            if (!empty($data['subscription_tier_id']) && (int) $data['subscription_tier_id'] !== (int) $tier->id) {
                return back()->with('error', 'Selected SaaS plan and duration do not match.')->withInput();
            }
        } else {
            if (empty($data['subscription_tier_id'])) {
                return back()->with('error', 'Please select a SaaS plan.')->withInput();
            }
            $tier = SubscriptionTier::findOrFail((int) $data['subscription_tier_id']);
        }

        $durationMonths = !empty($data['duration_months'])
            ? (int) $data['duration_months']
            : max(1, (int) ($tierPrice?->duration_months ?? 1));
        $expiryDate = $this->adminSubscriptionExpiry($data['expiry_date'] ?? null, $startDate, $durationMonths);

        $gym->update([
            'subscription' => null,
            'subscription_tier_id' => $tier->id,
            'subscription_price_id' => $tierPrice?->id,
            'subscription_status' => $status,
            'subscription_started_at' => $startDate,
            'subscription_ends_at' => $expiryDate->copy()->endOfDay(),
            'subscription_expire_date' => $expiryDate->toDateString(),
        ]);

        return redirect()
            ->route('admin.gyms.show', $id)
            ->with('success', 'SaaS subscription updated to ' . $tier->name . ' until ' . $expiryDate->format('d M Y'));
    }

    private function adminSubscriptionExpiry(?string $expiryDate, Carbon $startDate, int $durationMonths): Carbon
    {
        if (!empty($expiryDate)) {
            return Carbon::parse($expiryDate, 'Asia/Kolkata')->startOfDay();
        }

        return $startDate->copy()->addMonthsNoOverflow(max(1, $durationMonths))->startOfDay();
    }

    private function legacyIntervalMonths(string $interval): int
    {
        $interval = strtolower(trim($interval));
        if (str_contains($interval, 'year') || str_contains($interval, 'annual') || str_contains($interval, '12')) return 12;
        if (str_contains($interval, 'quarter') || str_contains($interval, '3')) return 3;
        if (str_contains($interval, 'half') || str_contains($interval, '6')) return 6;
        return 1;
    }

    /**
     * FIX #2: Login as gym owner (impersonate)
     */
    public function loginAs(int $id)
    {
        $gym = User::where('id', $id)->where('type', 'admin')->firstOrFail();

        if (!$gym->is_active) {
            return redirect()->route('admin.gyms.show', $id)->with('error', 'Cannot login to inactive gym');
        }

        // Store admin user ID in session to return later
        session(['admin_user_id' => Auth::id()]);

        // Login as gym owner
        Auth::login($gym);

        return redirect('/panel');
    }

    /**
     * Return to admin panel from impersonation
     */
    public function returnToAdmin()
    {
        $adminId = session('admin_user_id');

        if ($adminId) {
            $admin = User::find($adminId);
            if ($admin && $admin->type === 'super_admin') {
                Auth::login($admin);
                session()->forget('admin_user_id');
                return redirect()->route('admin.dashboard');
            }
        }

        // Fallback: logout and redirect to admin login
        Auth::logout();
        return redirect()->route('admin.login');
    }

    /** Send WhatsApp confirmation OTP before destructive gym deletion. */
    public function sendDeleteOtp(int $id)
    {
        if (!PlatformOperationMode::isDebug()) {
            return response()->json(['success' => false, 'error' => 'Gym deletion is disabled in Production Mode.'], 403);
        }
        $gym = User::where('id', $id)->where('type', 'admin')->firstOrFail();
        $admin = auth()->user();
        if (!$admin || empty($admin->phone_number)) {
            return response()->json(['success' => false, 'error' => 'Super Admin WhatsApp phone is missing.'], 422);
        }
        $otp = (string) random_int(100000, 999999);
        $whatsapp = new WhatsAppService();
        $result = $whatsapp->sendOtp($admin->phone_number, $otp, 1);
        if (empty($result['success'])) {
            return response()->json(['success' => false, 'error' => 'Could not send WhatsApp deletion confirmation code.'], 500);
        }
        session([
            'admin_gym_delete_id' => $gym->id,
            'admin_gym_delete_hash' => \Illuminate\Support\Facades\Hash::make($otp),
            'admin_gym_delete_expires_at' => now()->addMinutes(5)->toDateTimeString(),
            'admin_gym_delete_attempts' => 0,
        ]);
        return response()->json(['success' => true, 'message' => 'WhatsApp confirmation code sent.']);
    }

    /**
     * Delete gym with cascading delete
     */
    public function destroy(Request $request, int $id)
    {
        if (!PlatformOperationMode::isDebug()) {
            return response()->json(['success' => false, 'error' => 'Gym deletion is disabled in Production Mode.'], 403);
        }
        $hash = session('admin_gym_delete_hash');
        $expires = session('admin_gym_delete_expires_at');
        $sessionGymId = (int) session('admin_gym_delete_id', 0);
        $attempts = (int) session('admin_gym_delete_attempts', 0);
        $otp = trim((string) $request->input('delete_otp', ''));
        if ($sessionGymId !== $id || !$hash || !$expires || now()->greaterThan(Carbon::parse($expires)) || $attempts >= 5 || !\Illuminate\Support\Facades\Hash::check($otp, $hash)) {
            session()->forget(['admin_gym_delete_id', 'admin_gym_delete_hash', 'admin_gym_delete_expires_at', 'admin_gym_delete_attempts']);
            return response()->json(['success' => false, 'error' => 'Invalid or expired deletion confirmation code.'], 422);
        }
        session()->forget(['admin_gym_delete_id', 'admin_gym_delete_hash', 'admin_gym_delete_expires_at', 'admin_gym_delete_attempts']);
        $gym = User::where('id', $id)->where('type', 'admin')->firstOrFail();
        $gymName = $gym->name;

        DB::beginTransaction();
        try {
            $memberIds = User::where('type', 'trainee')->where('parent_id', $id)->pluck('id')->toArray();
            $trainerIds = User::where('type', 'trainer')->where('parent_id', $id)->pluck('id')->toArray();

            $invoiceIds = Invoice::where('parent_id', $id)->pluck('id')->toArray();
            if (!empty($invoiceIds)) {
                InvoiceItem::whereIn('invoice_id', $invoiceIds)->delete();
                InvoicePayment::whereIn('invoice_id', $invoiceIds)->delete();
            }
            Invoice::where('parent_id', $id)->delete();

            Attendance::where('parent_id', $id)->delete();
            Expense::where('parent_id', $id)->delete();
            Product::where('parent_id', $id)->delete();
            NoticeBoard::where('parent_id', $id)->delete();
            AppNotification::where('parent_id', $id)->delete();

            $lockerIds = Locker::where('parent_id', $id)->pluck('id')->toArray();
            if (!empty($lockerIds)) AssignLocker::whereIn('locker_id', $lockerIds)->delete();
            Locker::where('parent_id', $id)->delete();

            Event::where('parent_id', $id)->delete();
            Membership::where('parent_id', $id)->delete();

            $classIds = GymClass::where('parent_id', $id)->pluck('id')->toArray();
            if (!empty($classIds)) {
                ClassSchedule::whereIn('classes_id', $classIds)->delete();
                ClassAssign::whereIn('classes_id', $classIds)->delete();
            }
            GymClass::where('parent_id', $id)->delete();

            if (!empty($memberIds)) {
                Workout::whereIn('assign_id', $memberIds)->delete();
                Health::whereIn('user_id', $memberIds)->delete();
                FreezeMembershipLog::whereIn('trainee_id', $memberIds)->delete();
            }
            Workout::where('parent_id', $id)->delete();
            WorkoutActivity::where('parent_id', $id)->delete();
            Health::where('parent_id', $id)->delete();

            if (!empty($memberIds)) TraineeDetail::whereIn('user_id', $memberIds)->delete();
            TraineeDetail::where('parent_id', $id)->delete();

            if (!empty($trainerIds)) TrainerDetail::whereIn('user_id', $trainerIds)->delete();
            TrainerDetail::where('parent_id', $id)->delete();

            SubscriptionOrder::where('parent_id', $id)->delete();
            WhatsAppLog::where('parent_id', $id)->delete();
            Setting::where('parent_id', $id)->delete();

            if (!empty($memberIds)) User::whereIn('id', $memberIds)->delete();
            if (!empty($trainerIds)) User::whereIn('id', $trainerIds)->delete();

            $gym->delete();

            DB::commit();
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json(['success' => true, 'message' => "Gym '{$gymName}' and all related data deleted permanently"]);
            }
            return redirect()->route('admin.gyms.index')->with('success', "Gym '{$gymName}' and all related data deleted permanently");

        } catch (\Exception $e) {
            DB::rollBack();
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json(['success' => false, 'error' => 'Failed to delete gym safely'], 500);
            }
            return redirect()->route('admin.gyms.show', $id)->with('error', 'Failed to delete gym: ' . $e->getMessage());
        }
    }
}
