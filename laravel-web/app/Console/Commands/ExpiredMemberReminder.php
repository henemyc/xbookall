<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\TraineeDetail;
use App\Models\WhatsAppLog;
use App\Services\WhatsAppService;
use Illuminate\Console\Command;

class ExpiredMemberReminder extends Command
{
    protected $signature = 'whatsapp:expired-reminder';
    protected $description = 'Send WhatsApp reminders to expired members (Day 1, 3, 7)';

    public function handle(): int
    {
        $this->info('Starting expired member reminders...');

        $whatsapp = new WhatsAppService();

        if (!$whatsapp->isConfigured()) {
            $this->error('WhatsApp not configured. Check .env settings.');
            return Command::FAILURE;
        }

        // Find members expired 1, 3, or 7 days ago
        $expiredMembers = TraineeDetail::whereNotNull('membership_expiry_date')
            ->whereRaw('DATEDIFF(CURDATE(), membership_expiry_date) IN (1, 3, 7)')
            ->whereHas('user', function ($q) {
                $q->where('is_active', true)
                  ->where('type', 'trainee')
                  ->whereNotNull('phone_number')
                  ->where('phone_number', '!=', '');
            })
            ->with('user')
            ->limit(100)
            ->get();

        $sent = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($expiredMembers as $detail) {
            $user = $detail->user;
            if (!$user) continue;

            $phone = $user->phone_number;
            $daysExpired = (int) now()->diffInDays($detail->membership_expiry_date, false);

            try {
                // Check if already sent today
                $alreadySent = WhatsAppLog::where('to_number', 'like', '%' . substr($phone, -10))
                    ->where('template_name', 'member_expired')
                    ->whereDate('created_at', today())
                    ->exists();

                if ($alreadySent) {
                    $skipped++;
                    continue;
                }

                // Check max 3 total sends
                $totalSent = WhatsAppLog::where('to_number', 'like', '%' . substr($phone, -10))
                    ->where('template_name', 'member_expired')
                    ->count();

                if ($totalSent >= 3) {
                    $skipped++;
                    continue;
                }

                $result = $whatsapp->sendMemberExpired(
                    $phone,
                    $user->name,
                    $detail->membership_expiry_date->format('Y-m-d'),
                    $user->parent_id
                );

                if ($result['success']) {
                    $sent++;
                    $this->line("  ✓ Sent to {$user->name} ({$phone})");
                } else {
                    $failed++;
                    $this->warn("  ✗ Failed: {$user->name} - " . ($result['error'] ?? 'Unknown'));
                }

                // Rate limit
                usleep(500000); // 0.5 seconds

            } catch (\Exception $e) {
                $failed++;
                $this->error("  ✗ Error: {$user->name} - " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info("Completed! Sent: {$sent}, Failed: {$failed}, Skipped: {$skipped}");

        return Command::SUCCESS;
    }
}
