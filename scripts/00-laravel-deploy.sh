#!/usr/bin/env bash
set -e

echo "===== CraySafe Laravel deployment ====="

# 1. Clear any stale config cache
echo "Clearing config cache..."
php artisan config:clear || true
php artisan cache:clear || true
php artisan route:clear || true
php artisan view:clear || true

# 2. Create required storage directories
mkdir -p storage/framework/cache/data
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/logs
mkdir -p storage/app/firebase
mkdir -p bootstrap/cache
chmod -R 777 storage bootstrap/cache || true

# 3. Install dependencies (in case vendor is missing)
if [ ! -d "vendor" ]; then
    echo "Installing composer dependencies..."
    composer install --no-dev --optimize-autoloader --no-interaction
fi

# 4. Rebuild cache with the CURRENT environment variables
echo "Rebuilding config cache..."
php artisan config:cache

echo "===== Deployment complete ====="