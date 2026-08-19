#!/bin/bash
set -e

# Create session directory (volume may hide build-time directory)
mkdir -p /var/www/html/tmp/sessions
# Attempt to fix ownership; ignore failure (Docker Desktop VM sharing may block chown)
chown -R www-data:www-data /var/www/html/tmp 2>/dev/null || true
# Ensure the sessions directory is writable
chmod 777 /var/www/html/tmp/sessions 2>/dev/null || true

# Securely decode Google Drive credentials if passed via ENV
if [ -n "$GOOGLE_CLIENT_BASE64" ]; then
    echo "$GOOGLE_CLIENT_BASE64" | base64 -d > /var/www/html/config/google_client.json
fi
if [ -n "$GDRIVE_TOKENS_BASE64" ]; then
    echo "$GDRIVE_TOKENS_BASE64" | base64 -d > /var/www/html/config/gdrive_tokens.json
fi


# Run composer install if vendor directory doesn't exist
if [ ! -d "src/vendor" ]; then
    echo "Vendor directory not found in src. Running composer install..."
    cd src && composer install --no-interaction --optimize-autoloader && cd ..
else
    echo "Vendor directory found in src."
fi

# Run database migrations
echo "Running database migrations..."
if ! php Database/Migrate.php; then
    echo "ERROR: Migration failed."
    exit 1
fi

# Start Apache in foreground
echo "Starting web server..."
exec apache2-foreground