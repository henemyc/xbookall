@extends('admin.layouts.app')

@section('title', 'Dashboard')

@push('styles')
<style>
    .admin-hero {
        background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 52%, #4c1d95 100%);
        border: 0;
        color: #fff;
        border-radius: 24px;
        padding: 28px;
        position: relative;
        overflow: hidden;
        box-shadow: 0 22px 55px rgba(76, 29, 149, .22);
    }
    .admin-hero::after {
        content: '';
        position: absolute;
        width: 260px;
        height: 260px;
        border-radius: 50%;
        right: -90px;
        top: -90px;
        background: radial-gradient(circle, rgba(255,255,255,.18), transparent 64%);
    }
    .metric-card {
        background: #fff;
        border: 1px solid var(--border);
        border-radius: 20px;
        padding: 22px;
        height: 100%;
        box-shadow: 0 1px 3px rgba(0,0,0,.04);
        position: relative;
        overflow: hidden;
    }
    .metric-card::before {
        content: '';
        position: absolute;
        inset: 0 0 auto 0;
        height: 4px;
        background: var(--metric-gradient);
    }
    .metric-icon {
        width: 50px;
        height: 50px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        background: var(--metric-bg);
        color: var(--metric-color);
    }
    .metric-value {
        font-family: 'Space Grotesk', sans-serif;
        font-size: 32px;
        font-weight: 800;
        letter-spacing: -1px;
        margin-top: 14px;
    }
    .metric-label { color: var(--text-secondary); font-weight: 600; font-size: 13px; }
    .chart-shell {
        position: relative;
        min-height: 285px;
    }
    .chart-panel { display: none; }
    .chart-panel.active { display: block; }
    .area-chart {
        width: 100%;
        height: 235px;
        overflow: visible;
    }
    .chart-toggle {
        background: #f8fafc;
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 4px;
        display: inline-flex;
        gap: 4px;
    }
    .chart-toggle button {
        border: 0;
        background: transparent;
        padding: 8px 13px;
        border-radius: 11px;
        font-size: 12px;
        font-weight: 800;
        color: var(--text-secondary);
    }
    .chart-toggle button.active {
        color: #fff;
        background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        box-shadow: 0 8px 16px rgba(139, 92, 246, .22);
    }
    .chart-axis-label {
        font-size: 11px;
        fill: #6b7280;
        font-family: 'Poppins', sans-serif;
        font-weight: 600;
    }
    .chart-point-label {
        font-size: 11px;
        fill: #1f2937;
        font-family: 'Space Grotesk', sans-serif;
        font-weight: 800;
    }
    .last-active-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 9px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        background: rgba(22,199,132,.10);
        color: #0d9c5f;
    }
    .last-active-pill.offline {
        background: rgba(107,114,128,.10);
        color: #6b7280;
    }
    .analytics-card {
        background:
            radial-gradient(circle at 18% 0%, rgba(139, 92, 246, .10), transparent 32%),
            linear-gradient(180deg, #ffffff 0%, #fbfaff 100%);
    }
    .analytics-icon {
        width: 38px;
        height: 38px;
        border-radius: 13px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, rgba(139, 92, 246, .16), rgba(124, 58, 237, .08));
        color: #7c3aed;
        font-size: 18px;
    }
    .analytics-mini {
        border: 1px solid rgba(139, 92, 246, .14);
        border-radius: 16px;
        padding: 14px;
        background: rgba(139, 92, 246, .055);
    }
    .analytics-mini.orange {
        border-color: rgba(255, 107, 44, .16);
        background: rgba(255, 107, 44, .06);
    }
    .analytics-mini-value {
        font-family: 'Space Grotesk', sans-serif;
        font-size: 24px;
        font-weight: 800;
        color: #111827;
        letter-spacing: -0.5px;
        margin-top: 2px;
    }
</style>
@endpush

