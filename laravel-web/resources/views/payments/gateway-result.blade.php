<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>{{ $title ?? 'Payment Status' }} - GymXBook</title>
    <style>
        :root{--brand:#ff6b2c;--brand2:#ff8a3d;--success:#16a34a;--danger:#dc2626;--warning:#d97706;--text:#0f172a;--muted:#64748b;--card:#ffffff;--border:#e5e7eb;}
        *{box-sizing:border-box}body{margin:0;min-height:100vh;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;background:radial-gradient(circle at 20% 0%,rgba(255,107,44,.26),transparent 34%),linear-gradient(135deg,#0f172a 0%,#1e1b4b 58%,#7c2d12 100%);color:var(--text);padding:22px;display:grid;place-items:center}.wrap{width:100%;max-width:470px}.brand{display:flex;justify-content:center;margin-bottom:16px}.logo{width:70px;height:70px;border-radius:24px;background:linear-gradient(135deg,var(--brand2),var(--brand));display:grid;place-items:center;color:#fff;font-weight:900;font-size:28px;box-shadow:0 18px 40px rgba(255,107,44,.34)}.card{background:rgba(255,255,255,.96);border:1px solid rgba(255,255,255,.7);border-radius:30px;padding:28px;box-shadow:0 30px 90px rgba(0,0,0,.34);text-align:center;backdrop-filter:blur(18px)}.icon{width:86px;height:86px;border-radius:999px;display:grid;place-items:center;margin:0 auto 18px;font-size:44px;background:{{ !empty($success) ? '#dcfce7' : (($status ?? '') === 'pending' ? '#fef3c7' : '#fee2e2') }};color:{{ !empty($success) ? '#16a34a' : (($status ?? '') === 'pending' ? '#d97706' : '#dc2626') }};box-shadow:0 12px 32px rgba(15,23,42,.08)}h1{font-size:27px;line-height:1.1;margin:0 0 8px;font-weight:900;letter-spacing:-.5px}.msg{color:var(--muted);font-size:14px;line-height:1.6;margin:0 auto 18px;max-width:360px}.summary{border:1px solid var(--border);border-radius:20px;overflow:hidden;text-align:left;margin:18px 0;background:#f8fafc}.row{display:flex;justify-content:space-between;gap:14px;padding:12px 14px;border-bottom:1px solid var(--border);font-size:13px}.row:last-child{border-bottom:0}.row span:first-child{color:var(--muted);font-weight:700}.row span:last-child{font-weight:900;text-align:right}.actions{display:grid;gap:10px;margin-top:18px}.btn{border:0;border-radius:17px;padding:15px 18px;font-size:15px;font-weight:900;text-decoration:none;display:flex;align-items:center;justify-content:center;gap:8px;cursor:pointer}.btn-primary{background:linear-gradient(135deg,var(--brand2),var(--brand));color:#fff;box-shadow:0 14px 28px rgba(255,107,44,.28)}.btn-secondary{background:#f1f5f9;color:#334155}.hint{margin-top:16px;color:#64748b;font-size:12px;line-height:1.55}.steps{display:flex;gap:7px;justify-content:center;margin:16px 0 2px}.dot{width:8px;height:8px;border-radius:99px;background:#cbd5e1}.dot.active{background:var(--brand);width:22px}.fallback{display:none;margin-top:12px;padding:12px;border-radius:14px;background:#fff7ed;color:#9a3412;font-size:12px;font-weight:700;line-height:1.45}.small{font-size:11px;color:#94a3b8;margin-top:14px;word-break:break-all}@media(max-width:480px){body{padding:14px}.card{padding:24px 18px;border-radius:26px}h1{font-size:24px}.logo{width:62px;height:62px;border-radius:20px}}
    </style>
</head>
<body>
    @php
        $deepLink = $deepLink ?? 'gymxbook://subscription/result';
        $intentLink = $intentLink ?? 'intent://subscription/result#Intent;scheme=gymxbook;package=com.gymxbook.app;end';
        $status = $status ?? (!empty($success) ? 'paid' : 'failed');
    @endphp
    <div class="wrap">
        <div class="brand"><div class="logo">GXB</div></div>
        <div class="card">
            <div class="icon">{{ !empty($success) ? '✓' : (($status ?? '') === 'pending' ? '…' : '!') }}</div>
            <h1>{{ $title ?? 'Payment Status' }}</h1>
            <p class="msg">{{ $message ?? 'You can return to the GymXBook app.' }}</p>
            <div class="steps"><span class="dot active"></span><span class="dot active"></span><span class="dot {{ !empty($success) ? 'active' : '' }}"></span></div>

            <div class="summary">
                @if(!empty($gateway))<div class="row"><span>Gateway</span><span>{{ $gateway }}</span></div>@endif
                @if(!empty($orderId))<div class="row"><span>Order ID</span><span>{{ $orderId }}</span></div>@endif
                @if(isset($amount))<div class="row"><span>Amount</span><span>₹{{ number_format((float)$amount, 2) }}</span></div>@endif
                @if(!empty($statusText))<div class="row"><span>Status</span><span>{{ $statusText }}</span></div>@endif
                @if(!empty($newExpiry))<div class="row"><span>Valid Till</span><span>{{ $newExpiry }}</span></div>@endif
            </div>

            <div class="actions">
                <button type="button" class="btn btn-primary" onclick="openGymXBookApp()">Open GymXBook App</button>
                <button type="button" class="btn btn-secondary" onclick="window.location.reload()">Refresh Status</button>
            </div>

            <div class="fallback" id="fallbackBox">If the app does not open automatically, open GymXBook manually and tap <b>Subscription → Check Now</b>. Your payment status will update there.</div>
            <div class="hint">You can safely close this browser after returning to the app.</div>
            @if(!empty($orderId))<div class="small">Reference: {{ $orderId }}</div>@endif
        </div>
    </div>

    <script>
        const deepLink = @json($deepLink);
        const intentLink = @json($intentLink);
        function isAndroid(){ return /Android/i.test(navigator.userAgent || ''); }
        function openGymXBookApp(){
            document.getElementById('fallbackBox').style.display = 'block';
            window.location.href = isAndroid() ? intentLink : deepLink;
        }
        setTimeout(function(){
            // Soft prompt only; do not force redirect before user reads status.
            document.getElementById('fallbackBox').style.display = 'block';
        }, 1800);
    </script>
</body>
</html>
