#!/bin/sh
set -e

echo "🚀 Starting Attendance System..."

# Wait for database
echo "⏳ Waiting for database..."
while ! mysqladmin ping -h"$DB_HOST" --silent; do
    sleep 1
done
echo "✅ Database is ready!"

# Create storage directories
mkdir -p /var/www/html/storage/framework/{sessions,views,cache}
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/bootstrap/cache

# Set permissions
chown -R www-data:www-data /var/www/html/storage
chown -R www-data:www-data /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage
chmod -R 775 /var/www/html/bootstrap/cache

# Create log directory for supervisor
mkdir -p /var/log/supervisor

# Run migrations
echo "📦 Running migrations..."
php artisan migrate --force

# Check if database is empty and seed
TABLE_COUNT=$(mysql -h"$DB_HOST" -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_DATABASE';" 2>/dev/null || echo "0")
if [ "$TABLE_COUNT" -lt "10" ]; then
    echo "🌱 Seeding database..."
    php artisan db:seed --force
fi

# Cache config
echo "⚡ Caching configuration..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Create storage link
php artisan storage:link 2>/dev/null || true

echo "✅ Attendance System is ready!"

exec "$@"
