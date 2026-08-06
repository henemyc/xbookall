@extends('panel.layouts.app')

@section('title', 'Member Details')

@section('content')
@php
    $td = $member->traineeDetails;
    $isExpired = $td && $td->membership_expiry_date && \Carbon\Carbon::parse($td->membership_expiry_date)->isPast();
    $isFrozen = $td && $td->status == 3;
    $daysLeft = $td && $td->membership_expiry_date ? (int) floor(\Carbon\Carbon::now()->diffInDays(\Carbon\Carbon::parse($td->membership_expiry_date), false)) : null;
    
    $invoices = \App\Models\Invoice::where('user_id', $member->id)->where('parent_id', auth()->id())->with(['items', 'payments'])->orderBy('created_at', 'desc')->get();
    $attendance = \App\Models\Attendance::where('user_id', $member->id)->where('parent_id', auth()->id())->orderBy('date', 'desc')->limit(30)->get();
    $payments = \App\Models\InvoicePayment::whereHas('invoice', function($q) use ($member) { $q->where('user_id', $member->id); })->where('parent_id', auth()->id())->orderBy('payment_date', 'desc')->limit(20)->get();
    $workout = \App\Models\Workout::where('assign_id', $member->id)->where('parent_id', auth()->id())->orderBy('created_at', 'desc')->first();
    $healthRecords = \App\Models\Health::where('user_id', $member->id)->where('parent_id', auth()->id())->orderBy('measurement_date', 'desc')->get();
    $freezeLogs = \App\Models\FreezeMembershipLog::where('trainee_id', $member->id)->orderBy('created_at', 'desc')->get();
    $plans = \App\Models\Membership::where('parent_id', auth()->id())->orWhere('parent_id', 0)->orderBy('amount')->get();
    $trainers = \App\Models\User::where('type', 'trainer')->where('parent_id', auth()->id())->where('is_active', true)->orderBy('name')->get();
    $classes = \App\Models\GymClass::where('parent_id', auth()->id())->orderBy('title')->get();
    $activities = \App\Models\WorkoutActivity::where('parent_id', auth()->id())->orderBy('title')->get();
    
    $totalDue = 0;
    foreach ($invoices as $inv) {
        $invTotal = $inv->items->sum('amount');
        $invPaid = $inv->payments->sum('amount');
        $totalDue += max(0, $invTotal - $invPaid);
    }
    
    $trainerName = 'Not assigned';
    if ($td && $td->trainer_assign && $td->trainer_assign > 0) {
        $trainerUser = \App\Models\User::find($td->trainer_assign);
        $trainerName = $trainerUser ? $trainerUser->name : 'Not assigned';
    }
    
    // FIX #2: Check if currently in active freeze period
    $activeFreeze = $freezeLogs->first(function($log) {
        return now()->between($log->freeze_start_date, $log->freeze_end_date);
    });
    $isCurrentlyFrozen = $activeFreeze != null;
    
    $currentPlan = is_string($workout->workout_history ?? null) ? json_decode($workout->workout_history, true) : ($workout->workout_history ?? []);
@endphp

