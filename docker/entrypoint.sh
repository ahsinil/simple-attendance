#!/bin/sh
set -e

echo "🚀 Starting Attendance System..."

# ─── Wait for MySQL to be ready ──────────────────────────────
echo "⏳ Waiting for database..."
until php -r "try { new PDO('mysql:host='.getenv('DB_HOST').';port='.getenv('DB_PORT'), getenv('DB_USERNAME'), getenv('DB_PASSWORD')); exit(0); } catch (Exception \$e) { exit(1); }" 2>/dev/null; do
    echo "   ...database not ready yet, retrying in 2s"
    sleep 2
done
echo "✅ Database is ready!"

# ─── Ensure storage directories exist ────────────────────────
mkdir -p /var/www/html/storage/framework/{sessions,views,cache}
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/bootstrap/cache
mkdir -p /var/log/supervisor

# ─── Set permissions ─────────────────────────────────────────
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# ─── Run migrations ──────────────────────────────────────────
echo "📦 Running migrations..."
php artisan migrate --force

# ─── Seed only if the users table is empty ───────────────────
USER_COUNT=$(php artisan tinker --no-interaction --execute="echo \App\Models\User::count();" 2>/dev/null | tail -1 || echo "1")
if [ "$USER_COUNT" = "0" ]; then
    echo "🌱 Seeding database with initial data..."
    php artisan db:seed --force
else
    echo "ℹ️  Database already has data, skipping seed."
fi

# ─── Cache config, routes, views ─────────────────────────────
echo "⚡ Caching configuration..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# ─── Create storage symlink ──────────────────────────────────
php artisan storage:link 2>/dev/null || true

echo "✅ Attendance System is ready!"

exec "$@"
