<?php

namespace App\Http\Controllers;

use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class WhatsAppController extends BaseController
{
    private WhatsAppService $whatsapp;

    public function __construct()
    {
        $this->whatsapp = new WhatsAppService();
    }

    /**
     * Send WhatsApp message (supports templates from old api.php flow)
     */
    public function send(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string|max:20',
            'template' => 'nullable|string',
            'member_name' => 'nullable|string',
            'gym_name' => 'nullable|string',
            'expiry' => 'nullable|string',
            'amount' => 'nullable|numeric',
            'message' => 'nullable|string',
        ]);

        $phone = $request->phone;
        $template = $request->template ?? 'custom';
        $memberName = $request->member_name ?? 'Member';
        $gymName = $request->gym_name ?? 'GymXBook';
        $expiry = $request->expiry ?? now()->addMonth()->format('d-m-Y');
        $amount = $request->amount ?? 0;

        $pid = $this->getParentId();

        $result = match($template) {
            'member_welcome' => $this->whatsapp->sendMemberWelcome($phone, $memberName, $gymName, $expiry, $pid),
            'member_renew' => $this->whatsapp->sendMemberRenew($phone, $memberName, $gymName, $expiry, $amount, $pid),
            'member_expired' => $this->whatsapp->sendMemberExpired($phone, $memberName, $expiry, $pid),
            'payment_confirmation' => $this->whatsapp->sendPaymentConfirmation($phone, $memberName, 0, $amount, $gymName, $pid),
            'otp' => $this->whatsapp->sendOtp($phone, $request->message ?? '', $pid),
            'gymxbook_otp' => $this->whatsapp->sendOtp($phone, $request->message ?? '', $pid),
            default => $this->whatsapp->sendTemplate(
                $phone, 
                'custom', 
                'en', 
                [$request->message ?? 'Hello from GymXBook'], 
                $pid
            ),
        };

        if ($result['success'] ?? false) {
            return $this->success([
                'message' => 'WhatsApp sent successfully',
                'template' => $template,
                'to' => $result['to'] ?? $phone,
                'message_id' => $result['message_id'] ?? null,
            ]);
        }

        return $this->error($result['error'] ?? 'Failed to send WhatsApp message', 400);
    }

    public function logs(Request $request): JsonResponse
    {
        $parentIds = $this->getGymParentIds();
        $pid = $this->getParentId();
        $limit = min(100, (int) $request->get('limit', 20));

        $logs = \App\Models\WhatsAppLog::whereIn('parent_id', $parentIds)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        $monthlyStats = $this->whatsapp->getMonthlyStats($pid);
        $totalThisMonth = $this->whatsapp->getTotalCount($pid, now()->format('Y-m'));

        return $this->success([
            'logs' => $logs,
            'count' => $logs->count(),
            'monthly_stats' => $monthlyStats,
            'total_this_month' => $totalThisMonth,
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $pid = $this->getParentId();
        $month = $request->get('month', now()->format('Y-m'));

        $stats = $this->whatsapp->getMonthlyStats($pid, $month);
        $total = $this->whatsapp->getTotalCount($pid, $month);
        $totalAll = $this->whatsapp->getTotalCount($pid);

        return $this->success([
            'month' => $month,
            'total_sent_this_month' => $total,
            'total_sent_all_time' => $totalAll,
            'stats' => $stats,
        ]);
    }

    public function test(Request $request): JsonResponse
    {
        return $this->success([
            'configured' => $this->whatsapp->isConfigured(),
            'config' => $this->whatsapp->getConfig(),
            'supported_templates' => [
                'member_welcome',
                'member_renew',
                'member_expired',
                'payment_confirmation',
                'otp',
                'gymxbook_otp'
            ],
        ]);
    }

    /**
     * Send OTP via WhatsApp (used by AuthController)
     */
    public function sendOtp(string $phone, string $otp, int $parentId = 0): array
    {
        return $this->whatsapp->sendOtp($phone, $otp, $parentId);
    }
}
