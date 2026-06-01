#!/bin/bash
set -e

# Create session directory (volume may hide build-time directory)
mkdir -p /var/www/html/tmp/sessions
chown -R www-data:www-data /var/www/html/tmp
chmod 755 /var/www/html/tmp/sessions

# Run composer install if vendor directory doesn't exist
if [ ! -d "vendor" ]; then
    echo "Vendor directory not found. Running composer install..."
    composer install --no-interaction --optimize-autoloader
else
    echo "Vendor directory found."
fi

# Run database migrations
echo "Running database migrations..."
php Database/Migrate.php

# Start Apache in foreground
echo "Starting web server..."
exec apache2-foreground