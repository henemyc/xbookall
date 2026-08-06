@extends('panel.layouts.app')

@section('title', 'Subscription')

@push('styles')
<style>
    .sub-hero { background: linear-gradient(135deg, #0f172a, #1e293b 58%, #7c2d12); color:#fff; border-radius:24px; padding:24px; position:relative; overflow:hidden; box-shadow:0 18px 45px rgba(15,23,42,.16); }
    .sub-hero::after { content:''; position:absolute; right:-70px; top:-80px; width:230px; height:230px; border-radius:50%; background:radial-gradient(circle, rgba(255,255,255,.18), transparent 65%); }
    .current-plan-pill { display:inline-flex; align-items:center; gap:8px; border:1px solid rgba(255,255,255,.22); border-radius:999px; padding:7px 12px; font-size:12px; font-weight:800; letter-spacing:.02em; background:rgba(255,255,255,.1); backdrop-filter:blur(8px); }
    .current-plan-dot { width:9px; height:9px; border-radius:999px; display:inline-block; box-shadow:0 0 0 4px rgba(255,255,255,.12); }
    .tier-card { background:#fff; border:1px solid var(--border); border-radius:22px; padding:20px; height:100%; box-shadow:0 1px 3px rgba(0,0,0,.04); transition:.18s ease; position:relative; overflow:hidden; }
    .tier-card::before { content:''; position:absolute; left:0; right:0; top:0; height:5px; background:var(--tier-color, #ff6b2c); opacity:.92; }
    .tier-card:hover { transform:translateY(-2px); box-shadow:0 14px 34px rgba(15,23,42,.08); }
    .tier-card.current { border-color:var(--tier-color, #ff6b2c); box-shadow:0 16px 38px rgba(15,23,42,.12); }
    .tier-icon { width:52px;height:52px;border-radius:17px;display:flex;align-items:center;justify-content:center;font-size:25px;background:var(--tier-soft, rgba(255,107,44,.12));color:var(--tier-color, #ff6b2c); }
    .tier-name-badge { display:inline-flex; align-items:center; border-radius:999px; padding:5px 10px; font-size:11px; font-weight:800; background:var(--tier-soft, rgba(255,107,44,.12)); color:var(--tier-color, #ff6b2c); border:1px solid var(--tier-border, rgba(255,107,44,.25)); }
    .price-btn { border:1px solid var(--border); border-radius:14px; padding:12px; background:#f8fafc; text-align:left; width:100%; transition:.16s ease; }
    .price-btn:hover:not(:disabled) { border-color:var(--tier-color, #ff6b2c); background:var(--tier-soft, rgba(255,107,44,.08)); transform:translateY(-1px); }
    .feature-line { display:flex; gap:9px; align-items:flex-start; margin-bottom:9px; font-size:13px; }
    .feature-line .feature-label { flex:1; min-width:0; color:#334155; font-weight:600; }
    .feature-tooltip { color:#f59e0b; cursor:help; flex-shrink:0; margin-top:1px; }
</style>
@endpush

@section('content')
@php
    $expired = $isExpired || ($daysLeft !== null && $daysLeft < 0);
    $hasPlan = (bool) ($current || $legacyCurrent);
    $statusClass = !$hasPlan ? 'bg-secondary' : ($expired ? 'bg-danger' : (($daysLeft !== null && $daysLeft <= 7) ? 'bg-warning text-dark' : 'bg-success'));
    $statusText = !$hasPlan ? 'No Plan' : ($expired ? 'Expired' : 'Active');
    $currentCode = strtolower((string) ($current?->code ?? ''));
    $currentColor = \App\Services\SubscriptionFeatureService::tierColor($currentCode ?: null);
    $currentPlanName = $current?->name ?? $legacyCurrent?->title ?? 'No Active Plan';
    $currentPlanLabel = $current ? ucfirst($currentCode) . ' Plan' : ($legacyCurrent ? 'Legacy Plan' : 'No Plan');
@endphp

<div class="sub-hero mb-4">
    <div class="position-relative" style="z-index:1">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <div style="opacity:.7;font-size:12px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;">Current Subscription</div>
                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                    <h3 class="mb-0" style="font-family:'Space Grotesk';font-weight:800;">{{ $currentPlanName }}</h3>
                    <span class="current-plan-pill">
                        <span class="current-plan-dot" style="background:{{ $currentColor }};"></span>{{ $currentPlanLabel }}
                    </span>
                </div>
                <div style="opacity:.72;font-size:13px;">
                    @if($expiry)
                        Valid till {{ \Carbon\Carbon::parse($expiry)->format('d M Y') }}
                        @if($daysLeft !== null) • {{ $expired ? abs($daysLeft).' days expired' : $daysLeft.' days left' }} @endif
                    @else
                        Choose Bronze, Silver or Gold to activate your gym.
                    @endif
                </div>
            </div>
            <span class="badge {{ $statusClass }} px-3 py-2">{{ $statusText }}</span>
        </div>
    </div>
</div>

@if($tiers->isEmpty())
    <div class="alert alert-warning rounded-4">
        <strong>Plans not configured.</strong> Please contact support or ask Super Admin to run System Update.
    </div>
@else
<div class="row g-4">
    @foreach($tiers as $tier)
        @php
            $tierCode = strtolower((string) $tier->code);
            $color = \App\Services\SubscriptionFeatureService::tierColor($tierCode);
            $soft = $color . '18';
            $border = $color . '44';
            $isCurrent = $current && $current->id === $tier->id;
        @endphp
        <div class="col-xl-4 col-md-6">
            <div class="tier-card {{ $isCurrent ? 'current' : '' }}" style="--tier-color: {{ $color }}; --tier-soft: {{ $soft }}; --tier-border: {{ $border }};">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                    <div class="d-flex gap-3 align-items-center">
                        <div class="tier-icon"><i class="bi {{ $tierCode === 'gold' ? 'bi-trophy' : ($tierCode === 'silver' ? 'bi-stars' : 'bi-shield-check') }}"></i></div>
                        <div>
                            <div class="tier-name-badge mb-1">{{ ucfirst($tierCode) }}</div>
                            <h4 class="mb-1" style="font-family:'Space Grotesk';font-weight:800;">{{ $tier->name }}</h4>
                            <div class="d-flex gap-1 flex-wrap">
                                @if($tier->badge_text)<span class="badge bg-primary">{{ $tier->badge_text }}</span>@endif
                                @if($tier->is_coming_soon)<span class="badge bg-warning text-dark">Coming Soon</span>@endif
                                @if($isCurrent)<span class="badge bg-success">Current Active Plan</span>@endif
                            </div>
                        </div>
                    </div>
                </div>
                <p class="text-muted small">{{ $tier->description }}</p>

                <div class="mb-3">
                    @php
                        $cardFeatures = $tier->relationLoaded('cardFeatures')
                            ? $tier->cardFeatures->where('is_visible', true)->sortBy('sort_order')->values()
                            : collect();
                    @endphp
                    @if($cardFeatures->count() > 0)
                        @foreach($cardFeatures as $cardFeature)
                            <div class="feature-line">
                                <i class="bi {{ $cardFeature->is_included ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger' }}"></i>
                                <span class="feature-label">{{ $cardFeature->feature_label }}</span>
                                @if($cardFeature->tooltip_text)
                                    <i class="bi bi-exclamation-circle-fill feature-tooltip" title="{{ $cardFeature->tooltip_text }}" data-bs-toggle="tooltip" data-bs-placement="top"></i>
                                @endif
                            </div>
                        @endforeach
                    @else
                        @foreach($tier->features->where('is_highlighted', true)->take(7) as $feature)
                            @php
                                $value = $feature->castValue();
                                $labelValue = is_bool($value) ? ($value ? 'Yes' : 'No') : ($value === 'coming_soon' ? 'Coming Soon' : $value);
                                $ok = $labelValue !== 'No' && $labelValue !== '0';
                            @endphp
                            <div class="feature-line">
                                <i class="bi {{ $ok ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger' }}"></i>
                                <span class="feature-label">{{ $feature->feature_label }} <small class="text-muted">({{ $labelValue }})</small></span>
                            </div>
                        @endforeach
                    @endif
                </div>

                <div class="d-grid gap-2">
                    @forelse($tier->prices as $price)
                        @if($price->is_active && !$tier->is_coming_soon)
                            <form action="{{ route('panel.subscription.createPayment') }}" method="POST">
                                @csrf
                                <input type="hidden" name="subscription_tier_id" value="{{ $tier->id }}">
                                <input type="hidden" name="subscription_tier_price_id" value="{{ $price->id }}">
                                <input type="hidden" name="type" value="{{ $isCurrent ? 'renew' : 'upgrade' }}">
                                <button type="submit" class="price-btn">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong>{{ $price->duration_months }} Month{{ $price->duration_months == 1 ? '' : 's' }}</strong>
                                            <div class="text-muted small">{{ ucfirst($price->billing_cycle) }}{{ $price->discount_text ? ' • '.$price->discount_text : '' }}</div>
                                        </div>
                                        <div class="text-end">
                                            <div class="fw-bold" style="color:{{ $color }};">₹{{ number_format($price->price) }}</div>
                                            @if($price->strike_price)<small class="text-muted text-decoration-line-through">₹{{ number_format($price->strike_price) }}</small>@endif
                                        </div>
                                    </div>
                                </button>
                            </form>
                        @else
                            <button type="button" class="price-btn" disabled>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>{{ $price->duration_months }} Month{{ $price->duration_months == 1 ? '' : 's' }}</strong>
                                        <div class="text-muted small">{{ $tier->is_coming_soon ? 'Coming Soon' : 'Inactive' }}</div>
                                    </div>
                                    <div class="text-end"><div class="fw-bold text-muted">₹{{ number_format($price->price) }}</div></div>
                                </div>
                            </button>
                        @endif
                    @empty
                        <div class="text-muted small">No pricing configured.</div>
                    @endforelse
                </div>
            </div>
        </div>
    @endforeach
</div>
@endif

@if($orders && $orders->count() > 0)
<div class="table-card mt-4">
    <h6 class="mb-3"><i class="bi bi-clock-history me-2"></i> Recent Subscription Orders</h6>
    <div class="table-responsive">
        <table class="table table-sm align-middle">
            <thead><tr><th>Order ID</th><th>Plan</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
            @foreach($orders as $order)
                <tr>
                    <td><small class="text-muted">{{ $order->order_id }}</small></td>
                    <td>{{ $order->tier?->name ?? $order->plan?->title ?? 'N/A' }}</td>
                    <td>₹{{ number_format($order->amount) }}</td>
                    <td><span class="badge {{ strtolower($order->status) === 'paid' ? 'bg-success' : 'bg-warning text-dark' }}">{{ $order->status }}</span></td>
                    <td><small>{{ $order->created_at->format('d M Y, h:i A') }}</small></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

<div class="mt-4 text-center text-muted small">Select a duration to continue to the secure payment gateway.</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.bootstrap && bootstrap.Tooltip) {
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            new bootstrap.Tooltip(el);
        });
    }
});
</script>
@endpush
