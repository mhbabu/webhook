FROM php:8.3-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    supervisor \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip

# Install Redis extension
RUN pecl install redis \
    && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html/webhook

# Copy Laravel code
COPY . .

# Ensure proper permissions for Laravel storage & cache
RUN mkdir -p storage bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Copy full supervisord configuration (main config file)
COPY docker/supervisor/supervisord.conf /etc/supervisor/supervisord.conf

# Expose ports
EXPOSE 9000 8080

# Start Supervisor (which will manage php-fpm, reverb, queue)
CMD ["supervisord", "-c", "/etc/supervisor/supervisord.conf", "-n"]

