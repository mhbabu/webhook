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

# ✅ COPY CUSTOM PHP CONFIG (THIS IS THE FIX)
#COPY docker/php/custom.ini /usr/local/etc/php/conf.d/custom.ini

# Use production php.ini as the main php.ini
RUN mv /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini

# Verify the change (optional)
RUN php -i | grep "Loaded Configuration File"

# Update PHP settings directly
RUN sed -i \
    -e 's/memory_limit = .*/memory_limit = 1024M/' \
    -e 's/upload_max_filesize = .*/upload_max_filesize = 64M/' \
    -e 's/post_max_size = .*/post_max_size = 64M/' \
    -e 's/max_execution_time = .*/max_execution_time = 120/' \
    /usr/local/etc/php/php.ini


# Ensure FPM includes conf.d/*.ini
RUN echo "include=/usr/local/etc/php/conf.d/*.ini" >> /usr/local/etc/php-fpm.conf

# Set working directory
WORKDIR /var/www/html/webhook

# Copy Supervisor configuration
COPY docker/supervisor/supervisord.conf /etc/supervisor/supervisord.conf

# Ensure proper permissions for Laravel storage & cache
RUN mkdir -p storage bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# ✅ Create supervisor log & tmp directories (fixes your issue)
RUN mkdir -p /var/log/supervisor /tmp \
    && chmod -R 777 /var/log/supervisor /tmp

# Expose ports
EXPOSE 9000 8080

# Start Supervisor
CMD ["supervisord", "-c", "/etc/supervisor/supervisord.conf", "-n"]
