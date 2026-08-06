@extends('panel.layouts.app')

@section('title', 'Attendance QR Code')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="table-card text-center">
            <h5 class="mb-4">
                <i class="bi bi-qr-code me-2" style="color: var(--primary);"></i>
                Attendance QR Code
            </h5>
            
            <p class="text-muted mb-4">Members scan this QR code to mark their attendance at your gym entrance.</p>
            
            <!-- QR Code Display -->
            <div class="mb-4" style="background: white; padding: 24px; border-radius: 16px; display: inline-block; box-shadow: 0 4px 12px rgba(0,0,0,0.08);">
                <div id="qrcode"></div>
            </div>
            
            <!-- Gym Name -->
            <h6 class="mb-2">{{ $gymName }}</h6>
            <p class="text-muted" style="font-size: 13px;">Scan to mark attendance</p>
            
            <!-- Download Button -->
            <div class="mt-4">
                <button class="btn btn-primary btn-lg w-100" onclick="downloadQR()">
                    <i class="bi bi-download me-2"></i> Download QR Code (PNG)
                </button>
            </div>
            
            <!-- Print Button -->
            <div class="mt-3">
                <button class="btn btn-outline-secondary w-100" onclick="window.print()">
                    <i class="bi bi-printer me-2"></i> Print QR Code
                </button>
            </div>
            
            <!-- Instructions -->
            <div class="mt-4 p-3 rounded" style="background: var(--bg);">
                <h6 class="mb-2"><i class="bi bi-info-circle me-2"></i> Instructions</h6>
                <ul class="text-start text-muted" style="font-size: 13px;">
                    <li class="mb-1">Print this QR code and place it at your gym entrance</li>
                    <li class="mb-1">Members open GymXBook app and scan this code</li>
                    <li class="mb-1">Attendance is automatically marked on scan</li>
                    <li class="mb-1">Second scan = Check-out</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- QR Code Library -->
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
    // Generate QR Code
    var qrcode = new QRCode(document.getElementById("qrcode"), {
        text: "{{ $qrData }}",
        width: 256,
        height: 256,
        colorDark : "#1a1a2e",
        colorLight : "#ffffff",
        correctLevel : QRCode.CorrectLevel.H
    });
    
    // Download QR as PNG
    function downloadQR() {
        var canvas = document.querySelector('#qrcode canvas');
        var link = document.createElement('a');
        link.download = '{{ Str::slug($gymName) }}-attendance-qr.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
    }
</script>

<style>
    @media print {
        .sidebar, .top-bar, .btn, .toast-container { display: none !important; }
        .main-content { margin: 0 !important; padding: 20px !important; }
        .table-card { box-shadow: none !important; border: none !important; }
    }
</style>
@endsection
