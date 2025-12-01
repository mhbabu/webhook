FROM php:8.3-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git curl zip unzip libzip-dev libpng-dev libonig-dev libxml2-dev supervisor \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring bcmath pcntl gd zip

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html/webhook

# Copy Supervisor configuration
COPY docker/supervisor/supervisord.conf /etc/supervisor/supervisord.conf
COPY docker/supervisor/*.conf /etc/supervisor/conf.d/

# Ensure proper permissions for Laravel
RUN mkdir -p src/storage src/bootstrap/cache \
    && chown -R www-data:www-data src/storage src/bootstrap/cache \
    && chmod -R 775 src/storage src/bootstrap/cache

# Expose ports (9000 PHP-FPM, 8080 Reverb WebSocket)
EXPOSE 9000 8080

# Start Supervisor
CMD ["supervisord", "-c", "/etc/supervisor/supervisord.conf", "-n"]
