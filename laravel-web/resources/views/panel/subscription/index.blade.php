@extends('panel.layouts.app')

@section('title', 'Subscription')

@push('styles')
<style>
    .sub-hero {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 58%, #7c2d12 130%);
        color: #fff; border-radius: 24px; padding: 28px; position: relative; overflow: hidden;
        box-shadow: 0 18px 45px rgba(15,23,42,.18);
    }
    .sub-hero::after { content:''; position:absolute; right:-80px; top:-90px; width:260px; height:260px; border-radius:50%; background: radial-gradient(circle, rgba(255,107,44,.4), transparent 65%); }
    .sub-hero > div { position: relative; z-index: 1; }
    .current-plan-pill { display:inline-flex; align-items:center; gap:8px; border:1px solid rgba(255,255,255,.22); border-radius:999px; padding:7px 12px; font-size:12px; font-weight:800; letter-spacing:.02em; background:rgba(255,255,255,.1); backdrop-filter:blur(8px); }
    .current-plan-dot { width:9px; height:9px; border-radius:999px; display:inline-block; box-shadow:0 0 0 4px rgba(255,255,255,.12); }

    /* ── tier cards ─────────────────────────────────────────── */
    .tier-card {
        background:#fff; border:1px solid var(--border); border-radius:22px; padding:0;
        height:100%; display:flex; flex-direction:column;
        box-shadow:0 1px 3px rgba(0,0,0,.04); transition:.18s ease; position:relative; overflow:hidden;
    }
    .tier-card::before { content:''; position:absolute; left:0; right:0; top:0; height:5px; background:var(--tier-color, #b45309); }
    .tier-card:hover { transform:translateY(-2px); box-shadow:0 14px 34px rgba(15,23,42,.08); }
    .tier-card.current { border-color:var(--tier-color, #b45309); box-shadow:0 16px 38px rgba(15,23,42,.12); }
    .tier-card.recommended { border-color:var(--tier-color, #2563eb); }
    .tier-body { padding:24px 22px 22px; display:flex; flex-direction:column; flex:1; }
    .tier-icon { width:52px; height:52px; border-radius:17px; display:flex; align-items:center; justify-content:center; font-size:25px; background:var(--tier-soft, rgba(180,83,9,.12)); color:var(--tier-color, #b45309); flex-shrink:0; }
    .tier-name-badge { display:inline-flex; align-items:center; border-radius:999px; padding:5px 10px; font-size:11px; font-weight:800; background:var(--tier-soft, rgba(180,83,9,.12)); color:var(--tier-color, #b45309); border:1px solid var(--tier-border, rgba(180,83,9,.25)); }
    .popular-ribbon {
        position:absolute; right:0; top:5px; background:var(--tier-color,#2563eb); color:#fff;
        padding:6px 14px; font-size:10px; font-weight:900; letter-spacing:.08em;
        border-bottom-left-radius:12px;
    }
    .feature-list { display:grid; gap:9px; margin:16px 0; }
    .feature-line { display:flex; gap:9px; align-items:flex-start; font-size:13px; }
    .feature-line .fi { flex-shrink:0; font-size:16px; margin-top:0; }
    .feature-line .fi.on { color:#16a34a; }
    .feature-line .fi.off { color:#cbd5e1; }
    .feature-line .feature-label { flex:1; min-width:0; color:#334155; font-weight:600; line-height:1.4; }
    .feature-line.off .feature-label { color:#94a3b8; }
    .feature-tooltip { color:#f59e0b; cursor:help; flex-shrink:0; margin-top:1px; }
    .price-btn { border:1.5px solid var(--border); border-radius:14px; padding:13px 14px; background:#fff; text-align:left; width:100%; transition:.16s ease; }
    .price-btn:hover:not(:disabled) { border-color:var(--tier-color, #b45309); background:var(--tier-soft, rgba(180,83,9,.06)); transform:translateY(-1px); }
    .price-btn .amt { font-family:'Space Grotesk',sans-serif; font-weight:800; font-size:17px; color:var(--tier-color,#b45309); }
    .cta-choose {
        display:flex; align-items:center; justify-content:center; gap:8px; height:50px;
        border-radius:14px; background:linear-gradient(135deg, var(--tier-color,#b45309), color-mix(in srgb, var(--tier-color,#b45309) 80%, #000));
        color:#fff; font-weight:800; font-size:14.5px; margin-top:auto;
        box-shadow:0 10px 22px -8px var(--tier-color,#b45309);
    }
    .cta-choose.disabled { background:#e2e8f0; color:#94a3b8; box-shadow:none; }
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

{{-- ─── Hero ──────────────────────────────────────────────── --}}
<div class="sub-hero mb-4">
    <div>
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
            $soft = $color . '14';
            $border = $color . '44';
            $isCurrent = $current && $current->id === $tier->id;
            $recommended = $tierCode === 'silver';
            $tierIcon = $tierCode === 'gold' ? 'bi-trophy' : ($tierCode === 'silver' ? 'bi-stars' : 'bi-shield-check');

            $cardFeatures = $tier->relationLoaded('cardFeatures')
                ? $tier->cardFeatures->where('is_visible', true)->sortBy('sort_order')->values()
                : collect();
        @endphp
        <div class="col-xl-4 col-md-6">
            <div class="tier-card {{ $isCurrent ? 'current' : '' }} {{ $recommended ? 'recommended' : '' }}" style="--tier-color: {{ $color }}; --tier-soft: {{ $soft }}; --tier-border: {{ $border }};">
                @if($recommended)
                    <div class="popular-ribbon"><i class="bi bi-lightning-charge-fill me-1"></i>MOST POPULAR</div>
                @endif
                <div class="tier-body">
                    <div class="d-flex align-items-start gap-3 mb-3">
                        <div class="tier-icon"><i class="bi {{ $tierIcon }}"></i></div>
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="tier-name-badge">{{ ucfirst($tierCode) }}</span>
                                @if($tier->badge_text)<span class="badge bg-primary">{{ $tier->badge_text }}</span>@endif
                                @if($tier->is_coming_soon)<span class="badge bg-warning text-dark">Coming Soon</span>@endif
                                @if($isCurrent)<span class="badge bg-success">Current Plan</span>@endif
                            </div>
                            <h4 class="mb-0 mt-2" style="font-family:'Space Grotesk';font-weight:800;">{{ $tier->name }}</h4>
                        </div>
                    </div>

                    @if($tier->description)
                        <p class="text-muted small mb-3">{{ $tier->description }}</p>
                    @endif

                    {{-- App features (card features from Super Admin, not limits) --}}
                    <div class="feature-list">
                        @if($cardFeatures->count() > 0)
                            @foreach($cardFeatures as $cardFeature)
                                <div class="feature-line {{ $cardFeature->is_included ? '' : 'off' }}">
                                    <i class="bi fi {{ $cardFeature->is_included ? 'bi-check-circle-fill on' : 'bi-x-circle-fill off' }}"></i>
                                    <span class="feature-label">{{ $cardFeature->feature_label }}</span>
                                    @if($cardFeature->tooltip_text)
                                        <i class="bi bi-question-circle-fill feature-tooltip" title="{{ $cardFeature->tooltip_text }}" data-bs-toggle="tooltip" data-bs-placement="top"></i>
                                    @endif
                                </div>
                            @endforeach
                        @else
                            <div class="text-muted small">Feature list coming soon.</div>
                        @endif
                    </div>

                    {{-- Pricing --}}
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
                                                <div class="amt">₹{{ number_format($price->price) }}</div>
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
                                        <div class="text-end"><div class="text-muted fw-bold">₹{{ number_format($price->price) }}</div></div>
                                    </div>
                                </button>
                            @endif
                        @empty
                            <div class="text-muted small">No pricing configured.</div>
                        @endforelse
                    </div>
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
