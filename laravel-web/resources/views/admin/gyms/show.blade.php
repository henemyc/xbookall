@extends('admin.layouts.app')

@section('title', 'Gym Details')

@section('content')
@php
    $currentTier = $gym->subscriptionTier;
    $currentTierPrice = $gym->subscriptionPrice;
    $currentLegacyPlan = $gym->subscriptionPlan;
    $currentFormat = $currentTier ? 'new' : 'legacy';
    $currentPlanName = $currentTier?->name ?? $currentLegacyPlan?->title ?? 'No Plan';
    $currentPlanCode = strtolower((string) ($currentTier?->code ?? 'legacy'));
    $currentPlanColor = \App\Services\SubscriptionFeatureService::tierColor($currentPlanCode);
    $currentExpiryRaw = $gym->subscription_ends_at ?: $gym->subscription_expire_date;
    $currentExpiryDate = $currentExpiryRaw ? \Carbon\Carbon::parse($currentExpiryRaw)->format('Y-m-d') : '';
    $currentExpiryDisplay = $currentExpiryRaw ? \Carbon\Carbon::parse($currentExpiryRaw)->format('d M Y') : null;
    $currentExpired = $currentExpiryRaw ? \Carbon\Carbon::parse($currentExpiryRaw)->endOfDay()->isPast() : false;
    $currentStartDate = $gym->subscription_started_at ? \Carbon\Carbon::parse($gym->subscription_started_at)->format('Y-m-d') : now('Asia/Kolkata')->format('Y-m-d');
    $currentStatus = $gym->subscription_status ?: ($currentExpired ? 'expired' : 'active');
@endphp

