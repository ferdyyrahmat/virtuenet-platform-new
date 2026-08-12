#!/bin/sh
set -e

# Wait for DB if DB_HOST is set
if [ -n "$DB_HOST" ]; then
    echo "Waiting for database connection ($DB_HOST)..."
    until php -r 'try { new PDO("mysql:host=".getenv("DB_HOST").";port=".getenv("DB_PORT"), getenv("DB_USERNAME"), getenv("DB_PASSWORD")); echo "Connected"; } catch (Throwable $e) { exit(1); }'; do
        sleep 2
    done
    echo "Database connected successfully!"
fi

# Ensure storage & bootstrap permissions
chmod -R 777 /var/www/html/storage /var/www/html/bootstrap/cache

# Generate key if empty
if [ -z "$APP_KEY" ]; then
    echo "Generating Application Key..."
    php artisan key:generate
fi

if [ "${RUN_APP_SETUP:-false}" = "true" ]; then
    if [ ! -d "/var/www/html/public/storage" ]; then
        php artisan storage:link || true
    fi

    echo "Running Database Migrations..."
    php artisan migrate --force
fi

# Execute main CMD (php-fpm)
exec "$@"
