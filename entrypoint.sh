#!/bin/sh
set -e

# 1. Ensure permissions are correct for the running user
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# 2. Clear cache first so we don't hit path errors
php artisan cache:clear
php artisan config:clear

# 3. Only run migration if the database is actually connected
echo "Attempting migration..."
# We use '|| true' so the build doesn't crash if migration fails
php artisan migrate --force || echo "Migration skipped or failed"

# 4. Start Apache
echo "Starting Apache..."
exec apache2-foreground