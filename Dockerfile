# Use PHP 8.2 FPM as base image
FROM php:8.2-fpm

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    git curl zip unzip libzip-dev libpng-dev libonig-dev libxml2-dev \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Install Composer from official Composer image
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory to your Laravel app folder
WORKDIR /var/www/html/webhook

# Copy Laravel project files to container
COPY --chown=www-data:www-data . /var/www/html/webhook

# Optional: suppress Git safe directory warnings (if Git is used)
RUN git config --global --add safe.directory /var/www/html/webhook

# Set permissions for Laravel storage and cache directories
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Install PHP dependencies for production
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

