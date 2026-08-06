<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Super Admin Login - GymXBook</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand: #ff6b2c;
            --brand2: #ff8a3d;
            --purple: #8b5cf6;
            --purple2: #6d28d9;
            --dark: #0f172a;
            --muted: #64748b;
            --border: #e5e7eb;
            --danger: #ef4444;
            --success: #16c784;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Poppins', system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
            color: #111827;
            background:
                radial-gradient(circle at 15% 18%, rgba(255,107,44,.28), transparent 28%),
                radial-gradient(circle at 86% 12%, rgba(139,92,246,.26), transparent 30%),
                linear-gradient(135deg, #070b16 0%, #111827 48%, #1e1b4b 100%);
            overflow-x: hidden;
        }
        .page-shell {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 28px;
            position: relative;
        }
        .orb {
            position: fixed;
            border-radius: 999px;
            pointer-events: none;
            filter: blur(2px);
            opacity: .75;
        }
        .orb.one { width: 240px; height: 240px; left: -80px; bottom: 8%; background: radial-gradient(circle, rgba(255,107,44,.22), transparent 68%); }
        .orb.two { width: 320px; height: 320px; right: -120px; top: 5%; background: radial-gradient(circle, rgba(139,92,246,.24), transparent 68%); }
        .login-wrap {
            width: 100%;
            max-width: 1060px;
            display: grid;
            grid-template-columns: 1.06fr .94fr;
            gap: 22px;
            align-items: stretch;
        }
        .hero-panel {
            min-height: 620px;
            border-radius: 34px;
            padding: 34px;
            color: #fff;
            position: relative;
            overflow: hidden;
            background: linear-gradient(145deg, rgba(255,255,255,.13), rgba(255,255,255,.045));
            border: 1px solid rgba(255,255,255,.14);
            box-shadow: 0 34px 95px rgba(0,0,0,.36);
            backdrop-filter: blur(18px);
        }
        .hero-panel::after {
            content: '';
            position: absolute;
            right: -120px;
            bottom: -120px;
            width: 370px;
            height: 370px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255,107,44,.24), transparent 64%);
        }
        .brand-mark {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            border-radius: 19px;
            background: rgba(255,255,255,.10);
            border: 1px solid rgba(255,255,255,.13);
        }
        .brand-icon {
            width: 44px;
            height: 44px;
            border-radius: 15px;
            background: linear-gradient(135deg, var(--brand2), var(--brand));
            display: grid;
            place-items: center;
            font-size: 23px;
            box-shadow: 0 16px 32px rgba(255,107,44,.34);
        }
        .brand-name {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -.5px;
        }
        .hero-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: clamp(36px, 5vw, 58px);
            line-height: .98;
            font-weight: 800;
            letter-spacing: -1.8px;
            margin: 46px 0 16px;
            position: relative;
            z-index: 1;
        }
        .hero-subtitle {
            color: rgba(255,255,255,.70);
            max-width: 560px;
            line-height: 1.75;
            font-size: 15px;
            position: relative;
            z-index: 1;
        }
        .feature-grid {
            margin-top: 34px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
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
            min-height: 620px;
            background: rgba(255,255,255,.97);
            border-radius: 34px;
            padding: 30px;
            box-shadow: 0 34px 95px rgba(0,0,0,.36);
            border: 1px solid rgba(255,255,255,.70);
            backdrop-filter: blur(18px);
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .top-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #5b21b6;
            background: #f5f3ff;
            border: 1px solid #ddd6fe;
            border-radius: 999px;
            padding: 7px 11px;
            font-size: 12px;
            font-weight: 800;
            margin-bottom: 18px;
        }
        .login-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 31px;
            line-height: 1.05;
            font-weight: 800;
            letter-spacing: -.9px;
            margin: 0 0 6px;
        }
        .login-subtitle {
            color: var(--muted);
            font-size: 13.5px;
            line-height: 1.55;
            margin-bottom: 24px;
        }
        .form-floating { margin-bottom: 14px; }
        .form-floating .form-control {
            height: 58px;
            border-radius: 16px;
            border: 1.5px solid var(--border);
            padding: 17px 18px;
            font-size: 14.5px;
            background: #fff;
        }
        .form-floating .form-control:focus {
            border-color: var(--purple);
            box-shadow: 0 0 0 4px rgba(139,92,246,.12);
        }
        .form-floating label {
            color: #94a3b8;
            padding: 17px 18px;
            font-size: 13.5px;
        }
        .otp-info {
            background: linear-gradient(135deg, #f5f3ff, #fff7ed);
            border: 1px solid #ddd6fe;
            color: #4c1d95;
            border-radius: 18px;
            padding: 14px;
            font-size: 13px;
            line-height: 1.55;
            margin-bottom: 14px;
        }
        .btn-login {
            width: 100%;
            min-height: 56px;
            border-radius: 17px;
            border: 0;
            background: linear-gradient(135deg, var(--purple), var(--purple2));
            color: #fff;
            font-size: 15px;
            font-weight: 800;
            box-shadow: 0 14px 28px rgba(139,92,246,.32);
            transition: .18s ease;
            margin-top: 8px;
        }
        .btn-login:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: 0 18px 34px rgba(139,92,246,.42);
        }
        .btn-login:disabled { opacity: .72; transform: none; }
        .footer-link {
            text-align: center;
            margin-top: 22px;
        }
        .footer-link a {
            color: var(--muted);
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
        }
        .security-note {
            margin-top: 18px;
            padding: 13px;
            border-radius: 16px;
            background: #f8fafc;
            border: 1px solid var(--border);
            display: flex;
            gap: 10px;
            color: var(--muted);
            font-size: 12.5px;
            line-height: 1.45;
        }
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 999999;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        @media(max-width: 992px) {
            .login-wrap { grid-template-columns: 1fr; max-width: 520px; }
            .hero-panel { display: none; }
            .login-card { min-height: auto; }
        }
        @media(max-width: 576px) {
            .page-shell { padding: 16px; }
            .login-card { padding: 24px 20px; border-radius: 28px; }
            .toast-container { left: 16px; right: 16px; }
        }
    </style>
