#!/bin/sh
set -e

# Ensure permissions again at runtime
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Start Apache
exec apache2-foreground