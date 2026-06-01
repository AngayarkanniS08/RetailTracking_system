FROM php:8.2-apache

# Install PostgreSQL client dev libraries and other dependencies
RUN apt-get update && apt-get install -y \
    libpq-dev \
    git \
    unzip \
    && docker-php-ext-install pdo pdo_pgsql pgsql

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Allow .htaccess overrides
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Create sessions directory and set permissions
RUN mkdir -p /var/www/html/tmp/sessions && \
    chown -R www-data:www-data /var/www/html/tmp && \
    chmod 755 /var/www/html/tmp/sessions
    
# Set working directory
WORKDIR /var/www/html

# Copy entrypoint script
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Set entrypoint
ENTRYPOINT ["/entrypoint.sh"]

