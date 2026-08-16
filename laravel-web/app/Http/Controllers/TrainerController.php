<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\TrainerDetail;
use App\Models\TraineeDetail;
use App\Models\ClassAssign;
use App\Models\GymClass;
use App\Models\Attendance;
use App\Models\Workout;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use App\Services\PhoneIdentityService;

class TrainerController extends BaseController
{
    private const DEFAULT_PASSWORD = '1234@paas';

    public function index(Request $request): JsonResponse
    {
        if (!$this->canPerformGymAction('trainers.view')) return $this->error('Permission denied', 403);
        if (!$this->planFeatureEnabled('trainers_enabled', true)) {
            return $this->error(\App\Services\SubscriptionFeatureService::featureLockedMessage('Trainer management'), 402);
        }
        $parentIds = $this->getGymParentIds();

        $trainers = User::where('type', 'trainer')
            ->whereIn('parent_id', $parentIds)
            ->with('trainerDetails')
            ->orderBy('created_at', 'desc')
            ->get();

        $trainers->each(function ($trainer) {
            $trainer->makeHidden(['password', 'remember_token', 'twofa_secret']);
            if ($trainer->trainerDetails) {
                $trainer->qualification = $trainer->trainerDetails->qualification;
                $trainer->specialization = $trainer->trainerDetails->specialization ?? '';
                $trainer->experience_years = $trainer->trainerDetails->experience_years ?? 0;
                $trainer->joining_date = $trainer->trainerDetails->joining_date;
                $trainer->salary = $trainer->trainerDetails->salary ?? 0;
                $trainer->gender = $trainer->trainerDetails->gender;
                $trainer->dob = $trainer->trainerDetails->dob;
                $trainer->address = $trainer->trainerDetails->address;
                $trainer->city = $trainer->trainerDetails->city;
                $trainer->bio = $trainer->trainerDetails->bio ?? '';
                $trainer->emergency_contact = $trainer->trainerDetails->emergency_contact ?? '';
                $trainer->trainer_status = $trainer->trainerDetails->status;
            }
            $assignedMembersCount = TraineeDetail::where('trainer_assign', $trainer->id)
                ->whereIn('parent_id', $this->getGymParentIds())
                ->count();
            $trainer->assigned_members_count = $assignedMembersCount;
            $trainer->assigned_classes_count = ClassAssign::where('assign_id', $trainer->id)
                ->where('assign_type', 'trainer')
                ->count();
            $trainer->can_delete = $assignedMembersCount === 0;
        });

        return $this->success(['trainers' => $trainers]);
    }

