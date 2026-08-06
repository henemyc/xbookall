@extends('admin.layouts.app')

@section('title', 'Payment Gateways')

@push('styles')
<style>
    .gateway-hero {
        background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 55%, #4c1d95 100%);
        color: white;
        border-radius: 26px;
        padding: 30px;
        position: relative;
        overflow: hidden;
        box-shadow: 0 22px 55px rgba(76, 29, 149, .24);
    }
    .gateway-hero::after {
        content: '';
        position: absolute;
        width: 280px;
        height: 280px;
        right: -100px;
        top: -100px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(255,255,255,.20), transparent 64%);
    }
    .gateway-shell {
        display: grid;
        grid-template-columns: 330px minmax(0, 1fr);
        gap: 22px;
        align-items: start;
    }
    .gateway-sidebar,
    .gateway-panel {
        background: #fff;
        border: 1px solid var(--border);
        border-radius: 24px;
        box-shadow: 0 1px 3px rgba(0,0,0,.04);
    }
    .gateway-sidebar { padding: 12px; position: sticky; top: 24px; }
    .gateway-tab {
        width: 100%;
        border: 0;
        background: transparent;
        border-radius: 18px;
        padding: 14px;
        display: flex;
        align-items: center;
        gap: 13px;
        text-align: left;
        color: var(--text);
        transition: .2s ease;
    }
    .gateway-tab:hover { background: #f8fafc; }
    .gateway-tab.active {
        background: linear-gradient(135deg, rgba(139,92,246,.14), rgba(124,58,237,.06));
        box-shadow: inset 0 0 0 1px rgba(139,92,246,.16);
    }
    .gateway-icon {
        width: 48px;
        height: 48px;
        border-radius: 16px;
        display:flex;
        align-items:center;
        justify-content:center;
        background: linear-gradient(135deg, rgba(139,92,246,.16), rgba(124,58,237,.06));
        color:#7c3aed;
        font-size:22px;
        flex-shrink: 0;
    }
    .gateway-tab.active .gateway-icon {
        background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        color: #fff;
        box-shadow: 0 10px 20px rgba(139,92,246,.25);
    }
    .gateway-title { font-weight: 800; font-size: 14px; margin-bottom: 2px; }
    .gateway-sub { font-size: 11.5px; color: var(--text-secondary); }
    .gateway-status-dot {
        width: 9px;
        height: 9px;
        border-radius: 999px;
        display: inline-block;
        margin-right: 5px;
    }
    .gateway-panel { padding: 0; overflow: hidden; }
    .gateway-panel-head {
        padding: 24px;
        border-bottom: 1px solid var(--border);
        background: radial-gradient(circle at 90% 0%, rgba(139,92,246,.10), transparent 34%), #fff;
    }
    .gateway-panel-body { padding: 24px; }
    .gateway-badge {
        padding: 6px 10px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 800;
        white-space: nowrap;
    }
    .credential-box {
        background: #f8fafc;
        border: 1px solid var(--border);
        border-radius: 18px;
        padding: 18px;
    }
    .gateway-help {
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        color: #1d4ed8;
        padding: 14px 16px;
        border-radius: 16px;
        font-size: 13px;
    }
    .gateway-actions {
        background: #f8fafc;
        border-top: 1px solid var(--border);
        padding: 18px 24px;
        display: flex;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        align-items: center;
    }
    .webhook-url-row {
        display: flex;
        align-items: center;
        gap: 10px;
        background: #0f172a;
        border-radius: 14px;
        padding: 10px 12px;
        margin-bottom: 10px;
    }
    .webhook-url-row code {
        color: #e2e8f0;
        font-size: 12px;
        word-break: break-all;
        flex: 1;
    }
    .copy-url-btn {
        border: 0;
        background: rgba(255,255,255,.12);
        color: white;
        border-radius: 10px;
        padding: 7px 10px;
        font-size: 12px;
        font-weight: 700;
        white-space: nowrap;
    }
    .copy-url-btn:hover { background: rgba(255,255,255,.20); }
    @media (max-width: 992px) {
        .gateway-shell { grid-template-columns: 1fr; }
        .gateway-sidebar { position: relative; top: 0; }
    }
</style>
@endpush

@section('content')
@php
    $gatewayMeta = [
        'cashfree' => ['icon' => 'bi-cash-coin', 'desc' => 'Payment links, UPI, cards and netbanking via Cashfree.'],
        'razorpay' => ['icon' => 'bi-lightning-charge', 'desc' => 'Razorpay orders/payment links integration.'],
        'payu' => ['icon' => 'bi-bank', 'desc' => 'PayU checkout and payment verification.'],
        'phonepe' => ['icon' => 'bi-phone', 'desc' => 'PhonePe payment request and callback flow.'],
        'instamojo' => ['icon' => 'bi-bag-check', 'desc' => 'Instamojo payment request and webhook flow.'],
    ];
    $gatewayUrls = [
        'cashfree' => [
            'Webhook URL' => url('/api/v1/subscription/webhook'),
        ],
        'razorpay' => [
            'Webhook URL' => url('/api/v1/subscription/webhook/razorpay'),
            'Callback URL' => url('/api/v1/subscription/webhook/razorpay'),
        ],
        'payu' => [
            'Webhook / Success URL' => url('/api/v1/subscription/webhook/payu'),
            'Failure URL' => url('/api/v1/subscription/webhook/payu'),
        ],
        'phonepe' => [
            'Callback URL' => url('/api/v1/subscription/webhook/phonepe'),
        ],
        'instamojo' => [
            'Webhook URL' => url('/api/v1/subscription/webhook/instamojo'),
            'Redirect URL' => url('/api/v1/subscription/webhook/instamojo'),
        ],
    ];
    $activeKey = optional($gateways->firstWhere('is_default', true))->gateway_key ?: optional($gateways->first())->gateway_key;
@endphp

<div class="gateway-hero mb-4">
    <div class="position-relative" style="z-index:1">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <div style="width:64px;height:64px;border-radius:22px;background:rgba(255,255,255,.14);display:flex;align-items:center;justify-content:center;">
                    <i class="bi bi-credit-card-2-front" style="font-size:32px;"></i>
                </div>
                <div>
                    <h3 class="mb-1" style="font-family:'Space Grotesk';font-weight:800;">Payment Gateways</h3>
                    <div style="opacity:.72;font-size:13px;">Enable providers, store credentials securely and choose your default checkout gateway.</div>
                </div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <span class="badge bg-light text-dark px-3 py-2">{{ $gateways->where('enabled', true)->count() }} Enabled</span>
                <span class="badge bg-warning text-dark px-3 py-2">Default: {{ optional($gateways->firstWhere('is_default', true))->name ?? 'None' }}</span>
            </div>
        </div>
    </div>
</div>

<div class="gateway-help mb-4">
    <i class="bi bi-info-circle me-2"></i>
    Configure all payment gateways here. In the next phase, subscription checkout will use the gateway marked as <strong>Default</strong>.
</div>

<div class="gateway-shell">
    <div class="gateway-sidebar">
        <div class="nav flex-column nav-pills gap-1" id="gatewayTabs" role="tablist" aria-orientation="vertical">
            @foreach($gateways as $gateway)
                @php
                    $meta = $gatewayMeta[$gateway->gateway_key] ?? ['icon' => 'bi-credit-card', 'desc' => 'Payment gateway configuration.'];
                    $isActive = $gateway->gateway_key === $activeKey;
                @endphp
                <button
                    class="gateway-tab {{ $isActive ? 'active' : '' }}"
                    id="tab-{{ $gateway->gateway_key }}"
                    data-bs-toggle="pill"
                    data-bs-target="#pane-{{ $gateway->gateway_key }}"
                    type="button"
                    role="tab"
                    aria-controls="pane-{{ $gateway->gateway_key }}"
                    aria-selected="{{ $isActive ? 'true' : 'false' }}">
                    <div class="gateway-icon"><i class="bi {{ $meta['icon'] }}"></i></div>
                    <div class="flex-grow-1">
                        <div class="gateway-title">{{ $gateway->name }}</div>
                        <div class="gateway-sub">
                            <span class="gateway-status-dot" style="background:{{ $gateway->enabled ? '#16c784' : '#cbd5e1' }}"></span>
                            {{ $gateway->enabled ? 'Enabled' : 'Disabled' }}
                            @if($gateway->is_default) • Default @endif
                        </div>
                    </div>
                </button>
            @endforeach
        </div>
    </div>

    <div class="tab-content" id="gatewayTabsContent">
        @foreach($gateways as $gateway)
            @php
                $masked = $gateway->maskedCredentials();
                $gatewayFields = $fields[$gateway->gateway_key] ?? [];
                $meta = $gatewayMeta[$gateway->gateway_key] ?? ['icon' => 'bi-credit-card', 'desc' => 'Payment gateway configuration.'];
                $isActive = $gateway->gateway_key === $activeKey;
            @endphp
            <div class="tab-pane fade {{ $isActive ? 'show active' : '' }}" id="pane-{{ $gateway->gateway_key }}" role="tabpanel" aria-labelledby="tab-{{ $gateway->gateway_key }}" tabindex="0">
                <div class="gateway-panel">
                    <div class="gateway-panel-head">
                        <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
                            <div class="d-flex align-items-center gap-3">
                                <div class="gateway-icon"><i class="bi {{ $meta['icon'] }}"></i></div>
                                <div>
                                    <h4 class="mb-1" style="font-family:'Space Grotesk';font-weight:800;">{{ $gateway->name }}</h4>
                                    <div class="text-muted" style="font-size:13px;">{{ $meta['desc'] }}</div>
                                </div>
                            </div>
                            <div class="d-flex gap-2 flex-wrap justify-content-end">
                                @if($gateway->enabled)
                                    <span class="gateway-badge" style="background:#dcfce7;color:#166534;">Enabled</span>
                                @else
                                    <span class="gateway-badge" style="background:#f1f5f9;color:#64748b;">Disabled</span>
                                @endif
                                @if($gateway->is_default)
                                    <span class="gateway-badge" style="background:#ede9fe;color:#6d28d9;">Default</span>
                                @endif
                                @if($gateway->hasCredentials())
                                    <span class="gateway-badge" style="background:#e0f2fe;color:#0369a1;">Credentials saved</span>
                                @endif
                                <span class="gateway-badge" style="background:#fff7ed;color:#9a3412;">{{ strtoupper($gateway->mode) }}</span>
                            </div>
                        </div>
                    </div>

                    <form action="{{ route('admin.payment-gateways.update', $gateway->gateway_key) }}" method="POST">
                        @csrf
                        <div class="gateway-panel-body">
                            <div class="row g-4 mb-4">
                                <div class="col-md-4">
                                    <label class="form-label">Mode</label>
                                    <select name="mode" class="form-select">
                                        <option value="sandbox" {{ $gateway->mode === 'sandbox' ? 'selected' : '' }}>Sandbox</option>
                                        <option value="production" {{ $gateway->mode === 'production' ? 'selected' : '' }}>Production</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label d-block">Gateway Status</label>
                                    <div class="credential-box py-3">
                                        <div class="form-check form-switch mb-0">
                                            <input type="hidden" name="enabled" value="0">
                                            <input class="form-check-input" type="checkbox" name="enabled" value="1" id="enabled_{{ $gateway->gateway_key }}" {{ $gateway->enabled ? 'checked' : '' }}>
                                            <label class="form-check-label fw-bold" for="enabled_{{ $gateway->gateway_key }}">Enabled</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label d-block">Default Provider</label>
                                    <div class="credential-box py-3">
                                        <div class="form-check form-switch mb-0">
                                            <input type="hidden" name="set_default" value="0">
                                            <input class="form-check-input" type="checkbox" name="set_default" value="1" id="default_{{ $gateway->gateway_key }}" {{ $gateway->is_default ? 'checked' : '' }}>
                                            <label class="form-check-label fw-bold" for="default_{{ $gateway->gateway_key }}">Set as default</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="credential-box mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                                    <div>
                                        <h6 class="mb-1"><i class="bi bi-link-45deg me-2 text-primary"></i> Webhook / Callback URLs</h6>
                                        <div class="text-muted" style="font-size:12px;">Copy these URLs into the payment gateway dashboard when required.</div>
                                    </div>
                                </div>

                                @foreach(($gatewayUrls[$gateway->gateway_key] ?? []) as $urlLabel => $urlValue)
                                    <label class="form-label small mb-1">{{ $urlLabel }}</label>
                                    <div class="webhook-url-row">
                                        <code>{{ $urlValue }}</code>
                                        <button type="button" class="copy-url-btn" onclick="copyGatewayUrl('{{ $urlValue }}', this)">
                                            <i class="bi bi-clipboard me-1"></i> Copy
                                        </button>
                                    </div>
                                @endforeach
                            </div>

                            <div class="credential-box">
                                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                                    <div>
                                        <h6 class="mb-1"><i class="bi bi-shield-lock me-2 text-primary"></i> Credentials</h6>
                                        <div class="text-muted" style="font-size:12px;">Saved credentials are shown masked inside the fields. Replace a field only when you want to change it.</div>
                                    </div>
                                </div>

                                <div class="row g-3">
                                    @foreach($gatewayFields as $field => $fieldMeta)
                                        <div class="col-md-6">
                                            <label class="form-label">{{ $fieldMeta['label'] }}</label>
                                            <input type="{{ $fieldMeta['type'] ?? 'text' }}" name="credentials[{{ $field }}]" class="form-control" value="{{ $masked[$field] ?? '' }}" placeholder="{{ $fieldMeta['placeholder'] ?? 'Enter credential' }}">
                                            <div class="form-text">{{ !empty($masked[$field]) ? 'Saved value is shown masked. Replace it only if you want to change it.' : 'No value saved yet.' }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <div class="gateway-actions">
                            <div class="text-muted" style="font-size:12px;">
                                <i class="bi bi-lock me-1"></i> Credentials are encrypted before saving.
                            </div>
                            <div class="d-flex gap-2 flex-wrap">
                                @if(!$gateway->is_default)
                                    <button type="submit" name="set_default" value="1" class="btn btn-outline-primary">
                                        <i class="bi bi-star me-1"></i> Save & Make Default
                                    </button>
                                @endif
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save me-2"></i> Save {{ $gateway->name }}
                                </button>
                            </div>
                        </div>
                    </form>
                    <div class="px-4 pb-4">
                        <form action="{{ route('admin.payment-gateways.test', $gateway->gateway_key) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-plug me-1"></i> Test {{ $gateway->name }} Connection
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection

@push('scripts')
<script>
function copyGatewayUrl(url, btn) {
    navigator.clipboard.writeText(url).then(function () {
        const old = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check2 me-1"></i> Copied';
        setTimeout(function () { btn.innerHTML = old; }, 1500);
    }).catch(function () {
        const input = document.createElement('input');
        input.value = url;
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        input.remove();
        const old = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check2 me-1"></i> Copied';
        setTimeout(function () { btn.innerHTML = old; }, 1500);
    });
}
</script>
@endpush
