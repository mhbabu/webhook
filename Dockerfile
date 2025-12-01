# Dockerfile
FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    git curl zip unzip \
    libzip-dev libpng-dev libonig-dev libxml2-dev \
    supervisor \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo_mysql mbstring bcmath pcntl gd zip

RUN pecl install redis && docker-php-ext-enable redis

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html/webhook

# Copy Supervisor configs
COPY docker/supervisor/supervisord.conf /etc/supervisor/supervisord.conf
COPY docker/supervisor/*.conf /etc/supervisor/conf.d/

# Permission Fix
RUN mkdir -p storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

# Create Supervisor log directories
RUN mkdir -p /var/log/supervisor /tmp \
    && chmod -R 777 /var/log/supervisor /tmp

CMD ["supervisord", "-c", "/etc/supervisor/supervisord.conf", "-n"]