    public function show(int $id): JsonResponse
    {
        if (!$this->canPerformGymAction('trainers.view')) return $this->error('Permission denied', 403);
        if (!$this->planFeatureEnabled('trainers_enabled', true)) {
            return $this->error(\App\Services\SubscriptionFeatureService::featureLockedMessage('Trainer management'), 402);
        }
        $parentIds = $this->getGymParentIds();
        $trainer = User::where('id', $id)
            ->where('type', 'trainer')
            ->whereIn('parent_id', $parentIds)
            ->with('trainerDetails')
            ->first();

        if (!$trainer) return $this->error('Trainer not found', 404);

        $trainer->makeHidden(['password', 'remember_token', 'twofa_secret']);
        $detail = $trainer->trainerDetails;

        $assignedMembers = User::where('type', 'trainee')
            ->whereIn('parent_id', $parentIds)
            ->whereHas('traineeDetails', fn($q) => $q->where('trainer_assign', $trainer->id))
            ->with('traineeDetails.membership')
            ->orderBy('name')
            ->get()
            ->map(function ($member) {
                $d = $member->traineeDetails;
                return [
                    'id' => $member->id,
                    'name' => $member->name,
                    'phone_number' => $member->phone_number,
                    'email' => $member->email,
                    'plan_name' => $d && $d->membership ? $d->membership->title : 'No Plan',
                    'membership_expiry_date' => $d?->membership_expiry_date,
                    'status' => $d?->status,
                ];
            })->values();

        $classIds = ClassAssign::where('assign_id', $trainer->id)
            ->where('assign_type', 'trainer')
            ->pluck('classes_id');

        $assignedClasses = GymClass::whereIn('id', $classIds)
            ->with('schedules')
            ->orderBy('title')
            ->get()
            ->map(function ($class) {
                return [
                    'id' => $class->id,
                    'title' => $class->title,
                    'fees' => $class->fees,
                    'address' => $class->address,
                    'schedules' => $class->schedules->map(fn($s) => [
                        'days' => $s->days,
                        'start_time' => $this->cleanTime($s->start_time ?? ''),
                        'end_time' => $this->cleanTime($s->end_time ?? ''),
                    ])->values(),
                ];
            })->values();

        $payload = array_merge($trainer->toArray(), [
            'qualification' => $detail?->qualification ?? '',
            'specialization' => $detail?->specialization ?? '',
            'experience_years' => $detail?->experience_years ?? 0,
            'joining_date' => $detail?->joining_date,
            'salary' => $detail?->salary ?? 0,
            'gender' => $detail?->gender ?? '',
            'dob' => $detail?->dob,
            'address' => $detail?->address ?? '',
            'city' => $detail?->city ?? '',
            'bio' => $detail?->bio ?? '',
            'emergency_contact' => $detail?->emergency_contact ?? '',
            'trainer_status' => $detail?->status,
            'assigned_members' => $assignedMembers,
            'assigned_members_count' => $assignedMembers->count(),
            'assigned_classes' => $assignedClasses,
            'assigned_classes_count' => $assignedClasses->count(),
            'can_delete' => $assignedMembers->count() === 0,
        ]);

        return $this->success(['trainer' => $payload]);
    }

