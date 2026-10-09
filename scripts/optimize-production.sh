#!/usr/bin/env bash
# Run on cPanel after git pull to maximize Laravel performance.
set -euo pipefail

cd "$(dirname "$0")/.."

echo "==> Clearing stale caches..."
php artisan route:clear
php artisan config:clear
php artisan view:clear
php artisan cache:clear

echo "==> Running migrations (indexes)..."
php artisan migrate --force

echo "==> Rebuilding caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Optimizing autoloader..."
composer install --no-dev --optimize-autoloader --no-interaction 2>/dev/null || true

echo "Done. If Redis is enabled in .env, start queue worker:"
echo "  php artisan queue:work redis --queue=pdf-imports,default --timeout=330 --tries=3"
