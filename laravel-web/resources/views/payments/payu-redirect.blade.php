<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redirecting to PayU - GymXBook</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&family=Space+Grotesk:wght@600;700;800&display=swap" rel="stylesheet">
    <style>
        body{margin:0;min-height:100vh;display:grid;place-items:center;background:linear-gradient(135deg,#0f172a,#1e1b4b);font-family:Poppins,sans-serif;color:#fff;padding:24px}.card{max-width:420px;width:100%;background:rgba(255,255,255,.10);border:1px solid rgba(255,255,255,.15);border-radius:28px;padding:32px;text-align:center;box-shadow:0 28px 70px rgba(0,0,0,.35);backdrop-filter:blur(18px)}.logo{width:72px;height:72px;border-radius:22px;background:linear-gradient(135deg,#ff8a3d,#ff6b2c);display:grid;place-items:center;margin:0 auto 18px;font-size:32px}.title{font-family:'Space Grotesk';font-size:25px;font-weight:800;margin-bottom:8px}.muted{opacity:.7;font-size:13px;line-height:1.6}.spinner{width:38px;height:38px;border:4px solid rgba(255,255,255,.2);border-top-color:#ff8a3d;border-radius:50%;animation:spin .8s linear infinite;margin:22px auto}@keyframes spin{to{transform:rotate(360deg)}}button{border:0;border-radius:14px;background:linear-gradient(135deg,#ff8a3d,#ff6b2c);color:#fff;font-weight:800;padding:13px 20px;width:100%;margin-top:16px;cursor:pointer}
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">₹</div>
        <div class="title">Redirecting to PayU</div>
        <div class="muted">Please wait while we open the secure PayU checkout for your GymXBook subscription.</div>
        <div class="spinner"></div>
        <form id="payuForm" method="POST" action="{{ $action }}">
            @foreach($fields as $key => $value)
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endforeach
            <button type="submit">Continue to PayU</button>
        </form>
    </div>
    <script>
        setTimeout(function(){ document.getElementById('payuForm').submit(); }, 700);
    </script>
</body>
</html>