    public function store(Request $request): JsonResponse
    {
        if (!$this->canPerformGymAction('trainers.create')) return $this->error('Permission denied', 403);
        $pid = $this->getParentId();
        if (!$this->planFeatureEnabled('trainers_enabled', true)) {
            return $this->error(\App\Services\SubscriptionFeatureService::featureLockedMessage('Trainer management'), 402);
        }
        $trainerLimit = $this->planLimit('trainers_limit', 0);
        if ($trainerLimit > 0 && User::where('type', 'trainer')->where('parent_id', $pid)->count() >= $trainerLimit) {
            return $this->error(\App\Services\SubscriptionFeatureService::limitReachedMessage('Trainer', $trainerLimit), 402);
        }
        $this->ensureTrainerDetailColumns();

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone_number' => 'required|string|max:20',
            'gender' => 'nullable|in:male,female,other',
            'dob' => 'nullable|date',
            'qualification' => 'nullable|string|max:255',
            'specialization' => 'nullable|string|max:255',
            'experience_years' => 'nullable|integer|min:0|max:80',
            'joining_date' => 'nullable|date',
            'salary' => 'nullable|numeric|min:0',
            'address' => 'nullable|string|max:1000',
            'city' => 'nullable|string|max:100',
            'bio' => 'nullable|string|max:2000',
            'emergency_contact' => 'nullable|string|max:30',
        ]);

        try {
            $phoneDigits = app(PhoneIdentityService::class)->requireAvailable($data['phone_number']);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 400);
        }

        $email = trim($data['email'] ?? '');
        if ($email !== '' && User::where('email', $email)->exists()) {
            return $this->error('Email already exists', 400);
        }
        if ($email === '') {
            $email = 'trainer_' . $pid . '_' . $phoneDigits . '@gymxbook.temp';
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $email,
            'phone_number' => $phoneDigits,
            'type' => 'trainer',
            'password' => Hash::make(self::DEFAULT_PASSWORD),
            'parent_id' => $pid,
            'is_active' => true,
        ]);

        TrainerDetail::create([
            'user_id' => $user->id,
            'trainer_id' => $user->id,
            'address' => $data['address'] ?? '',
            'city' => $data['city'] ?? '',
            'state' => $request->state ?? '',
            'country' => $request->country ?? '',
            'zip_code' => $request->zip_code ?? '',
            'dob' => $data['dob'] ?? null,
            'gender' => $data['gender'] ?? 'male',
            'qualification' => $data['qualification'] ?? '',
            'specialization' => $data['specialization'] ?? '',
            'experience_years' => $data['experience_years'] ?? 0,
            'joining_date' => $data['joining_date'] ?? now()->toDateString(),
            'salary' => $data['salary'] ?? 0,
            'bio' => $data['bio'] ?? '',
            'emergency_contact' => $data['emergency_contact'] ?? '',
            'parent_id' => $pid,
            'status' => 1,
        ]);

        $user->load('trainerDetails');
        $user->makeHidden(['password', 'remember_token', 'twofa_secret']);

        return $this->success([
            'id' => $user->id,
            'trainer' => $user,
            'default_password' => self::DEFAULT_PASSWORD,
        ], 'Trainer added successfully. Default password is ' . self::DEFAULT_PASSWORD, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if (!$this->canPerformGymAction('trainers.edit')) return $this->error('Permission denied', 403);
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();
        $this->ensureTrainerDetailColumns();

        $trainer = User::where('id', $id)
            ->where('type', 'trainer')
            ->whereIn('parent_id', $parentIds)
            ->first();

        if (!$trainer) return $this->error('Trainer not found', 404);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone_number' => 'required|string|max:20',
            'gender' => 'nullable|in:male,female,other',
            'dob' => 'nullable|date',
            'qualification' => 'nullable|string|max:255',
            'specialization' => 'nullable|string|max:255',
            'experience_years' => 'nullable|integer|min:0|max:80',
            'joining_date' => 'nullable|date',
            'salary' => 'nullable|numeric|min:0',
            'address' => 'nullable|string|max:1000',
            'city' => 'nullable|string|max:100',
            'bio' => 'nullable|string|max:2000',
            'emergency_contact' => 'nullable|string|max:30',
        ]);

        try {
            $phoneDigits = app(PhoneIdentityService::class)->requireAvailable($data['phone_number'], (int) $trainer->id);
        } catch (\InvalidArgumentException $e) {
            return $this->error($e->getMessage(), 400);
        }

        $email = trim($data['email'] ?? '');
        if ($email !== '' && User::where('email', $email)->where('id', '!=', $trainer->id)->exists()) {
            return $this->error('Email already exists', 400);
        }
        if ($email === '') $email = $trainer->email;

        $trainer->update([
            'name' => $data['name'],
            'email' => $email,
            'phone_number' => $phoneDigits,
        ]);

        $detail = $trainer->trainerDetails ?: new TrainerDetail(['user_id' => $trainer->id, 'trainer_id' => $trainer->id, 'parent_id' => $pid]);
        $detail->fill([
            'address' => $data['address'] ?? '',
            'city' => $data['city'] ?? '',
            'dob' => $data['dob'] ?? null,
            'gender' => $data['gender'] ?? 'male',
            'qualification' => $data['qualification'] ?? '',
            'specialization' => $data['specialization'] ?? '',
            'experience_years' => $data['experience_years'] ?? 0,
            'joining_date' => $data['joining_date'] ?? null,
            'salary' => $data['salary'] ?? 0,
            'bio' => $data['bio'] ?? '',
            'emergency_contact' => $data['emergency_contact'] ?? '',
            'status' => $detail->status ?? 1,
        ]);
        $detail->save();

        return $this->success([], 'Trainer updated successfully');
    }

    public function toggle(int $id): JsonResponse
    {
        if (!$this->canPerformGymAction('trainers.edit')) return $this->error('Permission denied', 403);
        $parentIds = $this->getGymParentIds();
        $trainer = User::where('id', $id)
            ->where('type', 'trainer')
            ->whereIn('parent_id', $parentIds)
            ->with('trainerDetails')
            ->first();

        if (!$trainer) return $this->error('Trainer not found', 404);

        $trainer->update(['is_active' => !$trainer->is_active]);
        if ($trainer->trainerDetails) {
            $trainer->trainerDetails->update(['status' => $trainer->is_active ? 1 : 0]);
        }

        return $this->success([], 'Trainer status updated');
    }

    public function destroy(int $id): JsonResponse
    {
        if (!$this->canPerformGymAction('trainers.delete')) return $this->error('Permission denied', 403);
        $parentIds = $this->getGymParentIds();
        $trainer = User::where('id', $id)
            ->where('type', 'trainer')
            ->whereIn('parent_id', $parentIds)
            ->first();

        if (!$trainer) return $this->error('Trainer not found', 404);

        $assignedMembersCount = TraineeDetail::where('trainer_assign', $trainer->id)
            ->whereIn('parent_id', $parentIds)
            ->count();

        if ($assignedMembersCount > 0) {
            return $this->error('Cannot delete trainer while members are assigned. Reassign or remove assigned members first.', 422);
        }

        ClassAssign::where('assign_id', $trainer->id)
            ->where('assign_type', 'trainer')
            ->delete();
        TrainerDetail::where('user_id', $trainer->id)->delete();
        $trainer->delete();

        return $this->success([], 'Trainer deleted');
    }

    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || $user->type !== 'trainer') return $this->error('Trainer access required', 403);

        $memberQuery = TraineeDetail::where('trainer_assign', $user->id);
        $memberIds = (clone $memberQuery)->pluck('user_id');
        $assignedMemberCount = $memberIds->count();
        $today = now('Asia/Kolkata')->toDateString();
        $nextWeek = now('Asia/Kolkata')->addDays(7)->toDateString();

        $activeMembersCount = (clone $memberQuery)
            ->where('status', 1)
            ->where(function ($q) use ($today) {
                $q->whereNull('membership_expiry_date')
                  ->orWhere('membership_expiry_date', '>=', $today);
            })
            ->count();

        $expiringMembersCount = (clone $memberQuery)
            ->whereNotNull('membership_expiry_date')
            ->whereBetween('membership_expiry_date', [$today, $nextWeek])
            ->count();

        $workoutsCount = Workout::whereIn('assign_id', $memberIds)
            ->where('assign_to', 'member')
            ->count();

        $classIds = ClassAssign::where('assign_id', $user->id)
            ->where('assign_type', 'trainer')
            ->distinct()
            ->pluck('classes_id');
        $classCount = $classIds->count();
        $todayClassesCount = 0;

        if ($classIds->isNotEmpty()) {
            $todayClassesCount = GymClass::whereIn('id', $classIds)
                ->with('schedules')
                ->get()
                ->filter(function ($class) {
                    return $class->schedules->contains(function ($schedule) {
                        return $this->scheduleMatchesToday($schedule->days ?? '');
                    });
                })
                ->count();
        }

        return $this->success([
            'assigned_members_count' => $assignedMemberCount,
            'active_members_count' => $activeMembersCount,
            'expiring_members_count' => $expiringMembersCount,
            'workouts_count' => $workoutsCount,
            'classes_count' => $classCount,
            'today_classes_count' => $todayClassesCount,
            'trainer' => $user->load('trainerDetails'),
        ]);
    }

    public function assignedMembers(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || $user->type !== 'trainer') return $this->error('Trainer access required', 403);

        $members = User::where('type', 'trainee')
            ->whereHas('traineeDetails', fn($q) => $q->where('trainer_assign', $user->id))
            ->with('traineeDetails.membership')
            ->orderBy('name')
            ->get()
            ->map(function ($member) {
                $detail = $member->traineeDetails;
                $expiry = $detail?->membership_expiry_date;
                $daysLeft = $expiry ? now('Asia/Kolkata')->startOfDay()->diffInDays($expiry, false) : null;
                $lastAttendance = Attendance::where('user_id', $member->id)
                    ->orderBy('date', 'desc')
                    ->orderBy('id', 'desc')
                    ->first();

                return [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'phone_number' => $member->phone_number,
                    'gender' => $detail?->gender,
                    'city' => $detail?->city,
                    'address' => $detail?->address,
                    'plan_name' => $detail && $detail->membership ? $detail->membership->title : 'No Plan',
                    'plan_amount' => $detail && $detail->membership ? $detail->membership->amount : 0,
                    'membership_start_date' => $detail?->membership_start_date,
                    'membership_expiry_date' => $expiry,
                    'days_left' => $daysLeft,
                    'fitness_goal' => $detail?->fitness_goal,
                    'status' => $detail?->status,
                    'last_attendance_date' => $lastAttendance?->date,
                    'last_check_in' => $lastAttendance?->checked_in_time,
                    'last_check_out' => $lastAttendance?->checked_out_time,
                ];
            });

        return $this->success([
            'members' => $members,
            'total' => $members->count(),
        ]);
    }

    public function memberDetail(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user || $user->type !== 'trainer') return $this->error('Trainer access required', 403);

        $member = User::where('id', $id)
            ->where('type', 'trainee')
            ->whereHas('traineeDetails', fn($q) => $q->where('trainer_assign', $user->id))
            ->with('traineeDetails.membership')
            ->first();

        if (!$member) return $this->error('Member not assigned to you', 404);

        $attendance = Attendance::where('user_id', $member->id)
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->limit(15)
            ->get()
            ->map(function ($att) {
                return [
                    'id' => $att->id,
                    'date' => $att->date,
                    'checked_in_time' => $att->checked_in_time,
                    'checked_out_time' => $att->checked_out_time,
                    'notes' => $att->notes,
                ];
            });

        $detail = $member->traineeDetails;
        $expiry = $detail?->membership_expiry_date;
        $daysLeft = $expiry ? now('Asia/Kolkata')->startOfDay()->diffInDays($expiry, false) : null;

        return $this->success([
            'member' => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'phone_number' => $member->phone_number,
                'address' => $detail?->address,
                'city' => $detail?->city,
                'gender' => $detail?->gender,
                'dob' => $detail?->dob,
                'fitness_goal' => $detail?->fitness_goal,
                'plan_name' => $detail && $detail->membership ? $detail->membership->title : 'No Plan',
                'plan_amount' => $detail && $detail->membership ? $detail->membership->amount : 0,
                'membership_start_date' => $detail?->membership_start_date,
                'membership_expiry_date' => $expiry,
                'days_left' => $daysLeft,
                'status' => $detail?->status,
            ],
            'attendance_history' => $attendance,
        ]);
    }

    public function workouts(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || $user->type !== 'trainer') return $this->error('Trainer access required', 403);

        $memberIds = TraineeDetail::where('trainer_assign', $user->id)->pluck('user_id');

        $workouts = Workout::whereIn('assign_id', $memberIds)
            ->where('assign_to', 'member')
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($workout) {
                return [
                    'id' => $workout->id,
                    'assign_id' => $workout->assign_id,
                    'member_name' => $workout->user ? $workout->user->name : '',
                    'member_phone' => $workout->user ? $workout->user->phone_number : '',
                    'start_date' => $workout->start_date,
                    'end_date' => $workout->end_date,
                    'workout_history' => $workout->workout_history,
                    'notes' => $workout->notes,
                    'created_at' => $workout->created_at,
                ];
            });

        return $this->success(['workouts' => $workouts]);
    }

    public function storeWorkout(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || $user->type !== 'trainer') return $this->error('Trainer access required', 403);

        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'workout_plan' => 'required|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'notes' => 'nullable|string|max:1000',
        ]);

        if (!$this->trainerCanAccessMember($user->id, (int)$data['user_id'])) {
            return $this->error('This member is not assigned to you', 403);
        }

        $plan = json_decode($data['workout_plan'], true);
        if (!is_array($plan) || empty($plan)) {
            return $this->error('Workout plan must contain at least one day and exercise', 422);
        }

        $workout = Workout::create([
            'assign_to' => 'member',
            'assign_id' => $data['user_id'],
            'start_date' => $data['start_date'] ?? now('Asia/Kolkata')->toDateString(),
            'end_date' => $data['end_date'] ?? null,
            'workout_history' => $plan,
            'notes' => $data['notes'] ?? '',
            'parent_id' => $user->parent_id ?: 0,
        ]);

        return $this->success(['workout' => $workout], 'Workout plan created', 201);
    }

    public function updateWorkout(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user || $user->type !== 'trainer') return $this->error('Trainer access required', 403);

        $workout = $this->trainerWorkout($user->id, $id);
        if (!$workout) return $this->error('Workout not found', 404);

        $data = $request->validate([
            'workout_plan' => 'required|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'notes' => 'nullable|string|max:1000',
        ]);

        $plan = json_decode($data['workout_plan'], true);
        if (!is_array($plan) || empty($plan)) {
            return $this->error('Workout plan must contain at least one day and exercise', 422);
        }

        $workout->update([
            'start_date' => $data['start_date'] ?? $workout->start_date,
            'end_date' => $data['end_date'] ?? $workout->end_date,
            'workout_history' => $plan,
            'notes' => $data['notes'] ?? $workout->notes,
        ]);

        return $this->success(['workout' => $workout], 'Workout plan updated');
    }

    public function destroyWorkout(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user || $user->type !== 'trainer') return $this->error('Trainer access required', 403);

        $workout = $this->trainerWorkout($user->id, $id);
        if (!$workout) return $this->error('Workout not found', 404);

        $workout->delete();
        return $this->success([], 'Workout plan deleted');
    }

    public function classes(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || $user->type !== 'trainer') return $this->error('Trainer access required', 403);

        $classIds = ClassAssign::where('assign_id', $user->id)
            ->where('assign_type', 'trainer')
            ->distinct()
            ->pluck('classes_id');

        if ($classIds->isEmpty()) {
            return $this->success([
                'classes' => [],
                'total' => 0,
                'today_count' => 0,
                'total_members' => 0,
            ]);
        }

        $classes = GymClass::whereIn('id', $classIds)
            ->with([
                'schedules' => fn($q) => $q->orderBy('id'),
                'assigns' => fn($q) => $q->where('assign_type', 'member')
                    ->with(['user.traineeDetails.membership'])
                    ->orderBy('created_at', 'desc'),
            ])
            ->withCount(['assignedMembers as member_count'])
            ->orderBy('title')
            ->get()
            ->map(function ($class) {
                $schedules = $class->schedules->map(function ($schedule) {
                    $start = $this->cleanTime($schedule->start_time ?? '');
                    $end = $this->cleanTime($schedule->end_time ?? '');

                    return [
                        'id' => $schedule->id,
                        'days' => $schedule->days,
                        'start_time' => $start,
                        'end_time' => $end,
                        'time_range' => trim($start . ($end ? ' - ' . $end : '')),
                        'is_today' => $this->scheduleMatchesToday($schedule->days ?? ''),
                    ];
                })->values();

                $members = $class->assigns->filter(fn($assign) => $assign->user)->map(function ($assign) {
                    $member = $assign->user;
                    $detail = $member->traineeDetails;
                    $expiry = $detail?->membership_expiry_date;
                    $daysLeft = $expiry ? now('Asia/Kolkata')->startOfDay()->diffInDays($expiry, false) : null;

                    return [
                        'id' => $member->id,
                        'name' => $member->name,
                        'phone_number' => $member->phone_number,
                        'plan_name' => $detail && $detail->membership ? $detail->membership->title : 'No Plan',
                        'membership_expiry_date' => $expiry,
                        'days_left' => $daysLeft,
                        'status' => $detail?->status,
                    ];
                })->values();

                return [
                    'id' => $class->id,
                    'title' => $class->title,
                    'fees' => $class->fees,
                    'address' => $class->address,
                    'notes' => $class->notes,
                    'member_count' => (int) ($class->member_count ?? $members->count()),
                    'schedules' => $schedules,
                    'today_schedules' => $schedules->where('is_today', true)->values(),
                    'has_today_schedule' => $schedules->contains('is_today', true),
                    'assigned_members' => $members,
                ];
            })->values();

        $totalMembers = ClassAssign::whereIn('classes_id', $classIds)
            ->where('assign_type', 'member')
            ->distinct()
            ->count('assign_id');

        return $this->success([
            'classes' => $classes,
            'total' => $classes->count(),
            'today_count' => $classes->where('has_today_schedule', true)->count(),
            'total_members' => $totalMembers,
        ]);
    }

    private function cleanTime($time): string
    {
        if (!$time) return '';
        return substr((string) $time, 0, 5);
    }

    private function scheduleMatchesToday(?string $days): bool
    {
        $value = strtolower(trim((string) $days));
        if ($value === '') return false;
        if ($value === 'all' || str_contains($value, 'daily') || str_contains($value, 'everyday') || str_contains($value, 'all day')) return true;

        $todayShort = strtolower(now('Asia/Kolkata')->format('D'));
        $todayFull = strtolower(now('Asia/Kolkata')->format('l'));
        $tokens = preg_split('/[^a-z]+/', $value) ?: [];

        foreach ($tokens as $token) {
            if ($token === '') continue;
            $normalized = substr($token, 0, 3);
            if ($token === $todayFull || $normalized === $todayShort) {
                return true;
            }
        }

        return false;
    }

    private function trainerCanAccessMember(int $trainerId, int $memberId): bool
    {
        return TraineeDetail::where('user_id', $memberId)
            ->where('trainer_assign', $trainerId)
            ->exists();
    }

    private function trainerWorkout(int $trainerId, int $workoutId): ?Workout
    {
        $memberIds = TraineeDetail::where('trainer_assign', $trainerId)->pluck('user_id');
        return Workout::where('id', $workoutId)
            ->whereIn('assign_id', $memberIds)
            ->where('assign_to', 'member')
            ->first();
    }

    private function normalizePhone(string $phone): ?string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) $digits = substr($digits, 2);
        if (strlen($digits) !== 10 || !preg_match('/^[6-9][0-9]{9}$/', $digits)) return null;
        return $digits;
    }

    private function ensureTrainerDetailColumns(): void
    {
        $columns = [
            'specialization' => fn(Blueprint $table) => $table->string('specialization')->nullable()->after('qualification'),
            'experience_years' => fn(Blueprint $table) => $table->integer('experience_years')->default(0)->after('specialization'),
            'joining_date' => fn(Blueprint $table) => $table->date('joining_date')->nullable()->after('experience_years'),
            'salary' => fn(Blueprint $table) => $table->decimal('salary', 10, 2)->default(0)->after('joining_date'),
            'bio' => fn(Blueprint $table) => $table->text('bio')->nullable()->after('salary'),
            'emergency_contact' => fn(Blueprint $table) => $table->string('emergency_contact', 30)->nullable()->after('bio'),
        ];

        foreach ($columns as $column => $callback) {
            if (!Schema::hasColumn('trainer_details', $column)) {
                Schema::table('trainer_details', function (Blueprint $table) use ($callback) {
                    $callback($table);
                });
            }
        }
    }
}
}
