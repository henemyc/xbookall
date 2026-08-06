@extends('admin.layouts.app')

@section('title', 'Settings')

@push('styles')
<style>
    .settings-hero {
        background: linear-gradient(135deg, #111827 0%, #1e1b4b 55%, #4c1d95 100%);
        color: #fff;
        border-radius: 22px;
        padding: 26px;
        position: relative;
        overflow: hidden;
        box-shadow: 0 18px 45px rgba(76, 29, 149, 0.22);
    }
    .settings-hero::after {
        content: '';
        position: absolute;
        right: -70px;
        top: -70px;
        width: 220px;
        height: 220px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(255,255,255,0.20), transparent 62%);
    }
    .settings-tabs {
        background: #fff;
        border: 1px solid var(--border);
        border-radius: 18px;
        padding: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }
    .settings-tabs .nav-link {
        border: 0;
        color: var(--text-secondary);
        border-radius: 13px;
        font-weight: 700;
        padding: 12px 16px;
        font-size: 13px;
    }
    .settings-tabs .nav-link.active {
        color: #fff;
        background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        box-shadow: 0 8px 18px rgba(139, 92, 246, 0.28);
    }
    .settings-panel {
        background: #fff;
        border: 1px solid var(--border);
        border-radius: 20px;
        padding: 24px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }
    .settings-card-title {
        font-family: 'Space Grotesk', sans-serif;
        font-weight: 800;
        font-size: 18px;
        margin-bottom: 4px;
    }
    .settings-muted { color: var(--text-secondary); font-size: 13px; }
    .credential-box {
        background: #f8fafc;
        border: 1px solid var(--border);
        border-radius: 18px;
        padding: 16px;
    }
    .mini-info {
        padding: 14px;
        border-radius: 15px;
        background: #f8fafc;
        border: 1px solid var(--border);
    }
    .code-pill {
        background: #111827;
        color: #fff;
        border-radius: 8px;
        padding: 2px 7px;
        font-size: 12px;
    }
    .maintenance-preview {
        border-radius: 22px;
        padding: 22px;
        color: #fff;
        background: linear-gradient(135deg, #111827, #7c2d12);
        position: relative;
        overflow: hidden;
    }
    .maintenance-preview::after {
        content: '';
        position: absolute;
        right: -50px;
        top: -50px;
        width: 160px;
        height: 160px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(255,255,255,.18), transparent 65%);
    }
    .timer-box {
        background: rgba(255,255,255,.12);
        border: 1px solid rgba(255,255,255,.14);
        border-radius: 15px;
        padding: 12px;
        text-align: center;
        min-width: 80px;
    }
    .timer-box strong { font-family: 'Space Grotesk'; font-size: 24px; display: block; }
</style>
@endpush

@section('content')
<div class="settings-hero mb-4">
    <div class="position-relative" style="z-index:1">
        <div class="d-flex align-items-center gap-3">
            <div style="width:58px;height:58px;border-radius:18px;background:rgba(255,255,255,0.14);display:flex;align-items:center;justify-content:center;">
                <i class="bi bi-sliders" style="font-size:28px;"></i>
            </div>
            <div>
                <h3 class="mb-1" style="font-family:'Space Grotesk';font-weight:800;">Platform Settings</h3>
                <div style="opacity:.72;font-size:13px;">Manage app updates, SMTP, WhatsApp diagnostics and system information in one organized place.</div>
            </div>
        </div>
    </div>
</div>

<ul class="nav nav-pills settings-tabs mb-4" id="settingsTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="platform-tab" data-bs-toggle="pill" data-bs-target="#platform-pane" type="button" role="tab">
            <i class="bi bi-phone me-2"></i> App & Updates
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="security-tab" data-bs-toggle="pill" data-bs-target="#security-pane" type="button" role="tab">
            <i class="bi bi-shield-lock me-2"></i> Security
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="maintenance-tab" data-bs-toggle="pill" data-bs-target="#maintenance-pane" type="button" role="tab">
            <i class="bi bi-tools me-2"></i> Maintenance
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="smtp-tab" data-bs-toggle="pill" data-bs-target="#smtp-pane" type="button" role="tab">
            <i class="bi bi-envelope me-2"></i> SMTP
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="whatsapp-tab" data-bs-toggle="pill" data-bs-target="#whatsapp-pane" type="button" role="tab">
            <i class="bi bi-whatsapp me-2"></i> WhatsApp
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="system-tab" data-bs-toggle="pill" data-bs-target="#system-pane" type="button" role="tab">
            <i class="bi bi-info-circle me-2"></i> System
        </button>
    </li>
