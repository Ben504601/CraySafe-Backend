#!/usr/bin/env bash
set -e

echo "Starting Laravel deployment..."

# Create storage directories
mkdir -p storage/framework/cache/data
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/logs
mkdir -p storage/app/firebase
mkdir -p bootstrap/cache

# Set writable permissions
chmod -R 775 storage bootstrap/cache

# Install composer dependencies
echo "Installing composer dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

# Cache Laravel config and routes
echo "Caching config and routes..."
php artisan config:cache
php artisan route:cache

echo "Laravel deployment complete."