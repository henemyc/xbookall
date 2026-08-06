<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Gym Owner Login - GymXBook</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand: #ff6b2c;
            --brand2: #ff8a3d;
            --dark: #0f172a;
            --muted: #64748b;
            --border: rgba(148, 163, 184, .22);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Poppins', system-ui, -apple-system, sans-serif;
            background:
                radial-gradient(circle at 18% 12%, rgba(255, 107, 44, .28), transparent 28%),
                radial-gradient(circle at 82% 18%, rgba(139, 92, 246, .24), transparent 30%),
                linear-gradient(135deg, #070b16 0%, #111827 45%, #1e1b4b 100%);
            color: #111827;
            overflow-x: hidden;
        }
        .page-shell {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 28px;
            position: relative;
        }
        .glow {
            position: fixed;
            border-radius: 999px;
            filter: blur(14px);
            opacity: .7;
            pointer-events: none;
        }
        .glow.one { width: 260px; height: 260px; left: -80px; bottom: 8%; background: rgba(255, 107, 44, .20); }
        .glow.two { width: 320px; height: 320px; right: -100px; top: 6%; background: rgba(124, 58, 237, .22); }
        .login-wrap {
            width: 100%;
            max-width: 1040px;
            display: grid;
            grid-template-columns: 1.05fr .95fr;
            gap: 22px;
            align-items: stretch;
        }
        .hero-panel {
            color: #fff;
            padding: 34px;
            border-radius: 34px;
            background: linear-gradient(145deg, rgba(255,255,255,.12), rgba(255,255,255,.04));
            border: 1px solid rgba(255,255,255,.14);
            box-shadow: 0 30px 90px rgba(0,0,0,.30);
            backdrop-filter: blur(18px);
            position: relative;
            overflow: hidden;
            min-height: 600px;
        }
        .hero-panel::after {
            content: '';
            position: absolute;
            right: -110px;
            bottom: -120px;
            width: 360px;
            height: 360px;
            background: radial-gradient(circle, rgba(255, 107, 44, .24), transparent 63%);
        }
        .brand-mark {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            border-radius: 18px;
            background: rgba(255,255,255,.10);
            border: 1px solid rgba(255,255,255,.13);
        }
        .brand-icon {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--brand2), var(--brand));
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 22px;
            box-shadow: 0 14px 30px rgba(255, 107, 44, .28);
        }
        .brand-name {
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 800;
            font-size: 24px;
            letter-spacing: -.5px;
        }
        .hero-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: clamp(34px, 5vw, 56px);
            line-height: .98;
            font-weight: 800;
            letter-spacing: -1.7px;
            margin: 42px 0 16px;
        }
        .hero-subtitle {
            color: rgba(255,255,255,.70);
            max-width: 540px;
            font-size: 15px;
            line-height: 1.75;
        }
        .feature-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 34px;
            position: relative;
            z-index: 1;
        }
        .feature-card {
            padding: 16px;
            border-radius: 20px;
            background: rgba(255,255,255,.09);
            border: 1px solid rgba(255,255,255,.12);
        }
        .feature-card i { color: #ffb178; font-size: 22px; }
        .feature-card strong { display: block; margin-top: 8px; font-size: 13px; }
        .feature-card span { color: rgba(255,255,255,.58); font-size: 11.5px; }
        .login-card {
            background: rgba(255,255,255,.96);
            border-radius: 34px;
            padding: 28px;
            box-shadow: 0 30px 90px rgba(0,0,0,.35);
            border: 1px solid rgba(255,255,255,.65);
            backdrop-filter: blur(18px);
            min-height: 600px;
        }
        .login-tabs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            background: #f1f5f9;
            border-radius: 18px;
            padding: 7px;
            margin-bottom: 24px;
        }
        .login-tab {
            border: 0;
            border-radius: 14px;
            padding: 12px 10px;
            font-weight: 800;
            font-size: 13px;
            background: transparent;
            color: var(--muted);
            transition: .2s ease;
        }
        .login-tab.active {
            background: linear-gradient(135deg, var(--brand2), var(--brand));
            color: #fff;
            box-shadow: 0 12px 28px rgba(255, 107, 44, .26);
        }
        .login-pane { display: none; }
        .login-pane.active { display: block; animation: fadeUp .28s ease; }
        @keyframes fadeUp { from { opacity:0; transform: translateY(8px); } to { opacity:1; transform: translateY(0); } }
        .pane-title {
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 800;
            font-size: 26px;
            letter-spacing: -.7px;
            margin-bottom: 5px;
        }
        .pane-subtitle { color: var(--muted); font-size: 13px; margin-bottom: 22px; }
        .qr-frame {
            position: relative;
            width: 264px;
            min-height: 264px;
            margin: 0 auto;
            padding: 18px;
            border-radius: 30px;
            background: linear-gradient(180deg, #fff 0%, #f8fafc 100%);
            border: 1px solid #e5e7eb;
            box-shadow: 0 18px 45px rgba(15, 23, 42, .10);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .qr-frame::before, .qr-frame::after {
            content: '';
            position: absolute;
            width: 52px;
            height: 52px;
            border-color: var(--brand);
            border-style: solid;
            pointer-events: none;
        }
        .qr-frame::before { left: 10px; top: 10px; border-width: 4px 0 0 4px; border-radius: 18px 0 0 0; }
        .qr-frame::after { right: 10px; bottom: 10px; border-width: 0 4px 4px 0; border-radius: 0 0 18px 0; }
        .qr-status {
            margin-top: 16px;
            padding: 13px 14px;
            border-radius: 17px;
            background: #fff7ed;
            color: #9a3412;
            font-size: 13px;
            font-weight: 700;
            text-align: center;
            border: 1px solid #fed7aa;
        }
        .steps {
            margin-top: 22px;
            display: grid;
            gap: 10px;
        }
        .step {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border-radius: 16px;
            background: #f8fafc;
            border: 1px solid #e5e7eb;
        }
        .step-num {
            width: 30px;
            height: 30px;
            border-radius: 11px;
            background: linear-gradient(135deg, var(--brand2), var(--brand));
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            font-size: 12px;
            flex-shrink: 0;
        }
        .step-text { font-size: 13px; font-weight: 700; color: #1f2937; }
        .form-control {
            border-radius: 14px;
            padding: 13px 15px;
            border: 1.5px solid #e5e7eb;
            font-size: 14px;
        }
        .form-control:focus {
            border-color: var(--brand);
            box-shadow: 0 0 0 4px rgba(255, 107, 44, .12);
        }
        .input-group-text {
            border-radius: 14px 0 0 14px;
            background: #f8fafc;
            border-color: #e5e7eb;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--brand2), var(--brand), #f43f1c);
            border: none;
            padding: 14px;
            font-weight: 800;
            border-radius: 16px;
            transition: .2s ease;
            box-shadow: 0 14px 32px rgba(255, 107, 44, .30);
        }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 18px 38px rgba(255, 107, 44, .36); }
        .toast-container { position: fixed; top: 20px; right: 20px; z-index: 999999; display: flex; flex-direction: column; gap: 10px; }
        @media(max-width: 940px) {
            .login-wrap { grid-template-columns: 1fr; max-width: 520px; }
            .hero-panel { min-height: auto; }
            .login-card { min-height: auto; }
        }
        @media(max-width: 520px) {
            .page-shell { padding: 14px; }
            .hero-panel, .login-card { border-radius: 26px; padding: 22px; }
            .feature-grid { grid-template-columns: 1fr; }
            .qr-frame { width: 238px; min-height: 238px; }
        }
    </style>
</head>
<body>
    <div class="glow one"></div>
    <div class="glow two"></div>
    <div id="toastContainer" class="toast-container"></div>

    <main class="page-shell">
        <div class="login-wrap">
            <section class="hero-panel">
                <div class="brand-mark">
                    <div class="brand-icon"><i class="bi bi-fire"></i></div>
                    <div>
                        <div class="brand-name"><span style="color:#ffb178;">Gym</span>XBook</div>
                        <div style="font-size:11px;color:rgba(255,255,255,.56);font-weight:700;letter-spacing:1px;">GYM OWNER WEB PANEL</div>
                    </div>
                </div>
                <h1 class="hero-title">Login to your gym panel securely.</h1>
                <p class="hero-subtitle">Scan the QR using your logged-in GymXBook app, just like WhatsApp Web. Fast, secure and password-free for your PC.</p>
                <div class="feature-grid">
                    <div class="feature-card"><i class="bi bi-qr-code-scan"></i><strong>QR Web Login</strong><span>One scan from app to PC.</span></div>
                    <div class="feature-card"><i class="bi bi-shield-check"></i><strong>Secure Session</strong><span>Short-lived QR tokens.</span></div>
                    <div class="feature-card"><i class="bi bi-speedometer2"></i><strong>Instant Access</strong><span>Auto login after approval.</span></div>
                    <div class="feature-card"><i class="bi bi-phone"></i><strong>App Verified</strong><span>Only gym owner can approve.</span></div>
                </div>
            </section>

            <section class="login-card">
                <div class="login-tabs">
                    <button type="button" class="login-tab active" data-login-tab="qr"><i class="bi bi-qr-code-scan me-1"></i> App QR</button>
                    <button type="button" class="login-tab" data-login-tab="password"><i class="bi bi-key me-1"></i> Password</button>
                </div>

                <div class="login-pane active" id="qrPane">
                    <div class="pane-title">Scan to login</div>
                    <div class="pane-subtitle">Open GymXBook app and approve this web login.</div>

                    <div id="qrBox" class="qr-frame">
                        <div class="text-muted small">Loading QR...</div>
                    </div>
                    <div id="qrStatus" class="qr-status">Generating secure QR...</div>

                    <div class="steps">
                        <div class="step"><div class="step-num">1</div><div class="step-text">Open GymXBook app</div></div>
                        <div class="step"><div class="step-num">2</div><div class="step-text">Go to Settings → Web Login</div></div>
                        <div class="step"><div class="step-num">3</div><div class="step-text">Scan this QR code</div></div>
                    </div>

                </div>

                <div class="login-pane" id="passwordPane">
                    <div class="pane-title">Phone login</div>
                    <div class="pane-subtitle">Use your registered phone number and password.</div>
                    <form id="loginForm" method="POST" action="{{ route('panel.login') }}" novalidate>
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Phone Number</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-phone"></i></span>
                                <input type="tel" name="email" id="email" class="form-control" placeholder="10-digit mobile number" value="{{ old('email') }}" maxlength="10" inputmode="numeric" pattern="[0-9]{10}" required>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" name="password" id="password" class="form-control" placeholder="Enter your password" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100" id="loginBtn">
                            <i class="bi bi-box-arrow-in-right me-2"></i>
                            <span id="btnText">Sign In</span>
                        </button>
                    </form>
                </div>

                <div class="text-center mt-4">
                    <small class="text-muted"><a href="/" class="text-decoration-none text-secondary">← Back to website</a></small>
                </div>
            </section>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <script>
        function showToast(message, type = 'error') {
            const container = document.getElementById('toastContainer');
            const isError = type === 'error';
            const bg = isError ? 'linear-gradient(135deg, #ff4d4f, #d4380d)' : 'linear-gradient(135deg, #16c784, #0d9c5f)';
            const toast = document.createElement('div');
            toast.style.cssText = `min-width:300px;max-width:380px;background:${bg};color:white;border-radius:14px;padding:14px 18px;box-shadow:0 18px 42px rgba(0,0,0,.25);display:flex;align-items:center;gap:10px;font-size:14px;font-weight:600;`;
            toast.innerHTML = `<span style="font-size:20px;">${isError ? '✕' : '✓'}</span><span style="flex:1;">${message}</span><span style="cursor:pointer;opacity:.8;font-size:18px;" onclick="this.closest('div').remove()">×</span>`;
            container.appendChild(toast);
            setTimeout(() => toast.remove(), 5200);
        }

        const loginForm = document.getElementById('loginForm');
        const loginBtn = document.getElementById('loginBtn');

        function setLoading(loading) {
            if (loading) {
                loginBtn.disabled = true;
                loginBtn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span><span>Signing in...</span>`;
            } else {
                loginBtn.disabled = false;
                loginBtn.innerHTML = `<i class="bi bi-box-arrow-in-right me-2"></i><span id="btnText">Sign In</span>`;
            }
        }

        loginForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            setLoading(true);
            try {
                const res = await fetch('{{ route("panel.login") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: new FormData(this)
                });
                const data = await res.json();
                if (res.ok && data.success) window.location.href = data.redirect || '/panel';
                else { setLoading(false); showToast(data.error || 'Invalid phone or password.', 'error'); }
            } catch (err) {
                setLoading(false);
                showToast('Network error. Please check your connection.', 'error');
            }
        });

        let qrToken = null;
        let qrPollTimer = null;
        let qrExpiresIn = 0;
        let qrCountdownTimer = null;

        document.querySelectorAll('[data-login-tab]').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('[data-login-tab]').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                document.querySelectorAll('.login-pane').forEach(p => p.classList.remove('active'));
                const target = btn.getAttribute('data-login-tab');
                document.getElementById(target === 'qr' ? 'qrPane' : 'passwordPane').classList.add('active');
                if (target === 'qr') loadQrLogin();
                else stopQrTimers();
            });
        });


        async function loadQrLogin() {
            stopQrTimers();
            const qrBox = document.getElementById('qrBox');
            const status = document.getElementById('qrStatus');
            qrBox.innerHTML = '<div class="spinner-border text-warning"></div>';
            status.textContent = 'Generating secure QR...';

            try {
                const res = await fetch('{{ route('panel.login.qr.token') }}', {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();
                if (!res.ok || !data.success) throw new Error(data.message || 'Could not generate QR');

                qrToken = data.token;
                qrExpiresIn = data.expires_in || 120;
                qrBox.innerHTML = '';
                new QRCode(qrBox, {
                    text: data.qr_payload || qrToken,
                    width: 216,
                    height: 216,
                    colorDark: '#111827',
                    colorLight: '#ffffff',
                    correctLevel: QRCode.CorrectLevel.H
                });
                status.textContent = 'Waiting for app scan... Expires in ' + qrExpiresIn + 's';
                startQrPolling();
                startQrCountdown();
            } catch (e) {
                qrBox.innerHTML = '<i class="bi bi-exclamation-triangle text-danger" style="font-size:42px"></i>';
                status.textContent = e.message || 'Could not generate QR';
            }
        }

        function startQrPolling() {
            qrPollTimer = setInterval(async () => {
                if (!qrToken) return;
                try {
                    const res = await fetch('{{ route('panel.login.qr.status') }}?token=' + encodeURIComponent(qrToken), {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const data = await res.json();
                    const status = document.getElementById('qrStatus');

                    if (data.status === 'pending') {
                        status.textContent = 'Waiting for app scan... Expires in ' + qrExpiresIn + 's';
                    } else if (data.status === 'approved') {
                        status.textContent = 'Approved. Logging you in...';
                    } else if (data.status === 'logged_in') {
                        stopQrTimers();
                        status.textContent = 'Login successful. Redirecting...';
                        window.location.href = data.redirect || '/panel';
                    } else if (data.status === 'expired' || data.status === 'invalid') {
                        stopQrTimers();
                        status.textContent = data.message || 'QR expired. Refresh and scan again.';
                    }
                } catch (_) {}
            }, 2000);
        }

        function startQrCountdown() {
            qrCountdownTimer = setInterval(() => {
                qrExpiresIn--;
                const status = document.getElementById('qrStatus');

                if (qrExpiresIn > 0) {
                    status.textContent = 'Waiting for app scan... Expires in ' + qrExpiresIn + 's';
                    return;
                }

                stopQrTimers();
                status.textContent = 'QR expired. Generating a new QR...';
                setTimeout(loadQrLogin, 600);
            }, 1000);
        }

        function stopQrTimers() {
            if (qrPollTimer) clearInterval(qrPollTimer);
            if (qrCountdownTimer) clearInterval(qrCountdownTimer);
            qrPollTimer = null;
            qrCountdownTimer = null;
        }

        document.addEventListener('DOMContentLoaded', function() {
            loadQrLogin();
        });
    </script>
</body>
</html>
