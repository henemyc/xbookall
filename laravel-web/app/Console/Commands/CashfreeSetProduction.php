<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CashfreeSetProduction extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cashfree:production 
                            {--id= : Your production Cashfree App ID}
                            {--secret= : Your production Cashfree Secret Key}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Switch Cashfree to Production mode and set live credentials';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $envPath = base_path('.env');

        if (!File::exists($envPath)) {
            $this->error('.env file not found!');
            return Command::FAILURE;
        }

        $appId  = $this->option('id');
        $secret = $this->option('secret');

        if (!$appId || !$secret) {
            $this->error('Production mode requires both --id and --secret.');
            $this->line('');
            $this->info('Example:');
            $this->line('  php artisan cashfree:production --id=1234567890abcdef --secret=cfsk_ma_prod_xxxx');
            return Command::FAILURE;
        }

        $this->warn('⚠️  SWITCHING TO PRODUCTION MODE');
        $this->warn('   This will use REAL money!');

        if (!$this->confirm('Are you sure you want to switch to production?', false)) {
            $this->info('Cancelled.');
            return Command::SUCCESS;
        }

        $content = File::get($envPath);

        $content = $this->setEnvValue($content, 'CASHFREE_SANDBOX', 'false');
        $content = $this->setEnvValue($content, 'CASHFREE_APP_ID', $appId);
        $content = $this->setEnvValue($content, 'CASHFREE_SECRET_KEY', $secret);
        $content = $this->setEnvValue($content, 'CASHFREE_API_VERSION', '2023-08-01');

        File::put($envPath, $content);

        $this->info('✅ Cashfree switched to PRODUCTION mode');
        $this->line("   APP_ID   : {$appId}");
        $this->line("   SANDBOX  : false");

        $this->call('config:clear');

        $this->newLine();
        $this->info('Remember to update CASHFREE_RETURN_URL in production.');

        return Command::SUCCESS;
    }

    private function setEnvValue(string $content, string $key, string $value): string
    {
        $pattern = "/^{$key}=.*/m";
        $replacement = "{$key}={$value}";

        if (preg_match($pattern, $content)) {
            return preg_replace($pattern, $replacement, $content);
        }

        if (!str_ends_with($content, "\n")) {
            $content .= "\n";
        }
        return $content . $replacement . "\n";
    }
}
