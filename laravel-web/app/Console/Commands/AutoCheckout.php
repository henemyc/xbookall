<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AutoCheckout extends Command
{
    protected $signature = 'attendance:auto-checkout';
    protected $description = 'Auto checkout members after 4 hours';

    public function handle(): int
    {
        $this->info('Starting auto checkout...');

        $today = now()->toDateString();

        try {
            // Auto checkout for today (4+ hours from check-in time)
            $todayCount = Attendance::where('date', $today)
                ->whereNull('checked_out_time')
                ->whereRaw('TIMESTAMPDIFF(HOUR, CONCAT(date, " ", checked_in_time), NOW()) >= 4')
                ->update([
                    'checked_out_time' => DB::raw('ADDTIME(checked_in_time, "04:00:00")'),
                    'notes' => DB::raw('CONCAT(COALESCE(notes, ""), " | Auto checkout after 4h")'),
                    'status' => 2,
                ]);

            // Auto checkout for previous days (any open records)
            $previousCount = Attendance::where('date', '<', $today)
                ->whereNull('checked_out_time')
                ->update([
                    'checked_out_time' => DB::raw('ADDTIME(checked_in_time, "04:00:00")'),
                    'notes' => DB::raw('CONCAT(COALESCE(notes, ""), " | Auto checkout (missed)")'),
                    'status' => 2,
                ]);

            $this->info("Completed! Today: {$todayCount}, Previous: {$previousCount} auto checked out.");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