</ul>

<div class="tab-content" id="settingsTabsContent">
    <div class="tab-pane fade show active" id="platform-pane" role="tabpanel" tabindex="0">
        <div class="settings-panel">
            <div class="d-flex align-items-start justify-content-between mb-4">
                <div>
                    <div class="settings-card-title"><i class="bi bi-phone me-2 text-primary"></i> App Update Control</div>
                    <div class="settings-muted">Control Flutter app version, update link and force-update behavior shown inside the app.</div>
                </div>
                <span class="badge bg-primary">Current latest: {{ $platform['app_version'] }}</span>
            </div>

            <form action="{{ route('admin.settings.platform') }}" method="POST">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">App Name</label>
                        <input type="text" name="app_name" class="form-control" value="{{ $platform['app_name'] }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Latest Flutter App Version</label>
                        <input type="text" name="app_version" class="form-control" value="{{ $platform['app_version'] }}" placeholder="1.1.0">
                        <div class="form-text">If installed version is lower, app shows Update Available.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Flutter App Download / Update Link</label>
                        <input type="url" name="app_download_url" class="form-control" value="{{ $platform['app_download_url'] ?? '' }}" placeholder="https://app.gymxbook.com/downloads/gymxbook.apk">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Update Message</label>
                        <textarea name="update_message" class="form-control" rows="3">{{ $platform['update_message'] ?? 'A new version of GymXBook is available.' }}</textarea>
                    </div>
                    <div class="col-md-4">
                        <div class="mini-info h-100">
                            <div class="form-check form-switch">
                                <input type="hidden" name="force_update" value="0">
                                <input class="form-check-input" type="checkbox" name="force_update" value="1" id="forceUpdateSwitch" {{ ($platform['force_update'] ?? '0') == '1' ? 'checked' : '' }}>
                                <label class="form-check-label fw-bold" for="forceUpdateSwitch">Force update</label>
                            </div>
                            <div class="settings-muted mt-2">When enabled, users cannot dismiss the update sheet.</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Support Email</label>
                        <input type="email" name="support_email" class="form-control" value="{{ $platform['support_email'] }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Support Phone</label>
                        <input type="text" name="support_phone" class="form-control" value="{{ $platform['support_phone'] }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Website</label>
                        <input type="url" name="website" class="form-control" value="{{ $platform['website'] }}">
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-2"></i> Save App Settings
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="tab-pane fade" id="security-pane" role="tabpanel" tabindex="0">
        <div class="settings-panel">
            <div class="d-flex align-items-start justify-content-between mb-4 gap-3 flex-wrap">
                <div>
                    <div class="settings-card-title"><i class="bi bi-shield-lock me-2 text-danger"></i> Super Admin Security</div>
                    <div class="settings-muted">Enable WhatsApp OTP verification after Super Admin password login.</div>
                </div>
                <span class="badge {{ ($security['super_admin_2fa_enabled'] ?? '0') == '1' ? 'bg-success' : 'bg-secondary' }} px-3 py-2">
                    {{ ($security['super_admin_2fa_enabled'] ?? '0') == '1' ? '2FA Enabled' : '2FA Disabled' }}
                </span>
            </div>

            <form action="{{ route('admin.settings.security') }}" method="POST">
                @csrf
                <div class="row g-3">
                    <div class="col-lg-5">
                        <div class="mini-info h-100">
                            <div class="form-check form-switch">
                                <input type="hidden" name="super_admin_2fa_enabled" value="0">
                                <input class="form-check-input" type="checkbox" name="super_admin_2fa_enabled" value="1" id="superAdmin2faSwitch" {{ ($security['super_admin_2fa_enabled'] ?? '0') == '1' ? 'checked' : '' }}>
                                <label class="form-check-label fw-bold" for="superAdmin2faSwitch">Require WhatsApp OTP after password</label>
                            </div>
                            <div class="settings-muted mt-2">When enabled, Super Admin must verify a 6-digit WhatsApp OTP before accessing the dashboard.</div>
                        </div>
                    </div>
                    <div class="col-lg-7">
                        <label class="form-label">Super Admin WhatsApp Phone</label>
                        <input type="text" name="super_admin_phone" class="form-control" value="{{ $security['super_admin_phone'] ?? '' }}" maxlength="10" inputmode="numeric" placeholder="10-digit mobile number">
                        <div class="form-text">Required before enabling 2FA. OTP uses your configured WhatsApp Cloud API template.</div>
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-2"></i> Save Security Settings</button>
                </div>
            </form>
        </div>
    </div>

    <div class="tab-pane fade" id="maintenance-pane" role="tabpanel" tabindex="0">
        <div class="settings-panel">
            <div class="d-flex align-items-start justify-content-between mb-4 gap-3 flex-wrap">
                <div>
                    <div class="settings-card-title"><i class="bi bi-tools me-2 text-warning"></i> Platform Maintenance Mode</div>
                    <div class="settings-muted">Control maintenance for the Gym Owner web panel and Flutter app from Super Admin.</div>
                </div>
                @php($mStatus = $maintenance['status'] ?? [])
                @if(!empty($mStatus['active']))
                    <span class="badge bg-danger px-3 py-2">Active Now</span>
                @elseif(!empty($mStatus['scheduled']))
                    <span class="badge bg-warning text-dark px-3 py-2">Scheduled</span>
                @else
                    <span class="badge bg-success px-3 py-2">Live</span>
                @endif
            </div>

            <div class="row g-4">
                <div class="col-lg-7">
                    <form action="{{ route('admin.settings.maintenance') }}" method="POST">
                        @csrf
                        <div class="mini-info mb-3">
                            <div class="form-check form-switch">
                                <input type="hidden" name="maintenance_enabled" value="0">
                                <input class="form-check-input" type="checkbox" name="maintenance_enabled" value="1" id="maintenanceEnabled" {{ ($maintenance['enabled'] ?? '0') == '1' ? 'checked' : '' }}>
                                <label class="form-check-label fw-bold" for="maintenanceEnabled">Enable maintenance mode</label>
                            </div>
                            <div class="settings-muted mt-2">Super Admin remains accessible. Gym web panel and Flutter app show a smooth countdown screen.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Maintenance Title</label>
                            <input type="text" name="maintenance_title" class="form-control" value="{{ $maintenance['title'] ?? '' }}" placeholder="GymXBook is under maintenance">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">User Message</label>
                            <textarea name="maintenance_message" rows="4" class="form-control" placeholder="We are upgrading GymXBook to serve you better.">{{ $maintenance['message'] ?? '' }}</textarea>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Start Time <small class="text-muted">IST</small></label>
                                <input type="datetime-local" name="maintenance_start_at" id="maintenanceStartAt" class="form-control" value="{{ $maintenance['start_at'] ?? '' }}">
                                <div class="form-text">Leave blank to start immediately.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">End Time <small class="text-muted">IST</small></label>
                                <input type="datetime-local" name="maintenance_end_at" id="maintenanceEndAt" class="form-control" value="{{ $maintenance['end_at'] ?? '' }}">
                                <div class="form-text">Users see countdown until this time.</div>
                            </div>
                        </div>
                        <div class="d-flex gap-2 flex-wrap mt-3">
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setMaintenanceWindow(30)">Next 30 min</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setMaintenanceWindow(60)">Next 1 hour</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setMaintenanceWindow(120)">Next 2 hours</button>
                        </div>
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-2"></i> Save Maintenance Settings</button>
                        </div>
                    </form>
                </div>
                <div class="col-lg-5">
                    <div class="maintenance-preview h-100">
                        <div class="position-relative" style="z-index:1">
                            <div class="small" style="opacity:.72">User Preview</div>
                            <h4 class="mt-2" style="font-family:'Space Grotesk';font-weight:800;">{{ $maintenance['title'] ?? 'GymXBook is under maintenance' }}</h4>
                            <p style="opacity:.78;font-size:13px;line-height:1.55;">{{ $maintenance['message'] ?? 'We are upgrading GymXBook to serve you better.' }}</p>
                            <div class="d-flex gap-2 flex-wrap mt-3" id="adminMaintenanceTimer" data-end="{{ $mStatus['end_at'] ?? '' }}">
                                <div class="timer-box"><strong id="adminMh">00</strong><span>Hours</span></div>
                                <div class="timer-box"><strong id="adminMm">00</strong><span>Minutes</span></div>
                                <div class="timer-box"><strong id="adminMs">00</strong><span>Seconds</span></div>
                            </div>
                            <div class="mt-3 small" style="opacity:.72">
                                Expected back: {{ !empty($mStatus['end_at']) ? \Carbon\Carbon::parse($mStatus['end_at'])->timezone('Asia/Kolkata')->format('d M Y, h:i A') : 'Not set' }}
                            </div>
                            <div class="mt-3 p-3 rounded" style="background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.14);font-size:12px;">
                                When the timer finishes, users will see <strong>“We are live now”</strong> and can refresh automatically/manual.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="smtp-pane" role="tabpanel" tabindex="0">
        <div class="settings-panel">
            <div class="settings-card-title"><i class="bi bi-envelope me-2 text-info"></i> SMTP Settings</div>
            <div class="settings-muted mb-4">Configure email sending credentials used by GymXBook.</div>

            <form action="{{ route('admin.settings.smtp') }}" method="POST">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">SMTP Host</label>
                        <input type="text" name="SERVER_HOST" class="form-control" value="{{ $smtp['SERVER_HOST'] }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Port</label>
                        <input type="text" name="SERVER_PORT" class="form-control" value="{{ $smtp['SERVER_PORT'] }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Driver</label>
                        <select name="SERVER_DRIVER" class="form-select">
                            <option value="smtp" {{ $smtp['SERVER_DRIVER'] === 'smtp' ? 'selected' : '' }}>SMTP</option>
                            <option value="sendmail" {{ $smtp['SERVER_DRIVER'] === 'sendmail' ? 'selected' : '' }}>Sendmail</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Username</label>
                        <input type="text" name="SERVER_USERNAME" class="form-control" value="{{ $smtp['SERVER_USERNAME'] }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Password</label>
                        <input type="password" name="SERVER_PASSWORD" class="form-control" value="{{ $smtp['SERVER_PASSWORD'] }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">From Email</label>
                        <input type="email" name="FROM_EMAIL" class="form-control" value="{{ $smtp['FROM_EMAIL'] }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">From Name</label>
                        <input type="text" name="FROM_NAME" class="form-control" value="{{ $smtp['FROM_NAME'] }}">
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-2"></i> Save SMTP Settings
                    </button>
                </div>
            </form>

            <div class="credential-box mt-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h6 class="mb-1"><i class="bi bi-send-check me-2 text-success"></i> Test SMTP</h6>
                        <div class="settings-muted">Send a real test email using the saved SMTP settings.</div>
                    </div>
                    <form action="{{ route('admin.settings.smtp.test') }}" method="POST" class="d-flex gap-2 flex-wrap align-items-center">
                        @csrf
                        <input type="email" name="test_email" class="form-control" style="min-width:260px;" value="{{ auth()->user()->email ?? $smtp['FROM_EMAIL'] }}" placeholder="Enter test email" required>
                        <button type="submit" class="btn btn-outline-success">
                            <i class="bi bi-plug me-1"></i> Send Test Email
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="whatsapp-pane" role="tabpanel" tabindex="0">
        <div class="settings-panel">
            <div class="settings-card-title"><i class="bi bi-whatsapp me-2 text-success"></i> WhatsApp Diagnostics</div>
            <div class="settings-muted mb-4">Super Admin tools only. App settings test section has been removed.</div>

            <div class="row g-3 mb-4">
                <div class="col-md-4"><div class="mini-info"><div class="settings-muted">API URL</div><strong>{{ $whatsapp['api_url'] }}</strong></div></div>
                <div class="col-md-4"><div class="mini-info"><div class="settings-muted">Token</div><strong>{{ $whatsapp['api_token'] ? 'Configured' : 'Not set' }}</strong></div></div>
                <div class="col-md-4"><div class="mini-info"><div class="settings-muted">Phone Number ID</div><strong>{{ $whatsapp['phone_number_id'] ?: 'Not set' }}</strong></div></div>
            </div>

            <form action="{{ route('admin.settings.whatsapp.test') }}" method="POST" id="waTemplateForm">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Test Phone Number <small class="text-muted">with country code</small></label>
                        <input type="text" name="test_phone" class="form-control" value="919876543210" placeholder="919876543210" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Select Template</label>
                        <select name="template" class="form-select" id="waTemplateSelect" required onchange="toggleWaParams()">
                            <option value="gymxbook_member_welcome">gymxbook_member_welcome</option>
                            <option value="gymxbook_member_renew">gymxbook_member_renew</option>
                            <option value="gymxbook_member_expired">gymxbook_member_expired</option>
                            <option value="gymxbook_otp">gymxbook_otp</option>
                            <option value="custom">Custom / Free Text</option>
                        </select>
                    </div>
                </div>

                <div id="waParamsWelcome" class="wa-params mt-3">
                    <div class="row g-2">
                        <div class="col-md-4"><input type="text" name="member_name" class="form-control form-control-sm" value="John Doe" placeholder="Member Name"></div>
                        <div class="col-md-4"><input type="text" name="gym_name" class="form-control form-control-sm" value="Iron Paradise Gym" placeholder="Gym Name"></div>
                        <div class="col-md-4"><input type="text" name="expiry" class="form-control form-control-sm" value="{{ now()->addDays(30)->format('d-m-Y') }}" placeholder="Expiry"></div>
                    </div>
                </div>

                <div id="waParamsRenew" class="wa-params d-none mt-3">
                    <div class="row g-2">
                        <div class="col-md-3"><input type="text" name="member_name" class="form-control form-control-sm" value="Jane Smith" placeholder="Member"></div>
                        <div class="col-md-3"><input type="text" name="gym_name" class="form-control form-control-sm" value="Iron Paradise Gym" placeholder="Gym"></div>
                        <div class="col-md-3"><input type="text" name="expiry" class="form-control form-control-sm" value="{{ now()->addDays(90)->format('d-m-Y') }}" placeholder="Expiry"></div>
                        <div class="col-md-3"><input type="number" name="amount" class="form-control form-control-sm" value="2499" placeholder="Amount"></div>
                    </div>
                </div>

                <div id="waParamsExpired" class="wa-params d-none mt-3">
                    <div class="row g-2">
                        <div class="col-md-6"><input type="text" name="member_name" class="form-control form-control-sm" value="Alex Kumar" placeholder="Member"></div>
                        <div class="col-md-6"><input type="text" name="expiry" class="form-control form-control-sm" value="{{ now()->subDays(3)->format('d-m-Y') }}" placeholder="Expiry"></div>
                    </div>
                </div>

                <div id="waParamsOtp" class="wa-params d-none mt-3">
                    <label class="form-label small">OTP Code</label>
                    <input type="text" name="otp_code" class="form-control form-control-sm" value="654321" maxlength="6">
                    <div class="form-text">Sent as body + copy_code button coupon code.</div>
                </div>

                <div id="waParamsCustom" class="wa-params d-none mt-3">
                    <label class="form-label small">Custom Message</label>
                    <textarea name="test_message" class="form-control form-control-sm" rows="2">Hello from GymXBook SuperAdmin! This is a custom test.</textarea>
                </div>

                <div class="mt-4 d-flex gap-2 flex-wrap">
                    <button type="submit" class="btn btn-success"><i class="bi bi-whatsapp me-2"></i> Send Template</button>
                </div>
            </form>

            <div class="mt-3 d-flex gap-2 flex-wrap">
                <form action="{{ route('admin.settings.whatsapp.test-connection') }}" method="POST">@csrf<button type="submit" class="btn btn-outline-success"><i class="bi bi-plug me-1"></i> Test Connection</button></form>
                <form action="{{ route('admin.settings.whatsapp.diagnose') }}" method="POST">@csrf<button type="submit" class="btn btn-outline-primary"><i class="bi bi-bug me-1"></i> Full Diagnose</button></form>
            </div>

            <div class="mt-4 mini-info small">
                <strong class="text-danger">Token expired?</strong> Generate a new never-expire token in Meta Business Suite, update <span class="code-pill">WHATSAPP_API_TOKEN</span>, then run <span class="code-pill">php artisan config:clear</span>.
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="system-pane" role="tabpanel" tabindex="0">
        <div class="settings-panel mb-4">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <div class="settings-card-title"><i class="bi bi-database-gear me-2 text-warning"></i> Database Update</div>
                    <div class="settings-muted">Use this after copying new Laravel files. It runs pending migrations and repairs critical staff-role tables/columns if missing.</div>
                </div>
                <form action="{{ route('admin.settings.platform') }}" method="POST" onsubmit="return confirm('Run database update now? Please make sure you have a DB backup.');">
                    @csrf
                    <input type="hidden" name="run_system_update" value="1">
                    <button class="btn btn-warning"><i class="bi bi-cloud-arrow-up me-2"></i>Update Database Now</button>
                </form>
            </div>
            @if(session('system_update_output'))
                <pre class="mt-3 p-3 rounded" style="background:#0f172a;color:#e5e7eb;white-space:pre-wrap;font-size:12px;max-height:260px;overflow:auto;">{{ session('system_update_output') }}</pre>
            @endif
        </div>

        <div class="settings-panel">
            <div class="settings-card-title"><i class="bi bi-info-circle me-2 text-primary"></i> System Info</div>
            <div class="settings-muted mb-4">Current server/runtime information.</div>
            @foreach([
                'PHP Version' => phpversion(),
                'Laravel Version' => app()->version(),
                'Database' => config('database.default'),
                'Environment' => app()->environment(),
                'Debug Mode' => config('app.debug') ? 'ON' : 'OFF',
            ] as $label => $value)
                <div class="d-flex justify-content-between py-3 border-bottom">
                    <span class="text-muted">{{ $label }}</span>
                    <strong>{{ $value }}</strong>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function pad2(v) { return String(v).padStart(2, '0'); }
