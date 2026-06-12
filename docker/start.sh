#!/bin/bash
set -e

echo "=== Starting Laravel E-Commerce API ==="

# ── 1. Generate APP_KEY if not set ──────────────────────────────────────────
if [ -z "$APP_KEY" ]; then
    echo "[start.sh] APP_KEY not set – generating..."
    php artisan key:generate --force
else
    echo "[start.sh] APP_KEY is set."
fi

# ── 2. Create a minimal .env so artisan doesn't complain ────────────────────
# On Render, all config comes from env vars injected at runtime.
# We only need a stub .env so Laravel bootstraps correctly.
if [ ! -f /var/www/html/.env ]; then
    echo "[start.sh] No .env found – creating stub from environment..."
    touch /var/www/html/.env
fi

# ── 3. Clear stale cache from build time ────────────────────────────────────
echo "[start.sh] Clearing stale cached config..."
php artisan config:clear  || true
php artisan route:clear   || true
php artisan view:clear    || true

# ── 4. Cache config/routes/views with RUNTIME env vars ──────────────────────
echo "[start.sh] Caching config with runtime env vars..."
php artisan config:cache  || echo "WARNING: config:cache failed – continuing without cache"
php artisan route:cache   || echo "WARNING: route:cache failed – continuing"
php artisan view:cache    || echo "WARNING: view:cache failed – continuing"

# ── 5. Create storage symlink ────────────────────────────────────────────────
echo "[start.sh] Creating storage symlink..."
php artisan storage:link  || true

# ── 6. Run database migrations ───────────────────────────────────────────────
echo "[start.sh] Running database migrations..."
if php artisan migrate --force; then
    echo "[start.sh] Migrations completed."

    # Seed only if no products exist
    PRODUCT_COUNT=$(php artisan tinker --execute="try { echo App\Models\Product::count(); } catch (\Exception \$e) { echo -1; }" 2>/dev/null | tail -1)
    if [ "$PRODUCT_COUNT" = "0" ]; then
        echo "[start.sh] Database is empty – seeding..."
        php artisan db:seed --force || echo "WARNING: Seeding failed – continuing"
    else
        echo "[start.sh] Database already has data (count=$PRODUCT_COUNT). Skipping seed."
    fi
else
    echo "WARNING: Migrations failed. App will start but DB features may not work."
fi

# ── 7. Fix permissions ───────────────────────────────────────────────────────
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

# ── 8. Start PHP-FPM in background ───────────────────────────────────────────
echo "[start.sh] Starting PHP-FPM..."
php-fpm -D

# ── 9. Start Nginx in foreground (keeps container alive) ────────────────────
echo "[start.sh] Starting Nginx on port 8080..."
exec nginx -g "daemon off;"