@section('content')
<div class="admin-hero mb-4">
    <div class="position-relative" style="z-index:1">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div>
                <div style="opacity:.62;font-size:13px;font-weight:600;">Welcome back,</div>
                <h2 class="mb-1" style="font-family:'Space Grotesk';font-weight:800;">{{ auth()->user()->name }} 👋</h2>
                <div style="opacity:.72;font-size:14px;">Track gyms, app activity, payments and onboarding growth from one dashboard.</div>
            </div>
            <div class="d-none d-md-flex" style="width:76px;height:76px;border-radius:24px;background:rgba(255,255,255,.12);align-items:center;justify-content:center;">
                <i class="bi bi-shield-check" style="font-size:38px;"></i>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="metric-card" style="--metric-gradient:linear-gradient(90deg,#8b5cf6,#7c3aed);--metric-bg:rgba(139,92,246,.12);--metric-color:#8b5cf6;">
            <div class="metric-icon"><i class="bi bi-building"></i></div>
            <div class="metric-value">{{ number_format($stats['total_gyms']) }}</div>
            <div class="metric-label">Total Gyms</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="metric-card" style="--metric-gradient:linear-gradient(90deg,#16c784,#0d9c5f);--metric-bg:rgba(22,199,132,.12);--metric-color:#16c784;">
            <div class="metric-icon"><i class="bi bi-activity"></i></div>
            <div class="metric-value">{{ number_format($stats['active_gyms']) }}</div>
            <div class="metric-label">Active Gyms</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="metric-card" style="--metric-gradient:linear-gradient(90deg,#ff4d4f,#d4380d);--metric-bg:rgba(255,77,79,.12);--metric-color:#ff4d4f;">
            <div class="metric-icon"><i class="bi bi-exclamation-triangle"></i></div>
            <div class="metric-value">{{ number_format($stats['expired_gyms']) }}</div>
            <div class="metric-label">Expired Gyms</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="metric-card" style="--metric-gradient:linear-gradient(90deg,#ff6b2c,#ffa726);--metric-bg:rgba(255,107,44,.12);--metric-color:#ff6b2c;">
            <div class="metric-icon"><i class="bi bi-calendar-plus"></i></div>
            <div class="metric-value">{{ number_format($stats['new_gyms_month']) }}</div>
            <div class="metric-label">New Gyms This Month</div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-7">
        <div class="table-card h-100 analytics-card">
            <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="analytics-icon"><i class="bi bi-activity"></i></span>
                        <h6 class="mb-0">New Gyms Onboarded</h6>
                    </div>
                    <div class="text-muted" style="font-size:12px;">Interactive growth analytics with hover insights.</div>
                </div>
                <div class="chart-toggle apex-toggle">
                    <button type="button" class="active" data-chart-range="monthly">Monthly</button>
                    <button type="button" data-chart-range="daily">Last 30 Days</button>
                </div>
            </div>

            <div class="row g-3 mb-2">
                <div class="col-6">
                    <div class="analytics-mini">
                        <div class="text-muted" style="font-size:11px;font-weight:700;">MONTHLY TOTAL</div>
                        <div class="analytics-mini-value">{{ number_format($monthlyGyms->sum('count')) }}</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="analytics-mini orange">
                        <div class="text-muted" style="font-size:11px;font-weight:700;">LAST 30 DAYS</div>
                        <div class="analytics-mini-value">{{ number_format($dailyGyms->sum('count')) }}</div>
                    </div>
                </div>
            </div>

            <div id="gymsOnboardChart" style="min-height: 330px;"></div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="table-card h-100">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h6 class="mb-0"><i class="bi bi-credit-card me-2" style="color:var(--success);"></i> Recent Payments</h6>
                <a href="{{ route('admin.revenue.payments') }}" class="btn btn-sm btn-outline-success">View All <i class="bi bi-arrow-right ms-1"></i></a>
            </div>
            @forelse($recentPayments as $payment)
                <div class="d-flex align-items-center {{ !$loop->last ? 'mb-3 pb-3 border-bottom' : '' }}">
                    <div class="avatar me-3" style="background:linear-gradient(135deg,#16c784,#0d9c5f);font-size:14px;width:42px;height:42px;border-radius:14px;">
                        <i class="bi bi-check-lg"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-semibold" style="font-size:13px;">{{ $payment->gym_business_name ?? 'Unknown Gym' }}</div>
                        <small class="text-muted">{{ $payment->plan->title ?? 'Plan' }} • {{ $payment->updated_at ? $payment->updated_at->diffForHumans() : '' }}</small>
                    </div>
                    <div class="fw-bold text-success" style="font-family:'Space Grotesk';">₹{{ number_format($payment->amount) }}</div>
                </div>
            @empty
                <div class="text-center py-4 text-muted">
                    <i class="bi bi-credit-card fs-1 d-block mb-2" style="opacity:.25"></i>
                    No recent payments
                </div>
            @endforelse
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-5">
        <div class="table-card h-100">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div>
                    <h6 class="mb-1"><i class="bi bi-pie-chart me-2" style="color:#ff6b2c;"></i> Gym Acquisition Sources</h6>
                    <div class="text-muted" style="font-size:12px;">How gym owners discovered GymXBook.</div>
                </div>
                <span class="badge bg-light text-dark">{{ $acquisitionSources->sum('total') }} gyms</span>
            </div>
            <div id="acquisitionChart" style="min-height:300px;"></div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="table-card h-100">
            <h6 class="mb-3"><i class="bi bi-list-check me-2" style="color:#8b5cf6;"></i> Acquisition Breakdown</h6>
            @forelse($acquisitionSources as $source)
                <div class="d-flex justify-content-between align-items-center {{ !$loop->last ? 'mb-3 pb-3 border-bottom' : '' }}">
                    <span>{{ ucwords(str_replace('_', ' ', $source->source)) }}</span>
                    <strong>{{ $source->total }}</strong>
                </div>
            @empty
                <div class="text-muted text-center py-4">No acquisition data yet.</div>
            @endforelse
        </div>
    </div>
