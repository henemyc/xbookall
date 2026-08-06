#!/bin/bash
# Quick helper script to set Cashfree sandbox credentials

cd "$(dirname "$0")/.."

echo "🔄 Setting Cashfree to SANDBOX mode..."

# Default official sandbox credentials
DEFAULT_APP_ID="TEST11110086697be295b81eb8db7ef068001111"
DEFAULT_SECRET="cfsk_ma_test_3185487ef4ace00eb1d1f0d1f43baeba_9c490924"

APP_ID="${1:-$DEFAULT_APP_ID}"
SECRET="${2:-$DEFAULT_SECRET}"

# Update .env using sed (cross-platform friendly)
sed -i.bak \
  -e "s/^CASHFREE_SANDBOX=.*/CASHFREE_SANDBOX=true/" \
  -e "s/^CASHFREE_APP_ID=.*/CASHFREE_APP_ID=$APP_ID/" \
  -e "s/^CASHFREE_SECRET_KEY=.*/CASHFREE_SECRET_KEY=$SECRET/" \
  -e "s/^CASHFREE_API_VERSION=.*/CASHFREE_API_VERSION=2023-08-01/" \
  .env

# Clean up backup
rm -f .env.bak

echo "✅ Sandbox credentials applied to .env"
echo ""
echo "CASHFREE_SANDBOX=true"
echo "CASHFREE_APP_ID=$APP_ID"
echo "CASHFREE_SECRET_KEY=${SECRET:0:25}..."
echo ""

# Clear Laravel caches
php artisan config:clear 2>/dev/null || echo "⚠️  Run: php artisan config:clear"

echo "Done. You can now test payments using sandbox mode."
