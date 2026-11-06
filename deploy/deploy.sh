#!/usr/bin/env bash
# Update the live server to the latest code. Run as the deploy user from /var/www/qronex.
set -euo pipefail
cd /var/www/qronex
php artisan down --retry=30 || true
git pull --ff-only
composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
php artisan up
php artisan qronex:preflight || echo "⚠ Preflight reported problems — check above."
