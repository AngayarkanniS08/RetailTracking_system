#!/bin/bash
set -e

# Create session directory (volume may hide build-time directory)
mkdir -p /var/www/html/tmp/sessions
chown -R www-data:www-data /var/www/html/tmp
chmod 755 /var/www/html/tmp/sessions

# Run composer install if vendor directory doesn't exist
if [ ! -d "src/vendor" ]; then
    echo "Vendor directory not found in src. Running composer install..."
    cd src && composer install --no-interaction --optimize-autoloader && cd ..
else
    echo "Vendor directory found in src."
fi

# Run database migrations
echo "Running database migrations..."
php Database/Migrate.php

# Start Apache in foreground
echo "Starting web server..."
exec apache2-foreground