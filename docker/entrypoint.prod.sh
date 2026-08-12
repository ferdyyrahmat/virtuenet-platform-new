#!/bin/sh
set -e

# Wait for DB
if [ -n "$DB_HOST" ]; then
    echo "Waiting for production database connection ($DB_HOST)..."
    until php -r 'try { new PDO("pgsql:host=".getenv("DB_HOST").";port=".getenv("DB_PORT").";dbname=".getenv("DB_DATABASE"), getenv("DB_USERNAME"), getenv("DB_PASSWORD")); echo "Connected"; } catch (Throwable $e) { exit(1); }'; do
        sleep 2
    done
    echo "Production Database connected successfully!"
fi

# Run Production Optimization Commands
echo "Optimizing Laravel for Production..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

if [ "${RUN_APP_SETUP:-false}" = "true" ]; then
    echo "Running Production Database Migrations..."
    php artisan migrate --force

    if [ ! -d "/app/public/storage" ]; then
        php artisan storage:link || true
    fi
fi

exec "$@"
