<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GymXBook Maintenance</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --brand:#ff6b35; --amber:#ffc043; --dark:#111827; --muted:#64748b; --green:#16c784; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: Poppins, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: #111827;
            background:
                radial-gradient(circle at 18% 20%, rgba(255,107,53,.22), transparent 28%),
                radial-gradient(circle at 88% 10%, rgba(124,58,237,.20), transparent 30%),
                linear-gradient(135deg, #fff7ed 0%, #f8fafc 45%, #eef2ff 100%);
            display: grid;
            place-items: center;
            padding: 24px;
        }
        .card {
            width: min(760px, 100%);
            background: rgba(255,255,255,.82);
            border: 1px solid rgba(255,255,255,.72);
            border-radius: 34px;
            box-shadow: 0 26px 70px rgba(17,24,39,.16);
            overflow: hidden;
            backdrop-filter: blur(18px);
        }
        .hero {
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #111827 0%, #1f2937 52%, #7c2d12 100%);
            color: #fff;
            padding: 34px;
        }
        .hero::after {
            content: '';
            position: absolute;
            width: 240px; height: 240px;
            right: -80px; top: -80px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255,255,255,.22), transparent 64%);
        }
        .brand { display:flex; gap:14px; align-items:center; position:relative; z-index:1; }
        .logo {
            width: 58px; height: 58px; border-radius: 20px;
            background: linear-gradient(135deg, var(--brand), var(--amber));
            display:grid; place-items:center; font-size: 30px;
            box-shadow: 0 14px 28px rgba(255,107,53,.32);
        }
        h1 { margin: 22px 0 8px; font-family: "Space Grotesk", sans-serif; font-size: clamp(30px, 5vw, 48px); line-height: 1.02; letter-spacing: -1px; position:relative; z-index:1; }
        .msg { margin: 0; color: rgba(255,255,255,.72); max-width: 610px; line-height: 1.62; position:relative; z-index:1; }
        .body { padding: 28px 34px 34px; }
        .timer-card {
            border-radius: 26px;
            padding: 22px;
            background: #fff;
            border: 1px solid #e5e7eb;
            box-shadow: 0 10px 30px rgba(17,24,39,.08);
        }
        .label { font-size: 12px; color: var(--muted); font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
        .timer { display:flex; gap:12px; margin-top: 14px; flex-wrap: wrap; }
        .unit {
            flex: 1; min-width: 112px;
            padding: 16px 12px;
            border-radius: 20px;
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            text-align:center;
        }
        .num { font-family: "Space Grotesk", sans-serif; font-size: 38px; font-weight: 800; color: var(--dark); line-height: 1; }
        .txt { margin-top: 6px; font-size: 12px; color: var(--muted); font-weight: 700; }
        .meta { display:grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 18px; }
        .meta > div { padding: 13px 14px; border-radius: 16px; background:#f8fafc; border:1px solid #e5e7eb; }
        .meta strong { display:block; margin-top: 4px; font-size: 13px; }
        .actions { margin-top: 22px; display:flex; gap:12px; flex-wrap:wrap; align-items:center; }
        .btn {
            border:0; border-radius: 16px; padding: 14px 18px; cursor:pointer;
            font-weight: 800; font-family:Poppins, sans-serif;
            background: linear-gradient(135deg, var(--brand), var(--amber)); color:#fff;
            box-shadow: 0 14px 24px rgba(255,107,53,.24);
        }
        .live { color: var(--green); font-weight: 800; display:none; }
        .sub { color: var(--muted); font-size: 13px; line-height: 1.5; }
        @media(max-width: 640px) { .hero,.body { padding: 24px; } .meta { grid-template-columns: 1fr; } .unit { min-width: 92px; } .num { font-size: 31px; } }
    </style>
</head>
<body>
@php
    $endIso = $maintenance['end_at'] ?? null;
    $startIso = $maintenance['start_at'] ?? null;
@endphp
<div class="card">
    <div class="hero">
        <div class="brand">
            <div class="logo">🏋️</div>
            <div>
                <div style="font-family:'Space Grotesk';font-weight:800;font-size:22px;">GymXBook</div>
                <div style="opacity:.62;font-size:12px;">Platform maintenance</div>
            </div>
        </div>
        <h1>{{ $maintenance['title'] ?? 'GymXBook is under maintenance' }}</h1>
        <p class="msg">{{ $maintenance['message'] ?? 'We are upgrading GymXBook to serve you better. Please wait until maintenance is complete.' }}</p>
    </div>
    <div class="body">
        <div class="timer-card">
            <div class="label" id="timerLabel">Estimated time remaining</div>
            <div class="timer" id="timer">
                <div class="unit"><div class="num" id="hours">00</div><div class="txt">Hours</div></div>
                <div class="unit"><div class="num" id="minutes">00</div><div class="txt">Minutes</div></div>
                <div class="unit"><div class="num" id="seconds">00</div><div class="txt">Seconds</div></div>
            </div>
            <div class="live" id="liveNow">✅ We are live now. Please refresh to continue.</div>
            <div class="meta">
                <div><span class="sub">Started</span><strong>{{ $startIso ? \Carbon\Carbon::parse($startIso)->timezone('Asia/Kolkata')->format('d M Y, h:i A') : 'Now' }}</strong></div>
                <div><span class="sub">Expected back</span><strong>{{ $endIso ? \Carbon\Carbon::parse($endIso)->timezone('Asia/Kolkata')->format('d M Y, h:i A') : 'Soon' }}</strong></div>
            </div>
            <div class="actions">
                <button class="btn" onclick="location.reload()">Refresh Status</button>
                <span class="sub">This page updates automatically when the timer completes.</span>
            </div>
        </div>
    </div>
</div>
<script>
    const endAt = @json($endIso);
    const live = document.getElementById('liveNow');
    const timer = document.getElementById('timer');
    const label = document.getElementById('timerLabel');
    const h = document.getElementById('hours');
    const m = document.getElementById('minutes');
    const s = document.getElementById('seconds');
    let reloaded = false;

    function pad(v) { return String(v).padStart(2, '0'); }
    function tick() {
        if (!endAt) return;
        const remaining = Math.max(0, new Date(endAt).getTime() - Date.now());
        const total = Math.floor(remaining / 1000);
        h.textContent = pad(Math.floor(total / 3600));
        m.textContent = pad(Math.floor((total % 3600) / 60));
        s.textContent = pad(total % 60);
        if (total <= 0) {
            timer.style.display = 'none';
            label.textContent = 'Maintenance completed';
            live.style.display = 'block';
            if (!reloaded) {
                reloaded = true;
                setTimeout(() => location.reload(), 3500);
            }
        }
    }
    tick();
    setInterval(tick, 1000);
</script>
</body>
</html>
