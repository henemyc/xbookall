<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\BaseController;
use App\Models\Setting;
use Illuminate\Http\Request;

class PanelQRController extends BaseController
{
    /**
     * Show attendance QR code
     */
    public function index()
    {
        $pid = $this->getParentId();
        $parentIds = $this->getGymParentIds();
        $user = \App\Models\User::find($pid) ?: auth()->user();

        // Get gym name. For staff, $pid is the gym owner id, so never show the
        // staff user's name on the QR poster.
        $gymName = Setting::getValue('company_name', $pid, $user->name);

        // Get or create QR secret
        $qrSecret = Setting::getValue('attendance_qr_secret', $pid);
        if (!$qrSecret) {
            $qrSecret = bin2hex(random_bytes(16));
            Setting::setValue('attendance_qr_secret', $qrSecret, $pid);
        }

        // FIX #1: QR data is just the secret
        $qrData = $qrSecret;

        return view('panel.qr.index', compact('gymName', 'qrData', 'qrSecret'));
    }
}
