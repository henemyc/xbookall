<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\TraineeDetail;
use App\Models\Membership;
use App\Models\GymClass;
use App\Models\ClassAssign;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\AppNotification;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class MemberController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $parentIds = $this->getGymParentIds();
        $search = $request->get('search', '');
        $status = $request->get('status', '');
        $page = max(1, intval($request->get('page', 1)));
        $perPage = 20;

        $query = User::where('type', 'trainee')
            ->whereIn('parent_id', $parentIds)
            ->with(['traineeDetails.membership']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        if ($status === 'active') {
            $query->whereHas('traineeDetails', function ($q) {
                $q->where('membership_expiry_date', '>=', now()->toDateString());
            });
        } elseif ($status === 'expired') {
            $query->whereHas('traineeDetails', function ($q) {
                $q->where('membership_expiry_date', '<', now()->toDateString())
                  ->whereNotNull('membership_expiry_date');
            });
        } elseif ($status === 'frozen') {
            $query->whereHas('traineeDetails', function ($q) {
                $q->where('status', 3);
            });
        } elseif ($status === 'expiring_7') {
            $query->whereHas('traineeDetails', function ($q) {
                $q->whereBetween('membership_expiry_date', [now()->toDateString(), now()->addDays(7)->toDateString()]);
            });
        } elseif ($status === 'expiring_14') {
            $query->whereHas('traineeDetails', function ($q) {
                $q->whereBetween('membership_expiry_date', [now()->toDateString(), now()->addDays(14)->toDateString()]);
            });
        }

        $total = $query->count();

        $members = $query->orderBy('created_at', 'desc')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        $members->each(function ($member) {
            $member->makeHidden(['password', 'remember_token', 'twofa_secret']);
            if ($member->traineeDetails) {
                $member->trainee_status = $member->traineeDetails->status;
                $member->membership_expiry_date = $member->traineeDetails->membership_expiry_date;
                $member->plan_name = $member->traineeDetails->membership ? $member->traineeDetails->membership->title : null;
            }
        });

        return $this->success([
            'members' => $members,
            'total' => $total,
            'page' => $page,
            'pages' => ceil($total / $perPage),
        ]);
    }

    /**
     * FULL STORE: Add new member with strict gym scoping + WhatsApp welcome
     */
    public function store(Request $request): JsonResponse
    {
        $pid = $this->getParentId(); // STRICT write scoping (current gym owner id)

        $memberLimit = $this->planLimit('members_limit', 0);
        if ($memberLimit > 0 && User::where('type', 'trainee')->where('parent_id', $pid)->count() >= $memberLimit) {
            return $this->error(\App\Services\SubscriptionFeatureService::limitReachedMessage('Member', $memberLimit), 402);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone_number' => 'required|string|max:20',
            'address' => 'nullable|string',
            'city' => 'nullable|string',
            'gender' => 'nullable|in:male,female,other',
            'dob' => 'nullable|date',
            'membership_plan' => 'required|integer|exists:memberships,id',
            'trainer_assign' => 'nullable|integer',
            'class_id' => 'nullable|integer',
            'category' => 'nullable|integer',
            'membership_start_date' => 'nullable|date',
            'membership_expiry_date' => 'nullable|date',
            'paid_amount' => 'nullable|numeric|min:0',
            'payments' => 'nullable|array',
            'payments.*.amount' => 'required_with:payments|numeric|min:0.01',
            'payments.*.payment_type' => 'nullable|string|max:30',
            'payments.*.payment_date' => 'nullable|date',
            'registration_fee' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|string',
            'fitness_goal' => 'nullable|string|max:255',
        ]);

        $email = trim((string) ($validated['email'] ?? ''));
        if ($email === '') {
            $email = 'member_' . time() . '_' . random_int(100, 999) . '@gymxbook.temp';
        }

        // Check for duplicate email within gym scope only when real email provided.
        if (!str_ends_with($email, '@gymxbook.temp')) {
            $existing = User::where('email', $email)
                ->where('parent_id', $pid)
                ->first();

            if ($existing) {
                return $this->error('A member with this email already exists', 400);
            }
        }

        // Phone uniqueness check (optional but recommended)
        if (!empty($validated['phone_number'])) {
            $phoneDigits = preg_replace('/\D/', '', $validated['phone_number']);
            $phoneExists = User::where('phone_number', $phoneDigits)
                ->where('parent_id', $pid)
                ->exists();

            if ($phoneExists) {
                return $this->error('A member with this phone number already exists', 400);
            }
        }

        DB::beginTransaction();

        try {
            // Create the trainee user
            $user = User::create([
                'name' => $validated['name'],
                'email' => $email,
                'phone_number' => preg_replace('/\D/', '', $validated['phone_number'] ?? ''),
                'password' => Hash::make('member123'), // Default; can be updated later
                'type' => 'trainee',
                'parent_id' => $pid,           // STRICT: belongs to this gym
                'is_active' => true,
            ]);

            // Resolve membership plan
            $membership = Membership::find($validated['membership_plan']);
            if (!$membership) {
                throw new \Exception('Selected membership plan not found');
            }

            // Calculate expiry if not provided
            $startDate = $validated['membership_start_date'] ?? now()->toDateString();
            $expiryDate = $validated['membership_expiry_date'] ?? $this->calculateExpiry($startDate, $membership->package ?? 'monthly');

            // Create TraineeDetail
            $trainee = TraineeDetail::create([
                'user_id' => $user->id,
                'trainee_id' => $user->id,
                'parent_id' => $pid,
                'membership_plan' => $membership->id,
                'membership_start_date' => $startDate,
                'membership_expiry_date' => $expiryDate,
                'trainer_assign' => $validated['trainer_assign'] ?? null,
                'category' => $validated['category'] ?? null,
                'address' => $validated['address'] ?? null,
                'city' => $validated['city'] ?? null,
                'gender' => $validated['gender'] ?? 'male',
                'dob' => $validated['dob'] ?? null,
                'fitness_goal' => $validated['fitness_goal'] ?? $request->input('fitness_goal') ?? '',   // FIX: always provide value (even empty)
                'status' => 1, // active
            ]);

            // === AUTO-CREATE INVOICE — same mapping as working PWA/api.php flow ===
            // Total = membership plan amount + class fee + registration fee.
            // Paid = min(user entered paid_amount, total). Invoice item amounts must
            // always be the actual item prices, not the paid amount.
            $classId = intval($validated['class_id'] ?? 0);
            $classInfo = null;
            $classAmount = 0;
            $className = '';

            if ($classId > 0) {
                $classInfo = GymClass::where('id', $classId)
                    ->whereIn('parent_id', $this->getGymParentIds())
                    ->first();
                if ($classInfo) {
                    $classAmount = floatval($classInfo->fees ?? 0);
                    $className = $classInfo->title ?? '';

                    // Same as PWA: assign class to member when selected
                    ClassAssign::create([
                        'classes_id' => $classId,
                        'assign_id' => $user->id,
                        'assign_type' => 'member',
                    ]);
                }
            }

            $planAmount = floatval($membership->amount ?? 0);
            $regFee = floatval($validated['registration_fee'] ?? 0);
            $discountAmount = floatval($validated['discount_amount'] ?? 0);
            $subtotalAmount = $planAmount + $classAmount + $regFee;
            if ($discountAmount > $subtotalAmount) {
                throw new \Exception('Discount cannot exceed invoice subtotal');
            }
            $totalAmount = max(0, $subtotalAmount - $discountAmount);

            $paymentRows = collect($validated['payments'] ?? [])
                ->map(function ($payment) use ($startDate) {
                    return [
                        'amount' => round((float) ($payment['amount'] ?? 0), 2),
                        'payment_type' => $payment['payment_type'] ?? 'cash',
                        'payment_date' => $payment['payment_date'] ?? $startDate,
                    ];
                })
                ->filter(fn($payment) => $payment['amount'] > 0)
                ->values()
                ->all();

            $paidAmount = array_sum(array_column($paymentRows, 'amount'));
            if ($paidAmount <= 0 && (float) ($validated['paid_amount'] ?? 0) > 0) {
                $paidAmount = min((float) $validated['paid_amount'], $totalAmount);
                $paymentRows[] = [
                    'amount' => $paidAmount,
                    'payment_type' => $validated['payment_method'] ?? 'cash',
                    'payment_date' => $startDate,
                ];
            }

            if ($paidAmount > $totalAmount) {
                throw new \Exception('Total paid amount cannot exceed invoice total');
            }

            $invoice = null;
            if ($totalAmount > 0) {
                $maxInvoiceId = Invoice::whereIn('parent_id', $this->getGymParentIds())->max('invoice_id') ?? 0;
                $nextInvoiceNumber = $maxInvoiceId + 1;

                $invoiceNotes = [];
                if ($membership) $invoiceNotes[] = ($membership->title ?? 'Membership') . ' Membership';
                if ($classInfo) $invoiceNotes[] = $className . ' Class';
                if ($regFee > 0) $invoiceNotes[] = 'Registration Fee';

                $invoiceStatus = $paidAmount >= $totalAmount ? 'paid' : ($paidAmount > 0 ? 'partial' : 'unpaid');

                $invoice = Invoice::create([
                    'invoice_id'       => $nextInvoiceNumber,
                    'user_id'          => $user->id,
                    'parent_id'        => $pid,
                    'invoice_date'     => $startDate,
                    'invoice_due_date' => $expiryDate,
                    'status'           => $invoiceStatus,
                    'notes'            => implode(' + ', $invoiceNotes),
                ]);

                // Same item mapping as PWA/api.php
                if ($regFee > 0) {
                    InvoiceItem::create([
                        'invoice_id'  => $invoice->id,
                        'type_id'     => 0,
                        'title'       => 'Registration Fee',
                        'amount'      => $regFee,
                        'description' => 'One-time registration fee',
                    ]);
                }

                if ($membership) {
                    InvoiceItem::create([
                        'invoice_id'  => $invoice->id,
                        'type_id'     => $membership->id,
                        'title'       => ($membership->title ?? 'Membership') . ' Membership',
                        'amount'      => $planAmount,
                        'description' => $membership->package ?? '',
                    ]);
                }

                if ($classInfo) {
                    InvoiceItem::create([
                        'invoice_id'  => $invoice->id,
                        'type_id'     => $classId,
                        'title'       => $className . ' Class',
                        'amount'      => $classAmount,
                        'description' => 'Class assignment',
                    ]);
                }

                if ($discountAmount > 0) {
                    InvoiceItem::create([
                        'invoice_id'  => $invoice->id,
                        'type_id'     => 0,
                        'title'       => 'Discount',
                        'amount'      => -1 * $discountAmount,
                        'description' => 'Discount applied while adding member',
                    ]);
                }

                foreach ($paymentRows as $paymentRow) {
                    InvoicePayment::create([
                        'invoice_id'   => $invoice->id,
                        'transaction_id' => '',
                        'amount'       => $paymentRow['amount'],
                        'payment_type' => $paymentRow['payment_type'],
                        'payment_date' => $paymentRow['payment_date'],
                        'parent_id'    => $pid,
                        'notes'        => 'Initial payment',
                    ]);
                }
            }

            // === ULTIMATE BULLETPROOF NOTIFICATION (pure raw SQL - 5 columns max) ===
            // We never let Laravel/Eloquent add any columns.
            // This is the final safe version that will work on any table.
            try {
                \DB::insert(
                    "INSERT INTO app_notifications (parent_id, user_id, title, message, type) VALUES (?, ?, ?, ?, ?)",
                    [
                        $pid,
                        $user->id,
                        'New Member Added',
                        $user->name . ' joined via ' . ($validated['payment_method'] ?? 'cash'),
                        'member'
                    ]
                );
            } catch (\Throwable $e) {
                \Log::warning('Notification skipped (safe): ' . $e->getMessage());
            }

            DB::commit();

            // =====================================================
            // WHATSAPP WELCOME MESSAGE (non-blocking)
            // =====================================================
            try {
                $whatsapp = new WhatsAppService();
                if ($whatsapp->isConfigured() && !empty($user->phone_number)) {
                    $gymName = \App\Models\Setting::getValue('company_name', $pid, 'Your Gym');
                    $whatsapp->sendMemberWelcome(
                        $user->phone_number,
                        $user->name,
                        $gymName,
                        $expiryDate,
                        $pid
                    );
                }
            } catch (\Exception $e) {
                \Log::info('WhatsApp welcome failed (non-blocking): ' . $e->getMessage());
            }

            // Return rich response
            $user->load('traineeDetails.membership');

            return $this->success([
                'member' => $user,
                'invoice' => $invoice,
                'invoice_total' => $totalAmount ?? 0,
                'invoice_paid' => $paidAmount ?? 0,
                'invoice_due' => isset($totalAmount, $paidAmount) ? max(0, $totalAmount - $paidAmount) : 0,
                'message' => 'Member added successfully. Welcome WhatsApp sent (if configured).',
                'whatsapp_sent' => !empty($user->phone_number),
            ], 'Member created', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to add member: ' . $e->getMessage(), 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        $parentIds = $this->getGymParentIds();
        $currentUser = $this->currentUser();

        if ($currentUser->type === 'trainee' && $currentUser->id != $id) {
            return $this->error('Forbidden', 403);
        }

        $user = User::where('id', $id)
            ->whereIn('parent_id', $parentIds)
            ->with(['traineeDetails.membership', 'trainerDetails'])
            ->first();

        if (!$user) {
            return $this->error('Member not found', 404);
        }

        if ($user->traineeDetails) {
            $user->trainee_status = $user->traineeDetails->status;
            $user->membership_expiry_date = $user->traineeDetails->membership_expiry_date;
            $user->plan_name = $user->traineeDetails->membership ? $user->traineeDetails->membership->title : null;
        }

        // === RICH DATA for Member Details screen (invoices, transactions, health, attendance) ===
        $invoices = \App\Models\Invoice::where('user_id', $id)
            ->whereIn('parent_id', $parentIds)
            ->with('items', 'payments')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        $transactions = \App\Models\InvoicePayment::whereHas('invoice', function($q) use ($id, $parentIds) {
                $q->where('user_id', $id)->whereIn('parent_id', $parentIds);
            })
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        $healthRecords = \App\Models\Health::where('user_id', $id)
            ->orderBy('measurement_date', 'desc')
            ->limit(10)
            ->get();

        $attendanceHistory = \App\Models\Attendance::where('user_id', $id)
            ->whereIn('parent_id', $parentIds)
            ->orderBy('date', 'desc')
            ->limit(15)
            ->get()
            ->map(function($a) {
                return [
                    'date' => $a->date,
                    'checked_in_time' => $a->checked_in_time,
                    'checked_out_time' => $a->checked_out_time,
                    'notes' => $a->notes,
                ];
            });

        $userData = $user->toArray();
        $userData['invoices'] = $invoices;
        $userData['transactions'] = $transactions;
        $userData['health_records'] = $healthRecords;
        $userData['attendance_history'] = $attendanceHistory;

        return $this->success(['member' => $userData]);
    }

    /**
     * PERMANENT SAFE UPDATE
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $parentIds = $this->getGymParentIds();
        $pid = $this->getParentId();

        $user = User::where('id', $id)
            ->whereIn('parent_id', $parentIds)
            ->first();

        if (!$user) {
            return $this->error('Member not found', 404);
        }

        DB::beginTransaction();
        try {
            $userUpdates = $request->only(['name', 'email', 'phone_number', 'is_active']);
            if (!empty($userUpdates)) {
                $user->update($userUpdates);
            }

            $trainee = $user->traineeDetails;
            $traineeData = [];

            $fields = ['address', 'city', 'gender', 'membership_plan', 'trainer_assign', 'membership_start_date', 'membership_expiry_date', 'category', 'status'];
            foreach ($fields as $f) {
                if ($request->has($f)) {
                    $traineeData[$f] = $request->input($f);
                }
            }

            if (!empty($traineeData)) {
                if ($trainee) {
                    $trainee->update($traineeData);
                } else {
                    if ($request->has('membership_plan')) {
                        $traineeData['user_id'] = $id;
                        $traineeData['trainee_id'] = $id;
                        $traineeData['parent_id'] = $pid;
                        TraineeDetail::create($traineeData);
                    }
                }
            }

            DB::commit();
            return $this->success([], 'Member updated successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to update: ' . $e->getMessage(), 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        $parentIds = $this->getGymParentIds();
        $user = User::where('id', $id)->whereIn('parent_id', $parentIds)->first();
        if (!$user) return $this->error('Member not found', 404);

        $user->update(['is_active' => !$user->is_active]);
        return $this->success([], 'Member status toggled');
    }

    public function hardDelete(int $id): JsonResponse
    {
        $parentIds = $this->getGymParentIds();
        $user = User::where('id', $id)->whereIn('parent_id', $parentIds)->first();
        if (!$user) return $this->error('Member not found', 404);

        $user->traineeDetails?->delete();
        $user->delete();

        return $this->success([], 'Member permanently deleted');
    }

    public function renew(Request $request, int $id): JsonResponse
    {
        $parentIds = $this->getGymParentIds();
        $pid = $this->getParentId();

        $user = User::where('id', $id)
            ->whereIn('parent_id', $parentIds)
            ->with('traineeDetails')
            ->first();

        if (!$user || !$user->traineeDetails) {
            return $this->error('Member not found', 404);
        }

        $validated = $request->validate([
            'membership_plan' => 'required|integer|exists:memberships,id',
            'membership_start_date' => 'nullable|date',
            'membership_expiry_date' => 'nullable|date',
            'paid_amount' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|string',
        ]);

        $membership = Membership::find($validated['membership_plan']);
        if (!$membership) {
            return $this->error('Plan not found', 404);
        }

        $start = $validated['membership_start_date'] ?? now()->toDateString();
        $expiry = $validated['membership_expiry_date'] ?? $this->calculateExpiry($start, $membership->package ?? 'monthly');

        $user->traineeDetails->update([
            'membership_plan' => $membership->id,
            'membership_start_date' => $start,
            'membership_expiry_date' => $expiry,
            'status' => 1,
        ]);

        // Renewal invoice must be dated today, with no due date. The invoice
        // item is the plan price; payment can be full or partial.
        $paid = min((float) ($validated['paid_amount'] ?? 0), (float) ($membership->amount ?? 0));
        if ($paid > 0 || (float) ($membership->amount ?? 0) > 0) {
            $maxInvoiceId = Invoice::whereIn('parent_id', $this->getGymParentIds())->max('invoice_id') ?? 0;
            $nextInvoiceNumber = $maxInvoiceId + 1;
            $planAmount = (float) ($membership->amount ?? 0);
            $status = $paid >= $planAmount && $planAmount > 0 ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');

            $invoice = Invoice::create([
                'invoice_id'       => $nextInvoiceNumber,
                'user_id'          => $user->id,
                'parent_id'        => $pid,
                'invoice_date'     => now('Asia/Kolkata')->toDateString(),
                'invoice_due_date' => null,
                'status'           => $status,
                'notes'            => 'Membership renewal',
            ]);

            InvoiceItem::create([
                'invoice_id'  => $invoice->id,
                'type_id'     => $membership->id,
                'title'       => ($membership->title ?? 'Membership') . ' (Renewal)',
                'amount'      => $planAmount,
                'description' => 'Renewal payment',
            ]);

            if ($paid > 0) {
                InvoicePayment::create([
                    'invoice_id'   => $invoice->id,
                    'amount'       => $paid,
                    'payment_type' => $validated['payment_method'] ?? 'cash',
                    'payment_date' => now('Asia/Kolkata')->toDateString(),
                    'parent_id'    => $pid,
                    'notes'        => 'Renewal payment',
                ]);
            }
        }

        // NUCLEAR SAFE notification (pure raw SQL)
        try {
            \DB::insert(
                "INSERT INTO app_notifications (parent_id, user_id, title, message, type) VALUES (?, ?, ?, ?, ?)",
                [
                    $pid,
                    $user->id,
                    'Membership Renewed',
                    $user->name . ' renewed membership',
                    'member'
                ]
            );
        } catch (\Throwable $e) {}

        // Send WhatsApp renewal message
        try {
            $whatsapp = new WhatsAppService();
            if ($whatsapp->isConfigured() && !empty($user->phone_number)) {
                $gymName = \App\Models\Setting::getValue('company_name', $pid, 'Your Gym');
                $whatsapp->sendMemberRenew(
                    $user->phone_number,
                    $user->name,
                    $gymName,
                    $expiry,
                    $paid,
                    $pid
                );
            }
        } catch (\Exception $e) {
            \Log::info('Renewal WhatsApp failed (non-blocking): ' . $e->getMessage());
        }

        $user->load('traineeDetails.membership');

        return $this->success([
            'member' => $user,
            'message' => 'Membership renewed successfully',
            'whatsapp_sent' => !empty($user->phone_number),
        ]);
    }

    public function freeze(Request $request, int $id): JsonResponse
    {
        $parentIds = $this->getGymParentIds();
        $user = User::where('id', $id)->whereIn('parent_id', $parentIds)->first();
        if (!$user || !$user->traineeDetails) return $this->error('Member not found', 404);

        $user->traineeDetails->update(['status' => 3]);
        return $this->success([], 'Membership frozen');
    }

    public function unfreeze(int $id): JsonResponse
    {
        $parentIds = $this->getGymParentIds();
        $user = User::where('id', $id)->whereIn('parent_id', $parentIds)->first();
        if (!$user || !$user->traineeDetails) return $this->error('Member not found', 404);

        $user->traineeDetails->update(['status' => 1]);
        return $this->success([], 'Membership unfrozen');
    }

    private function calculateExpiry(string $startDate, string $package): string
    {
        $start = \Carbon\Carbon::parse($startDate);
        return match (strtolower($package)) {
            'weekly', '1 week', '7 days' => $start->addDays(6)->toDateString(),
            'monthly', '1 month' => $start->addMonth()->subDay()->toDateString(),
            'quarterly', '3 months' => $start->addMonths(3)->subDay()->toDateString(),
            'half-yearly', '6 months' => $start->addMonths(6)->subDay()->toDateString(),
            'yearly', '12 months', '1 year' => $start->addYear()->subDay()->toDateString(),
            default => $start->addMonth()->subDay()->toDateString(),
        };
    }
}