@push('styles')
<style>
    .subscription-manager-card { border:1px solid #e5e7eb; border-radius:22px; padding:22px; background:linear-gradient(180deg,#fff,#f8fafc); box-shadow:0 12px 34px rgba(15,23,42,.06); }
    .current-subscription-box { border-radius:20px; padding:18px; color:#fff; background:linear-gradient(135deg,#111827,#1f2937 60%, var(--plan-color, #64748b)); position:relative; overflow:hidden; }
    .current-subscription-box::after { content:''; position:absolute; right:-70px; top:-80px; width:220px; height:220px; border-radius:50%; background:radial-gradient(circle,rgba(255,255,255,.18),transparent 65%); }
    .format-pill { border:1px solid #e5e7eb; border-radius:16px; padding:12px; background:#fff; cursor:pointer; transition:.16s ease; height:100%; }
    .format-pill:hover { border-color:#ff8a3d; box-shadow:0 8px 20px rgba(255,107,44,.08); }
    .format-pill input { margin-top:3px; }
    .format-panel { border:1px dashed #d1d5db; border-radius:18px; padding:16px; background:#fff; }
    .format-panel.d-none { display:none !important; }
</style>
@endpush

<div class="row g-4">
    <!-- Gym Info Card -->
    <div class="col-md-4">
        <div class="stat-card">
            <div class="text-center mb-3">
                <div class="avatar avatar-lg mx-auto" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed); font-size: 40px; width: 90px; height: 90px; border-radius: 24px;">
                    {{ strtoupper(substr($gymName, 0, 1)) }}
                </div>
            </div>

            <h4 class="text-center mb-1">{{ $gymName }}</h4>
            <p class="text-center text-muted mb-3">{{ $gym->email }}</p>

            <div class="text-center mb-4">
                @if($gym->is_active)
                    <span class="badge bg-success fs-6 px-3 py-2">✅ Active</span>
                @else
                    <span class="badge bg-danger fs-6 px-3 py-2">❌ Inactive</span>
                @endif
            </div>

            <div class="border-top pt-3">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted"><i class="bi bi-phone me-2"></i>Phone:</span>
                    <span>{{ $gymPhone ?: '-' }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted"><i class="bi bi-geo-alt me-2"></i>Address:</span>
                    <span class="text-end" style="max-width: 60%;">{{ $gymAddress ?: '-' }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted"><i class="bi bi-calendar me-2"></i>Joined:</span>
                    <span>{{ $gym->created_at->format('d M Y') }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted"><i class="bi bi-award me-2"></i>Plan:</span>
                    @if($currentTier)
                        <span class="badge" style="background:{{ $currentPlanColor }};">{{ $currentTier->name }}</span>
                    @elseif($currentLegacyPlan)
                        <span class="badge bg-primary">{{ $currentLegacyPlan->title }}</span>
                    @else
                        <span class="text-muted">No Plan</span>
                    @endif
                </div>
                @if($currentExpiryDisplay)
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted"><i class="bi bi-clock me-2"></i>Expires:</span>
                    <span class="{{ $currentExpired ? 'text-danger fw-bold' : '' }}">{{ $currentExpiryDisplay }}</span>
                </div>
                @endif
            </div>

            <!-- Actions -->
            <div class="mt-4">
                <form action="{{ route('admin.gyms.loginAs', $gym->id) }}" method="POST" class="mb-2">
                    @csrf
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-box-arrow-in-right me-2"></i> Login as Gym Owner
                    </button>
                </form>

                <form action="{{ route('admin.gyms.toggle', $gym->id) }}" method="POST" class="mb-2">
                    @csrf
                    @method('PUT')
                    <button type="submit" class="btn {{ $gym->is_active ? 'btn-outline-warning' : 'btn-outline-success' }} w-100">
                        <i class="bi bi-toggle-{{ $gym->is_active ? 'on' : 'off' }} me-2"></i>
                        {{ $gym->is_active ? 'Deactivate Gym' : 'Activate Gym' }}
                    </button>
                </form>

                <button class="btn btn-outline-danger w-100" onclick="confirmDelete('{{ route('admin.gyms.destroy', $gym->id) }}', 'This will permanently delete {{ $gymName }} and ALL related data ({{ $memberCount }} members, {{ $invoiceCount }} invoices). This cannot be undone!')">
                    <i class="bi bi-trash3 me-2"></i> Delete Gym Permanently
                </button>
            </div>
        </div>

        <!-- Data Summary -->
        <div class="table-card mt-4">
            <h6 class="mb-3"><i class="bi bi-database me-2"></i> Data Summary</h6>
            <div class="d-flex justify-content-between py-2 border-bottom"><span class="text-muted">Members</span><strong>{{ $memberCount }}</strong></div>
            <div class="d-flex justify-content-between py-2 border-bottom"><span class="text-muted">Trainers</span><strong>{{ $trainerCount }}</strong></div>
            <div class="d-flex justify-content-between py-2 border-bottom"><span class="text-muted">Invoices</span><strong>{{ $invoiceCount }}</strong></div>
            <div class="d-flex justify-content-between py-2 border-bottom"><span class="text-muted">Attendance</span><strong>{{ $attendanceCount }}</strong></div>
            <div class="d-flex justify-content-between py-2"><span class="text-muted">Expenses</span><strong>{{ $expenseCount }}</strong></div>
        </div>
    </div>

    <!-- Details -->
    <div class="col-md-8">
        <!-- Stats -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="stat-card text-center">
                    <div class="value" style="color: #8b5cf6;">{{ $memberCount }}</div>
                    <div class="label">Members</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card text-center">
                    <div class="value" style="color: var(--success);">{{ $trainerCount }}</div>
                    <div class="label">Trainers</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card text-center">
                    <div class="value" style="color: {{ $currentTier ? $currentPlanColor : 'var(--info)' }}; font-size: 18px;">{{ $currentPlanName }}</div>
                    <div class="label">Current Plan</div>
                </div>
            </div>
        </div>

        <!-- Subscription Manager -->
        <div class="subscription-manager-card mb-4">
            <div class="current-subscription-box mb-4" style="--plan-color: {{ $currentPlanColor }};">
                <div class="position-relative" style="z-index:1;">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                        <div>
                            <div style="opacity:.72;font-size:12px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;">Current Subscription</div>
                            <h4 class="mb-1" style="font-family:'Space Grotesk';font-weight:800;">{{ $currentPlanName }}</h4>
                            <div style="opacity:.78;font-size:13px;">
                                {{ $currentTier ? 'New SaaS format' : ($currentLegacyPlan ? 'Old legacy format' : 'No format selected') }}
                                @if($currentTierPrice)
                                    • {{ $currentTierPrice->duration_months }} month{{ $currentTierPrice->duration_months == 1 ? '' : 's' }}
                                @endif
                                @if($currentExpiryDisplay)
                                    • expires {{ $currentExpiryDisplay }}
                                @endif
                            </div>
                        </div>
                        <span class="badge {{ $currentExpired ? 'bg-danger' : 'bg-success' }} px-3 py-2">{{ ucfirst($currentStatus) }}</span>
                    </div>
                </div>
            </div>

            <h6 class="mb-3"><i class="bi bi-award me-2"></i> Change Plan, Duration & Expiry</h6>
            <form action="{{ route('admin.gyms.updateSubscription', $gym->id) }}" method="POST" id="subscriptionManagerForm">
                @csrf

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="format-pill d-flex gap-3">
                            <input type="radio" name="subscription_format" value="legacy" @checked($currentFormat === 'legacy')>
                            <span>
                                <strong>Old Subscription Format</strong>
                                <small class="d-block text-muted">Uses old subscriptions table and subscription_expire_date.</small>
                            </span>
                        </label>
                    </div>
                    <div class="col-md-6">
                        <label class="format-pill d-flex gap-3">
                            <input type="radio" name="subscription_format" value="new" @checked($currentFormat === 'new')>
                            <span>
                                <strong>New SaaS Plan Format</strong>
                                <small class="d-block text-muted">Uses Bronze / Silver / Gold tiers, prices and feature limits.</small>
                            </span>
                        </label>
                    </div>
                </div>

                <div id="legacySubscriptionPanel" class="format-panel mb-3">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Legacy Plan</label>
                            <select name="plan_id" id="legacyPlanSelect" class="form-select">
                                <option value="">Select old plan</option>
                                @foreach($plans as $plan)
                                    @php
                                        $interval = strtolower((string) ($plan->interval ?? 'monthly'));
                                        $legacyMonths = str_contains($interval, 'year') || str_contains($interval, 'annual') || str_contains($interval, '12') ? 12 : (str_contains($interval, 'quarter') || str_contains($interval, '3') ? 3 : (str_contains($interval, 'half') || str_contains($interval, '6') ? 6 : 1));
                                    @endphp
                                    <option value="{{ $plan->id }}" data-months="{{ $legacyMonths }}" @selected((int) $gym->subscription === (int) $plan->id)>
                                        {{ $plan->title }} - ₹{{ number_format($plan->package_amount) }}/{{ $plan->interval }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Suggested Duration</label>
                            <div class="form-control bg-light" id="legacyDurationHint">Select plan</div>
                        </div>
                    </div>
                </div>

                <div id="newSubscriptionPanel" class="format-panel mb-3">
                    @if($tiers->isEmpty())
                        <div class="alert alert-warning mb-0">New SaaS tiers are not available. Run System Update first.</div>
                    @else
                        <div class="row g-3">
                            <div class="col-md-5">
                                <label class="form-label">SaaS Plan</label>
                                <select name="subscription_tier_id" id="tierSelect" class="form-select">
                                    <option value="">Select SaaS plan</option>
                                    @foreach($tiers as $tier)
                                        <option value="{{ $tier->id }}" @selected((int) $gym->subscription_tier_id === (int) $tier->id)>
                                            {{ $tier->name }}{{ $tier->is_coming_soon ? ' (Coming Soon)' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-7">
                                <label class="form-label">Plan Duration / Price</label>
                                <select name="subscription_tier_price_id" id="tierPriceSelect" class="form-select">
                                    <option value="" data-tier="" data-months="">Custom duration without price row</option>
                                    @foreach($tiers as $tier)
                                        @foreach($tier->prices as $price)
                                            <option value="{{ $price->id }}" data-tier="{{ $tier->id }}" data-months="{{ $price->duration_months }}" @selected((int) $gym->subscription_price_id === (int) $price->id)>
                                                {{ $tier->name }} - {{ $price->duration_months }} month{{ $price->duration_months == 1 ? '' : 's' }} - ₹{{ number_format($price->price) }}{{ $price->is_active ? '' : ' (Inactive)' }}
                                            </option>
                                        @endforeach
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" id="subscriptionStartDate" class="form-control" value="{{ $currentStartDate }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Duration Months</label>
                        <input type="number" name="duration_months" id="durationMonths" class="form-control" min="1" max="120" value="{{ $currentTierPrice?->duration_months ?? '' }}" placeholder="eg. 1, 3, 12">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Expiry Date</label>
                        <input type="date" name="expiry_date" id="subscriptionExpiryDate" class="form-control" value="{{ $currentExpiryDate }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="subscription_status" class="form-select">
                            @foreach(['active' => 'Active', 'trial' => 'Trial', 'pending' => 'Pending', 'expired' => 'Expired', 'cancelled' => 'Cancelled', 'inactive' => 'Inactive'] as $statusKey => $statusLabel)
                                <option value="{{ $statusKey }}" @selected($currentStatus === $statusKey)>{{ $statusLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 d-flex flex-wrap gap-2 justify-content-end">
                        <button type="button" class="btn btn-outline-secondary" id="calculateExpiryBtn">
                            <i class="bi bi-calendar-plus me-1"></i> Calculate Expiry From Duration
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i> Update Subscription
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Edit Form -->
        <div class="table-card mb-4">
            <h6 class="mb-4"><i class="bi bi-pencil me-2"></i> Edit Gym Details</h6>

            <form action="{{ route('admin.gyms.update', $gym->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Gym Name</label>
                        <input type="text" name="company_name" class="form-control" value="{{ $gymName }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Owner Name</label>
                        <input type="text" name="name" class="form-control" value="{{ $gym->name }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="{{ $gym->email }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Phone</label>
                        <input type="text" name="company_phone" class="form-control" value="{{ $gymPhone }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Address</label>
                        <textarea name="company_address" class="form-control" rows="2">{{ $gymAddress }}</textarea>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-2"></i> Save Changes
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Recent Orders -->
        <div class="table-card">
            <h6 class="mb-4"><i class="bi bi-credit-card me-2"></i> Recent Orders</h6>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Plan</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($orders as $order)
                        <tr>
                            <td><code style="font-size: 11px;">{{ substr($order->order_id, 0, 20) }}...</code></td>
                            <td>
                                {{ $order->tier?->name ?? $order->plan?->title ?? '-' }}
                                @if($order->tierPrice)
                                    <small class="text-muted d-block">{{ $order->tierPrice->duration_months }} month{{ $order->tierPrice->duration_months == 1 ? '' : 's' }}</small>
                                @endif
                            </td>
                            <td class="fw-bold">₹{{ number_format($order->amount) }}</td>
                            <td>
                                @if($order->status === 'PAID')
                                    <span class="badge bg-success">Paid</span>
                                @elseif($order->status === 'CREATED')
                                    <span class="badge bg-warning">Pending</span>
                                @else
                                    <span class="badge bg-secondary">{{ $order->status }}</span>
                                @endif
                            </td>
                            <td>{{ $order->created_at->format('d M Y') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center py-3">No orders yet</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const formatRadios = document.querySelectorAll('input[name="subscription_format"]');
    const legacyPanel = document.getElementById('legacySubscriptionPanel');
    const newPanel = document.getElementById('newSubscriptionPanel');
    const legacyPlanSelect = document.getElementById('legacyPlanSelect');
    const legacyDurationHint = document.getElementById('legacyDurationHint');
    const tierSelect = document.getElementById('tierSelect');
    const tierPriceSelect = document.getElementById('tierPriceSelect');
    const durationInput = document.getElementById('durationMonths');
    const startInput = document.getElementById('subscriptionStartDate');
    const expiryInput = document.getElementById('subscriptionExpiryDate');
    const calculateBtn = document.getElementById('calculateExpiryBtn');

    function selectedFormat() {
        const checked = document.querySelector('input[name="subscription_format"]:checked');
        return checked ? checked.value : 'legacy';
    }

    function toggleFormat() {
        const mode = selectedFormat();
        legacyPanel.classList.toggle('d-none', mode !== 'legacy');
        newPanel.classList.toggle('d-none', mode !== 'new');
        if (mode === 'legacy') syncLegacyDuration();
        if (mode === 'new') filterTierPrices();
    }

    function syncLegacyDuration() {
        if (!legacyPlanSelect) return;
        const option = legacyPlanSelect.options[legacyPlanSelect.selectedIndex];
        const months = option ? option.dataset.months : '';
        legacyDurationHint.textContent = months ? months + ' month' + (months === '1' ? '' : 's') : 'Select plan';
        if (selectedFormat() === 'legacy' && months && !durationInput.value) durationInput.value = months;
    }

    function filterTierPrices() {
        if (!tierSelect || !tierPriceSelect) return;
        const tierId = tierSelect.value;
        Array.from(tierPriceSelect.options).forEach((option) => {
            const optionTier = option.dataset.tier || '';
            option.hidden = optionTier && tierId && optionTier !== tierId;
            option.disabled = optionTier && tierId && optionTier !== tierId;
        });
        const selectedOption = tierPriceSelect.options[tierPriceSelect.selectedIndex];
        if (selectedOption && selectedOption.disabled) tierPriceSelect.value = '';
    }

    function syncPriceDuration() {
        if (!tierPriceSelect) return;
        const option = tierPriceSelect.options[tierPriceSelect.selectedIndex];
        const months = option ? option.dataset.months : '';
        if (months) durationInput.value = months;
        const tierId = option ? option.dataset.tier : '';
        if (tierId && tierSelect) tierSelect.value = tierId;
        filterTierPrices();
    }

    function addMonthsNoOverflow(dateText, months) {
        if (!dateText || !months) return '';
        const parts = dateText.split('-').map(Number);
        const year = parts[0], month = parts[1] - 1, day = parts[2];
        const targetMonth = month + Number(months);
        const lastDay = new Date(year, targetMonth + 1, 0).getDate();
        const result = new Date(year, targetMonth, Math.min(day, lastDay));
        const y = result.getFullYear();
        const m = String(result.getMonth() + 1).padStart(2, '0');
        const d = String(result.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }

    function calculateExpiry() {
        const months = parseInt(durationInput.value || '0', 10);
        if (!months || months < 1) {
            alert('Enter duration months first.');
            return;
        }
        expiryInput.value = addMonthsNoOverflow(startInput.value, months);
    }

    formatRadios.forEach((radio) => radio.addEventListener('change', toggleFormat));
    legacyPlanSelect?.addEventListener('change', function () { durationInput.value = ''; syncLegacyDuration(); });
    tierSelect?.addEventListener('change', filterTierPrices);
    tierPriceSelect?.addEventListener('change', syncPriceDuration);
    calculateBtn?.addEventListener('click', calculateExpiry);

    toggleFormat();
    syncLegacyDuration();
    syncPriceDuration();
})();
</script>
@endpush
