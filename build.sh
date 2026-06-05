#!/usr/bin/env bash
# Exit on error
set -o errexit

# Install PHP dependencies (no dev)
composer install --no-dev --optimize-autoloader

# Generate app key if not set
php artisan key:generate --force

# Clear and cache config for production
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run database migrations
php artisan migrate --force

# Create storage symlink
php artisan storage:link