function toLocalDatetimeValue(date) {
    const y = date.getFullYear();
    const m = pad2(date.getMonth() + 1);
    const d = pad2(date.getDate());
    const h = pad2(date.getHours());
    const min = pad2(date.getMinutes());
    return `${y}-${m}-${d}T${h}:${min}`;
}
function setMaintenanceWindow(minutes) {
    const start = new Date();
    const end = new Date(Date.now() + minutes * 60000);
    document.getElementById('maintenanceStartAt').value = toLocalDatetimeValue(start);
    document.getElementById('maintenanceEndAt').value = toLocalDatetimeValue(end);
    document.getElementById('maintenanceEnabled').checked = true;
}
function adminMaintenanceCountdown() {
    const box = document.getElementById('adminMaintenanceTimer');
    if (!box) return;
    const end = box.dataset.end;
    if (!end) return;
    const remaining = Math.max(0, new Date(end).getTime() - Date.now());
    const total = Math.floor(remaining / 1000);
    document.getElementById('adminMh').textContent = pad2(Math.floor(total / 3600));
    document.getElementById('adminMm').textContent = pad2(Math.floor((total % 3600) / 60));
    document.getElementById('adminMs').textContent = pad2(total % 60);
}
setInterval(adminMaintenanceCountdown, 1000);
adminMaintenanceCountdown();

function toggleWaParams() {
    const value = document.getElementById('waTemplateSelect').value;
    document.querySelectorAll('.wa-params').forEach(el => el.classList.add('d-none'));
    if (value === 'gymxbook_member_welcome') document.getElementById('waParamsWelcome').classList.remove('d-none');
    else if (value === 'gymxbook_member_renew') document.getElementById('waParamsRenew').classList.remove('d-none');
    else if (value === 'gymxbook_member_expired') document.getElementById('waParamsExpired').classList.remove('d-none');
    else if (value === 'gymxbook_otp' || value === 'otp') document.getElementById('waParamsOtp').classList.remove('d-none');
    else document.getElementById('waParamsCustom').classList.remove('d-none');
}
document.addEventListener('DOMContentLoaded', toggleWaParams);
</script>
@endpush
