#!/usr/bin/env bash

# Create storage symlink if it doesn't exist
echo "Creating storage symlink..."
php artisan storage:link

# Clear and cache config/route/views for production performance
echo "Caching configurations..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run database migrations
echo "Running database migrations..."
php artisan migrate --force
