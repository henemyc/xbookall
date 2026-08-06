<?php

/**
 * Standalone script to set Cashfree Sandbox credentials
 * 
 * Usage:
 *   php scripts/set-cashfree-sandbox.php
 *   php scripts/set-cashfree-sandbox.php --id=YOUR_APP_ID --secret=YOUR_SECRET
 */

$basePath = dirname(__DIR__);
$envFile = $basePath . '/.env';

if (!file_exists($envFile)) {
    echo "❌ .env file not found at: $envFile\n";
    exit(1);
}

// Parse arguments
$options = getopt('', ['id::', 'secret::', 'help']);

if (isset($options['help'])) {
    echo <<<HELP
Cashfree Sandbox Credentials Setter

Usage:
  php scripts/set-cashfree-sandbox.php
  php scripts/set-cashfree-sandbox.php --id=TEST_XXXX --secret=cfsk_ma_test_XXXX

Options:
  --id       Cashfree Sandbox App ID
  --secret   Cashfree Sandbox Secret Key

HELP;
    exit(0);
}

$defaultAppId  = 'TEST11110086697be295b81eb8db7ef068001111';
$defaultSecret = 'cfsk_ma_test_3185487ef4ace00eb1d1f0d1f43baeba_9c490924';

$appId  = $options['id']    ?? $defaultAppId;
$secret = $options['secret'] ?? $defaultSecret;

echo "🔧 Setting Cashfree to SANDBOX mode...\n";

// Read .env
$content = file_get_contents($envFile);

// Helper to set or replace key
function setEnvValue(string $content, string $key, string $value): string
{
    $escaped = preg_quote($key, '/');
    $pattern = "/^{$escaped}=.*/m";
    
    if (preg_match($pattern, $content)) {
        return preg_replace($pattern, "{$key}={$value}", $content);
    }
    
    // Append
    if (!str_ends_with($content, "\n")) {
        $content .= "\n";
    }
    return $content . "{$key}={$value}\n";
}

// Apply values
$content = setEnvValue($content, 'CASHFREE_SANDBOX', 'true');
$content = setEnvValue($content, 'CASHFREE_APP_ID', $appId);
$content = setEnvValue($content, 'CASHFREE_SECRET_KEY', $secret);
$content = setEnvValue($content, 'CASHFREE_API_VERSION', '2023-08-01');

// Write back
file_put_contents($envFile, $content);

echo "✅ Successfully updated .env\n\n";
echo "Cashfree Configuration:\n";
echo "  SANDBOX     : true\n";
echo "  APP_ID      : {$appId}\n";
echo "  SECRET      : " . substr($secret, 0, 20) . "...\n";
echo "  API_VERSION : 2023-08-01\n\n";
echo "⚠️  These are SANDBOX / TEST credentials.\n";
echo "   Use real credentials only in production.\n\n";

echo "Next steps:\n";
echo "  php artisan config:clear\n";
echo "  php artisan cache:clear\n";
echo "  (Optional) php artisan cashfree:sandbox\n";
