<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\BaseController;
use App\Models\ClassAssign;
use App\Models\TrainerDetail;
use App\Models\TraineeDetail;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class PanelTrainerController extends BaseController
{
    private const DEFAULT_PASSWORD = '1234@paas';

    public function index(Request $request)
    {
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();
        if (!$this->planFeatureEnabled('trainers_enabled', true)) {
            return redirect()->route('panel.dashboard')->with('error', \App\Services\SubscriptionFeatureService::featureLockedMessage('Trainer management'));
        }
        $search = trim($request->get('search', ''));

        $query = User::where('type', 'trainer')
            ->where('parent_id', $pid)
            ->with('trainerDetails');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        $trainers = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        return view('panel.trainers.index', compact('trainers', 'search'));
    }

    public function create()
    {
        if (!$this->planFeatureEnabled('trainers_enabled', true)) {
            return redirect()->route('panel.dashboard')->with('error', \App\Services\SubscriptionFeatureService::featureLockedMessage('Trainer management'));
        }
        return view('panel.trainers.create');
    }

    public function store(Request $request)
    {
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();
        if (!$this->planFeatureEnabled('trainers_enabled', true)) {
            return $this->subscriptionDenied($request, \App\Services\SubscriptionFeatureService::featureLockedMessage('Trainer management'));
        }
        $trainerLimit = $this->planLimit('trainers_limit', 0);
        if ($trainerLimit > 0 && User::where('type', 'trainer')->where('parent_id', $pid)->count() >= $trainerLimit) {
            return $this->subscriptionDenied($request, \App\Services\SubscriptionFeatureService::limitReachedMessage('Trainer', $trainerLimit));
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
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'zip_code' => 'nullable|string|max:20',
            'bio' => 'nullable|string|max:2000',
            'emergency_contact' => 'nullable|string|max:30',
        ]);

        $phone = $this->normalizePhone($data['phone_number']);
        if (!$phone) {
            return $this->trainerError($request, 'Phone must be a valid 10-digit Indian mobile number', 422);
        }

        if (User::where('type', 'trainer')->where('parent_id', $pid)->where('phone_number', $phone)->exists()) {
            return $this->trainerError($request, 'A trainer with this phone number already exists', 422);
        }

        $email = trim($data['email'] ?? '');
        if ($email !== '' && User::where('email', $email)->exists()) {
            return $this->trainerError($request, 'Email already exists', 422);
        }
        if ($email === '') {
            $email = 'trainer_' . $pid . '_' . $phone . '@gymxbook.temp';
        }

        $user = User::create([
            'name' => trim($data['name']),
            'email' => $email,
            'phone_number' => $phone,
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
            'state' => $data['state'] ?? '',
            'country' => $data['country'] ?? '',
            'zip_code' => $data['zip_code'] ?? '',
            'dob' => $data['dob'] ?? null,
            'gender' => $data['gender'] ?? 'male',
            'qualification' => $data['qualification'] ?? '',
            'specialization' => $data['specialization'] ?? '',
            'experience_years' => $data['experience_years'] ?? 0,
            'joining_date' => $data['joining_date'] ?? now('Asia/Kolkata')->toDateString(),
            'salary' => $data['salary'] ?? 0,
            'bio' => $data['bio'] ?? '',
            'emergency_contact' => $data['emergency_contact'] ?? '',
            'parent_id' => $pid,
            'status' => 1,
        ]);

        if ($this->isAjax($request)) {
            return response()->json([
                'success' => true,
                'message' => 'Trainer added successfully. Default password is ' . self::DEFAULT_PASSWORD,
                'trainer' => $this->trainerPayload($user->load('trainerDetails')),
            ]);
        }

        return redirect()
            ->route('panel.trainers.show', $user->id)
            ->with('success', 'Trainer added successfully. Default password is ' . self::DEFAULT_PASSWORD);
    }

    public function show(int $id)
    {
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();
        if (!$this->planFeatureEnabled('trainers_enabled', true)) {
            return redirect()->route('panel.dashboard')->with('error', \App\Services\SubscriptionFeatureService::featureLockedMessage('Trainer management'));
        }

        $trainer = User::where('id', $id)
            ->where('parent_id', $pid)
            ->where('type', 'trainer')
            ->with('trainerDetails')
            ->firstOrFail();

        $assignedMembers = User::where('type', 'trainee')
            ->where('parent_id', $pid)
            ->whereHas('traineeDetails', fn($q) => $q->where('trainer_assign', $trainer->id))
            ->with('traineeDetails.membership')
            ->orderBy('name')
            ->get();

        $assignedClasses = ClassAssign::where('assign_id', $trainer->id)
            ->where('assign_type', 'trainer')
            ->with('gymClass.schedules')
            ->get()
            ->pluck('gymClass')
            ->filter();

        return view('panel.trainers.show', compact('trainer', 'assignedMembers', 'assignedClasses'));
    }

    public function update(Request $request, int $id)
    {
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();
        $this->ensureTrainerDetailColumns();

        $trainer = User::where('id', $id)
            ->where('parent_id', $pid)
            ->where('type', 'trainer')
            ->with('trainerDetails')
            ->firstOrFail();

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
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'zip_code' => 'nullable|string|max:20',
            'bio' => 'nullable|string|max:2000',
            'emergency_contact' => 'nullable|string|max:30',
        ]);

        $phone = $this->normalizePhone($data['phone_number']);
        if (!$phone) {
            return $this->trainerError($request, 'Phone must be a valid 10-digit Indian mobile number', 422);
        }

        if (User::where('type', 'trainer')->where('parent_id', $pid)->where('phone_number', $phone)->where('id', '!=', $trainer->id)->exists()) {
            return $this->trainerError($request, 'A trainer with this phone number already exists', 422);
        }

        $email = trim($data['email'] ?? '');
        if ($email !== '' && User::where('email', $email)->where('id', '!=', $trainer->id)->exists()) {
            return $this->trainerError($request, 'Email already exists', 422);
        }
        if ($email === '') $email = $trainer->email;

        $trainer->update([
            'name' => trim($data['name']),
            'email' => $email,
            'phone_number' => $phone,
        ]);

        $detail = $trainer->trainerDetails ?: new TrainerDetail([
            'user_id' => $trainer->id,
            'trainer_id' => $trainer->id,
            'parent_id' => $pid,
        ]);

        $detail->fill([
            'address' => $data['address'] ?? '',
            'city' => $data['city'] ?? '',
            'state' => $data['state'] ?? '',
            'country' => $data['country'] ?? '',
            'zip_code' => $data['zip_code'] ?? '',
            'dob' => $data['dob'] ?? null,
            'gender' => $data['gender'] ?? 'male',
            'qualification' => $data['qualification'] ?? '',
            'specialization' => $data['specialization'] ?? '',
            'experience_years' => $data['experience_years'] ?? 0,
            'joining_date' => $data['joining_date'] ?? null,
            'salary' => $data['salary'] ?? 0,
            'bio' => $data['bio'] ?? '',
            'emergency_contact' => $data['emergency_contact'] ?? '',
            'status' => $trainer->is_active ? 1 : 0,
        ]);
        $detail->save();

        if ($this->isAjax($request)) {
            return response()->json(['success' => true, 'message' => 'Trainer updated', 'trainer' => $this->trainerPayload($trainer->fresh('trainerDetails'))]);
        }

        return redirect()->route('panel.trainers.show', $trainer->id)->with('success', 'Trainer updated');
    }

    public function toggle(int $id)
    {
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();
        $trainer = User::where('id', $id)->where('parent_id', $pid)->where('type', 'trainer')->with('trainerDetails')->firstOrFail();

        $newStatus = !$trainer->is_active;
        $trainer->update(['is_active' => $newStatus]);
        if ($trainer->trainerDetails) {
            $trainer->trainerDetails->update(['status' => $newStatus ? 1 : 0]);
        }

        if ($this->isAjax(request())) {
            return response()->json([
                'success' => true,
                'message' => 'Trainer status updated',
                'is_active' => $newStatus,
                'trainer' => $this->trainerPayload($trainer->fresh('trainerDetails')),
            ]);
        }

        return redirect()->route('panel.trainers.index')->with('success', 'Trainer status updated');
    }

    public function destroy(int $id)
    {
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();
        $trainer = User::where('id', $id)->where('parent_id', $pid)->where('type', 'trainer')->with('trainerDetails')->firstOrFail();

        try {
            if ($trainer->trainerDetails) {
                $trainer->trainerDetails->delete();
            }

            TraineeDetail::where('trainer_assign', $trainer->id)->where('parent_id', $pid)->update(['trainer_assign' => 0]);
            ClassAssign::where('assign_id', $trainer->id)->where('assign_type', 'trainer')->delete();

            $trainerName = $trainer->name;
            $trainer->delete();

            if ($this->isAjax(request())) {
                return response()->json(['success' => true, 'message' => "Trainer \"{$trainerName}\" deleted successfully"]);
            }

            return redirect()->route('panel.trainers.index')->with('success', 'Trainer deleted successfully');
        } catch (\Throwable $e) {
            if ($this->isAjax(request())) {
                return response()->json(['success' => false, 'error' => 'Failed to delete trainer'], 500);
            }
            return back()->with('error', 'Failed to delete trainer');
        }
    }

    private function normalizePhone(string $phone): ?string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        }
        if (strlen($digits) !== 10 || !preg_match('/^[6-9][0-9]{9}$/', $digits)) {
            return null;
        }
        return $digits;
    }

    private function trainerPayload(User $trainer): array
    {
        $detail = $trainer->trainerDetails;
        return [
            'id' => $trainer->id,
            'name' => $trainer->name,
            'email' => $trainer->email,
            'phone_number' => $trainer->phone_number,
            'is_active' => (bool) $trainer->is_active,
            'qualification' => $detail?->qualification ?? '',
            'specialization' => $detail?->specialization ?? '',
            'city' => $detail?->city ?? '',
        ];
    }

    private function trainerError(Request $request, string $message, int $status = 400)
    {
        if ($this->isAjax($request)) {
            return response()->json(['success' => false, 'error' => $message], $status);
        }
        return back()->withInput()->with('error', $message);
    }

    private function isAjax(Request $request): bool
    {
        return $request->ajax()
            || $request->wantsJson()
            || $request->header('X-Requested-With') === 'XMLHttpRequest'
            || $request->expectsJson();
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
