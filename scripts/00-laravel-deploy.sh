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
php artisan migrate --force || echo "Database migration failed. Continuing deployment..."

# Seed database if there are no products
echo "Checking if database needs seeding..."
if php artisan tinker --execute="try { echo App\Models\Product::count(); } catch (\Exception \$e) { echo -1; }" | grep -q "^0$"; then
    echo "Database is empty. Seeding..."
    php artisan db:seed --force
else
    echo "Database already has data or connection failed. Skipping seed."
fi
