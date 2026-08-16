<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\BaseController;
use App\Models\User;
use App\Models\TraineeDetail;
use App\Models\Membership;
use App\Models\GymClass;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\ClassAssign;
use App\Models\FreezeMembershipLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Services\PhoneIdentityService;

class PanelMemberController extends BaseController
{
    public function index(Request $request)
    {
        $pid = $this->getParentId();
        $parentIds = $this->panelParentIds($pid);
        $search = $request->get('search', '');
        $status = $request->get('status', '');

        $query = User::where('type', 'trainee')
            ->whereIn('parent_id', $parentIds)
            ->with('traineeDetails.membership');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        if ($status === 'active') {
            $query->whereHas('traineeDetails', function ($q) {
                $q->where('membership_expiry_date', '>=', date('Y-m-d'));
            });
        } elseif ($status === 'expired') {
            $query->whereHas('traineeDetails', function ($q) {
                $q->where('membership_expiry_date', '<', date('Y-m-d'));
            });
        }

        $members = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        $trainers = User::where('type', 'trainer')->whereIn('parent_id', $parentIds)->where('is_active', true)->orderBy('name')->get();
        $classes = GymClass::whereIn('parent_id', $parentIds)->orderBy('title')->get();
        $plans = Membership::whereIn('parent_id', $parentIds)->orderBy('amount')->get();

        // AJAX pagination / filter support — return raw HTML (never load the partial view)
        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            $tableHtml = $this->renderMembersTableHtml($members);
            return response($tableHtml)
                ->header('Content-Type', 'text/html');
        }