<!-- Hero Profile Banner -->
<div class="table-card mb-4" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border: none;">
    <div class="row align-items-center">
        <div class="col-md-8">
            <div class="d-flex align-items-center">
                <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #ff8a3d, #ff6b2c); border-radius: 24px; font-size: 36px; color: white; display: flex; align-items: center; justify-content: center;">
                    {{ strtoupper(substr($member->name, 0, 1)) }}
                </div>
                <div class="ms-4">
                    <h3 style="color: white; font-family: 'Space Grotesk', sans-serif; font-weight: 700;">{{ $member->name }}</h3>
                    <p style="color: rgba(255,255,255,0.6); font-size: 13px; margin-bottom: 2px;">{{ $member->email }}</p>
                    @if($member->phone_number)
                        <p style="color: rgba(255,255,255,0.5); font-size: 12px; margin: 0;"><i class="bi bi-phone me-1"></i> {{ $member->phone_number }}</p>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-4 text-end">
            @if($isCurrentlyFrozen || $isFrozen)
                <span class="badge bg-info fs-6 px-3 py-2">❄️ Frozen</span>
            @elseif($isExpired)
                <span class="badge bg-danger fs-6 px-3 py-2">⚠️ Expired</span>
            @else
                <span class="badge bg-success fs-6 px-3 py-2">✅ Active</span>
            @endif
            <div class="mt-2">
                @if($daysLeft !== null)
                    @if($daysLeft < 0)
                        <span style="color: rgba(255, 77, 79, 0.8); font-size: 12px;">Expired {{ abs($daysLeft) }} days ago</span>
                    @elseif($daysLeft <= 7)
                        <span style="color: rgba(255, 167, 38, 0.8); font-size: 12px;">{{ $daysLeft }} days remaining</span>
                    @else
                        <span style="color: rgba(22, 199, 132, 0.8); font-size: 12px;">{{ $daysLeft }} days remaining</span>
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Quick Stats -->
<div class="row g-3 mb-4">
    <div class="col-3">
        <div class="stat-card text-center p-3">
            <div class="value" style="font-size: 22px; color: var(--primary);">{{ $attendance->count() }}</div>
            <div class="label" style="font-size: 11px;">Visits</div>
        </div>
    </div>
    <div class="col-3">
        <div class="stat-card text-center p-3">
            <div class="value" style="font-size: 22px; color: var(--info);">{{ $invoices->count() }}</div>
            <div class="label" style="font-size: 11px;">Invoices</div>
        </div>
    </div>
    <div class="col-3">
        <div class="stat-card text-center p-3">
            <div class="value" style="font-size: 22px; color: var(--success);">₹{{ number_format($payments->sum('amount')) }}</div>
            <div class="label" style="font-size: 11px;">Paid</div>
        </div>
    </div>
    <div class="col-3">
        <div class="stat-card text-center p-3">
            <div class="value" style="font-size: 22px; color: {{ $totalDue > 0 ? 'var(--danger)' : 'var(--success)' }};">₹{{ number_format($totalDue) }}</div>
            <div class="label" style="font-size: 11px;">Due</div>
        </div>
    </div>
</div>

<!-- Action Buttons -->
<div class="table-card mb-4">
    <div class="row g-2">
        <div class="col-6 col-md-3">
            <button class="btn btn-outline-primary w-100" data-bs-toggle="modal" data-bs-target="#editMemberModal">
                <i class="bi bi-pencil me-1"></i> Edit
            </button>
        </div>
        <div class="col-6 col-md-3">
            <button class="btn btn-outline-success w-100" data-bs-toggle="modal" data-bs-target="#renewModal">
                <i class="bi bi-arrow-repeat me-1"></i> Renew
            </button>
        </div>
        <div class="col-6 col-md-3">
            @if($isCurrentlyFrozen || $isFrozen)
                <button class="btn btn-info w-100" disabled style="opacity: 0.7;">
                    <i class="bi bi-snow me-1"></i> Frozen
                </button>
            @else
                <button class="btn btn-outline-info w-100" data-bs-toggle="modal" data-bs-target="#freezeModal">
                    <i class="bi bi-snow me-1"></i> Freeze
                </button>
            @endif
        </div>
        <div class="col-6 col-md-3">
            <button class="btn btn-outline-danger w-100" onclick="firstDeleteConfirm()">
                <i class="bi bi-trash3 me-1"></i> Delete
            </button>
        </div>
    </div>
</div>

<!-- Membership & Personal Details -->
<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="table-card h-100">
            <h6 class="mb-3"><i class="bi bi-card-list me-2" style="color: var(--primary);"></i> Membership</h6>
            <div class="d-flex justify-content-between py-2 border-bottom">
                <span class="text-muted">Plan</span>
                <strong>{{ $td->membership->title ?? 'No Plan' }}</strong>
            </div>
            <div class="d-flex justify-content-between py-2 border-bottom">
                <span class="text-muted">Amount</span>
                <strong>₹{{ number_format($td->membership->amount ?? 0) }}</strong>
            </div>
            <div class="d-flex justify-content-between py-2 border-bottom">
                <span class="text-muted">Package</span>
                <span>{{ ucfirst($td->membership->package ?? '-') }}</span>
            </div>
            <div class="d-flex justify-content-between py-2 border-bottom">
                <span class="text-muted">Start</span>
                <span>{{ $td->membership_start_date ? \Carbon\Carbon::parse($td->membership_start_date)->format('d M Y') : '-' }}</span>
            </div>
            <div class="d-flex justify-content-between py-2 border-bottom">
                <span class="text-muted">Expiry</span>
                <span class="{{ $isExpired ? 'text-danger fw-bold' : '' }}">
                    {{ $td->membership_expiry_date ? \Carbon\Carbon::parse($td->membership_expiry_date)->format('d M Y') : '-' }}
                </span>
            </div>
            <div class="d-flex justify-content-between py-2">
                <span class="text-muted">Trainer</span>
                <span>{{ $trainerName }}</span>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="table-card h-100">
            <h6 class="mb-3"><i class="bi bi-person me-2" style="color: var(--info);"></i> Personal Details</h6>
            <div class="d-flex justify-content-between py-2 border-bottom">
                <span class="text-muted">Gender</span>
                <span>{{ ucfirst($td->gender ?? '-') }}</span>
            </div>
            <div class="d-flex justify-content-between py-2 border-bottom">
                <span class="text-muted">DOB</span>
                <span>{{ $td->dob ? \Carbon\Carbon::parse($td->dob)->format('d M Y') : '-' }}</span>
            </div>
            <div class="d-flex justify-content-between py-2 border-bottom">
                <span class="text-muted">Fitness Goal</span>
                <span>{{ $td->fitness_goal ?? '-' }}</span>
            </div>
            <div class="d-flex justify-content-between py-2 border-bottom">
                <span class="text-muted">Address</span>
                <span>{{ $td->address ?? '-' }}</span>
            </div>
            <div class="d-flex justify-content-between py-2 border-bottom">
                <span class="text-muted">City</span>
                <span>{{ $td->city ?? '-' }}</span>
            </div>
            <div class="d-flex justify-content-between py-2">
                <span class="text-muted">Joined</span>
                <span>{{ $member->created_at->format('d M Y') }}</span>
            </div>
        </div>
    </div>
