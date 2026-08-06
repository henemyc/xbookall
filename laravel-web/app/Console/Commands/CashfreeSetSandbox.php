<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CashfreeSetSandbox extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cashfree:sandbox 
                            {--id= : Cashfree App ID (sandbox)}
                            {--secret= : Cashfree Secret Key (sandbox)}
                            {--force : Force overwrite even if production keys are present}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set Cashfree to Sandbox mode and optionally apply test credentials';

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

        $this->info('Configuring Cashfree for Sandbox...');

        $content = File::get($envPath);

        // Sandbox credentials (official Cashfree test values)
        $sandboxAppId = $this->option('id') ?: 'TEST11110086697be295b81eb8db7ef068001111';
        $sandboxSecret = $this->option('secret') ?: 'cfsk_ma_test_3185487ef4ace00eb1d1f0d1f43baeba_9c490924';

        // Update or add CASHFREE_SANDBOX
        $content = $this->setEnvValue($content, 'CASHFREE_SANDBOX', 'true');

        // Update or add credentials
        $content = $this->setEnvValue($content, 'CASHFREE_APP_ID', $sandboxAppId);
        $content = $this->setEnvValue($content, 'CASHFREE_SECRET_KEY', $sandboxSecret);

        // Ensure API version
        $content = $this->setEnvValue($content, 'CASHFREE_API_VERSION', '2023-08-01');

        // Write back to .env
        File::put($envPath, $content);

        $this->info('✅ Cashfree set to SANDBOX mode');
        $this->line("   APP_ID:    {$sandboxAppId}");
        $this->line("   SECRET:    " . substr($sandboxSecret, 0, 25) . '...');
        $this->line("   SANDBOX:   true");

        // Clear config cache
        $this->call('config:clear');

        $this->newLine();
        $this->warn('⚠️  Remember: These are TEST credentials. Never use in production!');
        $this->info('Test payment link: https://sandbox.cashfree.com');

        return Command::SUCCESS;
    }

    /**
     * Set or update an environment variable in the .env content
     */
    private function setEnvValue(string $content, string $key, string $value): string
    {
        $pattern = "/^{$key}=.*/m";
        $replacement = "{$key}={$value}";

        if (preg_match($pattern, $content)) {
            // Replace existing
            return preg_replace($pattern, $replacement, $content);
        } else {
            // Append if not exists
            if (!str_ends_with($content, "\n")) {
                $content .= "\n";
            }
            return $content . $replacement . "\n";
        }
    }
}
