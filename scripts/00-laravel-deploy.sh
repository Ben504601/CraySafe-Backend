#!/usr/bin/env bash
set -e

echo "===== CraySafe Laravel deployment ====="

# ─────────────────────────────────────────────────
# 1. Create storage directories
# ─────────────────────────────────────────────────
mkdir -p storage/app/firebase
mkdir -p storage/framework/cache/data
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/logs
mkdir -p bootstrap/cache

# ─────────────────────────────────────────────────
# 2. Copy Render Secret Files to readable locations
# ─────────────────────────────────────────────────
echo "Copying secret files..."

if [ -f /etc/secrets/service-account.json ]; then
    cp /etc/secrets/service-account.json storage/app/firebase/service-account.json
    chmod 644 storage/app/firebase/service-account.json
    echo "✅ service-account.json copied"
else
    echo "⚠️  /etc/secrets/service-account.json not found"
fi

if [ -f /etc/secrets/ca.pem ]; then
    mkdir -p storage/app/certs
    cp /etc/secrets/ca.pem storage/app/certs/ca.pem
    chmod 644 storage/app/certs/ca.pem
    echo "✅ ca.pem copied"
fi

# ─────────────────────────────────────────────────
# 3. Ensure storage and cache are writable
# ─────────────────────────────────────────────────
chmod -R 777 storage bootstrap/cache || true

# ─────────────────────────────────────────────────
# 4. Clear any stale config cache
# ─────────────────────────────────────────────────
echo "Clearing config cache..."
php artisan config:clear || true
php artisan cache:clear || true
php artisan route:clear || true
php artisan view:clear || true

# ─────────────────────────────────────────────────
# 5. Install dependencies if vendor is missing
# ─────────────────────────────────────────────────
if [ ! -d "vendor" ]; then
    echo "Installing composer dependencies..."
    composer install --no-dev --optimize-autoloader --no-interaction
fi

# ─────────────────────────────────────────────────
# 6. Rebuild config cache
# ─────────────────────────────────────────────────
echo "Rebuilding config cache..."
php artisan config:cache

echo "===== Deployment complete ====="