</div>

<div class="table-card">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h6 class="mb-1"><i class="bi bi-building me-2" style="color:#8b5cf6;"></i> Recent Gym Activity</h6>
            <div class="text-muted" style="font-size:12px;">Latest gym owners who opened the app</div>
        </div>
        <a href="{{ route('admin.gyms.index') }}" class="btn btn-sm btn-outline-primary">View All <i class="bi bi-arrow-right ms-1"></i></a>
    </div>

    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Gym</th>
                    <th>Plan</th>
                    <th>Expiry</th>
                    <th>Last App Open</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentGyms as $gym)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar me-3" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed);font-size:14px;">
                                    {{ strtoupper(substr($gym->business_name ?? $gym->name, 0, 1)) }}
                                </div>
                                <div>
                                    <strong>{{ $gym->business_name ?? $gym->name }}</strong>
                                    <div class="text-muted" style="font-size:12px;">Owner: {{ $gym->name }} • {{ $gym->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            @if($gym->subscriptionTier)
                                @php
                                    $tierColor = \App\Services\SubscriptionFeatureService::tierColor($gym->subscriptionTier->code);
                                @endphp
                                <span class="badge" style="background:{{ $tierColor }};">{{ $gym->subscriptionTier->name }}</span>
                                <small class="d-block text-muted">SaaS Plan</small>
                            @elseif($gym->subscriptionPlan)
                                <span class="badge bg-primary">{{ $gym->subscriptionPlan->title }}</span>
                                <small class="d-block text-muted">Legacy Plan</small>
                            @else
                                <span class="badge bg-secondary">No Plan</span>
                            @endif
                        </td>
                        <td>
                            @php
                                $expiry = $gym->subscription_ends_at ?: $gym->subscription_expire_date;
                            @endphp
                            @if($expiry)
                                @php
                                    $expiryDate = \Carbon\Carbon::parse($expiry);
                                @endphp
                                <span class="{{ $expiryDate->copy()->endOfDay()->isPast() ? 'text-danger' : 'text-success' }} fw-semibold">
                                    {{ $expiryDate->format('d M Y') }}
                                </span>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>
                            @php
                                $lastAccessAt = $gym->last_app_opened_at ?: $gym->last_login_at;
                                $lastAccessSource = $gym->last_app_opened_at ? 'app' : ($gym->last_login_at ? 'web' : null);
                            @endphp
                            @if($lastAccessAt)
                                <span class="last-active-pill"><i class="bi {{ $lastAccessSource === 'app' ? 'bi-phone' : 'bi-globe2' }}"></i>{{ $lastAccessAt->diffForHumans() }}</span>
                                <div class="text-muted mt-1" style="font-size:11px;">
                                    {{ $lastAccessAt->format('d M Y, h:i A') }}
                                    @if($lastAccessSource) • {{ $lastAccessSource }} @endif
                                    @if($gym->last_app_opened_at && $gym->last_app_version) • v{{ $gym->last_app_version }} @endif
                                </div>
                            @else
                                <span class="last-active-pill offline"><i class="bi bi-phone-slash"></i>Never</span>
                            @endif
                        </td>
                        <td>
                            @if($gym->is_active)
                                <span class="badge bg-success">Active</span>
                            @else
                                <span class="badge bg-danger">Inactive</span>
                            @endif
                        </td>
                        <td><a href="{{ route('admin.gyms.show', $gym->id) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No gyms found</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const monthlyLabels = @json($monthlyGyms->pluck('label')->values());
    const monthlyValues = @json($monthlyGyms->pluck('count')->map(fn($v) => (int) $v)->values());
    const dailyLabels = @json($dailyGyms->pluck('label')->values());
    const dailyValues = @json($dailyGyms->pluck('count')->map(fn($v) => (int) $v)->values());

    const acquisitionLabels = @json($acquisitionLabels);
    const acquisitionValues = @json($acquisitionValues);
    const acquisitionEl = document.querySelector('#acquisitionChart');
    if (acquisitionEl && !acquisitionValues.length) {
        acquisitionEl.innerHTML = '<div class="text-muted text-center pt-5">No acquisition data yet.</div>';
    }
    if (acquisitionEl && typeof ApexCharts !== 'undefined' && acquisitionValues.length) {
        new ApexCharts(acquisitionEl, {
            chart: { type: 'donut', height: 300, fontFamily: 'Poppins, sans-serif' },
            series: acquisitionValues,
            labels: acquisitionLabels,
            colors: ['#ff6b2c', '#7c3aed', '#16c784', '#3b9eff', '#ffb020', '#ec4899', '#06b6d4', '#64748b'],
            legend: { position: 'bottom', fontSize: '12px' },
            dataLabels: { enabled: true, formatter: function (value) { return Math.round(value) + '%'; } },
            plotOptions: {
                pie: {
                    donut: {
                        size: '62%',
                        labels: {
                            show: true,
                            total: {
                                show: true,
                                label: 'Gyms',
                                formatter: function () {
                                    return acquisitionValues.reduce((a, b) => a + b, 0);
                                }
                            }
                        }
                    }
                }
            },
            tooltip: { y: { formatter: function (value) { return value + (value === 1 ? ' gym' : ' gyms'); } } }
        }).render();
    }

    const chartEl = document.querySelector('#gymsOnboardChart');
    if (!chartEl || typeof ApexCharts === 'undefined') return;

    const baseOptions = {
        chart: {
            type: 'area',
            height: 330,
            fontFamily: 'Poppins, sans-serif',
            toolbar: {
                show: true,
                tools: {
                    download: true,
                    selection: false,
                    zoom: true,
                    zoomin: true,
                    zoomout: true,
                    pan: false,
                    reset: true,
                }
            },
            animations: {
                enabled: true,
                easing: 'easeinout',
                speed: 700,
            },
        },
        series: [{ name: 'New Gyms', data: monthlyValues }],
        xaxis: {
            categories: monthlyLabels,
            labels: { style: { colors: '#6b7280', fontSize: '11px', fontWeight: 600 } },
            axisBorder: { show: false },
            axisTicks: { show: false },
            tooltip: { enabled: false },
        },
        yaxis: {
            min: 0,
            forceNiceScale: true,
            labels: {
                style: { colors: '#6b7280', fontSize: '11px', fontWeight: 600 },
                formatter: function (val) { return Math.round(val); }
            }
        },
        stroke: { curve: 'smooth', width: 4, colors: ['#7c3aed'] },
        fill: {
            type: 'gradient',
            gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.38,
                opacityTo: 0.03,
                stops: [0, 90, 100],
                colorStops: [
                    { offset: 0, color: '#8b5cf6', opacity: 0.38 },
                    { offset: 100, color: '#8b5cf6', opacity: 0.03 },
                ]
            }
        },
        markers: {
            size: 5,
            strokeWidth: 3,
            strokeColors: '#ffffff',
            colors: ['#8b5cf6'],
            hover: { size: 8 }
        },
        grid: {
            borderColor: '#eef2f7',
            strokeDashArray: 5,
            padding: { left: 12, right: 18, top: 8, bottom: 6 },
        },
        dataLabels: {
            enabled: true,
            formatter: function (val) { return val > 0 ? val : ''; },
            style: { fontSize: '11px', fontFamily: 'Space Grotesk, sans-serif', fontWeight: 800, colors: ['#111827'] },
            background: { enabled: true, foreColor: '#111827', borderRadius: 8, padding: 4, opacity: 0.90, borderWidth: 0 },
            offsetY: -8,
        },
        tooltip: {
            enabled: true,
            theme: 'light',
            x: { show: true },
            y: {
                formatter: function (val) {
                    return val + (val === 1 ? ' gym onboarded' : ' gyms onboarded');
                }
            },
            marker: { show: true },
        },
        colors: ['#8b5cf6'],
    };

    const chart = new ApexCharts(chartEl, baseOptions);
    chart.render();

    document.querySelectorAll('[data-chart-range]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const range = this.getAttribute('data-chart-range');
            document.querySelectorAll('[data-chart-range]').forEach(b => b.classList.remove('active'));
            this.classList.add('active');

            const isDaily = range === 'daily';
            chart.updateOptions({
                series: [{ name: 'New Gyms', data: isDaily ? dailyValues : monthlyValues }],
                xaxis: { categories: isDaily ? dailyLabels : monthlyLabels },
                dataLabels: { enabled: !isDaily || dailyValues.filter(v => v > 0).length <= 12 },
                tooltip: {
                    y: {
                        formatter: function (val) {
                            return val + (val === 1 ? ' gym onboarded' : ' gyms onboarded');
                        }
                    }
                }
            }, true, true);
        });
    });
});
</script>
@endpush