</head>
<body>
    <div id="toastContainer" class="toast-container"></div>
    <div class="orb one"></div>
    <div class="orb two"></div>

    <main class="page-shell">
        <div class="login-wrap">
            <section class="hero-panel">
                <div class="brand-mark">
                    <div class="brand-icon"><i class="bi bi-shield-lock-fill"></i></div>
                    <div>
                        <div class="brand-name">GymXBook</div>
                        <div style="color:rgba(255,255,255,.55);font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;">Super Admin</div>
                    </div>
                </div>
                <h1 class="hero-title">Secure control center for your platform.</h1>
                <p class="hero-subtitle">Manage gyms, payments, app updates, maintenance mode, staff security and operational settings from one protected console.</p>
                <div class="feature-grid">
                    <div class="feature-card"><i class="bi bi-building-check"></i><strong>Gym oversight</strong><span>Monitor active gyms and subscriptions.</span></div>
                    <div class="feature-card"><i class="bi bi-credit-card-2-front"></i><strong>Gateway control</strong><span>Manage payments and callbacks.</span></div>
                    <div class="feature-card"><i class="bi bi-tools"></i><strong>Maintenance mode</strong><span>Schedule smooth downtime windows.</span></div>
                    <div class="feature-card"><i class="bi bi-whatsapp"></i><strong>WhatsApp 2FA</strong><span>Protect admin login with OTP.</span></div>
                </div>
            </section>

            <section class="login-card">
                <div>
                    <div class="top-badge"><i class="bi bi-patch-check-fill"></i> Protected admin access</div>
                    <h2 class="login-title" id="loginTitle">Welcome back</h2>
                    <p class="login-subtitle" id="loginSubtitle">Sign in to manage the GymXBook platform. WhatsApp OTP will be requested if 2FA is enabled.</p>

                    <form id="adminLoginForm" method="POST" action="{{ route('admin.login') }}" novalidate>
                        @csrf
                        <div class="form-floating">
                            <input type="email" name="email" id="email" class="form-control" placeholder="admin@gymxbook.com" value="{{ old('email') }}" required autofocus>
                            <label for="email"><i class="bi bi-envelope me-2"></i>Email address</label>
                        </div>

                        <div id="passwordStep">
                            <div class="form-floating">
                                <input type="password" name="password" id="password" class="form-control" placeholder="Password" required>
                                <label for="password"><i class="bi bi-lock me-2"></i>Password</label>
                            </div>
                        </div>

                        <div id="otpStep" style="display:none;">
                            <div class="otp-info">
                                <strong><i class="bi bi-whatsapp me-1"></i>WhatsApp OTP verification</strong><br>
                                Enter the 6-digit OTP sent to your registered WhatsApp. The code expires in 5 minutes.
                            </div>
                            <div class="form-floating">
                                <input type="text" name="otp" id="otp" class="form-control" placeholder="123456" maxlength="6" inputmode="numeric" autocomplete="one-time-code">
                                <label for="otp"><i class="bi bi-shield-lock me-2"></i>6-digit OTP</label>
                            </div>
                            <button type="button" class="btn btn-link w-100 mt-1" onclick="resetLoginStep()">Use different account</button>
                        </div>

                        <button type="submit" class="btn-login" id="loginBtn">
                            <span id="btnContent"><i class="bi bi-box-arrow-in-right me-2"></i> Sign In</span>
                        </button>
                    </form>

                    <div class="security-note">
                        <i class="bi bi-shield-check" style="color:var(--success);font-size:18px;"></i>
                        <div>For production safety, keep 2FA enabled and never share your Super Admin credentials.</div>
                    </div>

                    <div class="footer-link">
                        <a href="/"><i class="bi bi-arrow-left me-1"></i> Back to website</a>
                    </div>
                </div>
            </section>
        </div>
        <div class="text-center mt-4"><small style="color: rgba(255,255,255,0.35);">Powered by GymXBook</small></div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showToast(message, type = 'error') {
            const container = document.getElementById('toastContainer');
            const isError = type === 'error';
            const bg = isError ? 'linear-gradient(135deg, #ef4444, #b91c1c)' : 'linear-gradient(135deg, #16c784, #0d9c5f)';
            const toast = document.createElement('div');
            toast.style.cssText = 'min-width:300px;max-width:380px;background:' + bg + ';color:white;border-radius:14px;padding:14px 18px;box-shadow:0 15px 35px rgba(0,0,0,0.25);display:flex;align-items:center;gap:10px;font-size:14px;font-weight:600;';
            toast.innerHTML = '<span style="font-size:20px;opacity:.95;">' + (isError ? '✕' : '✓') + '</span><span style="flex:1;">' + message + '</span><span style="cursor:pointer;opacity:.8;font-size:18px;line-height:1;" onclick="this.parentNode.remove()">×</span>';
            container.appendChild(toast);
            setTimeout(function () {
                if (toast.parentNode) {
                    toast.style.transition = 'opacity .25s, transform .25s';
                    toast.style.opacity = '0';
                    toast.style.transform = 'translateX(20px)';
                    setTimeout(function () { if (toast.parentNode) toast.parentNode.removeChild(toast); }, 250);
                }
            }, 5200);
        }

        const loginForm = document.getElementById('adminLoginForm');
        const loginBtn = document.getElementById('loginBtn');
        const btnContent = document.getElementById('btnContent');
        const loginTitle = document.getElementById('loginTitle');
        const loginSubtitle = document.getElementById('loginSubtitle');
        let twoFaMode = false;

        function buttonLabel() {
            return twoFaMode ? 'Verify OTP' : 'Sign In';
        }

        function renderButton(isLoading) {
            if (isLoading) {
                loginBtn.disabled = true;
                btnContent.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span><span>' + (twoFaMode ? 'Verifying...' : 'Signing in...') + '</span>';
            } else {
                loginBtn.disabled = false;
                btnContent.innerHTML = '<i class="bi bi-box-arrow-in-right me-2"></i> ' + buttonLabel();
            }
        }

        function setLoading(isLoading) {
            renderButton(isLoading);
        }

        loginForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            setLoading(true);

            const formData = new FormData(this);
            const endpoint = twoFaMode ? '{{ route("admin.2fa.verify") }}' : '{{ route("admin.login") }}';
            if (twoFaMode) {
                formData.delete('email');
                formData.delete('password');
            }

            try {
                const res = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: formData
                });
                const data = await res.json();

                if (res.ok && data.success && data.requires_2fa) {
                    twoFaMode = true;
                    document.getElementById('passwordStep').style.display = 'none';
                    document.getElementById('otpStep').style.display = 'block';
                    document.getElementById('email').readOnly = true;
                    loginTitle.textContent = 'Verify OTP';
                    loginSubtitle.textContent = 'A WhatsApp verification code was sent to your registered WhatsApp.';
                    document.getElementById('otp').focus();
                    setLoading(false);
                    showToast(data.message || 'OTP sent to WhatsApp.', 'success');
                } else if (res.ok && data.success) {
                    window.location.href = data.redirect || '/admin';
                } else {
                    setLoading(false);
                    showToast(data.error || (twoFaMode ? 'Invalid OTP.' : 'Invalid email or password.'), 'error');
                }
            } catch (error) {
                console.error(error);
                setLoading(false);
                showToast('Network error. Please check your internet connection.', 'error');
            }
        });

        function resetLoginStep() {
            twoFaMode = false;
            document.getElementById('passwordStep').style.display = 'block';
            document.getElementById('otpStep').style.display = 'none';
            document.getElementById('email').readOnly = false;
            document.getElementById('otp').value = '';
            loginTitle.textContent = 'Welcome back';
            loginSubtitle.textContent = 'Sign in to manage the GymXBook platform. WhatsApp OTP will be requested if 2FA is enabled.';
            setLoading(false);
            document.getElementById('password').focus();
        }

        document.addEventListener('DOMContentLoaded', function() {
            const emailInput = document.getElementById('email');
            if (emailInput) emailInput.focus();
            renderButton(false);
        });
    </script>
</body>
</html>