        return view('panel.members.index', compact('members', 'search', 'status', 'trainers', 'classes', 'plans'));
    }

    public function show(int $id)
    {
        $pid = $this->getParentId();
        $parentIds = $this->panelParentIds($pid);

        $member = User::where('id', $id)
            ->whereIn('parent_id', $parentIds)
            ->where('type', 'trainee')
            ->with('traineeDetails.membership')
            ->firstOrFail();

        return view('panel.members.show', compact('member'));
    }

    /** 
     * Store member - Returns JSON 
     */
    public function store(Request $request)
    {
        $pid = $this->getParentId();
        $parentIds = $this->panelParentIds($pid);

        $memberLimit = $this->planLimit('members_limit', 0);
        if ($memberLimit > 0 && User::where('type', 'trainee')->where('parent_id', $pid)->count() >= $memberLimit) {
            return $this->subscriptionDenied($request, \App\Services\SubscriptionFeatureService::limitReachedMessage('Member', $memberLimit));
        }

        Log::info('Member store called', ['data' => $request->all(), 'pid' => $pid]);

        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'phone_number' => 'required|string|max:20',
                'membership_plan' => 'required|integer',
            ]);

            $email = trim($request->input('email', ''));
            if (empty($email)) {
                $email = 'member_' . time() . '_' . rand(100, 999) . '@gymxbook.temp';
            }

            if ($request->input('email') && User::where('email', $request->input('email'))->exists()) {
                return response()->json(['success' => false, 'error' => 'Email already exists'], 400);
            }

            try {
                $phoneDigits = app(PhoneIdentityService::class)->requireAvailable($request->input('phone_number'));
            } catch (\InvalidArgumentException $e) {
                return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
            }

            DB::beginTransaction();

            $user = User::create([
                'name' => $request->input('name'),
                'email' => $email,
                'phone_number' => $phoneDigits,
                'type' => 'trainee',
                'password' => Hash::make('123456789'),
                'parent_id' => $pid,
                'is_active' => true,
            ]);

            $plan = Membership::find($request->input('membership_plan'));
            $planAmount = $plan ? floatval($plan->amount) : 0;
            $startDate = $request->input('membership_start_date', date('Y-m-d'));
            $expiryDate = $request->input('membership_expiry_date');
            if (!$expiryDate && $plan) {
                $expiryDate = $this->calculateExpiry($startDate, $plan->package);
            }

            TraineeDetail::create([
                'user_id' => $user->id,
                'trainee_id' => $user->id,
                'address' => $request->input('address', ''),
                'city' => $request->input('city', ''),
                'state' => $request->input('state', ''),
                'country' => $request->input('country', ''),
                'zip_code' => $request->input('zip_code', ''),
                'dob' => $request->input('dob'),
                'age' => $request->input('age', 0),
                'gender' => $request->input('gender', ''),
                'fitness_goal' => $request->input('fitness_goal', ''),
                'membership_plan' => $request->input('membership_plan'),
                'trainer_assign' => $request->input('trainer_assign', 0),
                'membership_start_date' => $startDate,
                'membership_expiry_date' => $expiryDate,
                'category' => $request->input('category', 0),
                'parent_id' => $pid,
                'status' => 1,
            ]);

            $classId = $request->input('class_id', 0);
            $classAmount = 0;
            $className = '';
            if ($classId > 0) {
                $class = GymClass::find($classId);
                if ($class) {
                    $classAmount = floatval($class->fees);
                    $className = $class->title;
                    ClassAssign::create(['classes_id' => $classId, 'assign_id' => $user->id, 'assign_type' => 'member']);
                }
            }

            $registrationFee = floatval($request->input('registration_fee', 0));
            $totalAmount = $planAmount + $classAmount + $registrationFee;
            $paidAmount = min(floatval($request->input('paid_amount', 0)), $totalAmount);

            if ($totalAmount > 0) {
                $maxInvoiceId = Invoice::whereIn('parent_id', $parentIds)->max('invoice_id') ?? 0;
                $invoiceId = $maxInvoiceId + 1;

                $notes = [];
                if ($plan) $notes[] = $plan->title . ' Membership';
                if ($classId > 0 && $className) $notes[] = $className . ' Class';
                if ($registrationFee > 0) $notes[] = 'Registration Fee';

                $invoiceStatus = $paidAmount >= $totalAmount ? 'paid' : ($paidAmount > 0 ? 'partial' : 'unpaid');

                $invoice = Invoice::create([
                    'invoice_id' => $invoiceId,
                    'user_id' => $user->id,
                    'invoice_date' => $startDate,
                    'invoice_due_date' => $expiryDate,
                    'status' => $invoiceStatus,
                    'notes' => implode(' + ', $notes),
                    'parent_id' => $pid,
                ]);

                if ($registrationFee > 0) {
                    InvoiceItem::create(['invoice_id' => $invoice->id, 'type_id' => 0, 'title' => 'Registration Fee', 'amount' => $registrationFee, 'description' => '']);
                }
                if ($plan) {
                    InvoiceItem::create(['invoice_id' => $invoice->id, 'type_id' => $request->input('membership_plan'), 'title' => $plan->title . ' Membership', 'amount' => $planAmount, 'description' => $plan->package ?? '']);
                }
                if ($classId > 0 && $className) {
                    InvoiceItem::create(['invoice_id' => $invoice->id, 'type_id' => $classId, 'title' => $className . ' Class', 'amount' => $classAmount, 'description' => '']);
                }

                if ($paidAmount > 0) {
                    InvoicePayment::create([
                        'invoice_id' => $invoice->id,
                        'transaction_id' => '',
                        'payment_type' => $request->input('payment_method', 'cash'),
                        'amount' => $paidAmount,
                        'payment_date' => $startDate,
                        'parent_id' => $pid,
                        'notes' => 'Initial payment',
                    ]);
                }
            }

            DB::commit();

            Log::info('Member created successfully', ['id' => $user->id, 'name' => $user->name, 'plan' => $plan ? $plan->title : 'None']);

            $planName = $plan ? $plan->title : 'No Plan';

            return response()->json([
                'success' => true,
                'message' => 'Member added successfully',
                'member' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone_number' => $user->phone_number,
                    'membership_expiry_date' => $expiryDate,
                    'plan_name' => $planName,
                    'is_active' => true,
                ],
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Member validation failed', ['errors' => $e->errors()]);
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Member store failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Failed: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, int $id)
    {
        $pid = $this->getParentId();
        $parentIds = $this->panelParentIds($pid);
        $member = User::where('id', $id)->whereIn('parent_id', $parentIds)->firstOrFail();

        if ($request->email) {
            $existing = User::where('email', $request->email)->where('id', '!=', $id)->first();
            if ($existing) return back()->with('error', 'Email already in use');
        }

        DB::beginTransaction();
        try {
            $member->update([
                'name' => $request->name ?? $member->name,
                'email' => $request->email ?? $member->email,
                'phone_number' => $request->has('phone_number')
                    ? app(PhoneIdentityService::class)->requireAvailable($request->phone_number, (int) $member->id)
                    : $member->phone_number,
            ]);

            if ($member->traineeDetails) {
                $member->traineeDetails->update([
                    'trainer_assign' => $request->trainer_assign ?? $member->traineeDetails->trainer_assign,
                    'fitness_goal' => $request->fitness_goal ?? $member->traineeDetails->fitness_goal,
                    'gender' => $request->gender ?? $member->traineeDetails->gender,
                    'dob' => $request->dob ?? $member->traineeDetails->dob,
                    'address' => $request->address ?? $member->traineeDetails->address,
                    'city' => $request->city ?? $member->traineeDetails->city,
                    'state' => $request->state ?? $member->traineeDetails->state,
                    'zip_code' => $request->zip_code ?? $member->traineeDetails->zip_code,
                ]);
            }

            DB::commit();
            return redirect()->route('panel.members.show', $id)->with('success', 'Member updated');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to update: ' . $e->getMessage());
        }
    }

    public function destroy(int $id)
    {
        $pid = $this->getParentId();
        $parentIds = $this->panelParentIds($pid);
        $member = User::where('id', $id)->whereIn('parent_id', $parentIds)->firstOrFail();

        if (request()->input('hard_delete') == '1') {
            DB::beginTransaction();
            try {
                $invoiceIds = Invoice::where('user_id', $id)->whereIn('parent_id', $parentIds)->pluck('id')->toArray();
                if (!empty($invoiceIds)) {
                    InvoiceItem::whereIn('invoice_id', $invoiceIds)->delete();
                    InvoicePayment::whereIn('invoice_id', $invoiceIds)->delete();
                }
                Invoice::where('user_id', $id)->whereIn('parent_id', $parentIds)->delete();
                \App\Models\Attendance::where('user_id', $id)->whereIn('parent_id', $parentIds)->delete();
                \App\Models\Health::where('user_id', $id)->whereIn('parent_id', $parentIds)->delete();
                \App\Models\Workout::where('assign_id', $id)->whereIn('parent_id', $parentIds)->delete();
                ClassAssign::where('assign_id', $id)->delete();
                $lockerIds = \App\Models\AssignLocker::where('user_id', $id)->whereNull('end_date')->pluck('locker_id')->toArray();
                if (!empty($lockerIds)) \App\Models\Locker::whereIn('id', $lockerIds)->update(['available' => true]);
                \App\Models\AssignLocker::where('user_id', $id)->delete();
                FreezeMembershipLog::where('trainee_id', $id)->delete();
                TraineeDetail::where('user_id', $id)->delete();
                $member->delete();

                DB::commit();
                return redirect()->route('panel.members.index')->with('success', 'Member deleted permanently');
            } catch (\Exception $e) {
                DB::rollBack();
                return back()->with('error', 'Failed to delete member');
            }
        }

        $member->update(['is_active' => !$member->is_active]);
        return redirect()->route('panel.members.index')->with('success', 'Member status updated');
    }

    public function deleteFreeze(int $id, int $freezeId)
    {
        $pid = $this->getParentId();
        $parentIds = $this->panelParentIds($pid);
        $member = User::where('id', $id)->whereIn('parent_id', $parentIds)->firstOrFail();

        $freeze = FreezeMembershipLog::where('id', $freezeId)->where('trainee_id', $id)->first();
        if (!$freeze) return back()->with('error', 'Freeze record not found');

        DB::beginTransaction();
        try {
            if ($member->traineeDetails && $member->traineeDetails->membership_expiry_date) {
                $currentExpiry = \Carbon\Carbon::parse($member->traineeDetails->membership_expiry_date);
                $newExpiry = $currentExpiry->subDays($freeze->freeze_days)->toDateString();
                $member->traineeDetails->update(['membership_expiry_date' => $newExpiry]);
            }

            $isActiveFreeze = now()->between($freeze->freeze_start_date, $freeze->freeze_end_date);
            if ($isActiveFreeze && $member->traineeDetails) {
                $member->traineeDetails->update(['status' => 1]);
            }

            $freeze->delete();
            DB::commit();

            return back()->with('success', 'Freeze deleted. Expiry reduced by ' . $freeze->freeze_days . ' days.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to delete freeze');
        }
    }

    public function importTemplate()
    {
        $csv = "name,phone_number,gender,address,membership_plan,start_date,registration_fee,paid_amount,payment_method\n";
        $csv .= "Rahul Singh,9876543210,male,Main Road Gorakhpur,Monthly Basic," . date('Y-m-d') . ",500,1499,cash\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="gymxbook_member_import_template.csv"',
        ]);
    }

    public function importMembers(Request $request)
    {
        $pid = $this->getParentId();
        $parentIds = $this->panelParentIds($pid);

        if (!$this->planFeatureEnabled('bulk_import_enabled', true)) {
            return $this->subscriptionDenied($request, \App\Services\SubscriptionFeatureService::featureLockedMessage('Bulk member import'));
        }

        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        $file = $request->file('csv_file');
        $handle = fopen($file->getRealPath(), 'r');
        if (!$handle) {
            return response()->json(['success' => false, 'error' => 'Could not read uploaded CSV file'], 400);
        }

        $requiredHeaders = ['name', 'phone_number', 'gender', 'address', 'membership_plan', 'start_date', 'registration_fee', 'paid_amount', 'payment_method'];
        $rawHeader = fgetcsv($handle);
        if (!$rawHeader) {
            fclose($handle);
            return response()->json(['success' => false, 'error' => 'CSV is empty'], 400);
        }

        $headers = array_map(fn($h) => strtolower(trim((string)$h)), $rawHeader);
        if ($headers !== $requiredHeaders) {
            fclose($handle);
            return response()->json([
                'success' => false,
                'error' => 'Invalid CSV format. Please download the latest template and keep the exact columns/order.',
                'expected_headers' => $requiredHeaders,
                'received_headers' => $headers,
            ], 422);
        }

        $bulkImportLimit = $this->planLimit('bulk_import_limit', 0);
        if ($bulkImportLimit > 0) {
            $lineCount = max(0, count(file($file->getRealPath(), FILE_SKIP_EMPTY_LINES)) - 1);
            if ($lineCount > $bulkImportLimit) {
                fclose($handle);
                return response()->json([
                    'success' => false,
                    'error' => 'Your current plan allows maximum ' . $bulkImportLimit . ' rows per bulk import. This file has ' . $lineCount . ' rows. Upgrade plan or split the file.',
                    'limit' => $bulkImportLimit,
                    'rows' => $lineCount,
                ], 422);
            }
        }

        $plans = Membership::whereIn('parent_id', $parentIds)
            ->get();

        $plansById = $plans->keyBy(fn($p) => (string)$p->id);
        $plansByTitle = $plans->keyBy(fn($p) => strtolower(trim($p->title)));

        $allowedMethods = ['cash', 'upi', 'card', 'online', 'bank'];
        $allowedGender = ['male', 'female', 'other', ''];
        $seenPhones = [];
        $imported = [];
        $failed = [];
        $rowNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            if ($this->isEmptyCsvRow($row)) continue;

            $row = array_pad($row, count($requiredHeaders), '');
            $data = array_combine($requiredHeaders, array_slice($row, 0, count($requiredHeaders)));
            $data = array_map(fn($v) => trim((string)$v), $data);

            $errors = [];
            $name = $data['name'];
            $phone = $this->normalizePhone($data['phone_number']);
            $gender = strtolower($data['gender'] ?: 'male');
            $paymentMethod = strtolower($data['payment_method'] ?: 'cash');
            $registrationFee = $this->parseMoney($data['registration_fee']);
            $paidAmount = $this->parseMoney($data['paid_amount']);
            $startDate = $this->parseCsvDate($data['start_date'] ?: date('Y-m-d'));
            $plan = $this->resolveImportPlan($data['membership_plan'], $plansById, $plansByTitle);

            $memberLimit = $this->planLimit('members_limit', 0);
            $currentMemberCount = User::where('type', 'trainee')->where('parent_id', $pid)->count();
            if ($memberLimit > 0 && ($currentMemberCount + count($imported)) >= $memberLimit) $errors[] = 'Member limit reached (' . $memberLimit . '). Upgrade plan to import more members';
            if ($name === '') $errors[] = 'Name required';
            if (!$phone) $errors[] = 'Phone invalid. Use 10 digit Indian mobile starting 6-9';
            if ($phone && in_array($phone, $seenPhones)) $errors[] = 'Duplicate phone inside CSV';
            if ($phone && !app(PhoneIdentityService::class)->isAvailable($phone)) $errors[] = PhoneIdentityService::DUPLICATE_MESSAGE;
            if (!in_array($gender, $allowedGender)) $errors[] = 'Gender must be male, female or other';
            if (!$plan) $errors[] = 'Membership plan not found. Use exact plan title or plan ID';
            if (!$startDate) $errors[] = 'Start date invalid. Use YYYY-MM-DD';
            if ($registrationFee < 0) $errors[] = 'Registration fee cannot be negative';
            if ($paidAmount < 0) $errors[] = 'Paid amount cannot be negative';
            if (!in_array($paymentMethod, $allowedMethods)) $errors[] = 'Payment method must be cash, upi, card, online or bank';

            $planAmount = $plan ? floatval($plan->amount) : 0;
            $totalAmount = $planAmount + $registrationFee;
            if ($paidAmount > $totalAmount) {
                $errors[] = 'Paid amount cannot exceed invoice total ₹' . number_format($totalAmount, 2);
            }

            if (!empty($errors)) {
                $failed[] = $this->failedImportRow($rowNumber, $data, $errors);
                if ($phone) $seenPhones[] = $phone;
                continue;
            }

            try {
                DB::beginTransaction();

                $email = 'member_' . $pid . '_' . $phone . '_' . time() . '@gymxbook.temp';
                $expiryDate = $this->calculateExpiry($startDate, $plan->package ?? 'monthly');

                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'phone_number' => $phone,
                    'type' => 'trainee',
                    'password' => Hash::make('123456789'),
                    'parent_id' => $pid,
                    'is_active' => true,
                ]);

                TraineeDetail::create([
                    'user_id' => $user->id,
                    'trainee_id' => $user->id,
                    'address' => $data['address'],
                    'gender' => $gender ?: 'male',
                    'fitness_goal' => '',
                    'membership_plan' => $plan->id,
                    'trainer_assign' => 0,
                    'membership_start_date' => $startDate,
                    'membership_expiry_date' => $expiryDate,
                    'category' => 0,
                    'parent_id' => $pid,
                    'status' => 1,
                ]);

                if ($totalAmount > 0) {
                    $invoiceId = (Invoice::whereIn('parent_id', $parentIds)->max('invoice_id') ?? 0) + 1;
                    $status = $paidAmount >= $totalAmount ? 'paid' : ($paidAmount > 0 ? 'partial' : 'unpaid');
                    $invoice = Invoice::create([
                        'invoice_id' => $invoiceId,
                        'user_id' => $user->id,
                        'invoice_date' => $startDate,
                        'invoice_due_date' => $expiryDate,
                        'status' => $status,
                        'notes' => 'Bulk import - ' . $plan->title,
                        'parent_id' => $pid,
                    ]);

                    if ($registrationFee > 0) {
                        InvoiceItem::create(['invoice_id' => $invoice->id, 'type_id' => 0, 'title' => 'Registration Fee', 'amount' => $registrationFee, 'description' => 'Bulk import']);
                    }
                    InvoiceItem::create(['invoice_id' => $invoice->id, 'type_id' => $plan->id, 'title' => $plan->title . ' Membership', 'amount' => $planAmount, 'description' => $plan->package ?? '']);

                    if ($paidAmount > 0) {
                        InvoicePayment::create([
                            'invoice_id' => $invoice->id,
                            'transaction_id' => '',
                            'payment_type' => $paymentMethod,
                            'amount' => $paidAmount,
                            'payment_date' => $startDate,
                            'parent_id' => $pid,
                            'notes' => 'Initial payment - bulk import',
                        ]);
                    }
                }

                DB::commit();
                $seenPhones[] = $phone;
                $imported[] = ['row' => $rowNumber, 'name' => $name, 'phone_number' => $phone, 'plan' => $plan->title, 'total' => $totalAmount, 'paid' => $paidAmount];
            } catch (\Throwable $e) {
                DB::rollBack();
                $failed[] = $this->failedImportRow($rowNumber, $data, ['Import failed: ' . $e->getMessage()]);
                if ($phone) $seenPhones[] = $phone;
            }
        }

        fclose($handle);

        $failedCsv = $this->buildFailedRowsCsv($failed, $requiredHeaders);

        return response()->json([
            'success' => true,
            'message' => count($imported) . ' members imported, ' . count($failed) . ' rows failed',
            'total_rows' => count($imported) + count($failed),
            'imported_count' => count($imported),
            'failed_count' => count($failed),
            'imported' => $imported,
            'failed' => $failed,
            'failed_csv' => $failedCsv,
        ]);
    }

    private function panelParentIds(int $pid): array
    {
        return array_values(array_unique(array_filter($this->getGymParentIds())));
    }

    private function normalizePhone(string $phone): ?string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($digits) === 12 && substr($digits, 0, 2) === '91') $digits = substr($digits, 2);
        if (strlen($digits) !== 10 || !preg_match('/^[6-9][0-9]{9}$/', $digits)) return null;
        return $digits;
    }

    private function parseMoney(string $value): float
    {
        $clean = preg_replace('/[^0-9.]/', '', $value);
        if ($clean === '' || !is_numeric($clean)) return 0;
        return round(floatval($clean), 2);
    }

    private function parseCsvDate(string $value): ?string
    {
        $value = trim($value);
        foreach (['Y-m-d', 'd-m-Y', 'd/m/Y'] as $format) {
            try {
                $dt = \Carbon\Carbon::createFromFormat($format, $value);
                if ($dt && $dt->format($format) === $value) return $dt->toDateString();
            } catch (\Throwable $e) {}
        }
        try {
            return \Carbon\Carbon::parse($value)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function resolveImportPlan(string $value, $plansById, $plansByTitle)
    {
        $value = trim($value);
        if ($value === '') return null;
        if ($plansById->has($value)) return $plansById->get($value);
        $key = strtolower($value);
        return $plansByTitle->get($key);
    }

    private function isEmptyCsvRow(array $row): bool
    {
        foreach ($row as $v) {
            if (trim((string)$v) !== '') return false;
        }
        return true;
    }

    private function failedImportRow(int $rowNumber, array $data, array $errors): array
    {
        return ['row' => $rowNumber, 'data' => $data, 'errors' => $errors];
    }

    private function buildFailedRowsCsv(array $failed, array $headers): string
    {
        if (empty($failed)) return '';
        $out = fopen('php://temp', 'r+');
        // Keep exact template columns only so the file can be edited and reuploaded directly.
        // Detailed reasons remain in the AJAX report UI.
        fputcsv($out, $headers);
        foreach ($failed as $item) {
            $data = $item['data'];
            $row = [];
            foreach ($headers as $h) $row[] = $data[$h] ?? '';
            fputcsv($out, $row);
        }
        rewind($out);
        return stream_get_contents($out);
    }

    private function calculateExpiry(string $startDate, string $package): string
    {
        $start = \Carbon\Carbon::parse($startDate);
        return match(strtolower($package)) {
            'weekly', '1 week', '7 days' => $start->addDays(6)->toDateString(),
            'monthly', '1 month' => $start->addMonth()->subDay()->toDateString(),
            'quarterly', '3 months' => $start->addMonths(3)->subDay()->toDateString(),
            'half-yearly', '6 months' => $start->addMonths(6)->subDay()->toDateString(),
            'yearly', '12 months', '1 year' => $start->addYear()->subDay()->toDateString(),
            default => $start->addMonth()->subDay()->toDateString(),
        };
    }

    /**
     * Render members table HTML directly (bypasses Blade view system for AJAX)
     */
    private function renderMembersTableHtml($members): string
    {
        $html = '<div class="table-responsive">';
        $html .= '<table class="table members-table align-middle mb-0">';
        $html .= '<thead><tr>';
        $html .= '<th style="width: 34%; padding-left: 20px;">Member</th>';
        $html .= '<th style="width: 18%;">Phone</th>';
        $html .= '<th style="width: 20%;">Plan</th>';
        $html .= '<th style="width: 16%;">Expiry</th>';
        $html .= '<th style="width: 12%;">Status</th>';
        $html .= '</tr></thead>';
        $html .= '<tbody id="membersTableBody">';

        if ($members->count() > 0) {
            foreach ($members as $member) {
                $isActive = $member->traineeDetails &&
                           $member->traineeDetails->membership_expiry_date &&
                           \Carbon\Carbon::parse($member->traineeDetails->membership_expiry_date)->isFuture();

                $expiryDate = $member->traineeDetails && $member->traineeDetails->membership_expiry_date
                            ? \Carbon\Carbon::parse($member->traineeDetails->membership_expiry_date)->format('d M Y')
                            : '-';

                $planTitle = ($member->traineeDetails && $member->traineeDetails->membership)
                            ? $member->traineeDetails->membership->title
                            : 'No Plan';

                $statusClass = $isActive ? 'status-active' : 'status-expired';
                $statusText = $isActive ? 'Active' : 'Expired';
                $nameLower = strtolower($member->name);
                $phone = $member->phone_number ?? '';
                $email = strtolower($member->email ?? '');
                $status = $isActive ? 'active' : 'expired';
                $initial = strtoupper(substr($member->name, 0, 1));
                $showUrl = route('panel.members.show', $member->id);

                $html .= '<tr class="member-row" ';
                $html .= 'data-name="' . htmlspecialchars($nameLower) . '" ';
                $html .= 'data-phone="' . htmlspecialchars($phone) . '" ';
                $html .= 'data-email="' . htmlspecialchars($email) . '" ';
                $html .= 'data-status="' . $status . '" ';
                $html .= 'onclick="window.location=\'' . $showUrl . '\'">';

                $html .= '<td style="padding-left: 20px;">';
                $html .= '<div class="d-flex align-items-center">';
                $html .= '<div class="member-avatar me-3">' . $initial . '</div>';
                $html .= '<div>';
                $html .= '<div class="member-name">' . htmlspecialchars($member->name) . '</div>';
                $html .= '<div class="text-muted" style="font-size:12.5px;">' . htmlspecialchars($member->email ?? '') . '</div>';
                $html .= '</div></div></td>';

                $html .= '<td><span class="text-dark fw-medium">' . htmlspecialchars($member->phone_number ?? '—') . '</span></td>';

                $html .= '<td><span class="badge px-3 py-1" style="background:#e0f2fe;color:#0369a1;font-weight:600;font-size:12px;">' . htmlspecialchars($planTitle) . '</span></td>';

                $html .= '<td><span class="text-secondary" style="font-size:14px;">' . $expiryDate . '</span></td>';

                $html .= '<td><span class="status-badge ' . $statusClass . '">' . $statusText . '</span></td>';

                $html .= '</tr>';
            }
        } else {
            $html .= '<tr><td colspan="5" class="text-center py-5">';
            $html .= '<div class="text-muted"><i class="bi bi-people fs-1 d-block mb-2"></i>No members found</div>';
            $html .= '</td></tr>';
        }

        $html .= '</tbody></table></div>';

        if ($members->hasPages()) {
            $html .= '<div class="d-flex flex-column flex-md-row justify-content-between align-items-center mt-4 gap-2">';
            $html .= '<div class="text-muted small">';
            $html .= 'Showing <strong>' . $members->firstItem() . '</strong> to <strong>' . $members->lastItem() . '</strong> of <strong>' . $members->total() . '</strong> members';
            $html .= '</div>';
            $html .= '<div class="modern-pagination">';
            $html .= $members->links('pagination::bootstrap-5');
            $html .= '</div></div>';
        }

        return $html;
    }
}
