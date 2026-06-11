#!/usr/bin/env bash

set -e

# Create storage symlink if it doesn't exist
echo "Creating storage symlink..."
php artisan storage:link || true

# Clear any stale cached config (config:cache bakes in env values at build time which can be wrong)
echo "Clearing stale caches..."
php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear || true

# Run database migrations (non-blocking — if DB is not ready, app still starts)
echo "Running database migrations..."
if php artisan migrate --force; then
    echo "Migrations completed successfully."

    # Seed database if there are no products
    echo "Checking if database needs seeding..."
    PRODUCT_COUNT=$(php artisan tinker --execute="try { echo App\Models\Product::count(); } catch (\Exception \$e) { echo -1; }" 2>/dev/null | tail -1)
    if [ "$PRODUCT_COUNT" = "0" ]; then
        echo "Database is empty. Seeding..."
        php artisan db:seed --force || echo "Seeding failed. Continuing..."
    else
        echo "Database already has data (count=$PRODUCT_COUNT). Skipping seed."
    fi
else
    echo "WARNING: Database migration failed. App will start but DB features may not work."
fi

echo "Deploy script completed."
