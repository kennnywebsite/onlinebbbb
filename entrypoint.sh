#!/bin/sh
set -e

# Run migrations and cache
echo "Running migrations..."
php artisan migrate --force
php artisan config:cache
php artisan route:cache

# Start Apache
echo "Starting Apache..."
exec apache2-foreground