</div>

<!-- FIX #2: Freeze History with Delete Option -->
@if($freezeLogs->count() > 0)
<div class="table-card mb-4">
    <h6 class="mb-3"><i class="bi bi-snow me-2" style="color: var(--info);"></i> Freeze History</h6>
    @foreach($freezeLogs as $log)
    @php
        $isActiveFreeze = now()->between($log->freeze_start_date, $log->freeze_end_date);
    @endphp
    <div class="d-flex align-items-center justify-content-between py-2 {{ !$loop->last ? 'border-bottom' : '' }}">
        <div class="d-flex align-items-center">
            <div class="me-3">
                <div class="fw-bold" style="font-size: 13px;">
                    {{ \Carbon\Carbon::parse($log->freeze_start_date)->format('d M') }} - {{ \Carbon\Carbon::parse($log->freeze_end_date)->format('d M Y') }}
                </div>
                <small class="text-muted">{{ $log->freeze_days }} days @if($log->remarks) • {{ $log->remarks }} @endif</small>
            </div>
            @if($isActiveFreeze)
                <span class="badge bg-info">Active</span>
            @endif
        </div>
        <div class="d-flex align-items-center">
            <span class="badge bg-secondary me-2">{{ $log->freeze_days }}d</span>
            {{-- FIX #2: Delete freeze option (reduces days from expiry) --}}
            <button class="btn btn-sm btn-outline-danger" onclick="confirmDelete('{{ route('panel.members.deleteFreeze', ['id' => $member->id, 'freezeId' => $log->id]) }}', 'Delete this freeze? Membership expiry will be reduced by {{ $log->freeze_days }} days.')">
                <i class="bi bi-trash3" style="font-size: 12px;"></i>
            </button>
        </div>
    </div>
    @endforeach
</div>
@endif

<!-- Workout Plan -->
<div class="table-card mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="mb-0"><i class="bi bi-lightning me-2" style="color: var(--warning);"></i> Workout Plan</h6>
        @if($workout)
            <div>
                <button class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#editWorkoutModal">
                    <i class="bi bi-pencil"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger" onclick="confirmDelete('{{ route('panel.workouts.destroy', $workout->id) }}', 'Delete this workout plan?')">
                    <i class="bi bi-trash3"></i>
                </button>
            </div>
        @else
            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addWorkoutModal">
                <i class="bi bi-plus me-1"></i> Add
            </button>
        @endif
    </div>

    @if($workout)
        @if(is_array($currentPlan) && count($currentPlan) > 0)
            <div class="row g-2">
                @foreach($currentPlan as $day => $exercises)
                <div class="col-md-4 col-6">
                    <div class="p-2 rounded" style="background: var(--bg); border: 1px solid var(--border);">
                        <div class="fw-bold text-primary mb-1" style="font-size: 12px;">{{ ucfirst($day) }}</div>
                        @if(is_array($exercises))
                            @foreach($exercises as $ex)
                            <div style="font-size: 11px; color: var(--text-secondary);">
                                {{ $ex['exercise'] ?? '' }} {{ $ex['sets'] ?? '' }}x{{ $ex['reps'] ?? '' }}
                            </div>
                            @endforeach
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        @endif
        @if($workout->notes)
            <small class="text-muted mt-2 d-block"><i class="bi bi-sticky me-1"></i> {{ $workout->notes }}</small>
        @endif
    @else
        <div class="text-center py-3 text-muted">
            <i class="bi bi-lightning fs-4 d-block mb-1" style="opacity: 0.3;"></i>
            <small>No workout plan assigned</small>
        </div>
    @endif
</div>

<!-- Health Records -->
<div class="table-card mb-4">
    <h6 class="mb-3"><i class="bi bi-heart-pulse me-2" style="color: var(--danger);"></i> Health Records</h6>
    @forelse($healthRecords as $record)
    <div class="d-flex align-items-start py-2 {{ !$loop->last ? 'border-bottom' : '' }}">
        <div style="width: 36px; height: 36px; background: rgba(255, 77, 79, 0.1); border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
            <i class="bi bi-heart-pulse text-danger" style="font-size: 14px;"></i>
        </div>
        <div class="ms-2 flex-grow-1">
            <div class="d-flex justify-content-between">
                <span class="fw-bold" style="font-size: 13px;">
                    @php
                        $result = $record->result;
                        // Try to parse JSON health data
                        $parsed = json_decode($result, true);
                    @endphp
                    @if(is_array($parsed))
                        {{-- JSON format: [{"type":"Height","result":"20"},{"type":"Fat","result":"20"}] --}}
                        @foreach($parsed as $item)
                            <span class="badge bg-light text-dark me-1 mb-1" style="font-weight: 500;">
                                {{ $item['type'] ?? '' }}: <strong>{{ $item['result'] ?? '' }}</strong>
                            </span>
                        @endforeach
                    @else
                        {{ $result }}
                    @endif
                </span>
                <small class="text-muted">{{ \Carbon\Carbon::parse($record->measurement_date)->format('d M Y') }}</small>
            </div>
            @if($record->notes)
                <small class="text-muted">{{ $record->notes }}</small>
            @endif
        </div>
    </div>
    @empty
    <div class="text-center py-3 text-muted"><small>No health records</small></div>
    @endforelse
</div>

<!-- Invoices & Payments -->
<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="table-card h-100">
            <h6 class="mb-3"><i class="bi bi-receipt me-2" style="color: var(--primary);"></i> Invoices ({{ $invoices->count() }})</h6>
            @forelse($invoices->take(5) as $inv)
            @php
                $total = $inv->items->sum('amount');
                $paid = $inv->payments->sum('amount');
                $due = $total - $paid;
            @endphp
            <a href="{{ route('panel.invoices.show', $inv->id) }}" class="text-decoration-none">
                <div class="d-flex align-items-center justify-content-between py-2 {{ !$loop->last ? 'border-bottom' : '' }}">
                    <div class="d-flex align-items-center">
                        <div style="width: 36px; height: 36px; background: var(--bg); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 700; color: var(--primary); font-size: 12px;">#{{ $inv->invoice_id }}</div>
                        <div class="ms-2">
                            <div class="fw-bold" style="font-size: 13px; color: var(--text);">{{ $inv->notes ?: 'Invoice' }}</div>
                            <small style="color: var(--text-secondary);">{{ \Carbon\Carbon::parse($inv->invoice_date)->format('d M') }}</small>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="fw-bold" style="font-family: 'Space Grotesk', sans-serif; font-size: 14px;">₹{{ number_format($total) }}</div>
                        @if($due > 0)
                            <small style="color: var(--danger);">Due ₹{{ number_format($due) }}</small>
                        @else
                            <small style="color: var(--success);">Paid</small>
                        @endif
                    </div>
                </div>
            </a>
            @empty
            <div class="text-center py-3 text-muted"><small>No invoices</small></div>
            @endforelse
        </div>
    </div>

    <div class="col-lg-6">
        <div class="table-card h-100">
            <h6 class="mb-3"><i class="bi bi-credit-card me-2" style="color: var(--success);"></i> Payments ({{ $payments->count() }})</h6>
            @forelse($payments->take(5) as $pay)
            <div class="d-flex align-items-center justify-content-between py-2 {{ !$loop->last ? 'border-bottom' : '' }}">
                <div class="d-flex align-items-center">
                    <div style="width: 36px; height: 36px; background: rgba(22, 199, 132, 0.1); border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-check-circle text-success" style="font-size: 14px;"></i>
                    </div>
                    <div class="ms-2">
                        <div class="fw-bold" style="font-size: 13px;">{{ ucfirst($pay->payment_type) }}</div>
                        <small class="text-muted">{{ \Carbon\Carbon::parse($pay->payment_date)->format('d M') }}</small>
                    </div>
                </div>
                <div class="fw-bold text-success" style="font-family: 'Space Grotesk', sans-serif; font-size: 14px;">+₹{{ number_format($pay->amount) }}</div>
            </div>
            @empty
            <div class="text-center py-3 text-muted"><small>No payments</small></div>
            @endforelse
        </div>
    </div>
</div>

<!-- Attendance -->
<div class="table-card mb-4">
    <h6 class="mb-3"><i class="bi bi-fingerprint me-2" style="color: var(--info);"></i> Attendance ({{ $attendance->count() }})</h6>
    <div class="row g-2">
        @forelse($attendance->take(10) as $att)
        <div class="col-md-6">
            <div class="d-flex align-items-center py-2">
                <div style="width: 36px; height: 36px; background: rgba(59, 158, 255, 0.1); border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <i class="bi bi-fingerprint text-info" style="font-size: 14px;"></i>
                </div>
                <div class="ms-2 flex-grow-1">
                    <div class="d-flex justify-content-between">
                        <span class="fw-bold" style="font-size: 13px;">{{ \Carbon\Carbon::parse($att->date)->format('d M Y') }}</span>
                        @if($att->checked_out_time)
                            <span class="badge bg-secondary" style="font-size: 9px;">Out</span>
                        @else
                            <span class="badge bg-success" style="font-size: 9px;">In</span>
                        @endif
                    </div>
                    <small class="text-muted">
                        {{ \Carbon\Carbon::parse($att->checked_in_time)->format('h:i A') }}
                        @if($att->checked_out_time)
                            → {{ \Carbon\Carbon::parse($att->checked_out_time)->format('h:i A') }}
                        @endif
                    </small>
                </div>
            </div>
        </div>
        @empty
        <div class="col-12 text-center py-3 text-muted"><small>No attendance records</small></div>
        @endforelse
    </div>
</div>

<!-- Edit Member Modal -->
<div class="modal fade" id="editMemberModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="{{ route('panel.members.update', $member->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i> Edit Member</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="name" class="form-control" value="{{ $member->name }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" class="form-control" value="{{ $member->email }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone_number" class="form-control" value="{{ $member->phone_number }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Gender</label>
                            <select name="gender" class="form-select">
                                <option value="male" {{ ($td->gender ?? '') == 'male' ? 'selected' : '' }}>Male</option>
                                <option value="female" {{ ($td->gender ?? '') == 'female' ? 'selected' : '' }}>Female</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" name="dob" class="form-control" value="{{ $td->dob ?? '' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Fitness Goal</label>
                            <input type="text" name="fitness_goal" class="form-control" value="{{ $td->fitness_goal ?? '' }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Trainer</label>
                            <select name="trainer_assign" class="form-select">
                                <option value="0">No Trainer</option>
                                @foreach($trainers as $trainer)
                                    <option value="{{ $trainer->id }}" {{ ($td->trainer_assign ?? 0) == $trainer->id ? 'selected' : '' }}>{{ $trainer->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Address</label>
                            <input type="text" name="address" class="form-control" value="{{ $td->address ?? '' }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">City</label>
                            <input type="text" name="city" class="form-control" value="{{ $td->city ?? '' }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">State</label>
                            <input type="text" name="state" class="form-control" value="{{ $td->state ?? '' }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Pincode</label>
                            <input type="text" name="zip_code" class="form-control" value="{{ $td->zip_code ?? '' }}">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-2"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- FIX #1: Renew Modal -->
<div class="modal fade" id="renewModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('panel.members.update', $member->id) }}" method="POST" id="renewForm">
                @csrf
                @method('PUT')
                <input type="hidden" name="renew" value="1">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-arrow-repeat me-2"></i> Renew Membership</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @if($td && $td->membership_expiry_date && !$isExpired)
                    <div class="alert alert-info mb-3">
                        <i class="bi bi-info-circle me-2"></i>
                        Current plan expires <strong>{{ \Carbon\Carbon::parse($td->membership_expiry_date)->format('d M Y') }}</strong>
                    </div>
                    @endif

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Plan *</label>
                            <select name="membership_plan" id="renewPlan" class="form-select" required onchange="calculateRenew()">
                                @foreach($plans as $plan)
                                    <option value="{{ $plan->id }}" data-amount="{{ $plan->amount }}" data-package="{{ $plan->package }}" {{ $td && $td->membership_plan == $plan->id ? 'selected' : '' }}>
                                        {{ $plan->title }} - ₹{{ number_format($plan->amount) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Class (Optional)</label>
                            <select name="class_id" id="renewClass" class="form-select" onchange="calculateRenew()">
                                <option value="0" data-fees="0">No Class</option>
                                @foreach($classes as $class)
                                    <option value="{{ $class->id }}" data-fees="{{ $class->fees }}">{{ $class->title }} - ₹{{ number_format($class->fees) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="membership_start_date" id="renewStart" class="form-control" readonly style="background: var(--bg);">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Expiry Date</label>
                            <input type="date" name="membership_expiry_date" id="renewExpiry" class="form-control" readonly style="background: var(--bg);">
                        </div>
                    </div>

                    {{-- FIX #1: Paid amount with live validation --}}
                    <div class="mb-3">
                        <label class="form-label">Paid Amount</label>
                        <div class="input-group">
                            <span class="input-group-text">₹</span>
                            <input type="number" name="paid_amount" id="renewPaid" class="form-control" value="0" min="0" oninput="calculateRenew()">
                        </div>
                        <div id="renewPaidError" class="text-danger" style="font-size: 12px; display: none; margin-top: 4px;">
                            <i class="bi bi-exclamation-circle me-1"></i>Paid amount cannot exceed total
                        </div>
                    </div>

                    <div class="p-3 rounded" style="background: linear-gradient(135deg, #0f172a, #1e293b);">
                        <div class="d-flex justify-content-between mb-2">
                            <span style="color: rgba(255,255,255,0.6);">Plan Amount</span>
                            <span style="color: white; font-family: 'Space Grotesk', sans-serif;" id="renewPlanAmt">₹0</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span style="color: rgba(255,255,255,0.6);">Class Fee</span>
                            <span style="color: white; font-family: 'Space Grotesk', sans-serif;" id="renewClassAmt">₹0</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span style="color: rgba(255,255,255,0.6);">Paid Amount</span>
                            <span style="color: #16c784; font-family: 'Space Grotesk', sans-serif; font-weight: 700;" id="renewPaidAmt">-₹0</span>
                        </div>
                        <div style="height: 1px; background: rgba(255,255,255,0.1); margin: 8px 0;"></div>
                        <div class="d-flex justify-content-between">
                            <span style="color: rgba(255,255,255,0.8); font-weight: 600;">Balance Due</span>
                            <span style="color: #ff4d4f; font-family: 'Space Grotesk', sans-serif; font-weight: 700; font-size: 18px;" id="renewDueAmt">₹0</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" id="renewBtn"><i class="bi bi-check-circle me-2"></i> Renew</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Freeze Modal -->
<div class="modal fade" id="freezeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('panel.members.update', $member->id) }}" method="POST">
                @csrf
                @method('PUT')
                <input type="hidden" name="freeze" value="1">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-snow me-2"></i> Freeze Membership</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info mb-3">
                        <i class="bi bi-info-circle me-2"></i>
                        Expiry will be extended by the freeze duration.
                    </div>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Reason</label>
                            <input type="text" name="freeze_reason" class="form-control" placeholder="e.g., Vacation, Medical">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Start Date *</label>
                            <input type="date" name="freeze_start_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">End Date *</label>
                            <input type="date" name="freeze_end_date" class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info"><i class="bi bi-snow me-2"></i> Freeze</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Workout Modal -->
<div class="modal fade" id="addWorkoutModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="{{ route('panel.workouts.store') }}" method="POST">
                @csrf
                <input type="hidden" name="user_id" value="{{ $member->id }}">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-lightning me-2"></i> Add Workout Plan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @foreach(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'] as $day)
                    <div class="mb-3 p-3 rounded" style="background: var(--bg); border: 1px solid var(--border);">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong class="text-primary" style="font-size: 14px;">{{ ucfirst($day) }}</strong>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addExercise('{{ $day }}', 'add')"><i class="bi bi-plus"></i></button>
                        </div>
                        <div id="exercises-add-{{ $day }}">
                            <div class="row g-2 mb-2 exercise-row">
                                <div class="col-5">
                                    <select name="workout[{{ $day }}][0][exercise]" class="form-select form-select-sm">
                                        <option value="">Select</option>
                                        @foreach($activities as $act)<option value="{{ $act->title }}">{{ $act->title }}</option>@endforeach
                                    </select>
                                </div>
                                <div class="col-3"><input type="number" name="workout[{{ $day }}][0][sets]" class="form-control form-control-sm" placeholder="Sets"></div>
                                <div class="col-3"><input type="text" name="workout[{{ $day }}][0][reps]" class="form-control form-control-sm" placeholder="Reps"></div>
                                <div class="col-1"><button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="this.closest('.exercise-row').remove()"><i class="bi bi-x"></i></button></div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-2"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Workout Modal -->
@if($workout)
<div class="modal fade" id="editWorkoutModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="{{ route('panel.workouts.update', $workout->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i> Edit Workout Plan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @foreach(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'] as $day)
                    <div class="mb-3 p-3 rounded" style="background: var(--bg); border: 1px solid var(--border);">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong class="text-primary" style="font-size: 14px;">{{ ucfirst($day) }}</strong>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addExercise('{{ $day }}', 'edit')"><i class="bi bi-plus"></i></button>
                        </div>
                        <div id="exercises-edit-{{ $day }}">
                            @php $dayExercises = $currentPlan[$day] ?? []; @endphp
                            @if(count($dayExercises) > 0)
                                @foreach($dayExercises as $idx => $ex)
                                <div class="row g-2 mb-2 exercise-row">
                                    <div class="col-5">
                                        <select name="workout[{{ $day }}][{{ $idx }}][exercise]" class="form-select form-select-sm">
                                            <option value="">Select</option>
                                            @foreach($activities as $act)<option value="{{ $act->title }}" {{ ($ex['exercise'] ?? '') == $act->title ? 'selected' : '' }}>{{ $act->title }}</option>@endforeach
                                        </select>
                                    </div>
                                    <div class="col-3"><input type="number" name="workout[{{ $day }}][{{ $idx }}][sets]" class="form-control form-control-sm" placeholder="Sets" value="{{ $ex['sets'] ?? '' }}"></div>
                                    <div class="col-3"><input type="text" name="workout[{{ $day }}][{{ $idx }}][reps]" class="form-control form-control-sm" placeholder="Reps" value="{{ $ex['reps'] ?? '' }}"></div>
                                    <div class="col-1"><button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="this.closest('.exercise-row').remove()"><i class="bi bi-x"></i></button></div>
                                </div>
                                @endforeach
                            @else
                                <div class="row g-2 mb-2 exercise-row">
                                    <div class="col-5">
                                        <select name="workout[{{ $day }}][0][exercise]" class="form-select form-select-sm">
                                            <option value="">Select</option>
                                            @foreach($activities as $act)<option value="{{ $act->title }}">{{ $act->title }}</option>@endforeach
                                        </select>
                                    </div>
                                    <div class="col-3"><input type="number" name="workout[{{ $day }}][0][sets]" class="form-control form-control-sm" placeholder="Sets"></div>
                                    <div class="col-3"><input type="text" name="workout[{{ $day }}][0][reps]" class="form-control form-control-sm" placeholder="Reps"></div>
                                    <div class="col-1"><button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="this.closest('.exercise-row').remove()"><i class="bi bi-x"></i></button></div>
                                </div>
                            @endif
                        </div>
                    </div>
                    @endforeach
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2">{{ $workout->notes ?? '' }}</textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-2"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

<!-- Delete Confirmations -->
<div class="modal fade" id="deleteFirstModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-body text-center" style="padding: 32px 24px;">
                <div style="width: 64px; height: 64px; border-radius: 50%; background: rgba(255, 77, 79, 0.1); display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                    <i class="bi bi-exclamation-triangle" style="font-size: 32px; color: var(--danger);"></i>
                </div>
                <h5 class="mb-2">Delete {{ $member->name }}?</h5>
                <p class="text-muted mb-0">This will permanently delete this member.</p>
            </div>
            <div class="modal-footer justify-content-center border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger px-4" data-bs-dismiss="modal" onclick="secondDeleteConfirm()">Continue</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteSecondModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-body text-center" style="padding: 32px 24px;">
                <div style="width: 64px; height: 64px; border-radius: 50%; background: rgba(255, 77, 79, 0.2); display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                    <i class="bi bi-trash3" style="font-size: 32px; color: var(--danger);"></i>
                </div>
                <h5 class="mb-2 text-danger">Final Confirmation</h5>
                <p class="text-danger fw-bold">This cannot be undone!</p>
            </div>
            <div class="modal-footer justify-content-center border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                <form action="{{ route('panel.members.destroy', $member->id) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="hard_delete" value="1">
                    <button type="submit" class="btn btn-danger px-4"><i class="bi bi-trash3 me-2"></i> Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function firstDeleteConfirm() { new bootstrap.Modal(document.getElementById('deleteFirstModal')).show(); }
    function secondDeleteConfirm() { new bootstrap.Modal(document.getElementById('deleteSecondModal')).show(); }

    let exerciseCount = { add: {}, edit: {} };
    @foreach(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'] as $day)
        exerciseCount['add']['{{ $day }}'] = 1;
        exerciseCount['edit']['{{ $day }}'] = {{ count($currentPlan[$day] ?? []) }};
    @endforeach

    function addExercise(day, mode) {
        const container = document.getElementById('exercises-' + mode + '-' + day);
        const idx = exerciseCount[mode][day]++;
        const html = `<div class="row g-2 mb-2 exercise-row">
            <div class="col-5"><select name="workout[${day}][${idx}][exercise]" class="form-select form-select-sm"><option value="">Select</option>@foreach($activities as $act)<option value="{{ $act->title }}">{{ $act->title }}</option>@endforeach</select></div>
            <div class="col-3"><input type="number" name="workout[${day}][${idx}][sets]" class="form-control form-control-sm" placeholder="Sets"></div>
            <div class="col-3"><input type="text" name="workout[${day}][${idx}][reps]" class="form-control form-control-sm" placeholder="Reps"></div>
            <div class="col-1"><button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="this.closest('.exercise-row').remove()"><i class="bi bi-x"></i></button></div>
        </div>`;
        container.insertAdjacentHTML('beforeend', html);
    }

    // FIX #1: Calculate renew with validation
    function calculateRenew() {
        const planSelect = document.getElementById('renewPlan');
        const classSelect = document.getElementById('renewClass');
        const planOption = planSelect.options[planSelect.selectedIndex];
        const classOption = classSelect.options[classSelect.selectedIndex];
        
        const planAmount = parseFloat(planOption?.dataset.amount) || 0;
        const classFees = parseFloat(classOption?.dataset.fees) || 0;
        const packageType = planOption?.dataset.package || 'monthly';
        
        // Auto start date
        @if($td && $td->membership_expiry_date && !$isExpired)
            const currentExpiry = new Date('{{ \Carbon\Carbon::parse($td->membership_expiry_date)->format("Y-m-d") }}');
            const nextDay = new Date(currentExpiry);
            nextDay.setDate(nextDay.getDate() + 1);
            document.getElementById('renewStart').value = nextDay.toISOString().split('T')[0];
        @else
            if (!document.getElementById('renewStart').value) {
                document.getElementById('renewStart').value = new Date().toISOString().split('T')[0];
            }
        @endif
        
        // Calculate expiry
        const start = new Date(document.getElementById('renewStart').value);
        let expiry = new Date(start);
        switch(packageType.toLowerCase()) {
            case 'weekly': case '1 week': expiry.setDate(expiry.getDate() + 6); break;
            case 'monthly': case '1 month': expiry.setMonth(expiry.getMonth() + 1); expiry.setDate(expiry.getDate() - 1); break;
            case 'quarterly': case '3 months': expiry.setMonth(expiry.getMonth() + 3); expiry.setDate(expiry.getDate() - 1); break;
            case 'half-yearly': case '6 months': expiry.setMonth(expiry.getMonth() + 6); expiry.setDate(expiry.getDate() - 1); break;
            case 'yearly': case '12 months': expiry.setFullYear(expiry.getFullYear() + 1); expiry.setDate(expiry.getDate() - 1); break;
            default: expiry.setMonth(expiry.getMonth() + 1); expiry.setDate(expiry.getDate() - 1);
        }
        document.getElementById('renewExpiry').value = expiry.toISOString().split('T')[0];
        
        // Calculate amounts
        const total = planAmount + classFees;
        const paidInput = document.getElementById('renewPaid');
        let paid = parseFloat(paidInput.value) || 0;
        
        // FIX #1: Live validation - paid can't exceed total
        const errorEl = document.getElementById('renewPaidError');
        const renewBtn = document.getElementById('renewBtn');
        if (paid > total && total > 0) {
            paidInput.value = total;
            paid = total;
            errorEl.style.display = 'block';
            renewBtn.disabled = true;
        } else {
            errorEl.style.display = 'none';
            renewBtn.disabled = false;
        }
        
        const due = total - paid;
        
        document.getElementById('renewPlanAmt').textContent = '₹' + planAmount.toFixed(0);
        document.getElementById('renewClassAmt').textContent = '₹' + classFees.toFixed(0);
        document.getElementById('renewPaidAmt').textContent = '-₹' + paid.toFixed(0);
        document.getElementById('renewDueAmt').textContent = '₹' + due.toFixed(0);
    }
    
    calculateRenew();
</script>
@endpush
